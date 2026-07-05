<?php
// ================= KONFIGURASI PRODUKSI =================
// 1. Salin file ini jadi: config.php  (di folder yang sama, /app)
// 2. Isi sesuai data hosting (cPanel > MySQL Databases).
// 3. config.php TIDAK ikut ke git (sudah di .gitignore) — jangan pernah dibagikan.

$DB_HOST = 'localhost';                 // di cPanel umumnya 'localhost'
$DB_USER = 'NAMACPANEL_racikin';        // user MySQL yang kamu buat di cPanel
$DB_PASS = 'GANTI_PASSWORD_KUAT';       // password user MySQL tsb

// DB registry (daftar usaha). Buat DB ini di cPanel, assign user di atas.
define('DB_MASTER', 'NAMACPANEL_master');

// Prefix DB tiap usaha. Semua DB di cPanel otomatis berawalan "NAMACPANEL_".
// db_name usaha = prefix + kode_usaha (mis. kode "tokobudi" -> NAMACPANEL_tokobudi).
define('DB_TENANT_PREFIX', 'NAMACPANEL_');

// Shared hosting/cPanel -> false (DB dibuat manual di cPanel via panel Admin).
// VPS dengan hak CREATE DATABASE -> true (usaha baru bikin DB sendiri).
define('DB_ALLOW_CREATE', false);

// ---- Panel Admin (admin.php) ----
// Password untuk masuk panel aktivasi usaha. WAJIB ganti yang kuat.
define('ADMIN_PASS', 'GANTI_PASSWORD_ADMIN');

// ---- Email reset password ----
define('APP_URL', 'https://login.racikin.com');       // untuk link di email ('' = auto dari domain)
define('RESET_FROM', 'noreply@racikin.com');          // buat email ini di cPanel > Email Accounts
