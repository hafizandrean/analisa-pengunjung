<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/settings.php';

$pageTitle = 'Pengaturan & Kustomisasi Sistem';
$settings = getAllSettings();

include __DIR__ . '/includes/header.php';
?>

<!-- Title & Quick Status -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900"><?= __('settings_title') ?></h2>
        <p class="text-xs text-slate-500 mt-1"><?= __('settings_subtitle') ?></p>
    </div>

    <!-- Zona Waktu Status Display in Settings -->
    <div class="flex items-center gap-2.5 bg-white border border-slate-200/80 px-4 py-2 rounded-2xl shadow-sm text-xs font-semibold text-slate-700">
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
        <span class="text-slate-400"><?= __('timezone_pill') ?></span>
        <span class="font-bold text-orange-600"><?= htmlspecialchars($settings['timezone_label'] ?? 'WIB (Asia/Jakarta)') ?></span>
    </div>
</div>

<form id="settingsForm" enctype="multipart/form-data" class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- KARTU 1: PENGATURAN WAKTU & OPERASIONAL -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-10 h-10 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900"><?= __('card_time_title') ?></h3>
                    <p class="text-[11px] text-slate-500"><?= __('card_time_desc') ?></p>
                </div>
            </div>

            <!-- Zona Waktu -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= __('field_timezone') ?></label>
                <select name="timezone" id="inp_timezone" class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-800 focus:outline-none focus:border-orange-500">
                    <option value="Asia/Jakarta" <?= ($settings['timezone'] ?? '') === 'Asia/Jakarta' ? 'selected' : '' ?>>
                        WIB — Waktu Indonesia Barat (Jakarta, Jawa, Sumatera, Pontianak) UTC+7
                    </option>
                    <option value="Asia/Makassar" <?= ($settings['timezone'] ?? '') === 'Asia/Makassar' ? 'selected' : '' ?>>
                        WITA — Waktu Indonesia Tengah (Bali, Lombok, Makassar, Balikpapan) UTC+8
                    </option>
                    <option value="Asia/Jayapura" <?= ($settings['timezone'] ?? '') === 'Asia/Jayapura' ? 'selected' : '' ?>>
                        WIT — Waktu Indonesia Timur (Ambon, Jayapura, Papua) UTC+9
                    </option>
                    <option value="UTC" <?= ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>
                        UTC — Standar Universal Internasional (UTC+0)
                    </option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1"><?= __('current_set_to') ?> <strong class="text-slate-600"><?= htmlspecialchars($settings['timezone_label'] ?? 'WIB (Asia/Jakarta)') ?></strong></p>

                <!-- Pratinjau Perubahan (Hanya muncul jika pilihan diubah tapi belum diklik simpan) -->
                <div id="tzPreviewNotice" class="hidden mt-2 p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0"></i>
                        <span><?= __('preview_if_saved') ?> <strong id="previewClockText" class="font-mono font-bold text-amber-950">--:--:--</strong></span>
                    </div>
                    <span class="text-[10px] text-amber-700 font-bold bg-amber-100 px-2 py-0.5 rounded-md border border-amber-300"><?= __('not_saved_yet') ?></span>
                </div>
            </div>

            <!-- Format Jam -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= __('field_time_format') ?></label>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer hover:bg-slate-50 transition-colors <?= ($settings['time_format'] ?? '24') === '24' ? 'border-orange-500 bg-orange-50/40 font-bold text-orange-900' : 'border-slate-200 text-slate-700' ?>">
                        <input type="radio" name="time_format" value="24" <?= ($settings['time_format'] ?? '24') === '24' ? 'checked' : '' ?> class="text-orange-500 focus:ring-orange-500">
                        <span><?= __('time_format_24') ?></span>
                    </label>
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer hover:bg-slate-50 transition-colors <?= ($settings['time_format'] ?? '24') === '12' ? 'border-orange-500 bg-orange-50/40 font-bold text-orange-900' : 'border-slate-200 text-slate-700' ?>">
                        <input type="radio" name="time_format" value="12" <?= ($settings['time_format'] ?? '24') === '12' ? 'checked' : '' ?> class="text-orange-500 focus:ring-orange-500">
                        <span><?= __('time_format_12') ?></span>
                    </label>
                </div>
            </div>

            <!-- Jam Buka & Jam Tutup -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= __('field_opening_time') ?></label>
                    <input type="time" name="opening_time" value="<?= htmlspecialchars($settings['opening_time'] ?? '10:00') ?>" 
                           class="w-full text-xs font-mono font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= __('field_closing_time') ?></label>
                    <input type="time" name="closing_time" value="<?= htmlspecialchars($settings['closing_time'] ?? '22:00') ?>" 
                           class="w-full text-xs font-mono font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <!-- Otomatisasi Jadwal Buka & Tutup -->
            <div class="pt-2 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="max-w-md">
                    <div class="text-xs font-bold text-slate-800"><?= __('field_auto_hours') ?></div>
                    <div class="text-[11px] text-slate-500 mt-0.5">
                        <?= __('field_auto_hours_desc') ?> (<?= htmlspecialchars($settings['opening_time'] ?? '10:00') ?> - <?= htmlspecialchars($settings['closing_time'] ?? '22:00') ?>).
                    </div>
                    <p class="text-[10px] text-orange-600 font-semibold mt-1">
                        <?= __('field_auto_hours_note') ?>
                    </p>
                </div>
                <select name="auto_operating_hours" class="text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-orange-500 shrink-0">
                    <option value="1" <?= ($settings['auto_operating_hours'] ?? '1') === '1' ? 'selected' : '' ?>><?= __('auto_active') ?></option>
                    <option value="0" <?= ($settings['auto_operating_hours'] ?? '1') === '0' ? 'selected' : '' ?>><?= __('auto_inactive') ?></option>
                </select>
            </div>

            <!-- Status Operasional Restoran & Kontrol Cepat -->
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-bold text-slate-800"><?= __('store_status_label') ?></div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                        Status: 
                        <?php if (($settings['store_status'] ?? 'closed') === 'open'): ?>
                            <span class="inline-flex items-center gap-1 font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-200"><?= __('status_open_counting') ?></span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-lg border border-slate-200"><?= __('status_closed_standby') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" onclick="toggleStoreStatusFromSettings()" id="btnToggleSettingsStore" class="px-3.5 py-1.5 rounded-xl <?= ($settings['store_status'] ?? 'closed') === 'open' ? 'bg-slate-800 hover:bg-rose-500/20 text-slate-200 hover:text-rose-400 border border-slate-700' : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/25' ?> text-xs font-bold transition-all flex items-center gap-1.5 shrink-0">
                    <i data-lucide="<?= ($settings['store_status'] ?? 'closed') === 'open' ? 'square' : 'play' ?>" class="w-3.5 h-3.5"></i>
                    <span><?= ($settings['store_status'] ?? 'closed') === 'open' ? __('btn_close_now') : __('btn_open_now') ?></span>
                </button>
            </div>
        </div>

        <!-- KARTU 2: BAHASA & LOKALISASI -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i data-lucide="globe" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900"><?= __('card_lang_title') ?></h3>
                    <p class="text-[11px] text-slate-500"><?= __('card_lang_desc') ?></p>
                </div>
            </div>

            <!-- Pilihan Bahasa -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= __('field_language') ?></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all <?= ($settings['language'] ?? 'id') === 'id' ? 'border-orange-500 bg-orange-50/40 shadow-sm' : 'border-slate-200 hover:bg-slate-50' ?>">
                        <input type="radio" name="language" value="id" <?= ($settings['language'] ?? 'id') === 'id' ? 'checked' : '' ?> class="text-orange-500 focus:ring-orange-500">
                        <span class="text-xl">🇮🇩</span>
                        <div>
                            <div class="font-bold text-slate-800"><?= __('lang_id_title') ?></div>
                            <div class="text-[10px] text-slate-400"><?= __('lang_id_desc') ?></div>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all <?= ($settings['language'] ?? 'id') === 'en' ? 'border-orange-500 bg-orange-50/40 shadow-sm' : 'border-slate-200 hover:bg-slate-50' ?>">
                        <input type="radio" name="language" value="en" <?= ($settings['language'] ?? 'id') === 'en' ? 'checked' : '' ?> class="text-orange-500 focus:ring-orange-500">
                        <span class="text-xl">🇬🇧</span>
                        <div>
                            <div class="font-bold text-slate-800"><?= __('lang_en_title') ?></div>
                            <div class="text-[10px] text-slate-400"><?= __('lang_en_desc') ?></div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Notifikasi Bunyi Tamu Masuk -->
            <div class="pt-2 border-t border-slate-100">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold text-slate-800"><?= __('field_chime') ?></div>
                        <div class="text-[11px] text-slate-400 mt-0.5"><?= __('field_chime_desc') ?></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="playChimeSound()" class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold flex items-center gap-1 transition-colors" title="<?= __('btn_test_sound') ?>">
                            <i data-lucide="volume-2" class="w-3.5 h-3.5"></i>
                            <span><?= __('btn_test_sound') ?></span>
                        </button>
                        <select name="detection_sound" class="text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
                            <option value="1" <?= ($settings['detection_sound'] ?? '0') === '1' ? 'selected' : '' ?>><?= __('sound_on') ?></option>
                            <option value="0" <?= ($settings['detection_sound'] ?? '0') === '0' ? 'selected' : '' ?>><?= __('sound_off') ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- KARTU 3: PROFIL & IDENTITAS RESTORAN -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="store" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900"><?= __('card_resto_title') ?></h3>
                    <p class="text-[11px] text-slate-500"><?= __('card_resto_desc') ?></p>
                </div>
            </div>

            <!-- Bagian Upload Foto / Logo Brand -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex flex-col sm:flex-row items-center gap-4">
                <!-- Preview Box (Mirip dengan Sidebar Header) -->
                <div class="relative shrink-0">
                    <div id="logoPreviewContainer" class="w-16 h-16 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center overflow-hidden shadow-lg shadow-orange-500/10">
                        <?php 
                        $curLogo = $settings['restaurant_logo'] ?? '';
                        $hasCustomLogo = (!empty($curLogo) && file_exists(__DIR__ . '/' . $curLogo));
                        ?>
                        <img id="logoPreviewImg" src="<?= $hasCustomLogo ? htmlspecialchars($curLogo) : '' ?>" alt="Logo Restoran" class="w-full h-full object-contain p-1.5 <?= $hasCustomLogo ? '' : 'hidden' ?>">
                        <span id="logoPreviewDefault" class="text-2xl <?= $hasCustomLogo ? 'hidden' : '' ?>">🍗</span>
                    </div>
                </div>

                <!-- Kontrol Pilih Logo -->
                <div class="flex-1 text-center sm:text-left">
                    <div class="text-xs font-bold text-slate-800"><?= __('logo_title') ?></div>
                    <div class="text-[11px] text-slate-500 mt-0.5"><?= __('logo_desc') ?></div>
                    
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mt-2.5">
                        <!-- Tombol Pilih File -->
                        <label class="cursor-pointer px-3 py-1.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold transition-all shadow-sm shadow-orange-500/25 flex items-center gap-1.5">
                            <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                            <span><?= __('btn_choose_logo') ?></span>
                            <input type="file" id="inp_restaurant_logo" name="restaurant_logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="hidden" onchange="previewLogoFile(this)">
                        </label>

                        <!-- Tombol Hapus Logo / Kembalikan ke Default -->
                        <button type="button" id="btnRemoveLogo" onclick="removeLogoAction()" class="<?= !$hasCustomLogo ? 'hidden' : '' ?> px-3 py-1.5 rounded-xl bg-white hover:bg-rose-50 text-rose-600 border border-slate-200 hover:border-rose-200 text-xs font-semibold transition-all flex items-center gap-1">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            <span><?= __('btn_remove_logo') ?></span>
                        </button>
                        <input type="hidden" name="remove_logo" id="inp_remove_logo" value="0">
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1.5"><?= __('logo_hint') ?></div>
                </div>
            </div>

            <!-- Kustomisasi Tampilan & Ukuran Logo -->
            <div id="logoCustomControls" class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-3">
                <div class="text-[11px] font-bold text-slate-700 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="sliders" class="w-3.5 h-3.5 text-orange-500"></i>
                        <span><?= __('logo_customize_title') ?></span>
                    </span>
                    <span class="text-[10px] text-slate-400 font-normal"><?= __('logo_customize_hint') ?></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <!-- 1. Ukuran / Zoom Logo -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1 flex items-center justify-between">
                            <span><?= __('logo_scale_label') ?></span>
                            <span id="logoScaleVal" class="text-orange-600 font-mono font-bold"><?= htmlspecialchars($settings['logo_scale'] ?? '100') ?>%</span>
                        </label>
                        <input type="range" name="logo_scale" id="inp_logo_scale" min="70" max="140" step="5" value="<?= htmlspecialchars($settings['logo_scale'] ?? '100') ?>" oninput="updateLogoAppearance()" class="w-full accent-orange-500 cursor-pointer">
                        <div class="flex justify-between text-[9px] text-slate-400 mt-0.5 font-medium">
                            <span>70%</span>
                            <span>100%</span>
                            <span>140%</span>
                        </div>
                    </div>

                    <!-- 2. Bentuk Bingkai Logo -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1"><?= __('logo_shape_label') ?></label>
                        <select name="logo_shape" id="inp_logo_shape" onchange="updateLogoAppearance()" class="w-full text-xs font-semibold bg-white border border-slate-200 rounded-xl px-2.5 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
                            <option value="rounded-xl" <?= ($settings['logo_shape'] ?? 'rounded-xl') === 'rounded-xl' ? 'selected' : '' ?>><?= __('shape_rounded') ?></option>
                            <option value="rounded-full" <?= ($settings['logo_shape'] ?? 'rounded-xl') === 'rounded-full' ? 'selected' : '' ?>><?= __('shape_circle') ?></option>
                        </select>
                    </div>

                    <!-- 3. Warna Latar Belakang Logo -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1"><?= __('logo_bg_label') ?></label>
                        <select name="logo_bg" id="inp_logo_bg" onchange="updateLogoAppearance()" class="w-full text-xs font-semibold bg-white border border-slate-200 rounded-xl px-2.5 py-1.5 text-slate-800 focus:outline-none focus:border-orange-500">
                            <option value="white" <?= ($settings['logo_bg'] ?? 'white') === 'white' ? 'selected' : '' ?>><?= __('bg_white') ?></option>
                            <option value="dark" <?= ($settings['logo_bg'] ?? 'white') === 'dark' ? 'selected' : '' ?>><?= __('bg_dark') ?></option>
                            <option value="transparent" <?= ($settings['logo_bg'] ?? 'white') === 'transparent' ? 'selected' : '' ?>><?= __('bg_transparent') ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('field_restaurant_name') ?></label>
                    <input type="text" name="restaurant_name" value="<?= htmlspecialchars($settings['restaurant_name'] ?? 'Ayam Bang Jungko') ?>" 
                           class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('field_branch_name') ?></label>
                    <input type="text" name="branch_name" value="<?= htmlspecialchars($settings['branch_name'] ?? 'Cabang Utama') ?>" 
                           class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('field_tagline') ?></label>
                <input type="text" name="restaurant_tagline" value="<?= htmlspecialchars($settings['restaurant_tagline'] ?? 'Spesialis Ayam Goreng & Bakar') ?>" 
                       class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-800 focus:outline-none focus:border-orange-500">
            </div>
        </div>

        <!-- KARTU 4: KAPASITAS MEJA & TARGET OPERASIONAL -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="target" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900"><?= __('card_target_title') ?></h3>
                    <p class="text-[11px] text-slate-500"><?= __('card_target_desc') ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('field_dine_in_capacity') ?></label>
                    <div class="relative">
                        <input type="number" name="capacity_dine_in" value="<?= htmlspecialchars($settings['capacity_dine_in'] ?? '50') ?>" min="1" 
                               class="w-full text-xs font-bold font-mono bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-800 focus:outline-none focus:border-orange-500 pr-14">
                        <span class="absolute right-3 top-2 text-[11px] font-semibold text-slate-400"><?= __('unit_seats') ?></span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1"><?= __('desc_seats') ?></p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1"><?= __('field_daily_target') ?></label>
                    <div class="relative">
                        <input type="number" name="daily_target" value="<?= htmlspecialchars($settings['daily_target'] ?? '300') ?>" min="1" 
                               class="w-full text-xs font-bold font-mono bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-800 focus:outline-none focus:border-orange-500 pr-14">
                        <span class="absolute right-3 top-2 text-[11px] font-semibold text-slate-400"><?= __('unit_people') ?></span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1"><?= __('desc_target') ?></p>
                </div>
            </div>
        </div>

    </div>

    <!-- Tombol Simpan Pengaturan -->
    <div class="flex items-center justify-between pt-4 border-t border-slate-200">
        <div id="saveFeedback" class="hidden text-xs font-bold px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
            ✓ <?= __('saved_success') ?>
        </div>

        <button type="submit" id="btnSaveSettings" class="ml-auto px-6 py-3 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-xs font-bold rounded-2xl shadow-lg shadow-orange-500/25 transition-all flex items-center gap-2">
            <i data-lucide="check" class="w-4 h-4"></i>
            <span><?= __('save_all_settings') ?></span>
        </button>
    </div>
</form>

<script>
// Pratinjau jam jika zona waktu diubah (jam header tetap stabil dan hanya berganti setelah klik Simpan)
const savedTimezone = '<?= htmlspecialchars($settings['timezone'] ?? 'Asia/Jakarta') ?>';
const tzSelect = document.getElementById('inp_timezone');
const tzNotice = document.getElementById('tzPreviewNotice');
const previewClock = document.getElementById('previewClockText');

function updateTzPreview() {
    if (!tzSelect || !tzNotice) return;
    const selectedVal = tzSelect.value;
    if (selectedVal !== savedTimezone) {
        tzNotice.classList.remove('hidden');
        try {
            const timeStr = new Date().toLocaleTimeString('en-GB', {
                timeZone: selectedVal,
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            let tzName = 'WIB';
            if (selectedVal === 'Asia/Makassar') tzName = 'WITA';
            else if (selectedVal === 'Asia/Jayapura') tzName = 'WIT';
            else if (selectedVal === 'UTC') tzName = 'UTC';
            if (previewClock) previewClock.innerText = `${timeStr} ${tzName}`;
        } catch(e) {}
    } else {
        tzNotice.classList.add('hidden');
    }
}

if (tzSelect) {
    tzSelect.addEventListener('change', updateTzPreview);
    setInterval(updateTzPreview, 1000);
}

// Web Audio API Synthesized Bell Chime (Ding-Dong)
function playChimeSound() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        // Nada Ding (880Hz - A5)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, ctx.currentTime);
        gain1.gain.setValueAtTime(0.3, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(ctx.currentTime);
        osc1.stop(ctx.currentTime + 0.6);

        // Nada Dong (587.33Hz - D5)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(587.33, ctx.currentTime + 0.25);
        gain2.gain.setValueAtTime(0.35, ctx.currentTime + 0.25);
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.1);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(ctx.currentTime + 0.25);
        osc2.stop(ctx.currentTime + 1.1);
    } catch (e) {
        console.warn('Audio not allowed or supported:', e);
    }
}

// Fungsi Pratinjau Logo File Unggahan
function previewLogoFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 3 * 1024 * 1024) {
            alert('Ukuran file logo terlalu besar. Maksimal 3MB.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('logoPreviewImg');
            const previewDef = document.getElementById('logoPreviewDefault');
            const btnRemove = document.getElementById('btnRemoveLogo');
            const inpRemove = document.getElementById('inp_remove_logo');

            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.classList.remove('hidden');
            }
            if (previewDef) previewDef.classList.add('hidden');
            if (btnRemove) btnRemove.classList.remove('hidden');
            if (inpRemove) inpRemove.value = '0';
            updateLogoAppearance();
        };
        reader.readAsDataURL(file);
    }
}

// Fungsi Hapus Logo dan Kembali ke Default
function removeLogoAction() {
    const input = document.getElementById('inp_restaurant_logo');
    const previewImg = document.getElementById('logoPreviewImg');
    const previewDef = document.getElementById('logoPreviewDefault');
    const btnRemove = document.getElementById('btnRemoveLogo');
    const inpRemove = document.getElementById('inp_remove_logo');

    if (input) input.value = '';
    if (previewImg) {
        previewImg.src = '';
        previewImg.classList.add('hidden');
    }
    if (previewDef) previewDef.classList.remove('hidden');
    if (btnRemove) btnRemove.classList.add('hidden');
    if (inpRemove) inpRemove.value = '1';
    updateLogoAppearance();
}

// Fungsi Memperbarui Tampilan Logo Secara Real-Time (Zoom, Bentuk, Latar Belakang)
function updateLogoAppearance() {
    const scale = document.getElementById('inp_logo_scale')?.value || 100;
    const shape = document.getElementById('inp_logo_shape')?.value || 'rounded-xl';
    const bg = document.getElementById('inp_logo_bg')?.value || 'white';

    const scaleVal = document.getElementById('logoScaleVal');
    if (scaleVal) scaleVal.innerText = scale + '%';

    const container = document.getElementById('logoPreviewContainer');
    const img = document.getElementById('logoPreviewImg');

    if (img) {
        img.style.transform = `scale(${scale / 100})`;
    }

    if (container) {
        // Bentuk Bingkai
        container.classList.remove('rounded-2xl', 'rounded-full', 'rounded-xl');
        if (shape === 'rounded-full') {
            container.classList.add('rounded-full');
        } else {
            container.classList.add('rounded-2xl');
        }

        // Latar Belakang Wadah
        container.classList.remove('bg-slate-900', 'bg-white', 'bg-transparent', 'border-slate-800', 'border-slate-200', 'border-transparent');
        if (bg === 'white') {
            container.classList.add('bg-white', 'border-slate-200');
        } else if (bg === 'dark') {
            container.classList.add('bg-slate-900', 'border-slate-800');
        } else {
            container.classList.add('bg-transparent', 'border-transparent');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateLogoAppearance();
});

// Handle Form Submit AJAX
document.getElementById('settingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('btnSaveSettings');
    const feedback = document.getElementById('saveFeedback');

    btn.disabled = true;
    btn.innerHTML = '<span><?= __('saving') ?></span>';

    const formData = new FormData(form);

    try {
        const res = await fetch('api/settings.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            feedback.innerText = '✓ ' + (data.message || '<?= __('saved_success') ?>');
            feedback.classList.remove('hidden');
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } else {
            alert('Gagal menyimpan: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch(err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i><span><?= __('save_all_settings') ?></span>';
        lucide.createIcons();
    }
});

async function toggleStoreStatusFromSettings() {
    const btn = document.getElementById('btnToggleSettingsStore');
    if (btn) btn.disabled = true;

    try {
        const res = await fetch('api/stream_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_store_status', camera_id: 'cam-1' })
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Status operasional restoran berhasil diubah.');
            window.location.reload();
        } else {
            alert('Gagal mengubah status: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch(e) {
        alert('Gagal menghubungi server: ' + e.message);
    } finally {
        if (btn) btn.disabled = false;
        lucide.createIcons();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
