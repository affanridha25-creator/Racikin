<?php
// emailtpl.php — template email HTML bermerek Racikin (tabel + CSS inline, aman untuk Gmail/Outlook/HP) + pembuat multipart.
// Setiap email dikirim sebagai multipart/alternative: versi teks polos (cadangan) + versi HTML.
require_once __DIR__ . '/mail.php';

function etpl_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Baris info "label : nilai" dalam kartu krem. $pairs = [['Usaha','Roti Ana'], ...]
function etpl_facts(array $pairs) {
    $r = '';
    foreach ($pairs as [$k, $v]) {
        if ($v === '' || $v === null) continue;
        $r .= '<tr><td style="padding:7px 0;color:#8a6f64;font-size:13px;width:42%;vertical-align:top">' . etpl_h($k)
            . '</td><td style="padding:7px 0;color:#2a1512;font-size:14px;font-weight:700;vertical-align:top">' . $v . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff6ef;border:1px solid #f3dccd;border-radius:14px;margin:18px 0"><tr><td style="padding:10px 18px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $r . '</table></td></tr></table>';
}
// Langkah bernomor.
function etpl_steps(array $steps) {
    $r = '';
    foreach ($steps as $i => $t) {
        $r .= '<tr><td style="padding:6px 12px 6px 0;vertical-align:top;width:30px"><div style="width:26px;height:26px;line-height:26px;text-align:center;border-radius:13px;background:#D53E0F;color:#ffffff;font-weight:800;font-size:13px">' . ($i + 1)
            . '</div></td><td style="padding:6px 0;font-size:14px;line-height:1.55;color:#3b2a25">' . $t . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 4px">' . $r . '</table>';
}
function etpl_p($html) { return '<p style="margin:0 0 14px;font-size:15px;line-height:1.65;color:#3b2a25">' . $html . '</p>'; }
// Tombol utama (pil) — bulletproof: tautan berlatar, bukan gambar.
function etpl_button($label, $url, $bg = '#D53E0F') {
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 8px"><tr><td align="center" bgcolor="' . $bg . '" style="border-radius:999px"><a href="' . etpl_h($url)
        . '" style="display:inline-block;padding:14px 30px;font-size:15px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:999px;font-family:Arial,Helvetica,sans-serif">' . etpl_h($label) . '</a></td></tr></table>';
}
// Kotak kode besar (mis. OTP).
function etpl_code($code) {
    return '<div style="margin:18px 0;padding:16px;text-align:center;border-radius:14px;background:#fff6ef;border:2px dashed #f0b99a;font-size:34px;letter-spacing:10px;font-weight:800;color:#D53E0F;font-family:Arial,Helvetica,sans-serif">' . etpl_h($code) . '</div>';
}

/**
 * Bungkus isi menjadi halaman email lengkap.
 * @param string $title   judul besar di dalam kartu
 * @param string $preview teks pratinjau (tampil di daftar inbox, disembunyikan di badan)
 * @param string $content HTML isi (susun dari etpl_p / etpl_facts / etpl_steps / etpl_button)
 */
function etpl_html($title, $preview, $content) {
    $base = function_exists('app_base_url') ? app_base_url() : 'https://login.racikin.com';
    $mail = function_exists('support_email') ? support_email() : 'info@racikin.com';
    $logo = $base . '/icons/logo-bowl-web.png';
    return '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>' . etpl_h($title) . '</title></head>'
      . '<body style="margin:0;padding:0;background:#fbeee7;-webkit-text-size-adjust:100%">'
      . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#fbeee7">' . etpl_h($preview) . '&#8199;&#847;&#8199;&#847;&#8199;&#847;</div>'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fbeee7"><tr><td align="center" style="padding:24px 12px">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;font-family:Arial,Helvetica,sans-serif">'
      // header merek
      . '<tr><td bgcolor="#D53E0F" style="background:#D53E0F;background-image:linear-gradient(135deg,#F26A2E 0%,#D53E0F 55%,#8F1208 100%);border-radius:22px 22px 0 0;padding:26px 28px 30px">'
      . '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="vertical-align:middle"><img src="' . etpl_h($logo) . '" width="46" height="43" alt="" style="display:block;border:0;border-radius:23px;background:#ffffff;padding:3px"></td>'
      . '<td style="vertical-align:middle;padding-left:12px;font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-.3px">Racikin</td></tr></table>'
      . '<div style="margin-top:14px;font-size:13px;color:#ffe3d3">Racik. Hitung. Jual.</div></td></tr>'
      // kartu isi
      . '<tr><td style="background:#ffffff;padding:30px 28px 26px;border-radius:0 0 22px 22px;box-shadow:0 12px 30px rgba(126,3,6,.10)">'
      . '<h1 style="margin:0 0 16px;font-size:23px;line-height:1.25;color:#2a1512;font-weight:800;letter-spacing:-.3px">' . etpl_h($title) . '</h1>'
      . $content
      . '<div style="margin-top:26px;padding-top:18px;border-top:1px solid #f3e4da;font-size:12.5px;line-height:1.7;color:#8a6f64">Butuh bantuan? Balas email ini, tulis ke <a href="mailto:' . etpl_h($mail) . '" style="color:#D53E0F;font-weight:700;text-decoration:none">' . etpl_h($mail)
      . '</a> atau chat WhatsApp <a href="https://wa.me/6281325523999" style="color:#D53E0F;font-weight:700;text-decoration:none">0813-2552-3999</a>.</div>'
      . '</td></tr>'
      . '<tr><td align="center" style="padding:18px 10px 6px;font-size:11.5px;line-height:1.6;color:#a98f84">Email otomatis dari Racikin · <a href="' . etpl_h($base) . '" style="color:#a98f84">' . etpl_h(preg_replace('#^https?://#', '', $base)) . '</a><br>&copy; ' . date('Y') . ' Racikin</td></tr>'
      . '</table></td></tr></table></body></html>';
}

/** Susun multipart/alternative. Return [$headersTambahan, $body]. $text = cadangan polos, $html = tampilan utama. */
function etpl_multipart($text, $html) {
    $b = 'rk_' . bin2hex(random_bytes(10));
    $enc = function ($s) { return chunk_split(base64_encode((string)$s), 76, "\r\n"); };
    $body = "This is a multi-part message in MIME format.\r\n\r\n"
          . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($text)
          . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($html)
          . "--$b--\r\n";
    return ["Content-Type: multipart/alternative; boundary=\"$b\"\r\nMIME-Version: 1.0\r\n", $body];
}

require_once __DIR__ . '/emailbuilders.php';   // isi tiap jenis email + katalog contoh
