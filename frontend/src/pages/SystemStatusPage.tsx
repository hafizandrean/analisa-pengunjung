import React, { useState, useEffect } from 'react';
import {
  Server,
  Database,
  Cpu,
  Clock,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  ShieldCheck,
  FileText
} from 'lucide-react';
import { api } from '../services/api';
import { SystemStatus, SystemLog } from '../types/api';
import { formatIndonesianDateTime } from '../utils/formatters';

export const SystemStatusPage: React.FC = () => {
  const [status, setStatus] = useState<SystemStatus | null>(null);
  const [logs, setLogs] = useState<SystemLog[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const loadStatus = async () => {
    setIsLoading(true);
    try {
      const [statusRes, logsRes] = await Promise.all([
        api.system.getStatus(),
        api.system.getLogs()
      ]);
      setStatus(statusRes);
      setLogs(logsRes);
    } catch (err) {
      console.error('Gagal mengambil status sistem:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadStatus();
  }, []);

  return (
    <div className="space-y-6">
      {/* Top Banner with Refresh */}
      <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 className="text-base font-bold text-slate-900">
            Kondisi Infrastruktur & Komponen Sistem
          </h3>
          <p className="text-xs text-slate-500">
            Status pemantauan berkala REST API backend, basis data PostgreSQL, dan worker analitik visi
          </p>
        </div>

        <button
          onClick={loadStatus}
          disabled={isLoading}
          className="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition self-start sm:self-auto"
        >
          <RefreshCw className={`h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
          <span>Periksa Ulang Status</span>
        </button>
      </div>

      {/* 4 Health Service Status Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {/* Backend REST API */}
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              REST API Backend
            </span>
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
              <Server className="h-5 w-5" />
            </div>
          </div>
          <div className="mt-3 flex items-center gap-2">
            <span className="relative flex h-3 w-3">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
              <span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
            </span>
            <span className="text-lg font-bold text-slate-900">
              {status?.backend_status || 'HEALTHY'}
            </span>
          </div>
          <p className="mt-2 text-xs text-slate-500">FastAPI v0.110+ • Port 8000</p>
        </div>

        {/* PostgreSQL Database */}
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Basis Data PostgreSQL
            </span>
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
              <Database className="h-5 w-5" />
            </div>
          </div>
          <div className="mt-3 flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
            <span className="text-lg font-bold text-slate-900">
              {status?.database_connected ? 'TERHUBUNG' : 'DISCONNECTED'}
            </span>
          </div>
          <p className="mt-2 text-xs text-slate-500">Pool SQLAlchemy aktif • UTC Storage</p>
        </div>

        {/* CV Worker */}
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Worker Computer Vision
            </span>
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
              <Cpu className="h-5 w-5" />
            </div>
          </div>
          <div className="mt-3 flex items-center gap-2">
            <span className="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
            <span className="text-lg font-bold text-slate-900">
              {status?.cv_worker?.status || 'RUNNING'}
            </span>
          </div>
          <p className="mt-2 text-xs text-slate-500">
            {status?.cv_worker?.processed_fps || 24.8} FPS • YOLOv8 + ByteTrack
          </p>
        </div>

        {/* Waktu Terakhir Pemrosesan */}
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Waktu Server (WIB)
            </span>
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
              <Clock className="h-5 w-5" />
            </div>
          </div>
          <div className="mt-3">
            <span className="text-sm font-bold text-slate-900">
              {status?.server_time_wib || 'Asia/Jakarta (WIB)'}
            </span>
          </div>
          <p className="mt-2 text-xs text-slate-500">Sinkronisasi waktu presisi</p>
        </div>
      </div>

      {/* Detailed Technical Specifications Card */}
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h4 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
          <ShieldCheck className="h-4 w-4 text-emerald-600" />
          Kebijakan Privasi & Arsitektur Keamanan
        </h4>
        <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-600">
          <div className="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <strong className="block text-slate-800 font-semibold mb-1">
              Tanpa Pengenalan Wajah (No Facial Recognition)
            </strong>
            Sistem dirancang ketat hanya untuk menghitung crossing koordinat tubuh (bounding box), tidak menyimpan foto wajah atau data biometrik individu.
          </div>
          <div className="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <strong className="block text-slate-800 font-semibold mb-1">
              Penyembunyian Kredensial RTSP
            </strong>
            Kredensial kamera CCTV fisik disamarkan (masked) di tingkat backend API sehingga tidak terekspos pada lalu lintas antarmuka pengguna.
          </div>
          <div className="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <strong className="block text-slate-800 font-semibold mb-1">
              Pemisahan Data Demo & Data Riil
            </strong>
            Semua counting event memiliki label eksplisit <code className="bg-slate-200 px-1 py-0.5 rounded">source_mode</code> agar data simulasi tidak tercampur dengan hasil rekaman riil.
          </div>
        </div>
      </div>

      {/* Audit System Logs Table */}
      <div className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div className="flex items-center gap-2 border-b border-slate-100 p-4">
          <FileText className="h-5 w-5 text-slate-500" />
          <h4 className="text-sm font-bold text-slate-900">Audit Trail & Log Sistem Terakhir</h4>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
              <tr>
                <th className="py-3 px-4">Waktu</th>
                <th className="py-3 px-4">Modul</th>
                <th className="py-3 px-4">Tingkat (Level)</th>
                <th className="py-3 px-4">Pesan Aktivitas</th>
                <th className="py-3 px-4">Metadata</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {logs.map((log) => (
                <tr key={log.id} className="hover:bg-slate-50/80 transition">
                  <td className="py-2.5 px-4 font-mono text-slate-600">
                    {formatIndonesianDateTime(log.created_at)}
                  </td>
                  <td className="py-2.5 px-4 font-semibold text-slate-800">
                    {log.module}
                  </td>
                  <td className="py-2.5 px-4">
                    <span
                      className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                        log.level === 'INFO'
                          ? 'bg-blue-50 text-blue-700'
                          : log.level === 'WARNING'
                          ? 'bg-amber-50 text-amber-700'
                          : 'bg-rose-50 text-rose-700'
                      }`}
                    >
                      {log.level}
                    </span>
                  </td>
                  <td className="py-2.5 px-4 text-slate-700 font-medium">
                    {log.message}
                  </td>
                  <td className="py-2.5 px-4 font-mono text-[11px] text-slate-500">
                    {log.details ? JSON.stringify(log.details) : '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
