<?php
// ================= PANEL ADMIN RACIKIN =================
// Aktivasi/nonaktif/hapus usaha. Dilindungi ADMIN_PASS (set di config.php).
require __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
$m = master_pdo();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];
$adminConfigured = defined('ADMIN_PASS') && ADMIN_PASS !== '' && ADMIN_PASS !== 'GANTI_PASSWORD_ADMIN';

if (isset($_GET['logout'])) { unset($_SESSION['admin']); header('Location: admin.php'); exit; }

$msg = ''; $err = '';
$do = $_POST['do'] ?? '';

if ($do === 'login') {
    // kunci ber-prefix "adm:" supaya rate-limit admin TERPISAH dari brute-force login tenant
    $ipk = 'adm:' . ($_SERVER['REMOTE_ADDR'] ?? '?');
    $ac = $m->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND ts > (NOW() - INTERVAL 10 MINUTE)");
    $ac->execute([$ipk]);
    if ($ac->fetchColumn() >= 10) $err = 'Terlalu banyak percobaan. Coba lagi dalam 10 menit.';
    elseif (!$adminConfigured) $err = 'Admin belum dikonfigurasi. Set ADMIN_PASS (yang kuat) di config.php.';
    elseif (hash_equals(ADMIN_PASS, (string)($_POST['pass'] ?? ''))) {
        session_regenerate_id(true); $_SESSION['admin'] = true;
        $m->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([$ipk]);
        header('Location: admin.php'); exit;
    } else {
        $m->prepare("INSERT INTO login_attempts (ip,ts) VALUES (?,NOW())")->execute([$ipk]);
        $err = 'Password admin salah.';
    }
}

$isAdmin = !empty($_SESSION['admin']);

if ($isAdmin && in_array($do, ['activate','deactivate','delete'], true)) {
    if (!isset($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf'])) { $err = 'Sesi kadaluarsa, ulangi.'; }
    else {
        $alias = preg_replace('/[^a-z0-9]/', '', strtolower($_POST['alias'] ?? ''));
        if ($alias === '') { $err = 'Alias tidak valid.'; }
        elseif ($do === 'activate')   { $m->prepare("UPDATE businesses SET active=1, paid_until=IFNULL(paid_until, DATE_ADD(CURDATE(), INTERVAL 30 DAY)) WHERE alias=?")->execute([$alias]); $msg = "Usaha \"$alias\" diaktifkan — trial 30 hari dimulai. ✓"; }
        elseif ($do === 'deactivate') { $m->prepare("UPDATE businesses SET active=0 WHERE alias=?")->execute([$alias]); $msg = "Usaha \"$alias\" dinonaktifkan."; }
        elseif ($do === 'delete') {
            $m->prepare("DELETE FROM businesses WHERE alias=?")->execute([$alias]);
            $m->prepare("DELETE FROM users WHERE alias=?")->execute([$alias]);
            $m->prepare("DELETE FROM remember_tokens WHERE alias=?")->execute([$alias]);
            $m->prepare("DELETE FROM password_resets WHERE alias=?")->execute([$alias]);
            $msg = "Usaha \"$alias\" dihapus dari registry. (Database di cPanel hapus manual bila perlu.)";
        }
    }
}

// --- Pengaturan langganan + verifikasi bukti transfer ---
if ($isAdmin && in_array($do, ['save_settings','approve','reject','extend'], true)) {
    if (!isset($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf'])) { $err = 'Sesi kadaluarsa, ulangi.'; }
    elseif ($do === 'save_settings') {
        foreach (['price_1bln','price_3bln','price_1thn'] as $k) setting_set($k, max(0, (int)preg_replace('/\D/', '', $_POST[$k] ?? '0')));
        setting_set('bank_info', mb_substr(trim($_POST['bank_info'] ?? ''), 0, 1000));
        setting_set('uniq_on', empty($_POST['uniq_on']) ? '0' : '1');
        $msg = 'Pengaturan langganan disimpan. ✓';
    }
    elseif ($do === 'approve') {
        $rid = (int)($_POST['rid'] ?? 0);
        $r = $m->prepare("SELECT * FROM renewal_requests WHERE id=? AND status='pending'"); $r->execute([$rid]); $r = $r->fetch();
        if (!$r) { $err = 'Pengajuan tak ditemukan / sudah diproses.'; }
        else {
            // klaim atomik: flip status DULU (compare-and-set). Hanya 1 approve yang lolos →
            // cegah dobel-perpanjang saat admin double-click / request diulang.
            $claim = $m->prepare("UPDATE renewal_requests SET status='approved', reviewed_at=NOW() WHERE id=? AND status='pending'");
            $claim->execute([$rid]);
            if ($claim->rowCount() !== 1) { $err = 'Pengajuan sudah diproses barusan.'; }
            else {
                $mo = plan_months($r['plan']); if ($mo <= 0) $mo = 1;
                $m->prepare("UPDATE businesses SET active=1, paid_until = DATE_ADD(GREATEST(COALESCE(paid_until,CURDATE()),CURDATE()), INTERVAL ? MONTH) WHERE alias=?")->execute([$mo, $r['alias']]);
                $msg = "Perpanjangan \"{$r['alias']}\" disetujui (+{$mo} bulan). ✓";
            }
        }
    }
    elseif ($do === 'reject') {
        $rid = (int)($_POST['rid'] ?? 0);
        $m->prepare("UPDATE renewal_requests SET status='rejected', admin_note=?, reviewed_at=NOW() WHERE id=? AND status='pending'")
          ->execute([mb_substr(trim($_POST['admin_note'] ?? ''), 0, 255), $rid]);
        $msg = 'Pengajuan ditolak.';
    }
    elseif ($do === 'extend') {
        $alias = preg_replace('/[^a-z0-9]/', '', strtolower($_POST['alias'] ?? ''));
        $mo = (int)($_POST['months'] ?? 0);
        if ($alias !== '' && in_array($mo, [1,3,12], true)) {
            $m->prepare("UPDATE businesses SET active=1, paid_until = DATE_ADD(GREATEST(COALESCE(paid_until,CURDATE()),CURDATE()), INTERVAL ? MONTH) WHERE alias=?")->execute([$mo, $alias]);
            $msg = "Langganan \"$alias\" diperpanjang +$mo bulan. ✓";
        } else { $err = 'Data perpanjang tidak valid.'; }
    }
}

$settings = $isAdmin ? settings_all() : [];
$reqs = $isAdmin ? $m->query("SELECT rr.*, b.name AS biz_name, DATEDIFF(b.paid_until, CURDATE()) AS days_left
    FROM renewal_requests rr LEFT JOIN businesses b ON b.alias=rr.alias
    WHERE rr.status='pending' ORDER BY rr.created ASC")->fetchAll() : [];
$rows = $isAdmin ? $m->query("SELECT b.alias,b.name,b.db_name,b.active,b.created,b.paid_until,
    DATEDIFF(b.paid_until, CURDATE()) AS days_left,
    (SELECT email FROM users u WHERE u.alias=b.alias AND u.role='owner' LIMIT 1) AS owner_email,
    (SELECT COUNT(*) FROM users u WHERE u.alias=b.alias) AS user_count
    FROM businesses b ORDER BY b.active ASC, b.created DESC")->fetchAll() : [];
$pending = 0; foreach ($rows as $r) if ((int)$r['active'] === 0) $pending++;
?>
<!doctype html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — Racikin</title>
<style>
:root{--red:#D53E0F;--red-d:#7E0306;--orange:#FA8743;--bg:#FBEEE7;--ink:#2b2b2b;--muted:#8a8f99;--line:#eef0f2;--green:#1E8449;--amber:#C2651B}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#FBEEE7 linear-gradient(160deg,#FFF4EC,#FCE0CF);color:var(--ink);min-height:100vh;padding:24px 14px}
.wrap{max-width:1000px;margin:0 auto}
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px}
.brand img{width:38px;height:38px;object-fit:contain}
.brand small{font-size:12px;font-weight:700;color:#fff;background:var(--red);padding:2px 8px;border-radius:20px;margin-left:4px}
.card{background:#fff;border-radius:18px;padding:22px;box-shadow:0 10px 30px rgba(126,3,6,.12)}
.loginbox{max-width:380px;margin:8vh auto 0;text-align:center}
.loginbox img{width:66px;height:66px;object-fit:contain;margin-bottom:6px}
.loginbox h2{font-size:20px;margin-bottom:16px}
input[type=password]{width:100%;padding:11px 12px;border:1px solid #d8d8d8;border-radius:10px;font-size:15px;margin-bottom:12px}
.btn{background:var(--red);color:#fff;border:none;padding:11px 18px;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer}
.btn:hover{background:var(--red-d)}.btn.sm{padding:6px 12px;font-size:13px}
.btn.gray{background:#eee;color:#555}.btn.green{background:var(--green)}.btn.amber{background:var(--amber)}.btn.del{background:#fbe7e5;color:var(--red)}
.btn.wa{background:#25D366;color:#fff;text-decoration:none;display:inline-flex;align-items:center}
.logout{color:var(--red);font-weight:700;font-size:13px;text-decoration:none}
.msg{padding:11px 14px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:600}
.msg.ok{background:#e6f5ec;color:var(--green)}.msg.err{background:#fbe7e5;color:var(--red)}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th,td{text-align:left;padding:11px 10px;border-bottom:1px solid var(--line);vertical-align:middle}
th{font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted)}
.pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800}
.pill.on{background:#e6f5ec;color:var(--green)}.pill.off{background:#fdeede;color:var(--amber)}
.dbn{font-family:ui-monospace,monospace;font-size:12px;background:#f6f2f0;padding:2px 6px;border-radius:5px}
.acts{display:flex;gap:6px;flex-wrap:wrap}
.hint{font-size:12px;color:var(--muted);margin-top:14px;line-height:1.5}
.count{font-size:13px;color:var(--muted)}
.rq{display:flex;gap:14px;padding:12px 0;border-bottom:1px solid var(--line)}
.rq:last-child{border-bottom:none}
.rq-img{width:84px;height:84px;object-fit:cover;border-radius:10px;border:1px solid var(--line);flex-shrink:0;cursor:zoom-in}
.rq-img.noimg{display:flex;align-items:center;justify-content:center;text-align:center;font-size:11px;color:var(--muted);background:#f6f2f0;cursor:default}
.rq-main{flex:1;min-width:0}
.rq-amt{margin-top:6px;font-size:14px}.rq-amt b{color:var(--red);font-size:16px}
.setgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.setgrid label,details label{font-size:12px;color:var(--muted);display:block;margin-bottom:3px}
.setgrid input,textarea{width:100%;padding:9px 11px;border:1px solid #d8d8d8;border-radius:9px;font-size:14px;font-family:inherit}
details summary{list-style:none}details summary::-webkit-details-marker{display:none}
@media(max-width:640px){table,thead,tbody,tr,th,td{display:block}th{display:none}td{border:none;padding:3px 0}tr{border-bottom:1px solid var(--line);padding:12px 0}.setgrid{grid-template-columns:1fr}}
</style></head><body><div class="wrap">

<?php if (!$isAdmin): ?>
  <div class="card loginbox">
    <img src="icons/logo-bowl.png" alt="">
    <h2>Panel Admin Racikin</h2>
    <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
    <?php if (!$adminConfigured): ?><div class="msg err">Set <b>ADMIN_PASS</b> yang kuat di <b>config.php</b> dulu.</div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="do" value="login">
      <input type="password" name="pass" placeholder="Password admin" autofocus>
      <button class="btn" style="width:100%">Masuk</button>
    </form>
  </div>
<?php else: ?>
  <div class="top">
    <div class="brand"><img src="icons/logo-bowl.png" alt="">Racikin<small>ADMIN</small></div>
    <a class="logout" href="admin.php?logout=1">Keluar ⏻</a>
  </div>
  <?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>

  <!-- Antrian bukti transfer perpanjangan -->
  <div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h2 style="font-size:17px">Bukti Transfer Masuk</h2>
      <span class="count"><b style="color:var(--amber)"><?= count($reqs) ?></b> menunggu verifikasi</span>
    </div>
    <?php if (!$reqs): ?>
      <div style="text-align:center;color:var(--muted);padding:18px">Belum ada pengajuan perpanjangan.</div>
    <?php else: foreach ($reqs as $q): ?>
      <div class="rq">
        <?php if (!empty($q['proof'])): ?><a href="<?= h($q['proof']) ?>" target="_blank"><img class="rq-img" src="<?= h($q['proof']) ?>" alt="bukti"></a><?php else: ?><div class="rq-img noimg">tanpa bukti</div><?php endif; ?>
        <div class="rq-main">
          <b><?= h($q['biz_name'] ?: $q['alias']) ?></b> <span class="dbn"><?= h($q['alias']) ?></span><br>
          <span class="count">Paket <b><?= h($q['plan']) ?></b> · diajukan <?= h(substr((string)$q['created'],0,16)) ?><?php if ($q['days_left']!==null): ?> · langganan <?= (int)$q['days_left']<0?'habis':'sisa '.(int)$q['days_left'].' hari' ?><?php endif; ?></span>
          <div class="rq-amt">Nominal harus cocok: <b>Rp<?= number_format((int)$q['amount'],0,',','.') ?></b></div>
          <?php if (!empty($q['note'])): ?><div class="count">Catatan: <?= h($q['note']) ?></div><?php endif; ?>
          <div class="acts" style="margin-top:8px">
            <form method="post" onsubmit="return confirm('Setujui perpanjangan <?= h($q['alias']) ?> (+<?= plan_months($q['plan']) ?> bulan)? Pastikan nominal Rp<?= number_format((int)$q['amount'],0,',','.') ?> sudah masuk rekening.')">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="approve"><input type="hidden" name="rid" value="<?= (int)$q['id'] ?>">
              <button class="btn sm green">✓ Setujui & Perpanjang</button>
            </form>
            <form method="post" onsubmit="return confirm('Tolak pengajuan ini?')">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="reject"><input type="hidden" name="rid" value="<?= (int)$q['id'] ?>">
              <button class="btn sm del">Tolak</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Pengaturan harga & rekening -->
  <details class="card" style="margin-bottom:18px"<?= (($settings['price_1bln']??'0')==='0') ? ' open' : '' ?>>
    <summary style="font-weight:800;font-size:16px;cursor:pointer">⚙️ Pengaturan Harga &amp; Rekening</summary>
    <form method="post" style="margin-top:14px">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="save_settings">
      <div class="setgrid">
        <div><label>Harga 1 Bulan (Rp)</label><input name="price_1bln" inputmode="numeric" value="<?= (int)($settings['price_1bln']??0) ?>"></div>
        <div><label>Harga 3 Bulan (Rp)</label><input name="price_3bln" inputmode="numeric" value="<?= (int)($settings['price_3bln']??0) ?>"></div>
        <div><label>Harga 1 Tahun (Rp)</label><input name="price_1thn" inputmode="numeric" value="<?= (int)($settings['price_1thn']??0) ?>"></div>
      </div>
      <label style="margin-top:12px;display:block;font-size:13px;color:var(--muted)">Info Rekening / Cara Bayar (tampil ke pelanggan saat perpanjang)</label>
      <textarea name="bank_info" rows="3" placeholder="mis. BCA 1234567890 a.n. Nama Pemilik"><?= h($settings['bank_info']??'') ?></textarea>
      <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px"><input type="checkbox" name="uniq_on" value="1" <?= ($settings['uniq_on']??'1')==='1'?'checked':'' ?> style="width:auto"> Aktifkan kode unik nominal (1–50) — memudahkan cek transfer</label>
      <button class="btn" style="margin-top:14px">Simpan Pengaturan</button>
    </form>
  </details>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h2 style="font-size:17px">Daftar Usaha</h2>
      <span class="count"><?= count($rows) ?> usaha · <b style="color:var(--amber)"><?= $pending ?></b> menunggu aktivasi</span>
    </div>
    <table>
      <thead><tr><th>Usaha</th><th>Kode / DB</th><th>Pemilik</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px">Belum ada usaha.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): $on = (int)$r['active'] === 1; ?>
        <tr>
          <td><b><?= h($r['name']) ?></b><br><span class="count"><?= (int)$r['user_count'] ?> pengguna · daftar <?= h(substr((string)$r['created'],0,10)) ?></span></td>
          <td><b><?= h($r['alias']) ?></b><br><span class="dbn"><?= h($r['db_name']) ?></span></td>
          <td><?= h($r['owner_email'] ?: '-') ?></td>
          <td><span class="pill <?= $on?'on':'off' ?>"><?= $on?'AKTIF':'PENDING' ?></span>
            <?php if ($on): ?><div class="count" style="margin-top:5px">
              <?php if ($r['paid_until']===null): ?><span style="color:var(--muted)">tanpa batas</span>
              <?php else: $dl=(int)$r['days_left']; ?>s/d <?= h($r['paid_until']) ?><br><span style="font-weight:700;color:<?= $dl<0?'var(--red)':($dl<=7?'var(--amber)':'var(--green)') ?>"><?= $dl<0?('habis '.abs($dl).' hr lalu'):('sisa '.$dl.' hari') ?></span><?php endif; ?>
            </div><?php endif; ?>
          </td>
          <td><div class="acts">
            <?php if ($on): ?>
              <form method="post" style="display:flex;gap:4px;flex-wrap:wrap">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="extend"><input type="hidden" name="alias" value="<?= h($r['alias']) ?>">
                <button class="btn sm" name="months" value="1" title="Perpanjang 1 bulan">+1bln</button>
                <button class="btn sm" name="months" value="3" title="Perpanjang 3 bulan">+3bln</button>
                <button class="btn sm" name="months" value="12" title="Perpanjang 1 tahun">+1thn</button>
              </form>
              <?php if ($r['paid_until']!==null): $dl=(int)$r['days_left'];
                $waMsg = "Halo 🙏 Pengingat dari Racikin.\n\nLangganan usaha \"{$r['name']}\" "
                  . ($dl<0 ? "sudah habis pada {$r['paid_until']}" : ($dl<=0 ? "habis hari ini" : "akan habis dalam {$dl} hari ({$r['paid_until']})"))
                  . ".\n\nSilakan perpanjang lewat aplikasi (pilih paket → transfer → upload bukti) supaya bisa terus dipakai. Terima kasih 🙏"; ?>
                <a class="btn sm wa" href="https://wa.me/?text=<?= rawurlencode($waMsg) ?>" target="_blank" rel="noopener" title="Ingatkan pemilik via WhatsApp">💬 Ingatkan</a>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (!$on): ?>
              <form method="post" onsubmit="return confirm('Pastikan database <?= h($r['db_name']) ?> sudah dibuat di cPanel + user MySQL sudah di-assign. Aktifkan &amp; mulai TRIAL 30 hari gratis?')">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="activate"><input type="hidden" name="alias" value="<?= h($r['alias']) ?>">
                <button class="btn sm green" title="Aktifkan + mulai trial 30 hari gratis">✓ Aktifkan (Trial 30hr)</button>
              </form>
            <?php else: ?>
              <form method="post" onsubmit="return confirm('Nonaktifkan (blokir login) usaha ini?')">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="deactivate"><input type="hidden" name="alias" value="<?= h($r['alias']) ?>">
                <button class="btn sm amber">Nonaktifkan</button>
              </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('HAPUS usaha \'<?= h($r['alias']) ?>\' dari registry? (data di database tenant TIDAK ikut terhapus)')">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="delete"><input type="hidden" name="alias" value="<?= h($r['alias']) ?>">
              <button class="btn sm del">Hapus</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="hint">
      <b>Alur aktivasi (setelah pembayaran):</b><br>
      1. Di cPanel → MySQL Databases → buat database persis bernama seperti di kolom <b>DB</b> (mis. <span class="dbn">racikin_tokobudi</span>).<br>
      2. Add User to Database → tambahkan user MySQL utamamu ke DB itu (ALL PRIVILEGES).<br>
      3. Kembali ke sini → klik <b>✓ Aktifkan</b>. Pelanggan langsung bisa login. Tabel dibuat otomatis saat login pertama.
    </div>
  </div>
<?php endif; ?>
</div></body></html>
