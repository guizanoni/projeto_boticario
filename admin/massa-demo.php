<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · MASSA DE DEMONSTRAÇÃO (só admin).
// Cria alunos fictícios com progresso espalhado pelos últimos 14 dias,
// dois gestores com times, comentários (com um pendente de moderação) e
// conquistas — pra dashboard, times e relatório terem movimento na demo.
// Também garante as contas de teste Sara, Gui e Erica ZERADAS (começam
// a jornada do zero) e o acesso de RH (papel admin).
//
// Idempotente: apaga a massa anterior (e-mails @demo.boticario.com.br)
// antes de recriar; números fixos (mt_srand) — rodar de novo dá o mesmo.
//   GET ?go=DEMO2026               → mostra o plano, não grava nada
//   GET ?go=DEMO2026&confirmar=1   → executa
//   GET ?go=DEMO2026&acao=limpar   → só remove a massa (Sara/Gui/Erica/RH ficam)
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';
$u = lms_gate(['admin']);
if (($_GET['go'] ?? '') !== 'DEMO2026') { http_response_code(404); exit; }

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font:14px/1.6 ui-monospace,monospace;max-width:820px;margin:40px auto;color:#1F2A25">';
$db  = lms_db();
$cli = (int) $u['cliente_id'];
$DOM = '@demo.boticario.com.br';
$out = function (string $s) { echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . "\n"; };

// ── contexto: treinamento inaugural, fases e etapas (totais por tipo) ──
$trein = db_one('SELECT t.id FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id WHERE tr.cliente_id = ? ORDER BY tr.ordem, t.ordem, t.id LIMIT 1', [$cli]);
if (!$trein) { $out('Sem treinamento — nada a fazer.'); exit; }
$tid = (int) $trein['id'];
$fases = db_all('SELECT * FROM fase WHERE treinamento_id = ? ORDER BY ordem', [$tid]);
$etapas = [];  // sequência global: [id, fase_ordem, total, tipo]
foreach ($fases as $f) {
    foreach (db_all('SELECT * FROM etapa WHERE fase_id = ? ORDER BY ordem', [$f['id']]) as $e) {
        $c = json_decode($e['config'], true) ?: [];
        $total = match ($e['minigame_tipo']) {
            'leitura'      => max(1, count($c['pontos_confirmacao'] ?? [])),
            'historia'     => max(1, count($c['cenas'] ?? [])),
            'quiz'         => max(1, count($c['perguntas'] ?? [])),
            'sequencia'    => max(1, count($c['passos'] ?? [])),
            'investigacao' => max(1, count($c['cartas'] ?? [])),
            default        => 1,
        };
        $tempo = match ($e['minigame_tipo']) { 'video' => 250, 'investigacao' => [150, 300], 'sequencia' => [90, 240], 'quiz' => [60, 150], 'historia' => [90, 180], default => [60, 120] };
        $etapas[] = ['id' => (int) $e['id'], 'fase' => (int) $f['ordem'], 'fase_id' => (int) $f['id'], 'total' => $total, 'tipo' => $e['minigame_tipo'], 'tempo' => $tempo, 'nota' => (int) $f['nota_minima']];
    }
}
$nEtapas = count($etapas);
$badges = [];
foreach (db_all('SELECT id, nome FROM badge WHERE cliente_id = ?', [$cli]) as $b) $badges[$b['nome']] = (int) $b['id'];

// ── personas ──
// [nome, gênero, pele, perfil, time]  perfil: completo | meio | fraco | parado | nunca | hoje
$personas = [
    ['Ana Beatriz Souza', 'f', '2', 'completo', 1], ['Camila Rocha',    'f', '3', 'completo', 2], ['Juliana Freitas',  'f', '1', 'completo', 1],
    ['Larissa Mendes',    'f', '4', 'meio',     2], ['Patrícia Alves',  'f', '2', 'meio',     1], ['Thaís Oliveira',   'f', '5', 'meio',     2], ['Diego Martins', 'm', '3', 'meio', 1],
    ['Renata Castro',     'f', '3', 'fraco',    2], ['Lucas Pereira',   'm', '2', 'fraco',    1],
    ['Bianca Moreira',    'f', '1', 'parado',   2], ['Letícia Ramos',   'f', '4', 'parado',   1],
    ['Mariana Costa',     'f', '2', 'nunca',    2], ['Gabriela Nunes',  'f', '3', 'nunca',    1],
    ['Vanessa Teixeira',  'f', '5', 'hoje',     2],
];
$gestores = [1 => ['Marina Castro', 'f', '2', 'gestor1'], 2 => ['Rafael Nunes', 'm', '4', 'gestor2']];
$reais = [['Sara', 'sara@boticario.com.br', 1], ['Gui', 'gui@boticario.com.br', 2], ['Erica', 'erica@boticario.com.br', 1]];
$semAcento = fn(string $s) => strtolower(strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','â'=>'a','ê'=>'e','ã'=>'a','ç'=>'c']));
$emailDe = function (string $nome) use ($DOM, $semAcento) { $p = explode(' ', $nome); return $semAcento($p[0]) . '.' . $semAcento($p[count($p) - 1]) . $DOM; };

$acao = $_GET['acao'] ?? '';
$confirmar = !empty($_GET['confirmar']);

// ── limpeza da massa anterior ──
function massa_limpar(PDO $db, string $dom, callable $out): void {
    $ids = array_column($db->query("SELECT id FROM usuario WHERE email LIKE '%{$dom}'")->fetchAll(), 'id');
    if (!$ids) { $out('Massa anterior: nenhuma.'); return; }
    $in = implode(',', array_map('intval', $ids));
    $db->exec("DELETE FROM comentario WHERE usuario_id IN ({$in}) OR parent_id IN (SELECT id FROM comentario WHERE usuario_id IN ({$in}))");
    $db->exec("DELETE FROM conquista_usuario WHERE usuario_id IN ({$in})");
    $db->exec("DELETE FROM progresso_usuario WHERE usuario_id IN ({$in})");
    $db->exec("UPDATE usuario SET gestor_id = NULL WHERE gestor_id IN ({$in})");
    $db->exec("DELETE FROM usuario WHERE id IN ({$in})");
    $out('Massa anterior removida: ' . count($ids) . ' usuários.');
}

$out("Treinamento #{$tid} · {$nEtapas} etapas em " . count($fases) . ' fases.');
if ($acao === 'limpar') { massa_limpar($db, $DOM, $out); $out('Pronto.'); echo '</pre>'; exit; }
if (!$confirmar) {
    $cont = array_count_values(array_column($personas, 3));
    $out('PLANO: ' . count($personas) . ' alunos fictícios (' . implode(', ', array_map(fn($k, $v) => "{$v} {$k}", array_keys($cont), $cont)) . '), 2 gestores com times, comentários e conquistas.');
    $out('Sara, Gui e Erica: criadas (se faltarem) e ZERADAS. RH: rh@boticario.com.br (admin).');
    $out('Senha de todos: 1234. Para executar: &confirmar=1');
    echo '</pre>'; exit;
}

mt_srand(2026);
$rnd = fn(int $a, int $b) => mt_rand($a, $b);
$h = password_hash('1234', PASSWORD_DEFAULT);
$agora = time();
$dias = fn(float $d, ?int $hora = null) => date('Y-m-d H:i:s', $agora - (int) ($d * 86400) + (($hora ?? mt_rand(8, 20)) - 12) * 3600 + mt_rand(0, 3599));

$db->beginTransaction();
try {
    massa_limpar($db, $DOM, $out);

    $ins = $db->prepare('INSERT INTO usuario (cliente_id, nome, email, senha_hash, papel, avatar_genero, avatar_cor_pele, treinamento_id, ultimo_login, acessos, gestor_id, aceite_em, trilhas_vistas, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

    // gestores
    $gid = [];
    foreach ($gestores as $t => [$nome, $g, $p, $slug]) {
        $ins->execute([$cli, $nome, $slug . $DOM, $h, 'gestor', $g, $p, $tid, $dias(1), 12, null, $dias(20), '[1]', $dias(21)]);
        $gid[$t] = (int) $db->lastInsertId();
    }
    $out('Gestores: ' . implode(' · ', array_map(fn($x) => $x[0], $gestores)));

    // RH (admin) — conta real, fora da massa
    if (!db_one('SELECT 1 AS x FROM usuario WHERE email = ?', ['rh@boticario.com.br'])) {
        $ins->execute([$cli, 'RH O Boticário', 'rh@boticario.com.br', $h, 'admin', 'f', '2', $tid, null, 0, null, null, null, $dias(21)]);
        $out('RH criado: rh@boticario.com.br (admin).');
    }

    // Sara, Gui, Erica — zeradas
    foreach ($reais as [$nome, $email, $time]) {
        $ja = db_one('SELECT id FROM usuario WHERE email = ?', [$email]);
        if ($ja) {
            $id = (int) $ja['id'];
            $db->exec("DELETE FROM comentario WHERE usuario_id = {$id}");
            $db->exec("DELETE FROM conquista_usuario WHERE usuario_id = {$id}");
            $db->exec("DELETE FROM progresso_usuario WHERE usuario_id = {$id}");
            $db->prepare('UPDATE usuario SET papel = ?, avatar_genero = NULL, avatar_cor_pele = NULL, foto_url = NULL, avatar_v2 = NULL, treinamento_id = ?, treinamento_id_2 = NULL, ultimo_login = NULL, acessos = 0, gestor_id = ?, aceite_em = NULL, trilhas_vistas = NULL, lembrete_em = NULL WHERE id = ?')
               ->execute(['aluno', $tid, $gid[$time], $id]);
        } else {
            $ins->execute([$cli, $nome, $email, $h, 'aluno', null, null, $tid, null, 0, $gid[$time], null, null, date('Y-m-d H:i:s')]);
        }
    }
    $out('Sara, Gui e Erica: zeradas (aceite → avatar → apresentação → mapa).');

    // alunos fictícios
    $insP = $db->prepare('INSERT INTO progresso_usuario (usuario_id, etapa_id, status, acertos, total, tempo_seg, concluido_em) VALUES (?,?,\'concluido\',?,?,?,?)');
    $insB = $db->prepare('INSERT OR IGNORE INTO conquista_usuario (usuario_id, badge_id, ganho_em) VALUES (?,?,?)');
    $insC = $db->prepare('INSERT INTO comentario (cliente_id, fase_id, usuario_id, parent_id, texto, oficial, status, criado_em) VALUES (?,?,?,?,?,?,?,?)');
    $porPerfil = ['completo' => 0, 'meio' => 0, 'fraco' => 0, 'parado' => 0, 'nunca' => 0, 'hoje' => 0];
    $ids = [];
    $nPorFase = array_count_values(array_column($etapas, 'fase'));
    foreach ($personas as [$nome, $g, $p, $perfil, $time]) {
        // quantas etapas, janela de dias (início → fim), força, acessos, último login
        [$n, $d0, $d1, $forca, $acessos, $login] = match ($perfil) {
            'completo' => [$nEtapas, $rnd(11, 13), $rnd(2, 4), [86, 100], $rnd(9, 14), $rnd(1, 3)],
            'meio'     => [$rnd(5, 8), $rnd(7, 10), $rnd(0, 2), [66, 92], $rnd(4, 7), $rnd(0, 2)],
            'fraco'    => [$rnd(6, 7), $rnd(6, 9), $rnd(1, 2), [38, 62], $rnd(3, 5), $rnd(1, 2)],  // chega nas ordenações: é lá que a nota cai
            'parado'   => [$rnd(2, 3), $rnd(9, 11), $rnd(6, 8), [60, 85], 2, $rnd(6, 9)],
            'nunca'    => [0, 0, 0, [0, 0], 0, null],
            'hoje'     => [$rnd(1, 2), 0, 0, [70, 95], 1, 0],
        };
        $nunca = $perfil === 'nunca';
        $ins->execute([$cli, $nome, $emailDe($nome), $h, 'aluno', $nunca ? null : $g, $nunca ? null : $p, $tid,
                       $login === null ? null : $dias($login), $acessos, $gid[$time], $nunca ? null : $dias($d0 + 1), $nunca ? null : '[1]', $dias($d0 + 2)]);
        $uid = (int) $db->lastInsertId();
        $ids[$nome] = $uid;
        $porPerfil[$perfil]++;

        // progresso: etapas em sequência, datas do dia d0 até d1
        $porFase = [];
        for ($k = 0; $k < $n; $k++) {
            $e = $etapas[$k];
            $dia = $n > 1 ? $d0 - ($d0 - $d1) * $k / ($n - 1) : $d0;
            $total = $e['total'];
            $pct = $rnd($forca[0], $forca[1]) / 100;
            $acertos = in_array($e['tipo'], ['leitura', 'video', 'historia'], true) ? $total : max(0, min($total, (int) round($total * $pct)));
            if ($e['nota'] >= 80 && $total >= 5) $acertos = max($acertos, (int) ceil($total * 0.8)); // quiz final: quem concluiu passou na nota
            $tempo = is_array($e['tempo']) ? $rnd($e['tempo'][0], $e['tempo'][1]) : $e['tempo'];
            if ($e['tipo'] === 'sequencia' && $pct > .9) $tempo = $rnd(70, 120);
            $quando = $dias($dia);
            $insP->execute([$uid, $e['id'], $acertos, $total, $tempo, $quando]);
            $s = &$porFase[$e['fase']];
            $s['a'] = ($s['a'] ?? 0) + $acertos; $s['t'] = ($s['t'] ?? 0) + $total; $s['s'] = ($s['s'] ?? 0) + $tempo; $s['n'] = ($s['n'] ?? 0) + 1; $s['q'] = $quando;
            unset($s);
        }
        // conquistas pelas mesmas regras do engine
        $primeira = true;
        foreach ($porFase as $fo => $s) {
            if ($s['n'] < $nPorFase[$fo]) continue; // fase incompleta
            if ($primeira && isset($badges['Primeiro Passo'])) { $insB->execute([$uid, $badges['Primeiro Passo'], $s['q']]); $primeira = false; }
            if ($s['t'] > 0 && $s['a'] / $s['t'] >= .9 && isset($badges['Na Mosca'])) $insB->execute([$uid, $badges['Na Mosca'], $s['q']]);
            if ($s['s'] < 240 && isset($badges['Relâmpago'])) $insB->execute([$uid, $badges['Relâmpago'], $s['q']]);
        }
        if ($n === $nEtapas) {
            foreach (['Missão Cumprida', 'Trilha Completa'] as $b) if (isset($badges[$b])) $insB->execute([$uid, $badges[$b], $dias($d1)]);
        }
    }
    $out('Alunos fictícios: ' . count($personas) . ' (' . implode(' · ', array_map(fn($k, $v) => "{$v} {$k}", array_keys($porPerfil), $porPerfil)) . ').');

    // comentários: 3 aprovados (viram XP e badge), 1 pendente de moderação, 1 resposta oficial do gestor
    $faseId = fn(int $ordem) => (int) $fases[$ordem - 1]['id'];
    $insC->execute([$cli, $faseId(2), $ids['Ana Beatriz Souza'], null, 'A ordem da pele ficou muito clara com as cartas — eu sempre invertia pó e base na hora de explicar pra cliente.', 0, 'ativo', $dias(6)]);
    $c1 = (int) $db->lastInsertId();
    $insC->execute([$cli, $faseId(2), $gid[1], $c1, 'Boa, Ana! Lembra que a ordem também é o roteiro da apresentação completa — vale treinar em voz alta na bancada. 💚', 1, 'ativo', $dias(5)]);
    $insC->execute([$cli, $faseId(4), $ids['Camila Rocha'], null, 'A simulação da Amanda é igualzinha ao que acontece na loja: a cliente sempre acha que hidratar pesa a base.', 0, 'ativo', $dias(3)]);
    $insC->execute([$cli, $faseId(1), $ids['Juliana Freitas'], null, 'Não sabia que a social era o "tapete vermelho da vida real". Vou usar essa frase no atendimento!', 0, 'ativo', $dias(9)]);
    $insC->execute([$cli, $faseId(2), $ids['Larissa Mendes'], null, 'Dúvida: o contorno entra antes ou depois do pó quando a cliente tem pele oleosa?', 0, 'pendente', $dias(0.2)]);
    foreach (['Ana Beatriz Souza', 'Camila Rocha', 'Juliana Freitas'] as $nm) if (isset($badges['Voz da Equipe'])) $insB->execute([$ids[$nm], $badges['Voz da Equipe'], $dias(3)]);
    $out('Comentários: 4 (3 aprovados + 1 pendente de moderação) e 1 resposta oficial do gestor.');

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    $out('ERRO: ' . $e->getMessage());
    echo '</pre>'; exit;
}

$n = db_one('SELECT COUNT(*) AS n FROM usuario WHERE cliente_id = ?', [$cli])['n'];
$p = db_one('SELECT COUNT(*) AS n FROM progresso_usuario')['n'];
$out("Pronto. Usuários no tenant: {$n} · registros de progresso: {$p}.");
$out('Acessos (senha 1234): gestor1' . $DOM . ' · gestor2' . $DOM . ' · rh@boticario.com.br · sara@ / gui@ / erica@boticario.com.br');
echo '</pre>';
