<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Relatório do dashboard (equipe ou indivíduo).
// O corpo é gerado UMA vez em HTML compatível com e-mail (tabelas
// + estilos inline): a mesma peça vira a página imprimível
// (PDF pelo navegador) e o e-mail — fiéis ao dash da tela.
// POST acao=enviar {emails} envia para vários destinatários.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['gestor', 'admin']);
if (!in_array($u['papel'], ['gestor', 'admin'], true)) { header('Location: ' . lms_home($u)); exit; }
$ehAdmin = $u['papel'] === 'admin';

/* ── dados (mesmas contas do dashboard) ─────────────────────── */
// gestor só reporta a própria equipe; admin/RH podem filtrar um time (?time).
$timeRel = null;
if ($ehAdmin && !empty($_REQUEST['time'])) {
    $timeRel = db_one("SELECT id, nome FROM usuario WHERE id = ? AND cliente_id = ? AND papel = 'gestor'", [(int) $_REQUEST['time'], $u['cliente_id']]);
}
$gestorEscopo = $timeRel ? (int) $timeRel['id'] : (!$ehAdmin ? (int) $u['id'] : 0);
$escopoTime = $gestorEscopo ? ' AND gestor_id = ' . $gestorEscopo : '';
$alunos = db_all("SELECT * FROM usuario WHERE cliente_id = ? AND papel = 'aluno'{$escopoTime} ORDER BY nome", [$u['cliente_id']]);
$nAlunos = count($alunos);
$idList = $alunos ? implode(',', array_map(fn($a) => (int) $a['id'], $alunos)) : '0';

// estrutura: treinamentos ativos (com a trilha dona) → fases → nº de etapas.
// Números individuais sempre relativos ao treinamento ativo de cada aluno.
$treinos = db_all('SELECT t.id, t.nome, t.ordem, tr.nome AS trilha_nome
                   FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id
                   WHERE tr.cliente_id = ? AND t.status = \'ativo\' ORDER BY tr.ordem, tr.id, t.ordem', [$u['cliente_id']]);
$fasesTrein = db_all('SELECT f.id, f.nome, f.ordem, f.treinamento_id,
                             (SELECT COUNT(*) FROM etapa e WHERE e.fase_id = f.id) AS n_etapas
                      FROM fase f JOIN treinamento t ON t.id = f.treinamento_id JOIN trilha tr ON tr.id = t.trilha_id
                      WHERE tr.cliente_id = ? AND t.status = \'ativo\'
                      ORDER BY tr.ordem, t.ordem, f.ordem', [$u['cliente_id']]);
$totPorTrein = [];
foreach ($fasesTrein as $f) $totPorTrein[(int) $f['treinamento_id']] = ($totPorTrein[(int) $f['treinamento_id']] ?? 0) + (int) $f['n_etapas'];
$totalDe = fn(array $a) => (int) ($totPorTrein[(int) ($a['treinamento_id'] ?? 0)] ?? 0);

$prog = db_all("SELECT p.usuario_id, p.acertos, p.total, p.tempo_seg, e.fase_id, f.treinamento_id
                FROM progresso_usuario p
                JOIN etapa e ON e.id = p.etapa_id
                JOIN fase f ON f.id = e.fase_id
                JOIN treinamento t ON t.id = f.treinamento_id AND t.status = 'ativo'
                WHERE p.status = 'concluido' AND p.usuario_id IN ($idList)");

$porAluno = []; $porFase = []; $porAlunoTrein = [];
$somaAc = 0; $somaTt = 0; $somaTempo = 0;
foreach ($prog as $p) {
    $uid = (int) $p['usuario_id'];
    $porAluno[$uid]['ac'] = ($porAluno[$uid]['ac'] ?? 0) + (int) $p['acertos'];
    $porAluno[$uid]['tt'] = ($porAluno[$uid]['tt'] ?? 0) + (int) $p['total'];
    $porAluno[$uid]['tempo'] = ($porAluno[$uid]['tempo'] ?? 0) + (int) $p['tempo_seg'];
    $porFase[(int) $p['fase_id']][$uid] = ($porFase[(int) $p['fase_id']][$uid] ?? 0) + 1;
    $porAlunoTrein[$uid][(int) $p['treinamento_id']] = ($porAlunoTrein[$uid][(int) $p['treinamento_id']] ?? 0) + 1;
    $somaAc += (int) $p['acertos']; $somaTt += (int) $p['total']; $somaTempo += (int) $p['tempo_seg'];
}
$feitasDe = fn(array $a) => (int) ($porAlunoTrein[(int) $a['id']][(int) ($a['treinamento_id'] ?? 0)] ?? 0);

// conformidade = média do avanço individual (cada um no seu treinamento)
$somaPct = 0; $comMeta = 0;
foreach ($alunos as $a) {
    $tot = $totalDe($a);
    if ($tot > 0) { $comMeta++; $somaPct += lms_pct($feitasDe($a), $tot); }
}
$conformidade   = $comMeta ? (int) round($somaPct / $comMeta) : 0;
$ativos7d       = count(array_filter($alunos, fn($a) => $a['ultimo_login'] && strtotime($a['ultimo_login']) >= strtotime('-7 days')));
$nuncaLogaram   = count(array_filter($alunos, fn($a) => !$a['ultimo_login']));
$aproveitamento = $somaTt > 0 ? round($somaAc / $somaTt * 100) : 0;
$horasCap       = $somaTempo / 3600;

// engajamento (mesma métrica do dashboard): distribuição e por-aluno
$comentPorR = [];
foreach (db_all("SELECT usuario_id, COUNT(*) AS n FROM comentario WHERE usuario_id IN ($idList) AND status = 'ativo' GROUP BY usuario_id") as $c) {
    $comentPorR[(int) $c['usuario_id']] = (int) $c['n'];
}
$engDist = ['alto' => 0, 'medio' => 0, 'baixo' => 0];
$engajDe = [];
foreach ($alunos as $a) {
    $uid = (int) $a['id'];
    $eg = lms_engajamento(lms_pct($feitasDe($a), $totalDe($a)), (int) ($a['acessos'] ?? 0), $comentPorR[$uid] ?? 0);
    $engDist[$eg['nivel']]++;
    $engajDe[$uid] = $eg;
}

// panorama por treinamento: alunos que vivem nele + avanço médio
$porTreinamento = [];
foreach ($treinos as $t) {
    $doTrein = array_filter($alunos, fn($a) => (int) ($a['treinamento_id'] ?? 0) === (int) $t['id']);
    if (!$doTrein) continue;
    $tot = (int) ($totPorTrein[(int) $t['id']] ?? 0);
    $soma = 0;
    foreach ($doTrein as $a) $soma += $tot ? lms_pct($feitasDe($a), $tot) : 0;
    $porTreinamento[] = ['t' => $t, 'n' => count($doTrein), 'pct' => (int) round($soma / count($doTrein))];
}

// conformidade por fase (% dos alunos do treinamento com a fase concluída)
$heat = [];
foreach ($fasesTrein as $f) {
    $tid = (int) $f['treinamento_id'];
    $doTrein = array_filter($alunos, fn($a) => (int) ($a['treinamento_id'] ?? 0) === $tid);
    $done = 0;
    foreach ($doTrein as $a) {
        if ((int) $f['n_etapas'] > 0 && ($porFase[(int) $f['id']][(int) $a['id']] ?? 0) >= (int) $f['n_etapas']) $done++;
    }
    $heat[] = ['f' => $f, 'pct' => $doTrein ? (int) round($done / count($doTrein) * 100) : 0];
}

$risco = [];
foreach ($alunos as $a) {
    $uid = (int) $a['id'];
    $tot = $totalDe($a); $feitas = $feitasDe($a);
    $ac = $porAluno[$uid]['ac'] ?? 0; $tt = $porAluno[$uid]['tt'] ?? 0;
    if (!$a['ultimo_login']) { $risco[] = [$a['nome'], 'Nunca acessou a plataforma']; continue; }
    $dias = (int) floor((time() - strtotime($a['ultimo_login'])) / 86400);
    if ($tot > 0 && $feitas < $tot && $dias >= 4) { $risco[] = [$a['nome'], "Sem atividade há {$dias} dias · treinamento em " . lms_pct($feitas, $tot) . '%']; continue; }
    if ($tt > 20 && round($ac / $tt * 100) < 70) $risco[] = [$a['nome'], 'Aproveitamento de ' . round($ac / $tt * 100) . '% — abaixo do esperado'];
}

$rank = lms_ranking((int) $u['cliente_id']);
$top5 = array_slice(array_values(array_filter($rank, fn($r) => (int) $r['xp'] > 0)), 0, 5);

$com = db_one("SELECT SUM(status = 'ativo') AS pub, SUM(status = 'pendente') AS pend FROM comentario WHERE cliente_id = ?", [$u['cliente_id']]);
$semResp = (int) db_one("SELECT COUNT(*) AS n FROM comentario c
                         WHERE c.cliente_id = ? AND c.parent_id IS NULL AND c.status = 'ativo'
                           AND NOT EXISTS (SELECT 1 FROM comentario r WHERE r.parent_id = c.id AND r.status = 'ativo')", [$u['cliente_id']])['n'];

/* modo indivíduo */
$foco = null;
if (!empty($_REQUEST['aluno'])) foreach ($alunos as $a) if ((int) $a['id'] === (int) $_REQUEST['aluno']) $foco = $a;
$F = null; $focoFases = [];
if ($foco) {
    $fid = (int) $foco['id'];
    $pf = $porAluno[$fid] ?? ['ac' => 0, 'tt' => 0, 'tempo' => 0];
    $fTot = $totalDe($foco); $fFeitas = $feitasDe($foco);
    [$fPos, $fTotal, $fMedia, $fXp] = lms_posicao($rank, $fid);
    $F = [
        'trilha' => lms_pct($fFeitas, $fTot),
        'aprov'  => ($pf['tt'] ?? 0) > 0 ? round($pf['ac'] / $pf['tt'] * 100) : 0,
        'minutos' => (int) round(($pf['tempo'] ?? 0) / 60),
        'xp' => $fXp, 'pos' => $fPos, 'total' => $fTotal, 'media' => $fMedia,
    ];
    if (!$foco['ultimo_login']) $F['risco'] = 'Nunca acessou a plataforma';
    else {
        $dias = (int) floor((time() - strtotime($foco['ultimo_login'])) / 86400);
        $F['risco'] = $fTot > 0 && $fFeitas < $fTot && $dias >= 4 ? "Sem atividade há {$dias} dias"
                    : ((($pf['tt'] ?? 0) > 20 && $F['aprov'] < 70) ? "Aproveitamento de {$F['aprov']}% — abaixo do esperado" : null);
    }
    // fases do treinamento ATIVO do colaborador
    $focoFases = db_all(
        'SELECT f.nome, f.ordem, COUNT(e.id) AS total,
                SUM(CASE WHEN p.status = \'concluido\' THEN 1 ELSE 0 END) AS feitas,
                COALESCE(SUM(p.acertos), 0) AS acertos, COALESCE(SUM(p.total), 0) AS questoes,
                COALESCE(SUM(p.tempo_seg), 0) AS tempo
         FROM fase f
         JOIN etapa e ON e.fase_id = f.id
         LEFT JOIN progresso_usuario p ON p.etapa_id = e.id AND p.usuario_id = ? AND p.status = \'concluido\'
         WHERE f.treinamento_id = ?
         GROUP BY f.id ORDER BY f.ordem', [$fid, (int) ($foco['treinamento_id'] ?? 0)]);
}

/* ── peça do relatório: HTML compatível com e-mail ──────────── */
// cores fixas (e-mail não lê CSS vars): navy, azul, verde, areia, rosa
function rel_bar(int $pct, string $cor = '#1CAE8D'): string {
    $pct = max(0, min(100, $pct));
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
      <td style="background:#EDEAE0;border-radius:6px"><div style="width:' . $pct . '%;max-width:100%;background:' . $cor . ';height:10px;border-radius:6px;font-size:0;line-height:0">&nbsp;</div></td></tr></table>';
}
function rel_kpi(string $valor, string $rotulo, string $cor): string {
    return '<td width="20%" align="center" style="background:#FFFFFD;border:1.5px solid #E3E0D3;border-radius:10px;padding:12px 6px">
      <div style="font-family:Arial,sans-serif;font-size:24px;font-weight:800;color:' . $cor . '">' . $valor . '</div>
      <div style="font-family:Arial,sans-serif;font-size:11px;color:#69727D;margin-top:2px">' . $rotulo . '</div></td>';
}
function rel_secao(string $titulo): string {
    return '<h2 style="font-family:Arial,sans-serif;font-size:16px;color:#2A4971;margin:26px 0 10px">' . $titulo . '</h2>';
}

$hoje = date('d/m/Y H:i');
$corpo = '<div style="max-width:680px;margin:0 auto;background:#FDFBEF;padding:26px;border-radius:14px">';
$corpo .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
  <td><div style="font-family:Arial,sans-serif;font-size:21px;font-weight:800;color:#2A4971">💧 ' . e($u['cliente_nome']) . '</div>
      <div style="font-family:Arial,sans-serif;font-size:13px;color:#69727D;margin-top:2px">'
      . ($foco ? 'Relatório individual — <strong>' . e($foco['nome']) . '</strong>' : 'Relatório de treinamento — visão da equipe')
      . ' · ' . count($treinos) . ' treinamento' . (count($treinos) === 1 ? '' : 's') . ' ativo' . (count($treinos) === 1 ? '' : 's') . '</div></td>
  <td align="right" style="font-family:Arial,sans-serif;font-size:12px;color:#69727D">' . $hoje . '</td></tr></table>';

if (!$foco) {
    // ── KPIs da equipe
    $corpo .= rel_secao('📊 Indicadores gerais');
    $corpo .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="6"><tr>'
        . rel_kpi($conformidade . '%', 'Conformidade média', $conformidade >= 70 ? '#27A567' : '#D9534F')
        . rel_kpi((string) $ativos7d, 'Ativos nos últimos 7 dias', '#1CAE8D')
        . rel_kpi((string) $nuncaLogaram, 'Nunca acessaram', $nuncaLogaram ? '#D9534F' : '#27A567')
        . rel_kpi($aproveitamento . '%', 'Aproveitamento médio', '#2A4971')
        . rel_kpi(number_format($horasCap, 1, ',', '.') . 'h', 'Horas de capacitação', '#2A4971')
        . '</tr></table>';

    // ── engajamento da equipe (alto/médio/baixo)
    $corpo .= rel_secao('🎯 Engajamento da equipe');
    $corpo .= '<table role="presentation" width="100%" cellpadding="4" cellspacing="0" style="background:#FFFFFD;border:1.5px solid #E3E0D3;border-radius:10px">';
    $totE = max(1, count($alunos));
    foreach (['alto' => ['🟢 Alto', '#27A567'], 'medio' => ['🟡 Médio', '#E0A200'], 'baixo' => ['🔴 Baixo', '#D9534F']] as $niv => [$lbl, $cor]) {
        $q = $engDist[$niv]; $pc = (int) round($q / $totE * 100);
        $corpo .= '<tr><td width="150" style="font-family:Arial,sans-serif;font-size:13px;color:#2A4971;padding:7px 12px;font-weight:700">' . $lbl . '</td>
          <td style="padding:7px 6px">' . rel_bar($pc, $cor) . '</td>
          <td width="96" align="right" style="font-family:Arial,sans-serif;font-size:13px;font-weight:800;color:#2A4971;padding:7px 12px">' . $q . ' (' . $pc . '%)</td></tr>';
    }
    $corpo .= '</table>';

    // ── panorama por treinamento (quantos vivem nele + avanço médio)
    $corpo .= rel_secao('🗺 Onde a equipe está na jornada');
    $corpo .= '<table role="presentation" width="100%" cellpadding="4" cellspacing="0" style="background:#FFFFFD;border:1.5px solid #E3E0D3;border-radius:10px">';
    foreach ($porTreinamento as $pt) {
        $t = $pt['t'];
        $corpo .= '<tr><td width="260" style="font-family:Arial,sans-serif;font-size:13px;color:#2A4971;padding:7px 12px"><strong>' . e($t['nome']) . '</strong>
            <span style="color:#69727D">· ' . e($t['trilha_nome']) . ' · ' . $pt['n'] . ' colaborador' . ($pt['n'] === 1 ? '' : 'es') . '</span></td>
          <td style="padding:7px 6px">' . rel_bar($pt['pct']) . '</td>
          <td width="46" align="right" style="font-family:Arial,sans-serif;font-size:14px;font-weight:800;color:#2A4971;padding:7px 12px">' . $pt['pct'] . '%</td></tr>';
    }
    if (!$porTreinamento) $corpo .= '<tr><td style="font-family:Arial,sans-serif;font-size:13px;color:#69727D;padding:8px 14px">Nenhum colaborador com treinamento ativo ainda.</td></tr>';
    $corpo .= '</table>';

    // ── conformidade por fase
    $corpo .= rel_secao('✅ Conformidade por fase');
    $corpo .= '<table role="presentation" width="100%" cellpadding="4" cellspacing="0" style="background:#FFFFFD;border:1.5px solid #E3E0D3;border-radius:10px">';
    $nomesTrein = [];
    foreach ($treinos as $t) $nomesTrein[(int) $t['id']] = $t['nome'];
    foreach ($heat as $h) {
        $f = $h['f'];
        $cor = $h['pct'] >= 70 ? '#27A567' : ($h['pct'] >= 40 ? '#E8A13B' : '#D9534F');
        $corpo .= '<tr><td width="300" style="font-family:Arial,sans-serif;font-size:12.5px;color:#2A4971;padding:5px 12px">F' . (int) $f['ordem'] . ' · ' . e($f['nome'])
            . ' <span style="color:#9AA0AA">(' . e($nomesTrein[(int) $f['treinamento_id']] ?? '') . ')</span></td>
          <td style="padding:5px 6px">' . rel_bar($h['pct'], $cor) . '</td>
          <td width="44" align="right" style="font-family:Arial,sans-serif;font-size:12.5px;font-weight:800;color:' . $cor . ';padding:5px 12px">' . $h['pct'] . '%</td></tr>';
    }
    if (!$heat) $corpo .= '<tr><td style="font-family:Arial,sans-serif;font-size:13px;color:#69727D;padding:8px 14px">Nenhuma fase publicada ainda.</td></tr>';
    $corpo .= '</table>';

    // ── radar de risco
    $corpo .= rel_secao('🚨 Radar de risco (' . count($risco) . ')');
    if (!$risco) {
        $corpo .= '<p style="font-family:Arial,sans-serif;font-size:13px;color:#27A567;background:#E7F9F3;padding:10px 14px;border-radius:10px">Nenhum colaborador em risco. 👏</p>';
    } else {
        $corpo .= '<table role="presentation" width="100%" cellpadding="4" cellspacing="0" style="background:#F7E6EA;border-radius:10px">';
        foreach ($risco as [$nome, $msg]) {
            $corpo .= '<tr><td style="font-family:Arial,sans-serif;font-size:13px;padding:6px 14px"><strong style="color:#2A4971">' . e($nome) . '</strong>
              <span style="color:#B04352"> — ' . e($msg) . '</span></td></tr>';
        }
        $corpo .= '</table>';
    }

    // ── top engajamento
    $corpo .= rel_secao('🏅 Top engajamento');
    $corpo .= '<table role="presentation" width="100%" cellpadding="4" cellspacing="0" style="background:#F7EED7;border-radius:10px">';
    $medalha = ['🥇', '🥈', '🥉', '4º', '5º'];
    foreach ($top5 as $i => $r) {
        $corpo .= '<tr><td width="34" align="center" style="font-family:Arial,sans-serif;font-size:15px;padding:5px 0 5px 10px">' . $medalha[$i] . '</td>
          <td style="font-family:Arial,sans-serif;font-size:13px;color:#2A4971;padding:5px 4px"><strong>' . e($r['nome']) . '</strong>
</td>
          <td align="right" style="font-family:Arial,sans-serif;font-size:13px;font-weight:800;color:#8A6D1B;padding:5px 14px">' . number_format((int) $r['xp'], 0, ',', '.') . ' XP</td></tr>';
    }
    if (!$top5) $corpo .= '<tr><td style="font-family:Arial,sans-serif;font-size:13px;color:#69727D;padding:8px 14px">Ainda sem atividade pontuada.</td></tr>';
    $corpo .= '</table>';

    // ── comunidade
    $corpo .= rel_secao('💬 Comunidade');
    $corpo .= '<p style="font-family:Arial,sans-serif;font-size:13px;color:#2A4971;background:#D8EEF7;padding:10px 14px;border-radius:10px">'
        . (int) ($com['pub'] ?? 0) . ' mensagens publicadas · ' . (int) ($com['pend'] ?? 0) . ' aguardando moderação · ' . $semResp . ' sem resposta</p>';

} else {
    // ── KPIs do indivíduo
    $corpo .= rel_secao('📊 Indicadores de ' . e(explode(' ', $foco['nome'])[0]));
    $corpo .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="6"><tr>'
        . rel_kpi($F['trilha'] . '%', 'Treinamento concluído', $F['trilha'] >= 70 ? '#27A567' : '#D9534F')
        . rel_kpi($F['aprov'] . '%', 'Aproveitamento', $F['aprov'] >= 80 ? '#27A567' : '#2A4971')
        . rel_kpi($F['minutos'] . 'min', 'Tempo de capacitação', '#2A4971')
        . rel_kpi($F['pos'] . 'º/' . $F['total'], 'Posição no ranking', '#1CAE8D')
        . rel_kpi($foco['ultimo_login'] ? date('d/m', strtotime($foco['ultimo_login'])) : 'nunca', 'Último acesso', $foco['ultimo_login'] ? '#2A4971' : '#D9534F')
        . '</tr></table>';

    $focoEng = $engajDe[(int) $foco['id']] ?? null;
    $focoGestorR = !empty($foco['gestor_id']) ? db_one("SELECT nome FROM usuario WHERE id = ?", [(int) $foco['gestor_id']]) : null;
    $engLbl = $focoEng ? lms_engaj_info()[$focoEng['nivel']][0] . ' (' . $focoEng['score'] . '/100)' : '—';
    $corpo .= '<p style="font-family:Arial,sans-serif;font-size:12.5px;color:#69727D;margin-top:8px">'
        . e($foco['email'])
        . ' · 🧑‍🤝‍🧑 gestor <strong>' . ($focoGestorR ? e($focoGestorR['nome']) : 'sem time') . '</strong>'
        . ' · 🎯 engajamento <strong>' . $engLbl . '</strong>'
        . ' · ' . number_format($F['xp'], 0, ',', '.') . ' XP (média da equipe: ' . number_format($F['media'], 0, ',', '.') . ' XP)</p>';

    if ($F['risco']) {
        $corpo .= '<p style="font-family:Arial,sans-serif;font-size:13px;color:#B04352;background:#F7E6EA;padding:10px 14px;border-radius:10px;margin-top:10px"><strong>🚨 Atenção:</strong> ' . e($F['risco']) . '</p>';
    } else {
        $corpo .= '<p style="font-family:Arial,sans-serif;font-size:13px;color:#27A567;background:#E7F9F3;padding:10px 14px;border-radius:10px;margin-top:10px">✅ Em dia — sem sinais de risco.</p>';
    }

    // ── fases do treinamento ativo
    $corpo .= rel_secao('📚 Fases do treinamento');
    $corpo .= '<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="background:#FFFFFD;border:1.5px solid #E3E0D3;border-radius:10px;font-family:Arial,sans-serif;font-size:12.5px;color:#2A4971">';
    $corpo .= '<tr style="color:#69727D;font-size:11px"><td style="padding:8px 12px">FASE</td><td align="center">ETAPAS</td><td align="center">ACERTO</td><td align="center">TEMPO</td><td align="right" style="padding-right:12px">STATUS</td></tr>';
    foreach ($focoFases as $t) {
        $pctT = (int) $t['questoes'] > 0 ? round($t['acertos'] / $t['questoes'] * 100) . '%' : '—';
        $st = (int) $t['feitas'] >= (int) $t['total']
            ? '<span style="color:#27A567;font-weight:700">✔ Concluída</span>'
            : ((int) $t['feitas'] > 0 ? '<span style="color:#E8A13B;font-weight:700">… Em andamento</span>' : '<span style="color:#9AA0AA">— Não iniciada</span>');
        $corpo .= '<tr><td style="padding:5px 12px;border-top:1px solid #EFECe1"><strong>F' . (int) $t['ordem'] . '</strong> · ' . e($t['nome']) . '</td>
          <td align="center" style="border-top:1px solid #EFECE1">' . (int) $t['feitas'] . '/' . (int) $t['total'] . '</td>
          <td align="center" style="border-top:1px solid #EFECE1">' . $pctT . '</td>
          <td align="center" style="border-top:1px solid #EFECE1">' . ($t['tempo'] > 0 ? floor($t['tempo'] / 60) . 'm' : '—') . '</td>
          <td align="right" style="padding-right:12px;border-top:1px solid #EFECE1">' . $st . '</td></tr>';
    }
    if (!$focoFases) $corpo .= '<tr><td colspan="5" style="padding:8px 12px;color:#69727D">Sem treinamento ativo ou sem fases publicadas.</td></tr>';
    $corpo .= '</table>';
}

$corpo .= '<p style="font-family:Arial,sans-serif;font-size:11.5px;color:#9AA0AA;margin-top:26px;text-align:center">
  Relatório gerado por ' . e($u['nome']) . ' em ' . $hoje . ' · Plataforma de Capacitação ' . e($u['cliente_nome']) . '</p></div>';

/* ── envio por e-mail (vários destinatários) ────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'enviar') {
    require dirname(__DIR__, 2) . '/admin/_mail.php';
    $brutos = preg_split('/[\s,;]+/', (string) ($_POST['emails'] ?? ''));
    $emails = array_values(array_unique(array_filter($brutos, fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL))));
    $emails = array_slice($emails, 0, 10);
    if (!$emails) {
        lms_flash_set(false, 'Informe pelo menos um e-mail válido (separe vários por vírgula).');
    } else {
        $assunto = '📊 Relatório de treinamento — ' . $u['cliente_nome']
                 . ($foco ? ' · ' . $foco['nome'] : ' · equipe') . ' · ' . date('d/m/Y');
        $htmlMail = '<div style="background:#F3F1E8;padding:20px 8px">' . $corpo . '</div>';
        $alt = 'Relatório de treinamento ' . $u['cliente_nome'] . ' — abra em um leitor de HTML para ver os gráficos.';
        [$ok, $erro] = portal_mail($emails, $assunto, $htmlMail, $alt);
        if ($ok) lms_flash_set(true, 'Relatório enviado para ' . implode(', ', $emails) . ' ✉️');
        else { lms_flash_set(false, 'Não consegui enviar agora — tente novamente em instantes.'); error_log('[boticario relatorio] ' . $erro); }
    }
    header('Location: /boticario/gestor/relatorio' . ($foco ? '?aluno=' . (int) $foco['id'] : '')); exit;
}
$flash = lms_flash_get();

/* ── página (visualização + impressão/PDF) ──────────────────── */
lms_head('Relatório — ' . ($foco ? $foco['nome'] : 'equipe'), $u);
lms_topbar($u);
?>
<main class="lms-container" style="max-width:860px; padding-top:24px; padding-bottom:64px">

  <div class="rel-toolbar">
    <div class="lms-row" style="justify-content:space-between; gap:12px; flex-wrap:wrap">
      <div>
        <h1 style="font-size:var(--lms-title-md)">📄 Relatório <?= $foco ? 'individual' : 'da equipe' ?></h1>
        <p class="lms-small lms-muted" style="margin-top:2px">Salve em PDF pelo botão de imprimir, ou envie por e-mail — o relatório chega igualzinho a esta página.</p>
      </div>
      <div class="lms-row" style="gap:8px; flex-wrap:nowrap">
        <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/gestor/<?= $foco ? '?aluno=' . (int) $foco['id'] : '' ?>">← Dashboard</a>
        <button class="lms-btn lms-btn-game lms-btn-sm" onclick="window.print()">🖨 Salvar PDF</button>
      </div>
    </div>

    <?php if ($flash): ?>
    <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:12px">
      <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div>
    </div>
    <?php endif; ?>

    <form method="post" class="lms-card-pastel is-sky" style="margin-top:14px; padding:16px 18px">
      <input type="hidden" name="acao" value="enviar">
      <?php if ($foco): ?><input type="hidden" name="aluno" value="<?= (int) $foco['id'] ?>"><?php endif; ?>
      <label class="lms-label" for="rel-emails">📧 Enviar este relatório por e-mail</label>
      <div class="lms-row" style="gap:8px; flex-wrap:nowrap">
        <input class="lms-input" id="rel-emails" name="emails" required style="flex:1"
               placeholder="maria@empresa.com, joao@empresa.com — separe vários por vírgula">
        <button class="lms-btn lms-btn-primary lms-btn-sm" style="flex:none">Enviar ✉️</button>
      </div>
    </form>
  </div>

  <div style="margin-top:20px; border:var(--lms-border-game); border-radius:var(--lms-radius-lg); overflow:hidden" class="rel-folha">
    <?= $corpo ?>
  </div>
</main>

<style>
  @media print {
    .lms-topbar, header, .rel-toolbar { display:none !important }
    main { padding:0 !important; max-width:100% !important }
    .rel-folha { border:none !important; border-radius:0 !important }
    body { background:#fff !important }
  }
</style>
</body></html>
