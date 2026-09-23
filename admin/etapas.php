<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Etapas/conteúdos de uma FASE (o jogável).
// Construtor visual por tipo de dinâmica: o admin monta quiz,
// classificação, memória etc. em formulário e a tela gera o
// config JSON que o player consome (assets/js/player.js) —
// sem tocar em código. Só admin acessa.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['admin']);
if ($u['papel'] !== 'admin') { header('Location: ' . lms_home($u)); exit; }

$fid = (int) ($_GET['f'] ?? $_POST['fase_id'] ?? 0);
$fase = db_one('SELECT f.*, t.id AS trein_id, t.nome AS trein_nome, tr.nome AS trilha_nome, tr.cliente_id
                FROM fase f JOIN treinamento t ON t.id = f.treinamento_id JOIN trilha tr ON tr.id = t.trilha_id
                WHERE f.id = ?', [$fid]);
if (!$fase || (int) $fase['cliente_id'] !== (int) $u['cliente_id']) { header('Location: /boticario/admin/trilhas'); exit; }

// Tipos de dinâmica disponíveis (catálogo do player).
$tipos = [
    'leitura'       => ['🧾', 'Leitura com confirmação', 'Texto do procedimento + pontos críticos que o aluno confirma um a um.'],
    'quiz'          => ['❓', 'Quiz', 'Perguntas com uma ou várias respostas corretas, feedback imediato, explicação e introdução opcionais.'],
    'mito_verdade'  => ['🐉', 'Verdadeiro ou Falso', 'Afirmações para julgar (mito ou verdade), com explicação a cada resposta.'],
    'ligue_pontos'  => ['🔗', 'Ligue os pontos', 'Conectar termos da coluna esquerda às definições da direita.'],
    'classificacao' => ['🗂️', 'Classificação', 'Classificar itens entre 2 a 4 categorias, um por vez (ex.: pode / não pode).'],
    'memoria'       => ['🃏', 'Jogo da memória', 'Encontrar os pares de cartas relacionadas.'],
    'cenario'       => ['🎬', 'Cenário — o que você faria?', 'Situações narrativas com decisão: contexto ilustrado, opções e feedback do procedimento.'],
    'arrastar'      => ['🧳', 'Arrastar para zonas', 'Classificar muitos itens de uma vez em 2 a 4 zonas coloridas (ex.: obrigação / mediante aprovação / proibido).'],
    'historia'      => ['📖', 'História / HQ em cenas', 'Cenas sequenciais com imagem (quadrinhos), título e texto — para conteúdo narrativo.'],
    'caca'          => ['🎯', 'Caça no grid', 'Grid de células para varrer e revelar as ações corretas, uma a uma.'],
    'podcast'       => ['🎧', 'Podcast ilustrativo', 'Transcrição narrada por vozes/papéis, com player cenográfico (sem arquivo de áudio).'],
    'sequencia'     => ['🔢', 'Ordenação', 'Tocar nos passos na ordem em que acontecem — cada passo pode ter imagem e uma função revelada ao acertar.'],
    'video'         => ['🎞️', 'Vídeo', 'Vídeo do YouTube, Vimeo ou arquivo MP4 enviado pelo editor, com confirmação de assistido.'],
    'audio'         => ['🔊', 'Áudio real', 'Arquivo de áudio (MP3/M4A/OGG) com player nativo e transcrição opcional.'],
    'caca_palavras' => ['🔤', 'Caça-palavras', 'Grade de letras gerada automaticamente — o aluno encontra as palavras do tema.'],
    'cruzadinha'    => ['✏️', 'Cruzadinha', 'Palavras cruzadas geradas das suas palavras + dicas.'],
    'sete_erros'    => ['🔎', 'Jogo dos erros', 'Imagem com pontos escondidos para o aluno clicar e encontrar.'],
    'investigacao'  => ['🕵️', 'Investigação', 'Narrativa em cartas com áudio opcional por cena: ramificada (cada escolha abre um caminho) ou em modo domínio (errou? feedback e tenta de novo).'],
    'tabuleiro'     => ['🎲', 'Tabuleiro', 'Jogo de tabuleiro: jogue o dado, avance e responda a pergunta da casa.'],
    'atendimento'   => ['💬', 'Simulação de atendimento (chat)', 'A cliente conversa em balões de chat e o aluno escolhe a própria fala, turno a turno, pelas 4 etapas do atendimento — com bandeiras, satisfação, sacola e checklist do Cliente Oculto no fim.'],
];

// Validação mínima do config por tipo — garante que o player consegue rodar.
function etapa_valida(string $tipo, $cfg): ?string {
    if (!is_array($cfg)) return 'Config inválido.';
    $temTexto = fn($v) => is_string($v) && trim($v) !== '';
    switch ($tipo) {
        case 'leitura':
            if (empty($cfg['texto']) || !is_array($cfg['texto']) || !array_filter($cfg['texto'], $temTexto)) return 'A leitura precisa de ao menos um parágrafo.';
            if (empty($cfg['pontos_confirmacao']) || !is_array($cfg['pontos_confirmacao'])) return 'Inclua ao menos um ponto de confirmação.';
            foreach ($cfg['pontos_confirmacao'] as $p) if (!$temTexto($p['label'] ?? '')) return 'Todo ponto de confirmação precisa de texto.';
            return null;
        case 'quiz':
            if (empty($cfg['perguntas']) || !is_array($cfg['perguntas'])) return 'O quiz precisa de ao menos uma pergunta.';
            foreach ($cfg['perguntas'] as $q) {
                if (!$temTexto($q['enunciado'] ?? '')) return 'Toda pergunta precisa de enunciado.';
                if (count($q['alternativas'] ?? []) < 2) return 'Toda pergunta precisa de ao menos 2 alternativas.';
                if (empty($q['corretas']) || !is_array($q['corretas'])) return 'Marque ao menos uma alternativa correta em cada pergunta.';
                foreach ($q['corretas'] as $c) if (!isset($q['alternativas'][(int) $c])) return 'Alternativa correta fora do intervalo.';
            }
            return null;
        case 'mito_verdade':
            if (empty($cfg['itens']) || !is_array($cfg['itens'])) return 'Inclua ao menos uma afirmação.';
            foreach ($cfg['itens'] as $i) {
                if (!$temTexto($i['afirmacao'] ?? '') || !$temTexto($i['explicacao'] ?? '')) return 'Toda afirmação precisa de texto e explicação.';
                if (!is_bool($i['resposta'] ?? null)) return 'Defina Mito ou Verdade em todas as afirmações.';
            }
            return null;
        case 'ligue_pontos':
            if (count($cfg['pares'] ?? []) < 2) return 'Inclua ao menos 2 pares.';
            foreach ($cfg['pares'] as $p) if (!$temTexto($p['esquerda'] ?? '') || !$temTexto($p['direita'] ?? '')) return 'Todo par precisa dos dois lados preenchidos.';
            return null;
        case 'classificacao':
            $nCat = count($cfg['categorias'] ?? []);
            if ($nCat < 2 || $nCat > 4) return 'A classificação usa de 2 a 4 categorias.';
            if (empty($cfg['itens']) || !is_array($cfg['itens'])) return 'Inclua ao menos um item para classificar.';
            foreach ($cfg['itens'] as $i) {
                if (!$temTexto($i['item'] ?? '')) return 'Todo item precisa de texto.';
                if (!isset($cfg['categorias'][(int) ($i['categoria_correta'] ?? -1)])) return 'Todo item precisa de categoria correta válida.';
            }
            return null;
        case 'memoria':
            $n = count($cfg['cartas'] ?? []);
            if ($n < 4 || $n % 2 !== 0) return 'A memória precisa de ao menos 2 pares completos.';
            foreach ($cfg['cartas'] as $c) if (!$temTexto($c['texto'] ?? '') || !isset($c['par'])) return 'Toda carta precisa de texto e par.';
            return null;
        case 'cenario':
            if (empty($cfg['situacoes']) || !is_array($cfg['situacoes'])) return 'Inclua ao menos uma situação.';
            foreach ($cfg['situacoes'] as $s) {
                if (!$temTexto($s['contexto'] ?? '')) return 'Toda situação precisa de contexto.';
                if (count($s['opcoes'] ?? []) < 2) return 'Toda situação precisa de ao menos 2 opções.';
                if (!array_filter($s['opcoes'], fn($o) => !empty($o['correta']))) return 'Marque a opção correta em cada situação.';
                foreach ($s['opcoes'] as $o) if (!$temTexto($o['texto'] ?? '')) return 'Toda opção precisa de texto.';
            }
            return null;
        case 'arrastar':
            $nZ = count($cfg['zonas'] ?? []);
            if ($nZ < 2 || $nZ > 4) return 'Use de 2 a 4 zonas.';
            foreach ($cfg['zonas'] as $z) if (!$temTexto($z['titulo'] ?? '')) return 'Toda zona precisa de título.';
            if (empty($cfg['itens']) || !is_array($cfg['itens'])) return 'Inclua ao menos um item.';
            foreach ($cfg['itens'] as $i) {
                if (!$temTexto($i['texto'] ?? '')) return 'Todo item precisa de texto.';
                if (!isset($cfg['zonas'][(int) ($i['zona'] ?? -1)])) return 'Todo item precisa de zona válida.';
            }
            return null;
        case 'historia':
            if (empty($cfg['cenas']) || !is_array($cfg['cenas'])) return 'Inclua ao menos uma cena.';
            foreach ($cfg['cenas'] as $c) if (!$temTexto($c['texto'] ?? '')) return 'Toda cena precisa de texto.';
            return null;
        case 'caca':
            if (count($cfg['celulas'] ?? []) < 3) return 'A caça precisa de ao menos 3 células.';
            foreach ($cfg['celulas'] as $c) if (!$temTexto($c['titulo'] ?? '')) return 'Toda célula precisa de título.';
            return null;
        case 'podcast':
            if (empty($cfg['linhas']) || !is_array($cfg['linhas'])) return 'Inclua ao menos uma fala na transcrição.';
            foreach ($cfg['linhas'] as $l) if (!$temTexto($l['texto'] ?? '')) return 'Toda fala precisa de texto.';
            return null;
        case 'sequencia':
            if (count($cfg['passos'] ?? []) < 3) return 'A ordenação precisa de ao menos 3 passos.';
            foreach ($cfg['passos'] as $p) if (!$temTexto(is_array($p) ? ($p['texto'] ?? '') : $p)) return 'Todo passo precisa de texto.';
            return null;
        case 'video':
            if (!$temTexto($cfg['url'] ?? '')) return 'Informe a URL do vídeo (YouTube, Vimeo ou arquivo direto).';
            return null;
        case 'audio':
            if (!$temTexto($cfg['url'] ?? '')) return 'Envie o arquivo de áudio (ou informe a URL dele).';
            if (!empty($cfg['transcricao'])) {
                foreach ($cfg['transcricao'] as $l) if (!$temTexto($l['texto'] ?? '')) return 'Toda linha da transcrição precisa de texto.';
            }
            return null;
        case 'caca_palavras':
            $tam = (int) ($cfg['tam'] ?? 0);
            if ($tam < 6 || $tam > 18) return 'A grade do caça-palavras deve ter entre 6 e 18 letras de lado.';
            if (count($cfg['grade'] ?? []) !== $tam) return 'Grade inconsistente — salve de novo pelo construtor.';
            foreach ($cfg['grade'] as $linha) if (!is_string($linha) || mb_strlen($linha) !== $tam) return 'Grade inconsistente — salve de novo pelo construtor.';
            if (count($cfg['palavras'] ?? []) < 3) return 'Inclua ao menos 3 palavras.';
            foreach ($cfg['palavras'] as $p) {
                if (!$temTexto($p['palavra'] ?? '') || !isset($p['linha'], $p['col']) || !in_array($p['dir'] ?? '', ['h', 'v'], true)) return 'Palavra sem posição na grade — salve de novo pelo construtor.';
            }
            return null;
        case 'cruzadinha':
            if ((int) ($cfg['linhas'] ?? 0) < 2 || (int) ($cfg['cols'] ?? 0) < 2) return 'Cruzadinha sem grade — salve de novo pelo construtor.';
            if (count($cfg['palavras'] ?? []) < 2) return 'A cruzadinha precisa de ao menos 2 palavras.';
            foreach ($cfg['palavras'] as $p) {
                if (!$temTexto($p['palavra'] ?? '') || !$temTexto($p['dica'] ?? '')) return 'Toda palavra precisa de texto e dica.';
                if (!isset($p['linha'], $p['col'], $p['n']) || !in_array($p['dir'] ?? '', ['h', 'v'], true)) return 'Palavra sem posição — salve de novo pelo construtor.';
            }
            return null;
        case 'sete_erros':
            if (!$temTexto($cfg['imagem'] ?? '')) return 'Envie a imagem do jogo dos erros.';
            if (count($cfg['pontos'] ?? []) < 1) return 'Marque ao menos 1 ponto na imagem (clique sobre ela no construtor).';
            foreach ($cfg['pontos'] as $p) if (!isset($p['x'], $p['y'])) return 'Ponto sem posição — marque clicando na imagem.';
            $raio = (float) ($cfg['raio'] ?? 0);
            if ($raio < 2 || $raio > 20) return 'A tolerância do clique deve ficar entre 2 e 20.';
            return null;
        case 'investigacao':
            if (count($cfg['cartas'] ?? []) < 2) return 'A investigação precisa de ao menos 2 cartas.';
            foreach ($cfg['cartas'] as $c) {
                if (!$temTexto($c['pista'] ?? '')) return 'Toda carta precisa do texto da pista.';
                if (count($c['opcoes'] ?? []) < 2) return 'Toda carta precisa de ao menos 2 opções.';
                foreach ($c['opcoes'] as $o) {
                    if (!$temTexto($o['texto'] ?? '')) return 'Toda opção precisa de texto.';
                    $vai = $o['vai'] ?? null;
                    if ($vai !== null && (!is_numeric($vai) || (int) $vai < 0 || (int) $vai >= count($cfg['cartas']))) return 'Opção apontando para uma carta que não existe.';
                }
            }
            return null;
        case 'atendimento':
            if (!$temTexto($cfg['cliente']['nome'] ?? '')) return 'Dê um nome à cliente.';
            if (empty($cfg['turnos']) || !is_array($cfg['turnos'])) return 'O atendimento precisa de ao menos um turno.';
            $n = count($cfg['turnos']);
            foreach ($cfg['turnos'] as $t) {
                if (!is_array($t)) return 'Turno inválido.';
                $nOp = count($t['opcoes'] ?? []);
                if ($nOp === 0) continue;   // turno só narrativo (didascália + falas), sem decisão
                if ($nOp < 2) return 'Todo turno com decisão precisa de ao menos 2 opções de resposta.';
                if (!array_filter($t['opcoes'], fn($o) => !empty($o['correta']))) return 'Marque a opção correta em cada turno.';
                foreach ($t['opcoes'] as $o) {
                    if (!$temTexto($o['texto'] ?? '')) return 'Toda opção precisa de texto.';
                    if (array_key_exists('vai', $o) && $o['vai'] !== null && (!is_numeric($o['vai']) || (int) $o['vai'] < 0 || (int) $o['vai'] >= $n)) return 'Opção apontando para um turno que não existe.';
                }
            }
            foreach ($cfg['bandeiras'] ?? [] as $b) if (!$temTexto($b['id'] ?? '') || !$temTexto($b['nome'] ?? '')) return 'Toda bandeira precisa de id e nome.';
            foreach ($cfg['checklist'] ?? [] as $c) if (!$temTexto($c['id'] ?? '') || !$temTexto($c['texto'] ?? '')) return 'Todo item do checklist precisa de id e texto.';
            return null;
        case 'tabuleiro':
            if (count($cfg['casas'] ?? []) < 4) return 'O tabuleiro precisa de ao menos 4 casas.';
            foreach ($cfg['casas'] as $c2) {
                if (!$temTexto($c2['pergunta'] ?? '')) return 'Toda casa precisa de pergunta.';
                if (count($c2['alternativas'] ?? []) < 2) return 'Toda pergunta precisa de ao menos 2 alternativas.';
                if (empty($c2['corretas']) || !is_array($c2['corretas'])) return 'Marque a alternativa correta em cada casa.';
                foreach ($c2['corretas'] as $ci) if (!isset($c2['alternativas'][(int) $ci])) return 'Alternativa correta fora do intervalo.';
            }
            return null;
    }
    return 'Tipo de dinâmica desconhecido.';
}

// ── Ações (POST) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);
    $alvo = $id ? db_one('SELECT * FROM etapa WHERE id = ? AND fase_id = ?', [$id, $fid]) : null;

    if ($acao === 'e_salvar') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $tipo   = array_key_exists($_POST['tipo'] ?? '', $tipos) ? $_POST['tipo'] : '';
        $obrig  = !empty($_POST['obrigatoria']) ? 1 : 0;
        $cfg    = json_decode((string) ($_POST['config'] ?? ''), true);
        $erro   = $titulo === '' ? 'Dê um título à etapa.' : ($tipo === '' ? 'Escolha o tipo de dinâmica.' : etapa_valida($tipo, $cfg));
        if ($erro) {
            lms_flash_set(false, $erro);
        } else {
            $json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
            $tipoBase = $tipo === 'leitura' ? 'leitura' : 'minigame';
            if ($alvo) {
                db_run('UPDATE etapa SET titulo = ?, tipo = ?, minigame_tipo = ?, obrigatoria = ?, config = ? WHERE id = ?',
                       [$titulo, $tipoBase, $tipo, $obrig, $json, $id]);
                lms_flash_set(true, "Etapa “{$titulo}” atualizada.");
            } else {
                $ordem = (int) db_one('SELECT COALESCE(MAX(ordem),0)+1 AS o FROM etapa WHERE fase_id = ?', [$fid])['o'];
                db_run('INSERT INTO etapa (fase_id, ordem, tipo, minigame_tipo, obrigatoria, titulo, config) VALUES (?,?,?,?,?,?,?)',
                       [$fid, $ordem, $tipoBase, $tipo, $obrig, $titulo, $json]);
                lms_flash_set(true, "Etapa “{$titulo}” adicionada.");
            }
        }

    } elseif ($acao === 'e_excluir' && $alvo) {
        db_run('DELETE FROM progresso_usuario WHERE etapa_id = ?', [$id]);
        db_run('DELETE FROM etapa WHERE id = ?', [$id]);
        lms_flash_set(true, "Etapa “{$alvo['titulo']}” excluída (registros de progresso dela também).");

    } elseif ($acao === 'e_mover' && $alvo) {
        $dir = $_POST['dir'] === 'up' ? -1 : 1;
        $viz = db_one('SELECT * FROM etapa WHERE fase_id = ? AND ordem ' . ($dir < 0 ? '<' : '>') . ' ? ORDER BY ordem ' . ($dir < 0 ? 'DESC' : 'ASC') . ' LIMIT 1',
                      [$fid, $alvo['ordem']]);
        if ($viz) {
            db_run('UPDATE etapa SET ordem = ? WHERE id = ?', [$viz['ordem'], $alvo['id']]);
            db_run('UPDATE etapa SET ordem = ? WHERE id = ?', [$alvo['ordem'], $viz['id']]);
        }
    }
    header('Location: /boticario/admin/etapas?f=' . $fid); exit;
}

// ── Dados da tela ────────────────────────────────────────────
$editando = null;
if (!empty($_GET['editar'])) {
    $editando = db_one('SELECT * FROM etapa WHERE id = ? AND fase_id = ?', [(int) $_GET['editar'], $fid]);
}
$etapas = db_all('SELECT * FROM etapa WHERE fase_id = ? ORDER BY ordem', [$fid]);
$flash = lms_flash_get();

lms_head('Etapas · ' . $fase['nome'], $u);
lms_topbar($u);
?>
<main class="lms-container" style="padding-top:28px; padding-bottom:64px">

  <div class="lms-row" style="justify-content:space-between; margin-bottom:8px">
    <a class="lms-small" href="/boticario/admin/mapa?t=<?= (int) $fase['trein_id'] ?>">← Voltar ao mapa</a>
    <span class="lms-pill"><?= e($fase['trilha_nome']) ?> · 📁 <?= e($fase['trein_nome']) ?> · Fase <?= (int) $fase['ordem'] ?></span>
  </div>
  <div class="lms-row" style="justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap">
    <div>
      <h1 style="font-size:var(--lms-title-md)">📍 <?= e($fase['nome']) ?></h1>
      <p class="lms-muted lms-small" style="margin-top:4px"><?= count($etapas) ?> etapa<?= count($etapas) === 1 ? '' : 's' ?> — o aluno percorre na ordem abaixo</p>
    </div>
    <button type="button" class="lms-btn lms-btn-primary" id="btn-nova-etapa">+ Nova etapa</button>
  </div>

  <?php if ($flash): ?>
  <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:18px">
    <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div>
  </div>
  <?php endif; ?>

  <?php lms_busca('.lms-table tbody tr', 'Buscar etapa por título, dinâmica…', count($etapas), 'etapas', 'e-busca'); ?>

  <!-- lista de etapas -->
  <div class="lms-table-wrap" style="margin-top:14px">
    <table class="lms-table">
      <thead><tr><th style="width:36px">#</th><th>Etapa</th><th>Dinâmica</th><th>Obrigatória</th><th style="text-align:right">Ações</th></tr></thead>
      <tbody>
        <?php foreach ($etapas as $e2): [$ico, $lbl] = $tipos[$e2['minigame_tipo']] ?? ['⬜', $e2['minigame_tipo']]; ?>
        <tr>
          <td class="lms-muted lms-small"><?= (int) $e2['ordem'] ?></td>
          <td><strong style="font-family:var(--lms-font-ui); font-weight:600"><?= e($e2['titulo']) ?></strong></td>
          <td><span class="lms-pill"><?= $ico ?> <?= e($lbl) ?></span></td>
          <td class="lms-small"><?= $e2['obrigatoria'] ? 'sim' : 'não' ?></td>
          <td style="text-align:right; white-space:nowrap">
            <form method="post" style="display:inline"><input type="hidden" name="acao" value="e_mover"><input type="hidden" name="fase_id" value="<?= $fid ?>"><input type="hidden" name="id" value="<?= (int) $e2['id'] ?>"><input type="hidden" name="dir" value="up"><button class="lms-btn lms-btn-outline lms-btn-sm" title="Subir">↑</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="acao" value="e_mover"><input type="hidden" name="fase_id" value="<?= $fid ?>"><input type="hidden" name="id" value="<?= (int) $e2['id'] ?>"><input type="hidden" name="dir" value="down"><button class="lms-btn lms-btn-outline lms-btn-sm" title="Descer">↓</button></form>
            <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/admin/etapas?f=<?= $fid ?>&editar=<?= (int) $e2['id'] ?>">Editar</a>
            <form method="post" style="display:inline"
                  data-confirmar-titulo="Excluir a etapa “<?= e($e2['titulo']) ?>”?"
                  data-confirmar="O progresso dos alunos nesta etapa será apagado. Sem volta." data-confirmar-perigo>
              <input type="hidden" name="acao" value="e_excluir"><input type="hidden" name="fase_id" value="<?= $fid ?>"><input type="hidden" name="id" value="<?= (int) $e2['id'] ?>">
              <button class="lms-btn lms-btn-outline lms-btn-sm" style="color:var(--lms-error); border-color:var(--lms-error)">Excluir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; if (!$etapas): ?>
        <tr><td colspan="5" class="lms-muted lms-small">Nenhuma etapa ainda — clique em <strong>+ Nova etapa</strong> para montar a primeira. 👆</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php
// construtor de etapa: popup largo (o builder dos minigames é grande)
lms_popup_abre('fe', $editando ? '✏️ Editar etapa: ' . e($editando['titulo']) : '➕ Nova etapa',
               'Escolha a dinâmica, preencha o conteúdo e salve — o minigame fica pronto para o aluno na hora.', 880);
?>
    <form method="post" id="f-etapa">
      <input type="hidden" name="acao" value="e_salvar">
      <input type="hidden" name="fase_id" value="<?= $fid ?>">
      <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
      <input type="hidden" name="config" id="e-config">

      <div style="display:grid; grid-template-columns:2fr 1fr auto; gap:14px; align-items:end">
        <div>
          <label class="lms-label" for="e-titulo">Título da etapa</label>
          <input class="lms-input" id="e-titulo" name="titulo" required value="<?= e($editando['titulo'] ?? '') ?>" placeholder="Ex.: Quiz: içamento na prática">
        </div>
        <div>
          <label class="lms-label" for="e-tipo">Dinâmica</label>
          <select class="lms-select" id="e-tipo" name="tipo" <?= $editando ? 'disabled' : '' ?>>
            <?php foreach ($tipos as $k => [$ico, $lbl]): ?>
            <option value="<?= e($k) ?>" <?= ($editando['minigame_tipo'] ?? 'leitura') === $k ? 'selected' : '' ?>><?= $ico ?> <?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($editando): ?><input type="hidden" name="tipo" value="<?= e($editando['minigame_tipo']) ?>"><?php endif; ?>
        </div>
        <label class="lms-check" style="margin-bottom:2px">
          <input type="checkbox" name="obrigatoria" <?= ($editando['obrigatoria'] ?? 1) ? 'checked' : '' ?>> obrigatória
        </label>
      </div>

      <p class="lms-small lms-muted" id="e-desc" style="margin-top:10px"></p>

      <!-- área do construtor (montada pelo JS conforme o tipo) -->
      <div id="builder" style="margin-top:16px"></div>

      <div class="lms-row" style="margin-top:20px">
        <button class="lms-btn lms-btn-primary"><?= $editando ? 'Salvar alterações' : 'Adicionar etapa' ?></button>
        <button type="button" class="lms-btn lms-btn-outline" data-fecha="fe">Cancelar</button>
        <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="toggle-json" style="margin-left:auto">{ } modo avançado</button>
      </div>
      <textarea class="lms-input lms-hide" id="e-json" rows="12" style="margin-top:12px; font-family:monospace; font-size:12px" spellcheck="false"></textarea>
    </form>
<?php lms_popup_fecha('fe', 'btn-nova-etapa', (bool) $editando, '/boticario/admin/etapas?f=' . $fid); ?>

<style>
  .b-bloco { border:var(--lms-border); border-radius:var(--lms-radius-md); padding:16px; margin-bottom:12px; background:var(--lms-white) }
  .b-bloco .lms-input, .b-bloco textarea.lms-input { margin-top:4px }
  .b-linha { display:flex; gap:8px; align-items:center; margin-top:8px }
  .b-linha .lms-input { flex:1 }
  .b-rm { flex:none; width:32px; height:32px; border-radius:6px; border:1.5px solid var(--lms-error); color:var(--lms-error);
    background:var(--lms-white); cursor:pointer; font-weight:700; line-height:1 }
  .b-add { margin-top:10px }
  .b-corr { flex:none; display:flex; align-items:center; gap:5px; font-size:12px; font-family:var(--lms-font-ui);
    color:var(--lms-ink-soft); user-select:none; cursor:pointer }
</style>

<script>
/* ── Construtor visual de etapas ─────────────────────────────
   STATE é a fonte da verdade; cada tipo tem render() que desenha
   o formulário a partir do STATE e grava mudanças de volta nele.
   No submit, STATE vira o config JSON validado pelo servidor.  */

const DESCRICOES = <?= json_encode(array_map(fn($t) => $t[2], $tipos), JSON_UNESCAPED_UNICODE) ?>;
const EDITANDO = <?= $editando ? json_decode($editando['config']) !== null ? $editando['config'] : 'null' : 'null' ?>;
const TIPO_INICIAL = <?= json_encode($editando['minigame_tipo'] ?? 'leitura') ?>;

const builder = document.getElementById('builder');
const selTipo = document.getElementById('e-tipo');
const inpConfig = document.getElementById('e-config');
const txtJson = document.getElementById('e-json');
let STATE = null, tipoAtual = null, modoJson = false;

const VAZIOS = {
  leitura:       () => ({ texto: [''], pontos_confirmacao: [{ id: 'p1', label: '' }] }),
  quiz:          () => ({ intro: '', perguntas: [{ enunciado: '', alternativas: ['', ''], corretas: [], explicacao: '' }] }),
  mito_verdade:  () => ({ itens: [{ afirmacao: '', resposta: true, explicacao: '' }] }),
  ligue_pontos:  () => ({ instrucao: 'Conecte cada termo à sua definição.', pares: [{ esquerda: '', direita: '' }, { esquerda: '', direita: '' }] }),
  classificacao: () => ({ instrucao: '', categorias: ['', ''], itens: [{ item: '', categoria_correta: 0 }] }),
  memoria:       () => ({ instrucao: 'Encontre os pares.', cartas: [{ par: 1, texto: '' }, { par: 1, texto: '' }, { par: 2, texto: '' }, { par: 2, texto: '' }] }),
  cenario:       () => ({ situacoes: [{ icone: '🎬', contexto: '', opcoes: [{ texto: '', correta: false }, { texto: '', correta: true }], feedback: '' }] }),
  arrastar:      () => ({ instrucao: '', zonas: [{ titulo: '✔ Pode', cor: 'mint' }, { titulo: '✕ Não pode', cor: 'rose' }], itens: [{ texto: '', zona: 0 }] }),
  historia:      () => ({ cenas: [{ icone: '📖', titulo: '', texto: '', imagem: '' }] }),
  caca:          () => ({ instrucao: '', celulas: [{ titulo: '', descricao: '' }, { titulo: '', descricao: '' }, { titulo: '', descricao: '' }] }),
  podcast:       () => ({ titulo: '', subtitulo: '', linhas: [{ quem: '', texto: '' }] }),
  sequencia:     () => ({ instrucao: 'Toque nos passos na ordem correta.', so_imagem: false, passos: [{ texto: '', imagem: '', descricao: '' }, { texto: '', imagem: '', descricao: '' }, { texto: '', imagem: '', descricao: '' }] }),
  video:         () => ({ url: '', descricao: '' }),
  audio:         () => ({ url: '', titulo: '', subtitulo: '', transcricao: [] }),
  caca_palavras: () => ({ instrucao: 'Encontre as palavras na grade — clique na primeira e na última letra.', palavras: ['', '', ''] }),
  cruzadinha:    () => ({ instrucao: 'Preencha a cruzadinha com as dicas.', itens: [{ palavra: '', dica: '' }, { palavra: '', dica: '' }] }),
  sete_erros:    () => ({ instrucao: 'Clique nos pontos escondidos na imagem.', imagem: '', raio: 6, pontos: [] }),
  investigacao:  () => ({ modo: 'ramificada', rotulo_seguir: '', rotulo_fim: '', cartas: [
    { icone: '🕵️', titulo: '', pista: '', pergunta: '', audio: '', audio_quem: '', opcoes: [{ texto: '', correta: true, vai: 1, feedback: '' }, { texto: '', correta: false, vai: null, feedback: '' }] },
    { icone: '🔎', titulo: '', pista: '', pergunta: '', audio: '', audio_quem: '', opcoes: [{ texto: '', correta: true, vai: null, feedback: '' }, { texto: '', correta: false, vai: null, feedback: '' }] },
  ] }),
  tabuleiro:     () => ({ instrucao: '', casas: Array.from({ length: 4 }, () => ({ pergunta: '', alternativas: ['', ''], corretas: [0] })) }),
  atendimento:   () => ({ modo: 'dominio', humor_inicial: 50, etapas: ['Acolha', 'Personalize', 'Potencialize', 'Encante'],
    cliente: { nome: '', avatar: '👩', persona: '', descricao: '' }, briefing: { titulo: '', texto: '', botao: '' },
    bandeiras: [{ id: 'identificacao', icone: '🆔', nome: 'Identificação' }, { id: 'experimentacao', icone: '🧪', nome: 'Experimentação' }, { id: 'beautybox', icone: '💚', nome: 'Beautybox' }],
    checklist: [], turnos: [{ etapa: 0, cena: '', falas: [], pergunta: '', opcoes: [{ texto: '', correta: true, reacao: [], feedback: '' }, { texto: '', correta: false, reacao: [], feedback: '' }] }],
    final: { titulo: '', texto: '', sem_venda: false, motivo_nao_compra: '' } }),
};
const CORES_ZONA = { mint: '🟢 Verde', sand: '🟡 Âmbar', rose: '🔴 Vermelho', sky: '🔵 Azul' };

/* caça-palavras e cruzadinha são GERADOS: o admin edita palavras/dicas e,
   no salvar, o construtor calcula a grade. Ao reeditar, volta ao editável. */
const NORMALIZA = {
  sequencia:     cfg => ({ ...cfg, passos: (cfg.passos || []).map(p => typeof p === 'string' ? { texto: p, imagem: '', descricao: '' } : p) }),
  caca_palavras: cfg => cfg.grade ? ({ instrucao: cfg.instrucao || '', palavras: cfg.palavras.map(p => p.palavra) }) : cfg,
  cruzadinha:    cfg => cfg.linhas ? ({ instrucao: cfg.instrucao || '', itens: cfg.palavras.map(p => ({ palavra: p.palavra, dica: p.dica })) }) : cfg,
};

const soLetras = w => (w || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z]/g, '');

function geraCaca(s) {
  const palavras = (s.palavras || []).map(soLetras).filter(w => w.length >= 3);
  if (palavras.length < 3) throw new Error('Inclua ao menos 3 palavras com 3+ letras.');
  const maxLen = Math.max(...palavras.map(w => w.length));
  if (maxLen > 14) throw new Error('Use palavras de até 14 letras.');
  const tam = Math.min(18, Math.max(8, maxLen + 2, Math.ceil(Math.sqrt(palavras.join('').length * 2.2))));
  const g = Array.from({ length: tam }, () => Array(tam).fill(null));
  const pos = [];
  for (const w of [...palavras].sort((a, b) => b.length - a.length)) {
    let ok = false;
    for (let t = 0; t < 500 && !ok; t++) {
      const dir = Math.random() < .5 ? 'h' : 'v';
      const l = Math.floor(Math.random() * (dir === 'h' ? tam : tam - w.length + 1));
      const c = Math.floor(Math.random() * (dir === 'h' ? tam - w.length + 1 : tam));
      let cabe = true;
      for (let k = 0; k < w.length; k++) {
        const cur = g[dir === 'h' ? l : l + k][dir === 'h' ? c + k : c];
        if (cur !== null && cur !== w[k]) { cabe = false; break; }
      }
      if (!cabe) continue;
      for (let k = 0; k < w.length; k++) g[dir === 'h' ? l : l + k][dir === 'h' ? c + k : c] = w[k];
      pos.push({ palavra: w, linha: l, col: c, dir });
      ok = true;
    }
    if (!ok) throw new Error('Não coube a palavra ' + w + ' — tente menos palavras ou palavras menores.');
  }
  const AZ = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  return { instrucao: s.instrucao || '', tam,
           grade: g.map(row => row.map(ch => ch ?? AZ[Math.floor(Math.random() * 26)]).join('')),
           palavras: pos };
}

function geraCruzadinha(s) {
  const itens = (s.itens || []).map(o => ({ palavra: soLetras(o.palavra), dica: (o.dica || '').trim() }))
                               .filter(o => o.palavra.length >= 2 && o.dica);
  if (itens.length < 2) throw new Error('A cruzadinha precisa de ao menos 2 palavras com dica.');
  const cells = {};
  const put = (w, l, c, dir) => { for (let k = 0; k < w.length; k++) cells[(dir === 'h' ? l : l + k) + ',' + (dir === 'h' ? c + k : c)] = w[k]; };
  const okAt = (w, l, c, dir) => {
    const antes = dir === 'h' ? [l, c - 1] : [l - 1, c], depois = dir === 'h' ? [l, c + w.length] : [l + w.length, c];
    if (cells[antes.join(',')] !== undefined || cells[depois.join(',')] !== undefined) return false;
    let cruza = false;
    for (let k = 0; k < w.length; k++) {
      const L = dir === 'h' ? l : l + k, C = dir === 'h' ? c + k : c;
      const cur = cells[L + ',' + C];
      if (cur !== undefined) { if (cur !== w[k]) return false; cruza = true; continue; }
      const v1 = dir === 'h' ? [L - 1, C] : [L, C - 1], v2 = dir === 'h' ? [L + 1, C] : [L, C + 1];
      if (cells[v1.join(',')] !== undefined || cells[v2.join(',')] !== undefined) return false;
    }
    return cruza;
  };
  const colocadas = [{ ...itens[0], linha: 30, col: 30, dir: 'h' }];
  put(itens[0].palavra, 30, 30, 'h');
  let pend = itens.slice(1), rodadas = 0;
  while (pend.length && rodadas++ < itens.length + 3) {
    const ainda = [];
    for (const it of pend) {
      let feito = false;
      busca:
      for (const [key, ch] of Object.entries(cells)) {
        const [L, C] = key.split(',').map(Number);
        for (let i = 0; i < it.palavra.length; i++) {
          if (it.palavra[i] !== ch) continue;
          for (const dir of ['v', 'h']) {
            const l = dir === 'h' ? L : L - i, c = dir === 'h' ? C - i : C;
            if (okAt(it.palavra, l, c, dir)) {
              put(it.palavra, l, c, dir);
              colocadas.push({ ...it, linha: l, col: c, dir });
              feito = true; break busca;
            }
          }
        }
      }
      if (!feito) ainda.push(it);
    }
    if (ainda.length === pend.length) throw new Error('Não consegui cruzar "' + ainda[0].palavra + '" com as demais — troque a palavra ou a ordem da lista.');
    pend = ainda;
  }
  let minL = 999, minC = 999, maxL = 0, maxC = 0;
  Object.keys(cells).forEach(k => { const [l, c] = k.split(',').map(Number);
    minL = Math.min(minL, l); minC = Math.min(minC, c); maxL = Math.max(maxL, l); maxC = Math.max(maxC, c); });
  if (maxL - minL + 1 > 18 || maxC - minC + 1 > 18) throw new Error('A cruzadinha ficou grande demais — use palavras mais curtas.');
  const palavras = colocadas.map(p => ({ palavra: p.palavra, dica: p.dica, linha: p.linha - minL, col: p.col - minC, dir: p.dir }));
  const starts = {};
  [...palavras].sort((a, b) => a.linha - b.linha || a.col - b.col).forEach(p => {
    const k = p.linha + ',' + p.col;
    if (starts[k] === undefined) starts[k] = Object.keys(starts).length + 1;
  });
  palavras.forEach(p => p.n = starts[p.linha + ',' + p.col]);
  return { instrucao: s.instrucao || '', linhas: maxL - minL + 1, cols: maxC - minC + 1, palavras };
}
const GERA = { caca_palavras: geraCaca, cruzadinha: geraCruzadinha };

const h = (html) => { const d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstElementChild; };
const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');

/* input de texto ligado a um caminho do STATE */
function campo(valor, placeholder, onInput, tag = 'input') {
  const el2 = h(tag === 'textarea'
    ? `<textarea class="lms-input" rows="3" placeholder="${esc(placeholder)}">${esc(valor)}</textarea>`
    : `<input class="lms-input" placeholder="${esc(placeholder)}" value="${esc(valor)}">`);
  el2.addEventListener('input', () => onInput(el2.value));
  return el2;
}
function btnRm(onClick, titulo = 'Remover') {
  const b = h(`<button type="button" class="b-rm" title="${titulo}">×</button>`);
  b.addEventListener('click', onClick);
  return b;
}
function btnAdd(rotulo, onClick) {
  const b = h(`<button type="button" class="lms-btn lms-btn-outline lms-btn-sm b-add">+ ${rotulo}</button>`);
  b.addEventListener('click', onClick);
  return b;
}

/* upload de mídia (api/midia): imagem ou áudio → grava a URL no STATE */
function campoMidia(valor, aceita, onUrl) {
  const box = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
  const inp = h(`<input type="file" accept="${aceita}" class="lms-input" style="max-width:260px; padding:8px; flex:none">`);
  const status = h(`<span class="lms-small ${valor ? '' : 'lms-muted'}" style="flex:1; min-width:140px; overflow-wrap:anywhere">${valor ? '✓ ' + esc(valor) : 'nenhum arquivo ainda'}</span>`);
  inp.addEventListener('change', async () => {
    if (!inp.files[0]) return;
    status.textContent = '⏳ enviando…';
    const fd = new FormData();
    fd.append('arquivo', inp.files[0]);
    try {
      const r = await fetch('/boticario/api/midia', { method: 'POST', body: fd }).then(x => x.json());
      if (r.ok) { onUrl(r.url); status.textContent = '✓ ' + r.url; }
      else { status.textContent = 'falhou'; alert(r.msg || 'Upload não aceito.'); }
    } catch (e) { status.textContent = 'falhou'; alert('Falha de conexão no upload.'); }
  });
  box.appendChild(inp);
  box.appendChild(status);
  return box;
}

const RENDERS = {
  leitura(s) {
    const box = h('<div></div>');
    const bl1 = h('<div class="b-bloco"><label class="lms-label">Parágrafos do texto</label></div>');
    s.texto.forEach((t, i) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(t, `Parágrafo ${i + 1}…`, v => s.texto[i] = v, 'textarea'));
      if (s.texto.length > 1) linha.appendChild(btnRm(() => { s.texto.splice(i, 1); render(); }));
      bl1.appendChild(linha);
    });
    bl1.appendChild(btnAdd('parágrafo', () => { s.texto.push(''); render(); }));
    box.appendChild(bl1);

    const bl2 = h('<div class="b-bloco"><label class="lms-label">✋ Pontos de confirmação (o aluno marca um a um)</label></div>');
    s.pontos_confirmacao.forEach((p, i) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(p.label, 'Entendi que…', v => p.label = v));
      if (s.pontos_confirmacao.length > 1) linha.appendChild(btnRm(() => { s.pontos_confirmacao.splice(i, 1); render(); }));
      bl2.appendChild(linha);
    });
    bl2.appendChild(btnAdd('ponto de confirmação', () => { s.pontos_confirmacao.push({ id: 'p' + (s.pontos_confirmacao.length + 1), label: '' }); render(); }));
    box.appendChild(bl2);
    return box;
  },

  quiz(s) {
    const box = h('<div></div>');
    const blIntro = h('<div class="b-bloco"><label class="lms-label">Introdução (opcional — aparece antes da 1ª pergunta)</label></div>');
    blIntro.appendChild(campo(s.intro || '', 'Contexto ou regra que fundamenta as perguntas…', v => s.intro = v, 'textarea'));
    box.appendChild(blIntro);
    s.perguntas.forEach((q, qi) => {
      const bl = h(`<div class="b-bloco"></div>`);
      const cab = h(`<div class="lms-row" style="justify-content:space-between"><label class="lms-label">Pergunta ${qi + 1}</label></div>`);
      if (s.perguntas.length > 1) cab.appendChild(btnRm(() => { s.perguntas.splice(qi, 1); render(); }, 'Remover pergunta'));
      bl.appendChild(cab);
      bl.appendChild(campo(q.enunciado, 'Enunciado da pergunta…', v => q.enunciado = v, 'textarea'));

      const lbl = h('<label class="lms-label" style="margin-top:10px; display:block">Alternativas (marque a[s] correta[s])</label>');
      bl.appendChild(lbl);
      q.alternativas.forEach((a, ai) => {
        const linha = h('<div class="b-linha"></div>');
        const chk = h(`<label class="b-corr"><input type="checkbox" ${q.corretas.includes(ai) ? 'checked' : ''}> correta</label>`);
        chk.querySelector('input').addEventListener('change', (ev) => {
          if (ev.target.checked) q.corretas.push(ai);
          else q.corretas = q.corretas.filter(c => c !== ai);
          q.corretas.sort();
        });
        linha.appendChild(campo(a, `Alternativa ${ai + 1}…`, v => q.alternativas[ai] = v));
        linha.appendChild(chk);
        if (q.alternativas.length > 2) linha.appendChild(btnRm(() => {
          q.alternativas.splice(ai, 1);
          q.corretas = q.corretas.filter(c => c !== ai).map(c => c > ai ? c - 1 : c);
          render();
        }));
        bl.appendChild(linha);
      });
      bl.appendChild(btnAdd('alternativa', () => { q.alternativas.push(''); render(); }));
      bl.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">Explicação (opcional — aparece depois da resposta, certa ou errada)</label>'));
      bl.appendChild(campo(q.explicacao || '', 'Por que essa é a resposta certa…', v => q.explicacao = v, 'textarea'));
      box.appendChild(bl);
    });
    box.appendChild(btnAdd('pergunta', () => { s.perguntas.push({ enunciado: '', alternativas: ['', ''], corretas: [], explicacao: '' }); render(); }));
    return box;
  },

  mito_verdade(s) {
    const box = h('<div></div>');
    s.itens.forEach((it, i) => {
      const bl = h('<div class="b-bloco"></div>');
      const cab = h(`<div class="lms-row" style="justify-content:space-between"><label class="lms-label">Afirmação ${i + 1}</label></div>`);
      if (s.itens.length > 1) cab.appendChild(btnRm(() => { s.itens.splice(i, 1); render(); }));
      bl.appendChild(cab);
      bl.appendChild(campo(it.afirmacao, 'A afirmação que o aluno vai julgar…', v => it.afirmacao = v, 'textarea'));
      const selRow = h(`<div class="b-linha">
        <select class="lms-select" style="max-width:180px">
          <option value="false" ${!it.resposta ? 'selected' : ''}>🐉 Mito (falsa)</option>
          <option value="true" ${it.resposta ? 'selected' : ''}>✅ Verdade</option>
        </select>
      </div>`);
      selRow.querySelector('select').addEventListener('change', ev => it.resposta = ev.target.value === 'true');
      selRow.appendChild(campo(it.explicacao, 'Explicação mostrada após a resposta…', v => it.explicacao = v));
      bl.appendChild(selRow);
      box.appendChild(bl);
    });
    box.appendChild(btnAdd('afirmação', () => { s.itens.push({ afirmacao: '', resposta: true, explicacao: '' }); render(); }));
    return box;
  },

  ligue_pontos(s) {
    const box = h('<div class="b-bloco"></div>');
    box.appendChild(h('<label class="lms-label">Instrução</label>'));
    box.appendChild(campo(s.instrucao, 'Ex.: Conecte cada termo à sua definição.', v => s.instrucao = v));
    box.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Pares (esquerda ↔ direita)</label>'));
    s.pares.forEach((p, i) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(p.esquerda, 'Termo…', v => p.esquerda = v));
      linha.appendChild(h('<span class="lms-muted">↔</span>'));
      linha.appendChild(campo(p.direita, 'Definição…', v => p.direita = v));
      if (s.pares.length > 2) linha.appendChild(btnRm(() => { s.pares.splice(i, 1); render(); }));
      box.appendChild(linha);
    });
    box.appendChild(btnAdd('par', () => { s.pares.push({ esquerda: '', direita: '' }); render(); }));
    return box;
  },

  classificacao(s) {
    const box = h('<div></div>');
    const bl1 = h('<div class="b-bloco"><label class="lms-label">Instrução</label></div>');
    bl1.appendChild(campo(s.instrucao, 'Ex.: Classifique cada item…', v => s.instrucao = v));
    bl1.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Categorias (2 a 4)</label>'));
    s.categorias.forEach((c, ci) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(c, `Categoria ${ci + 1}…`, v => { s.categorias[ci] = v; }));
      if (s.categorias.length > 2) linha.appendChild(btnRm(() => {
        s.categorias.splice(ci, 1);
        s.itens.forEach(it => { if (it.categoria_correta >= s.categorias.length) it.categoria_correta = 0; });
        render();
      }));
      bl1.appendChild(linha);
    });
    if (s.categorias.length < 4) bl1.appendChild(btnAdd('categoria', () => { s.categorias.push(''); render(); }));
    box.appendChild(bl1);

    const bl2 = h('<div class="b-bloco"><label class="lms-label">Itens para classificar</label></div>');
    s.itens.forEach((it, i) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(it.item, 'Item…', v => it.item = v));
      const sel = h(`<select class="lms-select" style="max-width:200px">${s.categorias.map((c, ci) =>
        `<option value="${ci}" ${it.categoria_correta === ci ? 'selected' : ''}>${esc(c || 'Categoria ' + (ci + 1))}</option>`).join('')}</select>`);
      sel.addEventListener('change', ev => it.categoria_correta = +ev.target.value);
      linha.appendChild(sel);
      if (s.itens.length > 1) linha.appendChild(btnRm(() => { s.itens.splice(i, 1); render(); }));
      bl2.appendChild(linha);
    });
    bl2.appendChild(btnAdd('item', () => { s.itens.push({ item: '', categoria_correta: 0 }); render(); }));
    box.appendChild(bl2);
    return box;
  },

  memoria(s) {
    // edição por PARES; s.cartas é a forma final [{par, texto}, …]
    const pares = [];
    s.cartas.forEach(c => {
      (pares[c.par - 1] = pares[c.par - 1] || []).push(c);
    });
    const box = h('<div class="b-bloco"></div>');
    box.appendChild(h('<label class="lms-label">Instrução</label>'));
    box.appendChild(campo(s.instrucao, 'Ex.: Encontre os pares.', v => s.instrucao = v));
    box.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Pares de cartas (ex.: 🧴 Produto ↔ Benefício)</label>'));
    pares.forEach((par, i) => {
      const [c1, c2] = [par[0] || { texto: '' }, par[1] || { texto: '' }];
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(c1.texto, 'Carta A…', v => c1.texto = v));
      linha.appendChild(h('<span class="lms-muted">↔</span>'));
      linha.appendChild(campo(c2.texto, 'Carta B…', v => c2.texto = v));
      if (pares.length > 2) linha.appendChild(btnRm(() => {
        s.cartas = s.cartas.filter(c => c.par !== i + 1).map(c => ({ ...c, par: c.par > i + 1 ? c.par - 1 : c.par }));
        render();
      }));
      box.appendChild(linha);
    });
    if (pares.length < 8) box.appendChild(btnAdd('par de cartas', () => {
      const p = pares.length + 1;
      s.cartas.push({ par: p, texto: '' }, { par: p, texto: '' });
      render();
    }));
    return box;
  },

  cenario(s) {
    const box = h('<div></div>');
    s.situacoes.forEach((sc, si) => {
      const bl = h('<div class="b-bloco"></div>');
      const cab = h(`<div class="lms-row" style="justify-content:space-between"><label class="lms-label">Situação ${si + 1}</label></div>`);
      if (s.situacoes.length > 1) cab.appendChild(btnRm(() => { s.situacoes.splice(si, 1); render(); }, 'Remover situação'));
      bl.appendChild(cab);
      const linha1 = h('<div class="b-linha"></div>');
      const ico = campo(sc.icone || '', '🎬', v => sc.icone = v);
      ico.style.cssText = 'max-width:70px; text-align:center; font-size:20px';
      linha1.appendChild(ico);
      linha1.appendChild(campo(sc.contexto, 'Contexto: "Você acaba de chegar à unidade…"', v => sc.contexto = v));
      bl.appendChild(linha1);
      bl.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">Opções (marque a correta)</label>'));
      sc.opcoes.forEach((o, oi) => {
        const linha = h('<div class="b-linha"></div>');
        const chk = h(`<label class="b-corr"><input type="radio" name="cen-${si}" ${o.correta ? 'checked' : ''}> correta</label>`);
        chk.querySelector('input').addEventListener('change', () => sc.opcoes.forEach((x, xi) => x.correta = xi === oi));
        linha.appendChild(campo(o.texto, `Opção ${oi + 1}…`, v => o.texto = v));
        linha.appendChild(chk);
        if (sc.opcoes.length > 2) linha.appendChild(btnRm(() => { sc.opcoes.splice(oi, 1); render(); }));
        bl.appendChild(linha);
      });
      bl.appendChild(btnAdd('opção', () => { sc.opcoes.push({ texto: '', correta: false }); render(); }));
      bl.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">Feedback (regra do procedimento mostrada após a escolha)</label>'));
      bl.appendChild(campo(sc.feedback || '', 'Ex.: Obrigação: participar do briefing ao chegar à unidade.', v => sc.feedback = v));
      box.appendChild(bl);
    });
    box.appendChild(btnAdd('situação', () => { s.situacoes.push({ icone: '🎬', contexto: '', opcoes: [{ texto: '', correta: false }, { texto: '', correta: true }], feedback: '' }); render(); }));
    return box;
  },

  arrastar(s) {
    const box = h('<div></div>');
    const bl1 = h('<div class="b-bloco"><label class="lms-label">Instrução</label></div>');
    bl1.appendChild(campo(s.instrucao || '', 'Ex.: Classifique cada item do embarque…', v => s.instrucao = v));
    bl1.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Zonas (2 a 4)</label>'));
    s.zonas.forEach((z, zi) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(z.titulo, `Título da zona ${zi + 1}…`, v => z.titulo = v));
      const sel = h(`<select class="lms-select" style="max-width:150px">${Object.entries(CORES_ZONA).map(([k, lbl]) =>
        `<option value="${k}" ${z.cor === k ? 'selected' : ''}>${lbl}</option>`).join('')}</select>`);
      sel.addEventListener('change', ev => z.cor = ev.target.value);
      linha.appendChild(sel);
      if (s.zonas.length > 2) linha.appendChild(btnRm(() => {
        s.zonas.splice(zi, 1);
        s.itens.forEach(it => { if (it.zona >= s.zonas.length) it.zona = 0; });
        render();
      }));
      bl1.appendChild(linha);
    });
    if (s.zonas.length < 4) bl1.appendChild(btnAdd('zona', () => { s.zonas.push({ titulo: '', cor: 'sky' }); render(); }));
    box.appendChild(bl1);

    const bl2 = h('<div class="b-bloco"><label class="lms-label">Itens</label></div>');
    s.itens.forEach((it, i) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(it.texto, 'Item…', v => it.texto = v));
      const sel = h(`<select class="lms-select" style="max-width:220px">${s.zonas.map((z, zi) =>
        `<option value="${zi}" ${it.zona === zi ? 'selected' : ''}>${esc(z.titulo || 'Zona ' + (zi + 1))}</option>`).join('')}</select>`);
      sel.addEventListener('change', ev => it.zona = +ev.target.value);
      linha.appendChild(sel);
      if (s.itens.length > 1) linha.appendChild(btnRm(() => { s.itens.splice(i, 1); render(); }));
      bl2.appendChild(linha);
    });
    bl2.appendChild(btnAdd('item', () => { s.itens.push({ texto: '', zona: 0 }); render(); }));
    box.appendChild(bl2);
    return box;
  },

  historia(s) {
    const box = h('<div></div>');
    s.cenas.forEach((c, i) => {
      const bl = h('<div class="b-bloco"></div>');
      const cab = h(`<div class="lms-row" style="justify-content:space-between"><label class="lms-label">Cena ${i + 1}</label></div>`);
      if (s.cenas.length > 1) cab.appendChild(btnRm(() => { s.cenas.splice(i, 1); render(); }));
      bl.appendChild(cab);
      const linha = h('<div class="b-linha"></div>');
      const ico = campo(c.icone || '', '📖', v => c.icone = v);
      ico.style.cssText = 'max-width:70px; text-align:center; font-size:20px';
      linha.appendChild(ico);
      linha.appendChild(campo(c.titulo || '', 'Título da cena (opcional)…', v => c.titulo = v));
      bl.appendChild(linha);
      bl.appendChild(campo(c.texto, 'Texto da cena…', v => c.texto = v, 'textarea'));
      bl.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">🖼️ Imagem da cena (opcional — quadrinho/HQ)</label>'));
      if (c.imagem) bl.appendChild(h(`<img src="${esc(c.imagem)}" alt="Cena" style="max-width:280px; border-radius:10px; display:block; margin:6px 0">`));
      bl.appendChild(campoMidia(c.imagem || '', '.webp,.png,.jpg,.jpeg,.gif,image/*', url => { c.imagem = url; render(); }));
      box.appendChild(bl);
    });
    box.appendChild(btnAdd('cena', () => { s.cenas.push({ icone: '📖', titulo: '', texto: '', imagem: '' }); render(); }));
    return box;
  },

  video(s) {
    const box = h('<div class="b-bloco"></div>');
    box.appendChild(h('<label class="lms-label">URL do vídeo (YouTube, Vimeo ou arquivo .mp4)</label>'));
    box.appendChild(campo(s.url || '', 'https://www.youtube.com/watch?v=…', v => s.url = v));
    box.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">…ou envie o arquivo (MP4 — até 120 MB; fica em data/midia, só para quem está logado)</label>'));
    box.appendChild(campoMidia(s.url && s.url.includes('/midia?f=') ? s.url : '', '.mp4,video/mp4', url => { s.url = url; render(); }));
    box.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Descrição (opcional — aparece acima do vídeo)</label>'));
    box.appendChild(campo(s.descricao || '', 'Contexto do vídeo…', v => s.descricao = v));
    return box;
  },

  audio(s) {
    const box = h('<div></div>');
    const bl1 = h('<div class="b-bloco"><label class="lms-label">🔊 Arquivo de áudio (MP3, M4A ou OGG — até 12 MB)</label></div>');
    bl1.appendChild(campoMidia(s.url || '', '.mp3,.m4a,.ogg,audio/*', url => { s.url = url; render(); }));
    bl1.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">…ou cole uma URL direta</label>'));
    bl1.appendChild(campo(s.url || '', 'https://…/episodio.mp3', v => s.url = v));
    box.appendChild(bl1);

    const bl2 = h('<div class="b-bloco"></div>');
    bl2.appendChild(h('<label class="lms-label">Título</label>'));
    bl2.appendChild(campo(s.titulo || '', 'Ex.: Depoimento — por que reportar importa', v => s.titulo = v));
    bl2.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">Subtítulo (opcional)</label>'));
    bl2.appendChild(campo(s.subtitulo || '', 'Ex.: 4 minutos · vozes da equipe', v => s.subtitulo = v));
    box.appendChild(bl2);

    const bl3 = h('<div class="b-bloco"><label class="lms-label">📄 Transcrição (opcional — quem fala + texto)</label></div>');
    (s.transcricao = s.transcricao || []).forEach((l, i) => {
      const linha = h('<div class="b-linha" style="align-items:flex-start"></div>');
      const quem = campo(l.quem || '', 'QUEM/PAPEL', v => l.quem = v);
      quem.style.maxWidth = '200px';
      linha.appendChild(quem);
      linha.appendChild(campo(l.texto || '', 'Fala…', v => l.texto = v, 'textarea'));
      linha.appendChild(btnRm(() => { s.transcricao.splice(i, 1); render(); }));
      bl3.appendChild(linha);
    });
    bl3.appendChild(btnAdd('fala', () => { s.transcricao.push({ quem: '', texto: '' }); render(); }));
    box.appendChild(bl3);
    return box;
  },

  caca_palavras(s) {
    const box = h('<div class="b-bloco"></div>');
    box.appendChild(h('<label class="lms-label">Instrução</label>'));
    box.appendChild(campo(s.instrucao || '', 'Ex.: Encontre os EPIs escondidos na grade.', v => s.instrucao = v));
    box.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Palavras (3 a 14 letras — a grade é gerada sozinha ao salvar; acentos são removidos)</label>'));
    s.palavras.forEach((p, i) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(p, `Palavra ${i + 1}…`, v => s.palavras[i] = v));
      if (s.palavras.length > 3) linha.appendChild(btnRm(() => { s.palavras.splice(i, 1); render(); }));
      box.appendChild(linha);
    });
    if (s.palavras.length < 12) box.appendChild(btnAdd('palavra', () => { s.palavras.push(''); render(); }));
    return box;
  },

  cruzadinha(s) {
    const box = h('<div class="b-bloco"></div>');
    box.appendChild(h('<label class="lms-label">Instrução</label>'));
    box.appendChild(campo(s.instrucao || '', 'Ex.: Preencha com os termos do procedimento.', v => s.instrucao = v));
    box.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Palavras e dicas (o cruzamento é gerado sozinho ao salvar — as palavras precisam ter letras em comum)</label>'));
    s.itens.forEach((it, i) => {
      const linha = h('<div class="b-linha"></div>');
      const w = campo(it.palavra, 'PALAVRA…', v => it.palavra = v);
      w.style.maxWidth = '190px';
      linha.appendChild(w);
      linha.appendChild(campo(it.dica, 'Dica que aparece para o aluno…', v => it.dica = v));
      if (s.itens.length > 2) linha.appendChild(btnRm(() => { s.itens.splice(i, 1); render(); }));
      box.appendChild(linha);
    });
    if (s.itens.length < 12) box.appendChild(btnAdd('palavra', () => { s.itens.push({ palavra: '', dica: '' }); render(); }));
    return box;
  },

  sete_erros(s) {
    const box = h('<div></div>');
    const bl1 = h('<div class="b-bloco"><label class="lms-label">Instrução</label></div>');
    bl1.appendChild(campo(s.instrucao || '', 'Ex.: Encontre as 7 situações inseguras na cena.', v => s.instrucao = v));
    const linhaR = h('<div class="b-linha" style="margin-top:10px"><label class="lms-label" style="flex:none">🎯 Tolerância do clique (% da largura)</label></div>');
    const raio = h(`<input class="lms-input" type="number" min="2" max="20" value="${+s.raio || 6}" style="max-width:90px">`);
    raio.addEventListener('input', () => s.raio = +raio.value);
    linhaR.appendChild(raio);
    bl1.appendChild(linhaR);
    box.appendChild(bl1);

    const bl2 = h('<div class="b-bloco"><label class="lms-label">🖼️ Imagem do jogo</label></div>');
    bl2.appendChild(campoMidia(s.imagem || '', '.webp,.png,.jpg,.jpeg,image/*', url => { s.imagem = url; render(); }));
    if (s.imagem) {
      bl2.appendChild(h('<p class="lms-small lms-muted" style="margin-top:10px">👇 <strong>Clique na imagem</strong> para marcar cada ponto que o aluno deve encontrar (clique num marcador para removê-lo).</p>'));
      const prev = h(`<div style="position:relative; cursor:crosshair; margin-top:8px; border-radius:10px; overflow:hidden; border:var(--lms-border-game)">
        <img src="${esc(s.imagem)}" style="width:100%; display:block" draggable="false"></div>`);
      prev.addEventListener('click', (ev) => {
        const r = prev.getBoundingClientRect();
        const x = +((ev.clientX - r.left) / r.width * 100).toFixed(1);
        const y = +((ev.clientY - r.top) / r.height * 100).toFixed(1);
        s.pontos.push({ x, y, rotulo: '' });
        render();
      });
      s.pontos.forEach((p, i) => {
        const m = h(`<button type="button" title="Remover este ponto" style="position:absolute; left:${p.x}%; top:${p.y}%; transform:translate(-50%,-50%);
          width:30px; height:30px; border-radius:50%; border:3px solid #fff; background:var(--lms-error); color:#fff;
          font-family:var(--lms-font-ui); font-weight:800; cursor:pointer; box-shadow:0 2px 8px rgba(0,0,0,.4)">${i + 1}</button>`);
        m.addEventListener('click', (ev) => { ev.stopPropagation(); s.pontos.splice(i, 1); render(); });
        prev.appendChild(m);
      });
      bl2.appendChild(prev);
      s.pontos.forEach((p, i) => {
        const linha = h(`<div class="b-linha"><span class="lms-pill" style="flex:none">${i + 1}</span></div>`);
        linha.appendChild(campo(p.rotulo || '', 'Rótulo mostrado ao encontrar (opcional)…', v => p.rotulo = v));
        bl2.appendChild(linha);
      });
    }
    box.appendChild(bl2);
    return box;
  },

  investigacao(s) {
    const box = h('<div></div>');
    const destinos = (atual) => s.cartas.map((_, i) => i).filter(i => i !== atual);
    const bl0 = h('<div class="b-bloco"></div>');
    bl0.appendChild(h('<label class="lms-label">Modo de jogo</label>'));
    const selModo = h(`<select class="lms-select"><option value="ramificada" ${s.modo !== 'dominio' ? 'selected' : ''}>Ramificada — cada escolha leva a um caminho (errar também avança)</option><option value="dominio" ${s.modo === 'dominio' ? 'selected' : ''}>Domínio — errou? feedback e escolhe de novo; só avança acertando (conta acerto só de primeira)</option></select>`);
    selModo.addEventListener('change', ev => s.modo = ev.target.value);
    bl0.appendChild(selModo);
    const lr = h('<div class="b-linha" style="margin-top:8px"></div>');
    lr.appendChild(campo(s.rotulo_seguir || '', 'Botão de seguir (ex.: Continuar atendimento)', v => s.rotulo_seguir = v));
    lr.appendChild(campo(s.rotulo_fim || '', 'Botão final (ex.: Concluir simulação)', v => s.rotulo_fim = v));
    bl0.appendChild(lr);
    box.appendChild(bl0);
    s.cartas.forEach((c, ci) => {
      const bl = h('<div class="b-bloco"></div>');
      const cab = h(`<div class="lms-row" style="justify-content:space-between"><label class="lms-label">🃏 Carta ${ci + 1}</label></div>`);
      if (s.cartas.length > 2) cab.appendChild(btnRm(() => {
        s.cartas.splice(ci, 1);
        s.cartas.forEach(c2 => c2.opcoes.forEach(o => {
          if (o.vai === ci) o.vai = null;
          else if (o.vai !== null && o.vai > ci) o.vai--;
        }));
        render();
      }, 'Remover carta'));
      bl.appendChild(cab);
      const linha1 = h('<div class="b-linha"></div>');
      const ico = campo(c.icone || '', '🕵️', v => c.icone = v);
      ico.style.cssText = 'max-width:70px; text-align:center; font-size:20px';
      linha1.appendChild(ico);
      linha1.appendChild(campo(c.pista, 'Texto da pista/cena…', v => c.pista = v, 'textarea'));
      bl.appendChild(linha1);
      const lt = h('<div class="b-linha" style="margin-top:6px"></div>');
      lt.appendChild(campo(c.titulo || '', 'Título da carta (opcional — ex.: ETAPA: ACOLHA)', v => c.titulo = v));
      lt.appendChild(campo(c.pergunta || '', 'Pergunta (opcional — ex.: O que você faz agora?)', v => c.pergunta = v));
      bl.appendChild(lt);
      bl.appendChild(h('<label class="lms-label" style="margin-top:8px; display:block">🔊 Áudio da cena (opcional — MP3/M4A/OGG, fala de um personagem)</label>'));
      const la = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
      const quem = campo(c.audio_quem || '', 'QUEM FALA', v => c.audio_quem = v);
      quem.style.maxWidth = '160px';
      la.appendChild(quem);
      la.appendChild(campoMidia(c.audio || '', '.mp3,.m4a,.ogg,audio/*', url => { c.audio = url; render(); }));
      bl.appendChild(la);
      bl.appendChild(campo(c.audio || '', '…ou URL do áudio', v => c.audio = v));
      bl.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">Opções — para onde cada escolha leva</label>'));
      c.opcoes.forEach((o, oi) => {
        const linha = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
        linha.appendChild(campo(o.texto, `Opção ${oi + 1}…`, v => o.texto = v));
        const chk = h(`<label class="b-corr"><input type="checkbox" ${o.correta ? 'checked' : ''}> correta</label>`);
        chk.querySelector('input').addEventListener('change', ev => o.correta = ev.target.checked);
        linha.appendChild(chk);
        const sel = h(`<select class="lms-select" style="max-width:170px">
          <option value="">🏁 Encerra</option>
          ${destinos(ci).map(d => `<option value="${d}" ${o.vai === d ? 'selected' : ''}>→ Carta ${d + 1}</option>`).join('')}
        </select>`);
        sel.addEventListener('change', ev => o.vai = ev.target.value === '' ? null : +ev.target.value);
        linha.appendChild(sel);
        if (c.opcoes.length > 2) linha.appendChild(btnRm(() => { c.opcoes.splice(oi, 1); render(); }));
        const fb = h('<div class="b-linha" style="margin-top:4px"></div>');
        fb.appendChild(campo(o.feedback || '', 'Feedback ao escolher (opcional)…', v => o.feedback = v));
        bl.appendChild(linha);
        bl.appendChild(fb);
      });
      bl.appendChild(btnAdd('opção', () => { c.opcoes.push({ texto: '', correta: false, vai: null, feedback: '' }); render(); }));
      box.appendChild(bl);
    });
    box.appendChild(btnAdd('carta', () => { s.cartas.push({ icone: '🔎', pista: '', opcoes: [{ texto: '', correta: true, vai: null, feedback: '' }, { texto: '', correta: false, vai: null, feedback: '' }] }); render(); }));
    return box;
  },

  tabuleiro(s) {
    const box = h('<div></div>');
    const bl0 = h('<div class="b-bloco"><label class="lms-label">Instrução (opcional)</label></div>');
    bl0.appendChild(campo(s.instrucao || '', 'Ex.: Jogue o dado e avance respondendo sobre o procedimento.', v => s.instrucao = v));
    box.appendChild(bl0);
    s.casas.forEach((c, ci) => {
      const bl = h('<div class="b-bloco"></div>');
      const cab = h(`<div class="lms-row" style="justify-content:space-between"><label class="lms-label">🎲 Casa ${ci + 1}</label></div>`);
      if (s.casas.length > 4) cab.appendChild(btnRm(() => { s.casas.splice(ci, 1); render(); }, 'Remover casa'));
      bl.appendChild(cab);
      bl.appendChild(campo(c.pergunta, 'Pergunta desta casa…', v => c.pergunta = v, 'textarea'));
      bl.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">Alternativas (marque a correta)</label>'));
      c.alternativas.forEach((a, ai) => {
        const linha = h('<div class="b-linha"></div>');
        const chk = h(`<label class="b-corr"><input type="radio" name="tab-${ci}" ${c.corretas.includes(ai) ? 'checked' : ''}> correta</label>`);
        chk.querySelector('input').addEventListener('change', () => c.corretas = [ai]);
        linha.appendChild(campo(a, `Alternativa ${ai + 1}…`, v => c.alternativas[ai] = v));
        linha.appendChild(chk);
        if (c.alternativas.length > 2) linha.appendChild(btnRm(() => {
          c.alternativas.splice(ai, 1);
          c.corretas = c.corretas.filter(x => x !== ai).map(x => x > ai ? x - 1 : x);
          if (!c.corretas.length) c.corretas = [0];
          render();
        }));
        bl.appendChild(linha);
      });
      bl.appendChild(btnAdd('alternativa', () => { c.alternativas.push(''); render(); }));
      box.appendChild(bl);
    });
    box.appendChild(btnAdd('casa', () => { s.casas.push({ pergunta: '', alternativas: ['', ''], corretas: [0] }); render(); }));
    return box;
  },

  caca(s) {
    const box = h('<div class="b-bloco"></div>');
    box.appendChild(h('<label class="lms-label">Instrução</label>'));
    box.appendChild(campo(s.instrucao || '', 'Ex.: Varra o grid e revele as ações corretas…', v => s.instrucao = v, 'textarea'));
    box.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Células (cada uma revela um card)</label>'));
    s.celulas.forEach((c, i) => {
      const linha = h('<div class="b-linha"></div>');
      linha.appendChild(campo(c.titulo, 'Título (o que foi encontrado)…', v => c.titulo = v));
      linha.appendChild(campo(c.descricao || '', 'Descrição/detalhe…', v => c.descricao = v));
      if (s.celulas.length > 3) linha.appendChild(btnRm(() => { s.celulas.splice(i, 1); render(); }));
      box.appendChild(linha);
    });
    box.appendChild(btnAdd('célula', () => { s.celulas.push({ titulo: '', descricao: '' }); render(); }));
    return box;
  },

  podcast(s) {
    const box = h('<div class="b-bloco"></div>');
    box.appendChild(h('<label class="lms-label">Título do episódio</label>'));
    box.appendChild(campo(s.titulo || '', 'Ex.: Depoimento — "Por que reportar importa"', v => s.titulo = v));
    box.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">Subtítulo</label>'));
    box.appendChild(campo(s.subtitulo || '', 'Ex.: Dramatização do conteúdo do procedimento', v => s.subtitulo = v));
    box.appendChild(h('<label class="lms-label" style="margin-top:12px; display:block">Transcrição (quem fala + texto)</label>'));
    s.linhas.forEach((l, i) => {
      const linha = h('<div class="b-linha" style="align-items:flex-start"></div>');
      const quem = campo(l.quem || '', 'QUEM/PAPEL', v => l.quem = v);
      quem.style.maxWidth = '220px';
      linha.appendChild(quem);
      linha.appendChild(campo(l.texto, 'Fala…', v => l.texto = v, 'textarea'));
      if (s.linhas.length > 1) linha.appendChild(btnRm(() => { s.linhas.splice(i, 1); render(); }));
      box.appendChild(linha);
    });
    box.appendChild(btnAdd('fala', () => { s.linhas.push({ quem: '', texto: '' }); render(); }));
    return box;
  },

  sequencia(s) {
    const box = h('<div></div>');
    const bl0 = h('<div class="b-bloco"></div>');
    bl0.appendChild(h('<label class="lms-label">Instrução</label>'));
    bl0.appendChild(campo(s.instrucao || '', 'Ex.: Toque nos passos na ordem correta.', v => s.instrucao = v));
    const chk = h(`<label class="b-corr" style="margin-top:10px; display:inline-flex"><input type="checkbox" ${s.so_imagem ? 'checked' : ''}> nas cartas embaralhadas, mostrar só a imagem (o nome aparece ao acertar)</label>`);
    chk.querySelector('input').addEventListener('change', ev => s.so_imagem = ev.target.checked);
    bl0.appendChild(chk);
    box.appendChild(bl0);
    s.passos = s.passos.map(p => typeof p === 'string' ? { texto: p, imagem: '', descricao: '' } : p);
    s.passos.forEach((p, i) => {
      const bl = h('<div class="b-bloco"></div>');
      const cab = h(`<div class="lms-row" style="justify-content:space-between"><label class="lms-label">Passo ${i + 1} — já na ordem correta (o player embaralha)</label></div>`);
      if (s.passos.length > 3) cab.appendChild(btnRm(() => { s.passos.splice(i, 1); render(); }, 'Remover passo'));
      bl.appendChild(cab);
      bl.appendChild(campo(p.texto || '', `Nome do passo ${i + 1}…`, v => p.texto = v));
      bl.appendChild(h('<label class="lms-label" style="margin-top:8px; display:block">Imagem (opcional)</label>'));
      if (p.imagem) bl.appendChild(h(`<img src="${esc(p.imagem)}" alt="" style="height:64px; border-radius:8px; display:block; margin:4px 0; background:var(--lms-surface)">`));
      bl.appendChild(campoMidia(p.imagem || '', '.webp,.png,.jpg,.jpeg,.gif,image/*', url => { p.imagem = url; render(); }));
      bl.appendChild(campo(p.imagem || '', '…ou URL da imagem', v => p.imagem = v));
      bl.appendChild(h('<label class="lms-label" style="margin-top:8px; display:block">O que este passo faz (opcional — revelado ao acertar)</label>'));
      bl.appendChild(campo(p.descricao || '', 'Ex.: Uniformiza a textura da pele e fixa a maquiagem.', v => p.descricao = v));
      box.appendChild(bl);
    });
    box.appendChild(btnAdd('passo', () => { s.passos.push({ texto: '', imagem: '', descricao: '' }); render(); }));
    return box;
  },

  atendimento(s) {
    const box = h('<div></div>');
    const lista = v => v == null || v === '' ? [] : (Array.isArray(v) ? v : [v]);
    const ETAPAS = () => (s.etapas && s.etapas.length) ? s.etapas : ['Acolha', 'Personalize', 'Potencialize', 'Encante'];
    s.cliente = s.cliente || {}; s.briefing = s.briefing || {}; s.final = s.final || {};
    s.bandeiras = s.bandeiras || []; s.checklist = s.checklist || []; s.turnos = s.turnos || [];

    // a cliente e as regras da partida
    const bl0 = h('<div class="b-bloco"><label class="lms-label">🙂 A cliente</label></div>');
    const l0 = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
    const av = campo(s.cliente.avatar || '', '👩', v => s.cliente.avatar = v); av.style.cssText = 'max-width:70px; text-align:center; font-size:20px';
    l0.appendChild(av);
    l0.appendChild(campo(s.cliente.nome || '', 'Nome (ex.: Amanda)', v => s.cliente.nome = v));
    l0.appendChild(campo(s.cliente.persona || '', 'Persona (ex.: Uso próprio · item definido)', v => s.cliente.persona = v));
    bl0.appendChild(l0);
    bl0.appendChild(campo(s.cliente.descricao || '', 'Quem é e o que veio fazer (aparece na ficha de abertura)…', v => s.cliente.descricao = v, 'textarea'));
    const l1 = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
    const selModo = h(`<select class="lms-select"><option value="dominio" ${s.modo !== 'livre' ? 'selected' : ''}>Domínio — errou? a cliente reage, o coach explica e escolhe de novo (conta só de primeira)</option><option value="livre" ${s.modo === 'livre' ? 'selected' : ''}>Livre — errar segue o caminho da opção</option></select>`);
    selModo.addEventListener('change', ev => s.modo = ev.target.value);
    l1.appendChild(selModo);
    const humor = campo(String(s.humor_inicial ?? 50), 'Satisfação inicial (0–100)', v => s.humor_inicial = +v || 0); humor.style.maxWidth = '200px';
    l1.appendChild(humor);
    bl0.appendChild(l1);
    bl0.appendChild(h('<label class="lms-label" style="margin-top:8px; display:block">Etapas do atendimento (separadas por vírgula)</label>'));
    bl0.appendChild(campo(ETAPAS().join(', '), 'Acolha, Personalize, Potencialize, Encante', v => { s.etapas = v.split(',').map(x => x.trim()).filter(Boolean); }));
    bl0.appendChild(campo(s.nota_precos || '', 'Nota sobre os preços da sacola (opcional, aparece no relatório)', v => s.nota_precos = v));
    box.appendChild(bl0);

    // abertura
    const blB = h('<div class="b-bloco"><label class="lms-label">📋 Abertura (a ficha antes do chat)</label></div>');
    blB.appendChild(campo(s.briefing.titulo || '', 'Título (ex.: Sua vez de atender)', v => s.briefing.titulo = v));
    blB.appendChild(campo(s.briefing.texto || '', 'Contexto da cena e a missão…', v => s.briefing.texto = v, 'textarea'));
    blB.appendChild(campo(s.briefing.botao || '', 'Texto do botão (ex.: Receber a Amanda)', v => s.briefing.botao = v));
    box.appendChild(blB);

    // bandeiras
    const blF = h('<div class="b-bloco"><label class="lms-label">🚩 Bandeiras — o que precisa aparecer em algum momento</label></div>');
    s.bandeiras.forEach((b, i) => {
      const l = h('<div class="b-linha"></div>');
      const ic = campo(b.icone || '', '🚩', v => b.icone = v); ic.style.cssText = 'max-width:64px; text-align:center; font-size:18px';
      l.appendChild(ic);
      const id = campo(b.id || '', 'id (ex.: identificacao)', v => b.id = v); id.style.maxWidth = '190px';
      l.appendChild(id);
      l.appendChild(campo(b.nome || '', 'Nome (ex.: Identificação)', v => b.nome = v));
      l.appendChild(btnRm(() => { s.bandeiras.splice(i, 1); render(); }));
      blF.appendChild(l);
    });
    blF.appendChild(btnAdd('bandeira', () => { s.bandeiras.push({ id: '', icone: '🚩', nome: '' }); render(); }));
    box.appendChild(blF);

    // checklist do cliente oculto
    const blC = h('<div class="b-bloco"><label class="lms-label">🕵️ Checklist do Cliente Oculto (aparece no relatório final)</label></div>');
    s.checklist.forEach((c, i) => {
      const l = h('<div class="b-linha"></div>');
      const id = campo(c.id || '', 'id (ex.: cpf)', v => c.id = v); id.style.maxWidth = '160px';
      l.appendChild(id);
      l.appendChild(campo(c.texto || '', 'Pergunta do checklist…', v => c.texto = v));
      l.appendChild(btnRm(() => { s.checklist.splice(i, 1); render(); }));
      blC.appendChild(l);
    });
    blC.appendChild(btnAdd('item do checklist', () => { s.checklist.push({ id: '', texto: '' }); render(); }));
    box.appendChild(blC);

    // turnos da conversa
    const bandIds = () => s.bandeiras.map(b => b.id).filter(Boolean);
    s.turnos.forEach((t, ti) => {
      t.opcoes = t.opcoes || [];
      const bl = h('<div class="b-bloco"></div>');
      const cab = h(`<div class="lms-row" style="justify-content:space-between"><label class="lms-label">💬 Turno ${ti + 1}</label></div>`);
      if (s.turnos.length > 1) cab.appendChild(btnRm(() => {
        s.turnos.splice(ti, 1);
        s.turnos.forEach(t2 => (t2.opcoes || []).forEach(o => { if (o.vai === ti) o.vai = null; else if (o.vai != null && o.vai > ti) o.vai--; }));
        render();
      }, 'Remover turno'));
      bl.appendChild(cab);
      const le = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
      const selEt = h(`<select class="lms-select" style="max-width:230px">${ETAPAS().map((n, i) => `<option value="${i}" ${(+t.etapa || 0) === i ? 'selected' : ''}>${i + 1}. ${esc(n)}</option>`).join('')}</select>`);
      selEt.addEventListener('change', ev => t.etapa = +ev.target.value);
      le.appendChild(selEt);
      le.appendChild(campo(t.pergunta || '', 'Pergunta ao aluno (ex.: Como você recebe a Amanda?)', v => t.pergunta = v));
      bl.appendChild(le);
      bl.appendChild(campo(t.cena || '', 'Didascália — o que acontece na cena (opcional)…', v => t.cena = v));
      bl.appendChild(h('<label class="lms-label" style="margin-top:8px; display:block">Falas da cliente antes da decisão (uma por linha, opcional)</label>'));
      bl.appendChild(campo(lista(t.falas).join('\n'), 'Ex.: Oi! Vim buscar uma base…', v => t.falas = v.split('\n').map(x => x.trim()).filter(Boolean), 'textarea'));
      bl.appendChild(h('<label class="lms-label" style="margin-top:10px; display:block">Opções de resposta do aluno — <span class="lms-muted">{nome} vira o nome de quem joga</span></label>'));
      t.opcoes.forEach((o, oi) => {
        const op = h('<div style="border-top:1px dashed var(--lms-border); padding-top:8px; margin-top:8px"></div>');
        const l = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
        l.appendChild(campo(o.texto || '', `Fala ${oi + 1} do aluno…`, v => o.texto = v, 'textarea'));
        const chk = h(`<label class="b-corr"><input type="checkbox" ${o.correta ? 'checked' : ''}> correta</label>`);
        chk.querySelector('input').addEventListener('change', ev => o.correta = ev.target.checked);
        l.appendChild(chk);
        if (t.opcoes.length > 2) l.appendChild(btnRm(() => { t.opcoes.splice(oi, 1); render(); }));
        op.appendChild(l);
        op.appendChild(campo(lista(o.reacao).join('\n'), 'Reação da cliente (uma fala por linha)…', v => o.reacao = v.split('\n').map(x => x.trim()).filter(Boolean), 'textarea'));
        op.appendChild(campo(o.feedback || '', 'Coach — por que é (ou não é) por aí…', v => o.feedback = v, 'textarea'));
        const l2 = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
        if (bandIds().length) {
          const selB = h(`<select class="lms-select" multiple size="2" style="max-width:210px" title="Bandeiras que esta fala captura (Ctrl pra mais de uma)">${bandIds().map(id => `<option value="${esc(id)}" ${lista(o.bandeira).includes(id) ? 'selected' : ''}>🚩 ${esc(id)}</option>`).join('')}</select>`);
          selB.addEventListener('change', () => o.bandeira = [...selB.selectedOptions].map(x => x.value));
          l2.appendChild(selB);
        }
        l2.appendChild(campo(lista(o.oculto).join(', '), 'ids do checklist que esta fala cumpre (ex.: cpf, cpf_motivo)', v => o.oculto = v.split(',').map(x => x.trim()).filter(Boolean)));
        const hu = campo(o.humor == null ? '' : String(o.humor), '± satisfação', v => { if (v === '') delete o.humor; else o.humor = +v; }); hu.style.maxWidth = '120px';
        l2.appendChild(hu);
        const selV = h(`<select class="lms-select" style="max-width:180px"><option value="">→ próximo turno</option><option value="fim" ${o.vai === null ? 'selected' : ''}>🏁 Encerra</option>${s.turnos.map((_, d) => d === ti ? '' : `<option value="${d}" ${o.vai === d ? 'selected' : ''}>→ Turno ${d + 1}</option>`).join('')}</select>`);
        selV.addEventListener('change', ev => { const v = ev.target.value; if (v === '') delete o.vai; else o.vai = v === 'fim' ? null : +v; });
        l2.appendChild(selV);
        op.appendChild(l2);
        op.appendChild(campo((o.sacola || []).map(x => `${x.item} | ${x.preco ?? 0}${x.brinde ? ' | brinde' : ''}`).join('\n'),
          'Itens que entram na sacola — um por linha: Item | preço | brinde (opcional)', v => o.sacola = v.split('\n').map(x => x.trim()).filter(Boolean).map(x => {
            const p = x.split('|').map(y => y.trim());
            return { item: p[0], preco: +(p[1] || '0').replace(',', '.') || 0, brinde: /brinde/i.test(p[2] || '') };
          }), 'textarea'));
        bl.appendChild(op);
      });
      bl.appendChild(btnAdd('opção', () => { t.opcoes.push({ texto: '', correta: false, reacao: [], feedback: '' }); render(); }));
      box.appendChild(bl);
    });
    box.appendChild(btnAdd('turno', () => { s.turnos.push({ etapa: 0, cena: '', falas: [], pergunta: '', opcoes: [{ texto: '', correta: true, reacao: [], feedback: '' }, { texto: '', correta: false, reacao: [], feedback: '' }] }); render(); }));

    // relatório final
    const blZ = h('<div class="b-bloco"><label class="lms-label">🏁 Relatório final</label></div>');
    blZ.appendChild(campo(s.final.titulo || '', 'Título (ex.: Atendimento concluído)', v => s.final.titulo = v));
    blZ.appendChild(campo(s.final.texto || '', 'Fechamento — o que o aluno leva desse atendimento…', v => s.final.texto = v, 'textarea'));
    const lz = h('<div class="b-linha" style="flex-wrap:wrap"></div>');
    const chkSV = h(`<label class="b-corr"><input type="checkbox" ${s.final.sem_venda ? 'checked' : ''}> termina sem venda</label>`);
    chkSV.querySelector('input').addEventListener('change', ev => s.final.sem_venda = ev.target.checked);
    lz.appendChild(chkSV);
    lz.appendChild(campo(s.final.motivo_nao_compra || '', 'Motivo da não compra registrado (se terminar sem venda)', v => s.final.motivo_nao_compra = v));
    blZ.appendChild(lz);
    box.appendChild(blZ);
    return box;
  },
};

function render() {
  document.getElementById('e-desc').textContent = DESCRICOES[tipoAtual] || '';
  builder.innerHTML = '';
  builder.appendChild(RENDERS[tipoAtual](STATE));
}

function trocaTipo(tipo, estadoInicial = null) {
  tipoAtual = tipo;
  // tipos gerados (caça-palavras/cruzadinha) voltam à forma editável
  STATE = estadoInicial ? (NORMALIZA[tipo] ? NORMALIZA[tipo](estadoInicial) : estadoInicial) : VAZIOS[tipo]();
  render();
}

selTipo.addEventListener('change', () => trocaTipo(selTipo.value));
trocaTipo(TIPO_INICIAL, EDITANDO);

/* modo avançado: edita o JSON cru (para colar configs gerados fora) */
document.getElementById('toggle-json').addEventListener('click', () => {
  modoJson = !modoJson;
  if (modoJson) {
    txtJson.value = JSON.stringify(STATE, null, 2);
    txtJson.classList.remove('lms-hide');
    builder.classList.add('lms-hide');
  } else {
    try { STATE = JSON.parse(txtJson.value); } catch (e) { alert('JSON inválido — corrija antes de voltar ao modo visual.'); modoJson = true; return; }
    txtJson.classList.add('lms-hide');
    builder.classList.remove('lms-hide');
    render();
  }
});

/* submit: serializa o STATE (ou o JSON cru) no campo hidden */
document.getElementById('f-etapa').addEventListener('submit', (ev) => {
  if (modoJson) {
    try { STATE = JSON.parse(txtJson.value); } catch (e) { ev.preventDefault(); alert('JSON inválido.'); return; }
  }
  // ids dos pontos de confirmação sempre coerentes
  if (tipoAtual === 'leitura') STATE.pontos_confirmacao.forEach((p, i) => p.id = 'p' + (i + 1));
  // tipos gerados: transforma a forma editável na grade final
  if (GERA[tipoAtual]) {
    try {
      const editavel = NORMALIZA[tipoAtual] ? NORMALIZA[tipoAtual](STATE) : STATE;
      STATE = GERA[tipoAtual](editavel);
    } catch (e) { ev.preventDefault(); alert(e.message); return; }
  }
  inpConfig.value = JSON.stringify(STATE);
});
</script>
</body></html>
