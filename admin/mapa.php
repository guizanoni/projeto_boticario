<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Fases de um treinamento, montadas DIRETO NO MAPA.
// O cenário da trilha vira prancheta: clicar na arte cria o pin de
// uma fase nova; arrastar reposiciona; clicar no pin edita ou abre
// as etapas. Desbloqueio configurável por fase: sequência (anterior
// concluída), data específica ou XP mínimo. Só admin acessa.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['admin']);
if ($u['papel'] !== 'admin') { header('Location: ' . lms_home($u)); exit; }

$tid = (int) ($_GET['t'] ?? $_POST['treinamento_id'] ?? 0);
$trein = db_one('SELECT t.*, tr.nome AS trilha_nome, tr.cenario_mapa_url, tr.id AS trilha_id, tr.cliente_id
                 FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id WHERE t.id = ?', [$tid]);
if (!$trein || (int) $trein['cliente_id'] !== (int) $u['cliente_id']) { header('Location: /boticario/admin/trilhas'); exit; }

$desbloqueios = ['sequencia' => '🔗 Evolução — abre ao concluir a fase anterior',
                 'data'      => '📅 Data — abre num dia específico',
                 'xp'        => '⭐ XP — abre ao atingir uma quantidade de experiência (0 = sempre aberta)'];

function fase_valida_desbloqueio(string $tipo, string $valor): ?string {
    if ($tipo === 'data' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return 'Escolha a data de abertura da fase.';
    // XP 0 é válido: significa "sempre aberta" (usado nas vitrines/demos)
    if ($tipo === 'xp' && ($valor === '' || (int) $valor < 0)) return 'Informe o XP mínimo (0 = fase sempre aberta).';
    return null;
}

// ── Ações (POST) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);
    $alvo = $id ? db_one('SELECT * FROM fase WHERE id = ? AND treinamento_id = ?', [$id, $tid]) : null;

    // arrasto do pin (fetch) — responde JSON, sem redirect
    if ($acao === 'f_pin' && $alvo) {
        header('Content-Type: application/json');
        $x = max(0, min(100, (float) ($_POST['x'] ?? 50)));
        $y = max(0, min(100, (float) ($_POST['y'] ?? 50)));
        db_run('UPDATE fase SET pin_x = ?, pin_y = ? WHERE id = ?', [$x, $y, $id]);
        echo json_encode(['ok' => true]); exit;
    }

    if ($acao === 'f_salvar') {
        $nome  = trim((string) ($_POST['nome'] ?? ''));
        $tipo  = array_key_exists($_POST['desbloqueio'] ?? '', $desbloqueios) ? $_POST['desbloqueio'] : 'sequencia';
        $valor = trim((string) ($_POST['desbloqueio_valor'] ?? ''));
        $nota  = max(0, min(100, (int) ($_POST['nota_minima'] ?? 80)));
        $erro  = $nome === '' ? 'Dê um nome à fase.' : fase_valida_desbloqueio($tipo, $valor);
        if ($tipo === 'sequencia') $valor = null;

        if ($erro) {
            lms_flash_set(false, $erro);
        } elseif ($alvo) {
            db_run('UPDATE fase SET nome = ?, desbloqueio = ?, desbloqueio_valor = ?, nota_minima = ? WHERE id = ?',
                   [$nome, $tipo, $valor, $nota, $id]);
            lms_flash_set(true, "Fase “{$nome}” atualizada.");
        } else {
            $x = max(0, min(100, (float) ($_POST['x'] ?? 50)));
            $y = max(0, min(100, (float) ($_POST['y'] ?? 50)));
            $ordem = (int) db_one('SELECT COALESCE(MAX(ordem),0)+1 AS o FROM fase WHERE treinamento_id = ?', [$tid])['o'];
            db_run('INSERT INTO fase (treinamento_id, nome, ordem, pin_x, pin_y, desbloqueio, desbloqueio_valor, nota_minima) VALUES (?,?,?,?,?,?,?,?)',
                   [$tid, $nome, $ordem, $x, $y, $tipo, $valor, $nota]);
            $novo = (int) db_one('SELECT last_insert_rowid() AS i')['i'];
            lms_flash_set(true, "Fase “{$nome}” criada — agora monte as etapas dela.");
            header('Location: /boticario/admin/etapas?f=' . $novo); exit;
        }

    } elseif ($acao === 'f_excluir' && $alvo) {
        db_run('DELETE FROM progresso_usuario WHERE etapa_id IN (SELECT id FROM etapa WHERE fase_id = ?)', [$id]);
        db_run('DELETE FROM comentario WHERE fase_id = ?', [$id]);
        db_run('DELETE FROM etapa WHERE fase_id = ?', [$id]);
        db_run('DELETE FROM fase WHERE id = ?', [$id]);
        // renumera para a sequência continuar contínua
        foreach (db_all('SELECT id FROM fase WHERE treinamento_id = ? ORDER BY ordem', [$tid]) as $i => $f) {
            db_run('UPDATE fase SET ordem = ? WHERE id = ?', [$i + 1, $f['id']]);
        }
        lms_flash_set(true, "Fase “{$alvo['nome']}” excluída — etapas, progresso e conversas foram removidos.");

    } elseif ($acao === 'f_ordem' && $alvo) {
        $dir = $_POST['dir'] === 'up' ? -1 : 1;
        $viz = db_one('SELECT * FROM fase WHERE treinamento_id = ? AND ordem ' . ($dir < 0 ? '<' : '>') . ' ? ORDER BY ordem ' . ($dir < 0 ? 'DESC' : 'ASC') . ' LIMIT 1',
                      [$tid, $alvo['ordem']]);
        if ($viz) {
            db_run('UPDATE fase SET ordem = ? WHERE id = ?', [$viz['ordem'], $alvo['id']]);
            db_run('UPDATE fase SET ordem = ? WHERE id = ?', [$alvo['ordem'], $viz['id']]);
        }
    }
    header('Location: /boticario/admin/mapa?t=' . $tid); exit;
}

// ── Dados da tela ────────────────────────────────────────────
$fases = db_all('SELECT f.*, (SELECT COUNT(*) FROM etapa e WHERE e.fase_id = f.id) AS n_etapas
                 FROM fase f WHERE f.treinamento_id = ? ORDER BY f.ordem', [$tid]);
$fasesJs = array_map(fn($f) => [
    'id' => (int) $f['id'], 'nome' => $f['nome'], 'ordem' => (int) $f['ordem'],
    'x' => (float) $f['pin_x'], 'y' => (float) $f['pin_y'],
    'desbloqueio' => $f['desbloqueio'], 'valor' => $f['desbloqueio_valor'],
    'nota' => (int) $f['nota_minima'], 'etapas' => (int) $f['n_etapas'],
], $fases);

$flash = lms_flash_get();
lms_head('Fases no mapa · ' . $trein['nome'], $u);
lms_topbar($u);
?>
<main class="lms-container" style="max-width:1180px; padding-top:24px; padding-bottom:64px">

  <a class="lms-small" href="/boticario/admin/treinamentos?tr=<?= (int) $trein['trilha_id'] ?>">← <?= e($trein['trilha_nome']) ?></a>
  <div class="lms-row" style="justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-top:8px">
    <div>
      <h1 style="font-size:var(--lms-title-md)">📁 <?= e($trein['nome']) ?></h1>
      <p class="lms-muted lms-small" style="margin-top:4px">🖱️ <strong>Clique num ponto do mapa</strong> para criar uma fase ali · arraste um pin para reposicionar · clique no pin para editar ou abrir as etapas.</p>
    </div>
    <?php if ($trein['cenario_mapa_url']): ?>
    <button type="button" class="lms-btn lms-btn-primary" id="btn-nova-fase">+ Nova fase</button>
    <?php endif; ?>
  </div>

  <?php if ($flash): ?>
  <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:14px">
    <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div></div>
  <?php endif; ?>

  <?php if (!$trein['cenario_mapa_url']): ?>
  <div class="lms-alert is-error" style="margin-top:18px">🗺️ <div>Esta trilha ainda <strong>não tem arte de mapa</strong>.
    <a href="/boticario/admin/trilhas?editar=<?= (int) $trein['trilha_id'] ?>#form-trilha">Envie o cenário da trilha</a> para montar as fases.</div></div>
  <?php else: ?>

  <!-- o mapa-prancheta em largura total: é a peça principal da tela -->
  <div class="lms-card-game" style="padding:10px; margin-top:18px">
    <div id="ed-mapa" class="ed-mapa">
      <img src="<?= e($trein['cenario_mapa_url']) ?>" alt="Cenário da trilha <?= e($trein['trilha_nome']) ?>" draggable="false">
      <div class="ed-mira lms-hide" id="ed-mira">📍 criar fase aqui</div>
    </div>
  </div>

  <!-- fases embaixo, em cards: cada uma mostra QUANDO abre e a nota -->
  <div class="lms-row" style="justify-content:space-between; align-items:flex-end; margin:26px 0 4px; gap:10px; flex-wrap:wrap">
    <div>
      <h3>🧭 Fases desta trilha</h3>
      <p class="lms-small lms-muted" style="margin-top:2px">A ordem manda no desbloqueio por sequência · clique no card para editar quando a fase abre e a nota mínima.</p>
    </div>
    <span class="lms-pill"><?= count($fases) ?> fase<?= count($fases) === 1 ? '' : 's' ?></span>
  </div>

  <div id="ed-lista" class="ed-grid">
    <?php foreach ($fases as $f):
      // QUANDO a fase abre (xp com 0 = sempre aberta, caso da vitrine)
      [$desbIco, $desbTxt] = match ($f['desbloqueio']) {
          'data' => ['📅', $f['desbloqueio_valor'] ? 'Abre em ' . date('d/m/Y', strtotime($f['desbloqueio_valor'])) : 'Data a definir'],
          'xp'   => (int) $f['desbloqueio_valor'] > 0
                      ? ['⭐', 'Abre com ' . (int) $f['desbloqueio_valor'] . ' XP']
                      : ['🔓', 'Sempre aberta'],
          default => ['🔗', 'Abre ao concluir a anterior'],
      };
      $nota = (int) $f['nota_minima'];
      $nEt  = (int) $f['n_etapas'];
    ?>
    <div class="ed-item" data-id="<?= (int) $f['id'] ?>">
      <div class="ed-item-topo">
        <span class="ed-num"><?= (int) $f['ordem'] ?></span>
        <strong class="ed-nome" title="<?= e($f['nome']) ?>"><?= e($f['nome']) ?></strong>
      </div>

      <!-- regras da fase à mostra; a linha inteira abre o modal -->
      <button type="button" class="ed-cfg" data-editar="<?= (int) $f['id'] ?>" title="Configurar quando abre e a nota mínima">
        <span class="ed-cfg-linha"><i><?= $desbIco ?></i><?= e($desbTxt) ?></span>
        <span class="ed-cfg-linha"><i>🎯</i><?= $nota > 0 ? 'Nota mínima ' . $nota . '%' : 'Sem nota mínima' ?></span>
        <span class="ed-cfg-linha"><i>🧩</i><?= $nEt ? $nEt . ' etapa' . ($nEt === 1 ? '' : 's') : '<b class="ed-alerta">sem etapas</b>' ?></span>
      </button>

      <div class="ed-item-acoes">
        <form method="post"><input type="hidden" name="acao" value="f_ordem"><input type="hidden" name="treinamento_id" value="<?= $tid ?>"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="dir" value="up"><button class="lms-btn lms-btn-outline ed-mini" title="Subir na ordem">↑</button></form>
        <form method="post"><input type="hidden" name="acao" value="f_ordem"><input type="hidden" name="treinamento_id" value="<?= $tid ?>"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="dir" value="down"><button class="lms-btn lms-btn-outline ed-mini" title="Descer na ordem">↓</button></form>
        <button type="button" class="lms-btn lms-btn-outline ed-mini" data-editar="<?= (int) $f['id'] ?>" title="Configurar a fase">✏️</button>
        <a class="lms-btn lms-btn-primary lms-btn-sm ed-etapas" href="/boticario/admin/etapas?f=<?= (int) $f['id'] ?>" title="Montar o conteúdo">Etapas ▶</a>
      </div>
    </div>
    <?php endforeach; if (!$fases): ?>
    <p class="lms-muted">Nenhuma fase ainda — clique em <strong>+ Nova fase</strong> ou num ponto do mapa. 🖱️</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>

<!-- ── modal criar/editar fase (mesmo padrão do sistema; abre pelo
     clique no mapa, então a abertura é controlada aqui no JS) ── -->
<div id="fm-fundo" class="lms-hide lms-modal-fundo"></div>
<div id="fm-modal" class="lms-hide lms-modal-caixa" style="--modal-w:480px">
  <div class="lms-row" style="justify-content:space-between; margin-bottom:14px">
    <h3 id="fm-titulo">📍 Nova fase</h3>
    <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="fm-fechar">✕</button>
  </div>
  <form method="post" id="fm-form">
    <input type="hidden" name="acao" value="f_salvar">
    <input type="hidden" name="treinamento_id" value="<?= $tid ?>">
    <input type="hidden" name="id" id="fm-id" value="0">
    <div class="lms-stack-4" style="display:flex; flex-direction:column; gap:14px">
      <div>
        <label class="lms-label" for="fm-nome">Nome da fase</label>
        <input class="lms-input" id="fm-nome" name="nome" required placeholder="Ex.: Primeiros passos">
      </div>

      <!-- posição do pin: dá para digitar, usar as setas ou arrastar no
           mapa — o pin acompanha ao vivo enquanto os números mudam -->
      <div>
        <label class="lms-label">📍 Posição no mapa <span class="lms-muted" style="font-weight:400">— % da largura e da altura</span></label>
        <div class="fm-pos">
          <span class="fm-pos-eixo">X</span>
          <input class="lms-input" name="x" id="fm-x" type="number" min="0" max="100" step="0.5" required>
          <span class="fm-pos-eixo">Y</span>
          <input class="lms-input" name="y" id="fm-y" type="number" min="0" max="100" step="0.5" required>
          <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="fm-centro" title="Centralizar no mapa">⌖</button>
        </div>
        <p class="lms-small lms-muted" style="margin-top:6px">Dica: arraste o pin no mapa ou ajuste os números com as setas ↑↓ do teclado.</p>
      </div>

      <div>
        <label class="lms-label" for="fm-desb">🔓 Quando esta fase abre para o aluno</label>
        <select class="lms-select" id="fm-desb" name="desbloqueio">
          <?php foreach ($desbloqueios as $k => $lbl): ?>
          <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="fm-valor-data" class="lms-hide">
        <label class="lms-label">Abre em</label>
        <input class="lms-input" type="date" id="fm-data">
      </div>
      <div id="fm-valor-xp" class="lms-hide">
        <label class="lms-label">XP mínimo <span class="lms-muted" style="font-weight:400">— use 0 para deixar a fase sempre aberta</span></label>
        <input class="lms-input" type="number" id="fm-xp" min="0" step="10" placeholder="Ex.: 200">
      </div>
      <input type="hidden" name="desbloqueio_valor" id="fm-valor">
      <div>
        <label class="lms-label" for="fm-nota">🎯 Nota mínima para aprovar (%)</label>
        <input class="lms-input" id="fm-nota" name="nota_minima" type="number" min="0" max="100" step="5" value="80">
        <p class="lms-small lms-muted" style="margin-top:6px">Abaixo disso o aluno refaz a fase do zero. Use <strong>0</strong> para não exigir nota — concluir as etapas basta.</p>
      </div>
    </div>
    <div class="lms-row" style="margin-top:20px; gap:10px; flex-wrap:wrap">
      <button class="lms-btn lms-btn-primary" id="fm-salvar">Criar fase ▶</button>
      <a class="lms-btn lms-btn-outline lms-hide" id="fm-etapas" href="#">🧩 Etapas</a>
    </div>
  </form>
  <form method="post" id="fm-form-excluir" class="lms-hide" style="margin-top:12px; border-top:var(--lms-border); padding-top:12px">
    <input type="hidden" name="acao" value="f_excluir">
    <input type="hidden" name="treinamento_id" value="<?= $tid ?>">
    <input type="hidden" name="id" id="fm-id-excluir" value="0">
    <button class="lms-btn lms-btn-outline lms-btn-sm" style="color:var(--lms-error)">🗑 Excluir fase (etapas, progresso e conversas vão junto)</button>
  </form>
</div>

<style>
  /* fases em cards abaixo do mapa (o mapa fica em largura total) */
  .ed-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:14px; margin-top:12px }

  .ed-mapa { position:relative; border-radius:var(--lms-radius-md); overflow:hidden; cursor:crosshair; user-select:none }
  .ed-mapa img { width:100%; height:auto; display:block; -webkit-user-drag:none; pointer-events:none }
  /* grade de referência: ajuda a mirar a posição em % */
  .ed-mapa::after {
    content:''; position:absolute; inset:0; pointer-events:none; opacity:0; transition:opacity .2s ease;
    background:
      repeating-linear-gradient(90deg, rgba(31,42,37,.25) 0 1px, transparent 1px 10%),
      repeating-linear-gradient(180deg, rgba(31,42,37,.25) 0 1px, transparent 1px 10%);
  }
  .ed-mapa:hover::after { opacity:.55 }

  .ed-mira { position:absolute; transform:translate(-50%, -130%); background:var(--lms-heading); color:#fff;
    font-family:var(--lms-font-ui); font-size:12px; font-weight:600; padding:5px 10px; border-radius:6px;
    pointer-events:none; white-space:nowrap; z-index:9 }

  /* pin de fase: numerado, arrastável */
  .ed-pin { position:absolute; transform:translate(-50%, -50%); width:38px; height:38px; border-radius:50%;
    background:var(--lms-primary); border:3px solid #fff; color:#fff; cursor:grab; z-index:6;
    display:flex; align-items:center; justify-content:center; font-family:var(--lms-font-ui); font-weight:800; font-size:15px;
    box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 1.5px rgba(31,42,37,.35); touch-action:none }
  .ed-pin:active { cursor:grabbing }
  .ed-pin.is-drag { z-index:20; transform:translate(-50%, -50%) scale(1.15) }
  /* fantasma: pré-visualização da fase nova enquanto o admin acerta o ponto */
  .ed-pin.is-fantasma { background:var(--lms-success); pointer-events:none; z-index:19;
    animation:ed-fantasma 1.2s ease-in-out infinite }
  @keyframes ed-fantasma {
    0%, 100% { box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 0 rgba(46,125,50,.5) }
    50%      { box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 12px rgba(46,125,50,0) }
  }

  /* campos de posição do modal */
  .fm-pos { display:flex; align-items:center; gap:8px }
  .fm-pos .lms-input { flex:1; min-width:0; text-align:center; font-family:var(--lms-font-ui); font-weight:700 }
  .fm-pos-eixo { flex:none; font-family:var(--lms-font-ui); font-weight:800; font-size:12px;
    color:var(--lms-ink-soft); width:12px; text-align:center }
  .ed-pin .ed-tip { position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
    background:var(--lms-heading); color:#fff; font-size:12px; font-weight:600; white-space:nowrap;
    padding:4px 9px; border-radius:6px; opacity:0; pointer-events:none; transition:opacity .15s ease }
  .ed-pin:hover .ed-tip { opacity:1 }
  .ed-pin.sem-etapas { background:#B07A10 }

  /* cartão da fase: cabeçalho, regras e ações — alturas iguais na grade */
  .ed-item { display:flex; flex-direction:column; background:var(--lms-white);
    border:var(--lms-border-game); border-radius:var(--lms-radius-md);
    box-shadow:var(--lms-shadow-crisp-sm); overflow:hidden;
    transition:transform .15s var(--lms-ease, ease), box-shadow .15s ease }
  .ed-item:hover { transform:translateY(-2px); box-shadow:var(--lms-shadow-crisp) }
  .ed-item.is-alvo { box-shadow:var(--lms-shadow-crisp), 0 0 0 3px rgba(0,122,84,.45) }

  .ed-item-topo { display:flex; align-items:center; gap:9px; padding:12px 14px;
    border-bottom:1px dashed var(--lms-border) }
  .ed-num { flex:none; width:26px; height:26px; border-radius:50%; background:var(--lms-primary); color:#fff;
    font-family:var(--lms-font-ui); font-weight:800; font-size:12.5px;
    display:flex; align-items:center; justify-content:center }
  .ed-nome { flex:1; min-width:0; font-family:var(--lms-font-ui); font-weight:700; font-size:14.5px;
    line-height:1.3; color:var(--lms-heading); overflow-wrap:anywhere }

  /* regras da fase: linhas discretas; o bloco todo é o botão de configurar */
  .ed-cfg { flex:1; display:flex; flex-direction:column; gap:3px; width:100%; text-align:left;
    padding:11px 14px; cursor:pointer; background:none; border:none; font-family:inherit;
    transition:background .15s ease }
  .ed-cfg:hover { background:var(--lms-sky, #D3ECF9) }
  .ed-cfg-linha { display:flex; align-items:baseline; gap:8px; font-size:12.5px; line-height:1.5;
    color:var(--lms-ink-soft); overflow-wrap:anywhere }
  .ed-cfg-linha i { flex:none; font-style:normal; width:15px; text-align:center; opacity:.85 }
  .ed-alerta { color:var(--lms-error); font-weight:700 }

  .ed-item-acoes { display:flex; gap:6px; align-items:center; padding:10px 14px;
    border-top:1px dashed var(--lms-border); background:var(--lms-bg) }
  .ed-item-acoes form { display:contents }
  .ed-mini { flex:none; width:30px; height:30px; padding:0; font-size:12px;
    display:inline-flex; align-items:center; justify-content:center }
  .ed-etapas { flex:1; height:30px; padding:0 12px; margin-left:auto;
    display:inline-flex; align-items:center; justify-content:center; font-size:12.5px }
</style>

<script>
const FASES = <?= json_encode($fasesJs, JSON_UNESCAPED_UNICODE) ?>;
const TID = <?= $tid ?>;

const mapa = document.getElementById('ed-mapa');
const mira = document.getElementById('ed-mira');

/* coordenadas % a partir de um evento de mouse sobre o mapa */
function pct(ev) {
  const r = mapa.getBoundingClientRect();
  return {
    x: Math.max(0, Math.min(100, (ev.clientX - r.left) / r.width * 100)),
    y: Math.max(0, Math.min(100, (ev.clientY - r.top) / r.height * 100)),
  };
}

/* ── modal ─────────────────────────────────────────────────── */
const modal = document.getElementById('fm-modal'), fundo = document.getElementById('fm-fundo');
const campoDesb = document.getElementById('fm-desb');

const campoX = document.getElementById('fm-x'), campoY = document.getElementById('fm-y');
let faseAberta = null;              // fase em edição (para mover o pin ao vivo)

function abreModal(fase, x, y) {
  faseAberta = fase || null;
  document.getElementById('fm-id').value = fase ? fase.id : 0;
  document.getElementById('fm-nome').value = fase ? fase.nome : '';
  campoX.value = (+(x ?? fase?.x ?? 50)).toFixed(1);
  campoY.value = (+(y ?? fase?.y ?? 50)).toFixed(1);
  campoDesb.value = fase ? fase.desbloqueio : 'sequencia';
  document.getElementById('fm-data').value = fase && fase.desbloqueio === 'data' ? (fase.valor || '') : '';
  document.getElementById('fm-xp').value = fase && fase.desbloqueio === 'xp' ? (fase.valor || '') : '';
  document.getElementById('fm-nota').value = fase ? fase.nota : 80;
  document.getElementById('fm-titulo').textContent = fase ? '✏️ ' + fase.nome : '📍 Nova fase';
  document.getElementById('fm-salvar').textContent = fase ? 'Salvar alterações' : 'Criar fase ▶';
  const linkEtapas = document.getElementById('fm-etapas');
  linkEtapas.classList.toggle('lms-hide', !fase);
  if (fase) linkEtapas.href = '/boticario/admin/etapas?f=' + fase.id;
  const fx = document.getElementById('fm-form-excluir');
  fx.classList.toggle('lms-hide', !fase);
  if (fase) {
    document.getElementById('fm-id-excluir').value = fase.id;
    fx.onsubmit = (e) => {
      e.preventDefault();
      window.lmsConfirmar(`Excluir a fase "${fase.nome}"?`,
        `Apaga ${fase.etapas} etapa(s), o progresso dos alunos e as conversas.\nNão tem volta.`,
        { perigo: true }).then((ok) => { if (ok) fx.submit(); });
      return false;
    };
  }
  mudaDesb();
  previewPos();
  modal.classList.remove('lms-hide'); fundo.classList.remove('lms-hide');
  document.getElementById('fm-nome').focus();
}
function fechaModal() {
  modal.classList.add('lms-hide'); fundo.classList.add('lms-hide');
  document.getElementById('ed-fantasma')?.remove();
  if (faseAberta) {                       // devolve o pin à posição salva
    const pin = document.querySelector(`.ed-pin[data-id="${faseAberta.id}"]`);
    if (pin) { pin.style.left = faseAberta.x + '%'; pin.style.top = faseAberta.y + '%'; }
  }
  faseAberta = null;
  document.querySelectorAll('.ed-item.is-alvo').forEach(i => i.classList.remove('is-alvo'));
}
document.getElementById('fm-fechar').addEventListener('click', fechaModal);
fundo.addEventListener('click', fechaModal);
document.addEventListener('keydown', (ev) => {
  if (ev.key === 'Escape' && !modal.classList.contains('lms-hide')) fechaModal();
});

/* posição digitada → o pin (ou um fantasma, se a fase é nova) anda no
   mapa ao vivo, para o admin acertar o ponto sem tentativa e erro */
function previewPos() {
  const x = Math.max(0, Math.min(100, +campoX.value || 0));
  const y = Math.max(0, Math.min(100, +campoY.value || 0));
  if (faseAberta) {
    const pin = document.querySelector(`.ed-pin[data-id="${faseAberta.id}"]`);
    if (pin) { pin.style.left = x + '%'; pin.style.top = y + '%'; pin.classList.add('is-drag'); }
  } else if (mapa) {
    let f = document.getElementById('ed-fantasma');
    if (!f) {
      f = document.createElement('div');
      f.id = 'ed-fantasma';
      f.className = 'ed-pin is-fantasma';
      f.textContent = '+';
      mapa.appendChild(f);
    }
    f.style.left = x + '%';
    f.style.top = y + '%';
  }
}
[campoX, campoY].forEach(c => c.addEventListener('input', previewPos));
document.getElementById('fm-centro').addEventListener('click', () => {
  campoX.value = '50.0'; campoY.value = '50.0'; previewPos();
});

function mudaDesb() {
  document.getElementById('fm-valor-data').classList.toggle('lms-hide', campoDesb.value !== 'data');
  document.getElementById('fm-valor-xp').classList.toggle('lms-hide', campoDesb.value !== 'xp');
}
campoDesb.addEventListener('change', mudaDesb);

/* o valor do desbloqueio vai num único hidden, conforme o tipo */
document.getElementById('fm-form').addEventListener('submit', () => {
  const t = campoDesb.value;
  document.getElementById('fm-valor').value =
    t === 'data' ? document.getElementById('fm-data').value :
    t === 'xp'   ? document.getElementById('fm-xp').value : '';
});

/* ── pins: desenhar, arrastar, clicar ─────────────────────── */
if (mapa) {
  FASES.forEach(f => {
    const pin = document.createElement('button');
    pin.type = 'button';
    pin.className = 'ed-pin' + (f.etapas === 0 ? ' sem-etapas' : '');
    pin.dataset.id = f.id;
    pin.style.left = f.x + '%';
    pin.style.top = f.y + '%';
    pin.innerHTML = `${f.ordem}<span class="ed-tip">${f.nome}${f.etapas === 0 ? ' · sem etapas' : ''}</span>`;

    // passar o mouse no pin realça o cartão dele no painel (e vice-versa)
    const cartao = () => document.querySelector(`.ed-item[data-id="${f.id}"]`);
    pin.addEventListener('mouseenter', () => cartao()?.classList.add('is-alvo'));
    pin.addEventListener('mouseleave', () => cartao()?.classList.remove('is-alvo'));
    cartao()?.addEventListener('mouseenter', () => pin.classList.add('is-drag'));
    cartao()?.addEventListener('mouseleave', () => { if (faseAberta?.id !== f.id) pin.classList.remove('is-drag'); });

    let drag = null;
    pin.addEventListener('pointerdown', (ev) => {
      ev.preventDefault();
      pin.setPointerCapture(ev.pointerId);
      drag = { moveu: false, x0: ev.clientX, y0: ev.clientY };
    });
    pin.addEventListener('pointermove', (ev) => {
      if (!drag) return;
      if (!drag.moveu && Math.hypot(ev.clientX - drag.x0, ev.clientY - drag.y0) < 5) return;
      drag.moveu = true;
      pin.classList.add('is-drag');
      const p = pct(ev);
      pin.style.left = p.x + '%';
      pin.style.top = p.y + '%';
      drag.p = p;
      // modal aberto nesta fase: os números acompanham o arrasto
      if (faseAberta?.id === f.id) {
        campoX.value = p.x.toFixed(1);
        campoY.value = p.y.toFixed(1);
      }
    });
    pin.addEventListener('pointerup', async (ev) => {
      pin.releasePointerCapture(ev.pointerId);
      const foiDrag = drag && drag.moveu;
      const p = drag && drag.p;
      drag = null;
      if (faseAberta?.id !== f.id) pin.classList.remove('is-drag');
      if (foiDrag && p) {
        // com o modal aberto, quem salva é o formulário (evita gravar 2x)
        if (faseAberta?.id === f.id) return;
        const body = new URLSearchParams({ acao: 'f_pin', treinamento_id: TID, id: f.id, x: p.x.toFixed(2), y: p.y.toFixed(2) });
        try { await fetch('/boticario/admin/mapa?t=' + TID, { method: 'POST', body }); f.x = p.x; f.y = p.y; }
        catch (e) { alert('Não consegui salvar a posição — tente de novo.'); }
      } else {
        abreModal(f);
      }
    });

    mapa.appendChild(pin);
  });

  /* clique em área vazia do mapa → criar fase ali */
  mapa.addEventListener('click', (ev) => {
    if (ev.target.closest('.ed-pin')) return;
    const p = pct(ev);
    abreModal(null, p.x.toFixed(1), p.y.toFixed(1));
  });

  /* "+ Nova fase": nasce no centro e o admin ajusta pelos números
     (ou arrastando o fantasma depois de salvar) */
  document.getElementById('btn-nova-fase')?.addEventListener('click', () => abreModal(null, 50, 50));

  /* ✏️ do painel abre a mesma fase do pin */
  document.querySelectorAll('[data-editar]').forEach(b =>
    b.addEventListener('click', () => {
      const f = FASES.find(x => x.id === +b.dataset.editar);
      if (f) abreModal(f);
    }));

  /* mira segue o mouse (feedback de "vai criar aqui") */
  mapa.addEventListener('pointermove', (ev) => {
    if (ev.target.closest('.ed-pin')) { mira.classList.add('lms-hide'); return; }
    const p = pct(ev);
    mira.style.left = p.x + '%';
    mira.style.top = p.y + '%';
    mira.classList.remove('lms-hide');
  });
  mapa.addEventListener('pointerleave', () => mira.classList.add('lms-hide'));
}
</script>
</body></html>
