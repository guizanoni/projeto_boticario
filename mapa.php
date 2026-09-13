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

// Este tenant não usa o ciclo do dia (arte única do mapa): período fixo em
// "dia", sem relógio nem simulador na tela.
$periodo = 'dia';

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
<main id="mapa-hero" style="--mapa-bg:url('<?= e($mapaUrl) ?>')">
  <!-- canvas: cobre o viewport preservando a proporção da arte,
       cenário centralizado; pins e avatar usam % do canvas -->
  <div id="mapa-scroll" aria-label="Arraste para navegar pelo mapa">
  <div id="mapa" class="mapa-canvas">
    <img src="<?= e($mapaUrl) ?>" alt="Mapa da trilha <?= e($u['trilha_nome']) ?>" id="mapa-img"
         draggable="false" style="display:block; width:100%; height:100%; user-select:none; -webkit-user-drag:none">
    <div class="avatar" id="avatar" aria-label="Seu avatar no mapa">
      <img src="<?= e($avatarImg) ?>" alt="Seu avatar" class="avatar-img" id="avatar-img">
    </div>
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

  <!-- navegação do mapa: zoom e centralizar (o scroller aceita arrastar/rolar) -->
  <div class="mapa-nav" role="group" aria-label="Navegação do mapa">
    <button type="button" class="mapa-nav-btn" id="nav-mais" title="Aproximar (zoom)" aria-label="Aproximar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
    <button type="button" class="mapa-nav-btn" id="nav-menos" title="Afastar (zoom)" aria-label="Afastar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>
    <button type="button" class="mapa-nav-btn" id="nav-ajustar" title="Ver o mapa inteiro" aria-label="Ver o mapa inteiro"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button>
    <button type="button" class="mapa-nav-btn" id="nav-eu" title="Centralizar na sua fase atual" aria-label="Centralizar na sua fase atual"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg></button>
  </div>

  <!-- ações flutuantes: ajuda e suporte, empilhadas sobre o relógio -->
  <div class="flutua-acoes">
    <button class="flutua flutua-btn" id="btn-ajuda" title="Como usar a plataforma">❓ <span class="flutua-btn-lbl">Como usar</span></button>
    <button class="flutua flutua-btn" id="btn-suporte" title="Suporte: abrir chamado ou reportar bug">🎧 <span class="flutua-btn-lbl">Suporte</span></button>
  </div>

  <!-- detalhe: relógio de bordo (o cenário acompanha o horário de Brasília) -->

</main>

<!-- ── modal AJUDA (ponto de vista do aluno) ─────────────── -->
<div id="ajuda-fundo" class="lms-hide modal-fundo"></div>
<div id="ajuda-modal" class="lms-hide modal-caixa">
  <div class="ajuda-topo">
    <div>
      <p class="ajuda-kicker">Guia rápido</p>
      <h3>Como usar a plataforma</h3>
    </div>
    <button class="lms-btn lms-btn-outline lms-btn-sm" data-fecha="ajuda" aria-label="Fechar">✕</button>
  </div>
  <div class="ajuda-lista">
    <div class="ajuda-item"><span class="ajuda-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/></svg></span><div><strong>O mapa é a sua jornada.</strong> Cada pin é uma fase do treinamento.</div></div>
    <div class="ajuda-item"><span class="ajuda-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><path d="M12 7v4M12 14h.01"/></svg></span><div><strong>Pin verde com “!”</strong> é a fase liberada. Clique e seu avatar caminha até lá.</div></div>
    <div class="ajuda-item"><span class="ajuda-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span><div><strong>Cadeado</strong> é fase futura: abre ao concluir a anterior, numa data marcada ou ao juntar XP.</div></div>
    <div class="ajuda-item"><span class="ajuda-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg></span><div><strong>Selo “concluída”</strong> marca o que você já fechou. Dá para rever quando quiser.</div></div>
    <div class="ajuda-item"><span class="ajuda-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/></svg></span><div><strong>Dentro da fase</strong> vêm as etapas: leituras, vídeo e minigames. Complete todas para fechar a fase.</div></div>
    <div class="ajuda-item"><span class="ajuda-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9z"/></svg></span><div><strong>XP e conquistas</strong> chegam a cada etapa vencida. O ranking mostra a equipe.</div></div>
    <div class="ajuda-item"><span class="ajuda-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 17v3h16v-3"/></svg></span><div><strong>Seu progresso salva sozinho.</strong> Feche quando quiser e retome de onde parou.</div></div>
  </div>
  <div class="ajuda-acoes">
    <a class="lms-btn lms-btn-outline lms-btn-lg" href="/boticario/manual">Manual completo</a>
    <button class="lms-btn lms-btn-primary lms-btn-lg" data-fecha="ajuda">Entendi, bora jogar ▶</button>
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
  /* mapa ocupa tudo abaixo do topbar. O hero é a moldura (painéis
     flutuantes ficam nele); dentro, o scroller rola/arrasta e o canvas
     tem tamanho em px — "ajustado" cabe inteiro na tela (visão geral),
     zoom amplia e libera navegação. Sobras da moldura mostram o próprio
     mapa desfocado, não uma cor solta. */
  html, body { height:100% }
  #mapa-hero {
    position:relative; overflow:hidden;
    height:calc(100dvh - 73px);
    height:calc(100dvh - var(--topo-alt, 73px));
    background:#1F4D3A;
  }
  #mapa-hero::before {
    content:''; position:absolute; inset:-40px; z-index:0; pointer-events:none;
    background:var(--mapa-bg) center / cover no-repeat;
    filter:blur(26px) saturate(.85); opacity:.55;
  }
  #mapa-scroll {
    position:absolute; inset:0; z-index:1; overflow:auto; display:flex;
    cursor:grab; scrollbar-width:thin; overscroll-behavior:contain; -webkit-overflow-scrolling:touch;
  }
  #mapa-scroll.is-arrastando { cursor:grabbing; user-select:none }
  .mapa-canvas {
    position:relative; flex:none; margin:auto;   /* centra quando cabe; rola quando não cabe */
    width:1024px; height:572px;                  /* sobrescrito via JS pelo tamanho real */
    box-shadow:0 18px 50px rgba(16,24,40,.35);
  }
  .mapa-nav {
    position:absolute; left:16px; bottom:16px; z-index:13;
    display:flex; flex-direction:column; gap:6px;
  }
  .mapa-nav-btn {
    width:40px; height:40px; border-radius:12px; border:var(--lms-border-game);
    background:var(--lms-flutua-bg, rgba(255,255,253,.9)); backdrop-filter:blur(8px);
    box-shadow:var(--lms-shadow-crisp-sm); cursor:pointer; font-size:19px; line-height:1;
    font-family:var(--lms-font-ui); font-weight:800; color:var(--lms-heading);
    display:flex; align-items:center; justify-content:center; padding:0;
    transition:transform .12s var(--lms-ease, ease);
  }
  .mapa-nav-btn:hover { transform:scale(1.08) }
  .mapa-nav-btn:disabled { opacity:.4; cursor:default; transform:none }
  @media (max-width: 700px) { .mapa-nav { left:10px; bottom:96px } .mapa-nav-btn { width:36px; height:36px; font-size:17px } }

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

    width:28px; height:28px; border-radius:50%; text-decoration:none;
    display:flex; align-items:center; justify-content:center; font-size:13px;
    background:var(--lms-flutua-bg, rgba(255,255,253,.72)); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
    border:1.5px solid rgba(31,42,37,.22); opacity:.9;
    transition:transform .12s var(--lms-ease, ease), opacity .12s ease;
  }

  /* ações flutuantes (ajuda + suporte) sobre o relógio */
  .flutua-acoes { position:absolute; right:16px; bottom:56px; display:flex; flex-direction:column; gap:8px; align-items:flex-end; z-index:12 }
  /* pills no padrão do dock (Manual / Reportar bug) — não mais bolinhas */
  .flutua-btn {
    position:static; width:auto; padding:8px 14px; border-radius:999px;
    display:inline-flex; align-items:center; gap:6px; cursor:pointer;
    font-family:var(--lms-font-ui); font-weight:700; font-size:12.5px; color:var(--lms-heading, #1F2A25);
    transition:transform .15s var(--lms-ease, ease);
  }
  .flutua-btn:hover { transform:scale(1.05) }

  /* modais locais (ajuda / suporte) */
  .modal-fundo { position:fixed; inset:0; background:rgba(31,42,37,.45); z-index:40 }
  /* modal de ajuda: lista com chips de ícone, no tom do design */
  .ajuda-topo { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:14px }
  .ajuda-kicker { font-family:var(--lms-font-ui); font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--lms-primary); margin:0 0 2px }
  .ajuda-topo h3 { font-size:20px; margin:0 }
  .ajuda-lista { display:flex; flex-direction:column; gap:10px }
  .ajuda-item { display:flex; gap:12px; align-items:flex-start; font-size:14px; line-height:1.5; color:var(--lms-ink) }
  .ajuda-item strong { color:var(--lms-heading) }
  .ajuda-ico { flex:none; width:34px; height:34px; border-radius:10px; background:var(--lms-surface); color:var(--lms-primary);
    display:flex; align-items:center; justify-content:center; margin-top:1px }
  .ajuda-acoes { display:flex; gap:10px; margin-top:18px; flex-wrap:wrap }
  .ajuda-acoes > * { flex:1; min-width:150px; text-align:center }
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
    box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 1.5px rgba(31,42,37,.35);
    padding:0; font-family:var(--lms-font-ui); font-weight:800; font-size:15px;
    transition:transform var(--lms-fast, .15s) var(--lms-ease, ease);
  }
  /* hover/shake sobem o pin inteiro: o tooltip nunca fica atrás
     de outros marcadores nem do avatar */
  .hotspot:hover, .hotspot.is-shake { z-index:20; }
  .hotspot:hover { transform:translate(-50%, -50%) scale(1.18); }

  .hotspot.is-available { background:var(--lms-primary, #007A54); color:#fff; animation:hs-pulsa 2s ease-in-out infinite; }

  /* concluída = comemoração: pin maior com anel dourado que respira, ✓ grande,
     estrela balançando e selo "concluída" — entra com um pop escalonado */
  .hotspot.is-done {
    width:46px; height:46px; font-size:22px;
    background:var(--lms-success, #27A567); color:#fff; border-color:#FCCA5B;
    box-shadow:0 4px 12px rgba(16,24,40,.5), 0 0 0 5px rgba(252,202,91,.55);
    animation:hs-done-pop .7s cubic-bezier(.34,1.56,.64,1) calc(var(--i, 0) * .15s) backwards,
              hs-done-glow 2.6s ease-in-out calc(1s + var(--i, 0) * .15s) infinite;
  }
  .hotspot.is-done::before {
    content:'⭐'; position:absolute; top:-15px; right:-13px; font-size:19px; line-height:1;
    filter:drop-shadow(0 2px 3px rgba(0,0,0,.35));
    animation:hs-star 1.8s ease-in-out infinite;
  }
  .hotspot.is-done::after {
    content:'concluída'; position:absolute; top:calc(100% + 6px); left:50%; transform:translateX(-50%);
    background:#FCCA5B; color:#1F2A25; font-size:10.5px; font-weight:800; letter-spacing:.06em;
    text-transform:uppercase; padding:2px 8px; border-radius:999px; white-space:nowrap;
    box-shadow:0 2px 6px rgba(16,24,40,.3);
  }
  @keyframes hs-done-pop { 0% { transform:translate(-50%,-50%) scale(0) } 60% { transform:translate(-50%,-50%) scale(1.3) } 100% { transform:translate(-50%,-50%) scale(1) } }
  @keyframes hs-done-glow {
    0%, 100% { box-shadow:0 4px 12px rgba(16,24,40,.5), 0 0 0 5px rgba(252,202,91,.55) }
    50%      { box-shadow:0 4px 12px rgba(16,24,40,.5), 0 0 0 13px rgba(252,202,91,.10) }
  }
  @keyframes hs-star { 0%, 100% { transform:rotate(-12deg) scale(1) } 50% { transform:rotate(14deg) scale(1.3) } }

  .hotspot.is-locked {
    background:rgba(43,52,80,.92); cursor:not-allowed; color:transparent;
    box-shadow:0 3px 10px rgba(16,24,40,.45);
  }
  .hotspot.is-locked::after {
    content:''; width:14px; height:14px; display:block; position:absolute;
    background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zm-3 8V7a3 3 0 1 1 6 0v3H9z'/%3E%3C/svg%3E") center/contain no-repeat;
  }

  @keyframes hs-pulsa {
    0%, 100% { box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 0 rgba(0,122,84,.6); }
    50%      { box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 14px rgba(0,122,84,0); }
  }
  .hotspot.is-shake { animation:hs-shake .5s ease }
  @keyframes hs-shake {
    0%,100% { transform:translate(-50%,-50%) }
    25% { transform:translate(calc(-50% - 4px),-50%) } 75% { transform:translate(calc(-50% + 4px),-50%) }
  }

  /* tooltip do pin */
  .hotspot-tip {
    position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
    background:var(--lms-heading, #1F2A25); color:#fff; font-family:var(--lms-font-ui);
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
    border:3px solid rgba(0,122,84,.95);
    box-shadow:0 0 0 3px rgba(255,255,255,.85), inset 0 0 10px rgba(0,122,84,.4);
    animation:av-anel 2s ease-in-out infinite;
  }
  .avatar-img {
    position:relative; z-index:1; width:64px; height:auto;
    filter:drop-shadow(0 0 1.5px #fff) drop-shadow(0 0 1.5px #fff) drop-shadow(0 5px 7px rgba(31,42,37,.45));
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

// ── Navegação do mapa: ajustar (cabe inteiro) · zoom · arrastar · centralizar ──
const scroller = document.getElementById('mapa-scroll');
const mapaImg  = document.getElementById('mapa-img');
let proporcao  = 1024 / 572;   // largura/altura da arte — atualizada quando a imagem carrega
let zoom       = 1;            // 1 = ajustado à tela; até 3x
const ZOOMS    = [1, 1.5, 2, 2.6, 3.2];
const alturaAjustada = () => Math.min(scroller.clientWidth / proporcao, scroller.clientHeight);
function aplicaZoom(novo, foco) {
  // foco: ponto do mapa (fração 0–1) que deve continuar no mesmo lugar da tela;
  // sem foco, mantém o centro atual
  const wAntes = mapa.offsetWidth, hAntes = mapa.offsetHeight;
  const fx = foco ? foco.x : (scroller.scrollLeft + scroller.clientWidth  / 2) / Math.max(1, wAntes);
  const fy = foco ? foco.y : (scroller.scrollTop  + scroller.clientHeight / 2) / Math.max(1, hAntes);
  zoom = Math.max(1, Math.min(ZOOMS[ZOOMS.length - 1], novo));
  const h = Math.round(alturaAjustada() * zoom), w = Math.round(h * proporcao);
  mapa.style.width = w + 'px'; mapa.style.height = h + 'px';
  scroller.scrollLeft = fx * w - scroller.clientWidth  / 2;
  scroller.scrollTop  = fy * h - scroller.clientHeight / 2;
  document.getElementById('nav-menos').disabled = zoom <= 1;
  document.getElementById('nav-mais').disabled  = zoom >= ZOOMS[ZOOMS.length - 1];
}
const proximoZoom = (dir) => {
  const i = ZOOMS.findIndex(z => Math.abs(z - zoom) < .01);
  const alvo = i < 0 ? (dir > 0 ? ZOOMS.find(z => z > zoom) : [...ZOOMS].reverse().find(z => z < zoom)) : ZOOMS[Math.max(0, Math.min(ZOOMS.length - 1, i + dir))];
  return alvo ?? zoom;
};
const centraEm = (f) => aplicaZoom(zoom, { x: f.x / 100, y: f.y / 100 });
document.getElementById('nav-mais').addEventListener('click',    () => aplicaZoom(proximoZoom(+1)));
document.getElementById('nav-menos').addEventListener('click',   () => aplicaZoom(proximoZoom(-1)));
document.getElementById('nav-ajustar').addEventListener('click', () => aplicaZoom(1));
document.getElementById('nav-eu').addEventListener('click',      () => {
  const f = FASES.find(x => x.estado === 'available') || FASES[FASES.length - 1];
  if (zoom < 1.5) aplicaZoom(1.5, f ? { x: f.x / 100, y: f.y / 100 } : null); else if (f) centraEm(f);
});
// zoom inicial: no desktop o mapa inteiro; em tela em pé (celular) o mapa
// nasce ampliado pra preencher a altura, centrado na fase atual
const zoomInicial = () => {
  const emPe = scroller.clientHeight > scroller.clientWidth;
  const z = emPe ? Math.min(2.6, scroller.clientHeight / (scroller.clientWidth / proporcao)) : 1;
  const f = FASES.find(x => x.estado === 'available') || FASES[0];
  aplicaZoom(z, f ? { x: f.x / 100, y: f.y / 100 } : null);
};
const aoCarregarArte = () => {
  if (mapaImg.naturalWidth && mapaImg.naturalHeight) proporcao = mapaImg.naturalWidth / mapaImg.naturalHeight;
  zoomInicial();
};
if (mapaImg.complete && mapaImg.naturalWidth) aoCarregarArte(); else mapaImg.addEventListener('load', aoCarregarArte, { once: true });
mapaImg.addEventListener('error', zoomInicial, { once: true });
window.addEventListener('resize', () => aplicaZoom(zoom));
// arrastar com o mouse (toque já rola nativamente)
let arrasto = null;
scroller.addEventListener('mousedown', (ev) => {
  if (ev.button !== 0 || ev.target.closest('button, a')) return;
  arrasto = { x: ev.clientX, y: ev.clientY, sl: scroller.scrollLeft, st: scroller.scrollTop, moveu: false };
  scroller.classList.add('is-arrastando');
});
window.addEventListener('mousemove', (ev) => {
  if (!arrasto) return;
  const dx = ev.clientX - arrasto.x, dy = ev.clientY - arrasto.y;
  if (Math.abs(dx) + Math.abs(dy) > 3) arrasto.moveu = true;
  scroller.scrollLeft = arrasto.sl - dx; scroller.scrollTop = arrasto.st - dy;
});
window.addEventListener('mouseup', () => { arrasto = null; scroller.classList.remove('is-arrastando'); });
// ctrl/⌘ + roda do mouse = zoom no ponto (em vez de zoom da página)
scroller.addEventListener('wheel', (ev) => {
  if (!ev.ctrlKey && !ev.metaKey) return;
  ev.preventDefault();
  const r = mapa.getBoundingClientRect();
  const foco = { x: (ev.clientX - r.left) / r.width, y: (ev.clientY - r.top) / r.height };
  aplicaZoom(zoom * (ev.deltaY < 0 ? 1.15 : 1 / 1.15), foco);
}, { passive: false });

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
FASES.forEach((f, i) => {
  const hs = document.createElement('button');
  hs.className = 'hotspot is-' + f.estado;
  hs.style.setProperty('--i', i);  // escalona o pop das concluídas
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
