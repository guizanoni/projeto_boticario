<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Times (RF-07b). Visão de TODAS as equipes para
// admin/RH: conformidade média e distribuição de engajamento por
// gestor. Escala com muitos gestores (busca no topo). Clicar num
// nível de engajamento abre um popup com os alunos daquele nível;
// "Ver dashboard do time" abre o dashboard filtrado naquele gestor.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['gestor', 'admin']);
if ($u['papel'] !== 'admin') { header('Location: /boticario/gestor/'); exit; } // só admin/RH
$cli = (int) $u['cliente_id'];

// total de etapas por treinamento ativo (base do % individual)
$totPorTrein = [];
foreach (db_all("SELECT f.treinamento_id AS tid, COUNT(*) AS n
                 FROM fase f JOIN treinamento t ON t.id = f.treinamento_id JOIN trilha tr ON tr.id = t.trilha_id
                 JOIN etapa e ON e.fase_id = f.id
                 WHERE tr.cliente_id = ? AND t.status = 'ativo' GROUP BY f.treinamento_id", [$cli]) as $r) {
    $totPorTrein[(int) $r['tid']] = (int) $r['n'];
}

$gestores = db_all("SELECT id, nome, avatar_genero, avatar_cor_pele, foto_url
                    FROM usuario WHERE cliente_id = ? AND papel = 'gestor' ORDER BY nome", [$cli]);

$alunos = db_all("SELECT us.id, us.nome, us.email, us.gestor_id, us.treinamento_id, us.ultimo_login, us.acessos,
                    (SELECT COUNT(*) FROM progresso_usuario p JOIN etapa e ON e.id = p.etapa_id JOIN fase f ON f.id = e.fase_id
                     WHERE p.usuario_id = us.id AND p.status = 'concluido' AND f.treinamento_id = us.treinamento_id) AS feitas,
                    (SELECT COUNT(*) FROM comentario c WHERE c.usuario_id = us.id AND c.status = 'ativo') AS n_coment
                  FROM usuario us WHERE us.cliente_id = ? AND us.papel = 'aluno' AND us.gestor_id IS NOT NULL", [$cli]);

// agrega por gestor: nº, soma de conformidade e baldes de engajamento (com alunos)
$team = [];
foreach ($gestores as $g) $team[(int) $g['id']] = ['g' => $g, 'n' => 0, 'soma' => 0, 'alto' => [], 'medio' => [], 'baixo' => []];
$semTime = 0;
foreach ($alunos as $a) {
    $gid = (int) $a['gestor_id'];
    if (!isset($team[$gid])) { $semTime++; continue; }
    $tot = (int) ($totPorTrein[(int) $a['treinamento_id']] ?? 0);
    $pct = lms_pct((int) $a['feitas'], $tot);
    $eg  = lms_engajamento($pct, (int) $a['acessos'], (int) $a['n_coment']);
    $team[$gid]['n']++;
    $team[$gid]['soma'] += $pct;
    $team[$gid][$eg['nivel']][] = ['id' => (int) $a['id'], 'nome' => $a['nome'], 'email' => $a['email'], 'pct' => $pct];
}

// média geral (todos os times) para comparação
$somaAll = 0; $nAll = 0;
foreach ($team as $t) { $somaAll += $t['soma']; $nAll += $t['n']; }
$mediaGeral = $nAll ? (int) round($somaAll / $nAll) : 0;

// dados para o popup (JS): gestor_id => nivel => [alunos]
$popup = [];
foreach ($team as $gid => $t) $popup[$gid] = ['nome' => $t['g']['nome'], 'alto' => $t['alto'], 'medio' => $t['medio'], 'baixo' => $t['baixo']];

$engInfo = lms_engaj_info();

// roster completo p/ o editor de time (add/remover): todos os alunos do cliente,
// com o time atual de cada um. Alimenta o modal de edição (JS).
$rosterTodos = db_all("SELECT id, nome, email, gestor_id FROM usuario WHERE cliente_id = ? AND papel = 'aluno' ORDER BY nome", [$cli]);
$gestorNomes = [];
foreach ($gestores as $g) $gestorNomes[(int) $g['id']] = $g['nome'];
$editData = ['gestores' => $gestorNomes, 'alunos' => array_map(fn($a) => [
    'id' => (int) $a['id'], 'nome' => $a['nome'], 'email' => $a['email'], 'gestor_id' => $a['gestor_id'] === null ? null : (int) $a['gestor_id'],
], $rosterTodos)];

lms_head('Times', $u);
lms_topbar($u);
?>
<main class="lms-container" style="padding-top:28px; padding-bottom:64px; max-width:1200px">

  <div class="lms-row" style="justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px">
    <div>
      <h1 style="font-size:var(--lms-title-md)">🧑‍🤝‍🧑 Times</h1>
      <p class="lms-muted lms-small" style="margin-top:4px"><?= e($u['cliente_nome']) ?> · <?= count($gestores) ?> gestor<?= count($gestores) === 1 ? '' : 'es' ?> · conformidade geral <?= $mediaGeral ?>%</p>
    </div>
  </div>

  <?php lms_busca('.time-card', 'Buscar gestor / time…', count($gestores), 'times', 't-busca'); ?>

  <div class="times-grid" style="margin-top:16px">
    <?php foreach ($gestores as $g): $gid = (int) $g['id']; $t = $team[$gid];
      $conf = $t['n'] ? (int) round($t['soma'] / $t['n']) : 0;
      $nA = count($t['alto']); $nM = count($t['medio']); $nB = count($t['baixo']); $n = $t['n'];
      $delta = $conf - $mediaGeral; ?>
    <div class="lms-card-game time-card">
      <div class="time-head">
        <?= lms_avatar_html($g['avatar_genero'], $g['avatar_cor_pele'], 40, $g['foto_url'] ?? null) ?>
        <div class="time-id">
          <h3><?= e($g['nome']) ?></h3>
          <span class="time-sub"><?= $n ?> pessoa<?= $n === 1 ? '' : 's' ?></span>
        </div>
      </div>

      <?php if (!$n): ?>
      <div class="time-body"><p class="lms-small lms-muted">Ainda sem alunos neste time.</p></div>
      <?php else: ?>
      <div class="time-body">
        <!-- conformidade -->
        <div class="time-metric">
          <div class="time-metric-top">
            <span class="time-metric-lbl">Conformidade</span>
            <span class="time-delta <?= $delta >= 0 ? 'is-up' : 'is-down' ?>" title="Comparado à média geral de <?= $mediaGeral ?>%"><?= $delta >= 0 ? '▲' : '▼' ?> <?= abs($delta) ?> p.p.</span>
          </div>
          <div class="time-conf">
            <span class="time-conf-num"><?= $conf ?>%</span>
            <div class="time-bar"><div class="time-bar-fill" style="width:<?= $conf ?>%"></div></div>
          </div>
        </div>

        <!-- engajamento -->
        <div class="time-metric">
          <div class="time-metric-top">
            <span class="time-metric-lbl">Engajamento</span>
          </div>
          <div class="time-seg-bar">
            <?php if ($nA): ?><div class="time-seg-part" style="width:<?= $nA / $n * 100 ?>%; background:var(--lms-success)"></div><?php endif; ?>
            <?php if ($nM): ?><div class="time-seg-part" style="width:<?= $nM / $n * 100 ?>%; background:#E8A13C"></div><?php endif; ?>
            <?php if ($nB): ?><div class="time-seg-part" style="width:<?= $nB / $n * 100 ?>%; background:var(--lms-error)"></div><?php endif; ?>
          </div>
          <div class="time-chips">
            <?php foreach (['alto' => $nA, 'medio' => $nM, 'baixo' => $nB] as $niv => $qtd): [$lbl] = $engInfo[$niv]; ?>
            <button type="button" class="time-chip is-<?= $niv ?> time-seg" data-gestor="<?= $gid ?>" data-nivel="<?= $niv ?>" title="<?= $lbl ?>: <?= $qtd ?> — clique para ver" <?= $qtd ? '' : 'disabled' ?>>
              <span class="time-chip-dot"></span><strong><?= $qtd ?></strong>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="time-acoes">
        <button type="button" class="lms-btn lms-btn-outline lms-btn-sm time-editar" data-gestor="<?= $gid ?>">✏️ Editar</button>
        <a class="lms-btn lms-btn-primary lms-btn-sm time-ver" href="/boticario/gestor/?time=<?= $gid ?>">Ver time ▶</a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$gestores): ?>
    <p class="lms-small lms-muted">Nenhum gestor cadastrado ainda.</p>
    <?php endif; ?>
  </div>

  <?php if ($semTime): ?>
  <p class="lms-small lms-muted" style="margin-top:14px">⚠️ <?= $semTime ?> aluno<?= $semTime === 1 ? '' : 's' ?> sem time atribuído — defina o gestor em <a href="/boticario/admin/">Usuários</a>.</p>
  <?php endif; ?>
</main>

<style>
  .times-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; align-items: stretch; }
  @media (max-width: 1080px) { .times-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  @media (max-width: 820px)  { .times-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 520px)  { .times-grid { grid-template-columns: 1fr; } }

  .time-card { padding: 16px; display: flex; flex-direction: column; gap: 14px; }
  .time-head { display: flex; align-items: center; gap: 10px; }
  .time-id { flex: 1; min-width: 0; }
  .time-id h3 { font-size: 15px; color: var(--lms-heading); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .time-sub { font-size: 12px; color: var(--lms-ink-soft); }
  .time-body { display: flex; flex-direction: column; gap: 16px; flex: 1; }
  .time-metric-top { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 7px; }
  .time-metric-lbl { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--lms-ink-soft); }
  .time-delta { font-size: 11px; font-weight: 700; white-space: nowrap; }
  .time-delta.is-up { color: var(--lms-success); }
  .time-delta.is-down { color: var(--lms-error); }
  .time-conf { display: flex; align-items: center; gap: 10px; }
  .time-conf-num { font-family: var(--lms-font-ui); font-weight: 800; font-size: 26px; line-height: 1; color: var(--lms-heading); }
  .time-bar { flex: 1; height: 7px; border-radius: 999px; background: var(--lms-surface-2); overflow: hidden; }
  .time-bar-fill { height: 100%; border-radius: 999px; background: var(--lms-primary); }
  .time-seg-bar { display: flex; height: 9px; border-radius: 999px; overflow: hidden; background: var(--lms-surface-2); }
  .time-seg-part { height: 100%; }
  .time-chips { display: flex; gap: 6px; margin-top: 10px; }
  .time-chip { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--lms-border);
    background: var(--lms-surface); color: var(--lms-ink); border-radius: 999px; padding: 4px 11px;
    font-family: var(--lms-font-ui); font-size: 12.5px; font-weight: 700; cursor: pointer; transition: border-color .12s, transform .12s; }
  .time-chip:hover:not(:disabled) { transform: translateY(-1px); border-color: var(--lms-ink-faint); }
  .time-chip:disabled { opacity: .4; cursor: default; }
  .time-chip-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
  .time-chip.is-alto .time-chip-dot { background: var(--lms-success); }
  .time-chip.is-medio .time-chip-dot { background: #E8A13C; }
  .time-chip.is-baixo .time-chip-dot { background: var(--lms-error); }
  .time-acoes { display: flex; gap: 8px; margin-top: auto; }
  .time-acoes .lms-btn { flex: 1; justify-content: center; }
</style>

<!-- popup dos alunos de um nível de engajamento -->
<div id="seg-fundo" class="lms-hide" style="position:fixed; inset:0; background:rgba(30,37,67,.45); z-index:40"></div>
<div id="seg-modal" class="lms-hide" style="position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
     width:min(560px, calc(100vw - 32px)); max-height:82vh; overflow:auto; z-index:41;
     background:var(--lms-bg); border:var(--lms-border-game); border-radius:var(--lms-radius-xl);
     box-shadow:var(--lms-shadow-crisp); padding:24px">
  <div class="lms-row" style="justify-content:space-between; margin-bottom:12px">
    <h3 id="seg-titulo">—</h3>
    <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="seg-fechar">Fechar ✕</button>
  </div>
  <div id="seg-lista" class="lms-stack-3"></div>
</div>

<script>
const timesData = <?= json_encode($popup, JSON_UNESCAPED_UNICODE) ?>;
const nivelRotulo = { alto: '🟢 Alto', medio: '🟡 Médio', baixo: '🔴 Baixo' };
(function () {
  const modal = document.getElementById('seg-modal'), fundo = document.getElementById('seg-fundo');
  const titulo = document.getElementById('seg-titulo'), lista = document.getElementById('seg-lista');
  const alterna = m => [modal, fundo].forEach(el => el.classList.toggle('lms-hide', !m));
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  document.querySelectorAll('.time-seg').forEach(btn => btn.addEventListener('click', () => {
    const gid = btn.dataset.gestor, niv = btn.dataset.nivel, t = timesData[gid];
    if (!t) return;
    const alunos = t[niv] || [];
    titulo.textContent = `${t.nome} · ${nivelRotulo[niv]} (${alunos.length})`;
    lista.innerHTML = alunos.map(a => `
      <div class="lms-row" style="gap:10px; align-items:center; justify-content:space-between; border-bottom:1px dashed rgba(30,37,67,.12); padding-bottom:8px">
        <div style="min-width:0">
          <strong style="font-family:var(--lms-font-ui); font-weight:600">${esc(a.nome)}</strong><br>
          <span class="lms-muted" style="font-size:12px">${esc(a.email)} · ${a.pct}% do treinamento</span>
        </div>
        <a class="lms-btn lms-btn-outline lms-btn-sm" style="flex:none" href="/boticario/gestor/?aluno=${a.id}">Ver ▶</a>
      </div>`).join('') || '<p class="lms-small lms-muted">Ninguém neste nível.</p>';
    alterna(true);
  }));
  ['seg-fundo', 'seg-fechar'].forEach(id => document.getElementById(id).addEventListener('click', () => alterna(false)));
})();
</script>

<!-- editor de time: adicionar / remover alunos (admin) -->
<div id="ed-fundo" class="lms-hide" style="position:fixed; inset:0; background:rgba(30,37,67,.45); z-index:42"></div>
<div id="ed-modal" class="lms-hide" style="position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
     width:min(600px, calc(100vw - 32px)); max-height:86vh; overflow:auto; z-index:43;
     background:var(--lms-bg); border:var(--lms-border-game); border-radius:var(--lms-radius-xl);
     box-shadow:var(--lms-shadow-crisp); padding:24px">
  <div class="lms-row" style="justify-content:space-between; margin-bottom:4px">
    <h3 id="ed-titulo">Editar time</h3>
    <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="ed-fechar">Fechar ✕</button>
  </div>
  <p class="lms-small lms-muted" id="ed-sub" style="margin-bottom:16px">—</p>

  <p class="ed-secao">Na equipe (<span id="ed-n">0</span>)</p>
  <div id="ed-membros" class="lms-stack-2" style="margin-bottom:20px"></div>

  <p class="ed-secao">Adicionar aluno</p>
  <input type="search" class="lms-input" id="ed-busca" placeholder="🔎 Buscar aluno por nome ou e-mail…" autocomplete="off" style="margin:8px 0 10px">
  <div id="ed-candidatos" class="lms-stack-2" style="max-height:280px; overflow:auto"></div>
</div>

<style>
  .ed-secao { font-family:var(--lms-font-ui); font-weight:800; font-size:11px; letter-spacing:.05em;
    text-transform:uppercase; color:var(--lms-ink-soft); }
  .ed-linha { display:flex; align-items:center; gap:10px; justify-content:space-between;
    border:1px solid var(--lms-border); border-radius:var(--lms-radius-md); padding:9px 12px; background:var(--lms-white); }
  .ed-linha-info { min-width:0; }
  .ed-linha-nome { font-family:var(--lms-font-ui); font-weight:600; color:var(--lms-heading); font-size:13.5px; }
  .ed-linha-sub { font-size:11.5px; color:var(--lms-ink-soft); }
  .ed-tag { flex:none; font-size:10.5px; color:var(--lms-ink-faint); background:var(--lms-surface-2);
    border-radius:999px; padding:2px 8px; }
</style>

<script>
const edData = <?= json_encode($editData, JSON_UNESCAPED_UNICODE) ?>;
(function () {
  const modal = document.getElementById('ed-modal'), fundo = document.getElementById('ed-fundo');
  const titulo = document.getElementById('ed-titulo'), sub = document.getElementById('ed-sub');
  const membrosEl = document.getElementById('ed-membros'), candEl = document.getElementById('ed-candidatos');
  const buscaEl = document.getElementById('ed-busca'), nEl = document.getElementById('ed-n');
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  let gid = null, dirty = false, ocupado = false;

  const alterna = m => [modal, fundo].forEach(el => el.classList.toggle('lms-hide', !m));
  const fechar = () => { alterna(false); if (dirty) location.reload(); };

  async function chamar(acao, alunoId) {
    if (ocupado) return false;
    ocupado = true;
    try {
      const r = await fetch('/boticario/api/time', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao, aluno_id: alunoId, gestor_id: gid }),
      });
      const d = await r.json();
      if (!d.ok) { alert(d.msg || 'Não consegui salvar.'); return false; }
      const a = edData.alunos.find(x => x.id === alunoId);
      if (a) a.gestor_id = acao === 'add' ? gid : null;
      dirty = true;
      return true;
    } catch (e) { alert('Falha de conexão.'); return false; }
    finally { ocupado = false; }
  }

  function render() {
    const membros = edData.alunos.filter(a => a.gestor_id === gid);
    nEl.textContent = membros.length;
    membrosEl.innerHTML = membros.map(a => `
      <div class="ed-linha" data-id="${a.id}">
        <div class="ed-linha-info">
          <div class="ed-linha-nome">${esc(a.nome)}</div>
          <div class="ed-linha-sub">${esc(a.email)}</div>
        </div>
        <button type="button" class="lms-btn lms-btn-outline lms-btn-sm ed-remover" data-id="${a.id}" style="flex:none">✕ Remover</button>
      </div>`).join('') || '<p class="lms-small lms-muted">Ainda sem alunos neste time.</p>';

    const q = norm(buscaEl.value.trim());
    const cand = edData.alunos
      .filter(a => a.gestor_id !== gid)
      .filter(a => !q || norm(a.nome).includes(q) || norm(a.email).includes(q))
      .slice(0, 40);
    candEl.innerHTML = cand.map(a => {
      const time = a.gestor_id ? esc(edData.gestores[a.gestor_id] || 'outro time') : 'sem time';
      return `
      <div class="ed-linha" data-id="${a.id}">
        <div class="ed-linha-info">
          <div class="ed-linha-nome">${esc(a.nome)}</div>
          <div class="ed-linha-sub">${esc(a.email)}</div>
        </div>
        <span class="ed-tag">${time}</span>
        <button type="button" class="lms-btn lms-btn-primary lms-btn-sm ed-add" data-id="${a.id}" style="flex:none">+ Adicionar</button>
      </div>`;
    }).join('') || '<p class="lms-small lms-muted">Nenhum aluno encontrado.</p>';

    membrosEl.querySelectorAll('.ed-remover').forEach(b => b.addEventListener('click', async () => {
      b.disabled = true;
      if (await chamar('remove', +b.dataset.id)) render();
      else b.disabled = false;
    }));
    candEl.querySelectorAll('.ed-add').forEach(b => b.addEventListener('click', async () => {
      b.disabled = true;
      if (await chamar('add', +b.dataset.id)) render();
      else b.disabled = false;
    }));
  }

  document.querySelectorAll('.time-editar').forEach(btn => btn.addEventListener('click', () => {
    gid = +btn.dataset.gestor; dirty = false; buscaEl.value = '';
    titulo.textContent = '✏️ Editar time';
    sub.textContent = edData.gestores[gid] || '';
    render();
    alterna(true);
  }));
  buscaEl.addEventListener('input', render);
  document.getElementById('ed-fechar').addEventListener('click', fechar);
  document.getElementById('ed-fundo').addEventListener('click', fechar);
})();
</script>
</body></html>
