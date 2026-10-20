<?php
session_start();

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan kata sandi wajib diisi.';
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
                $error = 'Email atau kata sandi tidak valid.';
            }
        } catch (Exception $e) {
            // Jika ada kendala koneksi db, fallback login demo
            if ($email === 'admin@ayambangjungko.com' && $password === 'admin123') {
                $_SESSION['user'] = [
                    'id' => 'usr-001',
                    'email' => $email,
                    'full_name' => 'Manager Ayam Bang Jungko',
                    'role' => 'manager'
                ];
                header("Location: index.php");
                exit;
            }
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Ayam Bang Jungko Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="inline-flex h-16 w-16 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 items-center justify-center font-bold text-3xl text-white shadow-xl shadow-orange-500/25 mb-4">
                🍗
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Ayam Bang Jungko</h1>
            <p class="text-sm text-slate-400 mt-1">Sistem Pemantau Pengunjung Restoran</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-3xl p-8 shadow-2xl border border-slate-100">
            <h2 class="text-lg font-bold text-slate-800 mb-1">Masuk ke Dashboard</h2>
            <p class="text-xs text-slate-500 mb-6">Masuk untuk melihat data pengunjung dan waktu ramai restoran.</p>

            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-red-500"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email Pengguna</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </span>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? 'admin@ayambangjungko.com') ?>" required
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-orange-500 focus:bg-white transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kata Sandi</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input type="password" name="password" value="admin123" required
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-orange-500 focus:bg-white transition-all">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-semibold text-sm rounded-xl shadow-lg shadow-orange-500/25 transition-all flex items-center justify-center gap-2">
                        <span>Masuk Sekarang</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                <div class="text-[11px] text-slate-500 bg-slate-50 p-2.5 rounded-xl border border-slate-200/60 inline-block w-full">
                    <span class="font-semibold text-slate-700">Kredensial Bawaan:</span><br>
                    Email: <code class="text-orange-600 font-bold">admin@ayambangjungko.com</code> &bull; Sandi: <code class="text-orange-600 font-bold">admin123</code>
                </div>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
