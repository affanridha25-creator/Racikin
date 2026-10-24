<?php
// emailbuilders.php — pembuat isi email (satu sumber untuk email asli DAN tombol "Contoh Email" di admin.php).
// Tiap fungsi mengembalikan [subjek, teksPolos, html]. Dimuat oleh emailtpl.php.

function email_clean($v) { return trim(preg_replace('/[\r\n]+/', ' ', (string)$v)); }   // cegah header injection

// Pendaftar baru (ke admin)
function email_signup($name, $alias, $user, $email, $status, $wa = '', $ip = '?', $when = null) {
    $c = 'email_clean'; $when = $when ?: gmdate('Y-m-d H:i', time() + 7 * 3600) . ' WIB';
    $text = "Ada usaha baru mendaftar di Racikin.\n\n"
      . "Usaha   : " . $c($name) . "\nKode    : " . $c($alias) . "\nPemilik : " . $c($user) . "\nEmail   : " . $c($email) . "\nWhatsApp: " . ($wa !== '' ? '+' . $c($wa) . '  → https://wa.me/' . $c($wa) : '-') . "\n"
      . "Status  : " . $status . "\nWaktu   : " . $when . "\nIP      : " . $c($ip) . "\n\n"
      . "Kelola di: " . app_base_url() . "/admin.php\n\n— Racikin";
    $badge = (strpos($status, 'AKTIF') === 0) ? '<span style="background:#e6f5ec;color:#1e8449;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800">' . etpl_h($status) . '</span>'
                                               : '<span style="background:#fdeede;color:#b8560f;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800">' . etpl_h($status) . '</span>';
    $html = etpl_html('Pendaftar baru 🎉', $c($name) . ' baru saja mendaftar.',
        etpl_p('Ada usaha baru yang mendaftar di Racikin.')
      . etpl_facts([['Usaha', etpl_h($c($name))], ['Kode', etpl_h($c($alias))], ['Pemilik', etpl_h($c($user))], ['Email', etpl_h($c($email))],
                    ['WhatsApp', $wa !== '' ? '<a href="https://wa.me/' . etpl_h($c($wa)) . '" style="color:#1FA855;text-decoration:none">+' . etpl_h($c($wa)) . '</a>' : '-'],
                    ['Status', $badge], ['Waktu', etpl_h($when)], ['IP', etpl_h($c($ip))]])
      . ($wa !== '' ? etpl_button('Sapa lewat WhatsApp', 'https://wa.me/' . $c($wa) . '?text=' . rawurlencode('Halo ' . $c($user) . ', terima kasih sudah mendaftar di Racikin 🙏'), '#1FA855') : '')
      . etpl_button('Buka halaman admin', app_base_url() . '/admin.php'));
    return ['Pendaftar baru Racikin: ' . $c($name) . ' (' . $c($alias) . ')', $text, $html];
}

// Bukti transfer masuk (ke admin)
function email_proof($bizName, $alias, $planLbl, $amount, $paidUntil, $email, $wa = '', $note = '') {
    $c = 'email_clean'; $amt = 'Rp' . number_format((int)$amount, 0, ',', '.');
    $text = "Ada bukti transfer perpanjangan yang perlu diverifikasi.\n\n"
      . "Usaha    : " . $c($bizName) . " (" . $c($alias) . ")\nPaket    : " . $planLbl . "\nNominal  : " . $amt . "  ← harus cocok dengan mutasi rekening\n"
      . "Masa aktif sekarang: " . ($paidUntil ?: '-') . "\nEmail    : " . $c($email) . "\n"
      . ($wa !== '' ? "WhatsApp : https://wa.me/" . $c($wa) . "\n" : '') . ($note !== '' ? "Catatan  : " . $c($note) . "\n" : '')
      . "\nPeriksa mutasi rekening, lalu Setujui di: " . app_base_url() . "/admin.php\n\n— Racikin";
    $html = etpl_html('Bukti transfer masuk 💸', $c($bizName) . ' — ' . $planLbl . ' — ' . $amt,
        etpl_p('Ada bukti transfer perpanjangan yang perlu kamu verifikasi.')
      . etpl_facts([['Usaha', etpl_h($c($bizName)) . ' <span style="color:#8a6f64;font-weight:400">(' . etpl_h($c($alias)) . ')</span>'], ['Paket', etpl_h($planLbl)],
                    ['Nominal transfer', '<span style="color:#D53E0F;font-size:17px">' . etpl_h($amt) . '</span>'], ['Masa aktif sekarang', etpl_h($paidUntil ?: '-')],
                    ['Email', etpl_h($c($email))], ['WhatsApp', $wa !== '' ? '<a href="https://wa.me/' . etpl_h($c($wa)) . '" style="color:#1FA855;text-decoration:none">+' . etpl_h($c($wa)) . '</a>' : ''], ['Catatan', $note !== '' ? etpl_h($c($note)) : '']])
      . etpl_p('<span style="font-size:13px;color:#8a6f64">Pastikan <b>nominal</b> di atas sama persis dengan yang masuk di mutasi rekening, lalu setujui.</span>')
      . etpl_button('Verifikasi di admin', app_base_url() . '/admin.php'));
    return ['Bukti transfer masuk: ' . $c($bizName) . ' (' . $planLbl . ')', $text, $html];
}

// Reset password (ke pemilik)
function email_reset($name, $alias, $link) {
    $text = "Halo" . ($name ? ' ' . $name : '') . ",\n\nAda permintaan reset password untuk usaha \"$alias\" di Racikin.\nKlik link ini untuk membuat password baru (berlaku 1 jam):\n\n$link\n\n"
          . "Kalau kamu tidak meminta ini, abaikan saja email ini. Butuh bantuan? Hubungi " . support_email() . ".\n\n— Racikin";
    $html = etpl_html('Buat password baru', 'Klik tombol untuk membuat password baru (berlaku 1 jam).',
        etpl_p('Halo' . ($name ? ' <b>' . etpl_h($name) . '</b>' : '') . ', ada permintaan reset password untuk usaha <b>' . etpl_h($alias) . '</b> di Racikin.')
      . etpl_p('Klik tombol di bawah untuk membuat password baru. Tautan ini berlaku <b>1 jam</b> dan hanya bisa dipakai sekali.')
      . etpl_button('Buat password baru', $link)
      . etpl_p('<span style="font-size:12.5px;color:#8a6f64">Tombol tidak berfungsi? Salin tautan ini ke browser:<br><span style="word-break:break-all;color:#D53E0F">' . etpl_h($link) . '</span></span>')
      . '<div style="margin:14px 0 0;padding:12px 14px;border-radius:12px;background:#fff6ef;font-size:13px;line-height:1.6;color:#6b4a3f">Kalau kamu <b>tidak</b> meminta ini, abaikan saja email ini. Passwordmu tidak akan berubah.</div>');
    return ['Reset Password Racikin', $text, $html];
}

// OTP pembatalan transaksi kasir (ke pemilik)
function email_otp($staff, $bizName, $notaNo, $total, $code) {
    $text = "Halo,\n\nKasir \"$staff\" meminta MEMBATALKAN transaksi $notaNo senilai Rp" . number_format((int)$total, 0, ',', '.') . ".\n\nKode OTP: $code\n(berlaku 10 menit, sekali pakai)\n\n"
          . "Berikan kode ini ke kasir HANYA jika kamu menyetujui pembatalan. Abaikan jika tidak.\n\n— $bizName";
    $html = etpl_html('Kode OTP pembatalan', 'Kode OTP untuk menyetujui pembatalan transaksi.',
        etpl_p('Kasir <b>' . etpl_h($staff) . '</b> meminta <b>membatalkan transaksi</b>:')
      . etpl_facts([['Usaha', etpl_h($bizName)], ['No. nota', etpl_h($notaNo)], ['Nilai', 'Rp' . number_format((int)$total, 0, ',', '.')]])
      . etpl_p('Berikan kode berikut ke kasir <b>hanya jika kamu menyetujui</b> pembatalan:') . etpl_code($code)
      . etpl_p('<span style="font-size:13px;color:#8a6f64">Berlaku 10 menit dan hanya bisa dipakai sekali. Abaikan email ini jika kamu tidak menyetujui.</span>'));
    return ["Kode OTP Pembatalan Transaksi - $bizName", $text, $html];
}

// Perpanjangan disetujui (ke klien)
function email_approved($bizName, $paidUntil) {
    $text = "Halo,\n\nTerima kasih! Pembayaran perpanjangan untuk usaha \"$bizName\" sudah kami verifikasi.\n\nMasa aktif sekarang sampai: " . ($paidUntil ?: '-')
          . "\n\nSemua datamu tetap utuh dan aplikasi siap dipakai: " . app_base_url() . "\n\nAda pertanyaan? Balas email ini (" . support_email() . ") atau chat WhatsApp: https://wa.me/6281325523999\n\nTerima kasih sudah memakai Racikin 🙏\n— Tim Racikin";
    $html = etpl_html('Perpanjangan disetujui ✓', 'Terima kasih! Langgananmu sudah diperpanjang.',
        etpl_p('Terima kasih! Pembayaran perpanjangan untuk usaha <b>' . etpl_h($bizName) . '</b> sudah kami verifikasi.')
      . etpl_facts([['Usaha', etpl_h($bizName)], ['Masa aktif sampai', etpl_h($paidUntil ?: '-')]])
      . etpl_p('Semua datamu tetap utuh dan aplikasi siap dipakai.') . etpl_button('Buka Racikin', app_base_url()));
    return ['Perpanjangan Racikin disetujui ✓', $text, $html];
}

// Perpanjangan ditolak (ke klien)
function email_rejected($why = '') {
    $text = "Halo,\n\nMaaf, bukti transfer perpanjanganmu belum bisa kami verifikasi" . ($why !== '' ? ":\n\"$why\"" : '.') . "\n\n"
          . "Silakan buka " . app_base_url() . " lalu ajukan perpanjangan dan unggah bukti transfer yang benar, atau hubungi kami lewat WhatsApp: https://wa.me/6281325523999 / email " . support_email() . "\n\n— Tim Racikin";
    $html = etpl_html('Perpanjangan belum bisa kami proses', 'Bukti transfer belum bisa diverifikasi.',
        etpl_p('Maaf, bukti transfer perpanjanganmu <b>belum bisa kami verifikasi</b>.')
      . ($why !== '' ? '<div style="margin:12px 0;padding:14px 16px;border-radius:12px;background:#fdeceb;border-left:4px solid #D53E0F;font-size:14px;line-height:1.6;color:#7a2a1c"><b>Alasan:</b> ' . etpl_h($why) . '</div>' : '')
      . etpl_p('Silakan ajukan perpanjangan lagi dan unggah bukti transfer yang benar. Kalau perlu bantuan, hubungi kami kapan saja.') . etpl_button('Ajukan ulang', app_base_url()));
    return ['Perpanjangan Racikin belum bisa kami proses', $text, $html];
}

// Katalog contoh (data tiruan) untuk tombol "Contoh Email" di admin.php. key => [label, builder()]
function email_samples() {
    require_once __DIR__ . '/reminders.php';
    $until14 = date('Y-m-d', time() + 14 * 86400);
    return [
        'reminder' => ['⏰ Pengingat langganan (14 hari) — ke pelanggan', function () use ($until14) { $s = 'Roti Ana Kitchen'; return ['Langganan Racikin kamu berakhir 14 hari lagi', reminder_email_body($s, 'rotiana', 14, $until14, 'Ana'), reminder_email_html($s, 'rotiana', 14, $until14, 'Ana')]; }],
        'approved' => ['✅ Perpanjangan disetujui — ke pelanggan', function () { return email_approved('Roti Ana Kitchen', date('Y-m-d', time() + 44 * 86400)); }],
        'rejected' => ['❌ Perpanjangan ditolak — ke pelanggan', function () { return email_rejected('Nominal transfer tidak sesuai dengan mutasi rekening.'); }],
        'reset'    => ['🔑 Reset password — ke pelanggan', function () { return email_reset('Ana', 'rotiana', app_base_url() . '/?reset=contoh.tokenreset123'); }],
        'otp'      => ['🔢 OTP pembatalan transaksi kasir — ke pemilik', function () { return email_otp('Budi', 'Roti Ana Kitchen', 'ANNA-20261024-003', 111000, '482913'); }],
        'signup'   => ['🎉 Pendaftar baru — ke admin', function () { return email_signup('Toko Budi', 'tokobudi', 'Budi', 'budi@contoh.com', 'AKTIF — trial gratis 30 hari (otomatis)', '6281234567890', '203.0.113.7'); }],
        'proof'    => ['💸 Bukti transfer masuk — ke admin', function () { return email_proof('Roti Ana Kitchen', 'rotiana', '3 bulan', 135017, date('Y-m-d', time() + 10 * 86400), 'ana@contoh.com', '6281234567890', 'sudah tf via BCA'); }],
    ];
}
