import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import {
  LayoutDashboard,
  TrendingUp,
  History,
  Camera,
  Activity,
  LogOut,
  Flame,
  ShieldCheck,
  X
} from 'lucide-react';
import { useAuth } from '../../context/AuthContext';

interface SidebarProps {
  isOpen: boolean;
  onClose: () => void;
}

export const Sidebar: React.FC<SidebarProps> = ({ isOpen, onClose }) => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const navItems = [
    { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { to: '/analytics', label: 'Analisa Keramaian', icon: TrendingUp },
    { to: '/history', label: 'Riwayat Hitungan', icon: History },
    { to: '/cameras', label: 'Pengaturan CCTV', icon: Camera },
    { to: '/system', label: 'Status Sistem', icon: Activity },
  ];

  return (
    <>
      {/* Mobile Backdrop */}
      {isOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
          onClick={onClose}
        />
      )}

      {/* Sidebar Container */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-navy-900 text-slate-200 transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 ${
          isOpen ? 'translate-x-0' : '-translate-x-0 -translate-x-full'
        }`}
      >
        {/* Brand Header */}
        <div className="flex h-20 items-center justify-between border-b border-navy-800 px-6">
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-tr from-brand-orange to-amber-500 shadow-lg shadow-orange-500/20">
              <Flame className="h-6 w-6 text-white" />
            </div>
            <div>
              <h1 className="text-base font-bold tracking-tight text-white">Ayam Bang Jungko</h1>
              <p className="text-xs font-medium text-slate-400">CCTV Crowd Analytics</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-navy-800 hover:text-white lg:hidden"
            aria-label="Tutup Menu"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Demo Mode Badge Banner */}
        <div className="mx-4 mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-300">
          <div className="flex items-center gap-1.5 font-semibold">
            <span className="relative flex h-2 w-2">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
              <span className="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
            </span>
            <span>DATA MODE: DEMO SIMULASI</span>
          </div>
          <p className="mt-1 text-[11px] text-slate-300 leading-relaxed">
            Data statistik tersaji berbasis simulasi deterministik Ayam Bang Jungko.
          </p>
        </div>

        {/* Navigation Links */}
        <nav className="flex-1 space-y-1.5 px-4 py-5 overflow-y-auto">
          <div className="px-3 pb-2 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
            Menu Operasional
          </div>
          {navItems.map((item) => {
            const Icon = item.icon;
            return (
              <NavLink
                key={item.to}
                to={item.to}
                onClick={onClose}
                className={({ isActive }) =>
                  `flex items-center gap-3.5 rounded-xl px-4 py-3 text-sm font-medium transition-all ${
                    isActive
                      ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 font-semibold'
                      : 'text-slate-300 hover:bg-navy-800 hover:text-white'
                  }`
                }
              >
                <Icon className="h-5 w-5 shrink-0" />
                <span>{item.label}</span>
              </NavLink>
            );
          })}
        </nav>

        {/* User Card & Logout */}
        <div className="border-t border-navy-800 p-4">
          <div className="flex items-center justify-between rounded-xl bg-navy-800/60 p-3">
            <div className="flex items-center gap-3 overflow-hidden">
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-700 text-xs font-bold text-white">
                {user?.full_name ? user.full_name.charAt(0).toUpperCase() : 'M'}
              </div>
              <div className="truncate">
                <p className="truncate text-xs font-semibold text-white">
                  {user?.full_name || 'Manager Operasional'}
                </p>
                <p className="truncate text-[11px] text-slate-400">
                  {user?.role === 'manager' ? 'Operational Manager' : user?.email || 'admin@ayambangjungko.com'}
                </p>
              </div>
            </div>
            <button
              onClick={handleLogout}
              title="Keluar (Logout)"
              className="rounded-lg p-2 text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition"
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>

          <div className="mt-3 flex items-center justify-between px-2 text-[11px] text-slate-400">
            <span className="flex items-center gap-1">
              <ShieldCheck className="h-3.5 w-3.5 text-emerald-400" />
              Sistem Aktif
            </span>
            <span>v1.0.0 (WIB)</span>
          </div>
        </div>
      </aside>
    </>
  );
};
