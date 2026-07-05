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
if (in_array($action, ['authStatus','authLogin','authRegister','authLogout','authResetRequest','authResetConfirm','authChangePassword','usersList','userSave','userDelete'], true)) {
    try { handle_auth($action, $in); }
    catch (Exception $e) { error_log('auth: '.$e->getMessage()); http_response_code(500); echo json_encode(['error' => 'Terjadi kesalahan pada server.']); }
    exit;
}

$pdo = db();   // butuh login (kalau belum → 401 needLogin)

// ===== "Ingat saya": token persisten (selector:validator) — konstanta didefinisikan di atas file =====
function is_https() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
function set_remember_cookie($value, $expires) {
    setcookie(REMEMBER_COOKIE, $value, ['expires'=>$expires, 'path'=>'/', 'httponly'=>true, 'samesite'=>'Lax', 'secure'=>is_https()]);
}
function issue_remember_token($m, $alias, $email) {
    $selector = bin2hex(random_bytes(12));    // 24 char, publik (dipakai lookup)
    $validator = bin2hex(random_bytes(32));   // 64 char, rahasia (cookie only; DB simpan hash)
    $exp = time() + REMEMBER_DAYS * 86400;
    $m->prepare("INSERT INTO remember_tokens (alias,email,selector,validator_hash,expires) VALUES (?,?,?,?,?)")
      ->execute([$alias, $email, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $exp)]);
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
    return ['name'=>$b['name'], 'alias'=>$t['alias'], 'user'=>$u['name'], 'email'=>$t['email']] + me_payload();
}
// payload user aktif (role + daftar menu yg boleh diakses)
function me_payload() {
    $perms = trim($_SESSION['perms'] ?? '');
    return ['role' => $_SESSION['role'] ?? 'owner',
            'perms' => $perms === '' ? [] : array_values(array_filter(explode(',', $perms))),
            'email' => $_SESSION['email'] ?? ''];
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
        echo json_encode(['loggedIn'=>true, 'name'=>$b['name'], 'alias'=>$b['alias'], 'user'=>($_SESSION['user_name'] ?? '')] + me_payload());
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
        echo json_encode(['ok'=>true, 'name'=>$b['name'], 'alias'=>$alias, 'user'=>$u['name'], 'email'=>$email] + me_payload()); return;
    }
    if ($action === 'authRegister') {
        $name = trim($in['name'] ?? ''); $alias = strtolower(trim($in['code'] ?? $in['alias'] ?? ''));
        $user = trim($in['user'] ?? ''); $email = strtolower(trim($in['email'] ?? '')); $pass = (string)($in['password'] ?? '');
        if ($name === '' || $user === '') { http_response_code(400); echo json_encode(['error' => 'Nama usaha & nama user wajib diisi.']); return; }
        if (!valid_alias($alias)) { http_response_code(400); echo json_encode(['error' => 'Kode usaha harus 2–24 huruf kecil/angka tanpa spasi.']); return; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['error' => 'Email tidak valid.']); return; }
        if (strlen($pass) < 6) { http_response_code(400); echo json_encode(['error' => 'Password minimal 6 karakter.']); return; }
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
        // akun dibuat sebagai PENDING — tidak auto-login; tunggu aktivasi admin (setelah bayar)
        echo json_encode(['ok'=>true, 'pending'=>true, 'name'=>$name, 'alias'=>$alias]); return;
    }
    // ---- ganti password (sedang login) ----
    if ($action === 'authChangePassword') {
        $b = current_business();
        if (!$b) { http_response_code(401); echo json_encode(['error' => 'Belum login.']); return; }
        $old = (string)($in['oldPassword'] ?? ''); $new = (string)($in['newPassword'] ?? '');
        if (strlen($new) < 6) { http_response_code(400); echo json_encode(['error' => 'Password baru minimal 6 karakter.']); return; }
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
            $m->prepare("INSERT INTO password_resets (alias,email,selector,token_hash,expires) VALUES (?,?,?,?,?)")
              ->execute([$alias, $email, $selector, hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
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
        if (strlen($new) < 6) { http_response_code(400); echo json_encode(['error' => 'Password baru minimal 6 karakter.']); return; }
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
                if ($pass !== '' && strlen($pass) < 6) { http_response_code(400); echo json_encode(['error' => 'Password minimal 6 karakter.']); return; }
                if ($pass !== '') $m->prepare("UPDATE users SET name=?, perms=?, pass_hash=? WHERE id=?")->execute([$name, $perms, password_hash($pass, PASSWORD_DEFAULT), $ex['id']]);
                else $m->prepare("UPDATE users SET name=?, perms=? WHERE id=?")->execute([$name, $perms, $ex['id']]);
                echo json_encode(['ok' => true]); return;
            }
            if (strlen($pass) < 6) { http_response_code(400); echo json_encode(['error' => 'Password staf minimal 6 karakter.']); return; }
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
}

// ---- kirim email reset password (pakai mail() bawaan; di shared hosting umumnya jalan) ----
function app_base_url() {
    if (defined('APP_URL') && APP_URL) return rtrim(APP_URL, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function send_reset_email($to, $name, $alias, $tokenStr) {
    $link = app_base_url() . '/?reset=' . $tokenStr;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $from = defined('RESET_FROM') && RESET_FROM ? RESET_FROM : ('noreply@' . preg_replace('/^www\./', '', $host));
    $subject = 'Reset Password Racikin';
    $body = "Halo" . ($name ? ' ' . $name : '') . ",\n\n"
          . "Ada permintaan reset password untuk usaha \"$alias\" di Racikin.\n"
          . "Klik link ini untuk membuat password baru (berlaku 1 jam):\n\n$link\n\n"
          . "Kalau kamu tidak meminta ini, abaikan saja email ini.\n\n— Racikin";
    $headers = "From: Racikin <$from>\r\nReply-To: $from\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    @mail($to, $subject, $body, $headers);
}

// Daftar tabel dipakai bersama oleh reset & importAll (urutan: anak dulu, induk belakangan)
const TABLES = ['payments','distributions','notas','register_sessions','cash_out','batch_materials','batch_ops','batch_outputs','batches','material_prices','materials','products','profile'];
const BATCH_CHILDREN = ['batch_materials','batch_ops','batch_outputs'];

function gid($p) { return $p . bin2hex(random_bytes(5)); }
function today() { return date('Y-m-d'); }
// id dari klien harus alfanumerik saja (cegah XSS lewat interpolasi id ke handler onclick)
function safe_id($id) { $id = (string)$id; return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) ? $id : ''; }

// ---- Otorisasi per-aksi: pemilik (owner) lolos semua; staf hanya aksi yang menunya diizinkan ----
if (($_SESSION['role'] ?? 'owner') !== 'owner') {
    $OWNER_ONLY = ['reset', 'importAll', 'saveProfile'];   // hapus/timpa data & identitas usaha (incl. QRIS) = khusus pemilik
    $NEED = [
        'saveBatch'=>['produksi'], 'deleteBatch'=>['produksi'],
        'saveNota'=>['pos','distribusi'], 'deleteNota'=>['pos','distribusi'],
        'openRegister'=>['pos'], 'closeRegister'=>['pos'],
        'addPayment'=>['pos','pembayaran'], 'deletePayment'=>['pembayaran'],
        'saveCashOut'=>['keuangan'], 'deleteCashOut'=>['keuangan'],
        'saveProduct'=>['produk'], 'deleteProduct'=>['produk'],
        'saveStore'=>['pos','toko'], 'deleteStore'=>['toko'],
        'saveMaterial'=>['bahan'], 'deleteMaterial'=>['bahan'], 'deletePricePoint'=>['bahan'], 'resyncPrices'=>['bahan'],
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
            $n = $in['nota'];
            $id = safe_id($n['id'] ?? '') ?: gid('n');
            $storeId = $n['storeId'] ?? '';
            if ($storeId === '') { http_response_code(400); echo json_encode(['error' => 'Toko/penerima wajib dipilih.']); break; }
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
            if (!$clean) { http_response_code(400); echo json_encode(['error' => 'Nota harus punya minimal 1 item dengan qty > 0.']); break; }
            // validasi stok per produk (gabung qty item produk sama; item nota ini sendiri tak dihitung)
            $need = [];
            foreach ($clean as $it) $need[$it['productId']] = ($need[$it['productId']] ?? 0) + $it['qty'];
            foreach ($need as $pid => $q) {
                $x = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM batch_outputs WHERE product_id=?");
                $x->execute([$pid]); $produced = intval($x->fetchColumn());
                $x = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM distributions WHERE product_id=? AND (nota_id<>? OR nota_id IS NULL)");
                $x->execute([$pid, $id]); $others = intval($x->fetchColumn());
                $avail = $produced - $others;
                if ($q > $avail) {
                    $nm = $pdo->prepare("SELECT name FROM products WHERE id=?"); $nm->execute([$pid]); $nm = $nm->fetchColumn() ?: $pid;
                    http_response_code(400); echo json_encode(['error' => "Stok \"$nm\" tidak cukup. Tersedia $avail, diminta $q."]); break 2;
                }
            }
            $pdo->beginTransaction();
            // catat kasir/pembuat + sesi kasir + metode bayar: pertahankan nilai asli saat edit, isi baru saat pertama
            $ex = $pdo->prepare("SELECT created_by, session_id, pay_method FROM notas WHERE id=?"); $ex->execute([$id]); $ex = $ex->fetch();
            if ($ex === false) {   // nota baru
                $creator   = $_SESSION['email'] ?? '';
                $sessionId = safe_id($n['sessionId'] ?? '') ?: null;
                $payMethod = in_array(($n['payMethod'] ?? ''), ['Tunai','Transfer','QRIS'], true) ? $n['payMethod'] : '';
            } else {               // edit → pertahankan pembuat/sesi/metode asli
                $creator   = $ex['created_by'];
                $sessionId = $ex['session_id'];
                $payMethod = $ex['pay_method'];
            }
            $pdo->prepare("REPLACE INTO notas (id,nota_no,ndate,store_id,created_by,session_id,pay_method) VALUES (?,?,?,?,?,?,?)")
                ->execute([$id, $n['notaNo'] ?? '', $date, $storeId, $creator, $sessionId, $payMethod]);
            $pdo->prepare("DELETE FROM distributions WHERE nota_id=?")->execute([$id]);
            $ins = $pdo->prepare("INSERT INTO distributions (id,nota_id,ddate,store_id,product_id,qty,harga,hpp,kind) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($clean as $it)
                $ins->execute([gid('d'), $id, $date, $storeId, $it['productId'], $it['qty'], $it['harga'], $it['hpp'], $it['kind']]);
            $pdo->commit();
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteNota': {
            $id = $in['id'];
            $pdo->beginTransaction();
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

        case 'addPayment': {
            $notaId = $in['notaId'];
            $amount = intval($in['amount']);
            // batasi ke sisa tagihan nota supaya piutang tak bisa jadi negatif
            $q = $pdo->prepare("SELECT COALESCE(SUM(qty*harga),0) FROM distributions WHERE nota_id=?");
            $q->execute([$notaId]); $total = intval($q->fetchColumn());
            $q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE nota_id=?");
            $q->execute([$notaId]); $paid = intval($q->fetchColumn());
            $remaining = max(0, $total - $paid);
            $amount = min($amount, $remaining);
            if ($amount <= 0) { echo json_encode(['ok' => true, 'skipped' => true]); break; }
            $pdo->prepare("INSERT INTO payments (nota_id,pdate,amount,note) VALUES (?,?,?,?)")
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
            $pdo->prepare("REPLACE INTO products (id,name,cat,gram,harga,hpp) VALUES (?,?,?,?,?,?)")
                ->execute([$id, $p['name'], $p['cat'] ?: 'Umum', intval($p['gram']) ?: 1,
                    intval($p['harga']), intval($p['hpp'])]);
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'deleteProduct':
            $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$in['id']]);
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
            $pdo->prepare("REPLACE INTO materials (id,name,unit,price) VALUES (?,?,?,?)")
                ->execute([$id, $m['name'], $m['unit'] ?: 'kg', $newPrice]);
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

        case 'saveProfile': {
            $p = $in['profile'] ?? [];
            $logo = (string)($p['logo'] ?? '');
            if ($logo !== '' && !preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#', $logo)) {
                http_response_code(400); echo json_encode(['error' => 'Logo harus berupa gambar.']); break;
            }
            if (strlen($logo) > 3000000) { http_response_code(400); echo json_encode(['error' => 'Logo terlalu besar (maks ~2MB).']); break; }
            $g = function ($k, $max) use ($p) { return mb_substr(trim((string)($p[$k] ?? '')), 0, $max); };
            $qris = trim(preg_replace('/[\r\n\t]+/', '', (string)($p['qris'] ?? '')));   // buang enter/tab saja; spasi internal (nama merchant) dipertahankan
            if ($qris !== '' && !preg_match('/^[0-9A-Za-z.\- ]{20,600}$/', $qris)) { http_response_code(400); echo json_encode(['error' => 'Kode QRIS tidak valid.']); break; }
            $pdo->prepare("REPLACE INTO profile (id,address,phone,whatsapp,instagram,facebook,tiktok,logo,qris) VALUES (1,?,?,?,?,?,?,?,?)")
                ->execute([$g('address',255),$g('phone',60),$g('whatsapp',60),$g('instagram',120),$g('facebook',120),$g('tiktok',120),$logo,$qris]);
            echo json_encode(['ok' => true]);
            break;
        }

        case 'importAll':
            import_all($pdo, $in['data']);
            echo json_encode(['ok' => true]);
            break;

        case 'reset':
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

// Total penjualan sebuah sesi kasir, dipecah tunai vs non-tunai (dari nilai item nota)
function session_totals($pdo, $sid) {
    $rows = $pdo->prepare("SELECT n.pay_method AS pm, COALESCE(SUM(d.qty*d.harga),0) AS tot
        FROM notas n LEFT JOIN distributions d ON d.nota_id=n.id
        WHERE n.session_id=? GROUP BY n.id, n.pay_method");
    $rows->execute([$sid]);
    $cash = 0; $noncash = 0; $count = 0;
    foreach ($rows as $r) {
        $t = intval($r['tot']); $count++;
        if (($r['pm'] ?? '') === 'Tunai') $cash += $t; else $noncash += $t;
    }
    return ['cash' => $cash, 'noncash' => $noncash, 'count' => $count];
}

function bootstrap($pdo) {
    $products = $pdo->query("SELECT id,name,cat,gram,harga,hpp FROM products ORDER BY name")->fetchAll();
    foreach ($products as &$p) { $p['gram']=intval($p['gram']); $p['harga']=intval($p['harga']); $p['hpp']=intval($p['hpp']); } unset($p);

    $stores = $pdo->query("SELECT id,name,contact,address FROM stores ORDER BY name")->fetchAll();

    // materials + history
    $materials = $pdo->query("SELECT id,name,unit,price FROM materials ORDER BY name")->fetchAll();
    $hist = [];
    foreach ($pdo->query("SELECT id,material_id,pdate AS date,price,source,ref FROM material_prices ORDER BY pdate,id") as $h) {
        $h['price']=intval($h['price']);
        $hist[$h['material_id']][] = $h;
    }
    foreach ($materials as &$m) { $m['price']=intval($m['price']); $m['history']=$hist[$m['id']] ?? []; } unset($m);

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
    foreach ($pdo->query("SELECT id,nota_id AS notaId,pdate AS date,amount,note FROM payments ORDER BY pdate,id") as $r) {
        $r['amount']=intval($r['amount']); $pay[$r['notaId']][] = $r;
    }
    $notas = $pdo->query("SELECT id,nota_no AS notaNo,ndate AS date,store_id AS storeId,created_by AS createdBy,session_id AS sessionId,pay_method AS payMethod FROM notas ORDER BY ndate DESC,id DESC")->fetchAll();
    foreach ($notas as &$n) { $n['items']=$items[$n['id']]??[]; $n['payments']=$pay[$n['id']]??[]; } unset($n);

    // Kas keluar (termasuk prive pemilik) hanya untuk pemilik / staf ber-akses Keuangan
    $seeKeu = (($_SESSION['role'] ?? 'owner') === 'owner') || in_array('keuangan', array_filter(explode(',', $_SESSION['perms'] ?? '')), true);
    $cashOut = [];
    if ($seeKeu) foreach ($pdo->query("SELECT id,cdate AS date,category,amount,note FROM cash_out ORDER BY cdate DESC,id DESC") as $r) { $r['amount']=intval($r['amount']); $cashOut[] = $r; }

    $profile = $pdo->query("SELECT address,phone,whatsapp,instagram,facebook,tiktok,logo,qris FROM profile WHERE id=1")->fetch() ?: [];

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
    return compact('products','stores','materials','batches','notas','cashOut','profile','register','registerLog')
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
        if ($logo !== '' && !preg_match('#^data:image/#', $logo)) $logo = '';
        $pdo->prepare("REPLACE INTO profile (id,address,phone,whatsapp,instagram,facebook,tiktok,logo) VALUES (1,?,?,?,?,?,?,?)")
            ->execute([$pf['address']??'',$pf['phone']??'',$pf['whatsapp']??'',$pf['instagram']??'',$pf['facebook']??'',$pf['tiktok']??'',$logo]);
    }
    $pdo->commit();
}
