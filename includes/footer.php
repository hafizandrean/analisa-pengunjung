        </main>

        <!-- Footer -->
        <footer class="mt-auto py-6 px-8 bg-white border-t border-slate-200 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>
                &copy; <?= date('Y') ?> <strong class="text-slate-700"><?= htmlspecialchars(getSetting('restaurant_name', 'Ayam Bang Jungko')) ?></strong> &bull; <?= __('footer_desc') ?>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                <span><?= __('footer_ready') ?></span>
            </div>
        </footer>
    </div>

    <?php
    $activeTz = getSetting('timezone', 'Asia/Jakarta');
    $timeFormat = getSetting('time_format', '24');
    $is12Hour = ($timeFormat === '12');
    ?>
    <!-- Real-time Topbar Clock & Initialize Lucide Icons -->
    <script>
        lucide.createIcons();

        let SYSTEM_TIMEZONE = '<?= htmlspecialchars($activeTz) ?>';
        const SYSTEM_HOUR12 = <?= $is12Hour ? 'true' : 'false' ?>;

        function updateHeaderClock() {
            const clockEl = document.getElementById('topbarClock');
            if (!clockEl) return;
            try {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('en-GB', {
                    timeZone: SYSTEM_TIMEZONE,
                    hour12: SYSTEM_HOUR12,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                clockEl.innerText = timeStr;
            } catch (e) {
                const now = new Date();
                clockEl.innerText = now.toTimeString().split(' ')[0];
            }
        }
        setInterval(updateHeaderClock, 1000);
        updateHeaderClock();
    </script>
</body>
</html>
