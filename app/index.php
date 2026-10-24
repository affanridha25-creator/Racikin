<?php // Racikin — frontend SPA (data via api.php). CSS di css/*.css, JS di js/*.js — URUTAN MUAT PENTING (lihat daftar di bawah).
function asset($p){return $p.'?v='.(@filemtime(__DIR__.'/'.$p)?:1);} ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Racikin — Produksi, Distribusi & Pembayaran UMKM</title>
<link rel="icon" type="image/png" sizes="64x64" href="icons/favicon.png">
<link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#D53E0F">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Racikin">
<link rel="apple-touch-icon" href="icons/icon-192.png">
<link rel="stylesheet" href="<?=asset('css/base.css')?>">
<link rel="stylesheet" href="<?=asset('css/dashboard.css')?>">
<link rel="stylesheet" href="<?=asset('css/login.css')?>">
<link rel="stylesheet" href="<?=asset('css/dashboard-v2.css')?>">
<link rel="stylesheet" href="<?=asset('css/login-outro.css')?>">
<link rel="stylesheet" href="<?=asset('css/toko.css')?>">
<link rel="stylesheet" href="<?=asset('css/profil.css')?>">
<link rel="stylesheet" href="<?=asset('css/components.css')?>">
<link rel="stylesheet" href="<?=asset('css/pos.css')?>">
<link rel="stylesheet" href="<?=asset('css/print.css')?>">
<link rel="stylesheet" href="<?=asset('css/langganan.css')?>">
<link rel="stylesheet" href="<?=asset('css/base-v2.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-keuangan.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-produksi.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-bahan.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-produk.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-kasir.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-distribusi.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-pembayaran.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-rekap.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-pengguna.css')?>">
<link rel="stylesheet" href="<?=asset('css/p-export.css')?>">
</head>
<body>
<aside class="sidebar">
  <svg class="sb-blob" viewBox="0 0 240 900" preserveAspectRatio="none" aria-hidden="true"><path class="sb-b1" d="M0 0H240V210C196 250 262 300 214 352C168 404 250 450 206 510C170 560 238 610 196 676C160 730 220 790 190 900H0Z"/><path class="sb-b2" d="M0 520C60 548 40 600 94 628C146 656 130 720 190 748V900H0Z"/></svg>
  <div class="brand"><div class="logo"><img id="sideLogo" src="icons/logo-bowl.png" alt=""></div><img class="brand-word" src="icons/logo-word-white.png" alt="Racikin"></div>
  <nav id="nav">
    <i class="nav-ind" aria-hidden="true"></i>
    <div class="nav-grp">Utama</div>
    <button data-v="dashboard" data-label="Dashboard" class="active"><span class="ic">📊</span><span class="nl">Dashboard</span></button>
    <button data-v="pos" data-label="Kasir (POS)"><span class="ic">🛒</span><span class="nl">Kasir</span></button>
    <div class="nav-grp">Operasional</div>
    <button data-v="produksi" data-label="Produksi"><span class="ic">🏭</span><span class="nl">Produksi</span></button>
    <button data-v="distribusi" data-label="Distribusi"><span class="ic">🚚</span><span class="nl">Distribusi</span></button>
    <button data-v="pembayaran" data-label="Pembayaran"><span class="ic">💰</span><span class="nl">Pembayaran</span></button>
    <button data-v="keuangan" data-label="Keuangan"><span class="ic">💹</span><span class="nl">Keuangan</span></button>
    <div class="nav-grp">Katalog</div>
    <button data-v="bahan" data-label="Bahan Baku"><span class="ic">🧂</span><span class="nl">Bahan Baku</span></button>
    <button data-v="produk" data-label="Produk"><span class="ic">🫙</span><span class="nl">Produk</span></button>
    <button data-v="toko" data-label="Toko"><span class="ic">🏪</span><span class="nl">Toko</span></button>
    <div class="nav-grp">Laporan &amp; Admin</div>
    <button data-v="rekap" data-label="Rekap Kasir"><span class="ic">🧾</span><span class="nl">Rekap Kasir</span></button>
    <button data-v="users" data-label="Pengguna"><span class="ic">👥</span><span class="nl">Pengguna</span></button>
    <button data-v="profile" data-label="Profil Usaha"><span class="ic">🏷️</span><span class="nl">Profil Usaha</span></button>
    <button data-v="backup" data-label="Export Data"><span class="ic">📤</span><span class="nl">Export Data</span></button>
  </nav>
  <div class="sb-foot">
    <div class="sb-user"><div class="sb-av">🍲</div><div class="sb-meta"><div class="role">ANNA Snack &amp; Kitchen</div><small>Usaha aktif</small></div></div>
    <button class="logout" onclick="doLogout()"><span>⏻</span> Logout</button>
  </div>
</aside>
<div class="content">
  <div id="subBanner" style="display:none"></div>
  <div class="topbar"><div class="t-left"><div class="t-hi" id="topHi">Selamat datang kembali</div><div class="t-title" id="topTitle">Dashboard</div></div><img class="t-word-img" src="icons/logo-word.png" alt="Racikin"><div class="t-right">👋 Halo, <b>ANNA</b></div></div></div>
  <main>
    <section id="v-dashboard" class="view active"><div class="empty">Memuat data…</div></section>
    <section id="v-pos" class="view"></section>
    <section id="v-produksi" class="view"></section>
    <section id="v-distribusi" class="view"></section>
    <section id="v-pembayaran" class="view"></section>
    <section id="v-keuangan" class="view"></section>
    <section id="v-bahan" class="view"></section>
    <section id="v-produk" class="view"></section>
    <section id="v-toko" class="view"></section>
    <section id="v-rekap" class="view"></section>
    <section id="v-users" class="view"></section>
    <section id="v-profile" class="view"></section>
    <section id="v-backup" class="view"></section>
  </main>
</div>
<div class="modal-bg" id="modalBg"><div class="modal" id="modal"></div></div>
<div id="toast" role="status" aria-live="polite"></div>
<div id="printArea"></div>
<div class="loginbg lsplit" id="loginScreen" style="display:flex">
  <div class="lhero">
    <div class="lfx" aria-hidden="true"><span style="--x:8%;--s:30px;--d:19s;--w:0s">🌶️</span><span style="--x:24%;--s:24px;--d:23s;--w:-7s">🧅</span><span style="--x:41%;--s:28px;--d:21s;--w:-12s">🍤</span><span style="--x:58%;--s:26px;--d:25s;--w:-4s">🧄</span><span style="--x:73%;--s:32px;--d:20s;--w:-15s">🍅</span><span style="--x:88%;--s:24px;--d:24s;--w:-9s">🍋</span></div>
    <div class="lhero-in">
      <img class="lh-word" src="icons/logo-word-white.png" alt="Racikin">
      <div class="lh-copy"><h1>Racik. Hitung. <span>Jual.</span></h1><p>Hitung HPP otomatis, tagih piutang sekali klik, jualan lewat kasir QRIS — semua dalam satu aplikasi. Usaha lebih rapi, untung lebih jelas.</p></div>
      <div class="lh-tag">Tahu untung tiap produk. Tagih piutang tanpa ribet.</div>
      <div class="lstage" aria-hidden="true">
        <div class="lplate"><div class="lorbit"><i>🌶️</i><i>🧅</i><i>🍤</i><i>🧄</i></div><div class="ldisc"><img src="icons/logo-bowl.png" alt=""></div></div>
        <div class="lfc lfc1"><b>📦</b><div><strong>Untung jelas</strong><span>HPP otomatis dari bahan &amp; biaya</span></div></div>
        <div class="lfc lfc2"><b>🧾</b><div><strong>Piutang tertagih</strong><span>kirim tagihan via WhatsApp</span></div></div>
        <div class="lfc lfc3"><b>📱</b><div><strong>Kasir + QRIS</strong><span>cetak struk Bluetooth</span></div></div>
      </div>
    </div>
    <svg class="lwave" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><path d="M100 0 V100 H34 C12 86 58 74 38 56 C18 38 62 26 40 10 C33 4 26 2 22 0 Z" fill="#FFF8F3"/></svg>
    <svg class="lwave-b" viewBox="0 0 400 60" preserveAspectRatio="none" aria-hidden="true"><path d="M0 34 C70 0 130 58 210 30 C290 2 340 40 400 18 V60 H0 Z" fill="#FFF8F3"/></svg>
  </div>
  <div class="lpanel"><div class="loginbox intro" id="loginBox"></div></div>
</div>
<div class="loginbg lsplit sl" id="subLock" style="display:none">
  <div class="lhero">
    <div class="lfx" aria-hidden="true"><span style="--x:10%;--s:28px;--d:20s;--w:0s">🌶️</span><span style="--x:36%;--s:24px;--d:24s;--w:-8s">🧅</span><span style="--x:62%;--s:30px;--d:22s;--w:-13s">🍅</span><span style="--x:86%;--s:24px;--d:26s;--w:-5s">🍋</span></div>
    <div class="lhero-in">
      <img class="lh-word" src="icons/logo-word-white.png" alt="Racikin">
      <div class="lh-copy"><h1>Lanjut racik, <span>tanpa putus.</span></h1><p>Perpanjang sekarang dan semua datamu langsung terbuka lagi: produksi, piutang, kasir, dan laporan untung.</p></div>
      <div class="lh-tag">Data aman tersimpan. Tinggal perpanjang.</div>
      <div class="sl-perks" aria-hidden="true">
        <div class="lfc"><b>🔐</b><div><strong>Data tetap aman</strong><span>tidak ada yang hilang</span></div></div>
        <div class="lfc"><b>⚡</b><div><strong>Aktif otomatis</strong><span>setelah admin menyetujui</span></div></div>
        <div class="lfc"><b>💬</b><div><strong>Ada kendala?</strong><span>hubungi admin Racikin</span></div></div>
      </div>
    </div>
    <svg class="lwave" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><path d="M100 0 V100 H34 C12 86 58 74 38 56 C18 38 62 26 40 10 C33 4 26 2 22 0 Z" fill="#FFF8F3"/></svg>
    <svg class="lwave-b" viewBox="0 0 400 60" preserveAspectRatio="none" aria-hidden="true"><path d="M0 34 C70 0 130 58 210 30 C290 2 340 40 400 18 V60 H0 Z" fill="#FFF8F3"/></svg>
  </div>
  <div class="lpanel"><div class="loginbox" id="subLockBox"></div></div>
</div>
<nav class="botnav" id="botnav">
  <i class="bn-ind" aria-hidden="true"></i>
  <button data-v="dashboard" onclick="go('dashboard')"><span class="bic">📊</span><em>Beranda</em></button>
  <button id="bnSlot2" data-v="distribusi" onclick="go('distribusi')"><span class="bic">🚚</span><em>Distribusi</em></button>
  <button class="fab" onclick="fabAction()" aria-label="Tambah"><b>+</b></button>
  <button id="bnSlot4" data-v="produksi" onclick="go('produksi')"><span class="bic">🏭</span><em>Produksi</em></button>
  <button id="btnMore" onclick="openMore()"><span class="bic">✨</span><em>Lainnya</em></button>
</nav>
<div class="sheet-bg" id="sheetBg" onclick="closeMore(event)">
  <div class="sheet">
    <div class="sheet-grab" aria-hidden="true"></div>
    <div class="sheet-h">Menu Lainnya</div>
    <div class="sheet-grid">
      <button onclick="go('pos')"><span>🛒</span> Kasir (POS)</button>
      <button onclick="go('keuangan')"><span>💹</span> Keuangan</button>
      <button onclick="go('pembayaran')"><span>💰</span> Pembayaran</button>
      <button onclick="go('bahan')"><span>🧂</span> Bahan Baku</button>
      <button onclick="go('produk')"><span>🫙</span> Produk</button>
      <button onclick="go('toko')"><span>🏪</span> Toko</button>
      <button onclick="go('rekap')"><span>🧾</span> Rekap Kasir</button>
      <button onclick="go('users')"><span>👥</span> Pengguna</button>
      <button onclick="go('profile')"><span>🏷️</span> Profil Usaha</button>
      <button onclick="go('backup')"><span>📤</span> Export Data</button>
    </div>
    <button class="sheet-out" onclick="closeMore();doLogout()"><span>⏻</span> Keluar</button>
  </div>
</div>

<script src="<?=asset('js/core.js')?>"></script>
<script src="<?=asset('js/offline.js')?>"></script>
<script src="<?=asset('js/tenant.js')?>"></script>
<script src="<?=asset('js/keuangan.js')?>"></script>
<script src="<?=asset('js/export-csv.js')?>"></script>
<script src="<?=asset('js/derived.js')?>"></script>
<script src="<?=asset('js/nav.js')?>"></script>
<script src="<?=asset('js/dashboard.js')?>"></script>
<script src="<?=asset('js/qris.js')?>"></script>
<script src="<?=asset('js/pos.js')?>"></script>
<script src="<?=asset('js/print-thermal.js')?>"></script>
<script src="<?=asset('js/produksi.js')?>"></script>
<script src="<?=asset('js/distribusi.js')?>"></script>
<script src="<?=asset('js/pembayaran.js')?>"></script>
<script src="<?=asset('js/bahan.js')?>"></script>
<script src="<?=asset('js/produk.js')?>"></script>
<script src="<?=asset('js/toko.js')?>"></script>
<script src="<?=asset('js/rekap.js')?>"></script>
<script src="<?=asset('js/pengguna.js')?>"></script>
<script src="<?=asset('js/profil.js')?>"></script>
<script src="<?=asset('js/export-data.js')?>"></script>
<script src="<?=asset('js/auth.js')?>"></script>
<script src="<?=asset('js/langganan.js')?>"></script>
<script src="<?=asset('js/push.js')?>"></script>
<script src="<?=asset('js/boot.js')?>"></script>
</body>
</html>
