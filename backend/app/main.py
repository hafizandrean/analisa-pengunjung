from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from datetime import datetime, timezone
from app.core.config import settings
from app.api.v1.api import api_router
from app.schemas.system import HealthResponse
from app.db.session import SessionLocal
from app.db.init_db import init_db


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Konfigurasi stdout agar aman di Windows (menghindari UnicodeEncodeError emoji)
    import sys
    import io
    if hasattr(sys.stdout, 'reconfigure'):
        try:
            sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        except Exception:
            pass

    # -- Startup: Inisialisasi tabel dan seed data lokal ---------------------
    try:
        db = SessionLocal()
        init_db(db)
        db.close()
    except Exception as e:
        print(f"Peringatan saat inisialisasi basis data: {e}")

    # Auto-start AI untuk semua kamera aktif saat server dimulai.
    # Berjalan di daemon thread agar tidak memblokir startup uvicorn.
    import threading
    from app.services.video_source_manager import LiveVideoSourceManager
    from app.models.camera import Camera as CameraModel

    def _auto_start_worker():
        import time
        print("[AutoStart] Watchdog dimulai - akan auto-start AI untuk semua kamera aktif.")
        time.sleep(3)  # Tunggu DB pool & koneksi siap

        while True:
            try:
                db = SessionLocal()
                cameras = db.query(CameraModel).filter(CameraModel.is_active == True).all()

                for cam in cameras:
                    cam_id = str(cam.id)
                    already_running = LiveVideoSourceManager._ai_running_flags.get(cam_id, False)
                    if already_running:
                        continue

                    print(f"[AutoStart] Mencoba: {cam.name}")
                    success, msg, _ = LiveVideoSourceManager.start_processing(cam, db)
                    if success:
                        print(f"[AutoStart] OK - AI berjalan: {cam.name}")
                    else:
                        short_msg = msg[:70] if msg else "-"
                        print(f"[AutoStart] Belum connect ({cam.name}): {short_msg}")
                        # Jangan biarkan status ERROR terlihat di UI — kembalikan ke DISCONNECTED
                        if cam.processing_status == "ERROR":
                            cam.connection_status = "DISCONNECTED"
                            cam.processing_status = "STOPPED"
                            db.commit()
            except Exception as e:
                print(f"[AutoStart] Error watchdog: {e}")
            finally:
                try:
                    db.close()
                except Exception:
                    pass

            # Coba ulang setiap 45 detik untuk kamera yang belum terhubung
            time.sleep(45)

    watcher = threading.Thread(
        target=_auto_start_worker,
        daemon=True,
        name="ai_auto_start_watchdog"
    )
    watcher.start()

    yield
    # -- Shutdown ------------------------------------------------------------
    print("[Shutdown] Menghentikan semua sesi AI yang aktif...")




app = FastAPI(
    title=settings.PROJECT_NAME,
    description="REST API untuk Sistem Penghitungan Pengunjung & Analisa Pola Keramaian Berbasis CCTV untuk Usaha Ayam Bang Jungko.",
    version="1.0.0",
    openapi_url=f"{settings.API_V1_STR}/openapi.json",
    docs_url=f"{settings.API_V1_STR}/docs",
    redoc_url=f"{settings.API_V1_STR}/redoc",
    lifespan=lifespan
)

# Set CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# Root level health endpoint (required by specification: GET /health)
@app.get("/health", response_model=HealthResponse, tags=["Health"])
def root_health():
    return HealthResponse(
        status="ok",
        service=settings.PROJECT_NAME,
        version="1.0.0",
        timestamp=datetime.now(timezone.utc),
        timezone=settings.TIMEZONE
    )


# Include versioned API router under /api/v1
app.include_router(api_router, prefix=settings.API_V1_STR)


@app.get("/", tags=["Root"])
def root():
    return {
        "message": "Selamat Datang di REST API Sistem Analisa Pengunjung CCTV Ayam Bang Jungko",
        "docs": f"{settings.API_V1_STR}/docs",
        "health": "/health",
        "status": "online"
    }
