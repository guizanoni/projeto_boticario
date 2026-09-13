<?php
// Boticário LMS (V3) · Mapa da trilha em TELA CHEIA — o cenário É a
// interface. A arte vem da TRILHA do treinamento ativo do aluno; os
// pins são as FASES desse treinamento (posição livre definida pelo
// admin). Desbloqueio por fase: sequência, data ou XP.
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate(['aluno']);
$j = lms_jornada($u);
$fases = $j['fases'];
$temTreinamento = (bool) $j['treinamento'];

// próxima ação do aluno (CTA): primeira fase disponível não concluída
$proxima = null;
foreach ($fases as $f) if ($f['estado'] === 'available') { $proxima = $f; break; }

$totalEtapas = array_sum(array_column($fases, 'total'));
$feitas      = array_sum(array_column($fases, 'feitas'));
$pct         = lms_pct($feitas, $totalEtapas);

// próxima fase bloqueada (para informar o motivo quando não há CTA)
$bloqueada = null;
foreach ($fases as $f) if ($f['estado'] === 'locked') { $bloqueada = $f; break; }

$fasesJs = array_map(fn($f) => [
    'id' => (int) $f['id'], 'nome' => $f['nome'], 'ordem' => (int) $f['ordem'],
    'x' => (float) $f['pin_x'], 'y' => (float) $f['pin_y'],
    'estado' => $f['estado'], 'motivo' => $f['motivo_bloqueio'],
    'total' => $f['total'], 'feitas' => $f['feitas'],
], $fases);

// ciclo do dia (horário de São Paulo) — ?periodo= força um bloco (demo)
$periodo = $_GET['periodo'] ?? lms_periodo_atual();
if (!isset(lms_periodos()[$periodo])) $periodo = lms_periodo_atual();
$pdia = lms_periodos()[$periodo];

$mapaUrl   = $temTreinamento && $u['cenario_mapa_url'] ? lms_mapa_do_periodo($u['cenario_mapa_url'], $periodo, (int) $u['trilha_id']) : null;
$avatarImg = lms_avatar_mapa($u['avatar_genero'], $u['avatar_cor_pele'], (int) $u['trilha_id']);

lms_head('Mapa da jornada', $u);
lms_topbar($u);

// ── sem treinamento ativo (ou trilha sem mapa): estado de espera ──
if (!$mapaUrl) {
?>
<main class="lms-container lms-center" style="padding-top:80px; padding-bottom:80px">
  <div class="lms-card-game lms-center lms-stack-2" style="max-width:460px; margin:0 auto; padding:44px 36px">
    <div style="font-size:56px">🧭</div>
    <h1 style="font-size:var(--lms-title-md); margin-top:10px">Sua jornada está sendo preparada</h1>
    <p class="lms-small lms-muted" style="line-height:1.6; margin-top:8px">
      <?= $temTreinamento
          ? 'O mapa desta trilha ainda não foi publicado. Volte em breve!'
          : 'Você ainda não tem um treinamento ativo. Assim que a equipe atribuir o seu, o mapa aparece aqui.' ?>
    </p>
  </div>
</main>
</body></html>
<?php exit; } ?>

<!-- ── O MAPA EM TELA CHEIA ─────────────────────────────── -->
<main id="mapa-hero">
  <!-- canvas: cobre o viewport preservando a proporção da arte,
       cenário centralizado; pins e avatar usam % do canvas -->
  <div id="mapa" class="mapa-canvas">
    <img src="<?= e($mapaUrl) ?>" alt="Mapa da trilha <?= e($u['trilha_nome']) ?>" id="mapa-img"
         draggable="false" style="display:block; width:100%; height:100%; user-select:none; -webkit-user-drag:none">
    <div class="avatar" id="avatar" aria-label="Seu avatar no mapa">
      <img src="<?= e($avatarImg) ?>" alt="Seu avatar" class="avatar-img" id="avatar-img">
    </div>
  </div>

  <!-- painel flutuante: trilha + treinamento + progresso -->
  <div class="flutua-canto">
    <div class="flutua flutua-info">
      <h1 style="font-size:20px"><?= e($u['trilha_nome']) ?></h1>
      <p class="lms-small lms-muted" style="margin-top:2px">📁 <?= e($j['treinamento']['nome']) ?></p>
      <div class="lms-row" style="gap:10px; margin-top:10px; flex-wrap:nowrap">
        <div class="lms-progress" style="height:10px; flex:1"><div class="lms-progress-fill" style="width:<?= $pct ?>%"></div></div>
        <span class="lms-small" style="font-weight:700"><?= $pct ?>%</span>
      </div>
      <?php if (!empty($u['treinamento_id_2'])): ?>
      <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/escolher" style="margin-top:12px; width:100%; justify-content:center">🗺️ Trocar trilha</a>
      <?php endif; ?>
    </div>

    <?php
    // simulador de período (só admin) — mostra apenas os horários que TÊM foto
    // configurada; some se sobrar menos de 2 (não há o que simular).
    if ($u['papel'] === 'admin'):
      $periodosArte = array_filter(lms_periodos(), fn($k) => lms_periodo_tem_arte($u['cenario_mapa_url'] ?? '', $k, (int) ($u['trilha_id'] ?? 0)), ARRAY_FILTER_USE_KEY);
      if (count($periodosArte) >= 2): ?>
    <div class="flutua-simulador">
      <?php foreach ($periodosArte as $chave => $p): $ativo = $chave === $periodo; ?>
      <a class="sim-btn <?= $ativo ? 'is-ativo' : '' ?>"
         href="/boticario/mapa<?= $ativo ? '' : '?periodo=' . e($chave) ?>"
         title="Simular: <?= e($p['nome']) ?><?= $ativo ? ' (atual)' : '' ?>"><?= $p['emoji'] ?></a>
      <?php endforeach; ?>
    </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- CTA flutuante: próxima ação -->
  <?php if ($proxima): ?>
  <a class="flutua flutua-cta" href="/boticario/fase?id=<?= (int) $proxima['id'] ?>">
    <span class="lms-small lms-muted" style="display:block">Sua próxima missão · Fase <?= (int) $proxima['ordem'] ?></span>
    <strong style="font-family:var(--lms-font-ui)"><?= e($proxima['nome']) ?></strong>
    <span class="lms-btn lms-btn-game lms-btn-sm" style="margin-left:12px"><?= $proxima['feitas'] > 0 ? 'Continuar' : 'Começar' ?> ▶</span>
  </a>
  <?php elseif ($totalEtapas > 0 && $feitas >= $totalEtapas): ?>
  <div class="flutua flutua-cta" style="cursor:default">
    🏆 <strong style="font-family:var(--lms-font-ui)">Treinamento completo, <?= e(explode(' ', $u['nome'])[0]) ?>!</strong>
    <a class="lms-btn lms-btn-outline lms-btn-sm" style="margin-left:12px" href="/boticario/perfil">Ver conquistas</a>
  </div>
  <?php elseif ($bloqueada): ?>
  <div class="flutua flutua-cta" style="cursor:default">
    🔒 <strong style="font-family:var(--lms-font-ui)"><?= e($bloqueada['nome']) ?></strong>
    <span class="lms-small lms-muted" style="margin-left:8px"><?= e($bloqueada['motivo_bloqueio']) ?></span>
  </div>
  <?php else: ?>
  <!-- treinamento ainda sem fases publicadas -->
  <div class="flutua flutua-cta" style="cursor:default">
    ⏳ <strong style="font-family:var(--lms-font-ui)">As fases deste treinamento estão sendo preparadas.</strong>
  </div>
  <?php endif; ?>

  <!-- ações flutuantes: ajuda e suporte, empilhadas sobre o relógio -->
  <div class="flutua-acoes">
    <button class="flutua flutua-btn" id="btn-ajuda" title="Como usar a plataforma">❓ <span class="flutua-btn-lbl">Como usar</span></button>
    <button class="flutua flutua-btn" id="btn-suporte" title="Suporte: abrir chamado ou reportar bug">🎧 <span class="flutua-btn-lbl">Suporte</span></button>
  </div>

  <!-- detalhe: relógio de bordo (o cenário acompanha o horário de Brasília) -->
  <div class="flutua flutua-relogio" title="<?= e($pdia['nome']) ?> — o cenário acompanha o horário de Brasília">
    <?= $pdia['emoji'] ?> <?= e(date('H:i')) ?>
  </div>

</main>

<!-- ── modal AJUDA (ponto de vista do aluno) ─────────────── -->
<div id="ajuda-fundo" class="lms-hide modal-fundo"></div>
<div id="ajuda-modal" class="lms-hide modal-caixa">
  <div class="lms-row" style="justify-content:space-between; margin-bottom:6px">
    <h3>❓ Como usar a plataforma</h3>
    <button class="lms-btn lms-btn-outline lms-btn-sm" data-fecha="ajuda">Fechar ✕</button>
  </div>
  <div class="lms-stack-2" style="font-size:14.5px; line-height:1.55">
    <p>🗺️ <strong>O mapa é a sua jornada.</strong> Cada pin é uma fase do seu treinamento.</p>
    <p>🔵 <strong>Pins azuis com “!”</strong> estão liberados — clique e seu avatar caminha até lá para jogar.</p>
    <p>🔒 <strong>Cadeados</strong> são fases futuras: abrem ao concluir a anterior, numa data marcada ou ao juntar XP.</p>
    <p>✅ <strong>Verde</strong> significa fase concluída.</p>
    <p>🎮 Cada fase tem <strong>etapas</strong>: leituras e minigames. Complete todas para concluir a fase.</p>
    <p>⭐ Você ganha <strong>XP e badges</strong> a cada etapa vencida.</p>
    <p>💾 <strong>Seu progresso salva sozinho.</strong> Feche quando quiser — você retoma exatamente de onde parou.</p>
    <p>🌙 Repare no cenário: o mapa <strong>vive o horário real</strong> — dia, fim de tarde, noite e madrugada.</p>
  </div>
  <div class="lms-row" style="gap:10px; margin-top:18px; flex-wrap:wrap">
    <a class="lms-btn lms-btn-outline lms-btn-lg" href="/boticario/manual" style="flex:1; min-width:150px; text-align:center">📖 Manual completo</a>
    <button class="lms-btn lms-btn-primary lms-btn-lg" style="flex:1; min-width:150px" data-fecha="ajuda">Entendi, bora jogar ▶</button>
  </div>
</div>

<!-- ── modal SUPORTE (chamado ou bug → e-mail) ───────────── -->
<div id="suporte-fundo" class="lms-hide modal-fundo"></div>
<div id="suporte-modal" class="lms-hide modal-caixa">
  <div class="lms-row" style="justify-content:space-between; margin-bottom:6px">
    <h3>🎧 Falar com o suporte</h3>
    <button class="lms-btn lms-btn-outline lms-btn-sm" data-fecha="suporte">Fechar ✕</button>
  </div>
  <div id="sp-form" class="lms-stack-4">
    <div class="lms-row" style="gap:10px">
      <button type="button" class="sp-tipo is-ativo" data-tipo="chamado" style="flex:1">🎫 Abrir chamado</button>
      <button type="button" class="sp-tipo" data-tipo="bug" style="flex:1">🐞 Reportar bug</button>
    </div>
    <div>
      <label class="lms-label" for="sp-cat">Categoria</label>
      <select class="lms-select" id="sp-cat"></select>
    </div>
    <div>
      <label class="lms-label" for="sp-assunto">Assunto</label>
      <input class="lms-input" id="sp-assunto" maxlength="150" placeholder="Resuma em uma linha">
    </div>
    <div>
      <label class="lms-label" for="sp-msg">Mensagem</label>
      <textarea class="lms-input" id="sp-msg" rows="5" maxlength="4000" placeholder="Conte o que aconteceu ou o que você precisa. Quanto mais detalhes, mais rápido a gente resolve."></textarea>
    </div>
    <p class="lms-small lms-alert is-error lms-hide" id="sp-erro" style="margin:0"></p>
    <button class="lms-btn lms-btn-primary lms-btn-lg" id="sp-enviar" style="width:100%">Enviar ✉️</button>
  </div>
  <div id="sp-ok" class="lms-hide lms-center lms-stack-2" style="padding:26px 8px">
    <div style="font-size:52px">📨</div>
    <h3>Enviado!</h3>
    <p class="lms-small lms-muted">Recebemos sua mensagem e vamos retornar no seu e-mail.</p>
    <button class="lms-btn lms-btn-outline" data-fecha="suporte" style="margin-top:8px">Voltar ao mapa</button>
  </div>
</div>

<style>
  /* mapa ocupa tudo abaixo do topbar; sem scroll (altura exata via JS) */
  html, body { height:100% }
  #mapa-hero {
    position:relative; overflow:hidden;
    height:calc(100dvh - 73px);
    height:calc(100dvh - var(--topo-alt, 73px));
    /* céu e mar no tom da arte DO PERÍODO — cobre as sobras do crop */
    background:<?= $pdia['ceu'] ?>;
  }
  .mapa-canvas {
    position:absolute; left:50%; top:50%; transform:translate(-50%, -50%);
    aspect-ratio: 1024 / 572;
    min-width:100%; min-height:100%;
  }

  /* painéis flutuantes (vidro) */
  .flutua {
    position:absolute; z-index:12;
    background:var(--lms-flutua-bg, rgba(255,255,253,.88)); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);
    border:var(--lms-border-game); border-radius:var(--lms-radius-lg, 16px);
    box-shadow:var(--lms-shadow-crisp); padding:16px 20px;
  }
  .flutua-canto { position:absolute; top:18px; left:18px; z-index:12;
    display:flex; flex-direction:column; gap:9px; align-items:flex-start; max-width:320px }
  .flutua-info { position:static }
  .flutua-relogio {
    right:16px; bottom:16px; padding:6px 13px; border-radius:999px;
    font-family:var(--lms-font-ui); font-weight:600; font-size:12.5px;
    opacity:.88; cursor:default;
  }

  /* simulador de período (admin) — emojis discretos sob o painel de progresso */
  .flutua-simulador { display:flex; gap:5px; padding-left:5px }
  .sim-btn {
    width:28px; height:28px; border-radius:50%; text-decoration:none;
    display:flex; align-items:center; justify-content:center; font-size:13px;
    background:var(--lms-flutua-bg, rgba(255,255,253,.72)); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
    border:1.5px solid rgba(30,37,67,.22); opacity:.9;
    transition:transform .12s var(--lms-ease, ease), opacity .12s ease;
  }
  .sim-btn:hover { transform:scale(1.18); opacity:1 }
  .sim-btn.is-ativo { background:var(--lms-heading, #2A4971); border-color:var(--lms-heading, #2A4971); opacity:1 }

  /* ações flutuantes (ajuda + suporte) sobre o relógio */
  .flutua-acoes { position:absolute; right:16px; bottom:56px; display:flex; flex-direction:column; gap:8px; align-items:flex-end; z-index:12 }
  /* pills no padrão do dock (Manual / Reportar bug) — não mais bolinhas */
  .flutua-btn {
    position:static; width:auto; padding:8px 14px; border-radius:999px;
    display:inline-flex; align-items:center; gap:6px; cursor:pointer;
    font-family:var(--lms-font-ui); font-weight:700; font-size:12.5px; color:var(--lms-heading, #2A4971);
    transition:transform .15s var(--lms-ease, ease);
  }
  .flutua-btn:hover { transform:scale(1.05) }

  /* modais locais (ajuda / suporte) */
  .modal-fundo { position:fixed; inset:0; background:rgba(30,37,67,.45); z-index:40 }
  .modal-caixa {
    position:fixed; left:50%; top:50%; transform:translate(-50%,-50%);
    width:min(520px, calc(100vw - 32px)); max-height:86vh; overflow:auto; z-index:41;
    background:var(--lms-bg); border:var(--lms-border-game);
    border-radius:var(--lms-radius-xl); box-shadow:var(--lms-shadow-crisp);
    padding:26px;
  }
  .sp-tipo {
    padding:12px 10px; font-family:var(--lms-font-ui); font-weight:700; font-size:14px;
    background:var(--lms-white, #fff); border:var(--lms-border-game);
    border-radius:var(--lms-radius-md); cursor:pointer;
  }
  .sp-tipo.is-ativo { background:var(--lms-sky, #D3ECF9); box-shadow:var(--lms-shadow-crisp-sm) }
  .flutua-cta {
    bottom:20px; left:50%; transform:translateX(-50%);
    display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:center;
    text-decoration:none; color:inherit; max-width:min(92vw, 640px); text-align:center;
    transition:transform .15s var(--lms-ease, ease);
  }
  a.flutua-cta:hover { transform:translateX(-50%) scale(1.03) }

  /* ── mobile: painéis compactos, mapa inteiro respirando ── */
  @media (max-width: 700px) {
    .flutua { padding:10px 14px }
    .flutua-canto { top:10px; left:10px; max-width:min(72vw, 250px); gap:6px }
    .flutua-info h1 { font-size:15px }
    .flutua-info .lms-small { font-size:11.5px }
    .flutua-cta {
      bottom:12px; max-width:calc(100vw - 20px); width:calc(100vw - 20px);
      padding:10px 14px; gap:6px; font-size:13.5px;
    }
    .flutua-cta .lms-small { font-size:11px }
    .flutua-cta .lms-btn { margin-left:0 !important; padding:7px 14px; font-size:12.5px }
    /* ❓/🎧 sobem para não brigar com o CTA; relógio sai (é só detalhe) */
    .flutua-acoes { right:10px; bottom:96px; gap:6px }
    .flutua-btn { padding:9px 11px }
    .flutua-btn-lbl { display:none }
    .flutua-relogio { display:none }
    .sim-btn { width:26px; height:26px; font-size:12px }
  }

  /* O 📖 Manual é chrome global (fica no rodapé esquerdo em todas as telas),
     mas aqui o CTA vira uma barra que ocupa ~113px do rodapé inteiro — só
     nesta tela a pílula sobe para passar por cima dele. Corte em 860px, e
     não 700px: até lá o CTA centralizado ainda alcança o canto esquerdo. */
  @media (max-width: 860px) {
    .lms-dock { bottom:124px }
  }

  /* pin de fase: marcador clicável sobre o cenário.
     Contraste sobre a arte clara: anel branco + sombra forte em todos;
     concluído = verde sólido com halo (ênfase); bloqueado = navy escuro. */
  .hotspot {
    position:absolute; transform:translate(-50%, -50%);
    width:36px; height:36px; border-radius:50%;
    border:3px solid #fff;
    background:var(--lms-white, #fff); cursor:pointer; z-index:6;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 1.5px rgba(30,37,67,.35);
    padding:0; font-family:var(--lms-font-ui); font-weight:800; font-size:15px;
    transition:transform var(--lms-fast, .15s) var(--lms-ease, ease);
  }
  /* hover/shake sobem o pin inteiro: o tooltip nunca fica atrás
     de outros marcadores nem do avatar */
  .hotspot:hover, .hotspot.is-shake { z-index:20; }
  .hotspot:hover { transform:translate(-50%, -50%) scale(1.18); }

  .hotspot.is-available { background:var(--lms-primary, #1CAE8D); color:#fff; animation:hs-pulsa 2s ease-in-out infinite; }

  .hotspot.is-done {
    background:var(--lms-success, #2E7D32); color:#fff;
    box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 6px rgba(46,125,50,.30);
  }

  .hotspot.is-locked {
    background:rgba(43,52,80,.92); cursor:not-allowed; color:transparent;
    box-shadow:0 3px 10px rgba(16,24,40,.45);
  }
  .hotspot.is-locked::after {
    content:''; width:14px; height:14px; display:block; position:absolute;
    background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zm-3 8V7a3 3 0 1 1 6 0v3H9z'/%3E%3C/svg%3E") center/contain no-repeat;
  }

  @keyframes hs-pulsa {
    0%, 100% { box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 0 rgba(41,173,220,.6); }
    50%      { box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 14px rgba(41,173,220,0); }
  }
  .hotspot.is-shake { animation:hs-shake .5s ease }
  @keyframes hs-shake {
    0%,100% { transform:translate(-50%,-50%) }
    25% { transform:translate(calc(-50% - 4px),-50%) } 75% { transform:translate(calc(-50% + 4px),-50%) }
  }

  /* tooltip do pin */
  .hotspot-tip {
    position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
    background:var(--lms-heading, #2A4971); color:#fff; font-family:var(--lms-font-ui);
    font-size:12px; font-weight:600; white-space:nowrap; padding:5px 10px;
    border-radius:6px; opacity:0; pointer-events:none; transition:opacity .18s ease; z-index:7;
  }
  .hotspot:hover .hotspot-tip, .hotspot.is-shake .hotspot-tip { opacity:1; }

  /* avatar sobre o mapa — anel "você está aqui" + contorno branco
     para destacar o personagem da arte movimentada do cenário */
  .avatar {
    position:absolute; transform:translate(-50%, -12%); z-index:8; pointer-events:none;
    transition:top .8s ease, left .8s ease;
  }
  .avatar::after {
    content:''; position:absolute; left:50%; bottom:-2px; transform:translateX(-50%);
    width:54px; height:17px; border-radius:50%;
    border:3px solid rgba(41,173,220,.95);
    box-shadow:0 0 0 3px rgba(255,255,255,.85), inset 0 0 10px rgba(41,173,220,.4);
    animation:av-anel 2s ease-in-out infinite;
  }
  .avatar-img {
    position:relative; z-index:1; width:64px; height:auto;
    filter:drop-shadow(0 0 1.5px #fff) drop-shadow(0 0 1.5px #fff) drop-shadow(0 5px 7px rgba(30,37,67,.45));
    animation:av-flutua 3s ease-in-out infinite;
  }
  @keyframes av-flutua { 0%,100% { transform:translateY(0) } 50% { transform:translateY(-5px) } }
  @keyframes av-anel {
    0%,100% { transform:translateX(-50%) scale(1); opacity:.95 }
    50%     { transform:translateX(-50%) scale(1.15); opacity:.65 }
  }

  @media (max-width: 700px) {
    #mapa-hero { height:calc(100dvh - 66px) }
    .flutua-info { top:12px; left:12px; right:12px; max-width:none; padding:12px 16px }
    .flutua-info h1 { font-size:16px }
    .avatar-img { width:40px }
    .hotspot { width:28px; height:28px; font-size:12px }
  }
</style>

<script>
const FASES = <?= json_encode($fasesJs, JSON_UNESCAPED_UNICODE) ?>;
const AVATAR_MOVE_MS = 800;

// altura real do topbar (varia no mobile) → mapa preenche o resto exato
const ajustaAltura = () => document.documentElement.style.setProperty(
  '--topo-alt', document.querySelector('.lms-topbar').offsetHeight + 'px');
ajustaAltura();
window.addEventListener('resize', ajustaAltura);

const mapa = document.getElementById('mapa');
const avatar = document.getElementById('avatar');
let avatarBusy = false;

// posição inicial do avatar: primeira fase disponível (ou primeira, ou centro)
const inicial = FASES.find(f => f.estado === 'available') || FASES[0] || { x: 50, y: 60 };
avatar.style.left = inicial.x + '%';
avatar.style.top  = (inicial.y + 5) + '%';

// Voltar da fase pelo histórico restaura esta página do bfcache com o JS
// intacto — inclusive o avatarBusy=true da caminhada anterior, que trava
// TODOS os pins para sempre. 'pageshow' dispara no load e no restore.
window.addEventListener('pageshow', () => { avatarBusy = false; });

// pins das fases (bloqueadas ficam só com o cadeado — sem névoa,
// para a arte do cenário permanecer inteira à vista)
FASES.forEach(f => {
  const hs = document.createElement('button');
  hs.className = 'hotspot is-' + f.estado;
  hs.style.left = f.x + '%';
  hs.style.top = f.y + '%';
  if (f.estado === 'locked') hs.setAttribute('aria-disabled', 'true');
  hs.setAttribute('aria-label', f.nome);
  hs.textContent = f.estado === 'done' ? '✓' : (f.estado === 'available' ? '!' : '');

  const tip = document.createElement('span');
  tip.className = 'hotspot-tip';
  tip.textContent = `Fase ${f.ordem} · ${f.nome}` + (f.estado === 'locked' && f.motivo ? ` — ${f.motivo}` : '');
  hs.appendChild(tip);

  hs.addEventListener('click', () => {
    if (f.estado === 'locked') {
      hs.classList.add('is-shake');
      setTimeout(() => hs.classList.remove('is-shake'), 1100);
      return;
    }
    if (avatarBusy) return;
    avatarBusy = true;

    // avatar caminha até a fase; o jogo abre quando ele chega
    avatar.style.left = f.x + '%';
    avatar.style.top = (f.y + 5) + '%';
    setTimeout(() => { window.location.href = '/boticario/fase?id=' + f.id; }, AVATAR_MOVE_MS);
  });

  mapa.appendChild(hs);
});

/* ── ajuda + suporte ──────────────────────────────────────── */
const modal = (nome, aberto) => {
  document.getElementById(nome + '-fundo').classList.toggle('lms-hide', !aberto);
  document.getElementById(nome + '-modal').classList.toggle('lms-hide', !aberto);
};
document.getElementById('btn-ajuda').addEventListener('click', () => modal('ajuda', true));
document.getElementById('btn-suporte').addEventListener('click', () => {
  document.getElementById('sp-form').classList.remove('lms-hide');
  document.getElementById('sp-ok').classList.add('lms-hide');
  modal('suporte', true);
});
document.querySelectorAll('[data-fecha]').forEach(b =>
  b.addEventListener('click', () => modal(b.dataset.fecha, false)));
document.getElementById('ajuda-fundo').addEventListener('click', () => modal('ajuda', false));
document.getElementById('suporte-fundo').addEventListener('click', () => modal('suporte', false));

// categorias por tipo de solicitação
const SP_CATS = {
  chamado: ['Dúvida sobre o conteúdo', 'Acesso e senha', 'Progresso e certificado', 'Sugestão de melhoria', 'Outro assunto'],
  bug: ['Erro em uma fase ou minigame', 'Problema no mapa', 'Progresso não foi salvo', 'Problema de acesso/login', 'Outro problema'],
};
let spTipo = 'chamado';
const spCat = document.getElementById('sp-cat');
function spMontaCats() {
  spCat.innerHTML = SP_CATS[spTipo].map(c => `<option>${c}</option>`).join('');
}
spMontaCats();
document.querySelectorAll('.sp-tipo').forEach(b => b.addEventListener('click', () => {
  spTipo = b.dataset.tipo;
  document.querySelectorAll('.sp-tipo').forEach(x => x.classList.toggle('is-ativo', x === b));
  spMontaCats();
}));

document.getElementById('sp-enviar').addEventListener('click', async () => {
  const btn = document.getElementById('sp-enviar'), erro = document.getElementById('sp-erro');
  const assunto = document.getElementById('sp-assunto').value.trim();
  const mensagem = document.getElementById('sp-msg').value.trim();
  erro.classList.add('lms-hide');
  if (!assunto || !mensagem) {
    erro.textContent = 'Preencha o assunto e a mensagem.';
    erro.classList.remove('lms-hide');
    return;
  }
  btn.disabled = true; btn.textContent = 'Enviando…';
  try {
    const r = await fetch('/boticario/api/suporte', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tipo: spTipo, categoria: spCat.value, assunto, mensagem }),
    });
    const d = await r.json();
    if (d.ok) {
      document.getElementById('sp-form').classList.add('lms-hide');
      document.getElementById('sp-ok').classList.remove('lms-hide');
      document.getElementById('sp-assunto').value = '';
      document.getElementById('sp-msg').value = '';
    } else {
      erro.textContent = d.msg || 'Não deu certo. Tente de novo.';
      erro.classList.remove('lms-hide');
    }
  } catch (e) {
    erro.textContent = 'Falha de conexão — tente novamente.';
    erro.classList.remove('lms-hide');
  }
  btn.disabled = false; btn.textContent = 'Enviar ✉️';
});
</script>
</body></html>
