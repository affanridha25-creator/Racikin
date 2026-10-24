<?php
// reminders.php — pengingat langganan lewat EMAIL ke klien (default 14 hari sebelum habis).
// Dipakai oleh cron_email_reminders.php. Idempoten: tiap (usaha, tanggal berakhir, ambang) hanya dikirim SEKALI,
// jadi aman dijalankan tiap hari (atau berkali-kali) — tak akan dobel.
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/emailtpl.php';

// Ambang hari (config.php: define('REMINDER_EMAIL_DAYS', [14, 3]);). Default hanya 14 hari.
function reminder_days() {
    $d = (defined('REMINDER_EMAIL_DAYS') && is_array(REMINDER_EMAIL_DAYS)) ? REMINDER_EMAIL_DAYS : [14];
    $d = array_values(array_unique(array_filter(array_map('intval', $d), function ($x) { return $x > 0 && $x <= 90; })));
    sort($d);
    return $d ?: [14];
}
function reminder_table($m) {
    $m->exec("CREATE TABLE IF NOT EXISTS email_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alias VARCHAR(32) NOT NULL,
        paid_until DATE NOT NULL,
        kind VARCHAR(12) NOT NULL,
        sent_at DATETIME,
        UNIQUE KEY uq (alias, paid_until, kind)
    ) ENGINE=InnoDB");
}
function reminder_email_body($name, $alias, $days, $until, $owner) {
    $url = app_base_url();
    $st = function_exists('settings_all') ? settings_all() : [];
    $rp = function ($v) { return 'Rp' . number_format((int)$v, 0, ',', '.'); };
    $harga = '';
    if ((int)($st['price_1bln'] ?? 0) > 0 || (int)($st['price_3bln'] ?? 0) > 0 || (int)($st['price_1thn'] ?? 0) > 0) {
        $harga = "\nPilihan paket:\n";
        if ((int)$st['price_1bln'] > 0) $harga .= "  • 1 bulan  : " . $rp($st['price_1bln']) . "\n";
        if ((int)$st['price_3bln'] > 0) $harga .= "  • 3 bulan  : " . $rp($st['price_3bln']) . "\n";
        if ((int)$st['price_1thn'] > 0) $harga .= "  • 1 tahun  : " . $rp($st['price_1thn']) . "\n";
    }
    $kapan = $days <= 0 ? 'hari ini' : "dalam $days hari";
    return "Halo" . ($owner ? ' ' . $owner : '') . ",\n\n"
         . "Pengingat dari Racikin: langganan usaha \"$name\" (kode: $alias) akan berakhir $kapan, yaitu pada $until.\n"
         . $harga . "\n"
         . "Cara perpanjang (cuma beberapa menit):\n"
         . "  1. Buka $url dan masuk ke akunmu.\n"
         . "  2. Ketuk tombol Perpanjang di pita pengingat di bagian atas, lalu pilih paket yang kamu mau.\n"
         . "  3. Akan tampil nominal yang harus ditransfer (sudah termasuk kode unik) dan rekening tujuan.\n"
         . "  4. Transfer sesuai nominal itu, lalu unggah foto bukti transfernya di aplikasi.\n\n"
         . "Setelah kami verifikasi, masa aktifmu langsung diperpanjang dan semua datamu tetap utuh.\n"
         . "Kalau kamu sudah memperpanjang, abaikan email ini ya.\n\n"
         . "Ada pertanyaan? Balas email ini (" . support_email() . ") atau hubungi kami lewat WhatsApp: https://wa.me/6281325523999\n\n"
         . "Terima kasih sudah memakai Racikin 🙏\n— Tim Racikin";
}
function reminder_email_html($name, $alias, $days, $until, $owner) {
    $url = app_base_url();
    $st = function_exists('settings_all') ? settings_all() : [];
    $rp = function ($v) { return 'Rp' . number_format((int)$v, 0, ',', '.'); };
    $kapan = $days <= 0 ? 'hari ini' : "dalam $days hari";
    $c = etpl_p('Halo' . ($owner ? ' <b>' . etpl_h($owner) . '</b>' : '') . ', langganan usahamu di Racikin akan berakhir <b>' . $kapan . '</b>. Perpanjang lebih awal supaya aplikasi tetap bisa dipakai tanpa jeda.');
    $c .= etpl_facts([['Usaha', etpl_h($name)], ['Kode usaha', etpl_h($alias)], ['Berakhir pada', etpl_h($until)], ['Sisa waktu', $days <= 0 ? 'Hari ini' : $days . ' hari']]);
    $paket = '';
    foreach (['price_1bln' => '1 bulan', 'price_3bln' => '3 bulan', 'price_1thn' => '1 tahun'] as $k => $lab) if ((int)($st[$k] ?? 0) > 0) $paket .= '<tr><td style="padding:6px 0;font-size:14px;color:#3b2a25">' . $lab . '</td><td align="right" style="padding:6px 0;font-size:14px;font-weight:800;color:#D53E0F">' . $rp($st[$k]) . '</td></tr>';
    if ($paket !== '') $c .= '<div style="margin:6px 0 4px;font-size:13px;font-weight:800;color:#8a6f64;letter-spacing:.04em;text-transform:uppercase">Pilihan paket</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #f3e4da;border-bottom:1px solid #f3e4da;margin-bottom:6px">' . $paket . '</table>';
    $c .= '<div style="margin:18px 0 4px;font-size:13px;font-weight:800;color:#8a6f64;letter-spacing:.04em;text-transform:uppercase">Cara perpanjang</div>'
        . etpl_steps(['Buka Racikin dan masuk ke akunmu.', 'Ketuk tombol <b>Perpanjang</b> di pita pengingat di bagian atas, lalu pilih paket.', 'Transfer <b>sesuai nominal yang tampil</b> (sudah termasuk kode unik) ke rekening tujuan.', 'Unggah foto bukti transfer di aplikasi. Selesai!']);
    $c .= etpl_button('Perpanjang sekarang', $url);
    $c .= etpl_p('<span style="font-size:13px;color:#8a6f64">Setelah kami verifikasi, masa aktifmu langsung diperpanjang dan semua datamu tetap utuh. Kalau kamu sudah memperpanjang, abaikan email ini ya.</span>');
    return etpl_html('Langganan kamu berakhir ' . $kapan, "Usaha $name berakhir pada $until. Perpanjang sekarang.", $c);
}
/**
 * Kirim pengingat. $dry=true → hanya hitung, tak kirim & tak mencatat.
 * Return ['sent'=>[...], 'skipped'=>[...], 'failed'=>[...]] (tiap item: alias, days, until, to).
 */
function send_expiry_reminders($m, $dry = false) {
    reminder_table($m);
    $days = reminder_days(); $max = max($days);
    $rows = $m->query("SELECT b.alias, b.name, b.paid_until, DATEDIFF(b.paid_until, CURDATE()) AS days_left
                       FROM businesses b
                       WHERE b.active=1 AND b.paid_until IS NOT NULL
                         AND DATEDIFF(b.paid_until, CURDATE()) BETWEEN 0 AND $max
                       ORDER BY b.paid_until")->fetchAll(PDO::FETCH_ASSOC);
    $out = ['sent' => [], 'skipped' => [], 'failed' => []];
    $chk = $m->prepare("SELECT kind FROM email_reminders WHERE alias=? AND paid_until=?");
    $ins = $m->prepare("INSERT IGNORE INTO email_reminders (alias,paid_until,kind,sent_at) VALUES (?,?,?,NOW())");
    $own = $m->prepare("SELECT email,name FROM users WHERE alias=? AND role='owner'");
    foreach ($rows as $r) {
        $d = (int)$r['days_left'];
        // ambang terkecil yang sudah terlewati (mis. sisa 10 hari dgn [14,3] → ambang 14)
        $thr = null; foreach ($days as $t) if ($d <= $t) { $thr = $t; break; }
        if ($thr === null) continue;
        $chk->execute([$r['alias'], $r['paid_until']]); $done = $chk->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('h' . $thr, $done, true)) { $out['skipped'][] = ['alias' => $r['alias'], 'days' => $d, 'until' => $r['paid_until'], 'why' => 'sudah dikirim']; continue; }
        $own->execute([$r['alias']]); $owners = $own->fetchAll(PDO::FETCH_ASSOC);
        $sentAny = false; $tos = [];
        foreach ($owners as $o) {
            if (!filter_var($o['email'], FILTER_VALIDATE_EMAIL)) continue;
            $tos[] = $o['email'];
            if ($dry) { $sentAny = true; continue; }
            $subject = $d <= 0 ? 'Langganan Racikin kamu berakhir hari ini' : "Langganan Racikin kamu berakhir $d hari lagi";
            if (mail_client($o['email'], $subject, reminder_email_body($r['name'], $r['alias'], $d, $r['paid_until'], $o['name']), reminder_email_html($r['name'], $r['alias'], $d, $r['paid_until'], $o['name']))) $sentAny = true;
        }
        $item = ['alias' => $r['alias'], 'name' => $r['name'], 'days' => $d, 'until' => $r['paid_until'], 'to' => $tos];
        if (!$tos) { $out['failed'][] = $item + ['why' => 'tidak ada email pemilik']; continue; }
        if ($sentAny) {
            if (!$dry) foreach ($days as $t) if ($t >= $thr) $ins->execute([$r['alias'], $r['paid_until'], 'h' . $t]);   // tandai ambang ini & yg lebih besar → tak dobel
            $out['sent'][] = $item;
        } else $out['failed'][] = $item + ['why' => 'pengiriman email gagal'];
    }
    return $out;
}
// Ringkasan untuk admin. Hanya bila ada yang dikirim/gagal.
function email_reminder_summary($res) {
    if (!$res['sent'] && !$res['failed']) return;
    $l = function ($arr) { $o = ''; foreach ($arr as $x) $o .= "  • {$x['name']} ({$x['alias']}) — berakhir {$x['until']} (" . ($x['days'] <= 0 ? 'hari ini' : $x['days'] . ' hari') . ") → " . ($x['to'] ? implode(', ', $x['to']) : '-') . (isset($x['why']) ? " [{$x['why']}]" : '') . "\n"; return $o; };
    $body = "Ringkasan pengingat langganan (email ke klien) — " . gmdate('Y-m-d H:i', time() + 7 * 3600) . " WIB\n\n";
    if ($res['sent']) $body .= "TERKIRIM (" . count($res['sent']) . "):\n" . $l($res['sent']) . "\n";
    if ($res['failed']) $body .= "GAGAL (" . count($res['failed']) . ") — perlu tindak lanjut manual (mis. lewat WhatsApp di admin.php):\n" . $l($res['failed']) . "\n";
    $body .= "Kelola: " . app_base_url() . "/admin.php\n\n— Racikin";
    $rows = function ($arr, $bad) { $o = ''; foreach ($arr as $x) $o .= '<tr><td style="padding:8px 0;border-bottom:1px solid #f3e4da;font-size:13.5px;color:#2a1512"><b>' . etpl_h($x['name']) . '</b> <span style="color:#8a6f64">(' . etpl_h($x['alias']) . ')</span><br><span style="color:#8a6f64;font-size:12.5px">berakhir ' . etpl_h($x['until']) . ' · ' . ($x['days'] <= 0 ? 'hari ini' : $x['days'] . ' hari') . ' → ' . etpl_h($x['to'] ? implode(', ', $x['to']) : '-') . (isset($x['why']) ? ' · <span style="color:#c0392b">' . etpl_h($x['why']) . '</span>' : '') . '</span></td></tr>'; return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $o . '</table>'; };
    $h = etpl_p('Ringkasan pengingat langganan yang dikirim ke klien hari ini.');
    if ($res['sent']) $h .= '<div style="margin:10px 0 4px;font-size:13px;font-weight:800;color:#1e8449">✓ TERKIRIM (' . count($res['sent']) . ')</div>' . $rows($res['sent'], false);
    if ($res['failed']) $h .= '<div style="margin:16px 0 4px;font-size:13px;font-weight:800;color:#c0392b">✗ GAGAL (' . count($res['failed']) . ') — hubungi lewat WhatsApp</div>' . $rows($res['failed'], true);
    $h .= etpl_button('Buka halaman admin', app_base_url() . '/admin.php');
    notify_admin('Ringkasan pengingat langganan Racikin', $body, false, etpl_html('Ringkasan pengingat langganan', 'Pengingat langganan hari ini', $h));
}
