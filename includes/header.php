<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/lang.php';

$currentUser = $_SESSION['user'] ?? ['full_name' => 'Manager Operasional', 'role' => 'manager', 'email' => 'admin@ayambangjungko.com'];
$currentPage = basename($_SERVER['PHP_SELF']);
$restaurantName = getSetting('restaurant_name', 'Ayam Bang Jungko');
$restaurantTagline = getSetting('restaurant_tagline', 'Pemantau Pengunjung');
$restaurantLogo = getSetting('restaurant_logo', '');
$logoScale = max(60, min(150, (int)getSetting('logo_scale', '100')));
$logoShape = getSetting('logo_shape', 'rounded-xl');
$logoBg = getSetting('logo_bg', 'white');

$headerLogoBgClass = 'bg-white border-white/20 shadow-md';
if ($logoBg === 'dark') $headerLogoBgClass = 'bg-slate-800 border-slate-700/60 shadow-lg';
elseif ($logoBg === 'transparent') $headerLogoBgClass = 'bg-transparent border-transparent shadow-none';

$headerLogoShapeClass = ($logoShape === 'rounded-full') ? 'rounded-full' : 'rounded-xl';

$timezoneLabel = getSetting('timezone_label', 'WIB (Asia/Jakarta)');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getSetting('language', 'id')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= htmlspecialchars($restaurantName) ?> Analytics</title>
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
                    <?php if (!empty($restaurantLogo) && file_exists(__DIR__ . '/../' . $restaurantLogo)): ?>
                        <div class="h-10 w-10 <?= $headerLogoShapeClass ?> <?= $headerLogoBgClass ?> flex items-center justify-center overflow-hidden shrink-0 border">
                            <img src="<?= htmlspecialchars($restaurantLogo) ?>" alt="Logo <?= htmlspecialchars($restaurantName) ?>" class="w-full h-full object-contain p-1" style="transform: scale(<?= $logoScale / 100 ?>);">
                        </div>
                    <?php else: ?>
                        <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 flex items-center justify-center font-bold text-lg text-white shadow-lg shadow-orange-500/20 shrink-0">
                            🍗
                        </div>
                    <?php endif; ?>
                    <div class="overflow-hidden">
                        <h1 class="text-sm font-bold tracking-tight text-white leading-tight"><?= htmlspecialchars($restaurantName) ?></h1>
                        <span class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider"><?= htmlspecialchars($restaurantTagline) ?></span>
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5">
                <div class="px-3 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?= __('nav_menu_operational') ?></div>
                
                <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'index.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span><?= __('nav_dashboard') ?></span>
                </a>

                <a href="analytics.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'analytics.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    <span><?= __('nav_traffic') ?></span>
                </a>

                <a href="history.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'history.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="history" class="w-4 h-4"></i>
                    <span><?= __('nav_history') ?></span>
                </a>

                <div class="pt-4 px-3 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?= __('nav_menu_config') ?></div>

                <a href="cameras.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'cameras.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="camera" class="w-4 h-4"></i>
                    <span><?= __('nav_camera') ?></span>
                </a>

                <a href="system.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'system.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="activity" class="w-4 h-4"></i>
                    <span><?= __('nav_system_status') ?></span>
                </a>

                <a href="settings.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $currentPage === 'settings.php' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span><?= __('nav_settings') ?></span>
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
                        <div class="text-[10px] text-slate-400 uppercase font-medium"><?= __('role_manager') ?></div>
                    </div>
                </div>
                <a href="logout.php" title="<?= __('logout') ?>" class="p-1.5 text-slate-400 hover:text-red-400 hover:bg-slate-700/50 rounded-lg transition-colors">
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
                    <?= __('system_active_normal') ?>
                </span>
                <span class="text-xs text-slate-300">|</span>
                <!-- Jam Sistem Digital Real-Time di Header dengan Keterangan Zona Waktu -->
                <?php
                $tzCode = 'WIB';
                if (strpos($timezoneLabel, 'WITA') !== false) $tzCode = 'WITA';
                elseif (strpos($timezoneLabel, 'WIT') !== false) $tzCode = 'WIT';
                elseif (strpos($timezoneLabel, 'UTC') !== false) $tzCode = 'UTC';
                ?>
                <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-slate-700 bg-slate-50 border border-slate-200/80 px-3 py-1.5 rounded-xl shadow-xs">
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-orange-500"></i>
                    <span id="topbarClock"><?= date('H:i:s') ?></span>
                    <span id="topbarClockTz" class="text-[10px] font-sans font-bold px-1.5 py-0.5 rounded-md bg-orange-100 text-orange-700 uppercase tracking-wider"><?= $tzCode ?></span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200/80 flex items-center gap-1.5">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span><?= date('d M Y') ?></span>
                </span>
            </div>
        </header>

        <!-- Page Body -->
        <main class="flex-1 p-8 max-w-7xl w-full mx-auto space-y-6">
