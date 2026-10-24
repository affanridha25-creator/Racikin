<?php
// mail.php — pengirim email + helper host/URL. Dipakai bersama oleh api.php dan admin.php (tanpa efek samping saat di-include).
// ---- kirim email reset password (pakai mail() bawaan; di shared hosting umumnya jalan) ----
// Host kanonik yang tepercaya — cegah Host-header injection pada link reset (CWE-640).
// Override di config.php: define('APP_HOSTS', ['login.usahamu.com', ...]);
function app_host() {
    $allow = defined('APP_HOSTS') ? APP_HOSTS : ['login.racikin.com', 'racikin.com', 'localhost', '127.0.0.1'];
    $raw  = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $bare = preg_replace('/:\d+$/', '', $raw);              // buang port utk pencocokan
    // host asing (mis. dari Host header dipalsukan) → paksa ke host kanonik pertama
    return in_array($bare, $allow, true) ? $raw : $allow[0];
}

function app_base_url() {
    if (defined('APP_URL') && APP_URL) return rtrim(APP_URL, '/');   // paling utama: host eksplisit dari config
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . app_host();          // jaring pengaman: hanya host di allowlist
}

// Beri tahu admin lewat email saat ada usaha baru mendaftar (ADMIN_EMAIL di config.php; boleh beberapa, pisah koma).
// Defensif: gagal kirim tak boleh membatalkan pendaftaran.
// ---- Pengirim email: SMTP (akun email asli, lebih andal & tak mudah masuk spam) atau mail() bawaan hosting ----
// Aktifkan SMTP dengan mengisi SMTP_HOST/SMTP_USER/SMTP_PASS di config.php (buat akun email di cPanel → Email Accounts).
// Tanda tangan kompatibel mail(): app_mail($to, $subject, $body, $headers). Return true bila diterima server.
function smtp_read($fp) {
    $out = '';
    while (($line = fgets($fp, 515)) !== false) { $out .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
    return $out;
}

function smtp_cmd($fp, $cmd, $okPrefix) {
    if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
    $r = smtp_read($fp);
    return strpos($r, (string)$okPrefix) === 0;
}

function smtp_send($to, $subject, $body, $headers) {
    $host = (string)SMTP_HOST; $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 465;
    $secure = defined('SMTP_SECURE') ? strtolower((string)SMTP_SECURE) : 'ssl';
    $user = defined('SMTP_USER') ? (string)SMTP_USER : ''; $pass = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
    $fromAddr = defined('SMTP_FROM') && SMTP_FROM ? (string)SMTP_FROM : $user;
    if ($host === '' || !filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) return false;
    // ambil header turunan dari string $headers; From dipaksa ke akun SMTP (server biasanya menolak From asing)
    $name = 'Racikin'; $extra = [];
    foreach (preg_split("/\r?\n/", (string)$headers) as $h) {
        if ($h === '' || strpos($h, ':') === false) continue;
        [$k, $v] = array_map('trim', explode(':', $h, 2));
        $lk = strtolower($k);
        if ($lk === 'from') { if (preg_match('/^"?([^"<]+?)"?\s*</', $v, $mm)) $name = trim($mm[1]); }
        elseif (in_array($lk, ['content-type', 'mime-version', 'reply-to'], true)) $extra[] = "$k: $v";
    }
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $GLOBALS['smtp_last_error'] = "tidak bisa terhubung ke $host:$port ($es). Cek SMTP_HOST/SMTP_PORT/SMTP_SECURE."; error_log("smtp connect gagal: $es"); return false; }
    stream_set_timeout($fp, 15);
    try {
        if (!smtp_cmd($fp, null, '220')) throw new Exception('greeting');
        $ehlo = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        if (!smtp_cmd($fp, "EHLO $ehlo", '250')) throw new Exception('EHLO');
        if ($secure === 'tls') {
            if (!smtp_cmd($fp, 'STARTTLS', '220')) throw new Exception('STARTTLS');
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception('TLS');
            if (!smtp_cmd($fp, "EHLO $ehlo", '250')) throw new Exception('EHLO2');
        }
        if ($user !== '') {
            if (!smtp_cmd($fp, 'AUTH LOGIN', '334') || !smtp_cmd($fp, base64_encode($user), '334') || !smtp_cmd($fp, base64_encode($pass), '235')) throw new Exception('AUTH');
        }
        if (!smtp_cmd($fp, "MAIL FROM:<$fromAddr>", '250')) throw new Exception('MAIL FROM');
        if (!smtp_cmd($fp, "RCPT TO:<$to>", '250')) throw new Exception('RCPT TO');
        if (!smtp_cmd($fp, 'DATA', '354')) throw new Exception('DATA');
        $enc = function ($t) { return preg_match('/[^\x20-\x7E]/', $t) ? '=?UTF-8?B?' . base64_encode($t) . '?=' : $t; };
        $msg = 'From: ' . $enc($name) . " <$fromAddr>\r\nTo: <$to>\r\nSubject: " . $enc(preg_replace('/[\r\n]+/', ' ', $subject)) . "\r\nDate: " . date('r') . "\r\n"
             . (array_filter($extra) ? implode("\r\n", $extra) . "\r\n" : "Content-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n") . "\r\n"
             . preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $body));
        $msg = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $msg));
        fwrite($fp, $msg . "\r\n.\r\n");
        if (strpos(smtp_read($fp), '250') !== 0) throw new Exception('akhir pesan');
        smtp_cmd($fp, 'QUIT', '221');
        fclose($fp);
        return true;
    } catch (Exception $e) {
        $why = ['greeting' => 'server tidak menjawab dengan benar', 'EHLO' => 'server menolak salam EHLO', 'STARTTLS' => 'STARTTLS ditolak (coba SMTP_SECURE=ssl, port 465)', 'TLS' => 'enkripsi TLS gagal (sertifikat server tak cocok dengan SMTP_HOST? coba host yang sama dengan nama di sertifikat)',
                'AUTH' => 'LOGIN DITOLAK — alamat/sandi SMTP salah (SMTP_USER / SMTP_PASS)', 'MAIL FROM' => 'pengirim ditolak (SMTP_FROM harus = akun email yang login)', 'RCPT TO' => 'alamat tujuan ditolak', 'DATA' => 'server menolak isi pesan', 'akhir pesan' => 'pesan ditolak di akhir (kemungkinan dianggap spam)'];
        $GLOBALS['smtp_last_error'] = ($why[$e->getMessage()] ?? ('gagal di tahap ' . $e->getMessage()));
        error_log('smtp gagal di tahap ' . $e->getMessage()); @fclose($fp); return false;
    }
}

function app_mail($to, $subject, $body, $headers = '') {
    if (defined('SMTP_HOST') && SMTP_HOST !== '') return smtp_send($to, $subject, $body, $headers);
    return @mail($to, $subject, $body, $headers);
}

// Uji kirim dari halaman admin: kirim email percobaan ke penerima pemberitahuan. Return [bool ok, string pesan].
function run_mail_test() {
    $list = defined('ADMIN_EMAIL') ? (string)ADMIN_EMAIL : '';
    try { $st = settings_all(); $list .= ',' . ($st['notify_email'] ?? ''); } catch (Throwable $e) { }
    $to = array_values(array_unique(array_filter(array_map('trim', explode(',', $list)), function ($x) { return filter_var($x, FILTER_VALIDATE_EMAIL); })));
    if (!$to) return [false, 'Isi dulu "Email pemberitahuan pendaftar baru", klik Simpan Pengaturan, lalu coba lagi.'];
    $smtp = defined('SMTP_HOST') && SMTP_HOST !== '';
    $host = preg_replace('/:\d+$/', '', app_host());
    $from = $smtp ? ((defined('SMTP_FROM') && SMTP_FROM) ? SMTP_FROM : (defined('SMTP_USER') ? SMTP_USER : '')) : ((defined('RESET_FROM') && RESET_FROM) ? RESET_FROM : ('noreply@' . preg_replace('/^www\./', '', $host)));
    $GLOBALS['smtp_last_error'] = '';
    $body = "Ini email percobaan dari panel admin Racikin.\n\nPengirim : $from\nMetode   : " . ($smtp ? 'SMTP (' . SMTP_HOST . ':' . (defined('SMTP_PORT') ? SMTP_PORT : 465) . ')' : 'mail() bawaan hosting') . "\nWaktu    : " . gmdate('Y-m-d H:i', time() + 7 * 3600) . " WIB\n\nKalau email ini sampai, pemberitahuan pendaftar baru akan sampai juga.\n\n— Racikin";
    $headers = "From: Racikin <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    $okN = 0;
    foreach ($to as $t) if (app_mail($t, 'Tes email Racikin', $body, $headers)) $okN++;
    $via = $smtp ? 'lewat SMTP' : 'lewat mail() bawaan hosting (tanpa SMTP — kemungkinan masuk spam)';
    if ($okN === count($to)) return [true, "Email tes terkirim $via ke: " . implode(', ', $to) . '. Cek kotak masuk & folder spam. ✓'];
    $why = $smtp ? ($GLOBALS['smtp_last_error'] ?: 'tidak diketahui — cek error log hosting') : 'mail() ditolak hosting';
    return [false, "Email tes GAGAL $via. Penyebab: $why"];
}
