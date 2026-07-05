# ANNA Manager — Sistem Produksi, Distribusi & Pembayaran

Aplikasi web berbasis **PHP + MySQL** untuk ANNA Snack & Kitchen. Berjalan lokal
di **XAMPP**, dengan database sungguhan (bukan lagi penyimpanan browser).

## Fitur

- **Dashboard** — omzet, laba, piutang, stok, grafik omzet per produk & piutang per toko.
- **Produksi** — input bahan baku (harga otomatis dari master) + biaya operasional → tentukan produk jadi & qty → **HPP per batch dihitung otomatis** (dibagi per bobot gram antar produk). Botol jadi otomatis menambah stok.
- **Distribusi** — catat pengiriman ke toko; stok berkurang otomatis; laba per transaksi terhitung.
- **Pembayaran** — lacak tagihan tiap toko, bisa dicicil (Belum → Sebagian → Lunas), sisa piutang otomatis.
- **Bahan Baku** — master bahan + **riwayat harga**, lengkap dengan indikator naik/turun (%) untuk membandingkan perubahan harga dari waktu ke waktu.
- **Produk** & **Toko** — master data.
- **Backup** — export/restore .json, dan export CSV (batch, distribusi, pembayaran, harga bahan) untuk dibuka di Excel.

## Cara Pasang (XAMPP)

1. **Salin folder** `anna-manager` ini ke folder `htdocs` XAMPP:
   - Windows: `C:\xampp\htdocs\anna-manager`
   - macOS: `/Applications/XAMPP/htdocs/anna-manager` (atau `.../xamppfiles/htdocs/anna-manager`)
2. Buka **XAMPP Control Panel**, klik **Start** pada **Apache** dan **MySQL**.
3. Buka browser ke: **http://localhost/anna-manager/**
4. Selesai. Database `anna_manager` dan semua tabel dibuat **otomatis** saat pertama dibuka,
   sudah terisi contoh produk & bahan baku.

## Konfigurasi Database (opsional)

Default sudah cocok dengan XAMPP standar (user `root`, password kosong).
Kalau setup MySQL kamu berbeda, ubah bagian atas file **`db.php`**:

```php
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';        // isi kalau MySQL kamu pakai password
$DB_NAME = 'anna_manager';
```

## Struktur File

| File | Fungsi |
|------|--------|
| `index.php` | Tampilan aplikasi (frontend) |
| `api.php` | Backend — semua operasi data (JSON) |
| `db.php` | Koneksi + pembuatan database & tabel otomatis + data awal |
| `schema.sql` | Referensi skema tabel (tidak perlu diimpor manual) |

## Catatan

- Data tersimpan permanen di MySQL. Tetap disarankan **Download Backup (.json)** berkala dari menu Backup.
- HPP produk diperbarui otomatis mengikuti batch produksi terakhir, tapi tetap bisa diubah manual di menu Produk.
- Ingin diakses dari HP / beberapa komputer dalam satu jaringan? Bisa — cukup akses `http://<IP-komputer>/anna-manager/` dari device lain di jaringan yang sama (pastikan firewall mengizinkan). Untuk akses internet publik perlu langkah tambahan (hosting/port-forwarding).
