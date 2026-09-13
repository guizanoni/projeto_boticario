<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Treinamentos de uma trilha (as "pastas").
// O treinamento só agrupa e dá nome — o conteúdo de verdade são
// as fases, montadas direto no mapa (admin/mapa.php?t=ID).
// Criar, renomear, reordenar, arquivar e excluir aqui.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['admin']);
if ($u['papel'] !== 'admin') { header('Location: ' . lms_home($u)); exit; }

$trId = (int) ($_GET['tr'] ?? $_POST['trilha_id'] ?? 0);
$trilha = db_one('SELECT * FROM trilha WHERE id = ? AND cliente_id = ?', [$trId, $u['cliente_id']]);
if (!$trilha) { header('Location: /boticario/admin/trilhas'); exit; }

// Exclusão definitiva de um treinamento: fases, etapas, progresso,
// conversas — e solta os alunos que o tinham como ativo.
function trein_excluir(int $tid): void {
    db_run('DELETE FROM progresso_usuario WHERE etapa_id IN
            (SELECT e.id FROM etapa e JOIN fase f ON f.id = e.fase_id WHERE f.treinamento_id = ?)', [$tid]);
    db_run('DELETE FROM comentario WHERE fase_id IN (SELECT id FROM fase WHERE treinamento_id = ?)', [$tid]);
    db_run('DELETE FROM etapa WHERE fase_id IN (SELECT id FROM fase WHERE treinamento_id = ?)', [$tid]);
    db_run('DELETE FROM fase WHERE treinamento_id = ?', [$tid]);
    db_run('UPDATE usuario SET treinamento_id = NULL WHERE treinamento_id = ?', [$tid]);
    db_run('UPDATE usuario SET treinamento_id_2 = NULL WHERE treinamento_id_2 = ?', [$tid]);
    db_run('DELETE FROM treinamento WHERE id = ?', [$tid]);
}

// ── Ações (POST) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);
    $alvo = $id ? db_one('SELECT * FROM treinamento WHERE id = ? AND trilha_id = ?', [$id, $trId]) : null;

    if ($acao === 't_salvar') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $desc = trim((string) ($_POST['descricao'] ?? ''));
        if ($nome === '') {
            lms_flash_set(false, 'Dê um nome ao treinamento.');
        } elseif ($alvo) {
            db_run('UPDATE treinamento SET nome = ?, descricao = ? WHERE id = ?', [$nome, $desc, $id]);
            lms_flash_set(true, "Treinamento “{$nome}” atualizado.");
        } else {
            $ordem = (int) db_one('SELECT COALESCE(MAX(ordem),0)+1 AS o FROM treinamento WHERE trilha_id = ?', [$trId])['o'];
            db_run('INSERT INTO treinamento (trilha_id, nome, descricao, ordem) VALUES (?,?,?,?)', [$trId, $nome, $desc, $ordem]);
            $novo = (int) db_one('SELECT last_insert_rowid() AS i')['i'];
            lms_flash_set(true, "Treinamento “{$nome}” criado — agora coloque as fases no mapa.");
            header('Location: /boticario/admin/mapa?t=' . $novo); exit;
        }

    } elseif ($acao === 't_status' && $alvo) {
        $novo = $alvo['status'] === 'ativo' ? 'arquivado' : 'ativo';
        db_run('UPDATE treinamento SET status = ? WHERE id = ?', [$novo, $id]);
        lms_flash_set(true, $novo === 'arquivado'
            ? "“{$alvo['nome']}” arquivado — não pode ser atribuído a alunos (histórico preservado)."
            : "“{$alvo['nome']}” reativado.");

    } elseif ($acao === 't_excluir' && $alvo) {
        trein_excluir($id);
        lms_flash_set(true, "“{$alvo['nome']}” excluído definitivamente — fases, progresso e conversas foram removidos.");

    } elseif ($acao === 't_ssms_fase_unica' && $alvo) {
        // Ação manual e deliberada (RF de recuperação de incidente): aplica
        // a fase "Fundamentos do Indivíduo" (HARD CONTENT: 2 vídeos reais +
        // 2 leituras densas) neste treinamento. Só roda quando o admin
        // clica — nada disparado automaticamente por migração, de
        // propósito, depois de um incidente em que isso derrubou o site.
        require_once __DIR__ . '/../_lib/conteudo/diretrizes-ssms.php';
        try {
            conteudo_diretrizes_ssms_fase_unica_inserir(lms_db(), $id);
            lms_flash_set(true, 'Fase "Fundamentos do Indivíduo" aplicada com sucesso (21 etapas, com os 2 vídeos e as 2 leituras densas).');
        } catch (Throwable $e) {
            lms_flash_set(false, 'Falhou ao aplicar: ' . $e->getMessage());
        }

    } elseif ($acao === 't_mover' && $alvo) {
        $dir = $_POST['dir'] === 'up' ? -1 : 1;
        $viz = db_one('SELECT * FROM treinamento WHERE trilha_id = ? AND ordem ' . ($dir < 0 ? '<' : '>') . ' ? ORDER BY ordem ' . ($dir < 0 ? 'DESC' : 'ASC') . ' LIMIT 1',
                      [$trId, $alvo['ordem']]);
        if ($viz) {
            db_run('UPDATE treinamento SET ordem = ? WHERE id = ?', [$viz['ordem'], $alvo['id']]);
            db_run('UPDATE treinamento SET ordem = ? WHERE id = ?', [$alvo['ordem'], $viz['id']]);
        }
    }
    header('Location: /boticario/admin/treinamentos?tr=' . $trId); exit;
}

// ── Dados da tela ────────────────────────────────────────────
$editando = null;
if (!empty($_GET['editar'])) {
    $editando = db_one('SELECT * FROM treinamento WHERE id = ? AND trilha_id = ?', [(int) $_GET['editar'], $trId]);
}
$treinos = db_all('SELECT t.*,
        (SELECT COUNT(*) FROM fase f WHERE f.treinamento_id = t.id) AS n_fases,
        (SELECT COUNT(*) FROM etapa e JOIN fase f ON f.id = e.fase_id WHERE f.treinamento_id = t.id) AS n_etapas,
        (SELECT COUNT(*) FROM usuario us WHERE us.treinamento_id = t.id AND us.papel = \'aluno\') AS n_alunos
    FROM treinamento t WHERE t.trilha_id = ? ORDER BY t.ordem, t.id', [$trId]);

$flash = lms_flash_get();
lms_head('Treinamentos · ' . $trilha['nome'], $u);
lms_topbar($u);
?>
<main class="lms-container" style="padding-top:28px; padding-bottom:64px">

  <a class="lms-small" href="/boticario/admin/trilhas">← Todas as trilhas</a>
  <div class="lms-row" style="justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-top:8px">
    <div>
      <h1 style="font-size:var(--lms-title-md)">📁 <?= e($trilha['nome']) ?></h1>
      <p class="lms-muted lms-small" style="margin-top:4px"><?= $trilha['narrativa'] ? e($trilha['narrativa']) . ' · ' : '' ?><?= count($treinos) ?> treinamento<?= count($treinos) === 1 ? '' : 's' ?></p>
    </div>
    <button type="button" class="lms-btn lms-btn-primary" id="btn-novo-treinamento">+ Novo treinamento</button>
  </div>

  <?php if ($flash): ?>
  <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:18px">
    <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div></div>
  <?php endif; ?>

  <?php lms_busca('.t-card', 'Buscar treinamento por nome, descrição, status…', count($treinos), 'treinamentos', 't-busca'); ?>

  <!-- pastas de treinamento -->
  <div class="lms-stack-2" style="margin-top:14px; display:flex; flex-direction:column; gap:14px">
    <?php foreach ($treinos as $t): $arquivado = $t['status'] !== 'ativo'; ?>
    <div class="lms-card-game lms-row t-card" style="justify-content:space-between; gap:14px; padding:18px 22px; flex-wrap:wrap <?= $arquivado ? ';opacity:.55' : '' ?>">
      <div style="flex:1; min-width:230px">
        <div class="lms-row" style="gap:10px">
          <span style="font-size:26px">📁</span>
          <div>
            <h3 style="font-size:17px"><?= e($t['nome']) ?></h3>
            <?php if ($t['descricao']): ?><p class="lms-small lms-muted" style="margin-top:1px"><?= e($t['descricao']) ?></p><?php endif; ?>
          </div>
        </div>
        <div class="lms-row" style="gap:6px; margin-top:10px; flex-wrap:wrap">
          <?php if ((int) $t['n_fases'] === 0): ?>
          <span class="lms-pill is-warning">sem fases</span>
          <?php else: ?>
          <span class="lms-pill">📍 <?= (int) $t['n_fases'] ?> fase<?= (int) $t['n_fases'] === 1 ? '' : 's' ?></span>
          <span class="lms-pill">🧩 <?= (int) $t['n_etapas'] ?> etapa<?= (int) $t['n_etapas'] === 1 ? '' : 's' ?></span>
          <?php endif; ?>
          <?php if ((int) $t['n_alunos']): ?><span class="lms-pill is-primary">👤 <?= (int) $t['n_alunos'] ?> aluno<?= (int) $t['n_alunos'] === 1 ? '' : 's' ?></span><?php endif; ?>
          <?= $arquivado ? '<span class="lms-pill">arquivado</span>' : '' ?>
        </div>
      </div>
      <div class="lms-row" style="gap:6px; flex-wrap:nowrap; align-items:center">
        <form method="post" style="display:inline"><input type="hidden" name="acao" value="t_mover"><input type="hidden" name="trilha_id" value="<?= $trId ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="dir" value="up"><button class="lms-btn lms-btn-outline lms-btn-sm" title="Subir na ordem">↑</button></form>
        <form method="post" style="display:inline"><input type="hidden" name="acao" value="t_mover"><input type="hidden" name="trilha_id" value="<?= $trId ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="dir" value="down"><button class="lms-btn lms-btn-outline lms-btn-sm" title="Descer na ordem">↓</button></form>
        <a class="lms-btn lms-btn-game lms-btn-sm" href="/boticario/admin/mapa?t=<?= (int) $t['id'] ?>">🗺️ Fases no mapa ▶</a>
        <?php if (str_starts_with($t['nome'], 'Diretrizes de SSMS')): ?>
        <form method="post" style="display:inline"
              data-confirmar-titulo="Aplicar a fase &quot;Fundamentos do Indivíduo&quot;?"
              data-confirmar="21 etapas, 2 vídeos + 2 leituras densas.&#10;Se a fase já existir, as etapas dela são substituídas pela versão atual — o progresso de alunos nela é zerado.">
          <input type="hidden" name="acao" value="t_ssms_fase_unica"><input type="hidden" name="trilha_id" value="<?= $trId ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
          <button class="lms-btn lms-btn-outline lms-btn-sm" title="Aplicar fase 'Fundamentos do Indivíduo' (hard content)">🎬 Aplicar Fundamentos</button>
        </form>
        <?php endif; ?>
        <a class="lms-btn lms-btn-outline lms-btn-sm" title="Renomear" href="/boticario/admin/treinamentos?tr=<?= $trId ?>&editar=<?= (int) $t['id'] ?>">✏️</a>
        <form method="post" style="display:inline" <?= $arquivado ? '' : 'data-confirmar-titulo="Arquivar este treinamento?" data-confirmar="Ele não poderá ser atribuído a alunos (o histórico é preservado)."' ?>>
          <input type="hidden" name="acao" value="t_status"><input type="hidden" name="trilha_id" value="<?= $trId ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
          <button class="lms-btn lms-btn-outline lms-btn-sm" title="<?= $arquivado ? 'Reativar' : 'Arquivar' ?>"><?= $arquivado ? '♻️' : '📦' ?></button>
        </form>
        <form method="post" style="display:inline"
              data-confirmar-titulo="Excluir “<?= e($t['nome']) ?>” definitivamente?"
              data-confirmar="Apaga as <?= (int) $t['n_fases'] ?> fase(s), etapas, progresso dos alunos e conversas.&#10;Não tem volta." data-confirmar-perigo>
          <input type="hidden" name="acao" value="t_excluir"><input type="hidden" name="trilha_id" value="<?= $trId ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
          <button class="lms-btn lms-btn-outline lms-btn-sm" style="color:var(--lms-error)" title="Excluir definitivamente">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; if (!$treinos): ?>
    <p class="lms-muted">Nenhum treinamento nesta trilha ainda — clique em <strong>+ Novo treinamento</strong> para criar o primeiro. 👆</p>
    <?php endif; ?>
  </div>
</main>

<?php
$voltar = '/boticario/admin/treinamentos?tr=' . $trId;
lms_popup_abre('ft', $editando ? '✏️ Renomear: ' . e($editando['nome']) : '➕ Novo treinamento',
               'A pasta que agrupa as fases. O conteúdo de verdade você monta no mapa.', 620);
?>
    <form method="post">
      <input type="hidden" name="acao" value="t_salvar">
      <input type="hidden" name="trilha_id" value="<?= $trId ?>">
      <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
      <div class="lms-stack-4" style="display:flex; flex-direction:column; gap:14px">
        <div>
          <label class="lms-label" for="t-nome">Nome</label>
          <input class="lms-input" id="t-nome" name="nome" required value="<?= e($editando['nome'] ?? '') ?>" placeholder="Ex.: Integração SSMS">
        </div>
        <div>
          <label class="lms-label" for="t-desc">Descrição (opcional)</label>
          <input class="lms-input" id="t-desc" name="descricao" value="<?= e($editando['descricao'] ?? '') ?>" placeholder="Para quem é, o que cobre…">
        </div>
      </div>
      <div class="lms-row" style="margin-top:20px">
        <button class="lms-btn lms-btn-primary"><?= $editando ? 'Salvar' : 'Criar e abrir o mapa ▶' ?></button>
        <button type="button" class="lms-btn lms-btn-outline" data-fecha="ft">Cancelar</button>
      </div>
    </form>
<?php lms_popup_fecha('ft', 'btn-novo-treinamento', (bool) $editando, $voltar); ?>
</body></html>
