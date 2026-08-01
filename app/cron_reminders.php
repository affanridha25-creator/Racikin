<?php
// ============ CRON: Pengingat langganan via Push (FCM) ============
// Jalankan sekali sehari (cPanel → Cron Jobs):
//   /usr/local/bin/php /home/USER/racikin/app/cron_reminders.php
// Mengirim notifikasi ke HP pemilik usaha saat langganan mendekati/melewati jatuh tempo.
// Aman: jika Firebase belum dikonfigurasi (FIREBASE_SA), skrip tidak melakukan apa-apa.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require __DIR__ . '/push.php';

if (!fcm_configured()) { fwrite(STDERR, "Firebase belum dikonfigurasi (FIREBASE_SA). Lewati.\n"); exit(0); }

$m = master_pdo();
// usaha aktif dengan paid_until dalam 7 hari ke depan .. 1 hari lewat
$rows = $m->query("SELECT alias, name, paid_until, DATEDIFF(paid_until, CURDATE()) AS days_left
                   FROM businesses
                   WHERE active=1 AND paid_until IS NOT NULL
                     AND DATEDIFF(paid_until, CURDATE()) BETWEEN -1 AND 7")->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($rows as $r) {
    $d = (int)$r['days_left'];
    // hanya pada penanda tertentu supaya tak spam tiap hari
    if (!in_array($d, [7, 3, 1, 0, -1], true)) continue;
    $tokens = push_tokens_for($r['alias']);
    if (!$tokens) continue;
    $body = $d < 0 ? "Langganan sudah berakhir kemarin. Perpanjang agar aplikasi bisa dipakai lagi."
          : ($d === 0 ? "Langganan berakhir hari ini. Yuk perpanjang sekarang."
                      : "Langganan akan berakhir dalam {$d} hari ({$r['paid_until']}). Perpanjang lebih awal ya.");
    $n = fcm_send($tokens, "Pengingat Langganan Racikin", $body, ['type'=>'subscription','alias'=>$r['alias']]);
    $total += $n;
    echo "[{$r['alias']}] hari={$d} token=" . count($tokens) . " terkirim={$n}\n";
}
echo "Selesai. Total notifikasi terkirim: {$total}\n";
