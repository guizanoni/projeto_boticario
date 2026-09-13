<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Upload de mídia para etapas (só admin).
// POST multipart { arquivo } → guarda em data/midia/ e devolve
// { ok, url } com a URL de entrega (midia.php, exige sessão).
// Aceita: imagem (webp/png/jpg/gif ≤ 8 MB) e áudio (mp3/m4a/ogg
// ≤ 12 MB) — usados por 7 erros, HQ/história e áudio real.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';

$u = lms_gate_api();
header('Content-Type: application/json; charset=utf-8');
if ($u['papel'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Só o admin envia mídia.']); exit; }

$f = $_FILES['arquivo'] ?? null;
if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'msg' => 'Nenhum arquivo recebido (ou ele passou do limite do servidor).']); exit;
}

$tipos = [
    'image/webp' => ['webp', 8], 'image/png' => ['png', 8], 'image/jpeg' => ['jpg', 8], 'image/gif' => ['gif', 8],
    'audio/mpeg' => ['mp3', 12], 'audio/mp4' => ['m4a', 12], 'audio/x-m4a' => ['m4a', 12], 'audio/ogg' => ['ogg', 12],
];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
if (!isset($tipos[$mime])) { echo json_encode(['ok' => false, 'msg' => 'Formato não aceito — use WEBP/PNG/JPG/GIF (imagem) ou MP3/M4A/OGG (áudio).']); exit; }
[$ext, $maxMb] = $tipos[$mime];
if ($f['size'] > $maxMb * 1024 * 1024) { echo json_encode(['ok' => false, 'msg' => "Arquivo grande demais — o limite é {$maxMb} MB."]); exit; }

$dir = dirname(__DIR__) . '/data/midia';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

$nome = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nome)) {
    echo json_encode(['ok' => false, 'msg' => 'Não consegui gravar o arquivo — tente de novo.']); exit;
}

echo json_encode(['ok' => true, 'url' => '/boticario/midia?f=' . $nome, 'tipo' => str_starts_with($mime, 'image/') ? 'imagem' : 'audio']);
