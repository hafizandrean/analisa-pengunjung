import React, { useState, useEffect } from 'react';
import {
  TrendingUp,
  Clock,
  Calendar,
  Layers,
  ArrowUpRight,
  ArrowDownRight,
  Filter,
  Flame,
  AlertTriangle,
  Info
} from 'lucide-react';
import { api } from '../services/api';
import {
  HourlyTrafficItem,
  DailyTrafficItem,
  PeakHourItem,
  PeriodComparisonItem
} from '../types/api';
import { HourlyTrafficChart } from '../components/charts/HourlyTrafficChart';
import { DailyTrendChart } from '../components/charts/DailyTrendChart';
import { EmptyState } from '../components/EmptyState';
import { formatNumber } from '../utils/formatters';

export const AnalyticsPage: React.FC = () => {
  const [hourlyData, setHourlyData] = useState<HourlyTrafficItem[]>([]);
  const [dailyData, setDailyData] = useState<DailyTrafficItem[]>([]);
  const [peakHours, setPeakHours] = useState<PeakHourItem[]>([]);
  const [comparison, setComparison] = useState<PeriodComparisonItem[]>([]);
  const [selectedRange, setSelectedRange] = useState('7days');
  const [showEmptyState, setShowEmptyState] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  const loadData = async () => {
    setIsLoading(true);
    try {
      const [hRes, dRes, pRes, cRes] = await Promise.all([
        api.analytics.getHourly(),
        api.analytics.getDaily(7),
        api.analytics.getPeakHours(),
        api.analytics.getComparison()
      ]);
      setHourlyData(hRes);
      setDailyData(dRes);
      setPeakHours(pRes);
      setComparison(cRes);
    } catch (err) {
      console.error('Gagal memuat analitik:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [selectedRange]);

  return (
    <div className="space-y-6">
      {/* Filters & Range Controls */}
      <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
            <Filter className="h-4 w-4 text-slate-400" />
            <span>Rentang Waktu:</span>
          </div>

          <button
            onClick={() => { setSelectedRange('today'); setShowEmptyState(false); }}
            className={`rounded-xl px-3 py-1.5 text-xs font-medium transition ${
              selectedRange === 'today'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
            }`}
          >
            Hari Ini
          </button>
          <button
            onClick={() => { setSelectedRange('7days'); setShowEmptyState(false); }}
            className={`rounded-xl px-3 py-1.5 text-xs font-medium transition ${
              selectedRange === '7days'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
            }`}
          >
            7 Hari Terakhir
          </button>
          <button
            onClick={() => { setSelectedRange('30days'); setShowEmptyState(false); }}
            className={`rounded-xl px-3 py-1.5 text-xs font-medium transition ${
              selectedRange === '30days'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
            }`}
          >
            30 Hari Terakhir
          </button>

          <button
            onClick={() => setShowEmptyState(!showEmptyState)}
            className="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
          >
            Simulasi Filter Kosong
          </button>
        </div>

        <div className="text-xs font-medium text-slate-500">
          Zona Waktu: <span className="font-semibold text-slate-800">WIB (Asia/Jakarta, UTC+7)</span>
        </div>
      </div>

      {showEmptyState ? (
        <EmptyState
          title="Tidak Ada Data Pengunjung pada Rentang Ini"
          description="Kamera tidak mendeteksi rekaman crossing pada rentang filter yang dipilih. Silakan pilih rentang tanggal lain atau gunakan data demo."
          action={{
            label: "Kembali ke 7 Hari Terakhir",
            onClick: () => {
              setSelectedRange('7days');
              setShowEmptyState(false);
            }
          }}
        />
      ) : (
        <>
          {/* Peak-Hours Identification Section */}
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                  <Flame className="h-5 w-5 text-amber-500" />
                  Identifikasi Jam Sibuk (Peak-Hour Identification)
                </h3>
                <p className="text-xs text-slate-500">
                  Waktu okupansi keramaian tertinggi yang terverifikasi dari akumulasi crossing garis CCTV
                </p>
              </div>
              <span className="rounded-lg bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 self-start sm:self-auto">
                Shift Resto Ayam Bang Jungko
              </span>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th className="pb-3">Jendela Waktu Operasional</th>
                    <th className="pb-3">Jumlah Pengunjung</th>
                    <th className="pb-3">Status Kepadatan</th>
                    <th className="pb-3">Kontribusi Terhadap Harian</th>
                    <th className="pb-3">Rekomendasi Operasional Terukur</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {peakHours.map((item, idx) => (
                    <tr key={idx} className="hover:bg-slate-50/80 transition">
                      <td className="py-3 font-semibold text-slate-800">
                        {item.time_window}
                      </td>
                      <td className="py-3 font-mono text-sm font-bold text-slate-900">
                        {item.count} orang
                      </td>
                      <td className="py-3">
                        {item.occupancy_status === 'PADAT' ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 font-semibold text-rose-700">
                            <span className="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            PADAT
                          </span>
                        ) : item.occupancy_status === 'SEDANG' ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 font-semibold text-amber-700">
                            <span className="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            SEDANG
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 font-semibold text-emerald-700">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            LENGANG
                          </span>
                        )}
                      </td>
                      <td className="py-3">
                        <div className="flex items-center gap-2">
                          <div className="h-2 w-24 rounded-full bg-slate-100 overflow-hidden">
                            <div
                              className="h-full bg-blue-600 rounded-full"
                              style={{ width: `${item.percentage_of_daily}%` }}
                            ></div>
                          </div>
                          <span className="font-semibold text-slate-700">
                            {item.percentage_of_daily}%
                          </span>
                        </div>
                      </td>
                      <td className="py-3 text-slate-600">
                        {item.occupancy_status === 'PADAT'
                          ? 'Perkuat kesiapan dapur (pre-cooking) & pastikan 2 kasir aktif.'
                          : 'Waktu ideal pergantian shift & pembersihan dining area.'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Charts Row */}
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="mb-4">
                <h3 className="text-base font-bold text-slate-900">
                  Distribusi Pengunjung per Jam (Hourly Distribution)
                </h3>
                <p className="text-xs text-slate-500">
                  Visualisasi volume lalu lintas dua arah (Masuk vs Keluar)
                </p>
              </div>
              <HourlyTrafficChart data={hourlyData} />
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
              <div className="mb-4">
                <h3 className="text-base font-bold text-slate-900">
                  Tren Pengunjung Harian & Mingguan
                </h3>
                <p className="text-xs text-slate-500">
                  Total pengunjung per hari memperlihatkan pola puncak di akhir pekan
                </p>
              </div>
              <DailyTrendChart data={dailyData} />
            </div>
          </div>

          {/* Period Comparison Section */}
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <h3 className="text-base font-bold text-slate-900">
                  Perbandingan Periode Operasional (Period Comparison)
                </h3>
                <p className="text-xs text-slate-500">
                  Evaluasi pertumbuhan pengunjung minggu ini dibandingkan minggu sebelumnya
                </p>
              </div>
              <span className="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                Minggu Berjalan vs Minggu Lalu
              </span>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th className="pb-3">Hari</th>
                    <th className="pb-3">Minggu Ini</th>
                    <th className="pb-3">Minggu Sebelumnya</th>
                    <th className="pb-3">Perubahan Persentase</th>
                    <th className="pb-3">Indikator Tren</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {comparison.map((row, idx) => (
                    <tr key={idx} className="hover:bg-slate-50/80 transition">
                      <td className="py-2.5 font-semibold text-slate-800">
                        {row.period_label}
                      </td>
                      <td className="py-2.5 font-mono text-slate-900 font-bold">
                        {formatNumber(row.current_period_count)} orang
                      </td>
                      <td className="py-2.5 font-mono text-slate-500">
                        {formatNumber(row.previous_period_count)} orang
                      </td>
                      <td className="py-2.5">
                        <span
                          className={`font-semibold ${
                            row.change_percentage >= 0 ? 'text-emerald-600' : 'text-rose-600'
                          }`}
                        >
                          {row.change_percentage >= 0 ? '+' : ''}
                          {row.change_percentage.toFixed(1)}%
                        </span>
                      </td>
                      <td className="py-2.5">
                        {row.change_percentage >= 0 ? (
                          <span className="inline-flex items-center gap-1 text-emerald-600 font-medium">
                            <ArrowUpRight className="h-4 w-4" /> Naik
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 text-rose-600 font-medium">
                            <ArrowDownRight className="h-4 w-4" /> Turun
                          </span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </div>
  );
};
