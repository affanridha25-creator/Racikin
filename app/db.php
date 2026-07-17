<?php
// ================== KONFIGURASI DATABASE ==================
// Produksi (hosting): salin config.example.php -> config.php, lalu isi kredensial.
// Tanpa config.php, pakai default XAMPP (host 127.0.0.1, user root, password kosong).
@include __DIR__ . '/config.php';
$DB_HOST = $DB_HOST ?? '127.0.0.1';
$DB_USER = $DB_USER ?? 'root';
$DB_PASS = $DB_PASS ?? '';
// Bentuk kanonik: config.php mendefinisikan konstanta DB_MASTER / DB_TENANT_PREFIX / DB_ALLOW_CREATE.
if (!defined('MASTER_DB'))       define('MASTER_DB', defined('DB_MASTER') ? DB_MASTER : 'racikin_master');
if (!defined('DB_PREFIX'))       define('DB_PREFIX', defined('DB_TENANT_PREFIX') ? DB_TENANT_PREFIX : 'racikin_');
if (!defined('ALLOW_DB_CREATE')) define('ALLOW_DB_CREATE', defined('DB_ALLOW_CREATE') ? (bool)DB_ALLOW_CREATE : true);
// =========================================================

// Cookie sesi lebih aman: HttpOnly (tak bisa dibaca JS) + SameSite=Lax (bantu cegah CSRF)
// + Secure saat HTTPS (cookie tak ikut terkirim lewat http → cegah pencurian sesi saat MITM).
if (session_status() !== PHP_SESSION_ACTIVE) {
    $_sec = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $_sec]);
}

function _pdo_opt() { return [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]; }

// koneksi ke server MySQL (tanpa database) — untuk bikin DB baru
function server_pdo() {
    global $DB_HOST, $DB_USER, $DB_PASS; static $p = null; if ($p) return $p;
    try { $p = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, _pdo_opt()); }
    catch (PDOException $e) { error_log('db: '.$e->getMessage()); http_response_code(500); header('Content-Type: application/json'); echo json_encode(['error' => 'Koneksi database gagal. Cek konfigurasi/kredensial MySQL.']); exit; }
    return $p;
}

// registry pusat (daftar usaha)
function master_pdo() {
    global $DB_HOST, $DB_USER, $DB_PASS; static $p = null; if ($p) return $p;
    if (ALLOW_DB_CREATE) { try { server_pdo()->exec("CREATE DATABASE IF NOT EXISTS `" . MASTER_DB . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Exception $e) { error_log('db create master: '.$e->getMessage()); } }
    $p = new PDO("mysql:host=$DB_HOST;dbname=" . MASTER_DB . ";charset=utf8mb4", $DB_USER, $DB_PASS, _pdo_opt());
    $p->exec("CREATE TABLE IF NOT EXISTS businesses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alias VARCHAR(32) UNIQUE,
        name VARCHAR(255),
        db_name VARCHAR(64),
        user_name VARCHAR(120),
        pin_hash VARCHAR(255),
        active TINYINT(1) DEFAULT 1,
        created DATETIME
    ) ENGINE=InnoDB");
    // active: 0 = pending (menunggu aktivasi admin setelah bayar), 1 = aktif.
    // DEFAULT 1 supaya usaha yang sudah ada tetap aktif; registrasi baru di-set 0.
    ensure_column($p, 'businesses', 'active', "active TINYINT(1) DEFAULT 1");
    // paid_until: tanggal langganan berakhir. NULL = tanpa batas (usaha lama/legacy tak terkunci).
    ensure_column($p, 'businesses', 'paid_until', "paid_until DATE DEFAULT NULL");
    // user per usaha: login = kode usaha (alias) + email + password
    // role: owner (pemilik, akses penuh) / staff (akses sesuai perms)
    // perms: daftar menu yang boleh diakses staff (CSV), mis. "pos,distribusi,pembayaran"
    $p->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alias VARCHAR(32),
        email VARCHAR(160),
        name VARCHAR(120),
        pass_hash VARCHAR(255),
        role VARCHAR(16) DEFAULT 'owner',
        perms TEXT,
        created DATETIME,
        UNIQUE KEY uq_alias_email (alias, email)
    ) ENGINE=InnoDB");
    ensure_column($p, 'users', 'role', "role VARCHAR(16) DEFAULT 'owner'");
    ensure_column($p, 'users', 'perms', "perms TEXT");
    // rate-limit login (anti brute-force) — 1 baris per percobaan gagal
    $p->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45),
        ts DATETIME,
        INDEX(ip, ts)
    ) ENGINE=InnoDB");
    // token "Ingat saya" (persisten): selector publik utk lookup, validator disimpan sbg hash (rahasia)
    $p->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alias VARCHAR(32),
        email VARCHAR(160),
        selector CHAR(24),
        validator_hash CHAR(64),
        expires DATETIME,
        UNIQUE KEY uq_selector (selector),
        INDEX(alias, email)
    ) ENGINE=InnoDB");
    // token reset password (dikirim via email; token disimpan sbg hash)
    $p->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alias VARCHAR(32),
        email VARCHAR(160),
        selector CHAR(20),
        token_hash CHAR(64),
        expires DATETIME,
        UNIQUE KEY uq_pr_selector (selector),
        INDEX(alias, email)
    ) ENGINE=InnoDB");
    // pengaturan aplikasi (harga paket, info rekening) — editable dari panel admin
    $p->exec("CREATE TABLE IF NOT EXISTS app_settings (
        k VARCHAR(50) PRIMARY KEY,
        v TEXT
    ) ENGINE=InnoDB");
    // pengajuan perpanjangan langganan (bukti transfer) — pelanggan submit, admin verifikasi
    $p->exec("CREATE TABLE IF NOT EXISTS renewal_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alias VARCHAR(32),
        email VARCHAR(160),
        plan VARCHAR(8),
        base_amount INT DEFAULT 0,
        uniq INT DEFAULT 0,
        amount INT DEFAULT 0,
        proof MEDIUMTEXT,
        note VARCHAR(255) DEFAULT '',
        status VARCHAR(10) DEFAULT 'awaiting',
        admin_note VARCHAR(255) DEFAULT '',
        created DATETIME,
        reviewed_at DATETIME DEFAULT NULL,
        INDEX(alias), INDEX(status)
    ) ENGINE=InnoDB");
    return $p;
}

// ---- Pengaturan aplikasi (key-value) ----
function settings_all() {
    $out = [];
    foreach (master_pdo()->query("SELECT k,v FROM app_settings") as $r) $out[$r['k']] = $r['v'];
    return $out + ['price_1bln'=>'0','price_3bln'=>'0','price_1thn'=>'0','bank_info'=>'','uniq_on'=>'1','uniq_max'=>'50'];
}
function setting_set($k, $v) {
    master_pdo()->prepare("INSERT INTO app_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([$k, (string)$v]);
}
// jumlah bulan per paket
function plan_months($plan) { return ['1bln'=>1, '3bln'=>3, '1thn'=>12][$plan] ?? 0; }

function valid_alias($a) { return (bool) preg_match('/^[a-z0-9]{2,24}$/', (string)$a); }

// Auto-provision DB tenant via cPanel UAPI (untuk FREE TRIAL instan di shared hosting).
// Return true kalau DB siap dipakai. Defensif: error apa pun → false (pemanggil fallback ke pending).
function cpanel_create_db($dbname, $dbuser) {
    if (!defined('CPANEL_HOST') || !defined('CPANEL_USER') || !defined('CPANEL_TOKEN') || !CPANEL_HOST || !CPANEL_TOKEN) return false;
    if (!function_exists('curl_init')) { error_log('cpanel: curl tidak tersedia'); return false; }
    $auth = 'Authorization: cpanel ' . CPANEL_USER . ':' . CPANEL_TOKEN;
    $port = defined('CPANEL_PORT') ? (int)CPANEL_PORT : 2083;
    $base = 'https://' . CPANEL_HOST . ':' . $port . '/execute/';
    $call = function ($url) use ($auth) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>[$auth], CURLOPT_TIMEOUT=>20, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
        $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($res === false) { error_log('cpanel curl: ' . $err); return null; }
        $j = json_decode($res, true); return is_array($j) ? $j : null;
    };
    // 1) buat database (kalau sudah ada → anggap ok)
    $r1 = $call($base . 'Mysql/create_database?name=' . rawurlencode($dbname));
    if (!$r1 || (int)($r1['status'] ?? 0) !== 1) {
        $errs = strtolower(implode(' ', (array)($r1['errors'] ?? [])));
        if (strpos($errs, 'exist') === false) { error_log('cpanel create_database gagal: ' . json_encode($r1)); return false; }
    }
    // 2) beri hak penuh user MySQL ke DB itu
    $r2 = $call($base . 'Mysql/set_privileges_on_database?user=' . rawurlencode($dbuser) . '&database=' . rawurlencode($dbname) . '&privileges=' . rawurlencode('ALL PRIVILEGES'));
    if (!$r2 || (int)($r2['status'] ?? 0) !== 1) { error_log('cpanel set_privileges gagal: ' . json_encode($r2)); return false; }
    return true;
}

// usaha yang sedang login (dari session)
function current_business() {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['alias'])) return null;
    $q = master_pdo()->prepare("SELECT * FROM businesses WHERE alias=?"); $q->execute([$_SESSION['alias']]);
    $b = $q->fetch();
    // usaha nonaktif (belum bayar / disetop) = seperti belum login → memutus sesi yang sudah berjalan
    if ($b && (int)($b['active'] ?? 1) === 0) return null;
    return $b ?: null;
}

// koneksi ke database usaha yang sedang login
function db() {
    global $DB_HOST, $DB_USER, $DB_PASS; static $pdo = null; if ($pdo) return $pdo;
    $biz = current_business();
    if (!$biz) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['error' => 'Belum login.', 'needLogin' => true]); exit; }
    try {
        if (ALLOW_DB_CREATE) { try { server_pdo()->exec("CREATE DATABASE IF NOT EXISTS `{$biz['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Exception $e) { error_log('db create tenant: '.$e->getMessage()); } }
        $pdo = new PDO("mysql:host=$DB_HOST;dbname={$biz['db_name']};charset=utf8mb4", $DB_USER, $DB_PASS, _pdo_opt());
        init_schema($pdo);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500); header('Content-Type: application/json');
        error_log('db: '.$e->getMessage()); echo json_encode(['error' => 'Koneksi database gagal.']); exit;
    }
}

function init_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id VARCHAR(32) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        cat VARCHAR(100) DEFAULT 'Umum',
        gram INT DEFAULT 1,
        harga INT DEFAULT 0,
        hpp INT DEFAULT 0
    ) ENGINE=InnoDB");
    // foto produk (data URI, opsional) — tampil di grid kasir
    ensure_column($pdo, 'products', 'photo', "photo MEDIUMTEXT DEFAULT NULL");
    // lacak stok? 0 = made-to-order (F&B) → penjualan tak dibatasi/dihitung stok
    ensure_column($pdo, 'products', 'track_stock', "track_stock TINYINT(1) DEFAULT 1");

    // Penyesuaian stok produk (rusak/hilang/pakai sendiri/koreksi/opname). qty bertanda:
    // negatif = stok berkurang, positif = bertambah. Stok = produksi − terjual + Σ penyesuaian.
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_adjustments (
        id VARCHAR(32) PRIMARY KEY,
        product_id VARCHAR(32),
        adate DATE,
        qty INT DEFAULT 0,
        reason VARCHAR(20) DEFAULT 'koreksi',
        note VARCHAR(255) DEFAULT '',
        created DATETIME DEFAULT NULL,
        INDEX(product_id)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS stores (
        id VARCHAR(32) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        contact VARCHAR(120) DEFAULT '',
        address VARCHAR(255) DEFAULT ''
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS batches (
        id VARCHAR(32) PRIMARY KEY,
        bdate DATE,
        note TEXT,
        created DATETIME DEFAULT NULL
    ) ENGINE=InnoDB");
    ensure_column($pdo, 'batches', 'created', "created DATETIME DEFAULT NULL");

    // Master bahan baku
    $pdo->exec("CREATE TABLE IF NOT EXISTS materials (
        id VARCHAR(32) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        unit VARCHAR(50) DEFAULT 'kg',
        price INT DEFAULT 0
    ) ENGINE=InnoDB");
    // stok minimum utk alert "menipis" (0 = tak dipakai). Stok bahan OPSIONAL — tak wajib & tak memblokir produksi.
    ensure_column($pdo, 'materials', 'min_stock', "min_stock DECIMAL(12,3) DEFAULT 0");

    // Pembelian/stok masuk bahan baku (opsional). Stok = SUM(pembelian) − SUM(pemakaian di batch produksi).
    $pdo->exec("CREATE TABLE IF NOT EXISTS material_purchases (
        id VARCHAR(32) PRIMARY KEY,
        material_id VARCHAR(32),
        pdate DATE,
        qty DECIMAL(12,3) DEFAULT 0,
        price INT DEFAULT 0,
        note VARCHAR(255) DEFAULT '',
        created DATETIME DEFAULT NULL,
        INDEX(material_id)
    ) ENGINE=InnoDB");

    // Riwayat harga bahan baku (untuk bandingkan naik/turun)
    // ref = id batch sumber (kalau source='batch') → tampilkan "dari batch X" di riwayat
    $pdo->exec("CREATE TABLE IF NOT EXISTS material_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id VARCHAR(32),
        pdate DATE,
        price INT DEFAULT 0,
        source VARCHAR(20) DEFAULT 'manual',
        ref VARCHAR(32) DEFAULT NULL,
        INDEX(material_id)
    ) ENGINE=InnoDB");
    ensure_column($pdo, 'material_prices', 'ref', "ref VARCHAR(32) DEFAULT NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS batch_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(32),
        material_id VARCHAR(32) DEFAULT NULL,
        name VARCHAR(255),
        qty DECIMAL(12,3) DEFAULT 0,
        unit VARCHAR(50) DEFAULT '',
        price INT DEFAULT 0,
        target_product VARCHAR(32) DEFAULT NULL,
        INDEX(batch_id)
    ) ENGINE=InnoDB");
    // migrasi: DB lama belum punya kolom alokasi bahan → produk tertentu
    ensure_column($pdo, 'batch_materials', 'target_product', "target_product VARCHAR(32) DEFAULT NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS batch_ops (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(32),
        name VARCHAR(255),
        amount INT DEFAULT 0,
        INDEX(batch_id)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS batch_outputs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(32),
        product_id VARCHAR(32),
        qty INT DEFAULT 0,
        INDEX(batch_id)
    ) ENGINE=InnoDB");

    // Nota / faktur: 1 nota = 1 toko + 1 tanggal, berisi banyak item distribusi
    $pdo->exec("CREATE TABLE IF NOT EXISTS notas (
        id VARCHAR(32) PRIMARY KEY,
        nota_no VARCHAR(64) DEFAULT '',
        ndate DATE,
        store_id VARCHAR(32),
        created_by VARCHAR(160) DEFAULT '',
        INDEX(store_id)
    ) ENGINE=InnoDB");
    ensure_column($pdo, 'notas', 'created_by', "created_by VARCHAR(160) DEFAULT ''");
    // waktu nota dibuat (tanggal+jam) — untuk stempel "Dibuat" di struk (ndate hanya tanggal bisnis)
    ensure_column($pdo, 'notas', 'created', "created DATETIME DEFAULT NULL");
    // sesi kasir & metode bayar (khusus penjualan POS; distribusi biasa kosong)
    ensure_column($pdo, 'notas', 'session_id', "session_id VARCHAR(32) DEFAULT NULL");
    ensure_column($pdo, 'notas', 'pay_method', "pay_method VARCHAR(16) DEFAULT ''");
    // diskon/potongan nota (Rp) — mengurangi nilai jual & laba
    ensure_column($pdo, 'notas', 'discount', "discount INT DEFAULT 0");
    ensure_column($pdo, 'notas', 'service', "service INT DEFAULT 0");        // service charge (Rp), beku saat transaksi
    ensure_column($pdo, 'notas', 'tax', "tax INT DEFAULT 0");                // pajak/PPN (Rp), beku saat transaksi
    ensure_column($pdo, 'notas', 'svc_rate', "svc_rate DECIMAL(5,2) DEFAULT 0");   // tarif service yg dipakai (utk label struk presisi)
    ensure_column($pdo, 'notas', 'tax_rate', "tax_rate DECIMAL(5,2) DEFAULT 0");   // tarif pajak yg dipakai
    // OTP persetujuan owner utk pembatalan transaksi kasir oleh staf (1 baris per nota, sekali pakai)
    $pdo->exec("CREATE TABLE IF NOT EXISTS void_otps (
        nota_id VARCHAR(32) PRIMARY KEY,
        code VARCHAR(8) NOT NULL,
        requested_by VARCHAR(160) DEFAULT '',
        expires_at DATETIME NOT NULL,
        attempts INT DEFAULT 0
    ) ENGINE=InnoDB");

    // Sesi kasir (buka/tutup laci): modal awal, siapa yang buka, rekonsiliasi saat tutup
    $pdo->exec("CREATE TABLE IF NOT EXISTS register_sessions (
        id VARCHAR(32) PRIMARY KEY,
        opened_by VARCHAR(160) DEFAULT '',
        opened_at DATETIME DEFAULT NULL,
        opening_float INT DEFAULT 0,
        closed_by VARCHAR(160) DEFAULT '',
        closed_at DATETIME DEFAULT NULL,
        closing_cash INT DEFAULT NULL,
        expected_cash INT DEFAULT NULL,
        cash_sales INT DEFAULT NULL,
        noncash_sales INT DEFAULT NULL,
        txn_count INT DEFAULT NULL,
        note VARCHAR(255) DEFAULT '',
        status VARCHAR(10) DEFAULT 'open',
        INDEX(status)
    ) ENGINE=InnoDB");

    // Item distribusi (baris produk dalam sebuah nota)
    $pdo->exec("CREATE TABLE IF NOT EXISTS distributions (
        id VARCHAR(32) PRIMARY KEY,
        nota_id VARCHAR(32) DEFAULT NULL,
        ddate DATE,
        store_id VARCHAR(32),
        product_id VARCHAR(32),
        qty INT DEFAULT 0,
        harga INT DEFAULT 0,
        hpp INT DEFAULT 0,
        kind VARCHAR(16) DEFAULT 'jual',
        INDEX(nota_id), INDEX(store_id), INDEX(product_id)
    ) ENGINE=InnoDB");
    ensure_column($pdo, 'distributions', 'nota_id', "nota_id VARCHAR(32) DEFAULT NULL");
    // jenis item: jual / bonus / endorse / tester (non-jual = gratis, tak ditagih)
    ensure_column($pdo, 'distributions', 'kind', "kind VARCHAR(16) DEFAULT 'jual'");

    // Pembayaran menempel ke nota (bukan per item lagi)
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nota_id VARCHAR(32) DEFAULT NULL,
        distribution_id VARCHAR(32),
        pdate DATE,
        amount INT DEFAULT 0,
        note VARCHAR(255) DEFAULT '',
        INDEX(nota_id), INDEX(distribution_id)
    ) ENGINE=InnoDB");
    ensure_column($pdo, 'payments', 'nota_id', "nota_id VARCHAR(32) DEFAULT NULL");
    // waktu pembayaran (tanggal+jam) — untuk stempel "Dibayar" di struk (pdate hanya tanggal)
    ensure_column($pdo, 'payments', 'created', "created DATETIME DEFAULT NULL");

    // Kas keluar: prive (ambil pemilik), operasional, modal (beli bahan), lain
    $pdo->exec("CREATE TABLE IF NOT EXISTS cash_out (
        id VARCHAR(32) PRIMARY KEY,
        cdate DATE,
        category VARCHAR(30) DEFAULT 'lain',
        amount INT DEFAULT 0,
        note VARCHAR(255) DEFAULT '',
        INDEX(cdate)
    ) ENGINE=InnoDB");

    // Tabel meta (flag) — usaha baru mulai KOSONG (tanpa data contoh).
    $pdo->exec("CREATE TABLE IF NOT EXISTS meta (k VARCHAR(50) PRIMARY KEY, v VARCHAR(255)) ENGINE=InnoDB");

    // Profil usaha (1 baris): logo (data URI), alamat, kontak, sosmed → tampil di kop laporan cetak.
    $pdo->exec("CREATE TABLE IF NOT EXISTS profile (
        id INT PRIMARY KEY,
        address VARCHAR(255) DEFAULT '',
        phone VARCHAR(60) DEFAULT '',
        whatsapp VARCHAR(60) DEFAULT '',
        instagram VARCHAR(120) DEFAULT '',
        facebook VARCHAR(120) DEFAULT '',
        tiktok VARCHAR(120) DEFAULT '',
        logo LONGTEXT DEFAULT NULL,
        qris VARCHAR(600) DEFAULT ''
    ) ENGINE=InnoDB");
    ensure_column($pdo, 'profile', 'qris', "qris VARCHAR(600) DEFAULT ''");
    ensure_column($pdo, 'profile', 'footer', "footer VARCHAR(255) DEFAULT ''");   // pesan bawah struk (custom)
    ensure_column($pdo, 'profile', 'svc_enabled', "svc_enabled TINYINT(1) DEFAULT 0");     // service charge on/off
    ensure_column($pdo, 'profile', 'svc_rate', "svc_rate DECIMAL(5,2) DEFAULT 0");          // tarif service charge (%)
    ensure_column($pdo, 'profile', 'tax_enabled', "tax_enabled TINYINT(1) DEFAULT 0");      // pajak/PPN on/off
    ensure_column($pdo, 'profile', 'tax_rate', "tax_rate DECIMAL(5,2) DEFAULT 0");          // tarif pajak (%)
    ensure_column($pdo, 'profile', 'oversell', "oversell TINYINT(1) DEFAULT 0");            // boleh jual walau stok habis (stok minus)
    ensure_column($pdo, 'profile', 'biz_type', "biz_type VARCHAR(16) DEFAULT 'produksi'");  // jenis usaha: produksi | fnb (resto/warung)

    // Sekali jalan: isi ref titik harga 'batch' lama dengan mencocokkan batch (material+tanggal+harga).
    if (!$pdo->query("SELECT v FROM meta WHERE k='ref_backfill'")->fetchColumn()) {
        try {
            $pdo->exec("UPDATE material_prices mp
                JOIN batches ba ON ba.bdate = mp.pdate
                JOIN batch_materials bm ON bm.batch_id = ba.id AND bm.material_id = mp.material_id AND bm.price = mp.price
                SET mp.ref = ba.id
                WHERE mp.source='batch' AND (mp.ref IS NULL OR mp.ref='')");
        } catch (Exception $e) { error_log('ref_backfill: '.$e->getMessage()); }
        $pdo->prepare("REPLACE INTO meta (k,v) VALUES ('ref_backfill','1')")->execute();
    }
}

// Tambah kolom kalau belum ada (migrasi ringan untuk DB yang sudah terlanjur dibuat)
function ensure_column($pdo, $table, $col, $ddl) {
    $q = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $q->execute([$table, $col]);
    if (!$q->fetchColumn()) $pdo->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
}
