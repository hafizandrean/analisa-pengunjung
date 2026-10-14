import React, { useState, useEffect } from 'react';
import {
  Users,
  Calendar,
  Clock,
  ArrowDownLeft,
  ArrowUpRight,
  TrendingUp,
  Activity,
  Video,
  Filter,
  RefreshCw,
  SlidersHorizontal
} from 'lucide-react';
import { MetricCard } from '../components/MetricCard';
import { HourlyTrafficChart } from '../components/charts/HourlyTrafficChart';
import { DailyTrendChart } from '../components/charts/DailyTrendChart';
import { api } from '../services/api';
import {
  DashboardSummary,
  HourlyTrafficItem,
  DailyTrafficItem,
  CountingEvent
} from '../types/api';
import { formatNumber, formatIndonesianDateTime, formatTimeOnly } from '../utils/formatters';

export const DashboardPage: React.FC = () => {
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [hourlyData, setHourlyData] = useState<HourlyTrafficItem[]>([]);
  const [dailyData, setDailyData] = useState<DailyTrafficItem[]>([]);
  const [recentEvents, setRecentEvents] = useState<CountingEvent[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedCamera, setSelectedCamera] = useState('all');
  const [dateFilter, setDateFilter] = useState('today');

  const loadDashboardData = async () => {
    setIsLoading(true);
    try {
      const [sumRes, hourlyRes, dailyRes, eventsRes] = await Promise.all([
        api.dashboard.getSummary(),
        api.analytics.getHourly(),
        api.analytics.getDaily(7),
        api.events.getList({ page: 1, page_size: 8 })
      ]);
      setSummary(sumRes);
      setHourlyData(hourlyRes);
      setDailyData(dailyRes);
      setRecentEvents(eventsRes.items);
    } catch (err) {
      console.error('Gagal memuat data dashboard:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadDashboardData();
  }, [selectedCamera, dateFilter]);

  return (
    <div className="space-y-6">
      {/* Filters & Refresh Bar */}
      <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
            <Filter className="h-4 w-4 text-slate-400" />
            <span>Filter Tampilan:</span>
          </div>

          <select
            value={selectedCamera}
            onChange={(e) => setSelectedCamera(e.target.value)}
            className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 focus:border-blue-500 focus:outline-none"
          >
            <option value="all">Semua Kamera (Pintu Masuk Utama)</option>
            <option value="cam-1">CCTV Pintu Masuk Utama</option>
            <option value="cam-2">CCTV Area Dining Lantai 2</option>
          </select>

          <select
            value={dateFilter}
            onChange={(e) => setDateFilter(e.target.value)}
            className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 focus:border-blue-500 focus:outline-none"
          >
            <option value="today">Hari Ini (13 Okt 2026)</option>
            <option value="yesterday">Kemarin (12 Okt 2026)</option>
            <option value="7days">7 Hari Terakhir</option>
          </select>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={loadDashboardData}
            disabled={isLoading}
            className="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? 'animate-spin' : ''}`} />
            <span>Perbarui Data</span>
          </button>
        </div>
      </div>

      {/* 4 Key Stat Metrics */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          title="Total Pengunjung Hari Ini"
          value={summary?.total_today || 482}
          subValue={`Masuk: ${summary?.total_today_in || 254} | Keluar: ${summary?.total_today_out || 228}`}
          icon={Users}
          iconColor="bg-emerald-50 text-emerald-600"
          trend={{
            percentage: summary?.trend_today_vs_yesterday || 12.4,
            label: 'vs kemarin'
          }}
          highlight
        />

        <MetricCard
          title="Total Pengunjung Minggu Ini"
          value={summary?.total_week || 3120}
          icon={Calendar}
          iconColor="bg-blue-50 text-blue-600"
          trend={{
            percentage: summary?.trend_week_vs_last_week || 8.1,
            label: 'vs minggu lalu'
          }}
        />

        <MetricCard
          title="Rata-Rata Pengunjung / Hari"
          value={summary?.average_daily ? Math.round(summary.average_daily) : 446}
          subValue="orang / hari"
          icon={TrendingUp}
          iconColor="bg-amber-50 text-amber-600"
        />

        <MetricCard
          title="Jam Paling Sibuk (Busiest Hour)"
          value={summary?.busiest_hour || "18:00 - 19:00"}
          subValue={`${summary?.busiest_hour_count || 86} orang (Makan Malam)`}
          icon={Clock}
          iconColor="bg-purple-50 text-purple-600"
        />
      </div>

      {/* Charts Grid */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Hourly Distribution */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="mb-4 flex items-center justify-between">
            <div>
              <h3 className="text-base font-bold text-slate-900">
                Pola Lalu Lintas Pengunjung per Jam
              </h3>
              <p className="text-xs text-slate-500">
                Perbandingan arus pengunjung masuk dan keluar sepanjang jam buka resto
              </p>
            </div>
            <span className="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
              Hari Ini
            </span>
          </div>
          <HourlyTrafficChart data={hourlyData} />
        </div>

        {/* Daily Trend */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="mb-4 flex items-center justify-between">
            <div>
              <h3 className="text-base font-bold text-slate-900">
                Tren Pengunjung 7 Hari Terakhir
              </h3>
              <p className="text-xs text-slate-500">
                Akumulasi trafik harian memperlihatkan lonjakan saat akhir pekan
              </p>
            </div>
            <span className="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
              7 Hari
            </span>
          </div>
          <DailyTrendChart data={dailyData} />
        </div>
      </div>

      {/* Operational Status & Recent Events Section */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Video Processing Status Card */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-1">
          <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
            <Video className="h-5 w-5 text-blue-600" />
            <h3 className="text-base font-bold text-slate-900">Status Pemrosesan CCTV</h3>
          </div>

          <div className="mt-4 space-y-4">
            <div className="flex items-center justify-between rounded-xl bg-slate-50 p-3">
              <span className="text-xs font-medium text-slate-500">Kondisi Worker:</span>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                <span className="h-1.5 w-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                RUNNING (Aktif)
              </span>
            </div>

            <div className="space-y-2 text-xs">
              <div className="flex justify-between py-1 border-b border-slate-100">
                <span className="text-slate-500">Kamera Sumber:</span>
                <span className="font-semibold text-slate-800">Pintu Masuk Utama</span>
              </div>
              <div className="flex justify-between py-1 border-b border-slate-100">
                <span className="text-slate-500">Resolusi / Frame Rate:</span>
                <span className="font-semibold text-slate-800">1280x720 @ 24.8 FPS</span>
              </div>
              <div className="flex justify-between py-1 border-b border-slate-100">
                <span className="text-slate-500">Detektor & Pelacak:</span>
                <span className="font-semibold text-slate-800">YOLOv8 + ByteTrack</span>
              </div>
              <div className="flex justify-between py-1">
                <span className="text-slate-500">Mode Eksekusi:</span>
                <span className="font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded">
                  DEMO SIMULASI
                </span>
              </div>
            </div>

            <div className="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-[11px] text-blue-800 leading-relaxed">
              <strong>Catatan Operasional:</strong> Worker memproses virtual line crossing secara asinkron tanpa membebani REST API.
            </div>
          </div>
        </div>

        {/* Recent Counting Events Table */}
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
          <div className="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
              <h3 className="text-base font-bold text-slate-900">
                Deteksi Penyeberangan Garis Terbaru
              </h3>
              <p className="text-xs text-slate-500">
                Catatan real-time orang melintasi garis virtual CCTV
              </p>
            </div>
            <span className="text-xs font-semibold text-blue-600">
              Live Feed Stream
            </span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                  <th className="pb-3">Waktu (WIB)</th>
                  <th className="pb-3">Arah</th>
                  <th className="pb-3">Kamera</th>
                  <th className="pb-3">Track ID</th>
                  <th className="pb-3">Kepercayaan</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {recentEvents.map((event) => (
                  <tr key={event.id} className="hover:bg-slate-50/80 transition">
                    <td className="py-2.5 font-medium text-slate-700">
                      {formatTimeOnly(event.event_timestamp)} WIB
                    </td>
                    <td className="py-2.5">
                      {event.direction === 'IN' ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 font-semibold text-emerald-700">
                          <ArrowDownLeft className="h-3.5 w-3.5" />
                          MASUK
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 font-semibold text-blue-700">
                          <ArrowUpRight className="h-3.5 w-3.5" />
                          KELUAR
                        </span>
                      )}
                    </td>
                    <td className="py-2.5 text-slate-600">
                      {event.camera_name || 'CCTV Pintu Masuk'}
                    </td>
                    <td className="py-2.5 font-mono text-slate-600">
                      #{event.track_id || '-'}
                    </td>
                    <td className="py-2.5">
                      <span className="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700">
                        {event.confidence ? `${Math.round(event.confidence * 100)}%` : '92%'}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};
