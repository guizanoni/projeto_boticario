<?php
// Boticário LMS (V3) · Player de FASE: etapas sequenciais (RF-04).
// A fase é o jogável — leituras e minigames montados pelo admin.
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate(['aluno', 'gestor']);   // gestor entra para responder a comunidade
$fid = (int) ($_GET['id'] ?? 0);

$fase = db_one('SELECT f.*, t.id AS trein_id, t.nome AS trein_nome, tr.nome AS trilha_nome, tr.cliente_id
                FROM fase f JOIN treinamento t ON t.id = f.treinamento_id JOIN trilha tr ON tr.id = t.trilha_id
                WHERE f.id = ?', [$fid]);
if (!$fase || (int) $fase['cliente_id'] !== (int) $u['cliente_id']) { header('Location: /boticario/mapa'); exit; }

// trava de progressão (só para alunos): a fase precisa ser do treinamento
// ativo do aluno e estar desbloqueada. Gestor/admin abrem qualquer fase.
if ($u['papel'] === 'aluno') {
    if ((int) $fase['trein_id'] !== (int) ($u['treinamento_id'] ?? 0)) { header('Location: /boticario/mapa'); exit; }
    $j = lms_jornada($u);
    foreach ($j['fases'] as $f) {
        if ((int) $f['id'] === $fid && $f['estado'] === 'locked') { header('Location: /boticario/mapa'); exit; }
    }
}

$etapas = db_all('SELECT * FROM etapa WHERE fase_id = ? ORDER BY ordem', [$fid]);
$prog = [];
foreach (db_all('SELECT * FROM progresso_usuario WHERE usuario_id = ?', [$u['id']]) as $p) $prog[$p['etapa_id']] = $p;

// Comunidade: discussão contextual da fase (threads de 1 nível).
// Moderação prévia: pendentes só aparecem para o próprio autor e para
// quem modera (gestor/admin) — o resto da equipe vê só os ativos.
$podeModerar = in_array($u['papel'], ['gestor', 'admin'], true);
$coments = db_all("SELECT c.*, u.nome AS autor, u.papel AS autor_papel, u.avatar_genero, u.avatar_cor_pele,
                          u.foto_url
                   FROM comentario c JOIN usuario u ON u.id = c.usuario_id
                   WHERE c.fase_id = ?
                     AND (c.status = 'ativo' OR (c.status = 'pendente' AND (c.usuario_id = ? OR ?)))
                   ORDER BY c.id", [$fid, $u['id'], (int) $podeModerar]);
$nPublicas = count(array_filter($coments, fn($c) => $c['status'] === 'ativo'));
$raizes = array_values(array_filter($coments, fn($c) => !$c['parent_id']));
$respostas = [];
foreach ($coments as $c) if ($c['parent_id']) $respostas[(int) $c['parent_id']][] = $c;

// "há 2 h" a partir do timestamp UTC do SQLite
function coment_quando(string $ts): string {
    $seg = max(0, time() - (new DateTime($ts, new DateTimeZone('UTC')))->getTimestamp());
    if ($seg < 60)     return 'agora mesmo';
    if ($seg < 3600)   return 'há ' . intdiv($seg, 60) . ' min';
    if ($seg < 86400)  return 'há ' . intdiv($seg, 3600) . ' h';
    return 'há ' . intdiv($seg, 86400) . ' d';
}

function coment_html(array $c, array $respostas, bool $podeModerar, bool $raiz = true): void {
    $pendente = $c['status'] === 'pendente'; ?>
  <div class="com-item <?= $c['oficial'] ? 'is-oficial' : '' ?> <?= $pendente ? 'is-pendente' : '' ?>" id="c<?= (int) $c['id'] ?>">
    <div class="com-avatar"><?= lms_avatar_html($c['avatar_genero'], $c['avatar_cor_pele'], 34, $c['foto_url'] ?? null) ?></div>
    <div class="com-corpo">
      <div class="lms-row" style="gap:8px; flex-wrap:wrap">
        <strong class="lms-small" style="color:var(--lms-heading)"><?= e($c['autor']) ?></strong>
        <?php if (in_array($c['autor_papel'], ['gestor', 'admin'], true)): ?><span class="lms-pill com-pill-equipe">🛡️ Equipe</span><?php endif; ?>
        <?php if ($c['oficial']): ?><span class="lms-pill com-pill-oficial">✅ Resposta oficial</span><?php endif; ?>
        <?php if ($pendente): ?><span class="lms-pill com-pill-pendente">⏳ Aguardando aprovação</span><?php endif; ?>
        <span class="lms-small lms-muted"><?= e(coment_quando($c['criado_em'])) ?></span>
      </div>
      <p class="com-texto"><?= nl2br(e($c['texto'])) ?></p>
      <div class="lms-row" style="gap:10px">
        <?php if ($raiz && !$pendente): ?><button type="button" class="com-acao" onclick="comResponder(<?= (int) $c['id'] ?>)">↩︎ Responder</button><?php endif; ?>
        <?php if ($podeModerar && $pendente): ?>
        <button type="button" class="com-acao is-aprova" onclick="comModerar(<?= (int) $c['id'] ?>, 'aprovar')">✔️ Aprovar</button>
        <button type="button" class="com-acao" onclick="comModerar(<?= (int) $c['id'] ?>, 'ocultar')">🚫 Recusar</button>
        <?php elseif ($podeModerar): ?>
        <button type="button" class="com-acao" onclick="comModerar(<?= (int) $c['id'] ?>, 'oficial')"><?= $c['oficial'] ? 'Remover selo' : '✅ Marcar oficial' ?></button>
        <button type="button" class="com-acao" onclick="comModerar(<?= (int) $c['id'] ?>, 'ocultar')">🙈 Ocultar</button>
        <?php endif; ?>
        <?php if ($GLOBALS['u']['papel'] === 'admin'): ?>
        <button type="button" class="com-acao is-perigo" onclick="comModerar(<?= (int) $c['id'] ?>, 'excluir')">🗑 Excluir</button>
        <?php endif; ?>
      </div>
      <?php foreach ($respostas[(int) $c['id']] ?? [] as $r) coment_html($r, $respostas, $podeModerar, false); ?>
      <?php if ($raiz): ?><div class="com-form-resposta lms-hide" id="cf<?= (int) $c['id'] ?>"></div><?php endif; ?>
    </div>
  </div>
<?php }

$etapasJs = array_map(fn($e2) => [
    'id' => (int) $e2['id'],
    'ordem' => (int) $e2['ordem'],
    'tipo' => $e2['tipo'],
    'minigame' => $e2['minigame_tipo'],
    'obrigatoria' => (bool) $e2['obrigatoria'],
    'titulo' => $e2['titulo'],
    'config' => json_decode($e2['config'], true),
    'concluida' => ($prog[$e2['id']]['status'] ?? '') === 'concluido',
    // resposta exata dada (se guardada) — permite reabrir em modo revisão
    // mostrando o que já foi respondido, sem poder mudar.
    'resposta' => isset($prog[$e2['id']]['resposta_json']) ? json_decode($prog[$e2['id']]['resposta_json'], true) : null,
], $etapas);

lms_head($fase['nome'], $u);
lms_topbar($u);
?>
<main class="lms-container" style="max-width:980px; padding-top:24px; padding-bottom:64px">

  <div class="lms-row" style="justify-content:space-between; margin-bottom:8px">
    <a class="lms-small" href="/boticario/mapa">← Voltar ao mapa</a>
    <span class="lms-pill">📁 <?= e($fase['trein_nome']) ?> · Fase <?= (int) $fase['ordem'] ?></span>
  </div>
  <h1 style="font-size:var(--lms-title-md)"><?= e($fase['nome']) ?></h1>

  <div class="pl-layout">
    <!-- trilha de etapas: espinha vertical, acompanha o scroll -->
    <aside id="pl-passos" class="pl-rail-vert"></aside>

    <div class="pl-main">
      <div id="pl-etapa"></div>

      <!-- tela final -->
      <div id="pl-final" class="lms-hide lms-center lms-stack-4"></div>

      <!-- ── Comunidade: conversa da equipe (minimizada por padrão,
           para não competir com as atividades) ── -->
      <section id="comunidade" style="margin-top:32px">
        <button type="button" class="com-toggle" id="com-toggle" aria-expanded="false">
          💬 Conversa da equipe
          <?php if ($nPublicas): ?><span class="com-toggle-n"><?= $nPublicas ?></span><?php endif; ?>
          <span class="com-toggle-seta" id="com-seta">▾</span>
        </button>

        <div class="lms-card-game lms-hide" id="com-caixa" style="margin-top:10px">
          <p class="lms-small lms-muted" style="margin:0 0 14px">
            Troque ideias, dúvidas e experiências sobre este tema com a equipe.
            As mensagens passam pela moderação — quando aprovadas, valem <strong>+15 XP</strong> por fase. ⭐
          </p>

          <form id="com-form" class="lms-stack-2">
            <textarea class="lms-input" id="com-texto" rows="2" maxlength="2000"
                      placeholder="Compartilhe uma ideia, dúvida ou experiência…"></textarea>
            <div class="lms-row" style="justify-content:flex-end">
              <button type="submit" class="lms-btn lms-btn-primary lms-btn-sm">Publicar 💬</button>
            </div>
          </form>
          <p class="lms-small lms-hide" id="com-msg" style="margin-top:6px"></p>

          <div id="com-lista" style="margin-top:14px">
            <?php if (!$raizes): ?>
            <p class="lms-small lms-muted lms-center" id="com-vazio" style="padding:10px 0">🌊 Ainda não há conversas por aqui — puxe o assunto!</p>
            <?php else: foreach ($raizes as $c) coment_html($c, $respostas, $podeModerar); endif; ?>
          </div>
        </div>
      </section>
    </div>
  </div>
</main>

<?php if ($u['papel'] === 'admin'): ?>
<!-- ── bypass do ADMIN: valida a progressão sem jogar etapa a etapa.
     Só afeta o progresso do próprio admin — alunos ficam intactos. ── -->
<div class="byp-bar">
  <span class="byp-rotulo">🛠 Admin</span>
  <button type="button" class="lms-btn lms-btn-game lms-btn-sm" id="byp-passar" title="Marca só a próxima etapa pendente como concluída para você e avança">✓ Passar etapa</button>
  <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="byp-reprovar" style="color:var(--lms-error)" title="Zera o seu progresso nesta fase (como uma reprovação) e recarrega">✕ Reprovar fase</button>
</div>
<style>
  .byp-bar {
    position:fixed; right:16px; bottom:16px; z-index:30;
    display:flex; align-items:center; gap:8px; padding:9px 14px;
    background:rgba(30,37,67,.92); backdrop-filter:blur(6px);
    border-radius:999px; box-shadow:var(--lms-shadow-crisp);
  }
  .byp-rotulo { color:#fff; font-family:var(--lms-font-ui); font-weight:700; font-size:12px;
    letter-spacing:.06em; text-transform:uppercase; opacity:.85 }
</style>
<script>
async function bypFase(acao) {
  const r = await fetch('/boticario/api/bypass', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ fase_id: <?= (int) $fase['id'] ?>, acao }),
  }).then(x => x.json()).catch(() => ({ ok: false }));
  if (!r.ok) return alert(r.msg || 'Bypass falhou.');
  // 'passar' avança uma etapa e recarrega pra mostrar a próxima — só vai
  // pro mapa quando a fase inteira já estava concluída (nada a avançar).
  if (acao === 'passar' && !r.fase_completa) window.location.reload();
  else if (acao === 'passar') window.location.href = '/boticario/mapa';
  else window.location.reload();
}
document.getElementById('byp-passar').addEventListener('click', () => bypFase('passar'));
document.getElementById('byp-reprovar').addEventListener('click', () => bypFase('reprovar'));
</script>
<?php endif; ?>

<style>
  /* ── espinha vertical de etapas: acompanha o scroll ao lado do
       conteúdo em vez da fileira horizontal de bolinhas ── */
  .pl-layout { display:flex; align-items:flex-start; gap:28px; margin-top:18px }
  .pl-rail-vert {
    flex:none; width:196px; display:flex; flex-direction:column;
    position:sticky; top:18px; max-height:calc(100vh - 36px); overflow-y:auto;
    padding:4px 10px 4px 2px; margin-left:-2px;
  }
  .pl-main { flex:1; min-width:0; max-width:760px; margin:0 auto }  /* margin auto: na tela final a espinha some e o card fica centrado */

  .pl-passo-vert { display:flex; align-items:flex-start; gap:10px; padding:5px 0; cursor:default }
  .pl-passo-num { flex:none; width:28px; height:28px; border-radius:50%; border:var(--lms-border-game);
    display:flex; align-items:center; justify-content:center;
    font-family:var(--lms-font-ui); font-weight:600; font-size:13px;
    background:var(--lms-white); color:var(--lms-ink-soft); }
  .pl-passo-vert.is-done .pl-passo-num    { background:var(--lms-success-soft); color:var(--lms-success); border-color:var(--lms-success) }
  .pl-passo-vert.is-current .pl-passo-num { background:var(--lms-sand); color:var(--lms-heading); box-shadow:var(--lms-shadow-crisp-sm) }
  .pl-passo-titulo { font-family:var(--lms-font-ui); font-size:12px; line-height:1.3; color:var(--lms-ink-soft);
    padding-top:5px; }
  .pl-passo-vert.is-current .pl-passo-titulo { color:var(--lms-heading); font-weight:700 }
  .pl-passo-vert.is-done .pl-passo-titulo { color:var(--lms-ink-soft) }
  .pl-linha-vert { width:2px; height:12px; background:var(--lms-border); margin-left:13px }
  .pl-som-vert { align-self:flex-start; margin:2px 0 10px 1px }

  /* etapas concluídas dão pra reabrir só pra rever (nunca pra mudar) */
  .pl-passo-vert.pl-passo-clicavel { cursor:pointer; border-radius:8px; margin:0 -6px; padding:5px 6px }
  .pl-passo-vert.pl-passo-clicavel:hover { background:rgba(41,173,220,.08) }
  .pl-passo-vert.pl-passo-clicavel:hover .pl-passo-titulo { color:var(--lms-primary) }
  .pl-passo-vert.is-revisando .pl-passo-num { background:var(--lms-primary); border-color:var(--lms-primary); color:#fff }
  .pl-passo-vert.is-revisando .pl-passo-titulo { color:var(--lms-primary); font-weight:700 }

  @media (max-width: 860px) {
    .pl-layout { flex-direction:column; gap:0 }
    .pl-rail-vert {
      position:static; width:100%; max-height:none; overflow:visible;
      flex-direction:row; align-items:center; gap:0; margin:0 0 20px;
      overflow-x:auto; padding:4px 2px 10px;
    }
    .pl-passo-vert { flex-direction:column; align-items:center; gap:4px; padding:0 2px; min-width:52px }
    .pl-passo-titulo { max-width:64px; text-align:center; padding-top:0;
      overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical }
    .pl-linha-vert { width:14px; height:2px; margin:0 2px 0; align-self:center }
    .pl-som-vert { align-self:center; margin:0 0 0 6px }
  }

  /* ── telas finais (aprovado / reprovado) ── */
  .fim-card { padding:44px 32px 36px; text-align:center }
  .fim-emoji {
    width:88px; height:88px; margin:0 auto; font-size:42px; line-height:1;
    background:var(--lms-white); border:var(--lms-border-game); border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    box-shadow:var(--lms-shadow-crisp-sm);
  }
  .fim-stats { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; margin-top:22px }
  .fim-stat {
    background:var(--lms-white); border:var(--lms-border-game);
    border-radius:var(--lms-radius-md); box-shadow:var(--lms-shadow-crisp-sm);
    padding:16px 26px; min-width:150px;
  }
  .fim-stat b { display:block; font-size:30px; line-height:1.15; color:var(--lms-heading) }
  .fim-stat span { font-size:12px; color:var(--lms-ink-soft); font-family:var(--lms-font-ui); font-weight:600 }
  .fim-barra {
    position:relative; height:14px; max-width:380px; margin:30px auto 0;
    background:var(--lms-white); border:2px solid var(--lms-heading); border-radius:999px;
  }
  .fim-barra-fill { height:100%; border-radius:999px; background:#E8A13B }
  .fim-meta {
    position:absolute; top:-6px; bottom:-6px; width:3px; margin-left:-2px;
    background:var(--lms-heading); border-radius:2px;
  }
  .fim-meta i {
    position:absolute; top:-20px; left:50%; transform:translateX(-50%);
    font-style:normal; font-family:var(--lms-font-ui); font-weight:700;
    font-size:10.5px; letter-spacing:.05em; text-transform:uppercase; color:var(--lms-heading);
  }
  .fim-txt { font-size:13.5px; color:var(--lms-ink-soft); max-width:430px; margin:22px auto 0; line-height:1.65 }

  .mg-carta { aspect-ratio:3/4; border:var(--lms-border-game); border-radius:var(--lms-radius-md);
    display:flex; align-items:center; justify-content:center; text-align:center; padding:8px;
    background:var(--lms-primary); color:#fff; font-size:26px; cursor:pointer; user-select:none;
    font-family:var(--lms-font-ui); transition:transform var(--lms-fast) var(--lms-ease); }
  .mg-carta:hover { transform:scale(1.04) }
  .mg-carta.is-aberta { background:var(--lms-white); color:var(--lms-ink); font-size:13px; font-weight:500; cursor:default }
  .mg-carta.is-par { background:var(--lms-success-soft); border-color:var(--lms-success); cursor:default }

  .mg-coluna-item { padding:12px 16px; border:var(--lms-border-game); border-radius:var(--lms-radius-md);
    background:var(--lms-white); cursor:pointer; font-size:15px;
    transition:transform var(--lms-fast) var(--lms-ease), background var(--lms-fast) var(--lms-ease); }
  .mg-coluna-item:hover { background:var(--lms-sky) }
  .mg-coluna-item.is-sel { background:var(--lms-sand); box-shadow:var(--lms-shadow-crisp-sm) }
  .mg-coluna-item.is-par { background:var(--lms-success-soft); border-color:var(--lms-success); cursor:default; opacity:.8 }

  /* ── comunidade ── */
  .com-toggle {
    display:flex; align-items:center; gap:8px; margin:0 auto;
    border:none; background:none; cursor:pointer; padding:8px 16px; border-radius:999px;
    font-family:var(--lms-font-ui); font-weight:600; font-size:13.5px; color:var(--lms-ink-soft);
    transition:color .15s ease, background .15s ease;
  }
  .com-toggle:hover { color:var(--lms-heading); background:rgba(41,173,220,.10) }
  .com-toggle-n {
    min-width:20px; height:20px; padding:0 6px; border-radius:999px;
    background:var(--lms-sky); color:var(--lms-heading);
    display:inline-flex; align-items:center; justify-content:center; font-size:11.5px; font-weight:700;
  }
  .com-toggle-seta { font-size:11px; transition:transform .2s var(--lms-ease, ease) }
  .com-toggle.is-aberto .com-toggle-seta { transform:rotate(180deg) }
  .com-item { display:flex; gap:10px; padding:12px 0; border-top:1px solid var(--lms-border) }
  .com-item .com-item { border-top:none; padding:10px 0 0; }
  .com-item.is-oficial > .com-corpo > .com-texto { background:var(--lms-success-soft);
    border-radius:var(--lms-radius-sm); padding:8px 10px; }
  .com-avatar { flex:none }
  .com-corpo { flex:1; min-width:0 }
  .com-texto { margin:4px 0 6px; font-size:14px; line-height:1.55; overflow-wrap:break-word }
  .com-pill-equipe  { background:var(--lms-sky);  font-size:11px; padding:2px 8px }
  .com-pill-oficial { background:var(--lms-success-soft); color:var(--lms-success); font-size:11px; padding:2px 8px }
  .com-pill-pendente { background:var(--lms-sand); color:#8A6D1B; font-size:11px; padding:2px 8px }
  .com-item.is-pendente > .com-corpo { opacity:.78 }
  .com-acao.is-aprova { color:var(--lms-success); font-weight:700 }
  .com-acao.is-perigo:hover { color:var(--lms-error) }
  .com-acao { border:none; background:none; padding:0; cursor:pointer; font-size:12px;
    color:var(--lms-ink-soft); font-family:var(--lms-font-ui); }
  .com-acao:hover { color:var(--lms-primary); text-decoration:underline }
  .com-form-resposta { margin-top:8px }
</style>

<script>
const FASE = { id: <?= (int) $fase['id'] ?>, nome: <?= json_encode($fase['nome'], JSON_UNESCAPED_UNICODE) ?> };
const ETAPAS = <?= json_encode($etapasJs, JSON_UNESCAPED_UNICODE) ?>;
const EU = <?= json_encode(['nome' => $u['nome'], 'papel' => $u['papel'], 'avatar' => lms_avatar_html($u['avatar_genero'] ?? null, $u['avatar_cor_pele'] ?? null, 34, $u['foto_url'] ?? null)], JSON_UNESCAPED_UNICODE) ?>;

/* ── comunidade ── */
const comToggle = document.getElementById('com-toggle');
function comAbrir(abre) {
  document.getElementById('com-caixa').classList.toggle('lms-hide', !abre);
  comToggle.classList.toggle('is-aberto', abre);
  comToggle.setAttribute('aria-expanded', abre);
}
comToggle.addEventListener('click', () =>
  comAbrir(document.getElementById('com-caixa').classList.contains('lms-hide')));
if (location.hash === '#comunidade') {
  comAbrir(true);
  setTimeout(() => document.getElementById('comunidade').scrollIntoView({ behavior: 'smooth' }), 150);
}

async function comEnviar(texto, parentId, form) {
  const msg = document.getElementById('com-msg');
  msg.classList.add('lms-hide');
  const r = await fetch('/boticario/api/comentario', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ acao: 'criar', fase_id: FASE.id, texto, parent_id: parentId || null }),
  }).then(x => x.json()).catch(() => ({ ok: false, msg: 'Falha de conexão — tente de novo.' }));
  if (!r.ok) { msg.textContent = '⚠️ ' + (r.msg || 'Não foi possível publicar.'); msg.classList.remove('lms-hide'); return false; }

  const esc = s => s.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const pendente = r.status === 'pendente';
  const bloco = document.createElement('div');
  bloco.className = 'com-item' + (pendente ? ' is-pendente' : '');
  bloco.innerHTML = `<div class="com-avatar">${EU.avatar}</div>
    <div class="com-corpo">
      <div class="lms-row" style="gap:8px"><strong class="lms-small" style="color:var(--lms-heading)">${esc(EU.nome)}</strong>
      ${pendente ? '<span class="lms-pill com-pill-pendente">⏳ Aguardando aprovação</span>' : ''}
      <span class="lms-small lms-muted">agora mesmo</span></div>
      <p class="com-texto">${esc(texto).replace(/\n/g, '<br>')}</p></div>`;
  if (parentId) document.querySelector(`#c${parentId} > .com-corpo`).insertBefore(bloco, document.getElementById('cf' + parentId));
  else {
    document.getElementById('com-vazio')?.remove();
    document.getElementById('com-lista').prepend(bloco);
  }
  if (form) form.remove();
  if (pendente) {
    msg.innerHTML = '🕓 Mensagem enviada! Ela passa pela moderação e aparece para toda a equipe assim que for aprovada — aí sim vêm os <strong>+15 XP</strong>. ⭐';
    msg.classList.remove('lms-hide');
  }
  return true;
}

document.getElementById('com-form').addEventListener('submit', async e => {
  e.preventDefault();
  const campo = document.getElementById('com-texto');
  if (!campo.value.trim()) return;
  if (await comEnviar(campo.value.trim(), null, null)) campo.value = '';
});

function comResponder(id) {
  const host = document.getElementById('cf' + id);
  host.classList.remove('lms-hide');
  if (host.children.length) { host.querySelector('textarea').focus(); return; }
  host.innerHTML = `<textarea class="lms-input" rows="2" maxlength="2000" placeholder="Escreva sua resposta…"></textarea>
    <div class="lms-row" style="justify-content:flex-end; gap:8px; margin-top:6px">
      <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" onclick="this.closest('.com-form-resposta').innerHTML=''">Cancelar</button>
      <button type="button" class="lms-btn lms-btn-primary lms-btn-sm">Responder ↩︎</button>
    </div>`;
  host.querySelector('.lms-btn-primary').addEventListener('click', async () => {
    const t = host.querySelector('textarea').value.trim();
    if (t) await comEnviar(t, id, host);
  });
  host.querySelector('textarea').focus();
}

async function comModerar(id, acao) {
  if (acao === 'ocultar' && !(await window.lmsConfirmar('Ocultar esta mensagem?', 'Fica escondida pra toda a equipe.'))) return;
  if (acao === 'excluir' && !(await window.lmsConfirmar('Excluir esta mensagem definitivamente?', 'As respostas vão junto. Não tem volta.', { perigo: true }))) return;
  const r = await fetch('/boticario/api/comentario', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ acao, comentario_id: id }),
  }).then(x => x.json()).catch(() => ({ ok: false }));
  if (r.ok) { location.hash = 'comunidade'; location.reload(); }
  else alert(r.msg || 'Não foi possível concluir a ação.');
}
</script>
<script src="/boticario/assets/js/player.js?v=<?= filemtime(__DIR__ . '/assets/js/player.js') ?>"></script>
</body></html>
