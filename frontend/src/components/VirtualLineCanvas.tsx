import React, { useState, useRef, useEffect, useCallback } from 'react';
import { VirtualLineCoords } from '../types/api';
import { Sliders, ArrowDown, ArrowUp, RefreshCw, Eye } from 'lucide-react';

interface VirtualLineCanvasProps {
  coords: VirtualLineCoords;
  onChange: (newCoords: VirtualLineCoords) => void;
  videoWidth?: number;
  videoHeight?: number;
  previewUrl?: string;
  isOnline?: boolean;
  sourceType?: 'RTSP' | 'NVR_CHANNEL' | 'USB_WEBCAM' | 'HTTP_STREAM';
  processingStatus?: string;
}

export const VirtualLineCanvas: React.FC<VirtualLineCanvasProps> = ({
  coords,
  onChange,
  videoWidth = 1280,
  videoHeight = 720,
  previewUrl,
  isOnline = false,
  sourceType = 'RTSP',
  processingStatus,
}) => {
  const [activeHandle, setActiveHandle] = useState<'p1' | 'p2' | null>(null);
  const [streamError, setStreamError] = useState(false);
  const [isWebcamActive, setIsWebcamActive] = useState(false);
  const [webcamError, setWebcamError] = useState<string | null>(null);
  const [mediaStream, setMediaStream] = useState<MediaStream | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const videoRef = useRef<HTMLVideoElement | null>(null);

  // Reset stream error when previewUrl changes
  useEffect(() => {
    setStreamError(false);
  }, [previewUrl]);

  // Callback ref to reliably attach stream whenever video element mounts
  const setVideoRef = useCallback((node: HTMLVideoElement | null) => {
    videoRef.current = node;
    if (node && mediaStream) {
      node.srcObject = mediaStream;
      node.play().catch((err) => {
        console.warn('Video play caught:', err);
      });
    }
  }, [mediaStream]);

  // Direct browser webcam access for USB_WEBCAM / Laptop Camera
  useEffect(() => {
    let activeStream: MediaStream | null = null;
    let isCancelled = false;

    if (sourceType === 'USB_WEBCAM') {
      setWebcamError(null);
      navigator.mediaDevices?.getUserMedia({
        video: { width: { ideal: videoWidth }, height: { ideal: videoHeight } }
      })
      .then((s) => {
        if (isCancelled) {
          s.getTracks().forEach((t) => t.stop());
          return;
        }
        activeStream = s;
        setMediaStream(s);
        setIsWebcamActive(true);
        if (videoRef.current) {
          videoRef.current.srcObject = s;
          videoRef.current.play().catch(() => {});
        }
      })
      .catch((err) => {
        console.warn('Akses kamera laptop browser tidak tersedia:', err);
        setIsWebcamActive(false);
        setWebcamError(
          err.name === 'NotAllowedError'
            ? 'Izin kamera ditolak browser. Izinkan akses kamera melalui ikon gembok pada address bar.'
            : err.name === 'NotReadableError'
            ? 'Kamera laptop sedang digunakan aplikasi lain. Tutup aplikasi tersebut dan coba lagi.'
            : 'Perangkat kamera webcam tidak dapat diakses.'
        );
      });
    } else {
      setIsWebcamActive(false);
      setMediaStream(null);
      setWebcamError(null);
    }

    return () => {
      isCancelled = true;
      if (activeStream) {
        activeStream.getTracks().forEach((t) => t.stop());
      }
    };
  }, [sourceType, videoWidth, videoHeight]);

  // Scaled coordinates for SVG viewbox
  const { x1, y1, x2, y2, direction_in, offset } = coords;

  const handleSvgClick = (e: React.MouseEvent<SVGSVGElement>) => {
    if (!containerRef.current) return;
    const rect = containerRef.current.getBoundingClientRect();
    const scaleX = videoWidth / rect.width;
    const scaleY = videoHeight / rect.height;

    const clickX = Math.round((e.clientX - rect.left) * scaleX);
    const clickY = Math.round((e.clientY - rect.top) * scaleY);

    if (activeHandle === 'p1') {
      onChange({ ...coords, x1: clickX, y1: clickY });
      setActiveHandle(null);
    } else if (activeHandle === 'p2') {
      onChange({ ...coords, x2: clickX, y2: clickY });
      setActiveHandle(null);
    }
  };

  // Center midpoint of virtual line
  const midX = (x1 + x2) / 2;
  const midY = (y1 + y2) / 2;

  return (
    <div className="space-y-4">
      {/* Visual Canvas Area */}
      <div
        ref={containerRef}
        className="relative overflow-hidden rounded-2xl border border-slate-700 bg-slate-950 shadow-inner aspect-video select-none"
      >
        {/* Render Live Video Feed: Direct Browser Webcam or Backend Stream */}
        {sourceType === 'USB_WEBCAM' && processingStatus === 'RUNNING' && previewUrl && !streamError ? (
          /* Saat AI berjalan: tampilkan stream backend yang sudah dianotasi (bounding box, counter) */
          <img
            src={previewUrl}
            alt="Live CCTV Feed (AI)"
            onError={() => setStreamError(true)}
            onLoad={() => setStreamError(false)}
            className="absolute inset-0 h-full w-full object-cover"
          />
        ) : sourceType === 'USB_WEBCAM' ? (
          isWebcamActive ? (
            <video
              ref={setVideoRef}
              autoPlay
              playsInline
              muted
              className="absolute inset-0 h-full w-full object-cover transform -scale-x-100"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-950 opacity-90 flex flex-col items-center justify-center p-6 text-center">
              <div className="h-10 w-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center mb-2 text-slate-400">
                <Eye className="h-5 w-5" />
              </div>
              <div className="text-xs font-bold text-slate-300">
                {webcamError ? 'Akses Kamera Terkendala' : 'Mengaktifkan Akses Kamera Laptop...'}
              </div>
              <p className="text-[11px] text-slate-400 mt-1 max-w-sm">
                {webcamError || 'Izinkan perizinan kamera pada pop-up browser agar live video tampil di layar.'}
              </p>
            </div>
          )
        ) : previewUrl && !streamError ? (
          <img
            src={previewUrl}
            alt="Live CCTV Feed"
            onError={() => setStreamError(true)}
            onLoad={() => setStreamError(false)}
            className="absolute inset-0 h-full w-full object-cover"
          />
        ) : (
          <div className="absolute inset-0 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-950 opacity-90 flex flex-col items-center justify-center p-6 text-center">
            {isOnline && processingStatus === 'RUNNING' ? (
              <>
                <RefreshCw className="h-8 w-8 text-blue-400 animate-spin mb-3" />
                <div className="text-xs font-bold text-slate-200">Memuat Stream AI...</div>
                <p className="text-[11px] text-slate-400 mt-1">Pemrosesan sedang berjalan, frame akan segera muncul.</p>
              </>
            ) : isOnline ? (
              <>
                <div className="h-10 w-10 rounded-full bg-emerald-900/60 border border-emerald-600 flex items-center justify-center mb-3">
                  <Eye className="h-5 w-5 text-emerald-400" />
                </div>
                <div className="flex items-center gap-2 mb-1">
                  <span className="relative flex h-2 w-2">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                  </span>
                  <div className="text-xs font-bold text-emerald-300">Kamera ONLINE</div>
                </div>
                <p className="text-[11px] text-slate-400 max-w-xs">
                  Klik <span className="text-white font-semibold">Mulai AI</span> untuk melihat live video dan memulai analisis pengunjung.
                </p>
              </>
            ) : (
              <>
                <div className="h-10 w-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center mb-2 text-slate-400">
                  <Eye className="h-5 w-5" />
                </div>
                <div className="text-xs font-bold text-slate-300">Kamera Belum Terhubung</div>
                <p className="text-[11px] text-slate-500 mt-0.5 max-w-sm">
                  Klik <span className="text-slate-300 font-semibold">Uji Koneksi</span> untuk memeriksa kamera, lalu Mulai AI.
                </p>
              </>
            )}
          </div>
        )}

        {/* SVG Overlay for Virtual Line */}
        <svg
          viewBox={`0 0 ${videoWidth} ${videoHeight}`}
          className="absolute inset-0 h-full w-full cursor-crosshair"
          onClick={handleSvgClick}
        >
          {/* Upper offset limit line */}
          <line
            x1={x1}
            y1={Math.max(0, y1 - offset)}
            x2={x2}
            y2={Math.max(0, y2 - offset)}
            stroke="#94a3b8"
            strokeWidth="2"
            strokeDasharray="6 6"
            strokeOpacity="0.6"
          />

          {/* Lower offset limit line */}
          <line
            x1={x1}
            y1={Math.min(videoHeight, y1 + offset)}
            x2={x2}
            y2={Math.min(videoHeight, y2 + offset)}
            stroke="#94a3b8"
            strokeWidth="2"
            strokeDasharray="6 6"
            strokeOpacity="0.6"
          />

          {/* Main Counting Line */}
          <line
            x1={x1}
            y1={y1}
            x2={x2}
            y2={y2}
            stroke="#ef4444"
            strokeWidth="6"
            strokeLinecap="round"
          />

          {/* Handle 1 (P1) */}
          <circle
            cx={x1}
            cy={y1}
            r="16"
            fill={activeHandle === 'p1' ? '#3b82f6' : '#ef4444'}
            stroke="#ffffff"
            strokeWidth="3"
            className="cursor-pointer transition hover:scale-125"
            onClick={(e) => {
              e.stopPropagation();
              setActiveHandle(activeHandle === 'p1' ? null : 'p1');
            }}
          />
          <text
            x={x1}
            y={y1 - 24}
            fill="#ffffff"
            fontSize="24"
            textAnchor="middle"
            fontWeight="bold"
          >
            P1 ({x1}, {y1})
          </text>

          {/* Handle 2 (P2) */}
          <circle
            cx={x2}
            cy={y2}
            r="16"
            fill={activeHandle === 'p2' ? '#3b82f6' : '#ef4444'}
            stroke="#ffffff"
            strokeWidth="3"
            className="cursor-pointer transition hover:scale-125"
            onClick={(e) => {
              e.stopPropagation();
              setActiveHandle(activeHandle === 'p2' ? null : 'p2');
            }}
          />
          <text
            x={x2}
            y={y2 - 24}
            fill="#ffffff"
            fontSize="24"
            textAnchor="middle"
            fontWeight="bold"
          >
            P2 ({x2}, {y2})
          </text>

          {/* Direction Indicator on Midpoint */}
          <circle cx={midX} cy={midY} r="22" fill="#1e293b" stroke="#38bdf8" strokeWidth="3" />
          <path
            d={
              direction_in === 'down'
                ? `M ${midX} ${midY - 10} L ${midX} ${midY + 10} M ${midX - 7} ${midY + 3} L ${midX} ${midY + 10} L ${midX + 7} ${midY + 3}`
                : `M ${midX} ${midY + 10} L ${midX} ${midY - 10} M ${midX - 7} ${midY - 3} L ${midX} ${midY - 10} L ${midX + 7} ${midY - 3}`
            }
            stroke="#38bdf8"
            strokeWidth="4"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>

        {/* Live overlay banner */}
        <div className="absolute top-3 left-3 flex items-center gap-2 rounded-lg bg-black/75 px-3 py-1.5 text-xs text-white backdrop-blur shadow-md">
          {sourceType === 'USB_WEBCAM' && isWebcamActive ? (
            <>
              <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span className="font-semibold text-emerald-300">LIVE KAMERA LAPTOP</span>
            </>
          ) : previewUrl && !streamError ? (
            <>
              <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span className="font-semibold text-emerald-300">LIVE CCTV FEED</span>
            </>
          ) : (
            <>
              <span className="h-2 w-2 rounded-full bg-amber-400"></span>
              <span className="text-slate-300">
                {sourceType === 'USB_WEBCAM' ? 'WEBCAM LOKAL' : 'OFFLINE'}
              </span>
            </>
          )}
          <span className="text-slate-400 text-[10px]">({videoWidth}x{videoHeight} px)</span>
        </div>

        {activeHandle && (
          <div className="absolute bottom-3 right-3 rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white shadow-lg animate-bounce">
            Klik pada layar untuk memindahkan titik {activeHandle.toUpperCase()}
          </div>
        )}
      </div>

      {/* Coordinate Control Inputs */}
      <div className="grid grid-cols-2 gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
        <div>
          <label className="text-[11px] font-semibold text-slate-500 uppercase">Titik 1 (X1, Y1)</label>
          <div className="mt-1 flex items-center gap-2">
            <input
              type="number"
              value={x1}
              onChange={(e) => onChange({ ...coords, x1: parseInt(e.target.value) || 0 })}
              className="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium focus:border-blue-500 focus:outline-none"
              placeholder="X1"
            />
            <input
              type="number"
              value={y1}
              onChange={(e) => onChange({ ...coords, y1: parseInt(e.target.value) || 0 })}
              className="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium focus:border-blue-500 focus:outline-none"
              placeholder="Y1"
            />
          </div>
        </div>

        <div>
          <label className="text-[11px] font-semibold text-slate-500 uppercase">Titik 2 (X2, Y2)</label>
          <div className="mt-1 flex items-center gap-2">
            <input
              type="number"
              value={x2}
              onChange={(e) => onChange({ ...coords, x2: parseInt(e.target.value) || 0 })}
              className="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium focus:border-blue-500 focus:outline-none"
              placeholder="X2"
            />
            <input
              type="number"
              value={y2}
              onChange={(e) => onChange({ ...coords, y2: parseInt(e.target.value) || 0 })}
              className="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium focus:border-blue-500 focus:outline-none"
              placeholder="Y2"
            />
          </div>
        </div>

        <div>
          <label className="text-[11px] font-semibold text-slate-500 uppercase">Arah Masuk (IN)</label>
          <div className="mt-1">
            <select
              value={direction_in}
              onChange={(e) => onChange({ ...coords, direction_in: e.target.value as any })}
              className="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium focus:border-blue-500 focus:outline-none"
            >
              <option value="down">Ke Bawah (Down)</option>
              <option value="up">Ke Atas (Up)</option>
            </select>
          </div>
        </div>

        <div>
          <label className="text-[11px] font-semibold text-slate-500 uppercase">Toleransi Garis (px)</label>
          <div className="mt-1">
            <input
              type="number"
              value={offset}
              onChange={(e) => onChange({ ...coords, offset: parseInt(e.target.value) || 20 })}
              className="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium focus:border-blue-500 focus:outline-none"
              placeholder="Offset"
            />
          </div>
        </div>
      </div>
    </div>
  );
};
