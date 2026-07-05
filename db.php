<?php
// ================== KONFIGURASI DATABASE ==================
// Produksi (hosting): salin config.example.php -> config.php, lalu isi kredensial.
// Tanpa config.php, pakai default XAMPP (host 127.0.0.1, user root, password kosong).
@include __DIR__ . '/config.php';
$DB_HOST = $DB_HOST ?? '127.0.0.1';
$DB_USER = $DB_USER ?? 'root';
$DB_PASS = $DB_PASS ?? '';
if (!defined('MASTER_DB'))      define('MASTER_DB', $DB_MASTER ?? 'racikin_master'); // DB registry (daftar usaha)
if (!defined('DB_PREFIX'))      define('DB_PREFIX', $DB_TENANT_PREFIX ?? 'racikin_'); // prefix DB tiap usaha
if (!defined('ALLOW_DB_CREATE')) define('ALLOW_DB_CREATE', $DB_ALLOW_CREATE ?? true); // shared hosting: set false (DB dibuat manual di cPanel)
// =========================================================

// Cookie sesi lebih aman: HttpOnly (tak bisa dibaca JS) + SameSite=Lax (bantu cegah CSRF).
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
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
        created DATETIME
    ) ENGINE=InnoDB");
    // user per usaha: login = kode usaha (alias) + email + password
    $p->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alias VARCHAR(32),
        email VARCHAR(160),
        name VARCHAR(120),
        pass_hash VARCHAR(255),
        created DATETIME,
        UNIQUE KEY uq_alias_email (alias, email)
    ) ENGINE=InnoDB");
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
    return $p;
}

function valid_alias($a) { return (bool) preg_match('/^[a-z0-9]{2,24}$/', (string)$a); }

// usaha yang sedang login (dari session)
function current_business() {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['alias'])) return null;
    $q = master_pdo()->prepare("SELECT * FROM businesses WHERE alias=?"); $q->execute([$_SESSION['alias']]);
    return $q->fetch() ?: null;
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
        INDEX(store_id)
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
