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
    $t0 = microtime(true);
    $r = smtp_read($fp);
    $dt = round(microtime(true) - $t0, 1);
    $rep = trim(preg_replace('/\s+/', ' ', $r));
    if ($rep === '') {   // server diam: bedakan waktu habis vs koneksi ditutup
        $meta = @stream_get_meta_data($fp);
        $rep = !empty($meta['timed_out']) ? "(server tidak menjawab dalam {$dt} detik — waktu habis)" : (@feof($fp) ? "(server menutup koneksi setelah {$dt} detik)" : '(jawaban kosong)');
    }
    $GLOBALS['smtp_last_reply'] = $rep;   // jawaban server (untuk diagnosis)
    return strpos($r, (string)$okPrefix) === 0;
}

function smtp_send($to, $subject, $body, $headers) {
    $GLOBALS['smtp_last_reply'] = '';
    @set_time_limit(120);   // beri ruang; batas waktu bawaan hosting (30 dtk) bisa terlewati bila server mail lambat
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
    foreach (['stream_socket_client', 'stream_context_create', 'stream_set_timeout', 'fgets', 'fwrite'] as $fn) {
        if (!function_exists($fn)) { $GLOBALS['smtp_last_error'] = "fungsi PHP \"$fn\" dinonaktifkan oleh hosting, jadi SMTP tidak bisa dipakai. Minta Rumahweb mengaktifkannya, atau kosongkan SMTP_HOST untuk memakai mail() bawaan."; return false; }
    }
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $GLOBALS['smtp_last_error'] = "tidak bisa terhubung ke $host:$port ($es). Cek SMTP_HOST/SMTP_PORT/SMTP_SECURE."; error_log("smtp connect gagal: $es"); return false; }
    stream_set_timeout($fp, 25);
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
    } catch (Throwable $e) {
        $why = ['greeting' => 'server tidak menjawab dengan benar', 'EHLO' => 'server menolak salam EHLO', 'STARTTLS' => 'STARTTLS ditolak (coba SMTP_SECURE=ssl, port 465)', 'TLS' => 'enkripsi TLS gagal (sertifikat server tak cocok dengan SMTP_HOST? coba host yang sama dengan nama di sertifikat)',
                'AUTH' => 'LOGIN DITOLAK — alamat/sandi SMTP salah (SMTP_USER / SMTP_PASS)', 'MAIL FROM' => 'pengirim ditolak (SMTP_FROM harus = akun email yang login)', 'RCPT TO' => 'alamat tujuan ditolak', 'DATA' => 'server menolak isi pesan', 'akhir pesan' => 'pesan ditolak di akhir (kemungkinan dianggap spam)'];
        $rep = (string)($GLOBALS['smtp_last_reply'] ?? '');
        $GLOBALS['smtp_last_error'] = ($why[$e->getMessage()] ?? ('gagal di tahap ' . $e->getMessage())) . ($rep !== '' ? ' — jawaban server: "' . mb_substr($rep, 0, 220) . '"' : '');
        error_log('smtp gagal di tahap ' . $e->getMessage()); @fclose($fp); return false;
    }
}

function app_mail($to, $subject, $body, $headers = '') {
    try {
        if (defined('SMTP_HOST') && SMTP_HOST !== '') return smtp_send($to, $subject, $body, $headers);
        if (!function_exists('mail')) { $GLOBALS['smtp_last_error'] = 'fungsi mail() dinonaktifkan hosting — isi pengaturan SMTP di config.php'; return false; }
        return @mail($to, $subject, $body, $headers);
    } catch (Throwable $e) {
        $GLOBALS['smtp_last_error'] = 'error PHP: ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        error_log('app_mail: ' . $GLOBALS['smtp_last_error']);
        return false;
    }
}


// Kode versi berkas ini (8 karakter pertama md5). Dipakai untuk memastikan versi yang berjalan di server sama dengan yang di-deploy.
function mail_build() { return substr((string)@md5_file(__FILE__), 0, 8); }
// Email bantuan untuk pelanggan (config.php: define('SUPPORT_EMAIL', '...')). Dipakai sebagai Reply-To semua email ke klien.
function support_email() {
    $e = defined('SUPPORT_EMAIL') ? trim((string)SUPPORT_EMAIL) : '';
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : 'info@racikin.com';
}
// Alamat pengirim default (SMTP_FROM / SMTP_USER bila SMTP aktif, selain itu RESET_FROM atau noreply@host)
function mail_from_addr() {
    if (defined('SMTP_HOST') && SMTP_HOST !== '') return (defined('SMTP_FROM') && SMTP_FROM) ? SMTP_FROM : (defined('SMTP_USER') ? SMTP_USER : '');
    $host = preg_replace('/:\d+$/', '', app_host());
    return (defined('RESET_FROM') && RESET_FROM) ? RESET_FROM : ('noreply@' . preg_replace('/^www\./', '', $host));
}
// Penerima pemberitahuan admin: ADMIN_EMAIL (config) + pengaturan notify_email (admin.php).
function admin_recipients() {
    $list = defined('ADMIN_EMAIL') ? (string)ADMIN_EMAIL : '';
    try { $st = settings_all(); $list .= ',' . ($st['notify_email'] ?? ''); } catch (Throwable $e) { }
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $list)), function ($x) { return filter_var($x, FILTER_VALIDATE_EMAIL); })));
}
// Kirim email teks biasa ke semua penerima admin. Aman dipanggil kapan saja (tak pernah melempar).
function notify_admin($subject, $body) {
    try {
        $to = admin_recipients(); if (!$to) return 0;
        $headers = "From: Racikin <" . mail_from_addr() . ">\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
        $n = 0; foreach ($to as $t) if (app_mail($t, preg_replace('/[\r\n]+/', ' ', $subject), $body, $headers)) $n++;
        return $n;
    } catch (Throwable $e) { error_log('notify_admin: ' . $e->getMessage()); return 0; }
}
// Email ke klien (satu penerima), teks biasa.
function mail_client($to, $subject, $body) {
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $headers = "From: Racikin <" . mail_from_addr() . ">\r\nReply-To: Racikin <" . support_email() . ">\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
        return app_mail($to, preg_replace('/[\r\n]+/', ' ', $subject), $body, $headers);
    } catch (Throwable $e) { error_log('mail_client: ' . $e->getMessage()); return false; }
}
// Diagnosa SMTP: coba beberapa jalur sampai tahap penerima, TANPA mengirim email. Satu koneksi BARU per penerima
// (server yang macet di satu penerima tidak boleh membuat penerima lain ikut gagal). Return [ok, ringkasan].
function smtp_try($host, $port, $secure, $rcpt, $timeout = 6) {
    $user = defined('SMTP_USER') ? (string)SMTP_USER : ''; $pass = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
    $from = (defined('SMTP_FROM') && SMTP_FROM) ? (string)SMTP_FROM : $user;
    $t0 = microtime(true); $el = function () use ($t0) { return round(microtime(true) - $t0, 1); };
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);   // diagnosa: abaikan sertifikat agar masalah lain terlihat
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, 'tidak bisa terhubung (' . trim($es) . ')'];
    stream_set_timeout($fp, $timeout);
    $ehlo = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
    $stage = 'sambutan';
    $chk = function ($cmd, $ok, $st) use ($fp, &$stage) { $stage = $st; return smtp_cmd($fp, $cmd, $ok); };
    $good = $chk(null, '220', 'sambutan') && $chk("EHLO $ehlo", '250', 'EHLO');
    if ($good && $secure === 'tls') { $good = $chk('STARTTLS', '220', 'STARTTLS') && @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) && $chk("EHLO $ehlo", '250', 'EHLO2'); }
    if ($good && $user !== '') $good = $chk('AUTH LOGIN', '334', 'login') && $chk(base64_encode($user), '334', 'login') && $chk(base64_encode($pass), '235', 'login (sandi)');
    $good = $good && $chk("MAIL FROM:<$from>", '250', 'MAIL FROM') && $chk("RCPT TO:<$rcpt>", '250', 'RCPT TO');
    $rep = (string)($GLOBALS['smtp_last_reply'] ?? '');
    if ($good) @fwrite($fp, "RSET\r\nQUIT\r\n");
    @fclose($fp);
    return $good ? [true, 'DITERIMA (' . $el() . 's)'] : [false, "gagal di tahap $stage (" . $el() . 's): ' . mb_substr($rep, 0, 160)];
}
// Pemeriksaan jaringan dari sisi server: DNS (tanpa ini server mail tak bisa mencari tujuan) & koneksi keluar.
function net_diagnose($timeout = 5) {
    $o = [];
    $t = microtime(true);
    $mx = []; $okMx = @getmxrr('gmail.com', $mx); $d = round(microtime(true) - $t, 1);
    $o[] = 'DNS dari server: MX gmail.com → ' . ($okMx && $mx ? count($mx) . " rekaman ($d" . 's)' : "GAGAL / kosong ($d" . 's) — resolver DNS server bermasalah atau sangat lambat');
    foreach ([['gmail-smtp-in.l.google.com', 25, 'Gmail (langsung, port 25)'], ['smtp.gmail.com', 587, 'Gmail SMTP (587)'], ['smtp-relay.brevo.com', 587, 'Brevo SMTP relay (587)']] as [$h, $p, $lab]) {
        $a = microtime(true); $fp = @stream_socket_client("tcp://$h:$p", $en, $es, $timeout); $dd = round(microtime(true) - $a, 1);
        $o[] = "Koneksi keluar ke $lab → " . ($fp ? "TERBUKA ($dd" . 's)' : "TERTUTUP/lambat ($dd" . 's: ' . trim((string)$es) . ')'); if ($fp) @fclose($fp);
    }
    return $o;
}
function run_smtp_diagnose() {
    try {
        $lines = [];
        $t0 = microtime(true);
        if (function_exists('pool_diagnose')) { try { $lines = pool_diagnose(master_pdo()); $lines[] = ''; } catch (Throwable $e) { $lines[] = 'Diagnosa kolam gagal: ' . $e->getMessage(); $lines[] = ''; } }
        @set_time_limit(180);
        $maxx = (int)ini_get('max_execution_time'); $budget = $maxx > 0 ? max(10, $maxx - 6) : 150;   // jangan melewati batas waktu PHP hosting
        $left = function () use ($t0, $budget) { return $budget - (microtime(true) - $t0); };
        $lines[] = 'Jaringan server:';
        if ($left() > 25) { foreach (net_diagnose(4) as $x) $lines[] = '  ' . $x; } else $lines[] = '  (dilewati: batas waktu PHP ' . $maxx . ' dtk)';
        $lines[] = '';
        if (!(defined('SMTP_HOST') && SMTP_HOST !== '')) { $lines[] = 'SMTP belum aktif: SMTP_HOST kosong di config.php. Versi mail.php v' . mail_build(); return [true, implode("\n", $lines)]; }
        $to = admin_recipients(); $rc = array_values(array_unique(array_filter(array_merge($to, ['info@racikin.com']))));
        $h = (string)SMTP_HOST;
        $lines[] = 'SMTP (tanpa mengirim email), penerima uji: ' . implode(', ', $rc);
        foreach ([[$h, 465, 'ssl'], [$h, 587, 'tls']] as [$hh, $pp, $sc]) {
            foreach ($rc as $r) {
                if ($left() < 14) { $lines[] = "  $hh:$pp/$sc → $r: (dilewati: sisa waktu PHP habis)"; continue; }
                [$ok, $info] = smtp_try($hh, $pp, $sc, $r, 6);
                $lines[] = "  $hh:$pp/$sc → $r: " . ($ok ? '✓ ' : '✗ ') . $info;
            }
        }
        $lines[] = 'Versi mail.php v' . mail_build();
        return [true, implode("\n", $lines)];
    } catch (Throwable $e) { return [false, 'Diagnosa gagal: ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')']; }
}
// Uji kirim dari halaman admin: kirim email percobaan ke penerima pemberitahuan. Return [bool ok, string pesan].
function run_mail_test() {
    try { return run_mail_test_inner(); }
    catch (Throwable $e) { error_log('run_mail_test: ' . $e->getMessage()); return [false, 'Tes gagal karena error PHP: ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . '). Kirim pesan ini ke pengembang.']; }
}
function run_mail_test_inner() {
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
    $ver = ' [mail.php v' . mail_build() . ']';
    if ($okN === count($to)) return [true, "Email tes terkirim $via ke: " . implode(', ', $to) . '. Cek kotak masuk & folder spam. ✓' . $ver];
    $why = $smtp ? ($GLOBALS['smtp_last_error'] ?: 'tidak diketahui — cek error log hosting') : 'mail() ditolak hosting';
    return [false, "Email tes GAGAL $via. Penyebab: $why" . $ver];
}
