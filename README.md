# Racikin — Aplikasi Produksi, Jualan & Keuangan UMKM

SaaS multi-usaha (PHP + MySQL) untuk UMKM produksi rumahan: **HPP per batch, distribusi/nota,
POS + QRIS, piutang, laba rugi, multi-user, rekap kasir**. Plus landing page.

## Struktur repo (deploy sekali `git pull`)

```
app/       -> aplikasi PHP  (docroot subdomain login.racikin.com)
public/    -> landing page  (docroot domain  racikin.com)
```

Satu repo, dua docroot. Sekali `git pull` memperbarui website & aplikasi sekaligus.

## Deploy di cPanel (shared hosting)

1. **cPanel → Git™ Version Control → Create** → clone repo ini ke sebuah folder (mis. `~/racikin`).
2. **Domain `racikin.com`** → set Document Root ke `~/racikin/public`.
3. **Subdomain `login.racikin.com`** → buat, set Document Root ke `~/racikin/app`.
4. **Database** (cPanel → MySQL Databases): buat DB registry `NAMACPANEL_master` + user MySQL, assign (ALL PRIVILEGES). Buat juga DB usaha pertama (mis. `NAMACPANEL_anna`).
5. **Konfigurasi**: salin `app/config.example.php` → `app/config.php`, isi kredensial + `ADMIN_PASS`.
6. **Import data** awal (sekali): dump DB ANNA via phpMyAdmin (file dump dikirim terpisah, tidak ada di repo publik).
7. **SSL** (AutoSSL/Let's Encrypt) untuk kedua domain — wajib (app pakai cookie login).
8. Update berikutnya: cukup **Pull** dari cPanel Git (atau `git pull`). `app/config.php` & data tidak tertimpa.

## Panel Admin (aktivasi berbayar)

Model: pengguna **Daftar** → akun *pending* → kamu buat DB di cPanel + **Aktifkan** di panel admin
setelah pembayaran. Buka **`login.racikin.com/admin.php`**, login dengan `ADMIN_PASS`.
DB tiap usaha = `DB_TENANT_PREFIX` + kode usaha.

## Jalan lokal (XAMPP)

1. Taruh repo di `htdocs`, Start Apache + MySQL.
2. Buka `http://localhost/anna-manager/app/`.
3. Tanpa `config.php`, pakai default XAMPP (root, tanpa password); DB & tabel dibuat otomatis.

## Fitur utama

Dashboard kokpit · Kasir (POS) + QRIS dinamis · Distribusi/Nota · Pembayaran & piutang ·
Keuangan (laba rugi + kas) · Bahan baku + tren harga · Produk · Toko · Profil (logo di struk) ·
Multi-user + hak akses per-user · Rekap penjualan per kasir · Backup · Reset password via email · PWA (installable).

> Aplikasi asal dari klien tunggal "ANNA Snack & Kitchen", kini jadi produk multi-tenant **Racikin**.
