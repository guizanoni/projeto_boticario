<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Registro de progresso de etapa (RF-04/05/09).
// POST JSON: { etapa_id, acertos, total, tempo_seg }
// Toda a progressão é decidida no servidor: conclusão de FASE
// (com nota mínima), de TREINAMENTO (todas as fases) e badges.
// Resposta: { ok, xp, events: [ {tipo: ...} ] }
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate_api();
header('Content-Type: application/json; charset=utf-8');

$in = json_decode((string) file_get_contents('php://input'), true);
$etapaId = (int) ($in['etapa_id'] ?? 0);
$acertos = max(0, (int) ($in['acertos'] ?? 0));
$total   = max(0, (int) ($in['total'] ?? 0));
$tempo   = max(0, min(3600, (int) ($in['tempo_seg'] ?? 0)));
$acertos = min($acertos, max($total, $acertos)); // sanidade

// resposta exata dada (por tipo de minigame) — pra reabrir a etapa depois
// em modo revisão mostrando o que foi respondido, sem poder mudar.
// Opcional: nem todo tipo de minigame precisa guardar (os que só concluem
// quando 100% corretos não têm "escolha errada" pra reconstruir).
$respostaJson = null;
if (isset($in['resposta'])) {
    $codificada = json_encode($in['resposta'], JSON_UNESCAPED_UNICODE);
    if ($codificada !== false && strlen($codificada) <= 20000) $respostaJson = $codificada;
}

$etapa = db_one('SELECT e.*, f.id AS fase_id2, f.nome AS fase_nome, f.ordem AS fase_ordem, f.nota_minima,
                        t.id AS trein_id, t.nome AS trein_nome, tr.id AS trilha_id, tr.cliente_id
                 FROM etapa e
                 JOIN fase f ON f.id = e.fase_id
                 JOIN treinamento t ON t.id = f.treinamento_id
                 JOIN trilha tr ON tr.id = t.trilha_id
                 WHERE e.id = ?', [$etapaId]);
if (!$etapa || (int) $etapa['cliente_id'] !== (int) $u['cliente_id']) {
    http_response_code(404); echo json_encode(['ok' => false, 'msg' => 'Etapa não encontrada']); exit;
}

$uid = (int) $u['id'];
$fid = (int) $etapa['fase_id'];
$events = [];

// estado ANTES do registro (para detectar transições)
$faseConcluidaAntes = fn() => (int) db_one(
    'SELECT COUNT(*) AS n FROM etapa e LEFT JOIN progresso_usuario p ON p.etapa_id = e.id AND p.usuario_id = ?
     WHERE e.fase_id = ? AND COALESCE(p.status, \'\') != \'concluido\'', [$uid, $fid])['n'] === 0;
$jaEstavaConcluida = $faseConcluidaAntes();

// upsert do progresso (revisões atualizam desempenho, preservam a 1ª conclusão)
db_run('INSERT INTO progresso_usuario (usuario_id, etapa_id, status, acertos, total, tempo_seg, concluido_em, resposta_json)
        VALUES (?,?,?,?,?,?, CURRENT_TIMESTAMP, ?)
        ON CONFLICT (usuario_id, etapa_id) DO UPDATE SET
          status = \'concluido\', acertos = excluded.acertos, total = excluded.total,
          tempo_seg = excluded.tempo_seg, resposta_json = excluded.resposta_json,
          concluido_em = COALESCE(progresso_usuario.concluido_em, excluded.concluido_em)',
       [$uid, $etapaId, 'concluido', $acertos, $total, $tempo, $respostaJson]);

$badges = [];
$daBadge = function (string $nome) use ($uid, $u, &$badges) {
    $b = db_one('SELECT * FROM badge WHERE cliente_id = ? AND nome = ?', [$u['cliente_id'], $nome]);
    if (!$b) return;
    $tem = db_one('SELECT 1 AS x FROM conquista_usuario WHERE usuario_id = ? AND badge_id = ?', [$uid, $b['id']]);
    if ($tem) return;
    db_run('INSERT INTO conquista_usuario (usuario_id, badge_id) VALUES (?,?)', [$uid, $b['id']]);
    $badges[] = ['nome' => $b['nome'], 'icone' => $b['icone'], 'descricao' => $b['descricao']];
};

// fase concluiu AGORA?
if (!$jaEstavaConcluida && $faseConcluidaAntes()) {
    $stats = db_one('SELECT COALESCE(SUM(p.acertos),0) AS a, COALESCE(SUM(p.total),0) AS t, COALESCE(SUM(p.tempo_seg),0) AS s
                     FROM etapa e JOIN progresso_usuario p ON p.etapa_id = e.id AND p.usuario_id = ?
                     WHERE e.fase_id = ?', [$uid, $fid]);
    $pct = (int) $stats['t'] > 0 ? round($stats['a'] / $stats['t'] * 100) : 100;

    // ── Meta de aprovação (nota mínima por FASE) ──
    // Abaixo da meta: o progresso das etapas é zerado e o aluno refaz.
    // Revisões de fase já aprovada nunca derrubam a conclusão
    // (este bloco só roda na PRIMEIRA conclusão — !$jaEstavaConcluida).
    $minimo = (int) ($etapa['nota_minima'] ?? 80);
    if ($minimo > 0 && $pct < $minimo) {
        db_run('DELETE FROM progresso_usuario WHERE usuario_id = ? AND etapa_id IN
                (SELECT id FROM etapa WHERE fase_id = ?)', [$uid, $fid]);
        echo json_encode(['ok' => true, 'xp' => lms_xp($uid), 'events' => [[
            'tipo' => 'reprovado', 'nome' => $etapa['fase_nome'],
            'pct' => $pct, 'minimo' => $minimo, 'tempo_seg' => (int) $stats['s'],
        ]]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // badges de desempenho da fase (RF-09)
    $nFases = (int) db_one('SELECT COUNT(DISTINCT e.fase_id) AS n
                            FROM progresso_usuario p JOIN etapa e ON e.id = p.etapa_id
                            WHERE p.usuario_id = ? AND p.status = \'concluido\'
                            AND NOT EXISTS (SELECT 1 FROM etapa e2 LEFT JOIN progresso_usuario p2
                                            ON p2.etapa_id = e2.id AND p2.usuario_id = p.usuario_id
                                            WHERE e2.fase_id = e.fase_id AND COALESCE(p2.status,\'\') != \'concluido\')',
                           [$uid])['n'];
    if ($nFases === 1)              $daBadge('Primeiro Passo');
    if ($pct >= 90)                 $daBadge('Na Mosca');
    if ((int) $stats['s'] <= 240)   $daBadge('Relâmpago');

    // treinamento (pasta) concluiu? — todas as fases dele feitas
    $treinAberto = (int) db_one('SELECT COUNT(*) AS n FROM etapa e
                                 JOIN fase f ON f.id = e.fase_id
                                 LEFT JOIN progresso_usuario p ON p.etapa_id = e.id AND p.usuario_id = ?
                                 WHERE f.treinamento_id = ? AND COALESCE(p.status, \'\') != \'concluido\'',
                                [$uid, $etapa['trein_id']])['n'];
    if ($treinAberto === 0) {
        $events[] = ['tipo' => 'treinamento_concluido', 'nome' => $etapa['trein_nome']];
        $daBadge('Missão Cumprida');

        // trilha inteira? — todos os treinamentos ativos dela
        $trilhaAberta = (int) db_one('SELECT COUNT(*) AS n FROM etapa e
                                      JOIN fase f ON f.id = e.fase_id
                                      JOIN treinamento t ON t.id = f.treinamento_id
                                      LEFT JOIN progresso_usuario p ON p.etapa_id = e.id AND p.usuario_id = ?
                                      WHERE t.trilha_id = ? AND t.status = \'ativo\' AND COALESCE(p.status, \'\') != \'concluido\'',
                                     [$uid, $etapa['trilha_id']])['n'];
        if ($trilhaAberta === 0) $daBadge('Trilha Completa');
    }
}

foreach ($badges as $b) $events[] = ['tipo' => 'badge', 'badge' => $b];

echo json_encode(['ok' => true, 'xp' => lms_xp($uid), 'events' => $events], JSON_UNESCAPED_UNICODE);
