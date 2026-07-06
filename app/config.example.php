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
// PENTING (keamanan): APP_URL WAJIB diisi host aslimu. Kalau kosong, link reset
// memakai Host dari request yang bisa dipalsukan penyerang (Host-header injection).
define('APP_URL', 'https://login.racikin.com');       // host aplikasi (dipakai untuk link di email)
define('RESET_FROM', 'noreply@racikin.com');          // buat email ini di cPanel > Email Accounts

// Daftar host yang diizinkan — jaring pengaman bila APP_URL kosong (cegah Host-header injection).
// Ganti kalau domainmu berbeda. Boleh biarkan bila sudah set APP_URL di atas.
define('APP_HOSTS', ['login.racikin.com', 'racikin.com']);

// ---- FREE TRIAL (auto-buat DB tenant via cPanel API) ----
// Kalau TRIAL_ON=true DAN CPANEL_* terisi benar: daftar → DB dibuat otomatis →
// langsung aktif trial. Kalau gagal / TRIAL_ON=false: daftar = PENDING (aktivasi manual admin).
define('TRIAL_ON', false);          // ganti true setelah CPANEL_* di bawah terisi & teruji
define('TRIAL_DAYS', 30);           // lama trial gratis (hari)
// cPanel API token: cPanel > "Manage API Tokens" > Create. RAHASIA — jangan dibagikan.
// CPANEL_HOST = host cPanel (mis. 'srv123.rumahweb.com' atau domainmu). CPANEL_USER = username cPanel.
// CPANEL_DB_USER = user MySQL yang diberi hak ke DB baru (kosongkan = pakai DB_USER di atas).
define('CPANEL_HOST', '');          // mis. 'srv123.rumahweb.com'
define('CPANEL_PORT', 2083);
define('CPANEL_USER', '');          // username cPanel
define('CPANEL_TOKEN', '');         // API token cPanel (rahasia)
define('CPANEL_DB_USER', '');       // kosong = pakai DB_USER
