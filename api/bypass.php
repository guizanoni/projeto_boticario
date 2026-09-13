<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Bypass de etapa (SÓ ADMIN — ferramenta de teste).
// Permite validar a progressão sem jogar etapa a etapa.
// POST JSON: { fase_id, acao: 'passar' | 'reprovar' }
//   passar   → marca só a PRÓXIMA etapa pendente da fase como concluída
//              (na ordem certa) PARA O PRÓPRIO ADMIN — não pula a fase
//              inteira, avança um degrau por clique (alunos não são
//              afetados)
//   reprovar → apaga o progresso do admin na fase (volta a zero)
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate_api();
header('Content-Type: application/json; charset=utf-8');
if ($u['papel'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Só o admin usa o bypass.']); exit; }

$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$fid  = (int) ($in['fase_id'] ?? 0);
$acao = (string) ($in['acao'] ?? '');

$fase = db_one('SELECT f.id FROM fase f JOIN treinamento t ON t.id = f.treinamento_id
                JOIN trilha tr ON tr.id = t.trilha_id
                WHERE f.id = ? AND tr.cliente_id = ?', [$fid, $u['cliente_id']]);
if (!$fase) { echo json_encode(['ok' => false, 'msg' => 'Fase não encontrada.']); exit; }

$uid = (int) $u['id'];

if ($acao === 'passar') {
    // a próxima etapa pendente, na ordem — não todas de uma vez
    $etapa = db_one("SELECT e.id FROM etapa e
                     LEFT JOIN progresso_usuario p ON p.etapa_id = e.id AND p.usuario_id = ?
                     WHERE e.fase_id = ? AND (p.status IS NULL OR p.status != 'concluido')
                     ORDER BY e.ordem LIMIT 1", [$uid, $fid]);
    if (!$etapa) {
        echo json_encode(['ok' => true, 'msg' => 'Todas as etapas desta fase já estavam concluídas.', 'fase_completa' => true]);
        exit;
    }
    db_run('INSERT INTO progresso_usuario (usuario_id, etapa_id, status, acertos, total, tempo_seg, concluido_em)
            VALUES (?,?,\'concluido\',1,1,0, CURRENT_TIMESTAMP)
            ON CONFLICT (usuario_id, etapa_id) DO UPDATE SET
              status = \'concluido\', acertos = 1, total = 1,
              concluido_em = COALESCE(progresso_usuario.concluido_em, CURRENT_TIMESTAMP)',
           [$uid, (int) $etapa['id']]);
    $restam = (int) db_one("SELECT COUNT(*) AS n FROM etapa e
                            LEFT JOIN progresso_usuario p ON p.etapa_id = e.id AND p.usuario_id = ?
                            WHERE e.fase_id = ? AND (p.status IS NULL OR p.status != 'concluido')", [$uid, $fid])['n'];
    echo json_encode(['ok' => true, 'msg' => 'Etapa aprovada no bypass.', 'fase_completa' => $restam === 0]);
} elseif ($acao === 'reprovar') {
    db_run('DELETE FROM progresso_usuario WHERE usuario_id = ? AND etapa_id IN
            (SELECT id FROM etapa WHERE fase_id = ?)', [$uid, $fid]);
    echo json_encode(['ok' => true, 'msg' => 'Progresso da fase zerado no bypass.']);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Ação desconhecida.']);
}
