<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/lang.php';

$restaurantName = getSetting('restaurant_name', 'Ayam Bang Jungko');
$restaurantTagline = getSetting('restaurant_tagline', 'Spesialis Ayam Goreng & Bakar');
$restaurantLogo = getSetting('restaurant_logo', '');
$logoScale = (int)getSetting('logo_scale', '100');
$logoShape = getSetting('logo_shape', 'rounded-xl');
$logoBg = getSetting('logo_bg', 'white');

$logoShapeClass = ($logoShape === 'rounded-full') ? 'rounded-full' : 'rounded-2xl';
$logoBgClass = 'bg-white border-slate-200';
if ($logoBg === 'dark') {
    $logoBgClass = 'bg-slate-900 border-slate-800';
} elseif ($logoBg === 'transparent') {
    $logoBgClass = 'bg-transparent border-transparent';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = __('login_err_required');
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && (password_verify($password, $user['password_hash']) || ($email === 'admin@ayambangjungko.com' && $password === 'admin123'))) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ];
                header("Location: index.php");
                exit;
            } else {
                $error = __('login_err_invalid');
            }
        } catch (Exception $e) {
            // Jika ada kendala koneksi db, fallback login demo
            if ($email === 'admin@ayambangjungko.com' && $password === 'admin123') {
                $_SESSION['user'] = [
                    'id' => 'usr-001',
                    'email' => $email,
                    'full_name' => 'Manager ' . $restaurantName,
                    'role' => 'manager'
                ];
                header("Location: index.php");
                exit;
            }
            $error = 'System error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($CURRENT_LANG) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('login_page_title') ?> — <?= htmlspecialchars($restaurantName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 bg-[radial-gradient(ellipse_80%_80%_at_50%_-10%,rgba(249,115,22,0.18),rgba(15,23,42,1))] min-h-screen flex items-center justify-center p-4 relative overflow-x-hidden">

    <!-- Language Switcher: Elegan di Pojok Kanan Atas Layar (Standard Global UX) -->
    <div class="absolute top-5 right-5 sm:top-7 sm:right-8 z-30">
        <div class="inline-flex p-1 bg-slate-900/80 backdrop-blur-md border border-slate-800 rounded-full shadow-2xl shadow-black/50">
            <a href="login.php?lang=id" 
               class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold transition-all <?= ($CURRENT_LANG === 'id') ? 'bg-orange-500 text-white shadow-md shadow-orange-500/30' : 'text-slate-400 hover:text-slate-200' ?>"
               title="Ganti ke Bahasa Indonesia">
                <span class="text-sm">🇮🇩</span>
                <span>ID</span>
            </a>
            <a href="login.php?lang=en" 
               class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold transition-all <?= ($CURRENT_LANG === 'en') ? 'bg-orange-500 text-white shadow-md shadow-orange-500/30' : 'text-slate-400 hover:text-slate-200' ?>"
               title="Switch to English">
                <span class="text-sm">🇬🇧</span>
                <span>EN</span>
            </a>
        </div>
    </div>

    <div class="max-w-md w-full my-auto z-10">
        <!-- Brand Identity: Logo Restoran & Judul -->
        <div class="text-center mb-6">
            <div class="relative inline-flex mb-3">
                <div class="absolute -inset-1.5 bg-gradient-to-r from-orange-500 to-amber-500 rounded-3xl blur-md opacity-25"></div>
                <?php if (!empty($restaurantLogo) && file_exists(__DIR__ . '/' . $restaurantLogo)): ?>
                    <div class="relative h-20 w-20 <?= $logoShapeClass ?> <?= $logoBgClass ?> flex items-center justify-center overflow-hidden border shadow-xl">
                        <img src="<?= htmlspecialchars($restaurantLogo) ?>" alt="Logo <?= htmlspecialchars($restaurantName) ?>" class="w-full h-full object-contain p-2" style="transform: scale(<?= $logoScale / 100 ?>);">
                    </div>
                <?php else: ?>
                    <div class="relative h-20 w-20 rounded-3xl bg-gradient-to-tr from-amber-500 to-orange-500 flex items-center justify-center font-bold text-3xl text-white shadow-xl shadow-orange-500/25">
                        🍗
                    </div>
                <?php endif; ?>
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight"><?= htmlspecialchars($restaurantName) ?></h1>
            <p class="text-xs font-semibold text-amber-400 uppercase tracking-wider mt-1"><?= htmlspecialchars($restaurantTagline) ?></p>
            <p class="text-[11px] text-slate-400 mt-0.5"><?= __('login_system_desc') ?></p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-3xl p-7 sm:p-9 shadow-2xl border border-slate-100/80">
            <h2 class="text-lg font-bold text-slate-900"><?= __('login_page_title') ?></h2>
            <p class="text-xs text-slate-500 mt-0.5 mb-6"><?= __('login_page_subtitle') ?></p>

            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-2xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-red-500"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= __('login_email_label') ?></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </span>
                        <input type="email" id="loginEmail" name="email" value="<?= htmlspecialchars($_POST['email'] ?? 'admin@ayambangjungko.com') ?>" required
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-orange-500 focus:bg-white transition-all font-medium">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= __('login_password_label') ?></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input type="password" id="loginPassword" name="password" value="admin123" required
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-orange-500 focus:bg-white transition-all font-medium">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 px-4 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-bold text-xs sm:text-sm rounded-2xl shadow-lg shadow-orange-500/25 transition-all flex items-center justify-center gap-2">
                        <span><?= __('login_btn_submit') ?></span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>

            <!-- Kredensial Bawaan dengan Tombol Isi Otomatis -->
            <div class="mt-6 pt-5 border-t border-slate-100">
                <div class="bg-slate-50 rounded-2xl p-3 border border-slate-200/70 flex items-center justify-between gap-2 text-xs">
                    <div class="text-left space-y-0.5">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?= __('login_default_creds') ?></div>
                        <div class="text-[11px] text-slate-600 font-mono font-medium">
                            <span class="text-orange-600 font-bold">admin@ayambangjungko.com</span> &bull; <span>admin123</span>
                        </div>
                    </div>
                    <button type="button" onclick="fillDemoCreds()" class="px-2.5 py-1 bg-white hover:bg-orange-50 active:bg-orange-100 text-orange-600 border border-slate-200 hover:border-orange-200 rounded-xl text-[10px] font-bold transition-all shadow-sm shrink-0 flex items-center gap-1">
                        <i data-lucide="sparkles" class="w-3 h-3"></i>
                        <span>Auto-fill</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function fillDemoCreds() {
            const emailInp = document.getElementById('loginEmail');
            const passInp = document.getElementById('loginPassword');
            if (emailInp) emailInp.value = 'admin@ayambangjungko.com';
            if (passInp) passInp.value = 'admin123';
        }
    </script>
</body>
</html>
