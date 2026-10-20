<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentUser = $_SESSION['user'] ?? ['full_name' => 'Manager Operasional', 'role' => 'manager', 'email' => 'admin@ayambangjungko.com'];
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Ayam Bang Jungko Analytics</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex">

    <!-- Sidebar Desktop -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col justify-between shrink-0 min-h-screen border-r border-slate-800 shadow-xl">
        <div>
            <!-- Brand Logo -->
            <div class="p-6 border-b border-slate-800/80">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 flex items-center justify-center font-bold text-lg text-white shadow-lg shadow-orange-500/20">
                        🍗
                    </div>
                    <div>
                        <h1 class="text-sm font-bold tracking-tight text-white leading-tight">Ayam Bang Jungko</h1>
                        <span class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider">Pemantau Pengunjung</span>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5">
                <div class="px-3 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Menu Operasional</div>
                
                <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'index.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>Dashboard Utama</span>
                </a>

                <a href="analytics.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'analytics.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    <span>Pola Keramaian</span>
                </a>

                <a href="history.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'history.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="history" class="w-4 h-4"></i>
                    <span>Riwayat Crossing</span>
                </a>

                <div class="pt-4 px-3 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Konfigurasi & AI</div>

                <a href="cameras.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'cameras.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="camera" class="w-4 h-4"></i>
                    <span>Kamera & Garis Virtual</span>
                </a>

                <a href="system.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'system.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="activity" class="w-4 h-4"></i>
                    <span>Status Sistem</span>
                </a>
            </nav>
        </div>

        <!-- User Profile Card & Logout -->
        <div class="p-4 border-t border-slate-800/80">
            <div class="flex items-center justify-between p-2 rounded-xl bg-slate-800/60">
                <div class="flex items-center gap-2.5 overflow-hidden">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-semibold text-amber-400">
                        <?= strtoupper(substr($currentUser['full_name'], 0, 2)) ?>
                    </div>
                    <div class="truncate">
                        <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                        <div class="text-[10px] text-slate-400 uppercase font-medium"><?= htmlspecialchars($currentUser['role']) ?></div>
                    </div>
                </div>
                <a href="logout.php" title="Keluar" class="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700/50 rounded-lg transition-colors">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-h-screen overflow-x-hidden">
        <!-- Topbar -->
        <header class="h-16 bg-white border-b border-slate-200/80 px-8 flex items-center justify-between sticky top-0 z-30 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Sistem Aktif & Normal
                </span>
                <span class="text-xs text-slate-400">|</span>
                <span class="text-xs text-slate-500 font-medium">Zona Waktu: Asia/Jakarta (WIB)</span>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200/60">
                    📅 <?= date('d M Y') ?>
                </span>
            </div>
        </header>

        <!-- Page Body -->
        <main class="flex-1 p-8 max-w-7xl w-full mx-auto space-y-6">
