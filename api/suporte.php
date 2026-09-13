<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Chamados de suporte e reporte de bugs.
// POST JSON: { tipo: 'chamado'|'bug', categoria, assunto, mensagem }
// Envia e-mail para gui@skope.cc via portal_mail (PHPMailer + SMTP
// do portal skope.cc), com o contexto do usuário logado.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';
require dirname(__DIR__, 2) . '/admin/_mail.php';

$u = lms_gate_api();
header('Content-Type: application/json; charset=utf-8');

$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$tipo      = ($in['tipo'] ?? '') === 'bug' ? 'bug' : 'chamado';
$categoria = trim((string) ($in['categoria'] ?? ''));
$assunto   = trim((string) ($in['assunto'] ?? ''));
$mensagem  = trim((string) ($in['mensagem'] ?? ''));

if ($assunto === '' || $mensagem === '' || $categoria === '') {
    echo json_encode(['ok' => false, 'msg' => 'Preencha categoria, assunto e mensagem.']); exit;
}
if (mb_strlen($assunto) > 150 || mb_strlen($mensagem) > 4000) {
    echo json_encode(['ok' => false, 'msg' => 'Assunto ou mensagem longos demais.']); exit;
}

// freio de repetição: 1 envio a cada 30s por sessão
lms_session_start();
if (($_SESSION['suporte_ts'] ?? 0) > time() - 30) {
    echo json_encode(['ok' => false, 'msg' => 'Aguarde alguns segundos antes de enviar de novo.']); exit;
}

$rotulo = $tipo === 'bug' ? '🐞 Bug' : '🎫 Chamado';
$titulo = "[Boticário LMS] {$rotulo} · {$categoria} — {$assunto}";

$linhas = [
    'Tipo'      => $tipo === 'bug' ? 'Reporte de bug' : 'Chamado de suporte',
    'Categoria' => $categoria,
    'Usuário'   => $u['nome'] . ' <' . $u['email'] . '>',
    'Papel'     => $u['papel'],
    'Cliente'   => $u['cliente_nome'],
    'Página'    => (string) ($_SERVER['HTTP_REFERER'] ?? '—'),
    'Navegador' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? '—'),
    'Data'      => date('d/m/Y H:i') . ' (Brasília)',
];
$meta = '';
foreach ($linhas as $k => $v) {
    $meta .= '<tr><td style="padding:4px 14px 4px 0;color:#69727D;white-space:nowrap">' . e($k) . '</td><td style="padding:4px 0">' . e($v) . '</td></tr>';
}
$html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1F2A25;max-width:640px">
  <h2 style="margin:0 0 4px">' . e($rotulo . ' · ' . $assunto) . '</h2>
  <p style="margin:0 0 16px;color:#69727D">Boticário LMS — enviado pela plataforma</p>
  <table style="font-size:13px;border-collapse:collapse;margin-bottom:18px">' . $meta . '</table>
  <div style="background:#F6F7F0;border-left:4px solid #007A54;padding:14px 18px;border-radius:6px;white-space:pre-wrap;line-height:1.6">' . e($mensagem) . '</div>
</div>';
$alt = "{$rotulo} · {$assunto}\n\n" . implode("\n", array_map(fn($k, $v) => "$k: $v", array_keys($linhas), $linhas)) . "\n\n{$mensagem}";

[$ok, $erro] = portal_mail(['gui@skope.cc'], $titulo, $html, $alt);
if ($ok) $_SESSION['suporte_ts'] = time();

echo json_encode($ok
    ? ['ok' => true]
    : ['ok' => false, 'msg' => 'Não consegui enviar agora. Tente novamente em instantes.'], JSON_UNESCAPED_UNICODE);
if (!$ok) error_log('[boticario suporte] ' . $erro);
