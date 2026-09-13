<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Entrega de mídia das etapas (data/midia é negado
// ao público; este passthrough exige sessão). GET midia.php?f={nome}
// Suporta Range (necessário para <audio>/<video> buscarem posições).
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/_lib/auth.php';

$eu = lms_user();
if (!$eu) { http_response_code(401); exit; }

$f = (string) ($_GET['f'] ?? '');
if (!preg_match('/^[0-9]{8}-[0-9a-f]{12}\.(webp|png|jpg|gif|mp3|m4a|ogg|mp4)$/', $f)) { http_response_code(404); exit; }

$arquivo = __DIR__ . '/data/midia/' . $f;
if (!is_file($arquivo)) { http_response_code(404); exit; }

$mimes = ['webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif',
          'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'mp4' => 'video/mp4'];
$ext = pathinfo($f, PATHINFO_EXTENSION);
$tam = filesize($arquivo);

header('Content-Type: ' . $mimes[$ext]);
header('Cache-Control: private, max-age=86400');
header('Accept-Ranges: bytes');

// Range simples (áudio): "bytes=início-fim"
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $ini = (int) $m[1];
    $fim = $m[2] !== '' ? min((int) $m[2], $tam - 1) : $tam - 1;
    if ($ini > $fim || $ini >= $tam) { http_response_code(416); header("Content-Range: bytes */{$tam}"); exit; }
    http_response_code(206);
    header("Content-Range: bytes {$ini}-{$fim}/{$tam}");
    header('Content-Length: ' . ($fim - $ini + 1));
    // em blocos: um vídeo de 100 MB não pode passar inteiro pela memória
    $h = fopen($arquivo, 'rb');
    fseek($h, $ini);
    $resta = $fim - $ini + 1;
    while ($resta > 0 && !feof($h)) {
        $bloco = fread($h, min(65536, $resta));
        if ($bloco === false || $bloco === '') break;
        echo $bloco;
        $resta -= strlen($bloco);
        flush();
    }
    fclose($h);
    exit;
}

header('Content-Length: ' . $tam);
readfile($arquivo);
