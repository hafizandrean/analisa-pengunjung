import os
import re
import socket
import struct
import uuid
import time
import ipaddress
import urllib.parse
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from typing import List, Dict, Any, Optional, Tuple

import cv2
import numpy as np

from app.schemas.camera import (
    DiscoveredCCTVDevice,
    DiscoveryResponse,
    DeviceValidationRequest,
    DeviceValidationResponse
)


class ONVIFWSDiscovery:
    """Layanan penemuan perangkat CCTV (IP Camera & NVR/DVR) otomatis menggunakan ONVIF WS-Discovery (OASIS).

    Mekanisme:
    1. Mengirimkan probe SOAP multicast UDP ke alamat 239.255.255.250:3702
    2. Menerima respons unicast ProbeMatches dari perangkat kamera / NVR di jaringan lokal
    3. Mengekstrak alamat IP, port, ONVIF service XAddrs, model, dan scopes
    4. Membatasi pemindaian hanya pada jaringan privat (RFC 1918 / Local Subnet) untuk keamanan
    """

    MULTICAST_GROUP = "239.255.255.250"
    MULTICAST_PORT = 3702

    # Namespace WS-Discovery & ONVIF umum
    NS_SOAP_12 = "http://www.w3.org/2003/05/soap-envelope"
    NS_WSA = "http://schemas.xmlsoap.org/ws/2004/08/addressing"
    NS_DISCOVERY = "http://schemas.xmlsoap.org/ws/2005/04/discovery"
    NS_ONVIF = "http://www.onvif.org/ver10/network/wsdl"

    @classmethod
    def is_authorized_local_ip(cls, ip_str: str) -> bool:
        """Memverifikasi bahwa IP adalah alamat jaringan lokal privat (RFC 1918 / Loopback / Link-Local).

        Mencegah pemindaian atau penyerangan ke alamat IP publik (SSRF Protection).
        """
        clean_ip = ip_str.strip().lower()
        if clean_ip in ["localhost", "127.0.0.1", "::1"]:
            return True
        try:
            ip = ipaddress.ip_address(clean_ip)
            if ip.is_global:
                return False
            return ip.is_private or ip.is_loopback or ip.is_link_local
        except ValueError:
            return False

    @classmethod
    def build_probe_xml(cls, message_id: Optional[str] = None, filter_types: bool = True) -> str:
        """Membuat payload SOAP XML WS-Discovery Probe."""
        msg_uuid = message_id or f"urn:uuid:{uuid.uuid4()}"
        type_tag = "<d:Types>dn:NetworkVideoTransmitter tds:Device</d:Types>" if filter_types else ""
        return (
            '<?xml version="1.0" encoding="utf-8"?>\n'
            '<Envelope xmlns="http://www.w3.org/2003/05/soap-envelope" '
            'xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/08/addressing" '
            'xmlns:d="http://schemas.xmlsoap.org/ws/2005/04/discovery" '
            'xmlns:dn="http://www.onvif.org/ver10/network/wsdl" '
            'xmlns:tds="http://www.onvif.org/ver10/device/wsdl">\n'
            '  <Header>\n'
            f'    <wsa:MessageID>{msg_uuid}</wsa:MessageID>\n'
            '    <wsa:To>urn:schemas-xmlsoap-org:ws:2005:04:discovery</wsa:To>\n'
            '    <wsa:Action>http://schemas.xmlsoap.org/ws/2005/04/discovery/Probe</wsa:Action>\n'
            '  </Header>\n'
            '  <Body>\n'
            '    <d:Probe>\n'
            f'      {type_tag}\n'
            '    </d:Probe>\n'
            '  </Body>\n'
            '</Envelope>'
        )

    @classmethod
    def _strip_tag(cls, tag: str) -> str:
        """Menghapus namespace prefix dari tag XML."""
        return tag.split("}")[-1] if "}" in tag else tag

    @classmethod
    def parse_probe_matches(cls, xml_data: str, sender_ip: str) -> List[DiscoveredCCTVDevice]:
        """Mem-parsing pesan XML ProbeMatches SOAP dan mengekstrak metadata perangkat CCTV."""
        devices: List[DiscoveredCCTVDevice] = []
        try:
            root = ET.fromstring(xml_data)
        except ET.ParseError:
            return devices

        # Cari semua elemen ProbeMatch di seluruh pohon XML
        probe_matches = []
        for elem in root.iter():
            if cls._strip_tag(elem.tag).lower() == "probematch":
                probe_matches.append(elem)

        for match in probe_matches:
            endpoint_ref = ""
            types_list: List[str] = []
            scopes_list: List[str] = []
            xaddrs_list: List[str] = []

            for child in match:
                tag = cls._strip_tag(child.tag).lower()
                if tag == "endpointreference":
                    for sub in child:
                        if cls._strip_tag(sub.tag).lower() == "address":
                            endpoint_ref = (sub.text or "").strip()
                elif tag == "types":
                    if child.text:
                        types_list = [t.strip() for t in child.text.split() if t.strip()]
                elif tag == "scopes":
                    if child.text:
                        scopes_list = [s.strip() for s in child.text.split() if s.strip()]
                elif tag == "xaddrs":
                    if child.text:
                        xaddrs_list = [x.strip() for x in child.text.split() if x.strip()]

            # Ekstrak detail spesifik dari scopes
            device_name = ""
            manufacturer = ""
            model = ""
            location = ""
            capabilities: Dict[str, Any] = {}

            for scope in scopes_list:
                scope_lower = scope.lower()
                decoded_scope = urllib.parse.unquote(scope)

                if "name/" in scope_lower:
                    device_name = decoded_scope.split("name/", 1)[-1].replace("_", " ").strip()
                elif "hardware/" in scope_lower or "model/" in scope_lower:
                    key = "hardware/" if "hardware/" in scope_lower else "model/"
                    model = decoded_scope.split(key, 1)[-1].strip()
                elif "location/" in scope_lower:
                    location = decoded_scope.split("location/", 1)[-1].replace("_", " ").strip()
                elif "manufacturer/" in scope_lower:
                    manufacturer = decoded_scope.split("manufacturer/", 1)[-1].strip()
                elif "profile/" in scope_lower:
                    capabilities.setdefault("profiles", []).append(decoded_scope.split("profile/", 1)[-1])

            # Tentukan alamat IP & Port
            device_ip = sender_ip
            device_port = 80
            primary_xaddr = ""

            if xaddrs_list:
                primary_xaddr = xaddrs_list[0]
                try:
                    parsed_url = urllib.parse.urlparse(primary_xaddr)
                    if parsed_url.hostname:
                        device_ip = parsed_url.hostname
                    if parsed_url.port:
                        device_port = parsed_url.port
                    elif parsed_url.scheme == "https":
                        device_port = 443
                except Exception:
                    pass
            else:
                primary_xaddr = f"http://{device_ip}:80/onvif/device_service"

            # Validasi keamanan alamat IP (harus lokal/privat)
            if not cls.is_authorized_local_ip(device_ip):
                print(f"[ONVIF Discovery] IP perangkat ditolak karena berada di luar jangkauan privat: {device_ip}")
                continue

            # Fallback untuk nama perangkat jika scope tidak menyediakan
            if not manufacturer:
                # Coba deteksi manufacturer dari nama atau model
                for brand in ["Hikvision", "Dahua", "Uniview", "Axis", "Hanwha", "TP-Link", "Bardi", "Ezviz"]:
                    if brand.lower() in (device_name + " " + model).lower():
                        manufacturer = brand
                        break
                if not manufacturer:
                    manufacturer = "ONVIF Compliant Device"

            if not device_name:
                if model:
                    device_name = f"{manufacturer} ({model})"
                else:
                    device_name = f"CCTV Kamera IP ({device_ip})"

            if not location:
                location = "Jaringan Area Restoran (LAN)"

            # Hitung rekomendasi URL RTSP stream
            suggested_rtsp = cls.suggest_rtsp_uri(device_ip, manufacturer, model)

            capabilities["types"] = types_list
            capabilities["xaddrs"] = xaddrs_list

            device_id = endpoint_ref or f"urn:uuid:{uuid.uuid5(uuid.NAMESPACE_DNS, f'{device_ip}:{device_port}')}"

            devices.append(
                DiscoveredCCTVDevice(
                    device_id=device_id,
                    name=device_name,
                    ip_address=device_ip,
                    port=device_port,
                    onvif_xaddr=primary_xaddr,
                    manufacturer=manufacturer,
                    model=model or None,
                    location=location,
                    types=types_list,
                    scopes=scopes_list,
                    suggested_rtsp_uri=suggested_rtsp,
                    capabilities=capabilities
                )
            )

        return devices

    @classmethod
    def suggest_rtsp_uri(
        cls,
        ip: str,
        manufacturer: Optional[str] = None,
        model: Optional[str] = None,
        onvif_port: Optional[int] = None,
    ) -> str:
        """Menghasilkan rekomendasi RTSP Stream URL sesuai konvensi pabrikan kamera CCTV."""
        mfg = (manufacturer or "").lower()
        if "hikvision" in mfg:
            return f"rtsp://{ip}:554/Streaming/Channels/101"
        elif "dahua" in mfg or "imou" in mfg:
            return f"rtsp://{ip}:554/cam/realmonitor?channel=1&subtype=0"
        elif "uniview" in mfg or "unv" in mfg:
            return f"rtsp://{ip}:554/unicast/c1/s0/live"
        elif "axis" in mfg:
            return f"rtsp://{ip}:554/axis-media/media.amp"
        elif "tp-link" in mfg or "tapo" in mfg:
            return f"rtsp://{ip}:554/stream1"
        # Generic / NVR tidak dikenal — coba port 554 dulu (paling umum)
        return f"rtsp://{ip}:554/live/ch0"

    @classmethod
    def get_stream_uri_via_onvif(
        cls,
        onvif_xaddr: str,
        username: Optional[str] = None,
        password: Optional[str] = None,
        timeout: float = 5.0,
    ) -> Optional[str]:
        """Mengambil RTSP stream URI asli dari perangkat ONVIF menggunakan SOAP GetProfiles+GetStreamUri.

        Mengembalikan URL RTSP jika berhasil, None jika gagal.
        """
        import urllib.request
        import base64
        import hashlib

        def _wsse_header(user: str, pwd: str) -> str:
            nonce_bytes = os.urandom(20)
            nonce_b64 = base64.b64encode(nonce_bytes).decode()
            created = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
            digest_raw = nonce_bytes + created.encode() + pwd.encode()
            digest_b64 = base64.b64encode(hashlib.sha1(digest_raw).digest()).decode()
            return (
                '<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" '
                'xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">'
                "<wsse:UsernameToken>"
                f"<wsse:Username>{user}</wsse:Username>"
                f'<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd#PasswordDigest">'
                f"{digest_b64}</wsse:Password>"
                f"<wsse:Nonce>{nonce_b64}</wsse:Nonce>"
                f"<wsu:Created>{created}</wsu:Created>"
                "</wsse:UsernameToken>"
                "</wsse:Security>"
            )

        security_header = ""
        if username and password:
            try:
                security_header = _wsse_header(username, password)
            except Exception:
                pass

        # Tentukan media endpoint (coba beberapa varian path)
        media_endpoints = []
        base = onvif_xaddr.rstrip("/")
        media_endpoints.append(base.replace("device_service", "media"))
        media_endpoints.append(base.replace("Device", "Media"))
        media_endpoints.append(base)  # fallback ke device service

        # Step 1: GetProfiles
        get_profiles_soap = (
            '<?xml version="1.0" encoding="utf-8"?>'
            '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" '
            'xmlns:trt="http://www.onvif.org/ver10/media/wsdl">'
            f"<s:Header>{security_header}</s:Header>"
            "<s:Body><trt:GetProfiles/></s:Body>"
            "</s:Envelope>"
        )

        profile_token: Optional[str] = None
        working_endpoint: Optional[str] = None

        for endpoint in media_endpoints:
            try:
                req = urllib.request.Request(
                    endpoint,
                    data=get_profiles_soap.encode("utf-8"),
                    headers={
                        "Content-Type": "application/soap+xml; charset=utf-8",
                        "User-Agent": "ONVIF-Client/1.0",
                    },
                    method="POST",
                )
                with urllib.request.urlopen(req, timeout=timeout) as resp:
                    body = resp.read().decode("utf-8", errors="ignore")
                    root = ET.fromstring(body)
                    for elem in root.iter():
                        tag = cls._strip_tag(elem.tag).lower()
                        if tag == "profiles":
                            tok = elem.get("token") or elem.get("Token")
                            if tok:
                                profile_token = tok
                                working_endpoint = endpoint
                                break
                    if profile_token:
                        break
            except Exception as e:
                print(f"[ONVIF GetProfiles] Endpoint {endpoint} gagal: {e}")
                continue

        if not profile_token or not working_endpoint:
            print("[ONVIF GetProfiles] Tidak ada ProfileToken ditemukan.")
            return None

        # Step 2: GetStreamUri
        get_stream_soap = (
            '<?xml version="1.0" encoding="utf-8"?>'
            '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" '
            'xmlns:trt="http://www.onvif.org/ver10/media/wsdl" '
            'xmlns:tt="http://www.onvif.org/ver10/schema">'
            f"<s:Header>{security_header}</s:Header>"
            "<s:Body>"
            "<trt:GetStreamUri>"
            "<trt:StreamSetup>"
            "<tt:Stream>RTP-Unicast</tt:Stream>"
            "<tt:Transport><tt:Protocol>RTSP</tt:Protocol></tt:Transport>"
            "</trt:StreamSetup>"
            f"<trt:ProfileToken>{profile_token}</trt:ProfileToken>"
            "</trt:GetStreamUri>"
            "</s:Body>"
            "</s:Envelope>"
        )

        try:
            req2 = urllib.request.Request(
                working_endpoint,
                data=get_stream_soap.encode("utf-8"),
                headers={
                    "Content-Type": "application/soap+xml; charset=utf-8",
                    "User-Agent": "ONVIF-Client/1.0",
                },
                method="POST",
            )
            with urllib.request.urlopen(req2, timeout=timeout) as resp2:
                body2 = resp2.read().decode("utf-8", errors="ignore")
                root2 = ET.fromstring(body2)
                for elem in root2.iter():
                    tag = cls._strip_tag(elem.tag).lower()
                    if tag == "uri":
                        uri = (elem.text or "").strip()
                        if uri.startswith("rtsp://"):
                            print(f"[ONVIF GetStreamUri] URI ditemukan: {uri}")
                            return uri
        except Exception as e:
            print(f"[ONVIF GetStreamUri] Gagal: {e}")

        return None

    @classmethod
    def _try_rtsp_candidates(
        cls,
        ip: str,
        username: Optional[str],
        password: Optional[str],
        manufacturer: Optional[str] = None,
        explicit_uri: Optional[str] = None,
        timeout_s: float = 6.0,
    ) -> Tuple[bool, Optional[str], Optional[cv2.VideoCapture]]:
        """Mencoba sejumlah kandidat RTSP URL dan mengembalikan yang pertama berhasil.

        Mengembalikan (sukses, uri_berhasil, cap_object).
        PENTING: Cap object WAJIB di-release oleh pemanggil setelah selesai.
        """
        def _build_auth_uri(base_uri: str) -> str:
            if username and password and "://" in base_uri and "@" not in base_uri:
                enc_pass = urllib.parse.quote(password, safe="")
                proto, rest = base_uri.split("://", 1)
                return f"{proto}://{username}:{enc_pass}@{rest}"
            return base_uri

        mfg = (manufacturer or "").lower()
        candidates: List[str] = []

        # URI eksplisit dari pengguna / ONVIF GetStreamUri — prioritas tertinggi
        if explicit_uri and explicit_uri.startswith("rtsp://"):
            candidates.append(explicit_uri)

        # Kandidat berdasarkan brand yang sudah diketahui
        if "hikvision" in mfg:
            candidates += [
                f"rtsp://{ip}:554/Streaming/Channels/101",
                f"rtsp://{ip}:554/Streaming/Channels/1",
                f"rtsp://{ip}:554/h264/ch1/main/av_stream",
            ]
        elif "dahua" in mfg or "imou" in mfg:
            candidates += [
                f"rtsp://{ip}:554/cam/realmonitor?channel=1&subtype=0",
                f"rtsp://{ip}:554/live",
            ]
        elif "uniview" in mfg or "unv" in mfg:
            candidates += [f"rtsp://{ip}:554/unicast/c1/s0/live"]
        elif "axis" in mfg:
            candidates += [f"rtsp://{ip}:554/axis-media/media.amp"]
        elif "tp-link" in mfg or "tapo" in mfg:
            candidates += [f"rtsp://{ip}:554/stream1"]
        else:
            # Generic NVR/DVR — coba berbagai port & path umum
            for rtsp_port in [554, 10554, 8554, 1935, 8000]:
                for path in ["/live/ch0", "/stream1", "/live/stream", "/video/main", "/live", "/1", "/cam/realmonitor?channel=1&subtype=0"]:
                    candidates.append(f"rtsp://{ip}:{rtsp_port}{path}")

        # Deduplikasi sambil mempertahankan urutan
        seen: set = set()
        unique_candidates: List[str] = []
        for c in candidates:
            if c not in seen:
                seen.add(c)
                unique_candidates.append(c)

        timeout_us = int(min(timeout_s, 8.0) * 1_000_000)

        for uri in unique_candidates:
            auth_uri = _build_auth_uri(uri)
            cap = None
            try:
                os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = (
                    f"rtsp_transport;tcp|stimeout;{timeout_us}|max_delay;500000"
                )
                cap = cv2.VideoCapture(auth_uri, cv2.CAP_FFMPEG)
                if not cap.isOpened():
                    cap.release()
                    cap = None
                    continue
                ret, frame = cap.read()
                if ret and frame is not None and frame.size > 0:
                    print(f"[RTSP Try] Sukses: {uri}")
                    return True, uri, cap  # Pemanggil wajib melepas cap
                cap.release()
                cap = None
            except Exception as e:
                if cap:
                    try:
                        cap.release()
                    except Exception:
                        pass
                print(f"[RTSP Try] Error {uri}: {e}")

        return False, None, None

    @classmethod
    def discover(cls, timeout: float = 3.0) -> DiscoveryResponse:
        """Melakukan pemindaian ONVIF WS-Discovery secara sinkron menggunakan UDP multicast socket."""
        start_time = time.time()
        discovered_map: Dict[str, DiscoveredCCTVDevice] = {}
        error_msg: Optional[str] = None

        # Timeout dibatasi antara 1.5 detik hingga 8.0 detik
        safe_timeout = max(1.5, min(float(timeout), 8.0))

        sock = None
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM, socket.IPPROTO_UDP)
            sock.settimeout(safe_timeout)

            # Konfigurasi socket options untuk broadcast/multicast
            sock.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)

            # TTL multicast = 2 (cukup untuk jaringan lokal restoran / LAN switch)
            ttl = struct.pack("b", 2)
            sock.setsockopt(socket.IPPROTO_IP, socket.IP_MULTICAST_TTL, ttl)

            # Kirim probe 1: Filter dengan NetworkVideoTransmitter
            probe_xml_1 = cls.build_probe_xml(filter_types=True)
            sock.sendto(probe_xml_1.encode("utf-8"), (cls.MULTICAST_GROUP, cls.MULTICAST_PORT))

            # Kirim probe 2: Generic probe untuk mencakup DVR/NVR non-standar
            probe_xml_2 = cls.build_probe_xml(filter_types=False)
            sock.sendto(probe_xml_2.encode("utf-8"), (cls.MULTICAST_GROUP, cls.MULTICAST_PORT))

            # Loop membaca respons hingga batas waktu timeout
            end_deadline = time.time() + safe_timeout
            while time.time() < end_deadline:
                remaining = max(0.1, end_deadline - time.time())
                sock.settimeout(remaining)
                try:
                    data, addr = sock.recvfrom(65535)
                    xml_str = data.decode("utf-8", errors="ignore")
                    parsed_devices = cls.parse_probe_matches(xml_str, addr[0])
                    for dev in parsed_devices:
                        # Gunakan kombinasi IP dan port sebagai deduplikasi unik
                        key = f"{dev.ip_address}:{dev.port}"
                        if key not in discovered_map:
                            discovered_map[key] = dev
                except (socket.timeout, TimeoutError):
                    break
                except Exception as e:
                    print(f"[ONVIF Discovery] Kesalahan saat membaca paket: {e}")
                    break

        except Exception as ex:
            error_msg = f"Gagal menginisialisasi multicast socket discovery: {str(ex)}"
            print(f"[ONVIF Discovery Error] {error_msg}")
        finally:
            if sock:
                try:
                    sock.close()
                except Exception:
                    pass

        devices = list(discovered_map.values())
        duration = round(time.time() - start_time, 2)

        if devices:
            message = f"Berhasil menemukan {len(devices)} perangkat CCTV ONVIF di jaringan lokal."
        else:
            message = (
                "Tidak ada perangkat CCTV ONVIF yang terdeteksi di jaringan lokal. "
                "Pastikan kamera terhubung ke LAN/Wi-Fi yang sama, protokol ONVIF aktif pada pengaturan kamera, "
                "dan router tidak memblokir UDP multicast port 3702."
            )

        return DiscoveryResponse(
            success=len(devices) > 0 or error_msg is None,
            device_count=len(devices),
            devices=devices,
            duration_seconds=duration,
            message=message,
            network_scope="Jaringan Lokal (UDP Multicast WS-Discovery 239.255.255.250:3702)",
            error_detail=error_msg
        )

    @classmethod
    def validate_device_connection(cls, req: DeviceValidationRequest) -> DeviceValidationResponse:
        """Memverifikasi keterjangkauan kamera yang ditemukan dan mengambil 1 frame nyata.

        Strategi validasi (berurutan):
        1. Coba ONVIF GetStreamUri SOAP ke device untuk mendapat RTSP URL asli.
        2. Jika ONVIF gagal, gunakan URL RTSP yang dimasukkan pengguna.
        3. Jika URL pengguna gagal, coba multi-port/path RTSP fallback (hanya untuk perangkat generik).

        Mengembalikan status ONLINE hanya jika 1 frame video valid berhasil di-decode.
        """
        now = datetime.now(timezone.utc)
        target_ip = req.ip_address.strip()

        # 1. Validasi keamanan IP (hanya izinkan IP privat lokal / loopback)
        if not cls.is_authorized_local_ip(target_ip):
            return DeviceValidationResponse(
                is_accessible=False,
                connection_status="ERROR",
                message="Akses ditolak: Alamat IP berada di luar jangkauan jaringan privat (RFC 1918).",
                error_detail="Validasi keamanan menolak pengujian ke IP publik untuk mencegah SSRF.",
                checked_at=now
            )

        raw_uri = (req.rtsp_uri or "").strip()
        username = req.username or None
        password = req.password or None

        # 2. Coba dapatkan RTSP URI asli dari ONVIF GetStreamUri (jika onvif_xaddr tersedia)
        onvif_rtsp_uri: Optional[str] = None
        if hasattr(req, "onvif_xaddr") and req.onvif_xaddr:
            try:
                print(f"[ValidateDevice] Mencoba ONVIF GetStreamUri dari {req.onvif_xaddr}")
                onvif_rtsp_uri = cls.get_stream_uri_via_onvif(
                    onvif_xaddr=req.onvif_xaddr,
                    username=username,
                    password=password,
                    timeout=5.0,
                )
            except Exception as e:
                print(f"[ValidateDevice] ONVIF GetStreamUri gagal: {e}")

        # Tentukan URI prioritas: ONVIF URI > URI dari pengguna
        explicit_uri = onvif_rtsp_uri or raw_uri

        # 3. Coba koneksi RTSP menggunakan kandidat multi-port/path
        # Untuk merk yang dikenal (mis. Hikvision), hanya coba URL spesifik brand.
        # Untuk perangkat generik/NVT, coba berbagai port.
        manufacturer = getattr(req, "manufacturer", None)

        timeout_s = max(4.0, min(float(req.timeout_seconds), 12.0))

        cap = None
        successful_uri: Optional[str] = None
        try:
            success, successful_uri, cap = cls._try_rtsp_candidates(
                ip=target_ip,
                username=username,
                password=password,
                manufacturer=manufacturer,
                explicit_uri=explicit_uri,
                timeout_s=timeout_s,
            )

            if not success or cap is None:
                # Buat pesan error informatif
                tried_onvif = " (termasuk ONVIF GetStreamUri)" if onvif_rtsp_uri else ""
                error_detail = (
                    f"Semua kandidat URL RTSP{tried_onvif} telah dicoba namun tidak ada yang berhasil "
                    f"mengembalikan frame video dari {target_ip}. "
                    "Kemungkinan penyebab: (1) Port RTSP ditutup oleh firewall perangkat atau router, "
                    "(2) Username/password salah, (3) Protokol RTSP tidak diaktifkan di pengaturan kamera/NVR, "
                    "(4) Kamera tidak terhubung ke jaringan yang sama. "
                    "Coba masukkan URL RTSP secara manual melalui 'Tambah IP/RTSP Manual'."
                )
                return DeviceValidationResponse(
                    is_accessible=False,
                    connection_status="ERROR",
                    message=f"Batas waktu koneksi stream RTSP terlampaui atau ditolak oleh {target_ip}.",
                    error_detail=error_detail,
                    checked_at=now,
                )

            # Berhasil mendapat frame
            ret, frame = cap.read()
            if not ret or frame is None or frame.size == 0:
                return DeviceValidationResponse(
                    is_accessible=False,
                    connection_status="ERROR",
                    message="Koneksi RTSP terbuka, namun tidak dapat mengambil frame video.",
                    error_detail="Stream video tidak menghasilkan data frame yang dapat didekode.",
                    checked_at=now,
                )

            h, w = frame.shape[:2]
            fps = int(cap.get(cv2.CAP_PROP_FPS) or 25)

            source_note = ""
            if onvif_rtsp_uri and successful_uri == onvif_rtsp_uri:
                source_note = " (URL diperoleh otomatis via ONVIF GetStreamUri)"
            elif successful_uri and successful_uri != raw_uri:
                source_note = f" — URL yang berhasil: {successful_uri}"

            return DeviceValidationResponse(
                is_accessible=True,
                connection_status="ONLINE",
                resolution_width=w,
                resolution_height=h,
                fps=fps,
                message=f"Verifikasi sukses! Feed live CCTV aktif pada resolusi {w}x{h} @ {fps}fps.{source_note}",
                working_rtsp_uri=successful_uri,
                checked_at=now,
            )

        except Exception as exc:
            return DeviceValidationResponse(
                is_accessible=False,
                connection_status="ERROR",
                message=f"Terjadi kesalahan saat memverifikasi koneksi kamera: {str(exc)}",
                error_detail=str(exc),
                checked_at=now,
            )
        finally:
            if cap:
                try:
                    cap.release()
                except Exception:
                    pass

    @classmethod
    def get_mock_discovery_devices(cls) -> List[DiscoveredCCTVDevice]:
        """Menyediakan data perangkat simulasi ONVIF untuk pengujian otomatis dan lingkungan tanpa hardware fisik."""
        return [
            DiscoveredCCTVDevice(
                device_id="urn:uuid:550e8400-e29b-41d4-a716-446655440001",
                name="Hikvision DS-2CD2043G0-I (Pintu Masuk)",
                ip_address="192.168.1.64",
                port=80,
                onvif_xaddr="http://192.168.1.64:80/onvif/device_service",
                manufacturer="Hikvision",
                model="DS-2CD2043G0-I",
                location="Pintu Masuk Utama Restoran",
                types=["dn:NetworkVideoTransmitter", "tds:Device"],
                scopes=[
                    "onvif://www.onvif.org/type/video_encoder",
                    "onvif://www.onvif.org/name/Hikvision_Camera_Entrance",
                    "onvif://www.onvif.org/hardware/DS-2CD2043G0-I",
                    "onvif://www.onvif.org/location/Main_Entrance"
                ],
                suggested_rtsp_uri="rtsp://192.168.1.64:554/Streaming/Channels/101",
                capabilities={"profiles": ["ProfileS", "ProfileT"], "audio": False, "ptz": False}
            ),
            DiscoveredCCTVDevice(
                device_id="urn:uuid:550e8400-e29b-41d4-a716-446655440002",
                name="Dahua IPC-HFW2431S (Area Kasir & Antrean)",
                ip_address="192.168.1.68",
                port=80,
                onvif_xaddr="http://192.168.1.68:80/onvif/device_service",
                manufacturer="Dahua",
                model="IPC-HFW2431S",
                location="Kasir & Display Antrean",
                types=["dn:NetworkVideoTransmitter"],
                scopes=[
                    "onvif://www.onvif.org/type/video_encoder",
                    "onvif://www.onvif.org/name/Dahua_Cashier_Cam",
                    "onvif://www.onvif.org/hardware/IPC-HFW2431S"
                ],
                suggested_rtsp_uri="rtsp://192.168.1.68:554/cam/realmonitor?channel=1&subtype=0",
                capabilities={"profiles": ["ProfileS"], "audio": True, "ptz": False}
            )
        ]
