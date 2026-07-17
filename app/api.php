<?php
// ================== API JSON ==================
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

// Anti-CSRF: hanya layani request dari app (fetch mengirim header khusus).
// Form lintas-situs tak bisa menyetel header kustom tanpa memicu CORS preflight.
if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit; }

// konstanta "Ingat saya" — didefinisikan di atas krn handle_auth dipanggil sebelum badan file lainnya
const REMEMBER_COOKIE = 'racikin_remember';
const REMEMBER_DAYS = 30;

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = [];
$action = $in['action'] ?? 'bootstrap';   // JANGAN ambil dari $_GET (cegah CSRF via navigasi)

// ---- AUTENTIKASI (tak butuh login dulu) ----
if (in_array($action, ['authStatus','authLogin','authRegister','authLogout','authResetRequest','authResetConfirm','authChangePassword','usersList','userSave','userDelete','subInfo','startRenewal','submitProof'], true)) {
    try { handle_auth($action, $in); }
    catch (Exception $e) { error_log('auth: '.$e->getMessage()); http_response_code(500); echo json_encode(['error' => 'Terjadi kesalahan pada server.']); }
    exit;
}

$pdo = db();   // butuh login (kalau belum → 401 needLogin)

// Gerbang langganan: kalau paid_until sudah lewat, blokir semua aksi data (perpanjangan lewat jalur auth di atas).
$__sub = sub_status(current_business());
if ($__sub['expired']) { http_response_code(403); echo json_encode(['error' => 'Langganan sudah berakhir. Silakan perpanjang untuk melanjutkan.', 'expired' => true, 'sub' => $__sub]); exit; }

// ===== "Ingat saya": token persisten (selector:validator) — konstanta didefinisikan di atas file =====
function is_https() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
function set_remember_cookie($value, $expires) {
    setcookie(REMEMBER_COOKIE, $value, ['expires'=>$expires, 'path'=>'/', 'httponly'=>true, 'samesite'=>'Lax', 'secure'=>is_https()]);
}
function issue_remember_token($m, $alias, $email) {
    $selector = bin2hex(random_bytes(12));    // 24 char, publik (dipakai lookup)
    $validator = bin2hex(random_bytes(32));   // 64 char, rahasia (cookie only; DB simpan hash)
    $exp = time() + REMEMBER_DAYS * 86400;   // untuk cookie: epoch nyata (dibaca browser)
    // expires di DB pakai NOW() MySQL (bukan waktu PHP) — konsisten walau timezone PHP≠MySQL
    $m->prepare("INSERT INTO remember_tokens (alias,email,selector,validator_hash,expires) VALUES (?,?,?,?, DATE_ADD(NOW(), INTERVAL ? DAY))")
      ->execute([$alias, $email, $selector, hash('sha256', $validator), REMEMBER_DAYS]);
    set_remember_cookie($selector . ':' . $validator, $exp);
}
function clear_remember($m) {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $sel = explode(':', $_COOKIE[REMEMBER_COOKIE], 2)[0];
        if ($sel !== '') $m->prepare("DELETE FROM remember_tokens WHERE selector=?")->execute([$sel]);
    }
    set_remember_cookie('', time() - 3600);
}
// coba auto-login dari cookie; rotasi token tiap sukses; cabut semua bila terdeteksi token dicuri
function try_remember_login($m) {
    if (empty($_COOKIE[REMEMBER_COOKIE])) return false;
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') { set_remember_cookie('', time() - 3600); return false; }
    [$selector, $validator] = $parts;
    $m->prepare("DELETE FROM remember_tokens WHERE expires < NOW()")->execute();
    $q = $m->prepare("SELECT * FROM remember_tokens WHERE selector=?"); $q->execute([$selector]); $t = $q->fetch();
    if (!$t) { set_remember_cookie('', time() - 3600); return false; }
    if (!hash_equals($t['validator_hash'], hash('sha256', $validator))) {
        // selector cocok tapi validator salah → token kemungkinan dicuri: cabut semua sesi identitas ini
        $m->prepare("DELETE FROM remember_tokens WHERE alias=? AND email=?")->execute([$t['alias'], $t['email']]);
        set_remember_cookie('', time() - 3600); return false;
    }
    $b = $m->prepare("SELECT * FROM businesses WHERE alias=?"); $b->execute([$t['alias']]); $b = $b->fetch();
    $u = $m->prepare("SELECT * FROM users WHERE alias=? AND email=?"); $u->execute([$t['alias'], $t['email']]); $u = $u->fetch();
    if (!$b || !$u || (int)($b['active'] ?? 1) === 0) { $m->prepare("DELETE FROM remember_tokens WHERE selector=?")->execute([$selector]); set_remember_cookie('', time() - 3600); return false; }
    // rotasi: buang token lama, terbitkan baru (pemakaian ulang token lama → terdeteksi di cabang di atas)
    $m->prepare("DELETE FROM remember_tokens WHERE selector=?")->execute([$selector]);
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    session_regenerate_id(true);
    $_SESSION['alias'] = $t['alias']; $_SESSION['user_name'] = $u['name']; $_SESSION['email'] = $t['email'];
    $_SESSION['role'] = $u['role'] ?? 'owner'; $_SESSION['perms'] = $u['perms'] ?? '';
    issue_remember_token($m, $t['alias'], $t['email']);
    return ['name'=>$b['name'], 'alias'=>$t['alias'], 'user'=>$u['name'], 'email'=>$t['email'], 'sub'=>sub_status($b)] + me_payload();
}
// status langganan sebuah usaha: sisa hari + apakah kadaluarsa (dihitung via MySQL, aman timezone)
function sub_status($b) {
    if (!$b || empty($b['paid_until'])) return ['paidUntil' => null, 'daysLeft' => null, 'expired' => false];  // null = tanpa batas (legacy)
    $q = master_pdo()->prepare("SELECT DATEDIFF(?, CURDATE())"); $q->execute([$b['paid_until']]);
    $days = (int)$q->fetchColumn();
    return ['paidUntil' => $b['paid_until'], 'daysLeft' => $days, 'expired' => ($days < 0)];
}
// payload user aktif (role + daftar menu yg boleh diakses)
function me_payload() {
    $perms = trim($_SESSION['perms'] ?? '');
    return ['role' => $_SESSION['role'] ?? 'owner',
            'perms' => $perms === '' ? [] : array_values(array_filter(explode(',', $perms))),
            'email' => $_SESSION['email'] ?? ''];
}

// Cek kekuatan password — kembalikan pesan JELAS apa yang kurang (UMKM: harus gamblang), '' bila lolos.
function pw_problem($pass) {
    $pass = (string)$pass;
    $len = mb_strlen($pass);
    if ($len < 8) return 'Password terlalu pendek — minimal 8 karakter (punyamu baru ' . $len . '). Tambah ' . (8 - $len) . ' karakter lagi.';
    if (preg_match('/^(.)\1+$/u', $pass)) return 'Password jangan satu karakter diulang terus (mis. "aaaaaaaa"). Campur huruf dan angka biar aman.';
    if (preg_match('/^(0123456789|123456789|12345678|1234567890|9876543210|87654321|abcdefgh|abcdefghij)$/i', $pass)) return 'Password jangan berurutan (mis. "12345678"). Buat kombinasi yang acak.';
    $common = ['12345678','123456789','1234567890','password','password1','passw0rd','qwerty123','qwertyui','11111111','00000000','iloveyou','admin123','racikin123','88888888','asdfasdf','1q2w3e4r','123123123','sayang123'];
    if (in_array(strtolower($pass), $common, true)) return 'Password ini terlalu umum & gampang ditebak orang. Pilih yang lebih unik (campur huruf, angka, atau simbol).';
    return '';
}

function handle_auth($action, $in) {
    global $DB_HOST, $DB_USER, $DB_PASS;
    $m = master_pdo();
    if ($action === 'authStatus') {
        $b = current_business();
        if (!$b) {
            $r = try_remember_login($m);
            if ($r) { echo json_encode(['loggedIn'=>true] + $r); return; }
            echo json_encode(['loggedIn'=>false]); return;
        }
        echo json_encode(['loggedIn'=>true, 'name'=>$b['name'], 'alias'=>$b['alias'], 'user'=>($_SESSION['user_name'] ?? ''), 'sub'=>sub_status($b)] + me_payload());
        return;
    }
    if ($action === 'authLogout') {
        clear_remember($m);
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $_SESSION = []; @session_destroy();
        echo json_encode(['ok'=>true]); return;
    }
    if ($action === 'authLogin') {
        $alias = strtolower(trim($in['code'] ?? $in['alias'] ?? ''));
        $email = strtolower(trim($in['email'] ?? ''));
        $pass  = (string)($in['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
        // rate-limit: maks 10 percobaan gagal / 10 menit per IP (anti brute-force)
        $ac = $m->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND ts > (NOW() - INTERVAL 10 MINUTE)");
        $ac->execute([$ip]);
        if ($ac->fetchColumn() >= 10) { http_response_code(429); echo json_encode(['error' => 'Terlalu banyak percobaan gagal. Coba lagi dalam 10 menit.']); return; }
        $b = null; if ($alias !== '') { $q = $m->prepare("SELECT * FROM businesses WHERE alias=?"); $q->execute([$alias]); $b = $q->fetch(); }
        $u = null; if ($b) { $x = $m->prepare("SELECT * FROM users WHERE alias=? AND email=?"); $x->execute([$alias, $email]); $u = $x->fetch(); }
        // pesan seragam supaya kode usaha tak bisa dienumerasi
        if (!$b || !$u || !password_verify($pass, $u['pass_hash'])) {
            $m->prepare("INSERT INTO login_attempts (ip,ts) VALUES (?,NOW())")->execute([$ip]);
            http_response_code(400); echo json_encode(['error' => 'Kode usaha, email, atau password salah.']); return;
        }
        $m->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([$ip]);   // sukses → reset counter
        if ((int)($b['active'] ?? 1) === 0) { http_response_code(403); echo json_encode(['error' => 'Akun belum aktif — menunggu aktivasi admin setelah pembayaran.', 'pending' => true]); return; }
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        session_regenerate_id(true);
        $_SESSION['alias'] = $alias; $_SESSION['user_name'] = $u['name']; $_SESSION['email'] = $email;
        $_SESSION['role'] = $u['role'] ?? 'owner'; $_SESSION['perms'] = $u['perms'] ?? '';
        if (!empty($in['remember'])) issue_remember_token($m, $alias, $email);
        echo json_encode(['ok'=>true, 'name'=>$b['name'], 'alias'=>$alias, 'user'=>$u['name'], 'email'=>$email, 'sub'=>sub_status($b)] + me_payload()); return;
    }
    if ($action === 'authRegister') {
        $name = trim($in['name'] ?? ''); $alias = strtolower(trim($in['code'] ?? $in['alias'] ?? ''));
        $user = trim($in['user'] ?? ''); $email = strtolower(trim($in['email'] ?? '')); $pass = (string)($in['password'] ?? '');
        if ($name === '' || $user === '') { http_response_code(400); echo json_encode(['error' => 'Nama usaha & nama user wajib diisi.']); return; }
        if (!valid_alias($alias)) { http_response_code(400); echo json_encode(['error' => 'Kode usaha harus 2–24 huruf kecil/angka tanpa spasi.']); return; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['error' => 'Email tidak valid.']); return; }
        if (($pe = pw_problem($pass)) !== '') { http_response_code(400); echo json_encode(['error' => $pe]); return; }
        // atomik: kalau salah satu insert gagal, semua batal (alias tak terkunci). DB tenant dibuat lazy saat bootstrap.
        try {
            $m->beginTransaction();
            $q = $m->prepare("SELECT COUNT(*) FROM businesses WHERE alias=?"); $q->execute([$alias]);
            if ($q->fetchColumn()) { $m->rollBack(); http_response_code(400); echo json_encode(['error' => 'Kode usaha "'.$alias.'" sudah dipakai, pilih yang lain.']); return; }
            $m->prepare("INSERT INTO businesses (alias,name,db_name,user_name,active,created) VALUES (?,?,?,?,0,NOW())")
              ->execute([$alias, $name, DB_PREFIX . $alias, $user]);
            $m->prepare("INSERT INTO users (alias,email,name,pass_hash,role,perms,created) VALUES (?,?,?,?,'owner','',NOW())")
              ->execute([$alias, $email, $user, password_hash($pass, PASSWORD_DEFAULT)]);
            $m->commit();
        } catch (Exception $e) {
            if ($m->inTransaction()) $m->rollBack();
            http_response_code(400); echo json_encode(['error' => 'Gagal daftar — kode usaha mungkin sudah dipakai.']); return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        session_regenerate_id(true);
        // FREE TRIAL: coba auto-provision DB tenant via cPanel API → aktif 30 hari langsung.
        // Kalau tak dikonfigurasi / gagal → fallback PENDING (tunggu aktivasi admin). Defensif, tak bikin nyangkut.
        if (defined('TRIAL_ON') && TRIAL_ON) {
            $days = defined('TRIAL_DAYS') ? max(1, (int)TRIAL_DAYS) : 30;
            $dbUser = defined('CPANEL_DB_USER') && CPANEL_DB_USER ? CPANEL_DB_USER : $DB_USER;
            if (cpanel_create_db(DB_PREFIX . $alias, $dbUser)) {
                $m->prepare("UPDATE businesses SET active=1, paid_until=DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE alias=?")->execute([$days, $alias]);
                $_SESSION['alias'] = $alias; $_SESSION['user_name'] = $user; $_SESSION['email'] = $email; $_SESSION['role'] = 'owner'; $_SESSION['perms'] = '';
                $b2 = $m->prepare("SELECT * FROM businesses WHERE alias=?"); $b2->execute([$alias]); $b2 = $b2->fetch();
                echo json_encode(['ok'=>true, 'trial'=>true, 'loggedIn'=>true, 'trialDays'=>$days, 'name'=>$name, 'alias'=>$alias, 'user'=>$user, 'email'=>$email, 'sub'=>sub_status($b2)] + me_payload()); return;
            }
        }
        // akun dibuat sebagai PENDING — tidak auto-login; tunggu aktivasi admin (setelah bayar)
        echo json_encode(['ok'=>true, 'pending'=>true, 'name'=>$name, 'alias'=>$alias]); return;
    }
    // ---- ganti password (sedang login) ----
    if ($action === 'authChangePassword') {
        $b = current_business();
        if (!$b) { http_response_code(401); echo json_encode(['error' => 'Belum login.']); return; }
        $old = (string)($in['oldPassword'] ?? ''); $new = (string)($in['newPassword'] ?? '');
        if (($pe = pw_problem($new)) !== '') { http_response_code(400); echo json_encode(['error' => $pe]); return; }
        $email = $_SESSION['email'] ?? '';
        $u = $m->prepare("SELECT * FROM users WHERE alias=? AND email=?"); $u->execute([$b['alias'], $email]); $u = $u->fetch();
        if (!$u || !password_verify($old, $u['pass_hash'])) { http_response_code(400); echo json_encode(['error' => 'Password lama salah.']); return; }
        $m->prepare("UPDATE users SET pass_hash=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        $m->prepare("DELETE FROM remember_tokens WHERE alias=? AND email=?")->execute([$b['alias'], $email]);
        echo json_encode(['ok' => true]); return;
    }
    // ---- minta reset password (kirim email) ----
    if ($action === 'authResetRequest') {
        $alias = strtolower(trim($in['code'] ?? $in['alias'] ?? '')); $email = strtolower(trim($in['email'] ?? ''));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
        $ac = $m->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND ts > (NOW() - INTERVAL 15 MINUTE)");
        $ac->execute([$ip]);
        if ($ac->fetchColumn() >= 15) { http_response_code(429); echo json_encode(['error' => 'Terlalu banyak permintaan. Coba lagi nanti.']); return; }
        $u = null;
        if ($alias !== '' && $email !== '') { $x = $m->prepare("SELECT * FROM users WHERE alias=? AND email=?"); $x->execute([$alias, $email]); $u = $x->fetch(); }
        if ($u) {
            $selector = bin2hex(random_bytes(10));   // 20 char
            $token = bin2hex(random_bytes(32));
            $m->prepare("DELETE FROM password_resets WHERE alias=? AND email=?")->execute([$alias, $email]);
            // expires dihitung di sisi MySQL (NOW()) — hindari mismatch timezone PHP vs MySQL yang bikin token lahir "kadaluarsa"
            $m->prepare("INSERT INTO password_resets (alias,email,selector,token_hash,expires) VALUES (?,?,?,?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
              ->execute([$alias, $email, $selector, hash('sha256', $token)]);
            send_reset_email($email, $u['name'] ?? '', $alias, $selector . '.' . $token);
        } else {
            $m->prepare("INSERT INTO login_attempts (ip,ts) VALUES (?,NOW())")->execute([$ip]);
        }
        // selalu balas sukses (cegah tebak email terdaftar)
        echo json_encode(['ok' => true]); return;
    }
    // ---- konfirmasi reset (set password baru dari link email) ----
    if ($action === 'authResetConfirm') {
        $raw = (string)($in['token'] ?? ''); $new = (string)($in['password'] ?? '');
        if (($pe = pw_problem($new)) !== '') { http_response_code(400); echo json_encode(['error' => $pe]); return; }
        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') { http_response_code(400); echo json_encode(['error' => 'Link reset tidak valid.']); return; }
        [$selector, $token] = $parts;
        $m->prepare("DELETE FROM password_resets WHERE expires < NOW()")->execute();
        $q = $m->prepare("SELECT * FROM password_resets WHERE selector=?"); $q->execute([$selector]); $t = $q->fetch();
        if (!$t || !hash_equals($t['token_hash'], hash('sha256', $token))) { http_response_code(400); echo json_encode(['error' => 'Link reset tidak valid atau sudah kadaluarsa.']); return; }
        $m->prepare("UPDATE users SET pass_hash=? WHERE alias=? AND email=?")->execute([password_hash($new, PASSWORD_DEFAULT), $t['alias'], $t['email']]);
        $m->prepare("DELETE FROM password_resets WHERE alias=? AND email=?")->execute([$t['alias'], $t['email']]);
        $m->prepare("DELETE FROM remember_tokens WHERE alias=? AND email=?")->execute([$t['alias'], $t['email']]);
        echo json_encode(['ok' => true]); return;
    }
    // ---- kelola pengguna (khusus pemilik) ----
    if (in_array($action, ['usersList','userSave','userDelete'], true)) {
        $b = current_business();
        if (!$b) { http_response_code(401); echo json_encode(['error' => 'Belum login.']); return; }
        if (($_SESSION['role'] ?? '') !== 'owner') { http_response_code(403); echo json_encode(['error' => 'Hanya pemilik yang boleh kelola pengguna.']); return; }
        $alias = $b['alias'];
        if ($action === 'usersList') {
            $q = $m->prepare("SELECT email,name,role,perms FROM users WHERE alias=? ORDER BY (role='owner') DESC, name");
            $q->execute([$alias]); $rows = $q->fetchAll();
            foreach ($rows as &$r) { $r['perms'] = $r['perms'] ? array_values(array_filter(explode(',', $r['perms']))) : []; } unset($r);
            echo json_encode(['users' => $rows]); return;
        }
        if ($action === 'userSave') {
            $email = strtolower(trim($in['email'] ?? '')); $name = trim($in['name'] ?? ''); $pass = (string)($in['password'] ?? '');
            $perms = is_array($in['perms'] ?? null) ? implode(',', array_filter(array_map(function($p){ return preg_replace('/[^a-z]/', '', (string)$p); }, $in['perms']))) : '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['error' => 'Email tidak valid.']); return; }
            if ($name === '') $name = strstr($email, '@', true) ?: $email;
            $x = $m->prepare("SELECT * FROM users WHERE alias=? AND email=?"); $x->execute([$alias, $email]); $ex = $x->fetch();
            if ($ex) {
                if (($ex['role'] ?? '') === 'owner') { http_response_code(400); echo json_encode(['error' => 'Akun pemilik tak bisa diubah dari sini.']); return; }
                if ($pass !== '' && ($pe = pw_problem($pass)) !== '') { http_response_code(400); echo json_encode(['error' => $pe]); return; }
                if ($pass !== '') $m->prepare("UPDATE users SET name=?, perms=?, pass_hash=? WHERE id=?")->execute([$name, $perms, password_hash($pass, PASSWORD_DEFAULT), $ex['id']]);
                else $m->prepare("UPDATE users SET name=?, perms=? WHERE id=?")->execute([$name, $perms, $ex['id']]);
                echo json_encode(['ok' => true]); return;
            }
            if (($pe = pw_problem($pass)) !== '') { http_response_code(400); echo json_encode(['error' => $pe]); return; }
            $m->prepare("INSERT INTO users (alias,email,name,pass_hash,role,perms,created) VALUES (?,?,?,?,'staff',?,NOW())")
              ->execute([$alias, $email, $name, password_hash($pass, PASSWORD_DEFAULT), $perms]);
            echo json_encode(['ok' => true]); return;
        }
        if ($action === 'userDelete') {
            $email = strtolower(trim($in['email'] ?? ''));
            if ($email === ($_SESSION['email'] ?? '')) { http_response_code(400); echo json_encode(['error' => 'Tak bisa hapus diri sendiri.']); return; }
            $x = $m->prepare("SELECT role FROM users WHERE alias=? AND email=?"); $x->execute([$alias, $email]);
            if ($x->fetchColumn() === 'owner') { http_response_code(400); echo json_encode(['error' => 'Akun pemilik tak bisa dihapus.']); return; }
            $m->prepare("DELETE FROM users WHERE alias=? AND email=? AND role<>'owner'")->execute([$alias, $email]);
            $m->prepare("DELETE FROM remember_tokens WHERE alias=? AND email=?")->execute([$alias, $email]);
            echo json_encode(['ok' => true]); return;
        }
    }
    // ---- Langganan: info harga/status + mulai perpanjangan (nominal unik) + submit bukti transfer ----
    if (in_array($action, ['subInfo','startRenewal','submitProof'], true)) {
        $b = current_business();   // aktif=1 (termasuk yang sudah kadaluarsa → tetap bisa perpanjang)
        if (!$b) { http_response_code(401); echo json_encode(['error' => 'Belum login.', 'needLogin' => true]); return; }
        $alias = $b['alias']; $email = $_SESSION['email'] ?? '';
        $set = settings_all();
        $prices = ['1bln' => (int)$set['price_1bln'], '3bln' => (int)$set['price_3bln'], '1thn' => (int)$set['price_1thn']];

        if ($action === 'subInfo') {
            $q = $m->prepare("SELECT id,plan,amount,status,note,created FROM renewal_requests WHERE alias=? AND status IN ('awaiting','pending') ORDER BY id DESC LIMIT 1");
            $q->execute([$alias]); $req = $q->fetch() ?: null;
            if ($req) $req['amount'] = (int)$req['amount'];
            echo json_encode(['ok' => true, 'sub' => sub_status($b), 'prices' => $prices, 'bank' => $set['bank_info'], 'request' => $req]); return;
        }
        if ($action === 'startRenewal') {
            $plan = $in['plan'] ?? '';
            if (!in_array($plan, ['1bln','3bln','1thn'], true)) { http_response_code(400); echo json_encode(['error' => 'Paket tidak dikenal.']); return; }
            $base = $prices[$plan];
            if ($base <= 0) { http_response_code(400); echo json_encode(['error' => 'Harga paket belum diatur. Hubungi admin dulu.']); return; }
            $uniqOn  = ($set['uniq_on'] ?? '1') === '1';
            $uniqMax = max(1, min(50, (int)($set['uniq_max'] ?? 50)));
            // kode unik yg belum dipakai request aktif lain (cegah nominal bentrok antar-pelanggan)
            $u = $m->query("SELECT amount FROM renewal_requests WHERE status IN ('awaiting','pending')")->fetchAll(PDO::FETCH_COLUMN);
            $usedAmt = array_flip(array_map('intval', $u));
            $uniq = 0; $amount = $base;
            if ($uniqOn) {
                $cands = range(1, $uniqMax); shuffle($cands);
                foreach ($cands as $c) { if (!isset($usedAmt[$base + $c])) { $uniq = $c; break; } }
                if ($uniq === 0) $uniq = random_int(1, $uniqMax);
                $amount = $base + $uniq;
            }
            $m->prepare("DELETE FROM renewal_requests WHERE alias=? AND status='awaiting'")->execute([$alias]);  // ganti draft lama
            $m->prepare("INSERT INTO renewal_requests (alias,email,plan,base_amount,uniq,amount,status,created) VALUES (?,?,?,?,?,?, 'awaiting', NOW())")
              ->execute([$alias, $email, $plan, $base, $uniq, $amount]);
            echo json_encode(['ok' => true, 'plan' => $plan, 'base' => $base, 'uniq' => $uniq, 'amount' => $amount, 'bank' => $set['bank_info']]); return;
        }
        if ($action === 'submitProof') {
            $proof = (string)($in['proof'] ?? '');
            if ($proof === '') { http_response_code(400); echo json_encode(['error' => 'Unggah foto bukti transfer dulu.']); return; }
            if (!is_img_datauri($proof)) { http_response_code(400); echo json_encode(['error' => 'Bukti harus berupa gambar.']); return; }
            if (strlen($proof) > 3500000) { http_response_code(400); echo json_encode(['error' => 'Gambar terlalu besar (maks ~2.5MB).']); return; }
            $note = mb_substr(trim((string)($in['note'] ?? '')), 0, 255);
            $q = $m->prepare("SELECT id FROM renewal_requests WHERE alias=? AND status='awaiting' ORDER BY id DESC LIMIT 1");
            $q->execute([$alias]); $rid = $q->fetchColumn();
            if (!$rid) { http_response_code(400); echo json_encode(['error' => 'Pilih paket dulu sebelum unggah bukti.']); return; }
            // maksimum 1 pending per usaha (cegah antrian & storage membengkak)
            $m->prepare("DELETE FROM renewal_requests WHERE alias=? AND status='pending' AND id<>?")->execute([$alias, $rid]);
            $m->prepare("UPDATE renewal_requests SET proof=?, note=?, status='pending', created=NOW() WHERE id=?")->execute([$proof, $note, $rid]);
            echo json_encode(['ok' => true]); return;
        }
    }
}

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
function send_reset_email($to, $name, $alias, $tokenStr) {
    $link = app_base_url() . '/?reset=' . $tokenStr;
    $host = preg_replace('/:\d+$/', '', app_host());                  // From juga pakai host tepercaya, bukan HTTP_HOST mentah
    $from = defined('RESET_FROM') && RESET_FROM ? RESET_FROM : ('noreply@' . preg_replace('/^www\./', '', $host));
    $subject = 'Reset Password Racikin';
    $body = "Halo" . ($name ? ' ' . $name : '') . ",\n\n"
          . "Ada permintaan reset password untuk usaha \"$alias\" di Racikin.\n"
          . "Klik link ini untuk membuat password baru (berlaku 1 jam):\n\n$link\n\n"
          . "Kalau kamu tidak meminta ini, abaikan saja email ini.\n\n— Racikin";
    $headers = "From: Racikin <$from>\r\nReply-To: $from\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    @mail($to, $subject, $body, $headers);
}

// email pemilik usaha (bisa lebih dari satu owner) dari DB master
function owner_emails($alias) {
    $q = master_pdo()->prepare("SELECT email,name FROM users WHERE alias=? AND role='owner'");
    $q->execute([$alias]); return $q->fetchAll();
}
// samarkan email utk ditampilkan ke staf: an***@gmail.com
function mask_email($e) {
    $e = (string)$e; $at = strpos($e, '@'); if ($at === false) return $e;
    $u = substr($e, 0, $at); $d = substr($e, $at);
    $keep = min(2, max(1, strlen($u) - 1));
    return substr($u, 0, $keep) . str_repeat('*', max(1, strlen($u) - $keep)) . $d;
}
// verifikasi OTP pembatalan (TIDAK meng-consume; lempar ApiError kalau tak valid). attempts di-increment saat salah.
function verify_void_otp($pdo, $notaId, $otp) {
    $otp = preg_replace('/\D/', '', (string)$otp);
    $o = $pdo->prepare("SELECT code,expires_at,attempts FROM void_otps WHERE nota_id=?"); $o->execute([$notaId]); $o = $o->fetch();
    if (!$o) throw new ApiError('Minta kode OTP ke owner dulu.');
    if (strtotime($o['expires_at']) < time()) { $pdo->prepare("DELETE FROM void_otps WHERE nota_id=?")->execute([$notaId]); throw new ApiError('Kode OTP kedaluwarsa — minta lagi.'); }
    if ((int)$o['attempts'] >= 5) { $pdo->prepare("DELETE FROM void_otps WHERE nota_id=?")->execute([$notaId]); throw new ApiError('Terlalu banyak percobaan salah — minta kode baru.'); }
    if (!hash_equals((string)$o['code'], $otp)) {
        $pdo->prepare("UPDATE void_otps SET attempts=attempts+1 WHERE nota_id=?")->execute([$notaId]);
        throw new ApiError('Kode OTP salah.');
    }
}

// Pilih stempel: tanggal bisnis + jam hanya bila entri sistem di hari yg sama (backdate → tanggal saja).
function stamp_of($bizDate, $sysTs) {
    $b = (string)$bizDate; $s = (string)$sysTs;
    return ($s !== '' && substr($s, 0, 10) === substr($b, 0, 10)) ? $s : ($b !== '' ? $b : $s);
}
// Format stempel "YYYY-MM-DD[ HH:MM:SS]" → "11 Jul 2026" atau "11 Jul 2026 · 14.35" (aman, hanya dari angka).
function fmt_stamp_id($s) {
    $s = (string)$s; if ($s === '') return '';
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $s, $m)) return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $mon = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $d = (int)$m[3] . ' ' . $mon[(int)$m[2]] . ' ' . $m[1];
    return isset($m[4]) ? $d . ' &middot; ' . $m[4] . '.' . $m[5] : $d;
}

// Bangun HTML struk untuk dikirim via email — dari data nota di server (otoritatif, anti-injeksi)
function build_receipt_html($pdo, $n) {
    $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $rp  = function ($v) { return 'Rp' . number_format((int)$v, 0, ',', '.'); };
    $biz = current_business(); $bizName = $biz['name'] ?? 'Racikin';
    $pf = $pdo->query("SELECT address,phone,whatsapp,footer FROM profile WHERE id=1")->fetch() ?: [];
    $q = $pdo->prepare("SELECT d.qty,d.harga,p.name FROM distributions d LEFT JOIN products p ON p.id=d.product_id WHERE d.nota_id=?");
    $q->execute([$n['id']]); $items = $q->fetchAll();
    $sub = 0; foreach ($items as $r) $sub += (int)$r['qty'] * (int)$r['harga'];
    $disc = min(max(0, (int)$n['discount']), $sub);
    $svc = (int)$n['service']; $tax = (int)$n['tax'];
    $total = $sub - $disc + $svc + $tax;
    // Stempel waktu struk: lunas → tanggal dibayar terakhir; belum → tanggal nota dibuat.
    $pq = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE nota_id=?");
    $pq->execute([$n['id']]); $paidSum = (int)$pq->fetchColumn();
    $isPaid = $total > 0 && $paidSum >= $total;
    if ($isPaid) {
        $lp = $pdo->prepare("SELECT pdate, created FROM payments WHERE nota_id=? ORDER BY COALESCE(created,pdate) DESC, id DESC LIMIT 1");
        $lp->execute([$n['id']]); $lp = $lp->fetch() ?: [];
        $whenLbl = 'Dibayar'; $whenStr = fmt_stamp_id(stamp_of($lp['pdate'] ?? $n['ndate'], $lp['created'] ?? null));
    } else {
        $whenLbl = 'Dibuat'; $whenStr = fmt_stamp_id(stamp_of($n['ndate'], $n['created'] ?? null));
    }
    $phone = $esc($pf['phone'] ?? ''); $wa = $esc($pf['whatsapp'] ?? '');
    $kontak = ($phone && $wa) ? ($phone === $wa ? "Telp/WA $phone" : "Telp $phone &middot; WA $wa") : ($phone ? "Telp $phone" : ($wa ? "WA $wa" : ''));
    $rows = '';
    foreach ($items as $r) {
        $lt = (int)$r['qty'] * (int)$r['harga'];
        $rows .= '<tr><td style="padding:4px 0">' . $esc($r['name'] ?: '?') . '<br><span style="color:#888;font-size:12px">' . (int)$r['qty'] . ' x ' . $rp($r['harga']) . '</span></td><td style="padding:4px 0;text-align:right;white-space:nowrap">' . $rp($lt) . '</td></tr>';
    }
    $line = function ($l, $v, $b = false) use ($esc, $rp) {
        $st = $b ? ';font-weight:700' : '';
        return '<tr><td style="padding:2px 0' . $st . '">' . $esc($l) . '</td><td style="padding:2px 0;text-align:right' . $st . '">' . $rp($v) . '</td></tr>';
    };
    $tot = '';
    if ($disc > 0 || $svc > 0 || $tax > 0) $tot .= $line('Subtotal', $sub);
    $pct = function ($r) { $r = (float)$r; return $r > 0 ? ' (' . rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.') . '%)' : ''; };
    if ($disc > 0) $tot .= $line('Diskon', -$disc);
    if ($svc > 0)  $tot .= $line('Service' . $pct($n['svc_rate'] ?? 0), $svc);
    if ($tax > 0)  $tot .= $line('Pajak' . $pct($n['tax_rate'] ?? 0), $tax);
    $tot .= $line('TOTAL', $total, true);
    $footer = trim((string)($pf['footer'] ?? '')); if ($footer === '') $footer = 'Terima kasih';
    $addr = $pf['address'] ?? '';
    return '<div style="max-width:340px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#222;border:1px solid #eee;border-radius:12px;padding:20px">'
        . '<div style="text-align:center;border-bottom:1px dashed #ccc;padding-bottom:12px;margin-bottom:12px">'
        . '<div style="font-size:18px;font-weight:800">' . $esc($bizName) . '</div>'
        . ($addr ? '<div style="font-size:12px;color:#666;margin-top:4px">' . $esc($addr) . '</div>' : '')
        . ($kontak ? '<div style="font-size:12px;color:#666">' . $kontak . '</div>' : '')
        . '</div>'
        . '<div style="font-size:13px;color:#444;margin-bottom:10px">' . $esc($n['nota_no'] ?: '') . '<br>' . $esc($whenLbl) . ' ' . $whenStr . (($n['store_name'] && $n['store_name'] !== 'Umum (Kasir)') ? ' &middot; ' . $esc($n['store_name']) : '') . '</div>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;border-bottom:1px dashed #ccc;margin-bottom:8px">' . $rows . '</table>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $tot . '</table>'
        . '<div style="text-align:center;color:#666;font-size:12px;margin-top:16px;border-top:1px dashed #ccc;padding-top:12px">' . $esc($footer) . '<br>&mdash; ' . $esc($bizName) . ' &mdash;</div>'
        . '</div>';
}

// Daftar tabel dipakai bersama oleh reset & importAll (urutan: anak dulu, induk belakangan)
const TABLES = ['payments','distributions','notas','register_sessions','stock_adjustments','cash_out','batch_materials','batch_ops','batch_outputs','batches','material_purchases','material_prices','materials','products','profile'];
const BATCH_CHILDREN = ['batch_materials','batch_ops','batch_outputs'];

class ApiError extends Exception {}   // pesan aman ditampilkan ke user (HTTP 400)
function gid($p) { return $p . bin2hex(random_bytes(5)); }
function today() { return date('Y-m-d'); }
// id dari klien harus alfanumerik saja (cegah XSS lewat interpolasi id ke handler onclick)
function safe_id($id) { $id = (string)$id; return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) ? $id : ''; }
// data-URI gambar yang KETAT (full-match, hanya karakter base64) → cegah breakout HTML/XSS via src="..."
function is_img_datauri($s) { return (bool) preg_match('#^data:image/(png|jpe?g|webp|gif);base64,[A-Za-z0-9+/\r\n=]+$#', (string)$s); }

// ---- Otorisasi per-aksi: pemilik (owner) lolos semua; staf hanya aksi yang menunya diizinkan ----
if (($_SESSION['role'] ?? 'owner') !== 'owner') {
    $OWNER_ONLY = ['reset', 'importAll', 'saveProfile'];   // hapus/timpa data & identitas usaha (incl. QRIS) = khusus pemilik
    $NEED = [
        'saveBatch'=>['produksi'], 'deleteBatch'=>['produksi'],
        'saveNota'=>['pos','distribusi'], 'deleteNota'=>['pos','distribusi'], 'posSale'=>['pos'], 'emailReceipt'=>['pos','distribusi','pembayaran'], 'requestVoidOtp'=>['pos'],
        'openRegister'=>['pos'], 'closeRegister'=>['pos'],
        'addPayment'=>['pos','pembayaran'], 'deletePayment'=>['pembayaran'],
        'saveCashOut'=>['keuangan'], 'deleteCashOut'=>['keuangan'],
        'saveProduct'=>['produk'], 'deleteProduct'=>['produk'],
        'saveStockAdj'=>['produk'], 'deleteStockAdj'=>['produk'],
        'saveStore'=>['pos','toko'], 'deleteStore'=>['toko'],
        'saveMaterial'=>['bahan'], 'deleteMaterial'=>['bahan'], 'deletePricePoint'=>['bahan'], 'resyncPrices'=>['bahan'],
        'saveMatPurchase'=>['bahan'], 'deleteMatPurchase'=>['bahan'],
    ];
    $perms = array_filter(explode(',', $_SESSION['perms'] ?? ''));
    if (in_array($action, $OWNER_ONLY, true)) { http_response_code(403); echo json_encode(['error' => 'Akses ditolak — khusus pemilik usaha.']); exit; }
    if (isset($NEED[$action]) && !array_intersect($NEED[$action], $perms)) { http_response_code(403); echo json_encode(['error' => 'Kamu tak punya akses untuk aksi ini.']); exit; }
}

try {
    switch ($action) {

        case 'bootstrap':
            echo json_encode(bootstrap($pdo));
            break;

        case 'saveBatch': {
            $b = $in['batch'];
            $id = safe_id($b['id'] ?? '') ?: gid('b');
            $pdo->beginTransaction();
            // bahan yang sebelumnya ada (untuk sinkron ulang harga bila ada yang dibuang saat edit)
            $om = $pdo->prepare("SELECT material_id FROM batch_materials WHERE batch_id=? AND material_id IS NOT NULL");
            $om->execute([$id]); $oldMats = $om->fetchAll(PDO::FETCH_COLUMN);
            $pdo->prepare("REPLACE INTO batches (id,bdate,note,created) VALUES (?,?,?,NOW())")
                ->execute([$id, $b['date'] ?: today(), $b['note'] ?? '']);
            foreach (BATCH_CHILDREN as $t)
                $pdo->prepare("DELETE FROM $t WHERE batch_id=?")->execute([$id]);
            insert_batch_children($pdo, $id, $b);
            // kalau harga bahan di batch beda dari master → update master + catat riwayat (tanggal batch)
            sync_material_prices($pdo, $b, $id);
            resync_materials($pdo, $oldMats);   // bahan yang dibuang saat edit ikut disinkron ulang
            // hitung & update HPP tiap produk (per bobot gram)
            update_product_hpp($pdo, $b);
            $pdo->commit();
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteBatch': {
            $id = $in['id'];
            // catat produk terdampak sebelum baris dihapus, untuk recompute HPP setelahnya
            $aff = $pdo->prepare("SELECT product_id FROM batch_outputs WHERE batch_id=?");
            $aff->execute([$id]);
            $affected = $aff->fetchAll(PDO::FETCH_COLUMN);
            $am = $pdo->prepare("SELECT material_id FROM batch_materials WHERE batch_id=? AND material_id IS NOT NULL");
            $am->execute([$id]); $affMats = $am->fetchAll(PDO::FETCH_COLUMN);
            $pdo->beginTransaction();
            foreach (BATCH_CHILDREN as $t)
                $pdo->prepare("DELETE FROM $t WHERE batch_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM batches WHERE id=?")->execute([$id]);
            recompute_product_hpp($pdo, $affected);
            resync_materials($pdo, $affMats);   // harga master bahan ikut disinkron ke batch tersisa
            $pdo->commit();
            echo json_encode(['ok' => true]);
            break;
        }

        case 'saveNota': {
            $pdo->beginTransaction();
            $r = persist_nota($pdo, $in['nota'] ?? []);
            $pdo->commit();
            echo json_encode(['ok' => true, 'id' => $r['id']]);
            break;
        }

        case 'posSale': {
            // Transaksi kasir ATOMIK: simpan nota + catat pembayaran dalam SATU transaksi DB.
            // Cegah "piutang hantu" bila pencatatan bayar gagal setelah nota tersimpan.
            $n = $in['nota'] ?? [];
            $sid = safe_id($n['sessionId'] ?? '');
            $open = $pdo->query("SELECT id FROM register_sessions WHERE status='open' LIMIT 1")->fetchColumn();
            if ($sid === '' || $open !== $sid) throw new ApiError('Kasir belum dibuka atau sesi berbeda. Muat ulang halaman.');
            $pdo->beginTransaction();
            $r = persist_nota($pdo, $n);
            if ($r['total'] > 0)   // catat pembayaran penuh (tunai/non-tunai) sebesar total setelah diskon
                // pdate & created dari jam MySQL yang sama (CURDATE()/NOW()) → tanggal & jam struk konsisten
                $pdo->prepare("INSERT INTO payments (nota_id,pdate,amount,note,created) VALUES (?,CURDATE(),?,?,NOW())")
                    ->execute([$r['id'], $r['total'], 'POS ' . ($r['payMethod'] ?: 'Tunai')]);
            $pdo->commit();
            echo json_encode(['ok' => true, 'id' => $r['id'], 'total' => $r['total']]);
            break;
        }

        case 'deleteNota': {
            $id = $in['id'];
            $meta = $pdo->prepare("SELECT n.session_id AS sid, s.status AS st FROM notas n LEFT JOIN register_sessions s ON s.id=n.session_id WHERE n.id=?");
            $meta->execute([$id]); $meta = $meta->fetch();
            if ($meta) {
                // Lindungi sesi kasir yang sudah ditutup: transaksinya tak boleh dibatalkan (settlement sudah beku)
                if (($meta['st'] ?? '') === 'closed') throw new ApiError('Transaksi dari sesi kasir yang sudah ditutup tak bisa dibatalkan.');
                // Staf membatalkan transaksi KASIR → wajib OTP persetujuan owner (owner sendiri lolos)
                if (($_SESSION['role'] ?? 'owner') !== 'owner' && !empty($meta['sid'])) verify_void_otp($pdo, $id, $in['otp'] ?? '');
            }
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM void_otps WHERE nota_id=?")->execute([$id]);   // OTP sekali pakai
            $pdo->prepare("DELETE FROM payments WHERE nota_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM distributions WHERE nota_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM notas WHERE id=?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['ok' => true]);
            break;
        }

        case 'openRegister': {
            $open = $pdo->query("SELECT id FROM register_sessions WHERE status='open' LIMIT 1")->fetchColumn();
            if ($open) { http_response_code(409); echo json_encode(['error' => 'Kasir sudah dibuka. Tutup dulu sebelum membuka sesi baru.']); break; }
            $float = max(0, intval($in['openingFloat'] ?? 0));
            $id = gid('rs');
            $pdo->prepare("INSERT INTO register_sessions (id,opened_by,opened_at,opening_float,note,status) VALUES (?,?,NOW(),?,?, 'open')")
                ->execute([$id, $_SESSION['email'] ?? '', $float, mb_substr((string)($in['note'] ?? ''), 0, 255)]);
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'closeRegister': {
            $s = $pdo->query("SELECT * FROM register_sessions WHERE status='open' LIMIT 1")->fetch();
            if (!$s) { http_response_code(409); echo json_encode(['error' => 'Tidak ada sesi kasir yang terbuka.']); break; }
            $t = session_totals($pdo, $s['id']);           // dihitung ulang di server (otoritatif)
            $opening  = intval($s['opening_float']);
            $expected = $opening + $t['cash'];             // kas seharusnya di laci = modal awal + penjualan tunai
            $closing  = max(0, intval($in['closingCash'] ?? 0));
            $pdo->prepare("UPDATE register_sessions SET status='closed', closed_by=?, closed_at=NOW(), closing_cash=?, expected_cash=?, cash_sales=?, noncash_sales=?, txn_count=?, note=CONCAT(note, ?) WHERE id=?")
                ->execute([$_SESSION['email'] ?? '', $closing, $expected, $t['cash'], $t['noncash'], $t['count'],
                    (($in['note'] ?? '') !== '') ? (' | tutup: ' . mb_substr((string)$in['note'], 0, 200)) : '', $s['id']]);
            echo json_encode(['ok' => true, 'summary' => [
                'openedBy' => $s['opened_by'], 'openedAt' => $s['opened_at'], 'openingFloat' => $opening,
                'cashSales' => $t['cash'], 'noncashSales' => $t['noncash'], 'count' => $t['count'],
                'expected' => $expected, 'closing' => $closing, 'diff' => $closing - $expected,
            ]]);
            break;
        }

        case 'requestVoidOtp': {
            // Staf minta batalkan transaksi kasir → kirim OTP ke email owner untuk persetujuan
            $notaId = safe_id($in['notaId'] ?? '');
            $q = $pdo->prepare("SELECT n.nota_no, n.session_id, s.status FROM notas n LEFT JOIN register_sessions s ON s.id=n.session_id WHERE n.id=?");
            $q->execute([$notaId]); $n = $q->fetch();
            if (!$n) throw new ApiError('Transaksi tak ditemukan.');
            if (empty($n['session_id'])) throw new ApiError('Hanya transaksi kasir yang butuh persetujuan ini.');
            if (($n['status'] ?? '') === 'closed') throw new ApiError('Sesi kasir sudah ditutup — tak bisa dibatalkan.');
            $tq = $pdo->prepare("SELECT COALESCE(SUM(d.qty*d.harga),0) - COALESCE(nn.discount,0) + COALESCE(nn.service,0) + COALESCE(nn.tax,0) AS total
                FROM notas nn LEFT JOIN distributions d ON d.nota_id=nn.id WHERE nn.id=? GROUP BY nn.id, nn.discount, nn.service, nn.tax");
            $tq->execute([$notaId]); $total = intval($tq->fetchColumn());
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $staff = $_SESSION['user_name'] ?? ($_SESSION['email'] ?? 'Kasir');
            $pdo->prepare("REPLACE INTO void_otps (nota_id,code,requested_by,expires_at,attempts) VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)")
                ->execute([$notaId, $code, $staff]);
            $alias = $_SESSION['alias'] ?? '';
            $owners = owner_emails($alias);
            $biz = current_business(); $bizName = $biz['name'] ?? 'Racikin';
            $host = preg_replace('/:\d+$/', '', app_host());
            $from = defined('RESET_FROM') && RESET_FROM ? RESET_FROM : ('noreply@' . preg_replace('/^www\./', '', $host));
            $subject = "Kode OTP Pembatalan Transaksi - $bizName";
            $body = "Halo,\n\nKasir \"$staff\" meminta MEMBATALKAN transaksi " . ($n['nota_no'] ?: $notaId)
                  . " senilai Rp" . number_format($total, 0, ',', '.') . ".\n\n"
                  . "Kode OTP: $code\n(berlaku 10 menit, sekali pakai)\n\n"
                  . "Berikan kode ini ke kasir HANYA jika kamu menyetujui pembatalan. Abaikan jika tidak.\n\n— $bizName";
            $headers = "From: " . mb_encode_mimeheader($bizName) . " <$from>\r\nReply-To: $from\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
            $sent = 0; foreach ($owners as $o) { if (@mail($o['email'], $subject, $body, $headers)) $sent++; }
            echo json_encode(['ok' => true, 'sent' => $sent > 0, 'owners' => array_map(fn($o) => mask_email($o['email']), $owners)]);
            break;
        }

        case 'emailReceipt': {
            $notaId = safe_id($in['notaId'] ?? '');
            $email  = trim((string)($in['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ApiError('Alamat email tidak valid.');
            $q = $pdo->prepare("SELECT n.*, s.name AS store_name FROM notas n LEFT JOIN stores s ON s.id=n.store_id WHERE n.id=?");
            $q->execute([$notaId]); $n = $q->fetch();
            if (!$n) throw new ApiError('Nota tak ditemukan.');
            $biz = current_business(); $bizName = $biz['name'] ?? 'Racikin';
            $host = preg_replace('/:\d+$/', '', app_host());
            $from = defined('RESET_FROM') && RESET_FROM ? RESET_FROM : ('noreply@' . preg_replace('/^www\./', '', $host));
            $subject = 'Struk ' . ($n['nota_no'] ?: '') . ' - ' . $bizName;
            $headers = "From: " . mb_encode_mimeheader($bizName) . " <$from>\r\nReply-To: $from\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
            $sent = @mail($email, $subject, build_receipt_html($pdo, $n), $headers);
            echo json_encode(['ok' => true, 'sent' => (bool)$sent]);
            break;
        }

        case 'addPayment': {
            $notaId = $in['notaId'];
            $amount = intval($in['amount']);
            // batasi ke sisa tagihan nota (base − diskon + service + pajak) supaya piutang tak jadi negatif/palsu
            $q = $pdo->prepare("SELECT COALESCE(SUM(qty*harga),0) FROM distributions WHERE nota_id=?");
            $q->execute([$notaId]); $sub = intval($q->fetchColumn());
            $q = $pdo->prepare("SELECT COALESCE(discount,0),COALESCE(service,0),COALESCE(tax,0) FROM notas WHERE id=?");
            $q->execute([$notaId]); $row = $q->fetch(PDO::FETCH_NUM) ?: [0,0,0];
            $total = max(0, $sub - intval($row[0])) + intval($row[1]) + intval($row[2]);
            $q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE nota_id=?");
            $q->execute([$notaId]); $paid = intval($q->fetchColumn());
            $remaining = max(0, $total - $paid);
            $amount = min($amount, $remaining);
            if ($amount <= 0) { echo json_encode(['ok' => true, 'skipped' => true]); break; }
            $pdo->prepare("INSERT INTO payments (nota_id,pdate,amount,note,created) VALUES (?,?,?,?,NOW())")
                ->execute([$notaId, $in['date'] ?: today(), $amount, $in['note'] ?? '']);
            echo json_encode(['ok' => true, 'amount' => $amount]);
            break;
        }

        case 'deletePayment':
            $pdo->prepare("DELETE FROM payments WHERE id=?")->execute([intval($in['id'])]);
            echo json_encode(['ok' => true]);
            break;

        case 'saveCashOut': {
            $c = $in['cash'];
            $id = safe_id($c['id'] ?? '') ?: gid('k');
            $cat = in_array(($c['category'] ?? 'lain'), ['prive','operasional','modal','lain'], true) ? $c['category'] : 'lain';
            $amt = intval($c['amount'] ?? 0);
            if ($amt <= 0) { http_response_code(400); echo json_encode(['error' => 'Jumlah harus lebih dari 0.']); break; }
            $pdo->prepare("REPLACE INTO cash_out (id,cdate,category,amount,note) VALUES (?,?,?,?,?)")
                ->execute([$id, $c['date'] ?: today(), $cat, $amt, $c['note'] ?? '']);
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteCashOut':
            $pdo->prepare("DELETE FROM cash_out WHERE id=?")->execute([$in['id']]);
            echo json_encode(['ok' => true]);
            break;

        case 'saveProduct': {
            $p = $in['product'];
            $id = safe_id($p['id'] ?? '') ?: gid('p');
            // REPLACE bersifat destruktif → kolom yang tak dikirim klien diambil dari baris lama
            $q = $pdo->prepare("SELECT photo, track_stock FROM products WHERE id=?"); $q->execute([$id]); $old = $q->fetch() ?: null;
            // foto: kalau key 'photo' dikirim → pakai (boleh '' utk hapus); kalau tidak → pertahankan yang lama
            if (array_key_exists('photo', $p)) {
                $photo = (string)$p['photo'];
                if ($photo !== '' && !is_img_datauri($photo)) { http_response_code(400); echo json_encode(['error' => 'Foto harus berupa gambar.']); break; }
                if (strlen($photo) > 900000) { http_response_code(400); echo json_encode(['error' => 'Foto terlalu besar (maks ~600KB).']); break; }
                $photo = ($photo === '') ? null : $photo;
            } else {
                $photo = $old['photo'] ?? null;
            }
            // lacak stok: 0 = made-to-order (F&B), penjualan tak dibatasi stok. Default 1.
            $track = array_key_exists('trackStock', $p) ? (!empty($p['trackStock']) ? 1 : 0) : (int)($old['track_stock'] ?? 1);
            $pdo->prepare("REPLACE INTO products (id,name,cat,gram,harga,hpp,photo,track_stock) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$id, $p['name'], $p['cat'] ?: 'Umum', intval($p['gram']) ?: 1,
                    intval($p['harga']), intval($p['hpp']), $photo, $track]);
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteProduct':
            $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$in['id']]);
            echo json_encode(['ok' => true]);
            break;

        case 'saveStockAdj': {
            $a = $in['adj'] ?? [];
            $pid = safe_id($a['productId'] ?? '');
            if ($pid === '') { http_response_code(400); echo json_encode(['error' => 'Produk tidak valid.']); break; }
            $qty = intval($a['qty'] ?? 0);   // bertanda: negatif=kurang, positif=tambah
            if ($qty === 0) { http_response_code(400); echo json_encode(['error' => 'Jumlah penyesuaian tidak boleh 0.']); break; }
            $reason = in_array(($a['reason'] ?? ''), ['rusak','hilang','pakai','koreksi','opname','expired'], true) ? $a['reason'] : 'koreksi';
            $id = gid('adj');
            $pdo->prepare("INSERT INTO stock_adjustments (id,product_id,adate,qty,reason,note,created) VALUES (?,?,?,?,?,?,NOW())")
                ->execute([$id, $pid, $a['date'] ?: today(), $qty, $reason, mb_substr((string)($a['note'] ?? ''), 0, 255)]);
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteStockAdj':
            $pdo->prepare("DELETE FROM stock_adjustments WHERE id=?")->execute([safe_id($in['id'] ?? '')]);
            echo json_encode(['ok' => true]);
            break;

        case 'saveStore': {
            $s = $in['store'];
            $id = safe_id($s['id'] ?? '') ?: gid('s');
            $pdo->prepare("REPLACE INTO stores (id,name,contact,address) VALUES (?,?,?,?)")
                ->execute([$id, $s['name'], $s['contact'] ?? '', $s['address'] ?? '']);
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteStore':
            $pdo->prepare("DELETE FROM stores WHERE id=?")->execute([$in['id']]);
            echo json_encode(['ok' => true]);
            break;

        case 'saveMaterial': {
            $m = $in['material'];
            $midIn = safe_id($m['id'] ?? '');
            $id = $midIn ?: gid('m');
            $newPrice = intval($m['price']);
            $old = null;
            if ($midIn) {
                $old = $pdo->prepare("SELECT price FROM materials WHERE id=?");
                $old->execute([$id]); $old = $old->fetchColumn();
            }
            $minStock = max(0, (float)($m['minStock'] ?? 0));
            $pdo->prepare("REPLACE INTO materials (id,name,unit,price,min_stock) VALUES (?,?,?,?,?)")
                ->execute([$id, $m['name'], $m['unit'] ?: 'kg', $newPrice, $minStock]);
            // catat riwayat harga kalau baru atau harga berubah
            if ($old === null || $old === false || intval($old) !== $newPrice) {
                $pdo->prepare("INSERT INTO material_prices (material_id,pdate,price,source) VALUES (?,?,?,'manual')")
                    ->execute([$id, $in['date'] ?? today(), $newPrice]);
            }
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteMaterial':
            $pdo->prepare("DELETE FROM material_prices WHERE material_id=?")->execute([$in['id']]);
            $pdo->prepare("DELETE FROM materials WHERE id=?")->execute([$in['id']]);
            echo json_encode(['ok' => true]);
            break;

        case 'deletePricePoint':
            $pdo->prepare("DELETE FROM material_prices WHERE id=?")->execute([intval($in['id'])]);
            echo json_encode(['ok' => true]);
            break;

        case 'saveMatPurchase': {
            $p = $in['purchase'] ?? [];
            $mid = safe_id($p['materialId'] ?? '');
            if ($mid === '') { http_response_code(400); echo json_encode(['error' => 'Bahan tidak valid.']); break; }
            $qty = (float)($p['qty'] ?? 0);
            if ($qty <= 0) { http_response_code(400); echo json_encode(['error' => 'Jumlah pembelian harus lebih dari 0.']); break; }
            $price = max(0, intval($p['price'] ?? 0));
            $date = $p['date'] ?: today();
            $id = gid('mp');
            $pdo->prepare("INSERT INTO material_purchases (id,material_id,pdate,qty,price,note,created) VALUES (?,?,?,?,?,?,NOW())")
                ->execute([$id, $mid, $date, $qty, $price, mb_substr((string)($p['note'] ?? ''), 0, 255)]);
            // harga pembelian = harga terkini → kalau beda dari master, update + catat riwayat
            if ($price > 0) {
                $cur = $pdo->prepare("SELECT price FROM materials WHERE id=?"); $cur->execute([$mid]); $cur = $cur->fetchColumn();
                if ($cur !== false && intval($cur) !== $price) {
                    $pdo->prepare("UPDATE materials SET price=? WHERE id=?")->execute([$price, $mid]);
                    $pdo->prepare("INSERT INTO material_prices (material_id,pdate,price,source) VALUES (?,?,?,'beli')")->execute([$mid, $date, $price]);
                }
            }
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteMatPurchase':
            $pdo->prepare("DELETE FROM material_purchases WHERE id=?")->execute([safe_id($in['id'] ?? '')]);
            echo json_encode(['ok' => true]);
            break;

        case 'saveProfile': {
            $p = $in['profile'] ?? [];
            $logo = (string)($p['logo'] ?? '');
            if ($logo !== '' && !is_img_datauri($logo)) {
                http_response_code(400); echo json_encode(['error' => 'Logo harus berupa gambar.']); break;
            }
            if (strlen($logo) > 3000000) { http_response_code(400); echo json_encode(['error' => 'Logo terlalu besar (maks ~2MB).']); break; }
            $g = function ($k, $max) use ($p) { return mb_substr(trim((string)($p[$k] ?? '')), 0, $max); };
            $qris = trim(preg_replace('/[\r\n\t]+/', '', (string)($p['qris'] ?? '')));   // buang enter/tab saja; spasi internal (nama merchant) dipertahankan
            if ($qris !== '' && !preg_match('/^[0-9A-Za-z.\- ]{20,600}$/', $qris)) { http_response_code(400); echo json_encode(['error' => 'Kode QRIS tidak valid.']); break; }
            $footer = preg_replace('/[\r\n\t]+/', ' ', $g('footer',255));   // pesan bawah struk (1 baris)
            // service charge & pajak: on/off + tarif % (0..100, maks 2 desimal) — khusus pemilik (saveProfile owner-only)
            $rate = function ($k) use ($p) { return max(0, min(100, round((float)($p[$k] ?? 0), 2))); };
            $svcOn = !empty($p['svc_enabled']) ? 1 : 0; $svcRate = $rate('svc_rate');
            $taxOn = !empty($p['tax_enabled']) ? 1 : 0; $taxRate = $rate('tax_rate');
            $oversell = !empty($p['oversell']) ? 1 : 0;
            // jenis usaha: produksi (default) | fnb — menentukan menu yang tampil di app
            $bizType = in_array(($p['biz_type'] ?? ''), ['produksi','fnb'], true) ? $p['biz_type'] : 'produksi';
            $pdo->prepare("REPLACE INTO profile (id,address,phone,whatsapp,instagram,facebook,tiktok,logo,qris,footer,svc_enabled,svc_rate,tax_enabled,tax_rate,oversell,biz_type) VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$g('address',255),$g('phone',60),$g('whatsapp',60),$g('instagram',120),$g('facebook',120),$g('tiktok',120),$logo,$qris,$footer,$svcOn,$svcRate,$taxOn,$taxRate,$oversell,$bizType]);
            echo json_encode(['ok' => true]);
            break;
        }

        // importAll & reset DISEMBUNYIKAN dari UI pelanggan (berisiko menghapus/menimpa data →
        // boomerang ke developer). Endpoint diblokir juga di server; developer bisa membuka
        // sementara via define('ALLOW_RESTORE', true) di config.php saat perlu pemulihan data.
        case 'importAll':
            if (!defined('ALLOW_RESTORE') || !ALLOW_RESTORE) throw new ApiError('Fitur restore dinonaktifkan. Hubungi admin Racikin bila perlu pemulihan data.');
            import_all($pdo, $in['data']);
            echo json_encode(['ok' => true]);
            break;

        case 'reset':
            if (!defined('ALLOW_RESTORE') || !ALLOW_RESTORE) throw new ApiError('Fitur reset dinonaktifkan. Hubungi admin Racikin bila perlu pengosongan data.');
            foreach (TABLES as $t)
                $pdo->exec("DROP TABLE IF EXISTS $t");
            $pdo->exec("DROP TABLE IF EXISTS meta");   // buang flag seed → init_schema isi contoh awal lagi
            init_schema($pdo);
            echo json_encode(['ok' => true]);
            break;

        case 'resyncPrices': {
            // Backfill: catat semua harga bahan dari batch ke riwayat + set master ke harga batch terbaru
            $pdo->beginTransaction();
            $rows = $pdo->query("SELECT bm.material_id AS mid, ba.bdate AS d, bm.price AS p, ba.id AS bid
                FROM batch_materials bm JOIN batches ba ON ba.id=bm.batch_id
                WHERE bm.material_id IS NOT NULL AND bm.price>0")->fetchAll();
            $dup = $pdo->prepare("SELECT COUNT(*) FROM material_prices WHERE material_id=? AND pdate=? AND price=? AND source='batch'");
            $ins = $pdo->prepare("INSERT INTO material_prices (material_id,pdate,price,source,ref) VALUES (?,?,?,'batch',?)");
            foreach ($rows as $r) { $dup->execute([$r['mid'],$r['d'],intval($r['p'])]); if (!$dup->fetchColumn()) $ins->execute([$r['mid'],$r['d'],intval($r['p']),$r['bid']]); }
            $mids = $pdo->query("SELECT DISTINCT material_id FROM batch_materials WHERE material_id IS NOT NULL AND price>0")->fetchAll(PDO::FETCH_COLUMN);
            resync_materials($pdo, $mids);
            $pdo->commit();
            echo json_encode(['ok'=>true,'materials'=>count($mids)]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Aksi tidak dikenal: ' . $action]);
    }
} catch (ApiError $e) {                      // error tervalidasi → pesan aman ditampilkan ke user
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('api['.$action.']: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan pada server.']);
}

// ---------- helpers ----------
// Kalau harga bahan yang dipakai di batch beda dari harga master saat ini,
// perbarui master dan catat titik riwayat harga (pakai tanggal batch, source 'batch').
function sync_material_prices($pdo, $b, $batchId = null) {
    $date = $b['date'] ?: today();
    $priceByMat = [];
    foreach ($b['materials'] ?? [] as $m) {
        $mid = $m['materialId'] ?? '';
        $price = intval($m['price'] ?? 0);
        if ($mid === '' || $price <= 0) continue;
        $priceByMat[$mid] = $price;   // baris terakhir menang bila material muncul >1x
    }
    if (!$priceByMat) return;
    // catat titik riwayat (hindari duplikat tanggal+harga dari batch); ref = id batch sumber
    $dup = $pdo->prepare("SELECT COUNT(*) FROM material_prices WHERE material_id=? AND pdate=? AND price=? AND source='batch'");
    $ins = $pdo->prepare("INSERT INTO material_prices (material_id,pdate,price,source,ref) VALUES (?,?,?,'batch',?)");
    foreach ($priceByMat as $mid => $price) {
        $dup->execute([$mid, $date, $price]);
        if (!$dup->fetchColumn()) $ins->execute([$mid, $date, $price, $batchId]);
    }
    resync_materials($pdo, array_keys($priceByMat));
}

// Set master harga bahan = harga dari batch bertanggal TERBARU yang memakainya (edit batch lama tak menimpa harga terkini).
function resync_materials($pdo, $matIds) {
    if (!$matIds) return;
    $latest = $pdo->prepare("SELECT bm.price FROM batch_materials bm JOIN batches ba ON ba.id=bm.batch_id WHERE bm.material_id=? AND bm.price>0 ORDER BY ba.bdate DESC, ba.created DESC, ba.id DESC LIMIT 1");
    $upd = $pdo->prepare("UPDATE materials SET price=? WHERE id=?");
    foreach (array_unique($matIds) as $mid) {
        if ($mid === '' || $mid === null) continue;
        $latest->execute([$mid]); $lp = $latest->fetchColumn();
        if ($lp !== false) $upd->execute([intval($lp), $mid]);   // tak ada batch tersisa → biarkan harga terakhir
    }
}

function insert_batch_children($pdo, $id, $b) {
    $sm = $pdo->prepare("INSERT INTO batch_materials (batch_id,material_id,name,qty,unit,price,target_product) VALUES (?,?,?,?,?,?,?)");
    foreach ($b['materials'] ?? [] as $m)
        $sm->execute([$id, $m['materialId'] ?? null, $m['name'] ?? '', floatval($m['qty'] ?? 0), $m['unit'] ?? '', intval($m['price'] ?? 0), ($m['targetProduct'] ?? '') ?: null]);
    $so = $pdo->prepare("INSERT INTO batch_ops (batch_id,name,amount) VALUES (?,?,?)");
    foreach ($b['ops'] ?? [] as $o)
        $so->execute([$id, $o['name'] ?? '', intval($o['amount'] ?? 0)]);
    $sp = $pdo->prepare("INSERT INTO batch_outputs (batch_id,product_id,qty) VALUES (?,?,?)");
    foreach ($b['outputs'] ?? [] as $o)
        $sp->execute([$id, $o['productId'], intval($o['qty'] ?? 0)]);
}

// HPP per produk = (porsi pool dibagi per bobot) + (bahan yang dialokasikan khusus ke produk itu).
// pool = operasional + bahan "dibagi rata"; bahan dengan target_product masuk 100% ke produk tsb.
function update_product_hpp($pdo, $b, $onlyProduct = null) {
    $grams = [];
    foreach ($pdo->query("SELECT id,gram FROM products") as $r) $grams[$r['id']] = intval($r['gram']) ?: 1;

    // produk jadi valid (gabung baris duplikat: total qty per produk)
    $qtyByProd = [];
    foreach ($b['outputs'] ?? [] as $o) {
        $pid = $o['productId'] ?? '';
        if ($pid === '' || intval($o['qty'] ?? 0) <= 0) continue;
        $qtyByProd[$pid] = ($qtyByProd[$pid] ?? 0) + intval($o['qty']);
    }
    if (!$qtyByProd) return;

    $ops = 0; foreach ($b['ops'] ?? [] as $o) $ops += intval($o['amount'] ?? 0);
    $sharedMat = 0; $assigned = [];
    foreach ($b['materials'] ?? [] as $m) {
        $cost = floatval($m['qty'] ?? 0) * intval($m['price'] ?? 0);
        $t = $m['targetProduct'] ?? '';
        if ($t !== '' && isset($qtyByProd[$t])) $assigned[$t] = ($assigned[$t] ?? 0) + $cost;
        else $sharedMat += $cost;
    }
    $pool = $sharedMat + $ops;

    $wsum = 0;
    foreach ($qtyByProd as $pid => $q) $wsum += $q * ($grams[$pid] ?? 1);
    if ($wsum <= 0) return;

    $up = $pdo->prepare("UPDATE products SET hpp=? WHERE id=?");
    foreach ($qtyByProd as $pid => $q) {
        if ($onlyProduct !== null && $pid !== $onlyProduct) continue;  // hanya tulis HPP produk ini (recompute)
        $g = $grams[$pid] ?? 1;
        $fromPool = $pool * $g / $wsum;             // per botol
        $fromAssigned = $q > 0 ? ($assigned[$pid] ?? 0) / $q : 0;
        $up->execute([round($fromPool + $fromAssigned), $pid]);
    }
}

// Muat isi lengkap sebuah batch dari DB (untuk recompute HPP)
function load_batch($pdo, $id) {
    $b = ['id' => $id];
    $q = $pdo->prepare("SELECT material_id AS materialId,name,qty,unit,price,target_product AS targetProduct FROM batch_materials WHERE batch_id=?");
    $q->execute([$id]); $b['materials'] = $q->fetchAll();
    $q = $pdo->prepare("SELECT name,amount FROM batch_ops WHERE batch_id=?");
    $q->execute([$id]); $b['ops'] = $q->fetchAll();
    $q = $pdo->prepare("SELECT product_id AS productId,qty FROM batch_outputs WHERE batch_id=?");
    $q->execute([$id]); $b['outputs'] = $q->fetchAll();
    return $b;
}

// Setelah sebuah batch dihapus, kembalikan HPP produk ke batch terakhir yang masih ada
function recompute_product_hpp($pdo, $productIds) {
    $find = $pdo->prepare(
        "SELECT b.id FROM batches b JOIN batch_outputs o ON o.batch_id=b.id
         WHERE o.product_id=? ORDER BY b.bdate DESC, b.id DESC LIMIT 1");
    foreach (array_unique($productIds) as $pid) {
        $find->execute([$pid]);
        $bid = $find->fetchColumn();
        // kalau tak ada batch tersisa, HPP terakhir dibiarkan (bisa diubah manual di menu Produk)
        // hanya tulis HPP produk ini — jangan sentuh produk lain di batch sumber
        if ($bid) update_product_hpp($pdo, load_batch($pdo, $bid), $pid);
    }
}

// Simpan/ubah 1 nota + itemnya (distributions) DI DALAM transaksi yang sudah dibuka pemanggil.
// Lempar ApiError untuk input tak valid / stok kurang. Return ['id','sub','total','sessionId','payMethod'].
function persist_nota($pdo, $n) {
    $id = safe_id($n['id'] ?? '') ?: gid('n');
    $storeId = $n['storeId'] ?? '';
    if ($storeId === '') throw new ApiError('Toko/penerima wajib dipilih.');
    $date = $n['date'] ?: today();
    // kumpulkan item valid (produk terisi & qty > 0)
    $clean = [];
    foreach ($n['items'] ?? [] as $it) {
        $pid = $it['productId'] ?? '';
        $qty = intval($it['qty'] ?? 0);
        if ($pid === '' || $qty <= 0) continue;
        $kind = $it['kind'] ?? 'jual';
        if (!in_array($kind, ['jual','bonus','endorse','tester'], true)) $kind = 'jual';
        // bonus/endorse/tester = gratis → harga 0 (tak ditagih)
        $harga = ($kind === 'jual') ? intval($it['harga'] ?? 0) : 0;
        $clean[] = ['productId'=>$pid, 'qty'=>$qty, 'harga'=>$harga, 'hpp'=>intval($it['hpp'] ?? 0), 'kind'=>$kind];
    }
    if (!$clean) throw new ApiError('Nota harus punya minimal 1 item dengan qty > 0.');
    // validasi stok per produk (gabung qty item produk sama; item nota ini sendiri tak dihitung)
    // kecuali pemilik mengaktifkan "boleh jual walau stok habis" → lewati blokir (stok boleh minus)
    $oversell = !empty($pdo->query("SELECT oversell FROM profile WHERE id=1")->fetchColumn());
    $need = [];
    foreach ($clean as $it) $need[$it['productId']] = ($need[$it['productId']] ?? 0) + $it['qty'];
    foreach ($need as $pid => $q) {
        if ($oversell) continue;
        // produk tanpa lacak stok (made-to-order F&B / jasa) → tak dibatasi stok
        $x = $pdo->prepare("SELECT track_stock FROM products WHERE id=?");
        $x->execute([$pid]); $ts = $x->fetchColumn();
        if ($ts !== false && (int)$ts === 0) continue;
        $x = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM batch_outputs WHERE product_id=?");
        $x->execute([$pid]); $produced = intval($x->fetchColumn());
        $x = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM distributions WHERE product_id=? AND (nota_id<>? OR nota_id IS NULL)");
        $x->execute([$pid, $id]); $others = intval($x->fetchColumn());
        $x = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM stock_adjustments WHERE product_id=?");
        $x->execute([$pid]); $adj = intval($x->fetchColumn());
        $avail = $produced - $others + $adj;   // penyesuaian (rusak/hilang/koreksi) ikut hitung
        if ($q > $avail) {
            $nm = $pdo->prepare("SELECT name FROM products WHERE id=?"); $nm->execute([$pid]); $nm = $nm->fetchColumn() ?: $pid;
            throw new ApiError("Stok \"$nm\" tidak cukup. Tersedia $avail, diminta $q.");
        }
    }
    // catat kasir/pembuat + sesi kasir + metode bayar: pertahankan nilai asli saat edit, isi baru saat pertama
    $ex = $pdo->prepare("SELECT created_by, session_id, pay_method, service, tax, svc_rate, tax_rate, created FROM notas WHERE id=?"); $ex->execute([$id]); $ex = $ex->fetch();
    if ($ex === false) {   // nota baru
        $creator   = $_SESSION['email'] ?? '';
        $sessionId = safe_id($n['sessionId'] ?? '') ?: null;
        $payMethod = in_array(($n['payMethod'] ?? ''), ['Tunai','Transfer','QRIS'], true) ? $n['payMethod'] : '';
    } else {               // edit → pertahankan pembuat/sesi/metode asli
        $creator   = $ex['created_by'];
        $sessionId = $ex['session_id'];
        $payMethod = $ex['pay_method'];
    }
    // diskon (Rp), dibatasi 0..subtotal item jual
    $sub = 0; foreach ($clean as $it) $sub += $it['qty'] * $it['harga'];
    $discount = max(0, min(intval($n['discount'] ?? 0), $sub));
    $base = $sub - $discount;
    // service charge & pajak: dibekukan per nota. Saat EDIT → pertahankan nilai lama (jangan hitung ulang dg tarif terkini).
    // Nota kasir BARU (POS = punya sessionId) → hitung dari tarif Profil (owner).
    $service = 0; $tax = 0; $svcRate = 0.0; $taxRate = 0.0;
    if ($ex !== false) {                          // edit → beku (nilai & tarif dipertahankan)
        $service = intval($ex['service']); $tax = intval($ex['tax']);
        $svcRate = (float)$ex['svc_rate'];  $taxRate = (float)$ex['tax_rate'];
    } elseif ($sessionId !== null) {              // nota kasir baru
        $cfg = $pdo->query("SELECT svc_enabled,svc_rate,tax_enabled,tax_rate FROM profile WHERE id=1")->fetch() ?: [];
        if (!empty($cfg['svc_enabled'])) { $svcRate = (float)$cfg['svc_rate']; $service = (int) round($base * $svcRate / 100); }
        if (!empty($cfg['tax_enabled'])) { $taxRate = (float)$cfg['tax_rate']; $tax = (int) round(($base + $service) * $taxRate / 100); }  // pajak atas base+service
    }
    $pdo->prepare("REPLACE INTO notas (id,nota_no,ndate,store_id,created_by,session_id,pay_method,discount,service,tax,svc_rate,tax_rate) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$id, $n['notaNo'] ?? '', $date, $storeId, $creator, $sessionId, $payMethod, $discount, $service, $tax, $svcRate, $taxRate]);
    // waktu dibuat: nota baru → NOW(); edit → pertahankan nilai lama (REPLACE mengosongkan kolom yg tak diisi)
    if ($ex === false) $pdo->prepare("UPDATE notas SET created=NOW() WHERE id=?")->execute([$id]);
    elseif (!empty($ex['created'])) $pdo->prepare("UPDATE notas SET created=? WHERE id=?")->execute([$ex['created'], $id]);
    $pdo->prepare("DELETE FROM distributions WHERE nota_id=?")->execute([$id]);
    $ins = $pdo->prepare("INSERT INTO distributions (id,nota_id,ddate,store_id,product_id,qty,harga,hpp,kind) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($clean as $it)
        $ins->execute([gid('d'), $id, $date, $storeId, $it['productId'], $it['qty'], $it['harga'], $it['hpp'], $it['kind']]);
    return ['id'=>$id, 'sub'=>$sub, 'total'=>$base + $service + $tax, 'service'=>$service, 'tax'=>$tax, 'sessionId'=>$sessionId, 'payMethod'=>$payMethod];
}

// Total penjualan sebuah sesi kasir, dipecah tunai vs non-tunai (dari nilai item nota)
function session_totals($pdo, $sid) {
    $rows = $pdo->prepare("SELECT n.pay_method AS pm, n.discount AS disc, n.service AS svc, n.tax AS tax, COALESCE(SUM(d.qty*d.harga),0) AS tot
        FROM notas n LEFT JOIN distributions d ON d.nota_id=n.id
        WHERE n.session_id=? GROUP BY n.id, n.pay_method, n.discount, n.service, n.tax");
    $rows->execute([$sid]);
    $cash = 0; $noncash = 0; $count = 0;
    foreach ($rows as $r) {
        $sub = intval($r['tot']);
        $t = $sub - min(max(0, intval($r['disc'])), $sub) + intval($r['svc']) + intval($r['tax']);   // total = base + service + pajak (mirror notaTotal)
        $count++;
        if (($r['pm'] ?? '') === 'Tunai') $cash += $t; else $noncash += $t;
    }
    return ['cash' => $cash, 'noncash' => $noncash, 'count' => $count];
}

function bootstrap($pdo) {
    $products = $pdo->query("SELECT id,name,cat,gram,harga,hpp,photo,track_stock AS trackStock FROM products ORDER BY name")->fetchAll();
    foreach ($products as &$p) { $p['gram']=intval($p['gram']); $p['harga']=intval($p['harga']); $p['hpp']=intval($p['hpp']); $p['trackStock']=intval($p['trackStock']); } unset($p);
    // penyesuaian stok produk (rusak/hilang/koreksi/opname) — utk stok akurat & riwayat
    $stockAdj = [];
    foreach ($pdo->query("SELECT id,product_id AS productId,adate AS date,qty,reason,note FROM stock_adjustments ORDER BY adate DESC,id DESC") as $r) { $r['qty']=intval($r['qty']); $stockAdj[] = $r; }

    $stores = $pdo->query("SELECT id,name,contact,address FROM stores ORDER BY name")->fetchAll();

    // materials + history
    $materials = $pdo->query("SELECT id,name,unit,price,min_stock FROM materials ORDER BY name")->fetchAll();
    $hist = [];
    foreach ($pdo->query("SELECT id,material_id,pdate AS date,price,source,ref FROM material_prices ORDER BY pdate,id") as $h) {
        $h['price']=intval($h['price']);
        $hist[$h['material_id']][] = $h;
    }
    // stok bahan OPSIONAL: total beli, total pakai (dari batch), + riwayat pembelian
    $bought = []; foreach ($pdo->query("SELECT material_id, COALESCE(SUM(qty),0) AS q FROM material_purchases GROUP BY material_id") as $r) $bought[$r['material_id']] = (float)$r['q'];
    $used = []; foreach ($pdo->query("SELECT material_id, COALESCE(SUM(qty),0) AS q FROM batch_materials WHERE material_id IS NOT NULL GROUP BY material_id") as $r) $used[$r['material_id']] = (float)$r['q'];
    $purch = []; foreach ($pdo->query("SELECT id,material_id,pdate AS date,qty,price,note FROM material_purchases ORDER BY pdate DESC,id DESC") as $r) { $r['qty']=(float)$r['qty']; $r['price']=intval($r['price']); $purch[$r['material_id']][] = $r; }
    foreach ($materials as &$m) {
        $m['price']=intval($m['price']); $m['history']=$hist[$m['id']] ?? [];
        $m['minStock']=(float)$m['min_stock']; unset($m['min_stock']);
        $m['bought']=$bought[$m['id']] ?? 0; $m['used']=$used[$m['id']] ?? 0;
        $m['purchases']=$purch[$m['id']] ?? [];
    } unset($m);

    // batches nested
    $batches = $pdo->query("SELECT id,bdate AS date,note FROM batches ORDER BY bdate DESC,id DESC")->fetchAll();
    $bm=[]; foreach ($pdo->query("SELECT batch_id,material_id AS materialId,name,qty,unit,price,target_product AS targetProduct FROM batch_materials") as $r){$r['qty']=floatval($r['qty']);$r['price']=intval($r['price']);$bm[$r['batch_id']][]=$r;}
    $bo=[]; foreach ($pdo->query("SELECT batch_id,name,amount FROM batch_ops") as $r){$r['amount']=intval($r['amount']);$bo[$r['batch_id']][]=$r;}
    $bp=[]; foreach ($pdo->query("SELECT batch_id,product_id AS productId,qty FROM batch_outputs") as $r){$r['qty']=intval($r['qty']);$bp[$r['batch_id']][]=$r;}
    foreach ($batches as &$b) { $b['materials']=$bm[$b['id']]??[]; $b['ops']=$bo[$b['id']]??[]; $b['outputs']=$bp[$b['id']]??[]; } unset($b);

    // nota (faktur) + item + pembayaran, semua bersarang
    $items = [];
    foreach ($pdo->query("SELECT id,nota_id AS notaId,product_id AS productId,qty,harga,hpp,kind FROM distributions") as $r) {
        $r['qty']=intval($r['qty']); $r['harga']=intval($r['harga']); $r['hpp']=intval($r['hpp']);
        $items[$r['notaId']][] = $r;
    }
    $pay = [];
    foreach ($pdo->query("SELECT id,nota_id AS notaId,pdate AS date,amount,note,created AS createdAt FROM payments ORDER BY pdate,id") as $r) {
        $r['amount']=intval($r['amount']); $pay[$r['notaId']][] = $r;
    }
    $notas = $pdo->query("SELECT id,nota_no AS notaNo,ndate AS date,store_id AS storeId,created_by AS createdBy,created AS createdAt,session_id AS sessionId,pay_method AS payMethod,discount,service,tax,svc_rate AS svcRate,tax_rate AS taxRate FROM notas ORDER BY ndate DESC,id DESC")->fetchAll();
    foreach ($notas as &$n) { $n['discount']=intval($n['discount']); $n['items']=$items[$n['id']]??[]; $n['payments']=$pay[$n['id']]??[]; } unset($n);

    // Kas keluar (termasuk prive pemilik) hanya untuk pemilik / staf ber-akses Keuangan
    $seeKeu = (($_SESSION['role'] ?? 'owner') === 'owner') || in_array('keuangan', array_filter(explode(',', $_SESSION['perms'] ?? '')), true);
    $cashOut = [];
    if ($seeKeu) foreach ($pdo->query("SELECT id,cdate AS date,category,amount,note FROM cash_out ORDER BY cdate DESC,id DESC") as $r) { $r['amount']=intval($r['amount']); $cashOut[] = $r; }

    $profile = $pdo->query("SELECT address,phone,whatsapp,instagram,facebook,tiktok,logo,qris,footer,svc_enabled,svc_rate,tax_enabled,tax_rate,oversell,biz_type FROM profile WHERE id=1")->fetch() ?: [];

    // sesi kasir yang sedang terbuka (kalau ada) + riwayat sesi tertutup
    $register = $pdo->query("SELECT id,opened_by AS openedBy,opened_at AS openedAt,opening_float AS openingFloat FROM register_sessions WHERE status='open' LIMIT 1")->fetch() ?: null;
    if ($register) $register['openingFloat'] = intval($register['openingFloat']);
    $registerLog = [];
    foreach ($pdo->query("SELECT id,opened_by AS openedBy,opened_at AS openedAt,closed_by AS closedBy,closed_at AS closedAt,opening_float AS openingFloat,cash_sales AS cashSales,noncash_sales AS noncashSales,txn_count AS txnCount,expected_cash AS expected,closing_cash AS closing FROM register_sessions WHERE status='closed' ORDER BY closed_at DESC LIMIT 60") as $r) {
        foreach (['openingFloat','cashSales','noncashSales','txnCount','expected','closing'] as $k) $r[$k] = intval($r[$k]);
        $r['diff'] = $r['closing'] - $r['expected'];
        $registerLog[] = $r;
    }

    $biz = current_business();
    return compact('products','stores','materials','batches','notas','cashOut','profile','register','registerLog','stockAdj')
        + ['biz' => ['name'=>$biz['name']??'', 'alias'=>$biz['alias']??'', 'user'=>$_SESSION['user_name']??($biz['user_name']??'')],
           'me' => me_payload()];
}

function import_all($pdo, $data) {
    // tolak backup dengan id tak sah (cegah XSS lewat id yang di-render ke handler onclick)
    foreach (['products','stores','materials','batches','notas','cashOut'] as $grp) {
        foreach ($data[$grp] ?? [] as $row) {
            if (isset($row['id']) && $row['id'] !== '' && safe_id($row['id']) === '') {
                http_response_code(400); throw new Exception('Backup tidak valid: ada id yang tidak sah.');
            }
            foreach ($row['items'] ?? [] as $it)
                if (isset($it['id']) && $it['id'] !== '' && safe_id($it['id']) === '')
                    { http_response_code(400); throw new Exception('Backup tidak valid: ada id item yang tidak sah.'); }
        }
    }
    $pdo->beginTransaction();
    foreach (TABLES as $t)
        $pdo->exec("DELETE FROM $t");
    $sp = $pdo->prepare("INSERT INTO products (id,name,cat,gram,harga,hpp) VALUES (?,?,?,?,?,?)");
    foreach ($data['products'] ?? [] as $p)
        $sp->execute([$p['id'],$p['name'],$p['cat']??'Umum',intval($p['gram'])?:1,intval($p['harga']),intval($p['hpp'])]);
    $ss = $pdo->prepare("INSERT INTO stores (id,name,contact,address) VALUES (?,?,?,?)");
    foreach ($data['stores'] ?? [] as $s)
        $ss->execute([$s['id'],$s['name'],$s['contact']??'',$s['address']??'']);
    $sm = $pdo->prepare("INSERT INTO materials (id,name,unit,price) VALUES (?,?,?,?)");
    $smh = $pdo->prepare("INSERT INTO material_prices (material_id,pdate,price,source,ref) VALUES (?,?,?,?,?)");
    foreach ($data['materials'] ?? [] as $m) {
        $sm->execute([$m['id'],$m['name'],$m['unit']??'kg',intval($m['price'])]);
        foreach ($m['history'] ?? [] as $h)
            $smh->execute([$m['id'],$h['date'],intval($h['price']),$h['source']??'manual',($h['ref']??'')?:null]);
    }
    $sb = $pdo->prepare("INSERT INTO batches (id,bdate,note) VALUES (?,?,?)");
    foreach ($data['batches'] ?? [] as $b) {
        $sb->execute([$b['id'],$b['date'],$b['note']??'']);
        insert_batch_children($pdo, $b['id'], $b);
    }
    $sn = $pdo->prepare("INSERT INTO notas (id,nota_no,ndate,store_id) VALUES (?,?,?,?)");
    $sd = $pdo->prepare("INSERT INTO distributions (id,nota_id,ddate,store_id,product_id,qty,harga,hpp,kind) VALUES (?,?,?,?,?,?,?,?,?)");
    $spay = $pdo->prepare("INSERT INTO payments (nota_id,pdate,amount,note) VALUES (?,?,?,?)");
    foreach ($data['notas'] ?? [] as $n) {
        $sn->execute([$n['id'],$n['notaNo']??'',$n['date'],$n['storeId']]);
        foreach ($n['items']??[] as $it)
            $sd->execute([$it['id']??gid('d'),$n['id'],$n['date'],$n['storeId'],$it['productId'],intval($it['qty']),intval($it['harga']),intval($it['hpp']),$it['kind']??'jual']);
        foreach ($n['payments']??[] as $p) $spay->execute([$n['id'],$p['date'],intval($p['amount']),$p['note']??'']);
    }
    $sco = $pdo->prepare("INSERT INTO cash_out (id,cdate,category,amount,note) VALUES (?,?,?,?,?)");
    foreach ($data['cashOut'] ?? [] as $c)
        $sco->execute([$c['id']??gid('k'),$c['date'],$c['category']??'lain',intval($c['amount']),$c['note']??'']);
    if (!empty($data['profile']) && is_array($data['profile'])) {
        $pf = $data['profile'];
        $logo = (string)($pf['logo'] ?? '');
        if ($logo !== '' && !is_img_datauri($logo)) $logo = '';
        // REPLACE = DELETE+INSERT → sertakan SEMUA kolom, jika tidak qris/footer/svc/tax ikut terhapus saat import
        $qris = trim(preg_replace('/[\r\n\t]+/', '', (string)($pf['qris'] ?? '')));
        if ($qris !== '' && !preg_match('/^[0-9A-Za-z.\- ]{20,600}$/', $qris)) $qris = '';
        $footer = mb_substr(preg_replace('/[\r\n\t]+/', ' ', (string)($pf['footer'] ?? '')), 0, 255);
        $clamp = function ($v) { return max(0, min(100, round((float)$v, 2))); };
        $bt = in_array(($pf['biz_type'] ?? ''), ['produksi','fnb'], true) ? $pf['biz_type'] : 'produksi';
        $pdo->prepare("REPLACE INTO profile (id,address,phone,whatsapp,instagram,facebook,tiktok,logo,qris,footer,svc_enabled,svc_rate,tax_enabled,tax_rate,oversell,biz_type) VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$pf['address']??'',$pf['phone']??'',$pf['whatsapp']??'',$pf['instagram']??'',$pf['facebook']??'',$pf['tiktok']??'',$logo,
                $qris,$footer,!empty($pf['svc_enabled'])?1:0,$clamp($pf['svc_rate']??0),!empty($pf['tax_enabled'])?1:0,$clamp($pf['tax_rate']??0),!empty($pf['oversell'])?1:0,$bt]);
    }
    $pdo->commit();
}
