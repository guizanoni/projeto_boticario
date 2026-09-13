<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Trilhas (pasta-mãe do conteúdo).
// Cada trilha é um UNIVERSO: dona do mapa/cenário e da narrativa
// (trilha pirata = mapa pirata). Dentro dela vivem os treinamentos
// (pastas) e, dentro deles, as fases (o jogável, com pin no mapa).
// Grid de cards com a miniatura do cenário; criar/editar aqui,
// navegar para as pastas em treinamentos.php. Só admin acessa.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['admin']);
if ($u['papel'] !== 'admin') { header('Location: ' . lms_home($u)); exit; }

$dirMapas = dirname(__DIR__) . '/data/mapas';

// Salva uma arte enviada (até 12 MB) → data/mapas/{id}{sufixo}.{ext}
// e devolve a URL de entrega (mapa-arte.php, com cache-bust). Null = erro.
// Sufixos: '' (dia, a arte base) · '-tarde/-noite/-madrugada' (períodos)
// · '-avatar-m/-avatar-f/-avatar-pet' (personagens — só PNG/WEBP, que
// têm transparência; personagem com fundo quadrado quebra a imersão).
function trilha_salvar_arte(int $trilhaId, array $file, string $dirMapas, string $sufixo = '', array $exts = ['webp', 'png', 'jpg']): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 12 * 1024 * 1024) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/webp' => 'webp', 'image/png' => 'png', 'image/jpeg' => 'jpg'][$mime] ?? null;
    if (!$ext || !in_array($ext, $exts, true)) return null;
    if (!is_dir($dirMapas)) @mkdir($dirMapas, 0755, true);
    foreach (['webp', 'png', 'jpg'] as $e) @unlink("{$dirMapas}/{$trilhaId}{$sufixo}.{$e}");
    if (!move_uploaded_file($file['tmp_name'], "{$dirMapas}/{$trilhaId}{$sufixo}.{$ext}")) return null;
    return '/boticario/mapa-arte?tr=' . $trilhaId . '&v=' . time();
}

// Períodos com variante própria de arte (o dia é a arte base).
const TRILHA_PERIODOS = ['tarde' => '🌅 Fim de tarde', 'noite' => '🌃 Noite', 'madrugada' => '🌙 Madrugada'];

// Avatares do mapa por trilha: o personagem que caminha no cenário.
// Masc/fem seguem o gênero escolhido pelo aluno; o pet é o coringa
// (vale para todos quando não há masc/fem — ou completa o que faltar).
const TRILHA_AVATARES = ['m' => '👨 Masculino', 'f' => '👩 Feminino', 'pet' => '🐾 Pet / mascote'];

// Quais variantes existem no disco para a trilha (para exibir no card).
function trilha_variantes(int $trilhaId, string $dirMapas): array {
    $tem = [];
    foreach (array_keys(TRILHA_PERIODOS) as $p) {
        foreach (['webp', 'png', 'jpg'] as $e) {
            if (is_file("{$dirMapas}/{$trilhaId}-{$p}.{$e}")) { $tem[] = $p; break; }
        }
    }
    return $tem;
}

// Quais avatares próprios a trilha tem no disco.
function trilha_avatares(int $trilhaId, string $dirMapas): array {
    $tem = [];
    foreach (array_keys(TRILHA_AVATARES) as $a) {
        foreach (['png', 'webp'] as $e) {
            if (is_file("{$dirMapas}/{$trilhaId}-avatar-{$a}.{$e}")) { $tem[] = $a; break; }
        }
    }
    return $tem;
}

// ── Ações (POST) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);
    $alvo = $id ? db_one('SELECT * FROM trilha WHERE id = ? AND cliente_id = ?', [$id, $u['cliente_id']]) : null;

    if ($acao === 'tr_salvar') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $narrativa = trim((string) ($_POST['narrativa'] ?? ''));
        $enviou = fn(string $campo) => ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        // salva a arte base (dia) + variantes de período + avatares do mapa
        $salvarArtes = function (int $trilhaId) use ($enviou, $dirMapas): ?string {
            $erros = [];
            if ($enviou('arte')) {
                $url = trilha_salvar_arte($trilhaId, $_FILES['arte'], $dirMapas);
                if ($url) db_run('UPDATE trilha SET cenario_mapa_url = ? WHERE id = ?', [$url, $trilhaId]);
                else $erros[] = 'arte do dia';
            }
            foreach (array_keys(TRILHA_PERIODOS) as $p) {
                if ($enviou('arte_' . $p) && !trilha_salvar_arte($trilhaId, $_FILES['arte_' . $p], $dirMapas, '-' . $p)) $erros[] = $p;
            }
            foreach (array_keys(TRILHA_AVATARES) as $a) {
                if ($enviou('avatar_' . $a) && !trilha_salvar_arte($trilhaId, $_FILES['avatar_' . $a], $dirMapas, '-avatar-' . $a, ['png', 'webp'])) $erros[] = 'avatar ' . $a . ' (use PNG/WEBP com fundo transparente)';
            }
            return $erros ? implode(', ', $erros) : null;
        };

        if ($nome === '') {
            lms_flash_set(false, 'Dê um nome à trilha.');
        } elseif ($alvo) {
            db_run('UPDATE trilha SET nome = ?, narrativa = ? WHERE id = ?', [$nome, $narrativa, $id]);
            $erro = $salvarArtes($id);
            if ($erro) lms_flash_set(false, "Trilha salva, mas a arte não foi aceita ({$erro}) — use WEBP, PNG ou JPG de até 12 MB.");
            else lms_flash_set(true, "Trilha “{$nome}” atualizada.");
        } else {
            $ordem = (int) db_one('SELECT COALESCE(MAX(ordem),0)+1 AS o FROM trilha WHERE cliente_id = ?', [$u['cliente_id']])['o'];
            db_run('INSERT INTO trilha (cliente_id, nome, narrativa, ordem) VALUES (?,?,?,?)', [$u['cliente_id'], $nome, $narrativa, $ordem]);
            $novo = (int) db_one('SELECT last_insert_rowid() AS i')['i'];
            $salvarArtes($novo);
            lms_flash_set(true, "Trilha “{$nome}” criada — agora crie os treinamentos dela.");
            header('Location: /boticario/admin/treinamentos?tr=' . $novo); exit;
        }

    } elseif ($acao === 'tr_excluir' && $alvo) {
        // exclusão definitiva: leva treinamentos, fases, etapas, progresso
        // e conversas. Alunos apontando para treinamentos dela ficam sem
        // treinamento ativo (o admin reatribui em Usuários).
        $fases = db_all('SELECT f.id FROM fase f JOIN treinamento t ON t.id = f.treinamento_id WHERE t.trilha_id = ?', [$id]);
        $fids = array_map(fn($f) => (int) $f['id'], $fases);
        if ($fids) {
            $in = implode(',', $fids);
            db_run("DELETE FROM progresso_usuario WHERE etapa_id IN (SELECT id FROM etapa WHERE fase_id IN ({$in}))");
            db_run("DELETE FROM comentario WHERE fase_id IN ({$in})");
            db_run("DELETE FROM etapa WHERE fase_id IN ({$in})");
            db_run("DELETE FROM fase WHERE id IN ({$in})");
        }
        db_run('UPDATE usuario SET treinamento_id = NULL WHERE treinamento_id IN (SELECT id FROM treinamento WHERE trilha_id = ?)', [$id]);
        db_run('UPDATE usuario SET treinamento_id_2 = NULL WHERE treinamento_id_2 IN (SELECT id FROM treinamento WHERE trilha_id = ?)', [$id]);
        db_run('DELETE FROM treinamento WHERE trilha_id = ?', [$id]);
        db_run('DELETE FROM trilha WHERE id = ?', [$id]);
        foreach (['', '-tarde', '-noite', '-madrugada', '-avatar-m', '-avatar-f', '-avatar-pet'] as $s) {
            foreach (['webp', 'png', 'jpg'] as $e) @unlink("{$dirMapas}/{$id}{$s}.{$e}");
        }
        lms_flash_set(true, "Trilha “{$alvo['nome']}” excluída com tudo que havia nela.");
    }
    header('Location: /boticario/admin/trilhas'); exit;
}

// ── Dados da tela ────────────────────────────────────────────
$editando = null;
if (!empty($_GET['editar'])) {
    $editando = db_one('SELECT * FROM trilha WHERE id = ? AND cliente_id = ?', [(int) $_GET['editar'], $u['cliente_id']]);
}
$trilhas = db_all('SELECT tr.*,
        (SELECT COUNT(*) FROM treinamento t WHERE t.trilha_id = tr.id) AS n_treins,
        (SELECT COUNT(*) FROM fase f JOIN treinamento t ON t.id = f.treinamento_id WHERE t.trilha_id = tr.id) AS n_fases,
        (SELECT COUNT(*) FROM usuario us WHERE us.treinamento_id IN (SELECT id FROM treinamento WHERE trilha_id = tr.id) AND us.papel = \'aluno\') AS n_alunos
    FROM trilha tr WHERE tr.cliente_id = ? ORDER BY tr.ordem, tr.id', [$u['cliente_id']]);

$flash = lms_flash_get();
lms_head('Trilhas', $u);
lms_topbar($u);
?>
<main class="lms-container" style="padding-top:28px; padding-bottom:64px">

  <div class="lms-row" style="justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap">
    <div>
      <h1 style="font-size:var(--lms-title-md)">🧭 Trilhas</h1>
      <p class="lms-muted lms-small" style="margin-top:4px">Cada trilha é um universo: mapa e narrativa próprios. Dentro dela: treinamentos (pastas) → fases (pins no mapa).</p>
    </div>
    <button type="button" class="lms-btn lms-btn-primary" id="btn-nova-trilha">+ Nova trilha</button>
  </div>

  <?php if ($flash): ?>
  <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:18px">
    <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div></div>
  <?php endif; ?>

  <?php lms_busca('.tr-card', 'Buscar trilha por nome, narrativa, treinamentos…', count($trilhas), 'trilhas', 'tr-busca'); ?>

  <!-- grid de trilhas -->
  <div class="tr-grid" style="margin-top:14px">
    <?php foreach ($trilhas as $tr): ?>
    <div class="lms-card-game tr-card">
      <a class="tr-thumb" href="/boticario/admin/treinamentos?tr=<?= (int) $tr['id'] ?>" title="Abrir a trilha">
        <?php if ($tr['cenario_mapa_url']): ?>
        <img src="<?= e($tr['cenario_mapa_url']) ?>" alt="Cenário de <?= e($tr['nome']) ?>" loading="lazy">
        <?php else: ?>
        <div class="tr-thumb-vazia">🗺️<br><span class="lms-small">sem mapa ainda</span></div>
        <?php endif; ?>
      </a>
      <div style="padding:16px 18px 18px">
        <h3><?= e($tr['nome']) ?></h3>
        <?php if ($tr['narrativa']): ?><p class="lms-small lms-muted" style="margin-top:3px"><?= e($tr['narrativa']) ?></p><?php endif; ?>
        <div class="lms-row" style="gap:6px; margin-top:10px; flex-wrap:wrap">
          <span class="lms-pill">📁 <?= (int) $tr['n_treins'] ?> treinamento<?= (int) $tr['n_treins'] === 1 ? '' : 's' ?></span>
          <span class="lms-pill">📍 <?= (int) $tr['n_fases'] ?> fase<?= (int) $tr['n_fases'] === 1 ? '' : 's' ?></span>
          <?php if ((int) $tr['n_alunos']): ?><span class="lms-pill is-primary">👤 <?= (int) $tr['n_alunos'] ?> aluno<?= (int) $tr['n_alunos'] === 1 ? '' : 's' ?></span><?php endif; ?>
          <?php $vars = trilha_variantes((int) $tr['id'], $dirMapas);
                $ciclo = ['☀️']; foreach (array_keys(TRILHA_PERIODOS) as $p) if (in_array($p, $vars, true)) $ciclo[] = ['tarde' => '🌅', 'noite' => '🌃', 'madrugada' => '🌙'][$p]; ?>
          <span class="lms-pill" title="Ciclo do dia: variantes de arte disponíveis (a base é o dia)"><?= implode(' ', $ciclo) ?></span>
          <?php $avs = trilha_avatares((int) $tr['id'], $dirMapas); if ($avs): ?>
          <span class="lms-pill" title="Avatares próprios da trilha no mapa"><?= implode(' ', array_map(fn($a) => ['m' => '👨', 'f' => '👩', 'pet' => '🐾'][$a], $avs)) ?></span>
          <?php endif; ?>
        </div>
        <div class="lms-row" style="gap:8px; margin-top:14px">
          <a class="lms-btn lms-btn-game lms-btn-sm" href="/boticario/admin/treinamentos?tr=<?= (int) $tr['id'] ?>">Abrir ▶</a>
          <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/admin/trilhas?editar=<?= (int) $tr['id'] ?>#form-trilha" title="Editar nome, narrativa e mapa">✏️</a>
          <form method="post" style="display:inline; margin-left:auto"
                data-confirmar-titulo="Excluir “<?= e($tr['nome']) ?>” definitivamente?"
                data-confirmar="Apaga os <?= (int) $tr['n_treins'] ?> treinamento(s), <?= (int) $tr['n_fases'] ?> fase(s), etapas, progresso dos alunos e conversas.&#10;Não tem volta." data-confirmar-perigo>
            <input type="hidden" name="acao" value="tr_excluir"><input type="hidden" name="id" value="<?= (int) $tr['id'] ?>">
            <button class="lms-btn lms-btn-outline lms-btn-sm" style="color:var(--lms-error)" title="Excluir a trilha e tudo que há nela">🗑</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; if (!$trilhas): ?>
    <p class="lms-muted">Nenhuma trilha ainda — clique em <strong>+ Nova trilha</strong> para criar a primeira. 👆</p>
    <?php endif; ?>
  </div>
</main>

<?php lms_popup_abre('ft', $editando ? '✏️ Editar trilha: ' . e($editando['nome']) : '➕ Nova trilha',
      'O mapa é a cara da trilha: a arte do cenário onde as fases ganham pins. Proporção larga (ex.: 1024×572) funciona melhor.', 860); ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="acao" value="tr_salvar">
      <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px">
        <div>
          <label class="lms-label" for="tr-nome">Nome da trilha</label>
          <input class="lms-input" id="tr-nome" name="nome" required value="<?= e($editando['nome'] ?? '') ?>" placeholder="Ex.: Jornada da Loja · Ilha Pirata · …">
        </div>
        <div>
          <label class="lms-label" for="tr-narrativa">Narrativa (uma linha que dá o clima)</label>
          <input class="lms-input" id="tr-narrativa" name="narrativa" value="<?= e($editando['narrativa'] ?? '') ?>" placeholder="Ex.: A jornada de quem está começando na equipe.">
        </div>
      </div>

      <?php
      // slots visuais de upload: cartão clicável com miniatura e status
      $varsEdit = $editando ? trilha_variantes((int) $editando['id'], $dirMapas) : [];
      $avsEdit  = $editando ? trilha_avatares((int) $editando['id'], $dirMapas) : [];
      $trId     = (int) ($editando['id'] ?? 0);
      ?>

      <!-- ciclo do dia: a arte base (dia) + variantes opcionais. O mapa do
           aluno acompanha o relógio de Brasília; variante ausente cai no dia. -->
      <p class="up-secao">🕐 Ciclo do dia <span>— só o ☀️ Dia é obrigatório; as demais entram no ciclo quando enviadas</span></p>
      <div class="up-grid">
        <?php
        $periodos = ['' => ['☀️', 'Dia', 'arte base']] ;
        foreach (TRILHA_PERIODOS as $p => $rot) {
            [$emoji, $nomeP] = explode(' ', $rot, 2);
            $periodos[$p] = [$emoji, $nomeP, 'opcional'];
        }
        foreach ($periodos as $p => [$emoji, $nomeP, $sub]):
            $tem   = $p === '' ? (bool) ($editando['cenario_mapa_url'] ?? false) : in_array($p, $varsEdit, true);
            $thumb = !$tem ? null : ($p === '' ? $editando['cenario_mapa_url'] : "/boticario/mapa-arte?tr={$trId}&p=" . e($p) . '&v=' . time());
            $campo = $p === '' ? 'arte' : 'arte_' . $p;
        ?>
        <label class="up-slot <?= $tem ? 'is-ok' : '' ?>">
          <input type="file" name="<?= e($campo) ?>" accept=".webp,.png,.jpg,.jpeg,image/webp,image/png,image/jpeg" hidden>
          <?php if ($thumb): ?><img class="up-thumb" src="<?= e($thumb) ?>" alt="<?= e($nomeP) ?>" loading="lazy">
          <?php else: ?><span class="up-vazio"><?= $emoji ?></span><?php endif; ?>
          <span class="up-nome"><?= $emoji ?> <?= e($nomeP) ?></span>
          <span class="up-status"><?= $tem ? '✓ enviada — clique para trocar' : ($sub === 'arte base' ? 'clique para enviar a arte' : 'opcional — clique para enviar') ?></span>
        </label>
        <?php endforeach; ?>
      </div>

      <!-- avatares do mapa: o personagem que caminha no cenário. Masc/fem
           seguem a escolha do aluno; o pet é o coringa (vale para todos
           quando é o único — ou completa o gênero que faltar). -->
      <p class="up-secao">🧍 Avatares do mapa <span>— opcional; PNG/WEBP com <strong>fundo transparente</strong>. Sem envio, vale o personagem padrão da plataforma</span></p>
      <div class="up-grid">
        <?php foreach (TRILHA_AVATARES as $a => $rotulo):
            [$emojiA, $nomeA] = explode(' ', $rotulo, 2);
            $temA = in_array($a, $avsEdit, true);
        ?>
        <label class="up-slot is-avatar <?= $temA ? 'is-ok' : '' ?>">
          <input type="file" name="avatar_<?= e($a) ?>" accept=".png,.webp,image/png,image/webp" hidden>
          <?php if ($temA): ?><img class="up-thumb is-avatar" src="/boticario/mapa-arte?tr=<?= $trId ?>&av=<?= e($a) ?>&v=<?= time() ?>" alt="<?= e($nomeA) ?>" loading="lazy">
          <?php else: ?><span class="up-vazio"><?= $emojiA ?></span><?php endif; ?>
          <span class="up-nome"><?= $emojiA ?> <?= e($nomeA) ?></span>
          <span class="up-status"><?= $temA ? '✓ enviado — clique para trocar' : 'opcional — clique para enviar' ?></span>
        </label>
        <?php endforeach; ?>
      </div>

      <div class="lms-row" style="margin-top:20px">
        <button class="lms-btn lms-btn-primary"><?= $editando ? 'Salvar alterações' : 'Criar trilha ▶' ?></button>
        <button type="button" class="lms-btn lms-btn-outline" data-fecha="ft">Cancelar</button>
      </div>
    </form>
<?php lms_popup_fecha('ft', 'btn-nova-trilha', (bool) $editando, '/boticario/admin/trilhas'); ?>

<script>
// slots de upload: ao escolher um arquivo, o cartão confirma na hora
document.querySelectorAll('.up-slot input[type="file"]').forEach(inp => {
  inp.addEventListener('change', () => {
    const slot = inp.closest('.up-slot');
    const status = slot.querySelector('.up-status');
    if (inp.files[0]) {
      slot.classList.add('is-picked');
      status.textContent = '📎 ' + inp.files[0].name + ' — salve para aplicar';
      // pré-visualização imediata do arquivo escolhido
      const url = URL.createObjectURL(inp.files[0]);
      let img = slot.querySelector('.up-thumb');
      if (!img) {
        img = document.createElement('img');
        img.className = 'up-thumb' + (slot.classList.contains('is-avatar') ? ' is-avatar' : '');
        slot.querySelector('.up-vazio')?.remove();
        slot.prepend(img);
      }
      img.src = url;
    }
  });
});
</script>

<style>
  /* slots de upload do popup: cartões clicáveis com miniatura e status */
  .up-secao { font-family:var(--lms-font-ui); font-weight:700; font-size:13px; margin:20px 0 10px }
  .up-secao span { font-weight:400; color:var(--lms-ink-soft); font-size:12px }
  .up-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px }
  .up-slot {
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px;
    min-height:128px; padding:14px 10px; text-align:center; cursor:pointer;
    border:2px dashed var(--lms-border); border-radius:var(--lms-radius-md, 12px);
    background:var(--lms-white); transition:border-color .15s ease, transform .15s var(--lms-ease, ease);
  }
  .up-slot:hover { border-color:var(--lms-primary); transform:translateY(-2px) }
  .up-slot.is-ok { border-style:solid; border-color:var(--lms-success) }
  .up-slot.is-picked { border-style:solid; border-color:var(--lms-primary); background:var(--lms-sky, #D3ECF9) }
  .up-vazio { font-size:30px; opacity:.45; line-height:1.2 }
  .up-thumb { width:100%; aspect-ratio:16/9; object-fit:cover; border-radius:8px; display:block }
  .up-thumb.is-avatar { width:auto; height:56px; aspect-ratio:auto; object-fit:contain }
  .up-nome { font-family:var(--lms-font-ui); font-weight:700; font-size:12.5px; color:var(--lms-heading) }
  .up-status { font-size:11px; color:var(--lms-ink-soft); line-height:1.35; overflow-wrap:anywhere }
  .up-slot.is-ok .up-status { color:var(--lms-success) }
  .up-slot.is-picked .up-status { color:var(--lms-heading); font-weight:600 }

  .tr-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(310px, 1fr)); gap:20px }
  .tr-card { padding:0; overflow:hidden }
  .tr-thumb { display:block; aspect-ratio:1024/450; overflow:hidden; background:#D3ECF9;
    border-bottom:var(--lms-border-game) }
  .tr-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .25s var(--lms-ease, ease) }
  .tr-thumb:hover img { transform:scale(1.05) }
  .tr-thumb-vazia { height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center;
    font-size:38px; color:var(--lms-ink-soft); gap:4px; text-align:center }
</style>
</body></html>
