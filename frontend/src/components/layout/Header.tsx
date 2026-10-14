import React, { useState, useEffect } from 'react';
import { Menu, Clock, Radio, Sparkles, CheckCircle2 } from 'lucide-react';
import { isDemoModeActive, setDemoModeActive } from '../../services/api';

interface HeaderProps {
  onMenuToggle: () => void;
  title: string;
  subtitle?: string;
}

export const Header: React.FC<HeaderProps> = ({ onMenuToggle, title, subtitle }) => {
  const [currentTime, setCurrentTime] = useState<string>('');
  const [demoMode, setDemoMode] = useState<boolean>(isDemoModeActive());

  useEffect(() => {
    const updateTime = () => {
      const now = new Date();
      setCurrentTime(
        now.toLocaleTimeString('id-ID', {
          timeZone: 'Asia/Jakarta',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: false
        }) + ' WIB'
      );
    };

    updateTime();
    const interval = setInterval(updateTime, 1000);
    return () => clearInterval(interval);
  }, []);

  const toggleDemoMode = () => {
    const nextVal = !demoMode;
    setDemoMode(nextVal);
    setDemoModeActive(nextVal);
    window.location.reload();
  };

  return (
    <header className="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur-md sm:px-6 lg:px-8">
      {/* Left: Mobile Trigger & Title */}
      <div className="flex items-center gap-3">
        <button
          onClick={onMenuToggle}
          className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
          aria-label="Buka Menu Navigasi"
        >
          <Menu className="h-6 w-6" />
        </button>
        <div>
          <h1 className="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
            {title}
          </h1>
          {subtitle && (
            <p className="hidden text-xs text-slate-500 sm:block">{subtitle}</p>
          )}
        </div>
      </div>

      {/* Right: Clock & Mode Switcher */}
      <div className="flex items-center gap-3 sm:gap-4">
        {/* Real-time Clock */}
        <div className="hidden items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 md:flex">
          <Clock className="h-4 w-4 text-slate-500" />
          <span>{currentTime || 'Memuat waktu...'}</span>
        </div>

        {/* Live System Indicator */}
        <div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700">
          <span className="relative flex h-2.5 w-2.5">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
            <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
          </span>
          <span className="hidden sm:inline">Worker CCTV:</span>
          <span className="font-semibold text-emerald-700">ONLINE</span>
        </div>

        {/* Mode Toggle Button */}
        <button
          onClick={toggleDemoMode}
          title="Klik untuk beralih mode demo / backend"
          className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition ${
            demoMode
              ? 'border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'
              : 'border border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100'
          }`}
        >
          {demoMode ? (
            <>
              <Sparkles className="h-3.5 w-3.5 text-amber-600" />
              <span>Mode Demo</span>
            </>
          ) : (
            <>
              <Radio className="h-3.5 w-3.5 text-blue-600 animate-pulse" />
              <span>Live Backend</span>
            </>
          )}
        </button>
      </div>
    </header>
  );
};
