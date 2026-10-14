import uuid
from datetime import datetime, timezone, timedelta
from sqlalchemy.orm import Session
from app.models.user import User
from app.models.camera import Camera
from app.models.counting_session import CountingSession
from app.models.counting_event import CountingEvent
from app.models.system_log import SystemLog
from app.core.security import get_password_hash

# Deterministic Fixed UUIDs for predictable testing and development
SEED_USER_ID = uuid.UUID("a0000000-0000-0000-0000-000000000001")
SEED_CAMERA_ID = uuid.UUID("c0000000-0000-0000-0000-000000000001")
SEED_CAMERA_WEBCAM_ID = uuid.UUID("c0000000-0000-0000-0000-000000000002")
SEED_SESSION_ID = uuid.UUID("e0000000-0000-0000-0000-000000000001")


def init_db(db: Session, force_reseed: bool = False) -> None:
    """Menginisialisasi seed data untuk pengembangan lokal dengan label DEMO eksplisit."""

    # 1. Seed User
    user = db.query(User).filter(User.email == "admin@ayambangjungko.com").first()
    if not user or force_reseed:
        if not user:
            user = User(
                id=SEED_USER_ID,
                email="admin@ayambangjungko.com",
                hashed_password=get_password_hash("password123"),
                full_name="Manager Operasional Ayam Bang Jungko",
                role="manager",
                is_active=True
            )
            db.add(user)
            db.commit()
            print("Seed User created: admin@ayambangjungko.com")

    # 2. Seed Camera 1 (IP Camera RTSP)
    camera = db.query(Camera).filter(Camera.id == SEED_CAMERA_ID).first()
    if not camera:
        camera = Camera(
            id=SEED_CAMERA_ID,
            name="CCTV Pintu Masuk Utama (Ayam Bang Jungko)",
            location="Pintu Depan Area Kasir & Dining",
            source_type="RTSP",
            source_uri="rtsp://192.168.1.100:554/live/ch0",
            username="admin",
            resolution_width=1280,
            resolution_height=720,
            fps=25,
            line_coords={
                "x1": 150,
                "y1": 380,
                "x2": 1130,
                "y2": 380,
                "direction_in": "down",
                "offset": 35
            },
            is_active=True,
            connection_status="DISCONNECTED",
            processing_status="STOPPED",
            notes="Kamera IP utama area pintu masuk restoran"
        )
        db.add(camera)
        db.commit()
        print("Seed Camera created: CCTV Pintu Masuk Utama (RTSP)")
    else:
        camera.source_type = "RTSP"
        camera.source_uri = "rtsp://192.168.1.100:554/live/ch0"
        camera.connection_status = "DISCONNECTED"
        camera.processing_status = "STOPPED"
        db.commit()

    # 2B. Seed Camera 2 (Laptop Camera / USB Webcam)
    webcam = db.query(Camera).filter(Camera.id == SEED_CAMERA_WEBCAM_ID).first()
    if not webcam:
        webcam = Camera(
            id=SEED_CAMERA_WEBCAM_ID,
            name="Kamera Laptop / USB Webcam Uji Coba",
            location="Meja Kasir & Laptop Penguji",
            source_type="USB_WEBCAM",
            source_uri="0",
            resolution_width=640,
            resolution_height=480,
            fps=30,
            line_coords={
                "x1": 50,
                "y1": 240,
                "x2": 590,
                "y2": 240,
                "direction_in": "down",
                "offset": 25
            },
            is_active=True,
            connection_status="ONLINE",
            processing_status="STOPPED",
            notes="Kamera webcam internal laptop / USB untuk verifikasi deteksi real-time"
        )
        db.add(webcam)
        db.commit()
        print("Seed Camera created: Kamera Laptop / USB Webcam Uji Coba")

    # 3. Seed Session
    session = db.query(CountingSession).filter(CountingSession.id == SEED_SESSION_ID).first()
    if not session:
        session = CountingSession(
            id=SEED_SESSION_ID,
            camera_id=SEED_CAMERA_ID,
            mode="DEMO",
            status="RUNNING",
            started_at=datetime.now(timezone.utc) - timedelta(hours=8),
            total_in=254,
            total_out=228,
            notes="Sesi operasional demo shift berjalan — data berlabel DEMO"
        )
        db.add(session)
        db.commit()
        print("Seed Session created: Running DEMO session")

    # 4. Seed Counting Events (jika belum ada event)
    existing_events_count = db.query(CountingEvent).filter(CountingEvent.source_mode == "DEMO").count()
    if existing_events_count == 0 or force_reseed:
        now = datetime.now(timezone.utc)
        events_to_add = []

        # Jam operasional resto: 10:00 - 22:00
        # Distribusi per jam untuk Hari Ini
        hourly_distribution = [
            (10, 12, 4),   # buka resto
            (11, 28, 14),
            (12, 68, 42),  # Puncak Makan Siang 1
            (13, 54, 62),  # Puncak Makan Siang 2
            (14, 18, 28),  # Jam santai siang
            (15, 14, 16),
            (16, 19, 15),
            (17, 36, 22),  # Menjelang makan malam
            (18, 86, 48),  # Puncak Makan Malam 1
            (19, 78, 74),  # Puncak Makan Malam 2
            (20, 42, 58),
            (21, 16, 32),  # Cooldown
        ]

        # Generate event hari ini (Today)
        # Menghitung awal hari ini (pukul 00:00 UTC atau WIB)
        today_start = now.replace(hour=0, minute=0, second=0, microsecond=0)

        track_counter = 100
        for hour, count_in, count_out in hourly_distribution:
            # Events IN
            for i in range(count_in):
                evt_time = today_start + timedelta(hours=hour, minutes=(i * 60) // max(count_in, 1), seconds=(i * 7) % 60)
                events_to_add.append(
                    CountingEvent(
                        id=uuid.uuid4(),
                        camera_id=SEED_CAMERA_ID,
                        session_id=SEED_SESSION_ID,
                        event_timestamp=evt_time,
                        direction="IN",
                        count_value=1,
                        track_id=track_counter,
                        confidence=0.88 + ((i % 10) * 0.01),
                        source_mode="DEMO",
                        metadata_json={"simulated": True, "detector": "YOLOv8n", "tracker": "ByteTrack"}
                    )
                )
                track_counter += 1

            # Events OUT
            for j in range(count_out):
                evt_time = today_start + timedelta(hours=hour, minutes=(j * 60) // max(count_out, 1) + 1, seconds=(j * 11) % 60)
                events_to_add.append(
                    CountingEvent(
                        id=uuid.uuid4(),
                        camera_id=SEED_CAMERA_ID,
                        session_id=SEED_SESSION_ID,
                        event_timestamp=evt_time,
                        direction="OUT",
                        count_value=1,
                        track_id=track_counter,
                        confidence=0.89 + ((j % 8) * 0.01),
                        source_mode="DEMO",
                        metadata_json={"simulated": True, "detector": "YOLOv8n", "tracker": "ByteTrack"}
                    )
                )
                track_counter += 1

        # Tambahkan event 6 hari sebelumnya untuk tren mingguan lengkap
        for day_offset in range(1, 7):
            past_date = today_start - timedelta(days=day_offset)
            # Volume akhir pekan lebih tinggi
            is_weekend = (past_date.weekday() in [5, 6])
            daily_in = 380 if is_weekend else 220
            daily_out = 370 if is_weekend else 215

            # Sample 20 events representatif per hari lalu
            for k in range(20):
                dir_val = "IN" if k % 2 == 0 else "OUT"
                events_to_add.append(
                    CountingEvent(
                        id=uuid.uuid4(),
                        camera_id=SEED_CAMERA_ID,
                        session_id=SEED_SESSION_ID,
                        event_timestamp=past_date + timedelta(hours=11 + (k % 10), minutes=k * 2),
                        direction=dir_val,
                        count_value=daily_in // 20 if dir_val == "IN" else daily_out // 20,
                        track_id=track_counter,
                        confidence=0.91,
                        source_mode="DEMO",
                        metadata_json={"simulated": True, "day_offset": day_offset}
                    )
                )
                track_counter += 1

        db.bulk_save_objects(events_to_add)
        db.commit()
        print(f"Seed CountingEvents created: {len(events_to_add)} events inserted with source_mode='DEMO'")

    # 5. Seed System Logs
    log_count = db.query(SystemLog).count()
    if log_count == 0:
        sample_logs = [
            SystemLog(
                id=uuid.uuid4(),
                module="DATABASE",
                level="INFO",
                message="Inisialisasi skema tabel PostgreSQL dan seed data berhasil.",
                details={"tables": 5, "mode": "development"}
            ),
            SystemLog(
                id=uuid.uuid4(),
                module="AUTH",
                level="INFO",
                message="Akun administratif operasional Ayam Bang Jungko terdaftar.",
                details={"email": "admin@ayambangjungko.com"}
            ),
            SystemLog(
                id=uuid.uuid4(),
                module="VISION_WORKER",
                level="INFO",
                message="Worker pipeline siap menerima frame dan melacak garis virtual.",
                details={"fps": 24.8, "tracker": "ByteTrack"}
            )
        ]
        db.bulk_save_objects(sample_logs)
        db.commit()
        print("Seed SystemLogs created.")


if __name__ == "__main__":
    from app.db.session import SessionLocal
    db = SessionLocal()
    try:
        init_db(db)
        print("Database seeding completed successfully!")
    finally:
        db.close()
