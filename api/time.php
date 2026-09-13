<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Edição de time (add/remove aluno) — só admin/RH.
// POST JSON: { acao: 'add'|'remove', aluno_id, gestor_id }
//   add    → define usuario.gestor_id = gestor_id
//   remove → define usuario.gestor_id = NULL (tira do time)
// Guardas: admin/RH; aluno e gestor têm de ser do mesmo cliente.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate_api();
header('Content-Type: application/json; charset=utf-8');

if ($u['papel'] !== 'admin') { echo json_encode(['ok' => false, 'msg' => 'Sem permissão.']); exit; }

$in       = json_decode((string) file_get_contents('php://input'), true) ?: [];
$acao     = ($in['acao'] ?? '') === 'remove' ? 'remove' : 'add';
$alunoId  = (int) ($in['aluno_id'] ?? 0);
$gestorId = (int) ($in['gestor_id'] ?? 0);
$cli      = (int) $u['cliente_id'];

$aluno = db_one("SELECT id FROM usuario WHERE id = ? AND cliente_id = ? AND papel = 'aluno'", [$alunoId, $cli]);
if (!$aluno) { echo json_encode(['ok' => false, 'msg' => 'Aluno inválido.']); exit; }

if ($acao === 'remove') {
    db_run('UPDATE usuario SET gestor_id = NULL WHERE id = ?', [$alunoId]);
    echo json_encode(['ok' => true]); exit;
}

$gestor = db_one("SELECT id FROM usuario WHERE id = ? AND cliente_id = ? AND papel = 'gestor'", [$gestorId, $cli]);
if (!$gestor) { echo json_encode(['ok' => false, 'msg' => 'Gestor inválido.']); exit; }

db_run('UPDATE usuario SET gestor_id = ? WHERE id = ?', [$gestorId, $alunoId]);
echo json_encode(['ok' => true]);
