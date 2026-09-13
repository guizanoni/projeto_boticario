<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Dashboard executivo de gestão (RF-07).
// Visão estratégica da capacitação: conformidade, risco, jornada
// da equipe, engajamento e atividade — tudo calculado do
// registro de aprendizagem (Arquitetura cap. 18).
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['gestor']);
$ehAdmin = $u['papel'] === 'admin';   // admin/RH veem todos os times; gestor só o seu
// admin/RH pode filtrar o dashboard num time específico (?time=gestor_id) —
// é o "como está a equipe do fulano?", vindo da aba Times.
$timeFoco = null;
if ($ehAdmin && !empty($_GET['time'])) {
    $timeFoco = db_one("SELECT id, nome FROM usuario WHERE id = ? AND cliente_id = ? AND papel = 'gestor'",
                       [(int) $_GET['time'], $u['cliente_id']]);
}
$gestorEscopo = $timeFoco ? (int) $timeFoco['id'] : (!$ehAdmin ? (int) $u['id'] : 0);
$escopoTime = $gestorEscopo ? ' AND gestor_id = ' . $gestorEscopo : '';

/* ── lembrete de engajamento (perfil observado) ──────────────
   Sugestão de texto gerada com o contexto do colaborador; o gestor
   revisa/edita no popup antes de enviar. */
function lembrete_sugestao(array $alvo, array $u, string $tipo = 'resgate'): array {
    // progresso e próximo passo dentro do treinamento ATIVO do colaborador
    $trein = (int) ($alvo['treinamento_id'] ?? 0);
    $tot = (int) db_one('SELECT COUNT(*) AS n FROM etapa e JOIN fase f ON f.id = e.fase_id
                         WHERE f.treinamento_id = ?', [$trein])['n'];
    $fez = (int) db_one('SELECT COUNT(*) AS n FROM progresso_usuario p JOIN etapa e ON e.id = p.etapa_id
                         JOIN fase f ON f.id = e.fase_id
                         WHERE p.usuario_id = ? AND p.status = \'concluido\' AND f.treinamento_id = ?',
                        [$alvo['id'], $trein])['n'];
    $prox = db_one('SELECT f.nome FROM fase f
                    WHERE f.treinamento_id = ?
                      AND (SELECT COUNT(*) FROM etapa e WHERE e.fase_id = f.id) >
                          (SELECT COUNT(*) FROM etapa e JOIN progresso_usuario p ON p.etapa_id = e.id
                           AND p.usuario_id = ? AND p.status = \'concluido\' WHERE e.fase_id = f.id)
                    ORDER BY f.ordem LIMIT 1', [$trein, $alvo['id']]);
    $nome = explode(' ', $alvo['nome'])[0];
    $pct = $tot ? round($fez / $tot * 100) : 0;
    $nunca = !$alvo['ultimo_login'];

    // ── PARABÉNS (engajamento alto): reconhecimento + incentivo ──
    if ($tipo === 'parabens') {
        $assunto = "🎉 Mandou muito bem, {$nome}!";
        $paragrafos = ["Oi, {$nome}! 👏",
            "Passando só para reconhecer seu empenho: você está entre os que mais avançam na plataforma — já são {$pct}% do treinamento, com ótimo aproveitamento. Isso faz diferença de verdade no dia a dia de todo mundo."];
        $paragrafos[] = $prox
            ? "Se quiser seguir nesse ritmo, seu próximo passo é {$prox['nome']} — mas fique à vontade, você está voando. 🚀"
            : 'Continue assim — você é referência para a equipe. 🚀';
        $paragrafos[] = 'Obrigado por levar a sério cada etapa. Qualquer coisa, é só responder este e-mail. 🤝';
        return [$assunto, implode("\n\n", $paragrafos)];
    }

    // ── CONTINUE (engajamento médio): você está no caminho ──
    if ($tipo === 'continue') {
        $assunto = "💪 Você está no caminho, {$nome}!";
        $paragrafos = ["Oi, {$nome}! 👋",
            "Só um empurrãozinho: você já concluiu {$pct}% do treinamento — está indo bem e falta pouco para completar mais uma etapa importante."];
        if ($prox) $paragrafos[] = "Seu próximo passo é rapidinho: {$prox['nome']}. Poucos minutos e você avança de novo.";
        $paragrafos[] = 'Cada etapa conta para proteger você e quem trabalha do seu lado. Bora seguir? Qualquer dúvida, é só responder este e-mail. 🤝';
        return [$assunto, implode("\n\n", $paragrafos)];
    }

    // ── RESGATE (padrão: nunca acessou ou parado) ──
    $assunto = $nunca
        ? "🎉 {$nome}, sua jornada no Grupo Boticário está pronta para começar!"
        : "👋 Sentimos sua falta por aqui, {$nome}!";
    $paragrafos = ["Oi, {$nome}! 👋"];
    $paragrafos[] = $nunca
        ? 'Seu acesso à plataforma de treinamentos já está liberado — e a equipe está esperando por você no mapa.'
        : "Faz alguns dias que você não aparece na plataforma, e a sua jornada ficou te esperando exatamente onde você parou — já são {$pct}% do caminho.";
    if ($prox) $paragrafos[] = "Seu próximo passo é rapidinho: {$prox['nome']}. Poucos minutos e você avança de novo.";
    $paragrafos[] = 'Esses treinamentos existem para você crescer junto com a equipe — cada etapa conta de verdade. E qualquer dificuldade de acesso ou dúvida, é só responder este e-mail que a gente resolve junto. 🤝';
    return [$assunto, implode("\n\n", $paragrafos)];
}
// Tipo de e-mail guiado pelo ENGAJAMENTO do aluno (o pedido do produto):
// alto → parabéns · médio → incentivo/continue · baixo → resgate.
// Quem nunca acessou sempre recebe resgate (parabéns não faria sentido).
function lembrete_tipo(string $nivelEngaj, bool $nunca): string {
    if ($nunca) return 'resgate';
    return ['alto' => 'parabens', 'medio' => 'continue', 'baixo' => 'resgate'][$nivelEngaj] ?? 'resgate';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'lembrete') {
    require dirname(__DIR__, 2) . '/admin/_mail.php';
    $alvo = db_one("SELECT * FROM usuario WHERE id = ? AND cliente_id = ? AND papel = 'aluno'{$escopoTime}",
                   [(int) ($_POST['id'] ?? 0), $u['cliente_id']]);
    if (!$alvo) {
        lms_flash_set(false, 'Colaborador não encontrado.');
    } elseif ($alvo['lembrete_em'] && strtotime($alvo['lembrete_em']) > strtotime('-1 day')) {
        lms_flash_set(false, 'Calma no ímpeto: ' . explode(' ', $alvo['nome'])[0] . ' já recebeu um lembrete nas últimas 24h.');
    } else {
        $tipoLemb = in_array($_POST['tipo'] ?? '', ['resgate', 'parabens', 'continue'], true) ? $_POST['tipo'] : 'resgate';
        [$assuntoPadrao, $corpoPadrao] = lembrete_sugestao($alvo, $u, $tipoLemb);
        $titulo = trim((string) ($_POST['assunto'] ?? '')) ?: $assuntoPadrao;
        $corpo  = trim((string) ($_POST['mensagem'] ?? '')) ?: $corpoPadrao;
        $corpo  = mb_substr($corpo, 0, 4000);

        // texto editado → parágrafos do e-mail (CTA e assinatura são fixos)
        $ps = implode('', array_map(
            fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>',
            array_filter(preg_split("/\n{2,}/", $corpo), fn($p) => trim($p) !== '')
        ));
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#1F2A25;max-width:560px;line-height:1.6">'
          . $ps . '
          <p style="margin:24px 0">
            <a href="https://skope.cc/boticario/" style="background:#007A54;color:#fff;font-weight:bold;padding:12px 26px;border-radius:8px;text-decoration:none">Voltar para a plataforma ▶</a>
          </p>
          <p style="color:#69727D">— ' . e($u['nome']) . ' · ' . e($u['cliente_nome']) . '</p>
        </div>';
        $alt = $corpo . "\n\nAcesse: https://skope.cc/boticario/ — " . $u['nome'];

        [$ok, $erro] = portal_mail([$alvo['email']], $titulo, $html, $alt);
        if ($ok) {
            db_run('UPDATE usuario SET lembrete_em = CURRENT_TIMESTAMP WHERE id = ?', [$alvo['id']]);
            lms_flash_set(true, "Lembrete enviado para {$alvo['nome']} ✉️ — leve, acolhedor e com o próximo passo.");
        } else {
            lms_flash_set(false, 'Não consegui enviar agora. Tente novamente em instantes.');
            error_log('[boticario lembrete] ' . $erro);
        }
    }
    header('Location: /boticario/gestor/' . (!empty($_POST['volta']) ? '?aluno=' . (int) $_POST['volta'] : '')); exit;
}
$flashG = lms_flash_get();

/* ── universo ──────────────────────────────────────────────── */
$alunos = db_all("SELECT * FROM usuario WHERE cliente_id = ? AND papel = 'aluno'{$escopoTime} ORDER BY nome", [$u['cliente_id']]);
$nAlunos = count($alunos);
$alunoIds = array_map(fn($a) => (int) $a['id'], $alunos);
$idList = $alunoIds ? implode(',', $alunoIds) : '0';

// estrutura: treinamentos ativos (com a trilha dona) → fases → nº de etapas.
// Cada aluno vive UM treinamento (usuario.treinamento_id) — os números
// individuais são sempre relativos ao treinamento da própria pessoa.
$treinos = db_all('SELECT t.id, t.nome, t.ordem, tr.nome AS trilha_nome,
                          (SELECT COUNT(*) FROM usuario us WHERE us.treinamento_id = t.id AND us.papel = \'aluno\') AS n_alunos
                   FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id
                   WHERE tr.cliente_id = ? AND t.status = \'ativo\' ORDER BY tr.ordem, tr.id, t.ordem', [$u['cliente_id']]);
$fasesTrein = db_all('SELECT f.id, f.nome, f.ordem, f.treinamento_id,
                             (SELECT COUNT(*) FROM etapa e WHERE e.fase_id = f.id) AS n_etapas
                      FROM fase f JOIN treinamento t ON t.id = f.treinamento_id JOIN trilha tr ON tr.id = t.trilha_id
                      WHERE tr.cliente_id = ? AND t.status = \'ativo\'
                      ORDER BY tr.ordem, t.ordem, f.ordem', [$u['cliente_id']]);
$totPorTrein = [];
foreach ($fasesTrein as $f) $totPorTrein[(int) $f['treinamento_id']] = ($totPorTrein[(int) $f['treinamento_id']] ?? 0) + (int) $f['n_etapas'];
// total de etapas que valem para UM aluno = as do treinamento ativo dele
$totalDe = fn(array $a) => (int) ($totPorTrein[(int) ($a['treinamento_id'] ?? 0)] ?? 0);

// todo o progresso concluído dos alunos, de uma vez
$prog = db_all("SELECT p.usuario_id, p.etapa_id, p.acertos, p.total, p.tempo_seg, p.concluido_em,
                       e.fase_id, f.treinamento_id
                FROM progresso_usuario p
                JOIN etapa e ON e.id = p.etapa_id
                JOIN fase f ON f.id = e.fase_id
                JOIN treinamento t ON t.id = f.treinamento_id AND t.status = 'ativo'
                WHERE p.status = 'concluido' AND p.usuario_id IN ($idList)");

/* ── agregações ────────────────────────────────────────────── */
$porAluno = []; $porFase = []; $porAlunoTrein = [];
$somaAc = 0; $somaTt = 0; $somaTempo = 0;
foreach ($prog as $p) {
    $uid = (int) $p['usuario_id'];
    $porAluno[$uid]['feitas'] = ($porAluno[$uid]['feitas'] ?? 0) + 1;   // global (todos os treinamentos) — base do XP
    $porAluno[$uid]['ac'] = ($porAluno[$uid]['ac'] ?? 0) + (int) $p['acertos'];
    $porAluno[$uid]['tt'] = ($porAluno[$uid]['tt'] ?? 0) + (int) $p['total'];
    $porAluno[$uid]['tempo'] = ($porAluno[$uid]['tempo'] ?? 0) + (int) $p['tempo_seg'];
    $porAluno[$uid]['ultima'] = max($porAluno[$uid]['ultima'] ?? '', (string) $p['concluido_em']);
    $porFase[(int) $p['fase_id']][$uid] = ($porFase[(int) $p['fase_id']][$uid] ?? 0) + 1;
    $porAlunoTrein[$uid][(int) $p['treinamento_id']] = ($porAlunoTrein[$uid][(int) $p['treinamento_id']] ?? 0) + 1;
    $somaAc += (int) $p['acertos']; $somaTt += (int) $p['total']; $somaTempo += (int) $p['tempo_seg'];
}
// etapas feitas por um aluno DENTRO do treinamento ativo dele
$feitasDe = fn(array $a) => (int) ($porAlunoTrein[(int) $a['id']][(int) ($a['treinamento_id'] ?? 0)] ?? 0);

// KPIs — conformidade = média do avanço individual (cada um no seu treinamento)
$somaPct = 0; $comMeta = 0;
foreach ($alunos as $a) {
    $tot = $totalDe($a);
    if ($tot > 0) { $comMeta++; $somaPct += lms_pct($feitasDe($a), $tot); }
}
$conformidade  = $comMeta ? (int) round($somaPct / $comMeta) : 0;
$ativos7d      = count(array_filter($alunos, fn($a) => $a['ultimo_login'] && strtotime($a['ultimo_login']) >= strtotime('-7 days')));
$nuncaLogaram  = count(array_filter($alunos, fn($a) => !$a['ultimo_login']));
$aproveitamento = $somaTt > 0 ? round($somaAc / $somaTt * 100) : 0;
$horasCap      = $somaTempo / 3600;

// ── Comparação entre TIMES (conformidade média por gestor) ───
// Independe do escopo: mesmo o gestor enxerga a média dos outros times
// (só os números agregados, nunca os alunos de fora da equipe dele).
$gestores = db_all("SELECT id, nome FROM usuario WHERE cliente_id = ? AND papel = 'gestor' ORDER BY nome", [$u['cliente_id']]);
$timeAgg = [];
foreach (db_all("SELECT us.gestor_id AS gid, us.treinamento_id AS tid,
                  (SELECT COUNT(*) FROM progresso_usuario p JOIN etapa e ON e.id = p.etapa_id JOIN fase f ON f.id = e.fase_id
                   WHERE p.usuario_id = us.id AND p.status = 'concluido' AND f.treinamento_id = us.treinamento_id) AS feitas
                 FROM usuario us WHERE us.cliente_id = ? AND us.papel = 'aluno' AND us.gestor_id IS NOT NULL", [$u['cliente_id']]) as $r) {
    $tot = (int) ($totPorTrein[(int) $r['tid']] ?? 0);
    $pct = $tot > 0 ? lms_pct((int) $r['feitas'], $tot) : 0;
    $gid = (int) $r['gid'];
    $timeAgg[$gid][0] = ($timeAgg[$gid][0] ?? 0) + $pct;
    $timeAgg[$gid][1] = ($timeAgg[$gid][1] ?? 0) + 1;
}
$times = [];
foreach ($gestores as $g) {
    $gid = (int) $g['id']; $n = $timeAgg[$gid][1] ?? 0;
    $times[$gid] = ['nome' => $g['nome'], 'n' => $n, 'pct' => $n ? (int) round($timeAgg[$gid][0] / $n) : 0];
}
// comparação "meu time vs outros" — para o gestor (seu time) e para o
// admin quando está filtrando um time específico (?time).
$meuTime = $gestorEscopo ? ($times[$gestorEscopo] ?? null) : null;
$outrosPct = null;
if ($meuTime) {
    $soma = 0; $c = 0;
    foreach ($times as $gid => $t) if ($gid !== $gestorEscopo && $t['n'] > 0) { $soma += $t['pct']; $c++; }
    $outrosPct = $c ? (int) round($soma / $c) : null;
}

// panorama por treinamento: quantos alunos vivem nele + avanço médio
$porTreinamento = [];
foreach ($treinos as $t) {
    $doTrein = array_filter($alunos, fn($a) => (int) ($a['treinamento_id'] ?? 0) === (int) $t['id']);
    if (!$doTrein) continue;
    $tot = (int) ($totPorTrein[(int) $t['id']] ?? 0);
    $soma = 0;
    foreach ($doTrein as $a) $soma += $tot ? lms_pct($feitasDe($a), $tot) : 0;
    $porTreinamento[] = ['t' => $t, 'n' => count($doTrein), 'pct' => (int) round($soma / count($doTrein))];
}

// ── Jornada aberta por fase e por etapa (visão de equipe) ──────
// Onde cada colaborador está: "aqui" = primeira fase ainda não concluída.
// Por fase: quantos concluíram, quantos estão nela, quantos não chegaram.
// Por etapa: quantos já fizeram e o acerto médio de quem fez.
$etapasTrein = db_all('SELECT e.id, e.titulo, e.ordem, e.fase_id, f.treinamento_id
                       FROM etapa e JOIN fase f ON f.id = e.fase_id JOIN treinamento t ON t.id = f.treinamento_id JOIN trilha tr ON tr.id = t.trilha_id
                       WHERE tr.cliente_id = ? AND t.status = \'ativo\' ORDER BY f.ordem, e.ordem', [$u['cliente_id']]);
$feitoPor = [];
foreach ($prog as $p) $feitoPor[(int) $p['usuario_id']][(int) $p['etapa_id']] = $p;
$jornada = [];
foreach ($porTreinamento as $pt) {
    $tid = (int) $pt['t']['id'];
    $cols = [];
    foreach ($fasesTrein as $f) if ((int) $f['treinamento_id'] === $tid) $cols[(int) $f['id']] = ['f' => $f, 'aqui' => [], 'concluiram' => 0, 'etapas' => []];
    foreach ($etapasTrein as $e) if ((int) $e['treinamento_id'] === $tid && isset($cols[(int) $e['fase_id']])) $cols[(int) $e['fase_id']]['etapas'][(int) $e['id']] = ['e' => $e, 'n' => 0, 'ac' => 0, 'tt' => 0];
    $naoComecaram = []; $concluiram = []; $nAlunos = 0;
    foreach ($alunos as $a) {
        if ((int) ($a['treinamento_id'] ?? 0) !== $tid) continue;
        $nAlunos++;
        $uid = (int) $a['id']; $feitas = $feitoPor[$uid] ?? [];
        if (!$feitas) { $naoComecaram[] = $a; }
        $atual = null;
        foreach ($cols as $fid => &$c) {
            $nf = 0;
            foreach ($c['etapas'] as $eid => &$et) if (isset($feitas[$eid])) { $nf++; $et['n']++; $et['ac'] += (int) $feitas[$eid]['acertos']; $et['tt'] += (int) $feitas[$eid]['total']; }
            unset($et);
            $tot = count($c['etapas']);
            if ($tot && $nf >= $tot) $c['concluiram']++;
            elseif ($atual === null && $feitas) { $atual = $fid; $c['aqui'][] = ['a' => $a, 'feitas' => $nf, 'tot' => $tot]; }
        }
        unset($c);
        if ($feitas && $atual === null) $concluiram[] = $a;
    }
    $jornada[$tid] = ['pt' => $pt, 'cols' => $cols, 'nao' => $naoComecaram, 'fim' => $concluiram, 'n' => $nAlunos];
}

// conformidade por fase (% dos alunos DO treinamento que concluíram cada fase)
$heat = [];
foreach ($fasesTrein as $f) {
    $tid = (int) $f['treinamento_id'];
    $doTrein = array_filter($alunos, fn($a) => (int) ($a['treinamento_id'] ?? 0) === $tid);
    $done = 0;
    foreach ($doTrein as $a) {
        if ((int) $f['n_etapas'] > 0 && ($porFase[(int) $f['id']][(int) $a['id']] ?? 0) >= (int) $f['n_etapas']) $done++;
    }
    $heat[] = ['f' => $f, 'pct' => $doTrein ? (int) round($done / count($doTrein) * 100) : 0,
               'done' => $done, 'n' => count($doTrein)];
}

// radar de risco
$risco = [];
foreach ($alunos as $a) {
    $uid = (int) $a['id'];
    $tot = $totalDe($a); $feitas = $feitasDe($a);
    $ac = $porAluno[$uid]['ac'] ?? 0; $tt = $porAluno[$uid]['tt'] ?? 0;
    if (!$a['ultimo_login']) { $risco[] = ['a' => $a, 'tipo' => 'nunca', 'msg' => 'Nunca acessou a plataforma']; continue; }
    $dias = (int) floor((time() - strtotime($a['ultimo_login'])) / 86400);
    if ($tot > 0 && $feitas < $tot && $dias >= 4) { $risco[] = ['a' => $a, 'tipo' => 'parado', 'msg' => "Sem atividade há {$dias} dias · treinamento em " . lms_pct($feitas, $tot) . '%']; continue; }
    if ($tt > 20 && round($ac / $tt * 100) < 70) { $risco[] = ['a' => $a, 'tipo' => 'nota', 'msg' => 'Aproveitamento de ' . round($ac / $tt * 100) . '% — abaixo do esperado']; }
}

// ranking de engajamento (XP = 20/etapa + 10/acerto) + badges
$badgesPor = [];
foreach (db_all("SELECT usuario_id, COUNT(*) AS n FROM conquista_usuario WHERE usuario_id IN ($idList) GROUP BY usuario_id") as $b) {
    $badgesPor[(int) $b['usuario_id']] = (int) $b['n'];
}
$rank = [];
foreach ($alunos as $a) {
    $uid = (int) $a['id'];
    $xp = ($porAluno[$uid]['feitas'] ?? 0) * 20 + ($porAluno[$uid]['ac'] ?? 0) * 10;
    if ($xp > 0) $rank[] = ['a' => $a, 'xp' => $xp, 'badges' => $badgesPor[$uid] ?? 0];
}
usort($rank, fn($x, $y) => $y['xp'] <=> $x['xp']);
$rank = array_slice($rank, 0, 5);

// ── Engajamento por aluno ────────────────────────────────────
// Score 0–100 = evolução do treinamento (50%) + acessos (30%) + comentários
// (20%). Faixas: alto ≥ 66 · médio 33–65 · baixo < 33.
$comentPor = [];
foreach (db_all("SELECT usuario_id, COUNT(*) AS n FROM comentario
                 WHERE usuario_id IN ($idList) AND status = 'ativo' GROUP BY usuario_id") as $c) {
    $comentPor[(int) $c['usuario_id']] = (int) $c['n'];
}
// cálculo compartilhado (ver _lib/helpers.php) — aliases mantêm os call-sites
$engaj     = fn(int $e, int $a, int $c) => lms_engajamento($e, $a, $c);
$engajInfo = lms_engaj_info();
$engajTip  = fn(array $e) => lms_engaj_tip($e);

/* ── modo indivíduo (?aluno=ID): o dashboard inteiro vira a pessoa ── */
$foco = null;
if (!empty($_GET['aluno'])) {
    foreach ($alunos as $a) if ((int) $a['id'] === (int) $_GET['aluno']) $foco = $a;
}
$fid = $foco ? (int) $foco['id'] : 0;
$filtroAluno = $fid ? " AND p.usuario_id = {$fid}" : '';

$F = null;
if ($foco) {
    $pf = $porAluno[$fid] ?? ['ac' => 0, 'tt' => 0, 'tempo' => 0];
    $fTot = $totalDe($foco); $fFeitas = $feitasDe($foco);
    [$fPos, $fTotal, $fMedia, $fXp] = lms_posicao(lms_ranking((int) $u['cliente_id']), $fid);
    $F = [
        'trilha'  => lms_pct($fFeitas, $fTot),
        'aprov'   => ($pf['tt'] ?? 0) > 0 ? round($pf['ac'] / $pf['tt'] * 100) : 0,
        'minutos' => (int) round(($pf['tempo'] ?? 0) / 60),
        'xp'      => $fXp, 'pos' => $fPos, 'total' => $fTotal, 'media' => $fMedia,
        'dias'    => $foco['ultimo_login'] ? (int) floor((time() - strtotime($foco['ultimo_login'])) / 86400) : null,
    ];
    $F['engaj'] = $engaj($F['trilha'], (int) ($foco['acessos'] ?? 0), $comentPor[$fid] ?? 0);
    // situação de risco (mesmas regras do radar)
    if (!$foco['ultimo_login']) $F['risco'] = ['🚫', 'Nunca acessou a plataforma'];
    elseif ($fTot > 0 && $fFeitas < $fTot && $F['dias'] >= 4) $F['risco'] = ['⏸️', "Sem atividade há {$F['dias']} dias"];
    elseif (($pf['tt'] ?? 0) > 20 && $F['aprov'] < 70) $F['risco'] = ['📉', "Aproveitamento de {$F['aprov']}% — abaixo do esperado"];
    else $F['risco'] = null;
}

// atividade dos últimos 14 dias (da equipe ou do indivíduo em foco)
$atividade = [];
for ($i = 13; $i >= 0; $i--) $atividade[date('Y-m-d', strtotime("-{$i} days"))] = 0;
foreach (db_all("SELECT date(concluido_em) AS d, COUNT(*) AS n FROM progresso_usuario p
                 WHERE p.status = 'concluido' AND p.usuario_id IN ($idList){$filtroAluno}
                   AND p.concluido_em >= datetime('now', '-14 days')
                 GROUP BY date(concluido_em)") as $r) {
    if (isset($atividade[$r['d']])) $atividade[$r['d']] = (int) $r['n'];
}
$maxAtv = max(1, max($atividade));

// conclusões recentes (feed — idem)
$recentes = db_all("SELECT p.concluido_em, p.acertos, p.total, us.nome AS aluno, us.avatar_genero, us.avatar_cor_pele, us.foto_url,
                           e.titulo AS etapa, f.nome AS fase_nome
                    FROM progresso_usuario p
                    JOIN usuario us ON us.id = p.usuario_id
                    JOIN etapa e ON e.id = p.etapa_id
                    JOIN fase f ON f.id = e.fase_id
                    WHERE p.status = 'concluido' AND p.usuario_id IN ($idList){$filtroAluno}
                    ORDER BY p.concluido_em DESC LIMIT 7");

function tempo_atras(?string $ts): string {
    if (!$ts) return '—';
    $s = time() - strtotime($ts);
    if ($s < 3600) return 'há ' . max(1, floor($s / 60)) . ' min';
    if ($s < 86400) return 'há ' . floor($s / 3600) . ' h';
    return 'há ' . floor($s / 86400) . ' d';
}
// cor do heatmap: 0% vermelho → 50% âmbar → 100% verde (fundo suave + texto forte)
function heat_css(int $pct): string {
    $h = (int) round($pct * 1.35); // 0..135 (vermelho→verde)
    // tema escuro (Car): fundo tingido mais forte + TEXTO CLARO (o padrão usa
    // texto escuro, que fica ilegível no dark).
    if (($_COOKIE['lms_skin'] ?? '') === 'car') {
        return "background:hsla({$h},48%,42%,.30); border:1px solid hsla({$h},55%,58%,.55); color:hsl({$h},80%,80%)";
    }
    return "background:hsla({$h},62%,48%,.16); border:1.5px solid hsla({$h},62%,42%,.55); color:hsl({$h},72%,26%)";
}

/* ── detalhe por aluno (?aluno=ID) — mantido ───────────────── */
$linhas = [];
foreach ($alunos as $a) {
    $uid = (int) $a['id'];
    $tot = $totalDe($a); $feitas = $feitasDe($a);
    $tt = $porAluno[$uid]['tt'] ?? 0; $ac = $porAluno[$uid]['ac'] ?? 0;
    $status = !$a['ultimo_login'] ? 'nunca' : ($feitas >= $tot && $tot > 0 ? 'concluido' : ($feitas > 0 ? 'andamento' : 'nao_iniciado'));
    $linhas[] = ['u' => $a, 'feitas' => $feitas, 'status' => $status, 'pct' => lms_pct($feitas, $tot),
                 'acerto' => $tt > 0 ? round($ac / $tt * 100) : null, 'tempo' => $porAluno[$uid]['tempo'] ?? 0,
                 'engaj' => $engaj(lms_pct($feitas, $tot), (int) ($a['acessos'] ?? 0), $comentPor[$uid] ?? 0)];
}
$detalhe = null;
if (!empty($_GET['aluno'])) {
    foreach ($linhas as $l) if ((int) $l['u']['id'] === (int) $_GET['aluno']) $detalhe = $l;
    if ($detalhe) {
        // fases do treinamento ATIVO do colaborador, com o avanço dele em cada uma
        $detalhe['fases'] = db_all(
            'SELECT f.nome, f.ordem, COUNT(e.id) AS total,
                    SUM(CASE WHEN p.status = \'concluido\' THEN 1 ELSE 0 END) AS feitas,
                    COALESCE(SUM(p.acertos), 0) AS acertos, COALESCE(SUM(p.total), 0) AS questoes,
                    COALESCE(SUM(p.tempo_seg), 0) AS tempo
             FROM fase f
             JOIN etapa e ON e.fase_id = f.id
             LEFT JOIN progresso_usuario p ON p.etapa_id = e.id AND p.usuario_id = ? AND p.status = \'concluido\'
             WHERE f.treinamento_id = ?
             GROUP BY f.id ORDER BY f.ordem', [$detalhe['u']['id'], (int) ($detalhe['u']['treinamento_id'] ?? 0)]);
    }
}

$pills = [
    'concluido'    => '<span class="lms-pill is-success">Concluído</span>',
    'andamento'    => '<span class="lms-pill is-warning">Em andamento</span>',
    'nao_iniciado' => '<span class="lms-pill">Não iniciado</span>',
    'nunca'        => '<span class="lms-pill is-error">Nunca logou</span>',
];
$riscoIcone = ['nunca' => '🚫', 'parado' => '⏸️', 'nota' => '📉'];

// Comunidade: fila de moderação (pendentes) + publicados sem resposta
$comCampos = "c.id, c.texto, c.criado_em, c.fase_id, f.nome AS fase_nome,
              u2.nome AS autor, u2.avatar_genero, u2.avatar_cor_pele, u2.foto_url
              FROM comentario c
              JOIN usuario u2 ON u2.id = c.usuario_id
              JOIN fase f ON f.id = c.fase_id";
$pendentes = db_all("SELECT {$comCampos} WHERE c.cliente_id = ? AND c.status = 'pendente'
                     ORDER BY c.id DESC LIMIT 4", [$u['cliente_id']]);
$duvidas = db_all("SELECT {$comCampos} WHERE c.cliente_id = ? AND c.parent_id IS NULL AND c.status = 'ativo'
                     AND NOT EXISTS (SELECT 1 FROM comentario r
                                     WHERE r.parent_id = c.id AND r.status = 'ativo')
                   ORDER BY c.id DESC LIMIT 4", [$u['cliente_id']]);

lms_head('Dashboard de gestão', $u);
lms_topbar($u);
?>
<main class="lms-container" style="padding-top:28px; padding-bottom:64px; max-width:1200px">

  <div class="lms-row" style="justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap">
    <div>
      <h1 style="font-size:var(--lms-title-md)">Dashboard de gestão</h1>
      <p class="lms-muted lms-small" style="margin-top:4px"><?= e($u['cliente_nome']) ?> · <?= count($treinos) ?> treinamento<?= count($treinos) === 1 ? '' : 's' ?> ativo<?= count($treinos) === 1 ? '' : 's' ?> · <?= date('d/m/Y') ?></p>
    </div>
    <div class="lms-row" style="gap:10px; flex-wrap:nowrap">
      <a class="lms-btn lms-btn-game lms-btn-sm" style="flex:none" title="Versão imprimível (PDF) ou envio por e-mail"
         href="/boticario/gestor/relatorio<?= $foco ? '?aluno=' . (int) $foco['id'] : ($timeFoco ? '?time=' . (int) $timeFoco['id'] : '') ?>">📄 Gerar relatório</a>
      <?php if ($foco): ?>
      <a class="lms-btn lms-btn-outline lms-btn-sm" style="flex:none" title="Editar cadastro e treinamento deste aluno"
         href="/boticario/admin/?editar=<?= (int) $foco['id'] ?>#form-usuario">✏️ Editar aluno</a>
      <span class="lms-pill is-primary" style="display:inline-flex; align-items:center; gap:8px; padding:8px 8px 8px 14px">
        Visão individual: <strong><?= e($foco['nome']) ?></strong>
        <a href="/boticario/gestor/" title="Voltar à visão da equipe"
           style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:rgba(31,42,37,.15); color:inherit; text-decoration:none; font-weight:800">✕</a>
      </span>
      <?php else: ?>
      <div style="position:relative; min-width:260px">
        <input class="lms-input" id="busca-topo" list="lista-colabs" type="search"
               placeholder="🔎 Ver dashboard de um colaborador…" autocomplete="off" style="padding:10px 14px">
        <datalist id="lista-colabs">
          <?php foreach ($alunos as $a): ?>
          <option value="<?= e($a['nome']) ?>" data-id="<?= (int) $a['id'] ?>"><?= e($a['email']) ?></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <span class="lms-pill is-primary" style="flex:none"><?= $nAlunos ?> colaborador<?= $nAlunos === 1 ? '' : 'es' ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flashG): ?>
  <div class="lms-alert <?= $flashG['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:16px">
    <?= $flashG['ok'] ? '✅' : '⚠️' ?> <div><?= e($flashG['msg']) ?></div>
  </div>
  <?php endif; ?>

  <!-- ── KPIs estratégicos (equipe ou indivíduo) ─────────── -->
  <?php if ($foco): ?>
  <div class="dash-kpis">
    <div class="lms-stat dash-entra" style="--i:0" data-tip="Quanto deste aluno já concluiu do treinamento ativo dele (etapas concluídas ÷ total).">
      <div class="lms-stat-value kpi-num" data-val="<?= $F['trilha'] ?>" data-suf="%" style="color:<?= $F['trilha'] >= 80 ? 'var(--lms-success)' : ($F['trilha'] >= 50 ? '#9A6A0D' : 'var(--lms-error)') ?>">0%</div>
      <div class="lms-stat-label">Treinamento concluído</div>
    </div>
    <div class="lms-stat dash-entra" style="--i:1" data-tip="Percentual de acertos deste aluno nos minigames que concluiu.">
      <div class="lms-stat-value kpi-num" data-val="<?= $F['aprov'] ?>" data-suf="%">0%</div>
      <div class="lms-stat-label">Aproveitamento</div>
    </div>
    <div class="lms-stat dash-entra" style="--i:2" data-tip="Tempo total que este aluno passou nas etapas do treinamento.">
      <div class="lms-stat-value kpi-num" data-val="<?= $F['minutos'] ?>" data-suf="min">0</div>
      <div class="lms-stat-label">Tempo de capacitação</div>
    </div>
    <div class="lms-stat dash-entra" style="--i:3" data-tip="Pontos de experiência acumulados e posição no ranking da equipe.">
      <div class="lms-stat-value kpi-num" data-val="<?= $F['xp'] ?>" style="color:var(--lms-primary)">0</div>
      <div class="lms-stat-label">XP · <?= $F['pos'] ?>º de <?= $F['total'] ?> no ranking</div>
    </div>
    <div class="lms-stat dash-entra" style="--i:4" data-tip="Quando este aluno acessou a plataforma pela última vez.">
      <div class="lms-stat-value" style="color:<?= $F['dias'] === null ? 'var(--lms-error)' : ($F['dias'] >= 4 ? '#9A6A0D' : 'var(--lms-success)') ?>">
        <?= $F['dias'] === null ? 'nunca' : ($F['dias'] === 0 ? 'hoje' : 'há ' . $F['dias'] . 'd') ?>
      </div>
      <div class="lms-stat-label">Último acesso</div>
    </div>
    <?php $eg = $F['engaj']; [$egLbl, $egIco, $egCls, $egCor] = $engajInfo[$eg['nivel']]; ?>
    <div class="lms-stat dash-entra" style="--i:5" data-tip="<?= e($engajTip($eg)) ?>">
      <div class="lms-stat-value" style="color:<?= $egCor ?>"><?= $egIco ?> <?= $egLbl ?></div>
      <div class="lms-stat-label">Engajamento · <?= $eg['score'] ?>/100</div>
    </div>
  </div>
  <?php else: ?>
  <div class="dash-kpis">
    <div class="lms-stat dash-entra" style="--i:0" data-tip="Total de alunos cadastrados na plataforma — tenham acessado ou não.">
      <div class="lms-stat-value kpi-num" data-val="<?= count($alunos) ?>" style="color:var(--lms-heading, #1F2A25)">0</div>
      <div class="lms-stat-label">Alunos cadastrados</div>
    </div>
    <div class="lms-stat dash-entra" style="--i:1" data-tip="Média do avanço de cada aluno no próprio treinamento ativo (etapas concluídas ÷ total de etapas).">
      <div class="lms-stat-value kpi-num" data-val="<?= $conformidade ?>" data-suf="%" style="color:<?= $conformidade >= 80 ? 'var(--lms-success)' : ($conformidade >= 50 ? '#9A6A0D' : 'var(--lms-error)') ?>">0%</div>
      <div class="lms-stat-label">Conformidade média</div>
    </div>
    <div class="lms-stat dash-entra" style="--i:2" data-tip="Alunos que fizeram ao menos um acesso nos últimos 7 dias.">
      <div class="lms-stat-value kpi-num" data-val="<?= $ativos7d ?>" style="color:var(--lms-primary)">0</div>
      <div class="lms-stat-label">Ativos nos últimos 7 dias</div>
    </div>
    <div class="lms-stat dash-entra" id="nunca-abrir" style="--i:3<?= $nuncaLogaram > 0 ? '; cursor:pointer' : '' ?>" <?= $nuncaLogaram > 0 ? 'role="button" tabindex="0"' : '' ?> data-tip="<?= $nuncaLogaram > 0 ? 'Clique para ver quem nunca acessou e agir (enviar e-mail).' : 'Ninguém deixou de acessar — tudo em dia.' ?>">
      <div class="lms-stat-value kpi-num" data-val="<?= $nuncaLogaram ?>" style="color:<?= $nuncaLogaram > 0 ? 'var(--lms-error)' : 'var(--lms-success)' ?>">0</div>
      <div class="lms-stat-label">Nunca acessaram <?php if ($nuncaLogaram > 0): ?><span aria-hidden="true" style="opacity:.55">↗</span><?php endif; ?></div>
    </div>
    <div class="lms-stat dash-entra" style="--i:4" data-tip="Percentual médio de acertos nos minigames concluídos por toda a equipe.">
      <div class="lms-stat-value kpi-num" data-val="<?= $aproveitamento ?>" data-suf="%">0%</div>
      <div class="lms-stat-label">Aproveitamento médio</div>
    </div>
    <div class="lms-stat dash-entra" style="--i:5" data-tip="Tempo total somado que a equipe passou nas etapas (leituras + minigames).">
      <div class="lms-stat-value kpi-num" data-val="<?= round($horasCap, 1) ?>" data-suf="h" data-dec="1">0h</div>
      <div class="lms-stat-label">Horas de capacitação</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$foco && $timeFoco): ?>
  <!-- ── Filtro de time ativo (admin/RH vendo a equipe do fulano) ── -->
  <div class="lms-alert is-info lms-pop" style="margin-top:16px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap">
    <div>👁 Vendo a equipe de <strong><?= e($timeFoco['nome']) ?></strong> — todos os números abaixo são só deste time.</div>
    <span style="display:flex; gap:8px"><a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/gestor/times">← Times</a><a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/gestor/">Ver todos ✕</a></span>
  </div>
  <?php endif; ?>

  <?php if (!$foco && $meuTime): ?>
  <!-- ── Time vs. os outros (gestor no seu time, ou admin filtrando um time) ── -->
  <div class="lms-card-game dash-entra" style="--i:4; margin-top:18px">
    <div class="lms-row" style="justify-content:space-between; flex-wrap:wrap; gap:14px">
      <div>
        <h3>🧑‍🤝‍🧑 <?= $timeFoco ? 'Este time vs. os outros' : 'Meu time vs. os outros' ?></h3>
        <p class="lms-muted lms-small" style="margin-top:4px"><?= $meuTime['n'] ?> pessoa<?= $meuTime['n'] === 1 ? '' : 's' ?> <?= $timeFoco ? 'na equipe' : 'na sua equipe' ?> · conformidade média</p>
      </div>
      <div class="lms-row" style="gap:26px; align-items:center; flex-wrap:wrap">
        <div style="text-align:center">
          <div style="font-family:var(--lms-font-ui); font-weight:800; font-size:28px; color:var(--lms-primary)"><?= $meuTime['pct'] ?>%</div>
          <div class="lms-small lms-muted"><?= $timeFoco ? 'este time' : 'seu time' ?></div>
        </div>
        <div style="text-align:center">
          <div style="font-family:var(--lms-font-ui); font-weight:800; font-size:28px; color:var(--lms-ink-soft, #69727D)"><?= $outrosPct === null ? '—' : $outrosPct . '%' ?></div>
          <div class="lms-small lms-muted">média dos outros times</div>
        </div>
        <?php if ($outrosPct !== null): $d = $meuTime['pct'] - $outrosPct; ?>
        <span class="lms-pill <?= $d >= 0 ? 'is-success' : 'is-error' ?>" style="font-size:14px; padding:8px 14px">
          <?= $d >= 0 ? '▲ ' . $d . ' p.p. acima' : '▼ ' . abs($d) . ' p.p. abaixo' ?>
        </span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php elseif (!$foco && $ehAdmin && count(array_filter($times, fn($t) => $t['n'] > 0)) > 0): ?>
  <!-- ── admin/RH sem filtro: atalho para a aba Times ─────── -->
  <a class="lms-card-game dash-entra" href="/boticario/gestor/times" style="--i:4; margin-top:18px; display:flex; align-items:center; justify-content:space-between; gap:12px; text-decoration:none; color:inherit">
    <div><h3>🧑‍🤝‍🧑 Times</h3><p class="lms-muted lms-small" style="margin-top:4px">Conformidade e engajamento de cada equipe — entre no time de cada gestor.</p></div>
    <span class="lms-btn lms-btn-outline lms-btn-sm" style="flex:none">Ver times ▶</span>
  </a>
  <?php endif; ?>

  <!-- ── Jornada (panorama por treinamento ou fases do indivíduo) ── -->
  <div class="lms-card-game dash-entra" style="--i:5; margin-top:18px">
    <div class="lms-row" style="justify-content:space-between; margin-bottom:14px">
      <h3>🗺️ <?= $foco ? 'Onde ' . e(explode(' ', $foco['nome'])[0]) . ' está na jornada' : 'Onde a equipe está na jornada' ?></h3>
      <span class="lms-small lms-muted"><?= $foco ? 'etapas concluídas por fase' : 'geral e aberto por fase — clique numa fase para ver por etapa' ?></span>
    </div>
    <div class="dash-funil">
      <?php if ($foco): $fasesFoco = array_values(array_filter($fasesTrein, fn($f) => (int) $f['treinamento_id'] === (int) ($foco['treinamento_id'] ?? 0))); ?>
      <?php if (!$fasesFoco): ?>
      <p class="lms-small lms-muted"><?= $foco['treinamento_id'] ? 'O treinamento deste colaborador ainda não tem fases publicadas.' : 'Este colaborador ainda não tem treinamento ativo — atribua um em Usuários.' ?></p>
      <?php else: ?>
      <!-- linhas de referência: média DESTE aluno vs média geral da equipe -->
      <div class="dash-media is-funil is-geral" style="--alt:<?= round(($conformidade / 100) * 110) ?>px">
        <span class="dash-media-tag is-geral">média geral <?= $conformidade ?>%</span>
      </div>
      <div class="dash-media is-funil is-aluno" style="--alt:<?= round(($F['trilha'] / 100) * 110) ?>px">
        <span class="dash-media-tag is-aluno"><?= e(explode(' ', $foco['nome'])[0]) ?> <?= $F['trilha'] ?>%</span>
      </div>
      <?php foreach ($fasesFoco as $f): $o = (int) $f['ordem'];
          $tf = (int) $f['n_etapas']; $ff = (int) ($porFase[(int) $f['id']][$fid] ?? 0);
          $pctF = lms_pct($ff, $tf); ?>
      <div class="dash-funil-col" data-tip="Fase <?= $o ?> · <?= e($f['nome']) ?> — <?= $ff ?>/<?= $tf ?> etapas (<?= $pctF ?>%)">
        <div class="dash-funil-barra"><div class="dash-funil-fill cresce<?= $pctF >= 100 ? ' is-ok' : ($pctF === 0 ? ' is-zero' : '') ?>" style="--w:<?= max(4, $pctF) ?>%"></div></div>
        <div class="dash-funil-n" style="font-size:16px"><?= $ff ?>/<?= $tf ?></div>
        <div class="dash-funil-papel"><?= e($f['nome']) ?></div>
        <div class="dash-funil-fase">Fase <?= $o ?> · <?= $pctF ?>%</div>
      </div>
      <?php endforeach; ?>
      <div class="dash-funil-col">
        <div class="dash-funil-barra lms-center" style="align-items:center"><span style="font-size:34px"><?= $F['trilha'] >= 100 ? '🏆' : '🚀' ?></span></div>
        <div class="dash-funil-n" style="font-size:16px; color:var(--lms-primary)"><?= $F['trilha'] ?>%</div>
        <div class="dash-funil-papel">do treinamento</div>
        <?php $treinFoco = array_values(array_filter($treinos, fn($t) => (int) $t['id'] === (int) ($foco['treinamento_id'] ?? 0))); ?>
        <div class="dash-funil-fase"><?= e($treinFoco[0]['nome'] ?? '') ?></div>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <?php if (!$porTreinamento): ?>
      <p class="lms-small lms-muted">Nenhum colaborador com treinamento ativo ainda — atribua os treinamentos em Usuários.</p>
      <?php else: foreach ($jornada as $tid => $J): $pt = $J['pt']; $t = $pt['t']; $den = max(1, $J['n']);
          $pctDe = fn(int $q) => (int) round($q / $den * 100); ?>
      <div class="dash-jor" data-jor="<?= $tid ?>">
        <div class="dash-jor-topo">
          <div><strong style="font-family:var(--lms-font-ui)"><?= e($t['nome']) ?></strong> <span class="lms-small lms-muted">· 🧭 <?= e($t['trilha_nome']) ?> · 👤 <?= $J['n'] ?> colaborador<?= $J['n'] === 1 ? '' : 'es' ?></span></div>
          <div class="dash-jor-geral" title="Média das porcentagens de conclusão de cada colaborador">
            <div class="lms-progress" style="height:8px; width:150px"><div class="lms-progress-fill" style="width:<?= $pt['pct'] ?>%"></div></div>
            <strong style="font-family:var(--lms-font-ui)"><?= $pt['pct'] ?>%</strong> <span class="lms-small lms-muted">avanço médio</span>
          </div>
        </div>
        <div class="dash-funil dash-jor-funil">
          <button type="button" class="dash-funil-col dash-jor-col" data-painel="nao-<?= $tid ?>" data-tip="<?= count($J['nao']) ?> ainda não fizeram nenhuma etapa">
            <div class="dash-funil-barra"><div class="dash-jor-bar"><span class="is-nao" style="height:<?= 100 - $pctDe(count($J['nao'])) ?>%"></span><span class="is-grey" style="height:<?= $pctDe(count($J['nao'])) ?>%"></span></div></div>
            <div class="dash-funil-n"><?= count($J['nao']) ?></div>
            <div class="dash-funil-papel">Não começaram</div>
            <div class="dash-funil-fase">0 etapas</div>
          </button>
          <?php foreach ($J['cols'] as $fid => $c): $aqui = count($c['aqui']); $conc = $c['concluiram']; $naoCheg = max(0, $J['n'] - $aqui - $conc); $o = (int) $c['f']['ordem']; ?>
          <button type="button" class="dash-funil-col dash-jor-col" data-painel="f-<?= $fid ?>"
                  data-tip="Fase <?= $o ?> · <?= e($c['f']['nome']) ?> — <?= $aqui ?> aqui · <?= $conc ?> concluíram · <?= $naoCheg ?> não chegaram">
            <div class="dash-funil-barra"><div class="dash-jor-bar">
              <span class="is-nao" style="height:<?= $pctDe($naoCheg) ?>%"></span>
              <span class="is-aqui" style="height:<?= $pctDe($aqui) ?>%"></span>
              <span class="is-ok" style="height:<?= $pctDe($conc) ?>%"></span>
            </div></div>
            <div class="dash-funil-n"><?= $aqui ?></div>
            <div class="dash-funil-papel">Fase <?= $o ?> · <?= e($c['f']['nome']) ?></div>
            <div class="dash-funil-fase"><?= $conc ?> concluíram · <?= $pctDe($conc) ?>%</div>
          </button>
          <?php endforeach; ?>
          <button type="button" class="dash-funil-col dash-jor-col" data-painel="fim-<?= $tid ?>" data-tip="<?= count($J['fim']) ?> concluíram o treinamento inteiro">
            <div class="dash-funil-barra"><div class="dash-jor-bar"><span class="is-nao" style="height:<?= 100 - $pctDe(count($J['fim'])) ?>%"></span><span class="is-ok" style="height:<?= $pctDe(count($J['fim'])) ?>%"></span></div></div>
            <div class="dash-funil-n" style="color:var(--lms-success)"><?= count($J['fim']) ?></div>
            <div class="dash-funil-papel">Concluíram 🏆</div>
            <div class="dash-funil-fase">treinamento inteiro</div>
          </button>
        </div>
        <div class="dash-jor-legenda"><span><i class="is-ok"></i>concluíram a fase</span><span><i class="is-aqui"></i>estão nela agora</span><span><i class="is-nao"></i>ainda não chegaram</span><span class="lms-muted">· o número grande é quem está na fase</span></div>

        <div class="dash-jor-painel lms-hide" data-painel-id="nao-<?= $tid ?>">
          <div class="dash-jor-painel-topo"><strong>Ainda não começaram</strong><span class="lms-small lms-muted"><?= count($J['nao']) ?> de <?= $J['n'] ?></span></div>
          <div class="dash-jor-quem"><?php foreach ($J['nao'] as $a): ?><a class="dash-jor-chip" href="/boticario/gestor/?aluno=<?= (int) $a['id'] ?>"><?= e($a['nome']) ?><small><?= $a['ultimo_login'] ? 'entrou, não jogou' : 'nunca acessou' ?></small></a><?php endforeach; if (!$J['nao']): ?><span class="lms-small lms-muted">Todo mundo já começou. 🎉</span><?php endif; ?></div>
        </div>
        <?php foreach ($J['cols'] as $fid => $c): $aqui = count($c['aqui']); $conc = $c['concluiram']; $naoCheg = max(0, $J['n'] - $aqui - $conc); ?>
        <div class="dash-jor-painel lms-hide" data-painel-id="f-<?= $fid ?>">
          <div class="dash-jor-painel-topo">
            <strong>Fase <?= (int) $c['f']['ordem'] ?> · <?= e($c['f']['nome']) ?></strong>
            <span class="lms-small lms-muted"><?= $aqui ?> aqui · <?= $conc ?> concluíram · <?= $naoCheg ?> não chegaram</span>
          </div>
          <div class="dash-jor-etapas">
            <?php foreach ($c['etapas'] as $et): $pctE = $pctDe($et['n']); $ac = $et['tt'] > 0 ? (int) round($et['ac'] / $et['tt'] * 100) : null; ?>
            <div class="dash-jor-etapa">
              <span class="dash-jor-etapa-nome"><?= (int) $et['e']['ordem'] ?>. <?= e($et['e']['titulo']) ?></span>
              <div class="dash-jor-etapa-bar" title="<?= $et['n'] ?> de <?= $J['n'] ?> concluíram (<?= $pctE ?>%)"><span style="width:<?= $pctE ?>%"></span></div>
              <span class="dash-jor-etapa-n"><?= $et['n'] ?>/<?= $J['n'] ?></span>
              <span class="dash-jor-etapa-ac"><?= $ac === null ? '—' : $ac . '% acerto' ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="dash-jor-quem"><strong class="lms-small">Quem está aqui:</strong>
            <?php foreach ($c['aqui'] as $q): ?><a class="dash-jor-chip" href="/boticario/gestor/?aluno=<?= (int) $q['a']['id'] ?>"><?= e($q['a']['nome']) ?><small><?= $q['feitas'] ?>/<?= $q['tot'] ?> etapas</small></a><?php endforeach; ?>
            <?php if (!$c['aqui']): ?><span class="lms-small lms-muted">ninguém parado nesta fase agora</span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="dash-jor-painel lms-hide" data-painel-id="fim-<?= $tid ?>">
          <div class="dash-jor-painel-topo"><strong>Concluíram o treinamento 🏆</strong><span class="lms-small lms-muted"><?= count($J['fim']) ?> de <?= $J['n'] ?></span></div>
          <div class="dash-jor-quem"><?php foreach ($J['fim'] as $a): ?><a class="dash-jor-chip" href="/boticario/gestor/?aluno=<?= (int) $a['id'] ?>"><?= e($a['nome']) ?></a><?php endforeach; if (!$J['fim']): ?><span class="lms-small lms-muted">Ninguém ainda — a primeira pessoa a fechar aparece aqui.</span><?php endif; ?></div>
        </div>
      </div>
      <?php endforeach; endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="dash-grid" style="margin-top:18px">
    <div class="lms-stack-4 dash-main" style="min-width:0">

      <!-- ── Atividade 14 dias ─────────────────────────── -->
      <?php $mediaAtv = array_sum($atividade) / 14; ?>
      <div class="lms-card-game dash-entra" style="--i:6">
        <div class="lms-row" style="justify-content:space-between; margin-bottom:6px">
          <h3>📈 Ritmo de treinamento</h3>
          <span class="lms-small lms-muted">etapas concluídas · últimos 14 dias</span>
        </div>
        <div class="dash-chart">
          <!-- linha de referência: média diária -->
          <div class="dash-media" style="--alt:<?= round(19 + ($mediaAtv / $maxAtv) * 92) ?>px">
            <span class="dash-media-tag">média <?= number_format($mediaAtv, 1, ',', '.') ?>/dia</span>
          </div>
          <?php $d = 0; foreach ($atividade as $dia => $n): ?>
          <div class="dash-chart-col" data-tip="<?= date('d/m', strtotime($dia)) ?> — <?= $n ?> etapa<?= $n === 1 ? '' : 's' ?> concluída<?= $n === 1 ? '' : 's' ?>">
            <span class="dash-chart-n"><?= $n ?: '' ?></span>
            <div class="dash-chart-track"><div class="dash-chart-bar" style="--h:<?= round($n / $maxAtv * 100) ?>%; --i:<?= $d++ ?>"></div></div>
            <span class="dash-chart-dia"><?= date('d', strtotime($dia)) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ── Heatmap de conformidade por fase (equipe ou indivíduo) ── -->
      <div class="lms-card-game dash-entra" style="--i:7">
        <div class="lms-row" style="justify-content:space-between; margin-bottom:12px">
          <h3>✅ <?= $foco ? 'Fases de ' . e(explode(' ', $foco['nome'])[0]) : 'Conformidade por fase' ?></h3>
          <span class="lms-small lms-muted"><?= $foco ? '% das etapas concluídas' : '% dos alunos do treinamento com a fase concluída' ?></span>
        </div>
        <?php
        $nomesTrein = [];
        foreach ($treinos as $t) $nomesTrein[(int) $t['id']] = $t;
        $heatMostrar = $foco
            ? array_values(array_filter($heat, fn($h) => (int) $h['f']['treinamento_id'] === (int) ($foco['treinamento_id'] ?? 0)))
            : $heat;
        $treinAtualH = 0; $primeiro = true;
        foreach ($heatMostrar as $h): $f = $h['f'];
          $pctH = $foco ? lms_pct((int) ($porFase[(int) $f['id']][$fid] ?? 0), (int) $f['n_etapas']) : $h['pct'];
          $tit  = $foco ? ($porFase[(int) $f['id']][$fid] ?? 0) . '/' . $f['n_etapas'] . ' etapas' : $h['done'] . '/' . $h['n'] . ' colaboradores'; ?>
          <?php if (!$foco && (int) $f['treinamento_id'] !== $treinAtualH): $treinAtualH = (int) $f['treinamento_id']; $tH = $nomesTrein[$treinAtualH] ?? null; ?>
            <p class="lms-small" style="font-family:var(--lms-font-ui); font-weight:700; margin:<?= $primeiro ? '0' : '14px' ?> 0 8px">
              📁 <?= e($tH['nome'] ?? '') ?> <span class="lms-muted" style="font-weight:400">· 🧭 <?= e($tH['trilha_nome'] ?? '') ?></span></p>
          <?php $primeiro = false; endif; ?>
          <div class="dash-heat-item" style="<?= heat_css($pctH) ?>" title="Fase <?= (int) $f['ordem'] ?> · <?= e($f['nome']) ?> — <?= e($tit) ?>">
            <span class="dash-heat-nome">F<?= (int) $f['ordem'] ?> · <?= e($f['nome']) ?></span>
            <span class="dash-heat-pct"><?= $pctH ?>%</span>
          </div>
        <?php endforeach; if (!$heatMostrar): ?>
        <p class="lms-small lms-muted">Nenhuma fase publicada ainda.</p>
        <?php endif; ?>
      </div>

    </div>
    <div class="lms-stack-4 dash-side" style="min-width:0">

      <?php if ($foco): ?>
      <!-- ── Situação do colaborador em foco ───────────── -->
      <?php
        $tipoEmail = lembrete_tipo($F['engaj']['nivel'], !$foco['ultimo_login']);
        $emailBtnLbl = ['resgate' => '✉️ Enviar e-mail de resgate', 'parabens' => '🎉 Enviar parabéns', 'continue' => '💪 Enviar incentivo'][$tipoEmail];
        $lembF = $foco['lembrete_em'] && strtotime($foco['lembrete_em']) > strtotime('-1 day');
        $focoGestor = !empty($foco['gestor_id']) ? db_one("SELECT nome FROM usuario WHERE id = ?", [(int) $foco['gestor_id']]) : null;
      ?>
      <div class="lms-card-pastel <?= $F['risco'] ? 'is-rose' : 'is-mint' ?> dash-entra" style="--i:6; padding:22px">
        <div class="lms-row" style="gap:12px; flex-wrap:nowrap; margin-bottom:8px">
          <?= lms_avatar_html($foco['avatar_genero'], $foco['avatar_cor_pele'], 46, $foco['foto_url'] ?? null) ?>
          <div>
            <h3 style="font-size:16px"><?= e($foco['nome']) ?></h3>
            <p class="lms-small lms-muted"><?= e($foco['email']) ?></p>
            <p class="lms-small lms-muted" style="margin-top:2px">🧑‍🤝‍🧑 Gestor: <strong><?= $focoGestor ? e($focoGestor['nome']) : 'sem time' ?></strong></p>
          </div>
        </div>
        <?php if ($F['risco']): ?>
        <p class="lms-small" style="color:var(--lms-error); font-weight:600"><?= $F['risco'][0] ?> <?= e($F['risco'][1]) ?></p>
        <?php else: ?>
        <p class="lms-small" style="color:var(--lms-success); font-weight:600">✅ Em dia — sem sinais de risco</p>
        <?php endif; ?>
        <div class="lms-row" style="margin-top:12px; gap:8px; flex-wrap:wrap">
          <button type="button" class="lms-btn <?= $F['risco'] ? 'lms-btn-game' : 'lms-btn-primary' ?> lms-btn-sm" id="lemb-abrir" <?= $lembF ? 'disabled title="E-mail enviado nas últimas 24h"' : '' ?>><?= $emailBtnLbl ?></button>
        </div>
        <?php if ($foco['lembrete_em']): ?><p class="lms-small lms-muted" style="margin-top:6px">Último e-mail <?= e(tempo_atras($foco['lembrete_em'])) ?></p><?php endif; ?>
      </div>

      <?php if (!$lembF): [$lembAssunto, $lembCorpo] = lembrete_sugestao($foco, $u, $tipoEmail);
        $tituloPopup = ['resgate' => '✉️ E-mail de resgate', 'parabens' => '🎉 Parabéns', 'continue' => '💪 Incentivo'][$tipoEmail]; ?>
      <!-- popup do e-mail (resgate / parabéns / incentivo): revisa a sugestão antes de enviar -->
      <div id="lemb-fundo" class="lms-hide" style="position:fixed; inset:0; background:rgba(31,42,37,.45); z-index:40"></div>
      <div id="lemb-modal" class="lms-hide" style="position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
           width:min(580px, calc(100vw - 32px)); max-height:88vh; overflow:auto; z-index:41;
           background:var(--lms-bg); border:var(--lms-border-game); border-radius:var(--lms-radius-xl);
           box-shadow:var(--lms-shadow-crisp); padding:26px">
        <div class="lms-row" style="justify-content:space-between; margin-bottom:4px">
          <h3><?= $tituloPopup ?> para <?= e(explode(' ', $foco['nome'])[0]) ?></h3>
          <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="lemb-fechar">Fechar ✕</button>
        </div>
        <p class="lms-small lms-muted" style="margin-bottom:14px">
          Sugestão pronta com o progresso e o próximo passo — ajuste do seu jeito antes de enviar.
        </p>
        <form method="post" class="lms-stack-4">
          <input type="hidden" name="acao" value="lembrete">
          <input type="hidden" name="tipo" value="<?= e($tipoEmail) ?>">
          <input type="hidden" name="id" value="<?= (int) $foco['id'] ?>">
          <input type="hidden" name="volta" value="<?= (int) $foco['id'] ?>">
          <div>
            <label class="lms-label" for="lemb-assunto">Assunto</label>
            <input class="lms-input" id="lemb-assunto" name="assunto" maxlength="150" required value="<?= e($lembAssunto) ?>">
          </div>
          <div>
            <label class="lms-label" for="lemb-msg">Mensagem</label>
            <textarea class="lms-input" id="lemb-msg" name="mensagem" rows="9" maxlength="4000" required
                      style="line-height:1.55"><?= e($lembCorpo) ?></textarea>
            <p class="lms-small lms-muted" style="margin-top:6px">O botão <strong>“Voltar para a plataforma ▶”</strong> e a sua assinatura entram automaticamente no final do e-mail.</p>
          </div>
          <div class="lms-row" style="justify-content:flex-end; gap:8px">
            <button type="button" class="lms-btn lms-btn-outline" id="lemb-cancelar">Cancelar</button>
            <button class="lms-btn lms-btn-primary">Enviar ✉️</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- ── Posição no ranking ────────────────────────── -->
      <div class="lms-card-pastel is-sand dash-entra" style="--i:7; padding:22px">
        <h3 style="margin-bottom:10px">🏅 Engajamento</h3>
        <p class="lms-small"><strong><?= $F['pos'] ?>º de <?= $F['total'] ?></strong> no ranking da equipe</p>
        <p class="lms-small lms-muted" style="margin-top:4px"><?= number_format($F['xp'], 0, ',', '.') ?> XP · média da equipe: <?= number_format($F['media'], 0, ',', '.') ?> XP</p>
        <div class="lms-progress" style="height:10px; margin-top:10px"><div class="lms-progress-fill" style="width:<?= lms_pct($F['xp'], max(1, $F['media'] * 2)) ?>%"></div></div>
      </div>

      <?php else: ?>
      <!-- ── Radar de risco ────────────────────────────── -->
      <div class="lms-card-pastel is-rose dash-entra" style="--i:6; padding:22px">
        <div class="lms-row" style="justify-content:space-between; margin-bottom:10px">
          <h3>🚨 Radar de risco</h3>
          <span class="lms-pill is-error"><?= count($risco) ?></span>
        </div>
        <?php if (!$risco): ?>
        <p class="lms-small">Nenhum colaborador em risco. 👏</p>
        <?php else: ?>
        <div class="dash-risco-list" data-pager="risco" data-page-size="5">
        <?php foreach ($risco as $r): $a = $r['a']; ?>
        <div class="dash-risco">
          <?= lms_avatar_html($a['avatar_genero'], $a['avatar_cor_pele'], 34, $a['foto_url'] ?? null) ?>
          <div style="flex:1; min-width:0">
            <strong style="font-family:var(--lms-font-ui); font-size:13.5px"><?= e($a['nome']) ?></strong>
            <p class="lms-small" style="color:var(--lms-error)"><?= $riscoIcone[$r['tipo']] ?> <?= e($r['msg']) ?></p>
            <?php if ($a['lembrete_em']): ?>
            <p class="lms-small lms-muted">✉️ lembrete <?= e(tempo_atras($a['lembrete_em'])) ?></p>
            <?php endif; ?>
          </div>
          <a class="lms-btn lms-btn-outline lms-btn-sm" style="flex:none" title="Abrir o perfil — de lá você envia o e-mail de resgate"
             href="/boticario/gestor/?aluno=<?= (int) $a['id'] ?>">Ver</a>
        </div>
        <?php endforeach; ?>
        </div>
        <?php if (count($risco) > 5): ?>
        <div class="dash-pager" data-pager-nav="risco">
          <button type="button" class="dash-pager-btn" data-dir="-1" aria-label="Anteriores">‹</button>
          <span class="dash-pager-info"></span>
          <button type="button" class="dash-pager-btn" data-dir="1" aria-label="Próximos">›</button>
        </div>
        <?php endif; endif; ?>
      </div>

      <!-- ── Top engajamento ───────────────────────────── -->
      <div class="lms-card-pastel is-sand dash-entra" style="--i:7; padding:22px">
        <h3 style="margin-bottom:10px">🏅 Top engajamento</h3>
        <?php $medalha = ['🥇', '🥈', '🥉', '4º', '5º'];
        foreach ($rank as $i => $r): $a = $r['a']; ?>
        <div class="dash-rank">
          <span class="dash-rank-pos"><?= $medalha[$i] ?></span>
          <?= lms_avatar_html($a['avatar_genero'], $a['avatar_cor_pele'], 32, $a['foto_url'] ?? null) ?>
          <div style="flex:1; min-width:0">
            <strong style="font-family:var(--lms-font-ui); font-size:13.5px"><?= e($a['nome']) ?></strong>
            <p class="lms-small lms-muted"><?= $r['badges'] ? $r['badges'] . ' 🎖' : 'sem conquistas ainda' ?></p>
          </div>
          <span class="lms-xp" style="flex:none"><span class="lms-xp-coin"></span> <?= number_format($r['xp'], 0, ',', '.') ?></span>
        </div>
        <?php endforeach; if (!$rank): ?><p class="lms-small lms-muted">Ainda sem atividade pontuada.</p><?php endif; ?>
      </div>

      <!-- ── Comunidade: moderação + sem resposta ──────────── -->
      <div class="lms-card-pastel is-sky dash-entra" style="--i:8; padding:22px">
        <div class="lms-row" style="justify-content:space-between; margin-bottom:10px">
          <h3>💬 Comunidade</h3>
          <span class="lms-pill <?= $pendentes || $duvidas ? 'is-warning' : 'is-success' ?>"><?= count($pendentes) + count($duvidas) ?></span>
        </div>

        <?php if (!$pendentes && !$duvidas): ?>
        <p class="lms-small">Tudo em dia — nada para moderar nem responder. 🙌</p>
        <?php endif; ?>

        <?php if ($pendentes): ?>
        <p class="lms-small" style="font-weight:700; color:#8A6D1B">⏳ Aguardando sua aprovação</p>
        <?php foreach ($pendentes as $d): ?>
        <div class="dash-risco">
          <?= lms_avatar_html($d['avatar_genero'], $d['avatar_cor_pele'], 34, $d['foto_url'] ?? null) ?>
          <div style="flex:1; min-width:0">
            <strong style="font-family:var(--lms-font-ui); font-size:13.5px"><?= e($d['autor']) ?></strong>
            <p class="lms-small" style="overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical">“<?= e($d['texto']) ?>”</p>
            <p class="lms-small lms-muted"><?= e($d['fase_nome']) ?> · <?= tempo_atras($d['criado_em']) ?></p>
          </div>
          <a class="lms-btn lms-btn-outline lms-btn-sm" style="flex:none" title="Abrir o painel de moderação"
             href="/boticario/admin/comentarios">Moderar</a>
        </div>
        <?php endforeach; endif; ?>

        <?php if ($duvidas): ?>
        <p class="lms-small" style="font-weight:700; margin-top:<?= $pendentes ? '12px' : '0' ?>">🗨 Publicadas sem resposta</p>
        <?php foreach ($duvidas as $d): ?>
        <div class="dash-risco">
          <?= lms_avatar_html($d['avatar_genero'], $d['avatar_cor_pele'], 34, $d['foto_url'] ?? null) ?>
          <div style="flex:1; min-width:0">
            <strong style="font-family:var(--lms-font-ui); font-size:13.5px"><?= e($d['autor']) ?></strong>
            <p class="lms-small" style="overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical">“<?= e($d['texto']) ?>”</p>
            <p class="lms-small lms-muted"><?= e($d['fase_nome']) ?> · <?= tempo_atras($d['criado_em']) ?></p>
          </div>
          <a class="lms-btn lms-btn-outline lms-btn-sm" style="flex:none" title="Abrir a discussão e responder"
             href="/boticario/fase?id=<?= (int) $d['fase_id'] ?>#comunidade">Responder</a>
        </div>
        <?php endforeach; endif; ?>

        <a class="lms-small" style="display:inline-block; margin-top:12px" href="/boticario/admin/comentarios">🛡 Ver todos os comentários →</a>
      </div>
      <?php endif; ?>

      <!-- ── Atividade recente ─────────────────────────── -->
      <div class="lms-card-game dash-entra" style="--i:8">
        <h3 style="margin-bottom:10px">⚡ <?= $foco ? 'Últimas atividades' : 'Acontecendo agora' ?></h3>
        <?php foreach ($recentes as $r): $pctR = (int) $r['total'] > 0 ? round($r['acertos'] / $r['total'] * 100) : 100; ?>
        <div class="dash-feed">
          <?= lms_avatar_html($r['avatar_genero'], $r['avatar_cor_pele'], 28, $r['foto_url'] ?? null) ?>
          <div style="flex:1; min-width:0">
            <p style="font-size:13px; line-height:1.4"><strong style="font-family:var(--lms-font-ui)"><?= e($r['aluno']) ?></strong>
              concluiu <em>“<?= e($r['etapa']) ?>”</em></p>
            <p class="lms-small lms-muted"><?= e($r['fase_nome']) ?> · <?= $pctR ?>% · <?= tempo_atras($r['concluido_em']) ?></p>
          </div>
        </div>
        <?php endforeach; if (!$recentes): ?><p class="lms-small lms-muted">Nenhuma atividade registrada ainda.</p><?php endif; ?>
      </div>

    </div>

  <?php if ($detalhe): $a = $detalhe['u']; ?>
  <!-- ── detalhe por colaborador (coluna principal, linha 2) ─── -->
  <div class="lms-card-game dash-after" id="detalhe">
    <div class="lms-row" style="justify-content:space-between; margin-bottom:14px">
      <div class="lms-row" style="gap:14px">
        <?= lms_avatar_html($a['avatar_genero'], $a['avatar_cor_pele'], 54, $a['foto_url'] ?? null) ?>
        <div>
          <h3><?= e($a['nome']) ?></h3>
          <p class="lms-small lms-muted"><?= e($a['email']) ?>
             · último acesso: <?= $a['ultimo_login'] ? e(date('d/m/Y H:i', strtotime($a['ultimo_login']))) : 'nunca' ?></p>
        </div>
      </div>
    </div>
    <div class="lms-table-wrap">
      <table class="lms-table">
        <thead><tr><th>Fase</th><th>Etapas</th><th>Acertos</th><th>Tempo</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($detalhe['fases'] as $t):
            $pctT = (int) $t['questoes'] > 0 ? round($t['acertos'] / $t['questoes'] * 100) . '%' : '—'; ?>
          <tr>
            <td><strong>F<?= (int) $t['ordem'] ?></strong> · <?= e($t['nome']) ?></td>
            <td><?= (int) $t['feitas'] ?>/<?= (int) $t['total'] ?></td>
            <td><?= $pctT ?></td>
            <td><?= $t['tempo'] > 0 ? floor($t['tempo'] / 60) . 'm' . str_pad($t['tempo'] % 60, 2, '0', STR_PAD_LEFT) : '—' ?></td>
            <td><?= (int) $t['feitas'] >= (int) $t['total'] ? $pills['concluido'] : ((int) $t['feitas'] > 0 ? $pills['andamento'] : $pills['nao_iniciado']) ?></td>
          </tr>
          <?php endforeach; if (!$detalhe['fases']): ?>
          <tr><td colspan="5" class="lms-muted lms-small"><?= $a['treinamento_id'] ? 'O treinamento deste colaborador ainda não tem fases.' : 'Sem treinamento ativo — atribua um em Usuários.' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$foco): ?>
  <!-- ── lista completa (só na visão da equipe · coluna principal, linha 2) ── -->
  <div class="lms-card-game dash-entra dash-after" style="--i:9" id="roster">
    <h3 style="margin-bottom:12px">👥 Todos os colaboradores</h3>
    <div class="lms-table-wrap">
      <table class="lms-table roster-table">
        <thead><tr><th>Colaborador</th><th>Engajam.</th><th>Progresso &amp; atividade</th><th></th></tr></thead>
        <tbody>
          <?php
          $statusTxt = ['concluido' => ['Concluído', 'var(--lms-success)'], 'andamento' => ['Em andamento', '#9A6A0D'],
                        'nao_iniciado' => ['Não iniciado', 'var(--lms-muted)'], 'nunca' => ['Nunca acessou', 'var(--lms-error)']];
          foreach ($linhas as $l): $a = $l['u']; [$stLbl, $stCor] = $statusTxt[$l['status']];
            $eg = $l['engaj']; [$egLbl, $egIco, $egCls] = $engajInfo[$eg['nivel']];
            $meta = [];
            if ($l['acerto'] !== null) $meta[] = $l['acerto'] . '% acerto';
            if ($l['tempo'] > 0) $meta[] = floor($l['tempo'] / 60) . 'min';
            if ($a['ultimo_login']) $meta[] = tempo_atras($a['ultimo_login']); ?>
          <tr>
            <td>
              <div class="lms-row" style="gap:10px; flex-wrap:nowrap">
                <?= lms_avatar_html($a['avatar_genero'], $a['avatar_cor_pele'], 34, $a['foto_url'] ?? null) ?>
                <div style="min-width:0"><strong style="font-family:var(--lms-font-ui); font-weight:600"><?= e($a['nome']) ?></strong><br>
                  <span class="lms-muted" style="font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:block"><?= e($a['email']) ?></span></div>
              </div>
            </td>
            <td><span class="lms-pill <?= $egCls ?>" title="<?= e($engajTip($eg)) ?>"><?= $egIco ?> <?= $egLbl ?></span></td>
            <td style="min-width:150px">
              <div class="lms-row" style="gap:8px; flex-wrap:nowrap">
                <div class="lms-progress <?= $l['status'] === 'concluido' ? 'is-success' : '' ?>" style="height:10px; flex:1">
                  <div class="lms-progress-fill" style="width:<?= $l['pct'] ?>%; <?= $l['feitas'] === 0 ? 'min-width:0' : '' ?>"></div>
                </div>
                <span class="lms-small lms-muted" style="flex:none"><?= $l['pct'] ?>%</span>
              </div>
              <div class="lms-muted" style="margin-top:4px; font-size:11px">
                <span style="color:<?= $stCor ?>; font-weight:600"><?= $stLbl ?></span><?= $meta ? ' · ' . implode(' · ', $meta) : '' ?>
              </div>
            </td>
            <td><a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/gestor/?aluno=<?= (int) $a['id'] ?>" title="Ver report do aluno">👁</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
  </div><!-- /.dash-grid -->

  <?php if (!$foco && $nuncaLogaram > 0): $nuncaLista = array_values(array_filter($linhas, fn($l) => $l['status'] === 'nunca')); ?>
  <!-- popup: quem nunca acessou (tomada de ação) — vale para todos os perfis -->
  <div id="nunca-fundo" class="lms-hide" style="position:fixed; inset:0; background:rgba(31,42,37,.45); z-index:40"></div>
  <div id="nunca-modal" class="lms-hide" style="position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
       width:min(560px, calc(100vw - 32px)); max-height:82vh; overflow:auto; z-index:41;
       background:var(--lms-bg); border:var(--lms-border-game); border-radius:var(--lms-radius-xl);
       box-shadow:var(--lms-shadow-crisp); padding:26px">
    <div class="lms-row" style="justify-content:space-between; margin-bottom:4px">
      <h3>🚫 Nunca acessaram (<?= count($nuncaLista) ?>)</h3>
      <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="nunca-fechar">Fechar ✕</button>
    </div>
    <p class="lms-small lms-muted" style="margin-bottom:14px">Abra o report de cada um e envie o e-mail de resgate com o passo a passo de acesso.</p>
    <div class="lms-stack-3">
      <?php foreach ($nuncaLista as $l): $a = $l['u']; ?>
      <div class="lms-row" style="gap:10px; align-items:center; justify-content:space-between; border-bottom:1px dashed rgba(31,42,37,.12); padding-bottom:8px">
        <div class="lms-row" style="gap:10px; min-width:0">
          <?= lms_avatar_html($a['avatar_genero'], $a['avatar_cor_pele'], 34, $a['foto_url'] ?? null) ?>
          <div style="min-width:0">
            <strong style="font-family:var(--lms-font-ui); font-weight:600"><?= e($a['nome']) ?></strong><br>
            <span class="lms-muted" style="font-size:12px"><?= e($a['email']) ?></span>
          </div>
        </div>
        <a class="lms-btn lms-btn-outline lms-btn-sm" style="flex:none" href="/boticario/gestor/?aluno=<?= (int) $a['id'] ?>">Ver ▶</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <p class="lms-small lms-muted" style="margin-top:14px">💡 Próximo passo do produto: report diário automático por e-mail para o gestor e PDF de resultado por aluno (RF-10).</p>
</main>

<style>
  .dash-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:14px; margin-top:22px }

  /* entrada em cascata dos blocos */
  .dash-entra { animation:dash-sobe .5s var(--lms-ease, ease) both; animation-delay:calc(var(--i, 0) * 70ms) }
  @keyframes dash-sobe { from { opacity:0; transform:translateY(14px) } to { opacity:1; transform:none } }

  /* linha 1 = altura da coluna principal; a lateral (que atravessa as 2 linhas) só
     estica a linha 2 — sem isso o grid distribuía a altura da lateral entre as
     linhas e abria um vão antes de "Todos os colaboradores" */
  .dash-grid { display:grid; grid-template-columns:minmax(0, 1.7fr) minmax(0, 1fr); grid-template-rows:auto 1fr; gap:18px; align-items:start }
  /* coluna direita = trilho que ocupa a altura das duas linhas da esquerda;
     detalhe/tabela (.dash-after) sobem para a linha 2 da esquerda e preenchem
     o vão que sobrava sob os gráficos. */
  .dash-grid > .dash-side  { grid-column:2; grid-row:1 / span 2 }
  .dash-grid > .dash-main,
  .dash-grid > .dash-after { grid-column:1 }
  @media (max-width: 900px) {
    .dash-grid { grid-template-columns:1fr }
    .dash-grid > .dash-side { grid-column:1; grid-row:auto }
    .dash-grid { grid-template-rows:none }
  }

  /* tooltips ricos no hover (funil + gráfico) */
  [data-tip] { position:relative; cursor:default }
  [data-tip]:hover::after {
    content:attr(data-tip);
    position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
    background:var(--lms-heading, #1F2A25); color:#fff;
    font-family:var(--lms-font-ui); font-size:12px; font-weight:600;
    white-space:nowrap; padding:6px 12px; border-radius:8px; z-index:30;
    box-shadow:0 4px 12px rgba(16,24,40,.35); pointer-events:none;
  }
  [data-tip]:hover::before {
    content:''; position:absolute; bottom:calc(100% + 2px); left:50%; transform:translateX(-50%);
    border:6px solid transparent; border-top-color:var(--lms-heading, #1F2A25); z-index:30;
  }
  /* nas bordas, o balão encosta no lado em vez de centralizar (não corta) */
  .dash-funil-col:first-child:hover::after, .dash-chart-col:first-child:hover::after,
  .dash-chart-col:nth-child(-n+4):hover::after { left:0; transform:none }
  .dash-funil-col:last-child:hover::after, .dash-chart-col:last-child:hover::after,
  .dash-chart-col:nth-last-child(-n+3):hover::after { left:auto; right:0; transform:none }

  /* tooltip explicativo dos KPIs: texto longo quebra em várias linhas */
  .lms-stat[data-tip] { cursor:help }
  .lms-stat[data-tip]:hover::after { white-space:normal; width:max-content; max-width:230px;
    text-align:center; line-height:1.45; font-weight:500 }
  .dash-kpis .lms-stat:first-child:hover::after { left:0; transform:none }
  .dash-kpis .lms-stat:last-child:hover::after { left:auto; right:0; transform:none }

  /* linha de referência (média) sobre os gráficos */
  .dash-media {
    position:absolute; left:8px; right:8px; bottom:var(--alt); z-index:2;
    border-top:2px dashed rgba(31,42,37,.45); pointer-events:none;
  }
  .dash-media.is-funil { bottom:auto; top:calc(110px - var(--alt)) }
  /* duas linhas no funil do aluno: a dele (azul, sólida) vs a geral (cinza, tracejada) */
  .dash-media.is-aluno { border-top:2px solid var(--lms-primary, #007A54) }
  .dash-media.is-geral { border-top:2px dashed rgba(31,42,37,.4) }
  .dash-media-tag {
    position:absolute; right:0; bottom:3px;
    font-family:var(--lms-font-ui); font-size:10.5px; font-weight:700;
    color:var(--lms-ink-soft, #69727D); background:rgba(255,255,253,.9);
    padding:1px 7px; border-radius:999px; border:1px solid rgba(31,42,37,.2);
  }
  /* tags em lados opostos pra nunca se sobreporem quando as médias ficam perto */
  .dash-media-tag.is-aluno { right:0; left:auto; color:var(--lms-primary, #1E7BA6); border-color:rgba(0,122,84,.55) }
  .dash-media-tag.is-geral { left:0; right:auto }

  /* funil da jornada — tudo numa linha só (fluxo em coluna, sem quebra).
     align-items:start alinha as barras pelo TOPO (sem "montanha russa"). */
  .dash-funil { display:grid; grid-auto-flow:column; grid-auto-columns:minmax(0, 1fr); gap:10px; align-items:start; position:relative }
  .dash-funil-col { text-align:center; border-radius:var(--lms-radius-md); transition:background .15s ease; padding:4px 2px }
  .dash-funil-col:hover { background:rgba(0,122,84,.08) }
  .dash-funil-col:hover .dash-funil-fill { filter:brightness(1.15) }
  .dash-funil-barra { height:110px; display:flex; align-items:flex-end; justify-content:center; position:relative }
  /* trilho vazio (a "meta" da fase): mostra o quanto ainda falta preencher */
  .dash-funil-barra::before {
    content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%);
    width:min(46px, 72%); height:100%; border-radius:6px; box-sizing:border-box;
    background:rgba(31,42,37,.045); border:2px dashed rgba(31,42,37,.13);
  }
  .dash-funil-fill {
    position:relative; z-index:1;
    width:min(46px, 72%); border-radius:6px; min-height:8px; box-sizing:border-box;
    background:var(--lms-primary, #007A54);
    border:2px solid var(--lms-heading, #1F2A25);
    transition:filter .15s ease;
  }
  .dash-funil-fill.is-ok { background:var(--lms-success, #27A567); }
  /* fase não iniciada: base neutra dentro do trilho (não azul, evita confusão) */
  .dash-funil-fill.is-zero { background:rgba(31,42,37,.14); border-color:rgba(31,42,37,.22); }
  .dash-funil-n { font-family:var(--lms-font-ui); font-weight:800; font-size:22px; margin-top:6px }
  /* rótulo com altura fixa (2 linhas) para o "Fase X · Y%" ficar alinhado
     entre todas as colunas — nome completo continua no tooltip da coluna. */
  .dash-funil-papel { font-size:12px; font-weight:600; font-family:var(--lms-font-ui); line-height:1.2; margin-top:2px;
    min-height:2.4em; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden }
  .dash-funil-fase { font-size:11px; color:var(--lms-ink-soft); margin-top:2px }
  @media (max-width: 700px) { .dash-funil { grid-auto-columns:minmax(66px, 1fr); overflow-x:auto; padding-bottom:6px } }

  /* barras que crescem */
  .cresce { animation:cresce-h .9s var(--lms-ease, ease) .15s both }
  @keyframes cresce-h { from { height:0 } to { height:var(--w) } }
  .dash-funil-fill.cresce { height:var(--w) }

  /* jornada aberta por fase (equipe): barra empilhada + painel por etapa */
  .dash-jor + .dash-jor { margin-top:22px; padding-top:18px; border-top:1px solid var(--lms-border) }
  .dash-jor-topo { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px }
  .dash-jor-geral { display:flex; align-items:center; gap:10px }
  .dash-jor-col { background:none; border:0; font:inherit; color:inherit; cursor:pointer; width:100% }
  .dash-jor-col.is-ativo { background:rgba(0,122,84,.12); box-shadow:inset 0 0 0 2px var(--lms-primary) }
  .dash-jor-bar { width:36px; height:100%; border-radius:9px; overflow:hidden; display:flex; flex-direction:column; justify-content:flex-end;
    background:rgba(31,42,37,.07); border:var(--lms-border-game) }
  .dash-jor-bar span { display:block; width:100%; transition:height .5s var(--lms-ease, ease) }
  .dash-jor-bar .is-ok { background:var(--lms-success, #27A567) }
  .dash-jor-bar .is-aqui { background:var(--lms-warning, #E8A13C) }
  .dash-jor-bar .is-nao { background:transparent }
  .dash-jor-bar .is-grey { background:rgba(31,42,37,.28) }
  .dash-jor-legenda { display:flex; gap:14px; flex-wrap:wrap; font-size:11.5px; color:var(--lms-ink-soft); margin-top:10px }
  .dash-jor-legenda i { display:inline-block; width:10px; height:10px; border-radius:3px; margin-right:5px; vertical-align:-1px; border:1px solid rgba(31,42,37,.25) }
  .dash-jor-legenda i.is-ok { background:var(--lms-success, #27A567) } .dash-jor-legenda i.is-aqui { background:var(--lms-warning, #E8A13C) } .dash-jor-legenda i.is-nao { background:rgba(31,42,37,.07) }
  .dash-jor-painel { margin-top:12px; padding:14px 16px; border-radius:var(--lms-radius-md); background:var(--lms-surface); border:var(--lms-border-game) }
  .dash-jor-painel-topo { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:baseline; margin-bottom:10px }
  .dash-jor-etapas { display:flex; flex-direction:column; gap:7px }
  .dash-jor-etapa { display:grid; grid-template-columns:minmax(150px, 1.3fr) 3fr auto auto; gap:10px; align-items:center; font-size:12.5px }
  .dash-jor-etapa-nome { font-family:var(--lms-font-ui); font-weight:600; color:var(--lms-heading); overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
  .dash-jor-etapa-bar { height:10px; border-radius:999px; background:rgba(31,42,37,.10); overflow:hidden }
  .dash-jor-etapa-bar span { display:block; height:100%; background:var(--lms-primary); border-radius:999px }
  .dash-jor-etapa-n { font-family:var(--lms-font-ui); font-weight:700; min-width:44px; text-align:right }
  .dash-jor-etapa-ac { color:var(--lms-ink-soft); min-width:84px; text-align:right }
  .dash-jor-quem { margin-top:12px; display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:12.5px }
  .dash-jor-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:var(--lms-white);
    border:1.5px solid var(--lms-border); text-decoration:none; color:var(--lms-heading); font-family:var(--lms-font-ui); font-weight:600; font-size:12px }
  .dash-jor-chip:hover { border-color:var(--lms-primary) }
  .dash-jor-chip small { color:var(--lms-ink-soft); font-weight:500 }
  @media (max-width: 700px) { .dash-jor-etapa { grid-template-columns:1fr; gap:4px } .dash-jor-etapa-n, .dash-jor-etapa-ac { text-align:left } }

  /* gráfico de atividade */
  .dash-chart { display:flex; gap:6px; align-items:flex-end; padding-top:18px; position:relative }
  .dash-chart-col { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap:4px;
    border-radius:var(--lms-radius-sm); transition:background .15s ease }
  .dash-chart-col:hover { background:rgba(0,122,84,.08) }
  .dash-chart-col:hover .dash-chart-bar { filter:brightness(1.18) }
  .dash-chart-col:hover .dash-chart-n { transform:scale(1.25) }
  .dash-chart-track { height:92px; width:100%; display:flex; align-items:flex-end; justify-content:center }
  .dash-chart-bar { width:100%; max-width:30px; height:var(--h); min-height:6px; border-radius:5px;
    box-sizing:border-box; background:var(--lms-primary, #007A54);
    border:1.5px solid var(--lms-heading, #1F2A25);
    transform-origin:bottom; animation:chart-sobe .7s var(--lms-ease, ease) calc(var(--i) * 45ms) both;
    transition:filter .15s ease }
  @keyframes chart-sobe { from { transform:scaleY(0) } to { transform:scaleY(1) } }
  .dash-chart-n { font-size:11px; font-weight:700; font-family:var(--lms-font-ui); color:var(--lms-primary); min-height:14px;
    transition:transform .15s ease }
  .dash-chart-dia { font-size:10.5px; color:var(--lms-ink-soft) }

  /* heatmap de conformidade */
  .dash-heat-item { display:flex; justify-content:space-between; align-items:center; gap:12px;
    border-radius:8px; padding:9px 14px; margin-bottom:6px; font-size:13.5px }
  .dash-heat-nome { font-family:var(--lms-font-ui); font-weight:600; min-width:0;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
  .dash-heat-pct { font-family:var(--lms-font-ui); font-weight:800; flex:none }

  /* radar de risco · ranking · feed */
  .dash-risco, .dash-rank, .dash-feed { display:flex; align-items:center; gap:10px; padding:8px 0;
    border-bottom:1px dashed rgba(31,42,37,.12) }
  .dash-risco:last-child, .dash-rank:last-child, .dash-feed:last-child { border-bottom:none; padding-bottom:0 }
  .dash-rank-pos { font-size:17px; width:28px; text-align:center; flex:none; font-family:var(--lms-font-ui); font-weight:700 }

  /* paginação (radar de risco) */
  .dash-risco-list .dash-risco.is-hidden { display:none }
  .dash-pager { display:flex; align-items:center; justify-content:center; gap:14px; margin-top:12px;
    padding-top:10px; border-top:1px dashed rgba(31,42,37,.12) }
  .dash-pager-info { font-family:var(--lms-font-ui); font-size:12.5px; font-weight:700; color:var(--lms-muted);
    min-width:74px; text-align:center }
  .dash-pager-btn { width:30px; height:30px; border-radius:8px; border:1px solid rgba(31,42,37,.16);
    background:#fff; color:var(--lms-ink, #1F2A25); font-size:16px; line-height:1; cursor:pointer;
    display:flex; align-items:center; justify-content:center; transition:filter .15s, opacity .15s }
  .dash-pager-btn:hover:not(:disabled) { filter:brightness(.96) }
  .dash-pager-btn:disabled { opacity:.4; cursor:default }

  /* tabela do roster compacta — reduz a necessidade de scroll horizontal */
  .dash-after .lms-table th, .dash-after .lms-table td { padding:9px 11px; font-size:12.5px }
  .dash-after .lms-table .lms-btn-sm { padding:5px 9px }
</style>

<script>
/* popup do e-mail de resgate (perfil observado) */
const lembAbrir = document.getElementById('lemb-abrir');
if (lembAbrir && document.getElementById('lemb-modal')) {
  const alterna = mostra => ['lemb-fundo', 'lemb-modal'].forEach(id2 =>
    document.getElementById(id2).classList.toggle('lms-hide', !mostra));
  lembAbrir.addEventListener('click', () => { alterna(true); document.getElementById('lemb-msg').focus(); });
  ['lemb-fundo', 'lemb-fechar', 'lemb-cancelar'].forEach(id2 =>
    document.getElementById(id2).addEventListener('click', () => alterna(false)));
}

/* popup: quem nunca acessou */
const nuncaAbrir = document.getElementById('nunca-abrir');
if (nuncaAbrir && document.getElementById('nunca-modal')) {
  const alterna = mostra => ['nunca-fundo', 'nunca-modal'].forEach(id2 =>
    document.getElementById(id2).classList.toggle('lms-hide', !mostra));
  nuncaAbrir.addEventListener('click', () => alterna(true));
  nuncaAbrir.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); alterna(true); } });
  ['nunca-fundo', 'nunca-fechar'].forEach(id2 =>
    document.getElementById(id2).addEventListener('click', () => alterna(false)));
}

/* busca no topo: escolher um colaborador abre o dashboard individual */
const buscaTopo = document.getElementById('busca-topo');
if (buscaTopo) {
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
  const opcoes = [...document.querySelectorAll('#lista-colabs option')]
    .map(o => ({ id: o.dataset.id, nome: norm(o.value), email: norm(o.textContent) }));
  const vai = () => {
    const q = norm(buscaTopo.value);
    if (q.length < 2) return;
    const hit = opcoes.find(o => o.nome === q)
      || opcoes.find(o => o.nome.startsWith(q))
      || opcoes.find(o => o.nome.split(' ').some(p => p.startsWith(q)))
      || opcoes.find(o => o.nome.includes(q) || o.email.includes(q));
    if (hit) location.href = '/boticario/gestor/?aluno=' + hit.id;
  };
  buscaTopo.addEventListener('change', vai);
  buscaTopo.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') vai(); });
}

// jornada por fase: clique numa coluna abre o painel por etapa (um por vez)
document.querySelectorAll('.dash-jor').forEach(bloco => {
  bloco.querySelectorAll('.dash-jor-col').forEach(col => col.addEventListener('click', () => {
    const alvo = col.dataset.painel;
    const jaAberto = col.classList.contains('is-ativo');
    bloco.querySelectorAll('.dash-jor-col').forEach(c => c.classList.remove('is-ativo'));
    bloco.querySelectorAll('.dash-jor-painel').forEach(p => p.classList.add('lms-hide'));
    if (jaAberto) return;
    col.classList.add('is-ativo');
    bloco.querySelector(`.dash-jor-painel[data-painel-id="${alvo}"]`)?.classList.remove('lms-hide');
  }));
});

/* paginação client-side (radar de risco): 5 por página */
document.querySelectorAll('[data-pager]').forEach(lista => {
  const nome = lista.dataset.pager;
  const nav = document.querySelector('[data-pager-nav="' + nome + '"]');
  const itens = [...lista.children];
  const tam = +(lista.dataset.pageSize || 5);
  const paginas = Math.ceil(itens.length / tam);
  if (!nav || paginas <= 1) return;
  const info = nav.querySelector('.dash-pager-info');
  const btns = nav.querySelectorAll('.dash-pager-btn');
  let pag = 0;
  const render = () => {
    itens.forEach((el, i) => el.classList.toggle('is-hidden', i < pag * tam || i >= (pag + 1) * tam));
    info.textContent = (pag + 1) + ' / ' + paginas;
    btns[0].disabled = pag === 0;
    btns[1].disabled = pag === paginas - 1;
  };
  btns.forEach(b => b.addEventListener('click', () => {
    pag = Math.min(paginas - 1, Math.max(0, pag + (+b.dataset.dir)));
    render();
  }));
  render();
});

/* KPIs com contagem animada */
document.querySelectorAll('.kpi-num').forEach(el => {
  const alvo = parseFloat(el.dataset.val), suf = el.dataset.suf || '', dec = +(el.dataset.dec || 0);
  const t0 = performance.now(), dur = 900;
  (function tick(t) {
    const p = Math.min(1, (t - t0) / dur), ease = 1 - Math.pow(1 - p, 3);
    el.textContent = (alvo * ease).toFixed(dec).replace('.', ',') + suf;
    if (p < 1) requestAnimationFrame(tick);
  })(t0);
});
</script>
</body></html>
