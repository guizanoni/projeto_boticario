<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Comunidade (3Fs — Friends): discussão contextual
// por FASE, com MODERAÇÃO PRÉVIA: mensagem de aluno nasce
// 'pendente' e só vale XP/badge depois de aprovada. POST JSON:
//   { acao:'criar', fase_id, texto, parent_id? }
//   { acao:'aprovar'|'ocultar'|'oficial', comentario_id }  (gestor/admin)
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate_api();
header('Content-Type: application/json; charset=utf-8');

$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$acao = (string) ($in['acao'] ?? 'criar');

if ($acao === 'criar') {
    $fid    = (int) ($in['fase_id'] ?? 0);
    $texto  = trim((string) ($in['texto'] ?? ''));
    $parent = (int) ($in['parent_id'] ?? 0) ?: null;

    $fase = db_one('SELECT f.id FROM fase f JOIN treinamento t ON t.id = f.treinamento_id
                    JOIN trilha tr ON tr.id = t.trilha_id
                    WHERE f.id = ? AND tr.cliente_id = ?', [$fid, $u['cliente_id']]);
    if (!$fase) { echo json_encode(['ok' => false, 'msg' => 'Fase não encontrada.']); exit; }
    if ($texto === '' || mb_strlen($texto) > 2000) { echo json_encode(['ok' => false, 'msg' => 'Escreva algo (até 2000 caracteres).']); exit; }
    if ($parent) {
        $pai = db_one('SELECT id, parent_id FROM comentario WHERE id = ? AND fase_id = ? AND status = \'ativo\'', [$parent, $fid]);
        if (!$pai) { echo json_encode(['ok' => false, 'msg' => 'Comentário original não encontrado.']); exit; }
        if ($pai['parent_id']) $parent = (int) $pai['parent_id'];   // thread raso: 1 nível
    }

    // freio: 1 comentário a cada 20s por sessão
    lms_session_start();
    if (($_SESSION['coment_ts'] ?? 0) > time() - 20) {
        echo json_encode(['ok' => false, 'msg' => 'Aguarde alguns segundos entre mensagens.']); exit;
    }

    // mensagem da equipe entra no ar; a de aluno aguarda moderação
    $status = in_array($u['papel'], ['gestor', 'admin'], true) ? 'ativo' : 'pendente';
    db_run('INSERT INTO comentario (cliente_id, fase_id, usuario_id, parent_id, texto, status) VALUES (?,?,?,?,?,?)',
           [$u['cliente_id'], $fid, $u['id'], $parent, $texto, $status]);
    $_SESSION['coment_ts'] = time();
    echo json_encode(['ok' => true, 'status' => $status, 'xp' => lms_xp((int) $u['id'])], JSON_UNESCAPED_UNICODE);
    exit;
}

// moderação: gestor/admin (exclusão definitiva: só admin)
if (in_array($acao, ['aprovar', 'ocultar', 'oficial', 'excluir'], true)) {
    if (!in_array($u['papel'], ['gestor', 'admin'], true)) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Sem permissão.']); exit; }
    if ($acao === 'excluir' && $u['papel'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Só o admin exclui definitivamente.']); exit; }
    $c = db_one('SELECT * FROM comentario WHERE id = ? AND cliente_id = ?', [(int) ($in['comentario_id'] ?? 0), $u['cliente_id']]);
    if (!$c) { echo json_encode(['ok' => false, 'msg' => 'Comentário não encontrado.']); exit; }
    if ($acao === 'aprovar')      lms_comentario_aprovar($c);
    elseif ($acao === 'ocultar')  db_run('UPDATE comentario SET status = \'oculto\', oficial = 0 WHERE id = ?', [$c['id']]);
    elseif ($acao === 'excluir')  db_run('DELETE FROM comentario WHERE id = ? OR parent_id = ?', [$c['id'], $c['id']]);
    else db_run('UPDATE comentario SET oficial = CASE oficial WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [$c['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação desconhecida.']);
