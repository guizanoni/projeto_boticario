<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Entrega da foto de perfil (data/fotos é negado
// ao público; este passthrough exige sessão e mesmo tenant).
// GET foto.php?u={id}
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/_lib/auth.php';

$eu = lms_user();
if (!$eu) { http_response_code(401); exit; }

$id = (int) ($_GET['u'] ?? 0);
$dono = db_one('SELECT cliente_id FROM usuario WHERE id = ?', [$id]);
if (!$dono || (int) $dono['cliente_id'] !== (int) $eu['cliente_id']) { http_response_code(404); exit; }

$arquivo = __DIR__ . '/data/fotos/' . $id . '.jpg';
if (!is_file($arquivo)) { http_response_code(404); exit; }

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($arquivo));
header('Cache-Control: private, max-age=3600');
readfile($arquivo);
