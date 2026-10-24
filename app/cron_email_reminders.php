<?php
// ============ CRON: Pengingat langganan via EMAIL ke klien ============
// Jalankan sekali sehari (cPanel → Cron Jobs, mis. tiap hari jam 08.00):
//   /usr/local/bin/php /home/USER/.../app/cron_email_reminders.php
// Uji tanpa kirim:  php cron_email_reminders.php --dry
// Mengirim email ke pemilik usaha saat masa langganan tinggal REMINDER_EMAIL_DAYS (default 14 hari). Satu kali per masa langganan.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require __DIR__ . '/db.php';
require __DIR__ . '/reminders.php';
$dry = in_array('--dry', $argv ?? [], true);
$GLOBALS['smtp_io_timeout'] = 90;   // cron tak dibatasi waktu: beri server mail waktu lega (DNS lambat)
@set_time_limit(0);
$res = send_expiry_reminders(master_pdo(), $dry);
echo ($dry ? "[DRY-RUN] " : "") . "Terkirim: " . count($res['sent']) . " | Dilewati (sudah pernah): " . count($res['skipped']) . " | Gagal: " . count($res['failed']) . "\n";
foreach (['sent' => 'KIRIM', 'failed' => 'GAGAL', 'skipped' => 'LEWAT'] as $k => $lab)
    foreach ($res[$k] as $x) echo "  [$lab] {$x['alias']} berakhir {$x['until']} ({$x['days']} hr)" . (!empty($x['to']) ? ' → ' . implode(',', $x['to']) : '') . (isset($x['why']) ? " — {$x['why']}" : '') . "\n";
if (!$dry) email_reminder_summary($res);
