import React, { useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Header } from './Header';

const PAGE_TITLES: Record<string, { title: string; subtitle: string }> = {
  '/dashboard': {
    title: 'Ringkasan Dashboard',
    subtitle: 'Pemantauan real-time arus pengunjung masuk dan keluar restoran Ayam Bang Jungko'
  },
  '/analytics': {
    title: 'Analisa Pola Keramaian',
    subtitle: 'Wawasan jam sibuk, perbandingan periode operasional, dan distribusi kunjungan'
  },
  '/history': {
    title: 'Riwayat Hitungan Pengunjung',
    subtitle: 'Catatan log detail deteksi crossing garis virtual CCTV dengan ekspor CSV'
  },
  '/cameras': {
    title: 'Pengaturan CCTV & Garis Virtual',
    subtitle: 'Konfigurasi sumber video kamera, koordinat virtual line, dan simulasi pengujian'
  },
  '/system': {
    title: 'Status Sistem & Audit Log',
    subtitle: 'Kondisi kesehatan REST API backend, basis data, dan worker computer vision'
  }
};

export const Layout: React.FC = () => {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const location = useLocation();

  const currentMeta = PAGE_TITLES[location.pathname] || {
    title: 'Ayam Bang Jungko Analytics',
    subtitle: 'Sistem Penghitungan Pengunjung Berbasis CCTV'
  };

  return (
    <div className="flex min-h-screen bg-slate-50 text-slate-800">
      {/* Sidebar */}
      <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      {/* Main Content Area */}
      <div className="flex flex-1 flex-col overflow-hidden">
        <Header
          onMenuToggle={() => setSidebarOpen(prev => !prev)}
          title={currentMeta.title}
          subtitle={currentMeta.subtitle}
        />

        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
          <div className="mx-auto max-w-7xl">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
};
