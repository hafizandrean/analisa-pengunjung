import pytest
from httpx import AsyncClient, ASGITransport
from unittest.mock import patch, MagicMock
import numpy as np

from app.main import app
from app.services.onvif_discovery import ONVIFWSDiscovery
from app.schemas.camera import DeviceValidationRequest


def test_probe_xml_generation():
    """Memverifikasi payload SOAP XML Probe WS-Discovery sesuai standar OASIS & ONVIF."""
    xml_str = ONVIFWSDiscovery.build_probe_xml(message_id="urn:uuid:test-probe-1234", filter_types=True)
    assert "urn:uuid:test-probe-1234" in xml_str
    assert "http://schemas.xmlsoap.org/ws/2005/04/discovery/Probe" in xml_str
    assert "dn:NetworkVideoTransmitter" in xml_str
    assert "239.255.255.250" not in xml_str  # Multicast IP ada di level transport UDP


def test_security_rejection_public_ips():
    """Memverifikasi bahwa pemindaian dibatasi ketat hanya pada rentang alamat IP privat (RFC 1918)."""
    # IP Publik yang harus DITOLAK
    assert ONVIFWSDiscovery.is_authorized_local_ip("8.8.8.8") is False
    assert ONVIFWSDiscovery.is_authorized_local_ip("1.1.1.1") is False
    assert ONVIFWSDiscovery.is_authorized_local_ip("142.250.190.46") is False
    assert ONVIFWSDiscovery.is_authorized_local_ip("93.184.216.34") is False

    # IP Privat / Lokal yang DIIZINKAN
    assert ONVIFWSDiscovery.is_authorized_local_ip("192.168.1.100") is True
    assert ONVIFWSDiscovery.is_authorized_local_ip("10.0.0.15") is True
    assert ONVIFWSDiscovery.is_authorized_local_ip("172.16.50.2") is True
    assert ONVIFWSDiscovery.is_authorized_local_ip("127.0.0.1") is True
    assert ONVIFWSDiscovery.is_authorized_local_ip("localhost") is True


def test_parse_probematches_xml():
    """Memverifikasi ekstraksi data dari paket respons SOAP ProbeMatches."""
    sample_xml = """<?xml version="1.0" encoding="utf-8"?>
    <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://www.w3.org/2003/05/soap-envelope"
                       xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/08/addressing"
                       xmlns:d="http://schemas.xmlsoap.org/ws/2005/04/discovery"
                       xmlns:dn="http://www.onvif.org/ver10/network/wsdl">
      <SOAP-ENV:Header>
        <wsa:RelatesTo>urn:uuid:test-probe-1234</wsa:RelatesTo>
        <wsa:Action>http://schemas.xmlsoap.org/ws/2005/04/discovery/ProbeMatches</wsa:Action>
      </SOAP-ENV:Header>
      <SOAP-ENV:Body>
        <d:ProbeMatches>
          <d:ProbeMatch>
            <wsa:EndpointReference>
              <wsa:Address>urn:uuid:abcdef01-2345-6789-abcd-ef0123456789</wsa:Address>
            </wsa:EndpointReference>
            <d:Types>dn:NetworkVideoTransmitter tds:Device</d:Types>
            <d:Scopes>
              onvif://www.onvif.org/type/video_encoder
              onvif://www.onvif.org/name/Hikvision_Camera_Dining
              onvif://www.onvif.org/hardware/DS-2CD2043G0-I
              onvif://www.onvif.org/location/Area_Meja_Makan
            </d:Scopes>
            <d:XAddrs>http://192.168.1.120:80/onvif/device_service</d:XAddrs>
            <d:MetadataVersion>1</d:MetadataVersion>
          </d:ProbeMatch>
        </d:ProbeMatches>
      </SOAP-ENV:Body>
    </SOAP-ENV:Envelope>"""

    devices = ONVIFWSDiscovery.parse_probe_matches(sample_xml, "192.168.1.120")
    assert len(devices) == 1
    dev = devices[0]
    assert dev.ip_address == "192.168.1.120"
    assert dev.port == 80
    assert "Hikvision" in dev.name or "DS-2CD2043G0-I" in dev.name
    assert dev.model == "DS-2CD2043G0-I"
    assert dev.onvif_xaddr == "http://192.168.1.120:80/onvif/device_service"
    assert "Streaming/Channels/101" in dev.suggested_rtsp_uri


@pytest.mark.anyio
async def test_discovery_scan_endpoint_simulated():
    """Menguji endpoint API scan ONVIF dengan parameter simulasi."""
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        res = await ac.post("/api/v1/cameras/discovery/scan?simulate=true")

    assert res.status_code == 200
    data = res.json()
    assert data["success"] is True
    assert data["device_count"] >= 1
    assert len(data["devices"]) >= 1
    dev = data["devices"][0]
    assert "ip_address" in dev
    assert "suggested_rtsp_uri" in dev
    assert "onvif_xaddr" in dev


@pytest.mark.anyio
async def test_discovery_scan_endpoint_real_timeout():
    """Menguji endpoint API scan ONVIF nyata pada timeout singkat (mengecek response schema non-blocking)."""
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        res = await ac.post("/api/v1/cameras/discovery/scan?timeout=1.5")

    assert res.status_code == 200
    data = res.json()
    assert "devices" in data
    assert "device_count" in data
    assert "network_scope" in data
    assert data["duration_seconds"] >= 0.0


@pytest.mark.anyio
async def test_validate_discovered_device_security_rejection():
    """Menguji bahwa validasi menolak IP publik untuk mencegah Server-Side Request Forgery."""
    payload = {
        "ip_address": "8.8.8.8",
        "port": 554,
        "source_type": "RTSP",
        "rtsp_uri": "rtsp://8.8.8.8:554/live",
        "timeout_seconds": 2.0
    }
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        res = await ac.post("/api/v1/cameras/discovery/validate", json=payload)

    assert res.status_code == 200
    data = res.json()
    assert data["is_accessible"] is False
    assert data["connection_status"] == "ERROR"
    assert "RFC 1918" in data["message"]


@pytest.mark.anyio
async def test_validate_discovered_device_mocked_success():
    """Menguji validasi feed frame live dengan mocking OpenCV VideoCapture."""
    fake_frame = np.zeros((720, 1280, 3), dtype=np.uint8)

    mock_cap = MagicMock()
    mock_cap.isOpened.return_value = True
    mock_cap.read.return_value = (True, fake_frame)
    mock_cap.get.return_value = 30.0

    payload = {
        "ip_address": "192.168.1.64",
        "port": 554,
        "source_type": "RTSP",
        "rtsp_uri": "rtsp://192.168.1.64:554/Streaming/Channels/101",
        "username": "admin",
        "password": "Password123",
        "timeout_seconds": 2.0
    }

    with patch("app.services.onvif_discovery.cv2.VideoCapture", return_value=mock_cap):
        async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
            res = await ac.post("/api/v1/cameras/discovery/validate", json=payload)

    assert res.status_code == 200
    data = res.json()
    assert data["is_accessible"] is True
    assert data["connection_status"] == "ONLINE"
    assert data["resolution_width"] == 1280
    assert data["resolution_height"] == 720
    assert data["fps"] == 30
    assert "Verifikasi sukses" in data["message"]
