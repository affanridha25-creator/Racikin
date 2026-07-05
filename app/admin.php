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
        elseif ($do === 'activate')   { $m->prepare("UPDATE businesses SET active=1 WHERE alias=?")->execute([$alias]); $msg = "Usaha \"$alias\" diaktifkan. ✓"; }
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

$rows = $isAdmin ? $m->query("SELECT b.alias,b.name,b.db_name,b.active,b.created,
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
@media(max-width:640px){table,thead,tbody,tr,th,td{display:block}th{display:none}td{border:none;padding:3px 0}tr{border-bottom:1px solid var(--line);padding:12px 0}}
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
          <td><span class="pill <?= $on?'on':'off' ?>"><?= $on?'AKTIF':'PENDING' ?></span></td>
          <td><div class="acts">
            <?php if (!$on): ?>
              <form method="post" onsubmit="return confirm('Pastikan database <?= h($r['db_name']) ?> sudah dibuat di cPanel + user MySQL sudah di-assign. Aktifkan usaha ini?')">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="activate"><input type="hidden" name="alias" value="<?= h($r['alias']) ?>">
                <button class="btn sm green">✓ Aktifkan</button>
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
