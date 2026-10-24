<?php
// ================= PANEL ADMIN RACIKIN =================
// Aktivasi/nonaktif/hapus usaha. Dilindungi ADMIN_PASS (set di config.php).
require __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';
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

if ($isAdmin && in_array($do, ['activate','deactivate','delete','set_wa'], true)) {
    if (!isset($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf'])) { $err = 'Sesi kadaluarsa, ulangi.'; }
    else {
        $alias = preg_replace('/[^a-z0-9]/', '', strtolower($_POST['alias'] ?? ''));
        if ($alias === '') { $err = 'Alias tidak valid.'; }
        elseif ($do === 'activate')   { $m->prepare("UPDATE businesses SET active=1, paid_until=IFNULL(paid_until, DATE_ADD(CURDATE(), INTERVAL 30 DAY)) WHERE alias=?")->execute([$alias]); $msg = "Usaha \"$alias\" diaktifkan — trial 30 hari dimulai. ✓"; }
        elseif ($do === 'set_wa') {
            $wa2 = norm_wa($_POST['wa'] ?? '');
            if ($wa2 === '') { $err = 'Nomor WhatsApp tidak valid (mis. 0812xxxxxxxx).'; }
            else { $m->prepare("UPDATE businesses SET wa=? WHERE alias=?")->execute([$wa2, $alias]); $msg = "Nomor WhatsApp usaha \"$alias\" disimpan. ✓"; }
        }
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
if ($isAdmin && in_array($do, ['save_settings','approve','reject','extend','test_mail'], true)) {
    if (!isset($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf'])) { $err = 'Sesi kadaluarsa, ulangi.'; }
    elseif ($do === 'test_mail') {
        [$okMail, $infoMail] = run_mail_test();
        if ($okMail) $msg = $infoMail; else $err = $infoMail;
    }
    elseif ($do === 'save_settings') {
        foreach (['price_1bln','price_3bln','price_1thn'] as $k) setting_set($k, max(0, (int)preg_replace('/\D/', '', $_POST[$k] ?? '0')));
        setting_set('bank_info', mb_substr(trim($_POST['bank_info'] ?? ''), 0, 1000));
        setting_set('uniq_on', empty($_POST['uniq_on']) ? '0' : '1');
        $ne = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['notify_email'] ?? ''))), function ($x) { return filter_var($x, FILTER_VALIDATE_EMAIL); })));
        setting_set('notify_email', implode(',', array_slice($ne, 0, 5)));
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
$rows = $isAdmin ? $m->query("SELECT b.alias,b.name,b.db_name,b.active,b.created,b.paid_until,b.wa,
    DATEDIFF(b.paid_until, CURDATE()) AS days_left,
    (SELECT email FROM users u WHERE u.alias=b.alias AND u.role='owner' LIMIT 1) AS owner_email,
    (SELECT COUNT(*) FROM users u WHERE u.alias=b.alias) AS user_count
    FROM businesses b ORDER BY b.active ASC, b.created DESC")->fetchAll() : [];
$poolTotal = $isAdmin ? count(pool_names()) : 0; $poolFree = ($isAdmin && $poolTotal) ? pool_free_count($m) : 0;
$pending = 0; $recent = 0;
foreach ($rows as $r) { if ((int)$r['active'] === 0) $pending++; if (strtotime((string)$r['created']) >= strtotime('-7 days')) $recent++; }
// --- turunan murah untuk tampilan (dihitung dari $rows yang sudah ada, tanpa query baru) ---
$tot = count($rows); $actN = 0; $soonN = 0; $expN = 0;
foreach ($rows as $r) {
    if ((int)$r['active'] === 1) {
        $actN++;
        if ($r['paid_until'] !== null) { $dd0 = (int)$r['days_left']; if ($dd0 < 0) $expN++; elseif ($dd0 <= 7) $soonN++; }
    }
}
$poolUsed = max(0, $poolTotal - $poolFree);

// Tombol kontak per usaha: WhatsApp (bila nomor tersimpan) atau isian nomor + email (bila belum).
function wa_actions($r, $on, $csrf) {
    ob_start(); ?>
              <?php
  $dl = $r['paid_until'] !== null ? (int)$r['days_left'] : null;
  if (!$on) $waMsg = "Halo 🙏 Terima kasih sudah mendaftar di Racikin (usaha \"{$r['name']}\", kode {$r['alias']}).\n\nMohon maaf baru kami hubungi. Akunmu sedang kami siapkan dan segera aktif. Kami kabari lagi begitu siap ya. Terima kasih 🙏";
  elseif ($dl === null) $waMsg = "Halo 🙏 Ini dari Racikin. Usaha \"{$r['name']}\" sudah aktif dan siap dipakai di https://login.racikin.com . Kalau ada kendala, balas pesan ini ya 🙏";
  else $waMsg = "Halo 🙏 Pengingat dari Racikin.\n\nLangganan usaha \"{$r['name']}\" "
    . ($dl<0 ? "sudah habis pada {$r['paid_until']}" : ($dl<=0 ? "habis hari ini" : "akan habis dalam {$dl} hari ({$r['paid_until']})"))
    . ".\n\nSilakan perpanjang lewat aplikasi (pilih paket → transfer → upload bukti) supaya bisa terus dipakai. Terima kasih 🙏";
  $waLabel = !$on ? '💬 Hubungi' : '💬 Ingatkan'; ?>
<?php if (!empty($r['wa'])): ?>
  <a class="btn sm wa" href="https://wa.me/<?= h($r['wa']) ?>?text=<?= rawurlencode($waMsg) ?>" target="_blank" rel="noopener" title="Kirim via WhatsApp ke +<?= h($r['wa']) ?>"><?= $waLabel ?></a>
<?php else: ?>
  <form method="post" class="wa-set" title="Nomor WhatsApp belum tersimpan — isi lalu simpan">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="set_wa"><input type="hidden" name="alias" value="<?= h($r['alias']) ?>">
    <input type="tel" name="wa" inputmode="tel" placeholder="No. WA 0812…" aria-label="Nomor WhatsApp <?= h($r['alias']) ?>" required>
    <button class="btn sm wa">Simpan</button>
  </form>
  <?php if (!empty($r['owner_email'])): ?><a class="btn sm" href="mailto:<?= h($r['owner_email']) ?>?subject=<?= rawurlencode('Racikin — ' . $r['name']) ?>&body=<?= rawurlencode($waMsg) ?>" title="Kirim email (nomor WA belum ada)">✉️ Email</a><?php endif; ?>
<?php endif; ?>
    <?php return ob_get_clean();
}
?>
<!doctype html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#D53E0F">
<title>Admin — Racikin</title>
<style>
:root{--red:#D53E0F;--red-d:#7E0306;--orange:#F26A2E;--orange-l:#FA8743;--cream:#FFF8F3;--bg:#FBEEE7;--ink:#2b2b2b;--muted:#8a8f99;--line:#f0e6e0;--green:#1E8449;--amber:#C2651B;--blue:#2F6FDE;--grad:linear-gradient(135deg,#F26A2E 0%,#D53E0F 55%,#7E0306 100%);--sh:0 1px 2px rgba(126,3,6,.05),0 10px 30px rgba(126,3,6,.10)}
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%}
body{font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:var(--cream) linear-gradient(170deg,#FFF8F3 0%,#FCE9DC 100%) fixed;color:var(--ink);min-height:100vh;padding:18px 14px 48px;overflow-x:hidden}
.wrap{max-width:1080px;margin:0 auto}
h1,h2,h3{letter-spacing:-.02em}
a{color:inherit}
/* ---- kartu & tombol ---- */
.card{background:#fff;border-radius:26px;padding:22px;box-shadow:var(--sh);margin-bottom:18px}
.card-h{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.card-h h2{font-size:18px;font-weight:800;display:flex;align-items:center;gap:10px}
.ico{width:36px;height:36px;border-radius:12px;background:linear-gradient(135deg,#FFE9DB,#FFD9C2);display:inline-flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.count{font-size:13px;color:var(--muted)}
.btn{background:var(--grad);color:#fff;border:none;padding:11px 20px;border-radius:999px;font-weight:700;font-size:14px;font-family:inherit;cursor:pointer;box-shadow:0 6px 16px rgba(213,62,15,.28);transition:transform .15s,box-shadow .15s,filter .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1.2}
.btn:hover{transform:translateY(-1px);filter:brightness(1.06)}
.btn:active{transform:translateY(0) scale(.98)}
.btn:focus-visible,.chip:focus-visible,.stat:focus-visible,input:focus-visible,textarea:focus-visible,.logout:focus-visible{outline:3px solid rgba(213,62,15,.35);outline-offset:2px}
.btn.sm{padding:7px 14px;font-size:12.5px;box-shadow:none}
.btn.gray{background:#f1ece9;color:#555;box-shadow:none}
.btn.green{background:linear-gradient(135deg,#2ea35e,#1E8449);box-shadow:0 6px 16px rgba(30,132,73,.25)}
.btn.sm.green{box-shadow:none}
.btn.amber{background:#fdeede;color:var(--amber);box-shadow:none}
.btn.del{background:#fbe7e5;color:var(--red);box-shadow:none}
.btn.plus{background:#fff;color:var(--red);box-shadow:inset 0 0 0 1.5px #f3c9b8}
.btn.plus:hover{background:#fff3ec}
.btn.wa{background:#25D366;color:#fff;box-shadow:none}
.btn.block{width:100%;padding:13px 20px;font-size:15px}
/* ---- header ---- */
.top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:21px;letter-spacing:-.02em}
.brand img{width:42px;height:42px;object-fit:contain;filter:drop-shadow(0 4px 8px rgba(194,58,11,.25))}
.brand small{font-size:11px;font-weight:800;letter-spacing:.06em;color:#fff;background:var(--grad);padding:3px 10px;border-radius:999px}
.logout{color:var(--red);font-weight:700;font-size:13px;text-decoration:none;background:#fff;padding:9px 16px;border-radius:999px;box-shadow:var(--sh);transition:transform .15s}
.logout:hover{transform:translateY(-1px)}
.msg{padding:12px 16px;border-radius:18px;margin-bottom:16px;font-size:14px;font-weight:600;display:flex;gap:10px;align-items:flex-start;animation:aUp .45s both}
.msg.ok{background:#e6f5ec;color:var(--green)}.msg.err{background:#fbe7e5;color:var(--red)}
/* ---- hero ---- */
.hero{position:relative;overflow:hidden;border-radius:30px;padding:26px 24px 24px;color:#fff;background:var(--grad);box-shadow:0 22px 50px rgba(126,3,6,.28);margin-bottom:18px}
.hero::before,.hero::after{content:"";position:absolute;border-radius:50%;pointer-events:none}
.hero::before{width:300px;height:300px;right:-90px;top:-130px;background:radial-gradient(circle,rgba(255,255,255,.22),rgba(255,255,255,0) 70%)}
.hero::after{width:240px;height:240px;left:-80px;bottom:-130px;background:radial-gradient(circle,rgba(255,196,150,.28),rgba(255,196,150,0) 70%)}
.hero>*{position:relative;z-index:1}
.hero h1{font-size:25px;font-weight:800;line-height:1.15}
.hero .sub{font-size:13.5px;opacity:.88;margin-top:4px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:20px}
.stat{display:block;text-align:left;font-family:inherit;color:#fff;border:none;cursor:default;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);border-radius:20px;padding:14px 14px 12px;-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);animation:aUp .6s cubic-bezier(.2,.8,.2,1) both;animation-delay:calc(var(--i,0)*70ms + 120ms);transition:transform .2s,background .2s}
button.stat{cursor:pointer}
button.stat:hover{transform:translateY(-2px);background:rgba(255,255,255,.22)}
.stat .em{font-size:18px;display:inline-flex;width:30px;height:30px;border-radius:10px;background:rgba(255,255,255,.18);align-items:center;justify-content:center}
.stat .n{display:block;font-size:34px;font-weight:800;letter-spacing:-.03em;line-height:1.05;margin-top:8px;font-variant-numeric:tabular-nums}
.stat .n small{font-size:15px;font-weight:700;opacity:.8;margin-left:2px}
.stat .l{display:block;font-size:12px;opacity:.9;margin-top:2px;font-weight:600}
.stat.hot{background:#fff;color:var(--red);border-color:#fff;box-shadow:0 10px 26px rgba(0,0,0,.18)}
.stat.hot .em{background:#fde7e4}
.stat.hot .n{color:var(--red-d)}
.stat.hot .em{animation:aPulse 1.8s ease-in-out infinite}
.stat{position:relative}
/* ---- banner kolam ---- */
.pool{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:22px;margin-bottom:18px;font-size:13.5px;line-height:1.5;animation:aUp .5s both}
.pool.ok{background:#e9f6ee;color:#17623a}.pool.err{background:#fbe7e5;color:#a8300a}
.pool .pe{font-size:22px;width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.7);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.pool .pb{flex:1;min-width:0}
.pool .bar{height:8px;border-radius:99px;background:rgba(0,0,0,.08);margin-top:8px;overflow:hidden}
.pool .bar i{display:block;height:100%;border-radius:99px;background:currentColor;transform-origin:left;animation:aGrow .9s cubic-bezier(.2,.8,.2,1) both}
code{background:rgba(0,0,0,.07);padding:1px 6px;border-radius:6px;font-size:12px}
/* ---- bukti transfer ---- */
.rq{display:flex;gap:16px;padding:16px;border-radius:22px;background:var(--cream);border:1px solid var(--line);margin-bottom:12px;animation:aUp .5s both}
.rq:last-child{margin-bottom:0}
.rq-img{width:96px;height:96px;object-fit:cover;border-radius:18px;border:1px solid var(--line);flex-shrink:0;cursor:zoom-in;background:#fff}
.rq-img.noimg{display:flex;align-items:center;justify-content:center;text-align:center;font-size:11px;color:var(--muted);background:#f6f2f0;cursor:default;padding:6px}
.rq-main{flex:1;min-width:0}
.rq-t{font-size:15px;word-break:break-word}
.rq-amt{margin-top:8px;font-size:13.5px;color:#555}.rq-amt b{color:var(--red);font-size:19px;letter-spacing:-.01em;font-variant-numeric:tabular-nums}
.empty{text-align:center;color:var(--muted);padding:26px 14px}
.empty .big{font-size:42px;display:block;margin-bottom:6px;animation:aBob 3.6s ease-in-out infinite}
.empty b{display:block;color:var(--ink);font-size:15px;margin-bottom:2px}
.acts{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.dbn{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;background:#f6f2f0;padding:2px 8px;border-radius:999px;color:#6b5c55;word-break:break-all}
/* ---- pengaturan ---- */
details.card>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px;letter-spacing:-.02em}
details.card>summary::-webkit-details-marker{display:none}
details.card>summary::after{content:"▾";margin-left:auto;color:var(--muted);transition:transform .25s;font-size:16px}
details[open].card>summary::after{transform:rotate(180deg)}
details.card .sbody{animation:aUp .4s both}
.setgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px}
label.fl{font-size:12px;color:var(--muted);display:block;margin-bottom:5px;font-weight:700}
.rpf{position:relative}
.rpf::before{content:"Rp";position:absolute;left:16px;top:50%;transform:translateY(-50%);font-weight:800;font-size:13px;color:var(--red);pointer-events:none}
.rpf input{padding-left:42px!important}
.prev{display:block;font-size:12px;color:var(--muted);margin:5px 0 0 6px;font-variant-numeric:tabular-nums;min-height:16px}
.prev b{color:var(--red)}
input[type=password],input[inputmode=numeric],textarea{width:100%;padding:12px 16px;border:1.5px solid #eadfd8;border-radius:999px;font-size:15px;font-family:inherit;background:#fffdfb;color:var(--ink);transition:border-color .2s,box-shadow .2s,background .2s}
textarea{border-radius:20px;resize:vertical;font-size:14px;line-height:1.5}
input:focus,textarea:focus{outline:none;border-color:var(--red);background:#fff;box-shadow:0 0 0 4px rgba(213,62,15,.12)}
.ck{display:flex;align-items:center;gap:10px;margin-top:14px;font-size:13px;background:var(--cream);border:1px solid var(--line);padding:11px 16px;border-radius:999px;cursor:pointer}
.ck input{width:18px;height:18px;accent-color:var(--red);flex-shrink:0}
/* ---- daftar usaha ---- */
.tools{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.wa-set{display:inline-flex;align-items:center;gap:6px;margin:0}
.wa-set input{width:132px;padding:7px 12px;border-radius:999px;border:1.5px solid #eadfd8;font-size:12.5px;font-family:inherit;background:#fffdfb}
.wa-set input:focus{outline:none;border-color:var(--red);box-shadow:0 0 0 3px rgba(213,62,15,.12)}
@media (max-width:640px){.wa-set{flex:1 1 100%}.wa-set input{flex:1;width:auto;min-width:0}}
.search{position:relative;flex:1;min-width:200px}
.search::before{content:"🔍";position:absolute;left:15px;top:50%;transform:translateY(-50%);font-size:14px;pointer-events:none}
.search input{padding-left:40px;padding-top:11px;padding-bottom:11px;border-radius:999px;border:1.5px solid #eadfd8;width:100%;font-size:14px;font-family:inherit;background:#fffdfb}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{border:none;font-family:inherit;cursor:pointer;background:#f6efea;color:#6b5c55;font-weight:700;font-size:12.5px;padding:8px 14px;border-radius:999px;transition:background .2s,color .2s,transform .15s}
.chip:hover{transform:translateY(-1px)}
.chip b{font-variant-numeric:tabular-nums;opacity:.7;margin-left:3px}
.chip.on{background:var(--grad);color:#fff;box-shadow:0 6px 14px rgba(213,62,15,.25)}
.ulist{display:flex;flex-direction:column;gap:10px}
.urow{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr) auto;gap:14px 18px;align-items:center;padding:14px 16px;border-radius:22px;border:1px solid var(--line);background:#fff;transition:box-shadow .2s,transform .2s;animation:aUp .5s both;animation-delay:calc(var(--i,0)*35ms)}
.urow:hover{box-shadow:0 10px 26px rgba(126,3,6,.10)}
.urow.pend{background:linear-gradient(135deg,#FFF3E8,#FFE9DA);border-color:#f7c9a8;box-shadow:0 8px 22px rgba(242,106,46,.15)}
.urow[hidden]{display:none}
.who{display:flex;gap:12px;align-items:center;min-width:0}
.av{width:46px;height:46px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:19px;color:#fff;flex-shrink:0;background:hsl(var(--h) 62% 50%);box-shadow:0 6px 14px hsl(var(--h) 62% 50%/.3)}
.who .nm{font-size:15px;font-weight:800;word-break:break-word;line-height:1.25}
.who .em2{font-size:12.5px;color:#6b5c55;word-break:break-all;margin-top:2px}
.who .mt{font-size:11.5px;color:var(--muted);margin-top:3px}
.who .cd{margin-top:5px;display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:10.5px;font-weight:800;letter-spacing:.03em}
.pill.on{background:#e6f5ec;color:var(--green)}.pill.off{background:#fdeede;color:var(--amber)}
.pill.soon{background:#fff0d6;color:#a35a00}.pill.exp{background:#fbe7e5;color:var(--red)}.pill.new{background:#fde7e4;color:var(--red);margin-left:6px;vertical-align:middle}
.pill.inf{background:#e8f0fd;color:var(--blue)}
.stt .tx{font-size:12.5px;margin-top:8px;color:#6b5c55;line-height:1.4}
.stt .tx b{font-weight:800}
.meter{height:8px;border-radius:99px;background:#f1e9e4;margin-top:8px;overflow:hidden}
.meter i{display:block;height:100%;border-radius:99px;transform-origin:left;animation:aGrow .9s cubic-bezier(.2,.8,.2,1) both}
.meter i.g{background:linear-gradient(90deg,#46c07a,#1E8449)}.meter i.a{background:linear-gradient(90deg,#f6b15a,#C2651B)}.meter i.r{background:linear-gradient(90deg,#f2705a,#D53E0F)}.meter i.b{background:linear-gradient(90deg,#8db4f5,#2F6FDE);opacity:.55}
.agrp{display:flex;flex-direction:column;gap:8px;align-items:flex-end}
.arow{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.arow form{display:flex;gap:6px;flex-wrap:wrap}
.sep{width:1px;height:20px;background:var(--line);margin:0 2px}
.noresult{display:none;text-align:center;color:var(--muted);padding:26px 10px}
.noresult.show{display:block}
.noresult .big{font-size:36px;display:block;margin-bottom:4px}
/* ---- panduan ---- */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:4px}
.step{position:relative;background:var(--cream);border:1px solid var(--line);border-radius:22px;padding:16px 16px 14px;font-size:13px;line-height:1.55;color:#4a4340}
.step .no{width:30px;height:30px;border-radius:50%;background:var(--grad);color:#fff;font-weight:800;font-size:14px;display:flex;align-items:center;justify-content:center;margin-bottom:8px;box-shadow:0 6px 12px rgba(213,62,15,.25)}
.step b{color:var(--ink)}
/* ---- login ---- */
body.lg{display:flex;align-items:center;justify-content:center;padding:20px;background:linear-gradient(160deg,#FFF4EC 0%,#FCE0CF 50%,#F6D6C6 100%);background-size:220% 220%;animation:aBg 22s ease-in-out infinite}
body.lg::before,body.lg::after{content:"";position:fixed;border-radius:50%;filter:blur(70px);pointer-events:none;opacity:.55}
body.lg::before{width:52vmax;height:52vmax;left:-14vmax;top:-18vmax;background:radial-gradient(circle,#FFB27A,rgba(255,178,122,0) 70%)}
body.lg::after{width:46vmax;height:46vmax;right:-14vmax;bottom:-16vmax;background:radial-gradient(circle,#F2705A,rgba(242,112,90,0) 70%)}
.loginbox{position:relative;z-index:1;width:100%;max-width:400px;text-align:center;background:rgba(255,255,255,.93);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.8);border-radius:30px;padding:30px 26px 26px;box-shadow:0 30px 70px rgba(126,3,6,.20),0 2px 0 rgba(255,255,255,.9) inset;animation:aUp .7s cubic-bezier(.2,.8,.2,1) both;margin:0}
.loginbox img{width:78px;height:78px;object-fit:contain;filter:drop-shadow(0 8px 14px rgba(194,58,11,.28));animation:aBob 4.6s ease-in-out infinite}
.loginbox h2{font-size:22px;font-weight:800;margin:6px 0 2px}
.loginbox .lsub{color:var(--muted);font-size:12.5px;margin-bottom:18px}
.loginbox form input{margin-bottom:14px;text-align:center}
.loginbox .msg{text-align:left;font-size:13px;border-radius:16px}
.loginbox small.ft{display:block;margin-top:14px;font-size:11.5px;color:var(--muted)}
.hint{font-size:12px;color:var(--muted);line-height:1.5}
/* ---- animasi ---- */
@keyframes aUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes aGrow{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes aBob{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
@keyframes aPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.14)}}
@keyframes aBg{0%,100%{background-position:0 0}50%{background-position:100% 100%}}
/* ---- responsif ---- */
@media(max-width:860px){
  .urow{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
  .agrp{grid-column:1/-1;align-items:flex-start;border-top:1px dashed var(--line);padding-top:12px}
  .arow{justify-content:flex-start}
  .steps{grid-template-columns:1fr}
}
@media(max-width:640px){
  body{padding:12px 12px 40px}
  .card{padding:16px;border-radius:24px}
  .hero{padding:20px 16px 16px;border-radius:26px}
  .hero h1{font-size:21px}
  .stats{grid-template-columns:repeat(2,1fr);gap:8px}
  .stat{padding:12px}
  .stat .n{font-size:28px}
  .setgrid{grid-template-columns:1fr}
  .rq{flex-direction:column;gap:12px}
  .rq-img{width:100%;height:150px}
  .rq-img.noimg{height:60px}
  .urow{grid-template-columns:1fr;gap:12px}
  .agrp{grid-column:auto}
  .tools{flex-direction:column;align-items:stretch;min-width:0}
  .tools>*,.search{min-width:0;max-width:100%;width:100%}
  .chips{max-width:100%}
  .chips{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px;margin:0 -2px;scrollbar-width:none}
  .chips::-webkit-scrollbar{display:none}
  .chip{flex-shrink:0}
  .brand{font-size:19px}
}
@media(max-width:480px){
  .arow .btn.sm{padding:8px 13px}
  .rq .acts>form{flex:1}.rq .acts .btn{width:100%}
  .pool{flex-direction:row;padding:12px}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
</style></head><body<?= $isAdmin ? '' : ' class="lg"' ?>>

<?php if (!$isAdmin): ?>
  <div class="card loginbox">
    <img src="icons/logo-bowl.png" alt="">
    <h2>Panel Admin Racikin</h2>
    <div class="lsub">Masuk dulu ya, untuk mengelola usaha &amp; langganan 🔐</div>
    <?php if ($err): ?><div class="msg err"><span>⚠️</span><span><?= h($err) ?></span></div><?php endif; ?>
    <?php if (!$adminConfigured): ?><div class="msg err"><span>⚙️</span><span>Set <b>ADMIN_PASS</b> yang kuat di <b>config.php</b> dulu.</span></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="do" value="login">
      <input type="password" name="pass" placeholder="Password admin" autofocus>
      <button class="btn block">Masuk</button>
    </form>
    <small class="ft">Halaman ini khusus pengelola Racikin.</small>
  </div>
<?php else: ?>
<div class="wrap">
  <div class="top">
    <div class="brand"><img src="icons/logo-bowl.png" alt="">Racikin<small>ADMIN</small></div>
    <a class="logout" href="admin.php?logout=1">Keluar ⏻</a>
  </div>
  <?php if ($msg): ?><div class="msg ok"><span>✅</span><span><?= h($msg) ?></span></div><?php endif; ?>
  <?php if ($err): ?><div class="msg err"><span>⚠️</span><span><?= h($err) ?></span></div><?php endif; ?>

  <!-- Hero ringkasan -->
  <section class="hero">
    <h1>Halo, Admin! 👋</h1>
    <div class="sub"><?php if ($pending > 0 || count($reqs) > 0): ?>Ada <b><?= $pending ?></b> usaha menunggu aktivasi dan <b><?= count($reqs) ?></b> bukti transfer menunggu verifikasi.<?php else: ?>Semua beres — tidak ada yang menunggu. Santai dulu ☕<?php endif; ?></div>
    <div class="stats">
      <div class="stat" style="--i:0"><span class="em">🏪</span><span class="n" data-n="<?= $tot ?>"><?= $tot ?></span><span class="l">Total usaha</span></div>
      <div class="stat" style="--i:1"><span class="em">✅</span><span class="n" data-n="<?= $actN ?>"><?= $actN ?></span><span class="l">Aktif</span></div>
      <button type="button" class="stat<?= $pending > 0 ? ' hot' : '' ?>" style="--i:2" data-go="pending" title="Lihat yang menunggu aktivasi"><span class="em">⏳</span><span class="n" data-n="<?= $pending ?>"><?= $pending ?></span><span class="l">Menunggu aktivasi</span></button>
      <div class="stat" style="--i:3"><span class="em">🆕</span><span class="n" data-n="<?= $recent ?>"><?= $recent ?></span><span class="l">Baru (7 hari)</span></div>
      <button type="button" class="stat" style="--i:4" data-go="<?= $expN > 0 && $soonN == 0 ? 'expired' : 'soon' ?>" title="Lihat yang hampir/sudah habis"><span class="em">⏰</span><span class="n"><span data-n="<?= $soonN ?>"><?= $soonN ?></span><small>/</small><span data-n="<?= $expN ?>"><?= $expN ?></span></span><span class="l">Hampir habis / habis</span></button>
      <div class="stat" style="--i:5"><span class="em">🗄️</span><?php if ($poolTotal): ?><span class="n"><span data-n="<?= $poolFree ?>"><?= $poolFree ?></span><small>/<?= $poolTotal ?></small></span><?php else: ?><span class="n">—</span><?php endif; ?><span class="l">Sisa kolam trial</span></div>
    </div>
  </section>

  <?php if ($poolTotal): $poolPct = (int)round($poolFree / $poolTotal * 100); ?>
  <div class="pool <?= $poolFree <= 2 ? 'err' : 'ok' ?>">
    <div class="pe"><?= $poolFree <= 2 ? '🚨' : '🌱' ?></div>
    <div class="pb">
      <b>Trial otomatis:</b> <?= $poolFree ?> dari <?= $poolTotal ?> database kolam masih kosong<?= $poolFree <= 2 ? ' — <b>segera tambah database baru di cPanel</b> lalu daftarkan di <code>TRIAL_POOL</code> (config.php). Kalau habis, pendaftar jadi PENDING.' : '.' ?>
      <div class="bar"><i style="width:<?= $poolPct ?>%"></i></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Antrian bukti transfer perpanjangan -->
  <div class="card" id="bukti">
    <div class="card-h">
      <h2><span class="ico">🧾</span>Bukti Transfer Masuk</h2>
      <span class="count"><b style="color:var(--amber)"><?= count($reqs) ?></b> menunggu verifikasi</span>
    </div>
    <?php if (!$reqs): ?>
      <div class="empty"><span class="big">📭</span><b>Belum ada pengajuan perpanjangan</b>Bukti transfer dari pelanggan akan muncul di sini.</div>
    <?php else: foreach ($reqs as $q): ?>
      <div class="rq">
        <?php if (!empty($q['proof'])): ?><a href="<?= h($q['proof']) ?>" target="_blank"><img class="rq-img" src="<?= h($q['proof']) ?>" alt="bukti"></a><?php else: ?><div class="rq-img noimg">tanpa bukti</div><?php endif; ?>
        <div class="rq-main">
          <div class="rq-t"><b><?= h($q['biz_name'] ?: $q['alias']) ?></b> <span class="dbn"><?= h($q['alias']) ?></span></div>
          <span class="count">Paket <b><?= h($q['plan']) ?></b> · diajukan <?= h(substr((string)$q['created'],0,16)) ?><?php if ($q['days_left']!==null): ?> · langganan <?= (int)$q['days_left']<0?'habis':'sisa '.(int)$q['days_left'].' hari' ?><?php endif; ?></span>
          <div class="rq-amt">Nominal harus cocok: <b>Rp<?= number_format((int)$q['amount'],0,',','.') ?></b></div>
          <?php if (!empty($q['note'])): ?><div class="count" style="margin-top:3px">Catatan: <?= h($q['note']) ?></div><?php endif; ?>
          <div class="acts" style="margin-top:12px">
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
  <details class="card"<?= (($settings['price_1bln']??'0')==='0') ? ' open' : '' ?>>
    <summary><span class="ico">⚙️</span>Pengaturan Harga &amp; Rekening</summary>
    <form method="post" class="sbody">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="save_settings">
      <div class="setgrid">
        <div><label class="fl">Harga 1 Bulan (Rp)</label><div class="rpf"><input name="price_1bln" inputmode="numeric" value="<?= (int)($settings['price_1bln']??0) ?>"></div><span class="prev"></span></div>
        <div><label class="fl">Harga 3 Bulan (Rp)</label><div class="rpf"><input name="price_3bln" inputmode="numeric" value="<?= (int)($settings['price_3bln']??0) ?>"></div><span class="prev"></span></div>
        <div><label class="fl">Harga 1 Tahun (Rp)</label><div class="rpf"><input name="price_1thn" inputmode="numeric" value="<?= (int)($settings['price_1thn']??0) ?>"></div><span class="prev"></span></div>
      </div>
      <label class="fl" style="margin-top:14px">Info Rekening / Cara Bayar (tampil ke pelanggan saat perpanjang)</label>
      <textarea name="bank_info" rows="3" placeholder="mis. BCA 1234567890 a.n. Nama Pemilik"><?= h($settings['bank_info']??'') ?></textarea>
      <label style="display:block;margin-top:14px">Email pemberitahuan pendaftar baru <span style="font-weight:400;color:var(--muted)">(boleh beberapa, pisah koma)</span></label>
      <input type="text" name="notify_email" value="<?= h($settings['notify_email'] ?? '') ?>" placeholder="mis. kamu@gmail.com" autocomplete="off" inputmode="email">
      <div class="mini" style="margin-top:6px;color:var(--muted);font-size:12px">Pengirim: <b><?= h((defined('SMTP_HOST') && SMTP_HOST !== '') ? ((defined('SMTP_FROM') && SMTP_FROM) ? SMTP_FROM : (defined('SMTP_USER') ? SMTP_USER : '-')) : 'mail() bawaan hosting (belum pakai SMTP)') ?></b> · simpan dulu, lalu klik <b>Kirim email tes</b> di bawah.</div>
      <label class="ck"><input type="checkbox" name="uniq_on" value="1" <?= ($settings['uniq_on']??'1')==='1'?'checked':'' ?>> <span>Aktifkan kode unik nominal (1–50) — memudahkan cek transfer</span></label>
      <button class="btn" style="margin-top:16px">Simpan Pengaturan</button>
    </form>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="test_mail">
      <button class="btn sm" style="background:#fff;color:var(--red);border:1.5px solid #f0c9bb">✉️ Kirim email tes</button>
    </form>
  </details>

  <div class="card" id="usaha">
    <div class="card-h">
      <h2><span class="ico">🏪</span>Daftar Usaha</h2>
      <span class="count"><?= count($rows) ?> usaha · <b style="color:var(--amber)"><?= $pending ?></b> menunggu aktivasi · <b style="color:var(--red)"><?= $recent ?></b> baru (7 hari)</span>
    </div>
    <div class="tools">
      <div class="search"><input type="search" id="q" placeholder="Cari nama, kode, atau email…" autocomplete="off" aria-label="Cari usaha"></div>
      <div class="chips" id="chips">
        <button type="button" class="chip on" data-f="all">Semua<b><?= $tot ?></b></button>
        <button type="button" class="chip" data-f="pending">Menunggu<b><?= $pending ?></b></button>
        <button type="button" class="chip" data-f="active">Aktif<b><?= $actN - $expN ?></b></button>
        <button type="button" class="chip" data-f="soon">Hampir habis<b><?= $soonN ?></b></button>
        <button type="button" class="chip" data-f="expired">Habis<b><?= $expN ?></b></button>
      </div>
    </div>
    <div class="ulist" id="ulist">
      <?php if (!$rows): ?><div class="empty"><span class="big">🌱</span><b>Belum ada usaha</b>Pendaftar baru akan muncul di sini.</div><?php endif; ?>
      <?php $ri = 0; foreach ($rows as $r): $on = (int)$r['active'] === 1; $ri++;
        $dl = $r['paid_until'] === null ? null : (int)$r['days_left'];
        if (!$on) $st = 'pending'; elseif ($dl !== null && $dl < 0) $st = 'expired'; elseif ($dl !== null && $dl <= 7) $st = 'active soon'; else $st = 'active';
        $hue = crc32((string)$r['alias']) % 360;
        $ini = mb_strtoupper(mb_substr(trim((string)$r['name']) !== '' ? trim((string)$r['name']) : (string)$r['alias'], 0, 1));
        $isNew = strtotime((string)$r['created']) >= strtotime('-7 days');
        $srch = mb_strtolower($r['name'].' '.$r['alias'].' '.$r['db_name'].' '.($r['owner_email'] ?: '')); ?>
        <div class="urow<?= !$on ? ' pend' : '' ?>" style="--i:<?= min($ri, 14) ?>" data-st="<?= h($st) ?>" data-q="<?= h($srch) ?>">
          <div class="who">
            <div class="av" style="--h:<?= (int)$hue ?>"><?= h($ini) ?></div>
            <div style="min-width:0">
              <div class="nm"><?= h($r['name']) ?><?php if ($isNew): ?><span class="pill new">BARU</span><?php endif; ?></div>
              <div class="em2">✉️ <?= h($r['owner_email'] ?: '-') ?></div>
              <?php if (!empty($r['wa'])): ?><div class="em2">💬 <a href="https://wa.me/<?= h($r['wa']) ?>" target="_blank" rel="noopener" style="color:inherit"><?= h('+' . $r['wa']) ?></a></div><?php endif; ?>
              <div class="cd"><b style="font-size:12.5px"><?= h($r['alias']) ?></b><span class="dbn"><?= h($r['db_name']) ?></span></div>
              <div class="mt"><?= (int)$r['user_count'] ?> pengguna · daftar <?= h(substr((string)$r['created'],0,10)) ?></div>
            </div>
          </div>
          <div class="stt">
            <span class="pill <?= !$on ? 'off' : ($st === 'expired' ? 'exp' : ($st === 'active soon' ? 'soon' : 'on')) ?>"><?= !$on ? 'PENDING' : ($st === 'expired' ? 'HABIS' : ($st === 'active soon' ? 'HAMPIR HABIS' : 'AKTIF')) ?></span>
            <?php if ($on): ?>
              <?php if ($r['paid_until']===null): ?>
                <div class="tx"><span class="pill inf">∞</span> <span style="color:var(--muted)">tanpa batas</span></div>
                <div class="meter"><i class="b" style="width:100%"></i></div>
              <?php else: $mp = $dl < 0 ? 100 : max(5, min(100, (int)round($dl / 30 * 100))); $mc = $dl < 0 ? 'r' : ($dl <= 7 ? 'a' : 'g'); ?>
                <div class="tx">s/d <?= h($r['paid_until']) ?><br><b style="color:<?= $dl<0?'var(--red)':($dl<=7?'var(--amber)':'var(--green)') ?>"><?= $dl<0?('habis '.abs($dl).' hr lalu'):('sisa '.$dl.' hari') ?></b></div>
                <div class="meter"><i class="<?= $mc ?>" style="width:<?= $mp ?>%"></i></div>
              <?php endif; ?>
            <?php else: ?>
              <div class="tx">Menunggu diaktifkan ⏳<br><span style="color:var(--muted);font-size:11.5px">siapkan database dulu, lalu aktifkan</span></div>
            <?php endif; ?>
          </div>
          <div class="agrp">
            <?php if ($on): ?>
            <div class="arow">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="extend"><input type="hidden" name="alias" value="<?= h($r['alias']) ?>">
                <button class="btn sm plus" name="months" value="1" title="Perpanjang 1 bulan">+1bln</button>
                <button class="btn sm plus" name="months" value="3" title="Perpanjang 3 bulan">+3bln</button>
                <button class="btn sm plus" name="months" value="12" title="Perpanjang 1 tahun">+1thn</button>
              </form>
              <?= wa_actions($r, $on, $csrf) ?>
            </div>
            <?php endif; ?>
            <div class="arow">
            <?php if (!$on): ?>
              <form method="post" onsubmit="return confirm('Pastikan database <?= h($r['db_name']) ?> sudah dibuat di cPanel + user MySQL sudah di-assign. Aktifkan &amp; mulai TRIAL 30 hari gratis?')">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="do" value="activate"><input type="hidden" name="alias" value="<?= h($r['alias']) ?>">
                <button class="btn sm green" title="Aktifkan + mulai trial 30 hari gratis">✓ Aktifkan (Trial 30hr)</button>
              </form>
              <?= wa_actions($r, $on, $csrf) ?>
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
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="noresult" id="noresult"><span class="big">🔎</span><b>Tidak ada usaha yang cocok</b><br>Coba kata kunci atau filter lain.</div>
    </div>
  </div>

  <div class="card">
    <div class="card-h"><h2><span class="ico">🧭</span>Alur aktivasi (setelah pembayaran)</h2></div>
    <div class="steps">
      <div class="step"><div class="no">1</div>Di cPanel → MySQL Databases → buat database persis bernama seperti di kolom <b>DB</b> (mis. <span class="dbn">racikin_tokobudi</span>).</div>
      <div class="step"><div class="no">2</div>Add User to Database → tambahkan user MySQL utamamu ke DB itu (<b>ALL PRIVILEGES</b>).</div>
      <div class="step"><div class="no">3</div>Kembali ke sini → klik <b>✓ Aktifkan</b>. Pelanggan langsung bisa login. Tabel dibuat otomatis saat login pertama.</div>
    </div>
  </div>
</div>

<script>
(function(){
  var reduce=window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches;
  // hitung-naik angka hero
  document.querySelectorAll('[data-n]').forEach(function(el){
    var to=parseInt(el.getAttribute('data-n'),10)||0;
    if(reduce||to===0)return;
    var t0=null,dur=900;
    el.textContent='0';
    function f(t){if(t0===null)t0=t;var p=Math.min(1,(t-t0)/dur);p=1-Math.pow(1-p,3);el.textContent=Math.round(to*p);if(p<1)requestAnimationFrame(f);}
    requestAnimationFrame(f);
  });
  // pratinjau format ribuan harga (nilai yang dikirim tetap angka biasa)
  document.querySelectorAll('.rpf input').forEach(function(inp){
    var pv=inp.parentNode.nextElementSibling;
    function u(){var n=parseInt(String(inp.value).replace(/\D/g,''),10)||0;pv.innerHTML=n>0?'= <b>Rp '+n.toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.')+'</b>':'';}
    inp.addEventListener('input',u);u();
  });
  // pencarian + filter daftar usaha
  var rows=[].slice.call(document.querySelectorAll('#ulist .urow')),q=document.getElementById('q'),chips=[].slice.call(document.querySelectorAll('#chips .chip')),nr=document.getElementById('noresult'),cur='all';
  function apply(){
    var s=(q.value||'').toLowerCase().trim(),shown=0;
    rows.forEach(function(r){
      var ok=(cur==='all'||(' '+r.getAttribute('data-st')+' ').indexOf(' '+cur+' ')>-1)&&(!s||r.getAttribute('data-q').indexOf(s)>-1);
      r.hidden=!ok;if(ok)shown++;
    });
    if(nr)nr.classList.toggle('show',rows.length>0&&shown===0);
  }
  function setF(f){cur=f;chips.forEach(function(c){c.classList.toggle('on',c.getAttribute('data-f')===f);});apply();}
  chips.forEach(function(c){c.addEventListener('click',function(){setF(c.getAttribute('data-f'));});});
  if(q)q.addEventListener('input',apply);
  document.querySelectorAll('[data-go]').forEach(function(b){b.addEventListener('click',function(){
    setF(b.getAttribute('data-go'));
    var t=document.getElementById('usaha');if(t&&t.scrollIntoView)t.scrollIntoView({behavior:reduce?'auto':'smooth',block:'start'});
  });});
})();
</script>
<?php endif; ?>
</body></html>
