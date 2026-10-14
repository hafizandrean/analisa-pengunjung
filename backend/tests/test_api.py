import pytest
from datetime import datetime, timezone
import uuid
from httpx import AsyncClient, ASGITransport
from app.main import app
from app.db.init_db import SEED_CAMERA_ID, SEED_SESSION_ID


@pytest.mark.anyio
async def test_root_endpoint():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.get("/")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "online"
    assert "Ayam Bang Jungko" in data["message"]


@pytest.mark.anyio
async def test_health_endpoints():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        res_root = await ac.get("/health")
        res_v1 = await ac.get("/api/v1/health")

    assert res_root.status_code == 200
    assert res_root.json()["status"] == "ok"
    assert res_root.json()["timezone"] == "Asia/Jakarta"

    assert res_v1.status_code == 200
    assert res_v1.json()["status"] == "ok"


@pytest.mark.anyio
async def test_auth_login_success():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.post(
            "/api/v1/auth/login",
            json={"email": "admin@ayambangjungko.com", "password": "password123"}
        )
    assert response.status_code == 200
    data = response.json()
    assert "access_token" in data
    assert data["token_type"] == "bearer"
    assert len(data["access_token"]) > 20


@pytest.mark.anyio
async def test_auth_login_invalid():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.post(
            "/api/v1/auth/login",
            json={"email": "admin@ayambangjungko.com", "password": "wrongpassword"}
        )
    assert response.status_code == 401


@pytest.mark.anyio
async def test_auth_me_with_jwt():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        login_res = await ac.post(
            "/api/v1/auth/login",
            json={"email": "admin@ayambangjungko.com", "password": "password123"}
        )
        token = login_res.json()["access_token"]

        me_res = await ac.get(
            "/api/v1/auth/me",
            headers={"Authorization": f"Bearer {token}"}
        )
    assert me_res.status_code == 200
    user_data = me_res.json()
    assert user_data["email"] == "admin@ayambangjungko.com"
    assert user_data["role"] == "manager"
    assert user_data["is_active"] is True


@pytest.mark.anyio
async def test_dashboard_summary_from_db():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.get("/api/v1/dashboard/summary")
    assert response.status_code == 200
    data = response.json()
    assert "total_today" in data
    assert "total_today_in" in data
    assert "total_today_out" in data
    assert "total_week" in data
    assert "busiest_hour" in data
    assert data["active_cameras"] >= 1
    assert data["worker_status"] in ["RUNNING", "IDLE"]


@pytest.mark.anyio
async def test_analytics_hourly_and_daily():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        res_hourly = await ac.get("/api/v1/analytics/hourly")
        res_daily = await ac.get("/api/v1/analytics/daily?days=7")

    assert res_hourly.status_code == 200
    hourly_items = res_hourly.json()
    assert len(hourly_items) == 12  # 10:00 - 21:00 operational hours
    assert "hour" in hourly_items[0]
    assert "count_in" in hourly_items[0]

    assert res_daily.status_code == 200
    daily_items = res_daily.json()
    assert len(daily_items) == 7
    assert "day_name" in daily_items[0]
    assert "total" in daily_items[0]


@pytest.mark.anyio
async def test_analytics_peak_hours_and_comparison():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        res_peak = await ac.get("/api/v1/analytics/peak-hours")
        res_comp = await ac.get("/api/v1/analytics/comparison")

    assert res_peak.status_code == 200
    peak_items = res_peak.json()
    assert len(peak_items) >= 1
    assert "occupancy_status" in peak_items[0]
    assert peak_items[0]["occupancy_status"] in ["PADAT", "SEDANG", "LENGANG"]

    assert res_comp.status_code == 200
    comp_items = res_comp.json()
    assert len(comp_items) == 7
    assert "change_percentage" in comp_items[0]


@pytest.mark.anyio
async def test_events_list_and_pagination():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.get("/api/v1/events?page=1&page_size=10")
    assert response.status_code == 200
    data = response.json()
    assert data["total"] > 0
    assert len(data["items"]) <= 10
    first_item = data["items"][0]
    assert first_item["direction"] in ["IN", "OUT"]
    assert first_item["source_mode"] == "DEMO"


@pytest.mark.anyio
async def test_record_new_counting_event():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        new_event_payload = {
            "camera_id": str(SEED_CAMERA_ID),
            "session_id": str(SEED_SESSION_ID),
            "event_timestamp": datetime.now(timezone.utc).isoformat(),
            "direction": "IN",
            "count_value": 1,
            "track_id": 9999,
            "confidence": 0.95,
            "source_mode": "DEMO",
            "metadata_json": {"test": True}
        }
        post_res = await ac.post("/api/v1/events", json=new_event_payload)
    assert post_res.status_code == 201
    evt_data = post_res.json()
    assert evt_data["track_id"] == 9999
    assert evt_data["direction"] == "IN"


@pytest.mark.anyio
async def test_cameras_management_and_line_coords():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        get_res = await ac.get("/api/v1/cameras")
        assert get_res.status_code == 200
        cameras = get_res.json()
        assert len(cameras) >= 1
        cam_id = cameras[0]["id"]

        # Update line coords
        new_coords = {
            "line_coords": {
                "x1": 180,
                "y1": 400,
                "x2": 1100,
                "y2": 400,
                "direction_in": "down",
                "offset": 40
            }
        }
        patch_res = await ac.patch(f"/api/v1/cameras/{cam_id}", json=new_coords)
    assert patch_res.status_code == 200
    updated_cam = patch_res.json()
    assert updated_cam["line_coords"]["x1"] == 180
    assert updated_cam["line_coords"]["offset"] == 40
    # Verify RTSP masking
    assert "source_uri_masked" in updated_cam


@pytest.mark.anyio
async def test_system_status_and_logs():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        status_res = await ac.get("/api/v1/system/status")
        logs_res = await ac.get("/api/v1/system/logs")

    assert status_res.status_code == 200
    status_data = status_res.json()
    assert status_data["backend_status"] == "HEALTHY"
    assert status_data["database_connected"] is True
    assert status_data["cv_worker"]["status"] == "RUNNING"
    assert "WIB" in status_data["server_time_wib"]

    assert logs_res.status_code == 200
    logs_data = logs_res.json()
    assert len(logs_data) >= 1
    assert "module" in logs_data[0]


@pytest.mark.anyio
async def test_cctv_source_lifecycle_and_connection_testing():
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        # 1. Register RTSP camera with credentials
        rtsp_payload = {
            "name": "CCTV Area Parkir Barat",
            "location": "Pintu Gerbang Parkir",
            "source_type": "RTSP",
            "source_uri": "rtsp://192.168.1.188:554/stream1",
            "username": "operator_cctv",
            "password": "SuperSecretPassword99!",
            "resolution_width": 1920,
            "resolution_height": 1080,
            "fps": 30,
            "line_coords": {
                "x1": 100,
                "y1": 300,
                "x2": 800,
                "y2": 300,
                "direction_in": "down",
                "offset": 25
            },
            "is_active": True,
            "notes": "Kamera outdoor sudut barat"
        }
        print("\n--- STEP 1: Creating RTSP camera ---")
        create_res = await ac.post("/api/v1/cameras", json=rtsp_payload)
        assert create_res.status_code == 201
        cam_data = create_res.json()
        rtsp_cam_id = cam_data["id"]

        # Kredensial tidak boleh bocor
        assert "SuperSecretPassword99!" not in str(cam_data)
        assert "password" not in cam_data
        assert cam_data["connection_status"] == "DISCONNECTED"
        assert cam_data["processing_status"] == "STOPPED"
        assert "***" in cam_data["source_uri_masked"]

        print("--- STEP 2: Testing RTSP camera connection (expect failure) ---")
        test_res = await ac.post(f"/api/v1/cameras/{rtsp_cam_id}/test")
        assert test_res.status_code == 200
        test_data = test_res.json()
        assert test_data["is_accessible"] is False
        assert test_data["connection_status"] in ["DISCONNECTED", "ERROR"]
        assert len(test_data["message"]) > 0

        print("--- STEP 3: Detecting webcams ---")
        detect_res = await ac.get("/api/v1/cameras/webcams/detect")
        assert detect_res.status_code == 200
        assert "devices" in detect_res.json()

        print("--- STEP 4: Creating webcam camera ---")
        webcam_payload = {
            "name": "Kamera Laptop Pengujian Live",
            "location": "Meja Kasir & Laptop Penguji",
            "source_type": "USB_WEBCAM",
            "source_uri": "0",
            "resolution_width": 640,
            "resolution_height": 480,
            "fps": 30,
            "line_coords": {
                "x1": 50,
                "y1": 240,
                "x2": 590,
                "y2": 240,
                "direction_in": "down",
                "offset": 25
            },
            "is_active": True,
            "notes": "Webcam bawaan laptop untuk verifikasi live frame"
        }
        create_webcam_res = await ac.post("/api/v1/cameras", json=webcam_payload)
        assert create_webcam_res.status_code == 201
        webcam_cam_data = create_webcam_res.json()
        webcam_cam_id = webcam_cam_data["id"]

        print("--- STEP 5: Testing webcam connection ---")
        test_webcam_res = await ac.post(f"/api/v1/cameras/{webcam_cam_id}/test")
        assert test_webcam_res.status_code == 200
        test_webcam_data = test_webcam_res.json()
        assert test_webcam_data["is_accessible"] is True
        assert test_webcam_data["connection_status"] == "ONLINE"
        assert test_webcam_data["resolution_width"] == 640
        assert test_webcam_data["resolution_height"] == 480

        print("--- STEP 6: Testing preview endpoint ---")
        preview_res = await ac.get(f"/api/v1/cameras/{webcam_cam_id}/preview?max_frames=1")
        assert preview_res.status_code == 200
        assert "multipart/x-mixed-replace" in preview_res.headers.get("content-type", "")
        assert len(preview_res.content) > 0

        print("--- STEP 7: Starting and stopping AI processing ---")
        start_res = await ac.post(f"/api/v1/cameras/{webcam_cam_id}/start-processing")
        assert start_res.status_code == 200
        assert start_res.json()["processing_status"] == "RUNNING"

        status_res = await ac.get(f"/api/v1/cameras/{webcam_cam_id}/status")
        assert status_res.status_code == 200
        assert status_res.json()["processing_status"] == "RUNNING"

        stop_res = await ac.post(f"/api/v1/cameras/{webcam_cam_id}/stop-processing")
        assert stop_res.status_code == 200
        assert stop_res.json()["processing_status"] == "STOPPED"

        print("--- STEP 8: Deleting cameras ---")
        del_res = await ac.delete(f"/api/v1/cameras/{rtsp_cam_id}")
        assert del_res.status_code == 200
        del_webcam_res = await ac.delete(f"/api/v1/cameras/{webcam_cam_id}")
        assert del_webcam_res.status_code == 200

        # Verifikasi 404 setelah dihapus
        verify_del = await ac.get(f"/api/v1/cameras/{rtsp_cam_id}")
        assert verify_del.status_code == 404
        print("--- ALL STEPS COMPLETED! ---")

