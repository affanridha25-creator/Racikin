<?php
// ============ PUSH NOTIFICATION (FCM HTTP v1) ============
// Fungsi bersama (dipakai api.php + cron_reminders.php). Tanpa side-effect.
// Aktif bila config.php mengisi FIREBASE_SA (path service-account.json dari Firebase Console)
// + FIREBASE_PROJECT_ID. Tanpa itu, semua fungsi no-op (return 0/false) — aman.
require_once __DIR__ . '/db.php';

function fcm_configured() {
    return defined('FIREBASE_SA') && FIREBASE_SA && is_readable(FIREBASE_SA)
        && defined('FIREBASE_PROJECT_ID') && FIREBASE_PROJECT_ID && function_exists('openssl_sign') && function_exists('curl_init');
}

// Tukar service account → OAuth access token (JWT RS256). Di-cache dalam proses.
function fcm_access_token() {
    static $cache = null; if ($cache && $cache['exp'] > time() + 60) return $cache['tok'];
    if (!fcm_configured()) return null;
    $sa = json_decode(@file_get_contents(FIREBASE_SA), true);
    if (!$sa || empty($sa['client_email']) || empty($sa['private_key'])) { error_log('fcm: service account tidak valid'); return null; }
    $now = time();
    $claim = ['iss'=>$sa['client_email'], 'scope'=>'https://www.googleapis.com/auth/firebase.messaging',
              'aud'=>($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token'), 'iat'=>$now, 'exp'=>$now+3600];
    $b64 = function ($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); };
    $jwtUnsigned = $b64(json_encode(['alg'=>'RS256','typ'=>'JWT'])) . '.' . $b64(json_encode($claim));
    $sig = ''; if (!openssl_sign($jwtUnsigned, $sig, $sa['private_key'], 'sha256WithRSAEncryption')) return null;
    $jwt = $jwtUnsigned . '.' . $b64($sig);
    $ch = curl_init($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>15,
        CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt])]);
    $res = curl_exec($ch); curl_close($ch);
    $j = json_decode($res, true);
    if (empty($j['access_token'])) { error_log('fcm token: '.$res); return null; }
    $cache = ['tok'=>$j['access_token'], 'exp'=>$now + (int)($j['expires_in'] ?? 3600)];
    return $cache['tok'];
}

// Kirim notif ke daftar token. Return jumlah token terkirim. Token invalid dibersihkan dari DB.
function fcm_send(array $tokens, $title, $body, array $data = []) {
    $tokens = array_values(array_unique(array_filter($tokens)));
    if (!$tokens || !fcm_configured()) return 0;
    $access = fcm_access_token(); if (!$access) return 0;
    $url = 'https://fcm.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/messages:send';
    $sent = 0; $dead = [];
    foreach ($tokens as $t) {
        $msg = ['message'=>['token'=>$t,
            'notification'=>['title'=>(string)$title, 'body'=>(string)$body],
            'data'=>array_map('strval', $data),
            'android'=>['priority'=>'high', 'notification'=>['channel_id'=>'racikin']]]];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>15,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$access, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS=>json_encode($msg)]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code === 200) $sent++;
        elseif ($code === 404 || $code === 400) $dead[] = $t;   // token kadaluarsa/tak valid → buang
    }
    if ($dead) { $q = master_pdo()->prepare("DELETE FROM push_tokens WHERE token=?"); foreach ($dead as $d) $q->execute([$d]); }
    return $sent;
}

// Semua token push milik satu usaha (kirim pengingat ke pemilik/staf usaha itu).
function push_tokens_for($alias) {
    $q = master_pdo()->prepare("SELECT token FROM push_tokens WHERE alias=?");
    $q->execute([$alias]); return $q->fetchAll(PDO::FETCH_COLUMN);
}
