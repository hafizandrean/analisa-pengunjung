import React, { useState, useEffect } from 'react';
import {
  Search,
  Download,
  Filter,
  ArrowDownLeft,
  ArrowUpRight,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  FileSpreadsheet
} from 'lucide-react';
import { api } from '../services/api';
import { CountingEvent } from '../types/api';
import { formatIndonesianDateTime, formatTimeOnly } from '../utils/formatters';

export const HistoryPage: React.FC = () => {
  const [events, setEvents] = useState<CountingEvent[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [directionFilter, setDirectionFilter] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [isLoading, setIsLoading] = useState(true);

  const fetchEvents = async () => {
    setIsLoading(true);
    try {
      const res = await api.events.getList({
        page,
        page_size: pageSize,
        direction: directionFilter || undefined,
        search: searchQuery || undefined
      });
      setEvents(res.items);
      setTotal(res.total);
    } catch (err) {
      console.error('Gagal mengambil data riwayat:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchEvents();
  }, [page, pageSize, directionFilter, searchQuery]);

  const totalPages = Math.ceil(total / pageSize) || 1;

  // Export to CSV
  const handleExportCSV = () => {
    if (events.length === 0) return;

    const headers = [
      'Event ID',
      'Waktu Deteksi (WIB)',
      'Sumber Kamera',
      'Arah Crossing',
      'Jumlah Hitungan',
      'Track ID Pelacakan',
      'Tingkat Kepercayaan',
      'Mode Sumber Data'
    ];

    const rows = events.map((e) => [
      e.id,
      formatIndonesianDateTime(e.event_timestamp),
      `"${e.camera_name || 'CCTV Utama'}"`,
      e.direction === 'IN' ? 'MASUK' : 'KELUAR',
      e.count_value,
      e.track_id || '-',
      e.confidence ? `${Math.round(e.confidence * 100)}%` : '90%',
      e.source_mode
    ]);

    const csvContent =
      'data:text/csv;charset=utf-8,' +
      [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `riwayat_pengunjung_ayam_bang_jungko_${Date.now()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-6">
      {/* Search, Filters, and Export Bar */}
      <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        {/* Search Input */}
        <div className="relative flex-1 max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <input
            type="text"
            placeholder="Cari berdasarkan Track ID, kamera, atau arah..."
            value={searchQuery}
            onChange={(e) => {
              setSearchQuery(e.target.value);
              setPage(1);
            }}
            className="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-4 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:outline-none"
          />
        </div>

        {/* Direction Filter and Export CSV */}
        <div className="flex flex-wrap items-center gap-3">
          <select
            value={directionFilter}
            onChange={(e) => {
              setDirectionFilter(e.target.value);
              setPage(1);
            }}
            className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 focus:border-blue-500 focus:outline-none"
          >
            <option value="">Semua Arah (IN & OUT)</option>
            <option value="IN">Hanya Pengunjung Masuk (IN)</option>
            <option value="OUT">Hanya Pengunjung Keluar (OUT)</option>
          </select>

          <button
            onClick={handleExportCSV}
            className="flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition"
          >
            <FileSpreadsheet className="h-4 w-4" />
            <span>Ekspor CSV</span>
          </button>
        </div>
      </div>

      {/* Main Events Table */}
      <div className="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
              <tr>
                <th className="py-3.5 px-4">Waktu (WIB)</th>
                <th className="py-3.5 px-4">Sumber Kamera</th>
                <th className="py-3.5 px-4">Arah Crossing</th>
                <th className="py-3.5 px-4 text-center">Jumlah Hitungan</th>
                <th className="py-3.5 px-4">Track ID</th>
                <th className="py-3.5 px-4">Kepercayaan</th>
                <th className="py-3.5 px-4">Sumber Data</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {events.length > 0 ? (
                events.map((evt) => (
                  <tr key={evt.id} className="hover:bg-slate-50/80 transition">
                    <td className="py-3 px-4 font-medium text-slate-700">
                      {formatIndonesianDateTime(evt.event_timestamp)}
                    </td>
                    <td className="py-3 px-4 font-semibold text-slate-800">
                      {evt.camera_name || 'CCTV Pintu Masuk Utama'}
                    </td>
                    <td className="py-3 px-4">
                      {evt.direction === 'IN' ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 font-semibold text-emerald-700">
                          <ArrowDownLeft className="h-3.5 w-3.5" />
                          MASUK (IN)
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 font-semibold text-blue-700">
                          <ArrowUpRight className="h-3.5 w-3.5" />
                          KELUAR (OUT)
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-4 text-center font-mono font-bold text-slate-900">
                      +{evt.count_value}
                    </td>
                    <td className="py-3 px-4 font-mono text-slate-600">
                      #{evt.track_id || '-'}
                    </td>
                    <td className="py-3 px-4">
                      <span className="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                        {evt.confidence ? `${Math.round(evt.confidence * 100)}%` : '92%'}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800 border border-amber-200">
                        {evt.source_mode}
                      </span>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-xs text-slate-500">
                    Tidak ada catatan riwayat yang cocok dengan kata kunci pencarian.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Bar */}
        <div className="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-xs text-slate-500">
            Menampilkan <span className="font-semibold text-slate-700">{events.length}</span> dari{' '}
            <span className="font-semibold text-slate-700">{total}</span> total baris riwayat
          </div>

          <div className="flex items-center gap-2">
            <select
              value={pageSize}
              onChange={(e) => {
                setPageSize(Number(e.target.value));
                setPage(1);
              }}
              className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700"
            >
              <option value="10">10 per halaman</option>
              <option value="20">20 per halaman</option>
              <option value="50">50 per halaman</option>
            </select>

            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="rounded-lg border border-slate-200 bg-white p-1 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            <span className="text-xs font-medium text-slate-700">
              Halaman {page} dari {totalPages}
            </span>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page >= totalPages}
              className="rounded-lg border border-slate-200 bg-white p-1 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
