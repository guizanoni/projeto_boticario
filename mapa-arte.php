<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Entrega das artes de trilha enviadas pelo admin
// (data/mapas é negado ao público; este passthrough exige sessão
// e mesmo tenant). GET mapa-arte.php?tr={trilha_id}
//   &p={tarde|noite|madrugada}  → variante de período do CENÁRIO
//   &av={m|f|pet}               → AVATAR do mapa (personagem)
// Variante de período ausente cai na arte base (ciclo progressivo).
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/_lib/auth.php';

$eu = lms_user();
if (!$eu) { http_response_code(401); exit; }

$id = (int) ($_GET['tr'] ?? 0);
$dona = db_one('SELECT cliente_id FROM trilha WHERE id = ?', [$id]);
if (!$dona || (int) $dona['cliente_id'] !== (int) $eu['cliente_id']) { http_response_code(404); exit; }

$tipos = ['webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg'];

$av = (string) ($_GET['av'] ?? '');
if (in_array($av, ['m', 'f', 'pet'], true)) {
    $candidatos = ["{$id}-avatar-{$av}"];
} else {
    $p = (string) ($_GET['p'] ?? '');
    $sufixo = in_array($p, ['tarde', 'noite', 'madrugada'], true) ? '-' . $p : '';
    $candidatos = $sufixo ? [$id . $sufixo, (string) $id] : [(string) $id];   // variante → base
}
foreach ($candidatos as $base) {
    foreach ($tipos as $ext => $mime) {
        $arquivo = __DIR__ . '/data/mapas/' . $base . '.' . $ext;
        if (is_file($arquivo)) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($arquivo));
            header('Cache-Control: private, max-age=86400');
            readfile($arquivo);
            exit;
        }
    }
}
http_response_code(404);
