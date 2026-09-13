/* ════════════════════════════════════════════════════════════
   Skope LMS · Player de treinamento + catálogo de minigames.
   Cada minigame: render(config, host, done) — recebe config JSON
   (nada hardcoded, PRD §9) e chama done({acertos, total}).
   O Player cuida de sequência, timer, registro e celebração.
   ════════════════════════════════════════════════════════════ */

/* ── util ── */
const el = (html) => { const d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstElementChild; };
const shuffle = (a) => { a = [...a]; for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; };

/* ── estilos dos minigames + transições (tokens do design system) ── */
document.head.appendChild(el(`<style>
  .mg-slide { animation: mg-entra .45s var(--lms-ease, ease) both }
  @keyframes mg-entra { from { opacity:0; transform:translateY(14px) } to { opacity:1; transform:none } }

  .mg-howto { display:flex; gap:10px; align-items:center; padding:9px 14px; border-radius:var(--lms-radius-md, 12px);
    background:var(--lms-sky, #D3ECF9); border:var(--lms-border-game); font-size:13.5px; line-height:1.45 }
  .mg-howto-ico { flex:none; font-size:18px }
  .mg-howto-lbl { font-family:var(--lms-font-ui); font-weight:800; color:var(--lms-heading);
    text-transform:uppercase; letter-spacing:.05em; font-size:11px; margin-right:2px }
  .mg-howto-txt { color:var(--lms-ink) }

  .mg-dots { display:flex; gap:6px }
  .mg-dot { width:9px; height:9px; border-radius:50%; background:var(--lms-border, #ddd); transition:background .2s }
  .mg-dot.is-done { background: var(--lms-success, #2E7D32) }
  .mg-dot.is-active { background: var(--lms-primary, #1CAE8D); box-shadow:0 0 0 4px rgba(41,173,220,.18) }

  .mg-quadro { display:flex; gap:16px; align-items:flex-start; border:var(--lms-border-game);
    border-radius:var(--lms-radius-lg, 16px); padding:24px; background:var(--lms-white, #fff) }
  .mg-quadro-icone { width:58px; height:58px; flex:none; border-radius:10px; background:var(--lms-sky, #D3ECF9);
    display:flex; align-items:center; justify-content:center; font-size:28px }
  .mg-quadro-titulo { font-family:var(--lms-font-ui); font-weight:700; font-size:13px; letter-spacing:.08em;
    text-transform:uppercase; color:var(--lms-primary, #1CAE8D); margin-bottom:6px }
  .mg-quadro-texto { font-size:15.5px; line-height:1.65 }

  .mg-pool { display:flex; flex-wrap:wrap; gap:8px; padding:14px; border:2px dashed var(--lms-border, #ddd);
    border-radius:var(--lms-radius-md, 12px); min-height:56px }
  .mg-chip { border:var(--lms-border-game); background:var(--lms-white, #fff); border-radius:15px;
    padding:8px 14px; font-size:13px; font-family:var(--lms-font-ui); cursor:pointer;
    transition:transform .15s var(--lms-ease, ease) }
  .mg-chip:hover { transform:scale(1.05) }
  .mg-chip.is-sel { background:var(--lms-sand, #FBEED3); box-shadow:var(--lms-shadow-crisp-sm) }
  .mg-chip.is-ok { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32);
    cursor:default; display:block; width:100%; text-align:left; margin-top:6px }
  .mg-zonas { display:grid; gap:12px }
  .mg-zona { border:2.5px dashed var(--lms-border, #ddd); border-radius:var(--lms-radius-md, 12px);
    padding:12px; min-height:120px; cursor:pointer; transition:background .15s }
  .mg-zona:hover { background:rgba(0,0,0,.025) }
  .mg-zona-titulo { font-family:var(--lms-font-ui); font-weight:700; font-size:12.5px; letter-spacing:.05em }
  .mg-zona.is-mint { border-color:var(--lms-success, #2E7D32) } .mg-zona.is-mint .mg-zona-titulo { color:var(--lms-success, #2E7D32) }
  .mg-zona.is-sand { border-color:#C98A12 } .mg-zona.is-sand .mg-zona-titulo { color:#9A6A0D }
  .mg-zona.is-rose { border-color:var(--lms-error, #C62828) } .mg-zona.is-rose .mg-zona-titulo { color:var(--lms-error, #C62828) }
  .mg-zona.is-sky { border-color:var(--lms-primary, #1CAE8D) } .mg-zona.is-sky .mg-zona-titulo { color:var(--lms-primary, #1CAE8D) }

  .mg-caca-wrap { display:flex; gap:20px; flex-wrap:wrap }
  .mg-caca-grid { display:grid; grid-template-columns:repeat(3, 64px); gap:10px; align-content:start }
  .mg-celula { width:64px; height:64px; border-radius:var(--lms-radius-md, 12px); border:var(--lms-border-game);
    background:var(--lms-sky, #D3ECF9); font-size:20px; cursor:pointer; transition:transform .15s }
  .mg-celula:hover { transform:scale(1.06) }
  .mg-celula.is-hit { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32);
    cursor:default; transform:none }
  .mg-caca-painel { flex:1; min-width:230px }
  .mg-caca-card { border:var(--lms-border); border-radius:var(--lms-radius-md, 12px); padding:10px 14px;
    margin-top:8px; font-size:13.5px; background:var(--lms-white, #fff) }

  .mg-podcast { display:flex; align-items:center; gap:14px; border:var(--lms-border-game);
    border-radius:var(--lms-radius-lg, 16px); padding:16px 20px; background:var(--lms-white, #fff) }
  .mg-play { width:46px; height:46px; flex:none; border-radius:50%; border:none; cursor:pointer;
    background:var(--lms-primary, #1CAE8D); color:#fff; font-size:16px }
  .mg-podcast-titulo { font-family:var(--lms-font-ui); font-weight:600; font-size:14px }
  .mg-wave { display:none; gap:2px; align-items:center; height:22px }
  .mg-wave span { width:3px; border-radius:2px; background:var(--lms-primary, #1CAE8D); opacity:.55 }
  .mg-wave.is-on { display:flex } .mg-wave.is-on span { animation: mg-eq .8s ease-in-out infinite alternate }
  .mg-wave.is-on span:nth-child(odd) { animation-duration:.6s }
  @keyframes mg-eq { from { transform:scaleY(.4) } to { transform:scaleY(1.15) } }
  .mg-quem { display:block; font-family:var(--lms-font-ui); font-weight:700; font-size:11.5px;
    letter-spacing:.08em; color:var(--lms-primary, #1CAE8D); margin-bottom:2px }

  .mg-passo { display:block; width:100%; text-align:left; padding:13px 16px; font-size:14.5px;
    border:var(--lms-border-game); border-radius:var(--lms-radius-md, 12px); background:var(--lms-white, #fff);
    cursor:pointer; transition:transform .15s var(--lms-ease, ease); font-family:inherit }
  .mg-passo:hover:not(:disabled) { transform:translateX(4px) }
  .mg-passo.is-ok { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32); cursor:default }
  .mg-passo-num { display:inline-flex; width:24px; height:24px; border-radius:50%; align-items:center;
    justify-content:center; background:var(--lms-success, #2E7D32); color:#fff; font-family:var(--lms-font-ui);
    font-weight:700; font-size:12px; margin-right:8px }

  .mg-passo-grid { display:flex; flex-wrap:wrap; gap:10px; justify-content:center }
  .mg-passo.is-card { width:150px; display:flex; flex-direction:column; align-items:center; text-align:center; padding:10px 8px 12px; gap:8px; font-size:14px; font-weight:600; font-family:var(--lms-font-ui); color:var(--lms-heading, #2A4971); line-height:1.2 }
  .mg-passo.is-card:hover:not(:disabled) { transform:translateY(-3px) }
  .mg-passo-img { width:120px; height:120px; object-fit:contain; border-radius:8px; background:var(--lms-surface, #F4F1EA); display:flex; align-items:center; justify-content:center; font-size:28px }
  .mg-passo-thumb { width:40px; height:40px; object-fit:contain; border-radius:6px; background:var(--lms-surface, #F4F1EA); vertical-align:middle; margin-right:8px }
  .mg-passo-desc { display:block; margin:6px 0 0 32px; font-size:13px; color:var(--lms-ink-soft, #69727D) }
  .mg-inv-audio { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:10px }
  .mg-inv-audio audio { flex:1; min-width:200px; height:40px }
  .mg-vid-frame.is-vertical { aspect-ratio:9/16; max-width:min(100%, 400px); margin:0 auto }
  .mg-video.is-palco .mg-vid-frame.is-vertical { width:auto; height:82vh; aspect-ratio:9/16 }

  @media (max-width: 700px) { .mg-caca-grid { grid-template-columns:repeat(3, 56px) } .mg-celula { width:56px; height:56px } }

  /* ── vídeo e áudio reais ── */
  .mg-video { position:relative; width:100% }
  .mg-vid-frame { position:relative; width:100%; aspect-ratio:16/9; border:var(--lms-border-game);
    border-radius:var(--lms-radius-lg, 16px); overflow:hidden; background:#000 }
  .mg-vid-frame iframe, .mg-vid-frame video { position:absolute; inset:0; width:100%; height:100%; border:0 }
  .mg-vid-play {
    position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
    width:72px; height:72px; border-radius:50%; border:3px solid #fff; cursor:pointer;
    background:var(--lms-primary, #1CAE8D); color:#fff; font-size:26px;
    box-shadow:0 4px 16px rgba(0,0,0,.45); transition:transform .15s var(--lms-ease, ease), opacity .2s ease;
  }
  .mg-vid-play:hover { transform:translate(-50%,-50%) scale(1.1) }
  .mg-vid-play.is-tocando { opacity:0 }
  .mg-vid-frame:hover .mg-vid-play.is-tocando { opacity:.85 }
  .mg-audio-el { width:100% }

  /* barra de controles custom: sem barra de progresso de propósito
     (o vídeo não pode ser adiantado) — só volume, velocidade e o
     modo palco (amplia sem sair da plataforma nem abrir o YouTube). */
  .mg-vid-controles {
    display:flex; align-items:center; gap:12px; margin-top:10px; padding:8px 14px;
    background:var(--lms-white); border:var(--lms-border-game); border-radius:var(--lms-radius-md);
    font-family:var(--lms-font-ui);
  }
  .mg-vid-ctl { border:none; background:none; cursor:pointer; font-size:17px; padding:2px 4px;
    color:var(--lms-ink); line-height:1; flex:none }
  .mg-vid-ctl:hover { color:var(--lms-primary) }
  .mg-vid-vol-slider { width:84px; accent-color:var(--lms-primary, #1CAE8D) }
  .mg-vid-vel { border:1px solid var(--lms-border); border-radius:6px; padding:4px 6px;
    font-size:12.5px; font-family:var(--lms-font-ui); color:var(--lms-ink); background:var(--lms-white); cursor:pointer }
  .mg-vid-spacer { flex:1 }

  /* modo palco: overlay fixo cobrindo a tela, sem sair da página */
  .mg-video.is-palco {
    position:fixed; inset:0; z-index:1000; padding:min(5vh,40px) min(5vw,40px);
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px;
    background:rgba(8,12,18,.94);
  }
  .mg-video.is-palco .mg-vid-frame { width:min(1500px, 92vw); max-height:82vh; aspect-ratio:16/9 }
  .mg-video.is-palco .mg-vid-controles { width:min(1500px, 92vw) }

  /* ── Leitura em cards (tema por palavra-chave do título) — quebra a
     parede de texto em cabeçalhos de seção, cards ilustrados com ícone
     e listas com marcador, em vez de parágrafos empilhados. ── */
  .mg-lc-wrap { display:flex; flex-direction:column; gap:2px }
  .mg-lc-secao { display:flex; align-items:center; gap:11px; margin:20px 0 6px; padding-bottom:9px;
    border-bottom:2px solid var(--lms-border) }
  .mg-lc-secao:first-child { margin-top:0 }
  .mg-lc-secao-icone { font-size:23px; flex:none; line-height:1 }
  .mg-lc-secao-titulo { font-family:var(--lms-font-ui); font-size:14.5px; letter-spacing:.05em;
    text-transform:uppercase; font-weight:800; margin:0; color:var(--lms-heading) }
  .mg-lc-secao.is-success .mg-lc-secao-titulo { color:var(--lms-success) }
  .mg-lc-secao.is-error   .mg-lc-secao-titulo { color:var(--lms-error) }
  .mg-lc-secao.is-warning .mg-lc-secao-titulo { color:#9A6A0D }
  .mg-lc-secao.is-primary .mg-lc-secao-titulo { color:var(--lms-primary) }

  .mg-lc-card { display:flex; gap:14px; padding:16px 18px; border-radius:var(--lms-radius-md, 12px);
    border:1.5px solid var(--lms-border); background:var(--lms-white, #fff); margin-bottom:10px;
    box-shadow:var(--lms-shadow-crisp-sm); transition:transform .15s var(--lms-ease, ease) }
  .mg-lc-card:hover { transform:translateY(-2px) }
  .mg-lc-card-icone { font-size:24px; flex:none; width:44px; height:44px; border-radius:11px;
    display:flex; align-items:center; justify-content:center; background:var(--lms-sky) }
  .mg-lc-card.is-mint .mg-lc-card-icone { background:var(--lms-mint) }
  .mg-lc-card.is-rose .mg-lc-card-icone { background:var(--lms-rose) }
  .mg-lc-card.is-sand .mg-lc-card-icone { background:var(--lms-sand) }
  .mg-lc-card-corpo { flex:1; min-width:0 }
  .mg-lc-card-titulo { font-family:var(--lms-font-ui); font-weight:800; font-size:13px; margin:0 0 4px; color:var(--lms-heading) }
  .mg-lc-card-texto { margin:0; font-size:15px; line-height:1.6; color:var(--lms-ink) }

  .mg-lc-lista { list-style:none; margin:2px 0 16px; padding:0; display:grid; gap:7px }
  .mg-lc-lista li { display:flex; gap:10px; align-items:flex-start; font-size:14.5px; line-height:1.55;
    color:var(--lms-ink); padding:10px 14px; background:var(--lms-white, #fff);
    border:1px solid var(--lms-border); border-radius:var(--lms-radius-sm, 8px) }
  .mg-lc-li-icone { flex:none; color:var(--lms-primary); font-weight:900 }

  .mg-lc-paragrafo { font-size:15.5px; line-height:1.7; margin:0 0 14px; color:var(--lms-ink) }

  /* ── Banner de abertura da leitura: ilustração SVG grande de verdade
     (não emoji pequeno) + manchete em tipografia grande — o "ativo
     visual" que dá respiro antes da parede de cards ── */
  .mg-lc-capa { display:flex; align-items:center; gap:22px; padding:26px 28px; border-radius:20px;
    margin-bottom:20px; position:relative; overflow:hidden }
  .mg-lc-capa.is-success { background:linear-gradient(120deg, var(--lms-mint), transparent 85%) }
  .mg-lc-capa.is-error   { background:linear-gradient(120deg, var(--lms-rose), transparent 85%) }
  .mg-lc-capa.is-warning { background:linear-gradient(120deg, var(--lms-sand), transparent 85%) }
  .mg-lc-capa.is-primary { background:linear-gradient(120deg, var(--lms-sky), transparent 85%) }
  .mg-lc-capa-ilustra { flex:none; width:88px; height:88px }
  .mg-lc-capa-ilustra svg { width:100%; height:100%; display:block }
  .mg-lc-capa-texto { flex:1; min-width:0 }
  .mg-lc-capa-kicker { font-family:var(--lms-font-ui); font-weight:800; font-size:11.5px; letter-spacing:.09em;
    text-transform:uppercase; margin:0 0 5px; opacity:.7; color:var(--lms-heading) }
  .mg-lc-capa-titulo { font-family:var(--lms-font-display, var(--lms-font-ui)); font-size:clamp(21px, 3.6vw, 29px);
    font-weight:800; line-height:1.18; margin:0; color:var(--lms-heading) }
  @media (max-width: 560px) {
    .mg-lc-capa { flex-direction:column; text-align:center; padding:22px 20px }
    .mg-lc-capa-ilustra { width:68px; height:68px }
  }

  /* ── Hero de comparação (2 itens lado a lado) — banner ilustrado
     reutilizável pra abrir quiz/caça/etc. com mais vida antes das
     perguntas, em vez de só texto corrido ── */
  .mg-hero-duo { display:grid; grid-template-columns:1fr auto 1fr; gap:14px; align-items:center; margin-bottom:16px }
  .mg-hero-item { text-align:center; padding:20px 14px; border-radius:var(--lms-radius-lg, 16px); border:1.5px solid var(--lms-border) }
  .mg-hero-item.is-mint { background:var(--lms-mint) }
  .mg-hero-item.is-sky  { background:var(--lms-sky) }
  .mg-hero-item.is-rose { background:var(--lms-rose) }
  .mg-hero-item.is-sand { background:var(--lms-sand) }
  .mg-hero-emoji { font-size:38px; display:block; margin-bottom:8px; line-height:1 }
  .mg-hero-titulo { font-family:var(--lms-font-ui); font-weight:800; font-size:15px; margin:0 0 3px; color:var(--lms-heading) }
  .mg-hero-sub { font-size:12.5px; color:var(--lms-ink-soft); margin:0 }
  .mg-hero-badge { display:inline-block; margin-top:10px; padding:4px 11px; border-radius:999px;
    font-family:var(--lms-font-ui); font-weight:700; font-size:11px }
  .mg-hero-vs { font-family:var(--lms-font-ui); font-weight:900; font-size:20px; color:var(--lms-ink-faint) }
  @media (max-width: 560px) { .mg-hero-duo { grid-template-columns:1fr } .mg-hero-vs { display:none } }

  /* ── HQ / história com imagem ── */
  .mg-hq-img { width:100%; height:auto; display:block; border:var(--lms-border-game);
    border-radius:var(--lms-radius-lg, 16px); box-shadow:var(--lms-shadow-crisp-sm) }

  /* ── caça-palavras ── */
  .mg-cp-wrap { display:flex; gap:18px; flex-wrap:wrap; align-items:flex-start }
  .mg-cp-grade { display:grid; gap:3px; user-select:none }
  .mg-cp-cel { width:32px; height:32px; display:flex; align-items:center; justify-content:center;
    font-family:var(--lms-font-ui); font-weight:700; font-size:14px; border-radius:7px;
    background:var(--lms-white, #fff); border:1.5px solid var(--lms-border, #ddd); cursor:pointer;
    transition:background .12s, transform .12s }
  .mg-cp-cel:hover { transform:scale(1.08) }
  .mg-cp-cel.is-sel { background:var(--lms-sand, #FBEED3); border-color:#C98A12 }
  .mg-cp-cel.is-found { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32);
    color:var(--lms-success, #2E7D32); cursor:default }
  .mg-cp-cel.is-hint { animation:cp-pisca 1s ease 2 }
  @keyframes cp-pisca { 0%,100% { background:var(--lms-white, #fff) } 50% { background:var(--lms-sky, #D3ECF9) } }
  .mg-cp-lista { flex:1; min-width:190px }
  .mg-cp-palavra { display:flex; align-items:center; gap:8px; padding:7px 10px; border:var(--lms-border);
    border-radius:var(--lms-radius-md, 12px); margin-bottom:6px; background:var(--lms-white, #fff);
    font-family:var(--lms-font-ui); font-weight:600; font-size:13px }
  .mg-cp-palavra.is-found { background:var(--lms-success-soft, #DFF3E4); text-decoration:line-through; opacity:.8 }
  .mg-cp-dica { margin-left:auto; border:none; background:none; cursor:pointer; font-size:14px; opacity:.6 }
  .mg-cp-dica:hover { opacity:1 }
  @media (max-width: 700px) { .mg-cp-cel { width:26px; height:26px; font-size:12px } }

  /* ── cruzadinha ── */
  .mg-cz-tabua {
    position:relative; padding:26px 18px; border:var(--lms-border-game); overflow:hidden;
    border-radius:var(--lms-radius-lg, 16px); display:flex; justify-content:center;
    background:
      radial-gradient(360px 180px at 15% -8%, rgba(41,173,220,.25), transparent 60%),
      radial-gradient(300px 160px at 95% 108%, rgba(240,180,41,.18), transparent 60%),
      linear-gradient(160deg, #2A4971, #2B3558);
  }
  .mg-cz-tabua::before { content:'✏️'; position:absolute; right:-8px; top:-14px; font-size:74px; opacity:.12; transform:rotate(18deg) }
  .mg-cz-grade { display:grid; gap:6px; user-select:none; justify-content:center }
  .mg-cz-cel { position:relative; width:40px; height:40px }
  .mg-cz-cel input { width:100%; height:100%; text-align:center; text-transform:uppercase;
    font-family:var(--lms-font-ui); font-weight:800; font-size:17px; border-radius:9px;
    border:none; background:var(--lms-white, #fff); padding:0; color:var(--lms-heading, #2A4971);
    box-shadow:inset 0 -3px 0 rgba(30,37,67,.18), 0 2px 4px rgba(0,0,0,.3);
    transition:transform .12s var(--lms-ease, ease) }
  .mg-cz-cel input:focus { outline:3px solid var(--lms-amarelo, #F0B429); outline:3px solid #F0B429; outline-offset:-1px; transform:scale(1.08); z-index:3; position:relative }
  .mg-cz-cel.is-ok input { background:var(--lms-success-soft, #DFF3E4); color:var(--lms-success, #2E7D32); box-shadow:inset 0 -3px 0 rgba(46,125,50,.35), 0 2px 4px rgba(0,0,0,.3) }
  .mg-cz-cel.is-erro input { background:#FBE4E4; color:var(--lms-error, #C62828) }
  .mg-cz-num { position:absolute; top:-5px; left:-5px; z-index:4; pointer-events:none;
    min-width:15px; height:15px; padding:0 3px; border-radius:5px;
    background:#F0B429; color:#3A2600; font-size:9.5px; font-weight:800; font-family:var(--lms-font-ui);
    display:flex; align-items:center; justify-content:center; box-shadow:0 1px 3px rgba(0,0,0,.4) }
  .mg-cz-mesa { display:grid; grid-template-columns:1fr 1fr; gap:12px }
  .mg-cz-bloco { border:var(--lms-border-game); border-radius:var(--lms-radius-md, 12px);
    background:var(--lms-white, #fff); padding:14px 16px; box-shadow:var(--lms-shadow-crisp-sm) }
  .mg-cz-bloco > p { font-family:var(--lms-font-ui); font-weight:800; font-size:12px; letter-spacing:.06em;
    text-transform:uppercase; color:var(--lms-primary, #1CAE8D); margin:0 0 8px }
  .mg-cz-dica { display:flex; gap:8px; align-items:flex-start; font-size:13.5px; line-height:1.45;
    padding:5px 0; border-bottom:1px dashed rgba(30,37,67,.1) }
  .mg-cz-dica:last-child { border-bottom:none }
  .mg-cz-dica .cz-dnum { flex:none; min-width:20px; height:20px; padding:0 4px; border-radius:6px;
    background:var(--lms-sand, #FBEED3); color:var(--lms-heading, #2A4971);
    font-family:var(--lms-font-ui); font-weight:800; font-size:11px;
    display:flex; align-items:center; justify-content:center }
  .mg-cz-dica.is-ok { color:var(--lms-success, #2E7D32); text-decoration:line-through; opacity:.75 }
  .mg-cz-dica.is-ok .cz-dnum { background:var(--lms-success-soft, #DFF3E4); color:var(--lms-success, #2E7D32) }
  .mg-cz-timer { flex:none; display:inline-flex; align-items:center; gap:6px;
    font-family:var(--lms-font-ui); font-weight:800; font-size:14px; color:var(--lms-heading, #2A4971);
    background:var(--lms-white, #fff); border:var(--lms-border-game); border-radius:999px; padding:7px 15px;
    box-shadow:var(--lms-shadow-crisp-sm) }
  .mg-cz-timer.is-urgente { color:var(--lms-error, #C62828); animation:cz-pulsa 1s ease infinite }
  @keyframes cz-pulsa { 50% { transform:scale(1.07) } }
  @media (max-width: 700px) {
    .mg-cz-cel { width:30px; height:30px } .mg-cz-cel input { font-size:14px }
    .mg-cz-mesa { grid-template-columns:1fr } .mg-cz-tabua { padding:18px 8px }
  }

  /* ── 7 erros ── */
  .mg-se-wrap { position:relative; cursor:crosshair; border:var(--lms-border-game);
    border-radius:var(--lms-radius-lg, 16px); overflow:hidden }
  .mg-se-wrap img { width:100%; height:auto; display:block; -webkit-user-drag:none; user-select:none }
  .mg-se-marca { position:absolute; transform:translate(-50%,-50%); border:3px solid var(--lms-success, #2E7D32);
    background:rgba(46,125,50,.15); border-radius:50%; pointer-events:none;
    box-shadow:0 0 0 2px rgba(255,255,255,.8); animation:se-pop .3s var(--lms-ease, ease) }
  @keyframes se-pop { from { transform:translate(-50%,-50%) scale(.3) } to { transform:translate(-50%,-50%) scale(1) } }
  .mg-se-x { position:absolute; transform:translate(-50%,-50%); color:var(--lms-error, #C62828);
    font-weight:900; font-size:22px; pointer-events:none; animation:se-some 1s ease forwards }
  @keyframes se-some { to { opacity:0; transform:translate(-50%,-80%) } }

  /* ── investigação ── */
  .mg-inv-pista { border-left:5px solid var(--lms-primary, #1CAE8D) }

  /* ── tabuleiro ── */
  .mg-tab-mar {
    position:relative; padding:22px; border:var(--lms-border-game);
    border-radius:var(--lms-radius-lg, 16px); overflow:hidden;
    background:
      radial-gradient(420px 200px at 85% -10%, rgba(255,255,255,.5), transparent 60%),
      linear-gradient(180deg, #CDE9F5 0%, #A9D9E8 55%, #8FCBD8 100%);
  }
  .mg-tab-mar::after { content:''; position:absolute; inset:auto 0 0 0; height:26px; pointer-events:none;
    background:repeating-linear-gradient(90deg, rgba(255,255,255,.35) 0 18px, transparent 18px 36px); opacity:.5 }
  .mg-tab-board { position:relative; display:grid; gap:12px; z-index:1 }
  .mg-tab-casa {
    position:relative; aspect-ratio:1; min-width:0; border-radius:16px;
    display:flex; align-items:center; justify-content:center;
    background:var(--lms-white, #fff); border:2.5px solid var(--lms-heading, #2A4971);
    box-shadow:0 4px 0 rgba(30,37,67,.35);
    font-size:clamp(18px, 3.4vw, 27px); font-family:var(--lms-font-ui); font-weight:800;
    transition:transform .15s var(--lms-ease, ease), background .2s ease;
  }
  .mg-tab-num { position:absolute; top:4px; left:8px; font-size:10px; color:var(--lms-ink-soft, #69727D); font-weight:800 }
  .mg-tab-casa.is-pergunta { background:var(--lms-sand, #FBEED3) }
  .mg-tab-casa.is-livre { border-style:dashed; border-color:rgba(30,37,67,.45); box-shadow:0 4px 0 rgba(30,37,67,.18); background:rgba(255,255,253,.72) }
  .mg-tab-casa.is-inicio { background:var(--lms-heading, #2A4971) }
  .mg-tab-casa.is-fim { background:var(--lms-success-soft, #DFF3E4) }
  .mg-tab-casa.is-atual { transform:scale(1.07); box-shadow:0 4px 0 rgba(30,37,67,.35), 0 0 0 5px rgba(41,173,220,.4) }
  .mg-tab-casa.is-feita { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32) }
  .mg-tab-casa.is-errada { background:#FBE4E4; border-color:var(--lms-error, #C62828) }
  .mg-tab-check { position:absolute; right:5px; bottom:3px; font-size:13px }
  .mg-tab-peao {
    position:absolute; z-index:6; font-size:clamp(26px, 4vw, 38px); pointer-events:none;
    transform:translate(-50%, -88%);
    transition:left .22s var(--lms-ease, ease), top .22s var(--lms-ease, ease);
    filter:drop-shadow(0 0 2px #fff) drop-shadow(0 4px 5px rgba(30,37,67,.5));
  }
  .mg-tab-peao.is-hop { animation:peao-hop .22s ease }
  @keyframes peao-hop { 50% { margin-top:-14px } }

  .mg-tab-mesa { display:flex; align-items:center; justify-content:center; gap:18px; flex-wrap:wrap; margin-top:16px }
  .mg-dado {
    width:86px; height:86px; padding:15px; border-radius:20px; cursor:pointer;
    background:var(--lms-white, #fff); border:2.5px solid var(--lms-heading, #2A4971);
    box-shadow:0 5px 0 rgba(30,37,67,.4);
    display:grid; grid-template-columns:repeat(3, 1fr); grid-template-rows:repeat(3, 1fr); gap:4px;
    transition:transform .15s var(--lms-ease, ease);
  }
  .mg-dado:hover:not(:disabled) { transform:translateY(-3px) }
  .mg-dado:disabled { opacity:.45; cursor:not-allowed }
  .mg-dado.is-rolando { animation:dado-gira .55s ease }
  @keyframes dado-gira { 25% { transform:rotate(-16deg) scale(1.12) } 75% { transform:rotate(16deg) scale(1.12) } }
  .mg-dado .pip { border-radius:50%; background:var(--lms-heading, #2A4971); opacity:0; transition:opacity .1s }
  .mg-dado .pip.on { opacity:1 }
  .mg-tab-status { font-family:var(--lms-font-ui); font-weight:700; font-size:14px; color:var(--lms-heading);
    background:var(--lms-white, #fff); border:var(--lms-border-game); border-radius:999px; padding:10px 18px;
    box-shadow:var(--lms-shadow-crisp-sm) }
  @media (max-width: 700px) { .mg-tab-board { gap:7px } .mg-tab-casa { border-radius:11px } }

  /* ══════════ REDESIGN v2 · deixar os minigames mais vivos ══════════ */

  /* ── Ligue os Pontos (antes eram botões crus) ── */
  .mg-liga-top { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:2px }
  .mg-liga-cont { flex:none; font-family:var(--lms-font-ui); font-weight:800; font-size:12.5px;
    color:var(--lms-primary, #1CAE8D); background:var(--lms-sky, #D3ECF9); border-radius:999px; padding:5px 13px }
  .mg-liga-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 18px }
  .mg-liga-col { display:flex; flex-direction:column; gap:10px }
  .mg-liga-item { position:relative; display:flex; align-items:center; gap:11px; width:100%; text-align:left;
    font-family:inherit; font-size:14.5px; font-weight:600; color:var(--lms-ink, #272C30); line-height:1.35;
    background:var(--lms-white, #fff); border:var(--lms-border-game); border-radius:var(--lms-radius-md, 12px);
    padding:14px 16px; cursor:pointer; box-shadow:var(--lms-shadow-crisp-sm);
    transition:transform .14s var(--lms-ease, ease), border-color .14s ease, background .14s ease, box-shadow .14s ease }
  .mg-liga-item.is-dir { flex-direction:row-reverse; text-align:right }
  .mg-liga-item:hover:not(.is-par) { transform:translateY(-2px); border-color:var(--lms-primary, #1CAE8D) }
  .mg-liga-no { flex:none; width:15px; height:15px; border-radius:50%; background:var(--lms-bg, #F4F1E9);
    border:2px solid var(--lms-ink-faint, #98A0AB); transition:all .14s ease }
  .mg-liga-item.is-sel { border-color:var(--lms-primary, #1CAE8D); background:var(--lms-sky, #D3ECF9);
    box-shadow:0 0 0 3px rgba(41,173,220,.22) }
  .mg-liga-item.is-sel .mg-liga-no { background:var(--lms-primary, #1CAE8D); border-color:var(--lms-primary, #1CAE8D);
    box-shadow:0 0 0 4px rgba(41,173,220,.25) }
  .mg-liga-item.is-par { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32);
    color:var(--lms-heading, #2A4971); cursor:default; box-shadow:none }
  .mg-liga-item.is-par .mg-liga-no { background:var(--lms-success, #2E7D32); border-color:var(--lms-success, #2E7D32);
    box-shadow:none }
  .mg-liga-item.is-par .mg-liga-no::after { content:'✓'; color:#fff; font-size:9px; font-weight:900;
    display:flex; align-items:center; justify-content:center; height:100% }

  /* ── Classificação (a carta fica visível, com anel de veredito) ── */
  .mg-cl-carta { position:relative; text-align:center; padding:30px 24px; border-radius:var(--lms-radius-lg, 16px);
    border:var(--lms-border-game); box-shadow:var(--lms-shadow-crisp); transition:box-shadow .22s ease, border-color .22s ease;
    background:radial-gradient(300px 130px at 50% -20%, var(--lms-sky, #D3ECF9), transparent 72%), var(--lms-white, #fff) }
  .mg-cl-tag { display:inline-block; font-family:var(--lms-font-ui); font-weight:800; font-size:11px;
    letter-spacing:.07em; text-transform:uppercase; color:var(--lms-primary, #1CAE8D); margin-bottom:9px }
  .mg-cl-carta h3 { font-size:23px; color:var(--lms-heading, #2A4971); line-height:1.25; margin:0 }
  .mg-cl-carta.is-ok { border-color:var(--lms-success, #2E7D32); box-shadow:0 0 0 3px rgba(46,125,50,.3), var(--lms-shadow-crisp) }
  .mg-cl-carta.is-err { border-color:var(--lms-error, #C62828); box-shadow:0 0 0 3px rgba(198,40,40,.28), var(--lms-shadow-crisp) }
  .mg-cl-cats { display:grid; grid-template-columns:repeat(auto-fit, minmax(128px, 1fr)); gap:12px }
  .mg-cl-cat { display:flex; align-items:center; justify-content:center; min-height:74px; padding:14px 12px;
    text-align:center; cursor:pointer; font-family:var(--lms-font-ui); font-weight:800; font-size:15px;
    line-height:1.25; border-radius:var(--lms-radius-md, 12px); border:2.5px solid var(--lms-border, #ddd);
    background:var(--lms-white, #fff); color:var(--lms-heading, #2A4971);
    box-shadow:0 3px 0 rgba(30,37,67,.14); transition:transform .14s var(--lms-ease, ease), background .14s ease }
  .mg-cl-cat:hover:not(:disabled) { transform:translateY(-3px) }
  .mg-cl-cat:disabled { cursor:default; box-shadow:none }
  .mg-cl-cat.c0 { border-color:var(--lms-success, #2E7D32) }
  .mg-cl-cat.c1 { border-color:var(--lms-error, #C62828) }
  .mg-cl-cat.c2 { border-color:var(--lms-primary, #1CAE8D) }
  .mg-cl-cat.c3 { border-color:#C98A12 }
  .mg-cl-cat.is-right { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32); color:var(--lms-success, #2E7D32) }
  .mg-cl-cat.is-badpick { background:var(--lms-error-soft, #FBE4E4); border-color:var(--lms-error, #C62828); color:var(--lms-error, #C62828) }

  /* ── Cenário (painel de cena com personagem) ── */
  .mg-cena { display:flex; border:var(--lms-border-game); border-radius:var(--lms-radius-lg, 16px);
    overflow:hidden; box-shadow:var(--lms-shadow-crisp) }
  .mg-cena-ico { flex:none; width:104px; display:flex; align-items:center; justify-content:center; font-size:46px;
    background:linear-gradient(160deg, var(--lms-primary, #1CAE8D), var(--lms-heading, #2A4971)) }
  .mg-cena-corpo { flex:1; padding:22px 24px; background:var(--lms-white, #fff) }
  .mg-cena-lbl { display:block; font-family:var(--lms-font-ui); font-weight:800; font-size:11px; letter-spacing:.08em;
    text-transform:uppercase; color:var(--lms-primary, #1CAE8D); margin-bottom:7px }
  .mg-cena-txt { font-size:16px; line-height:1.6; color:var(--lms-ink, #272C30); margin:0 }
  .mg-cena-perg { font-family:var(--lms-font-ui); font-weight:800; font-size:14.5px; color:var(--lms-heading, #2A4971); margin:0 }
  .mg-cen-op { display:flex; align-items:center; gap:12px; width:100%; text-align:left; font-family:inherit;
    font-size:14.5px; color:var(--lms-ink, #272C30); background:var(--lms-white, #fff); border:var(--lms-border-game);
    border-radius:var(--lms-radius-md, 12px); padding:12px 15px; cursor:pointer;
    transition:transform .14s var(--lms-ease, ease), background .14s ease, border-color .14s ease }
  .mg-cen-op:hover:not([data-lock]) { transform:translateX(5px); border-color:var(--lms-primary, #1CAE8D); background:var(--lms-sky, #D3ECF9) }
  .mg-cen-op-badge { flex:none; width:30px; height:30px; border-radius:9px; display:flex; align-items:center;
    justify-content:center; font-family:var(--lms-font-ui); font-weight:800; font-size:14px;
    background:var(--lms-surface-2, #EDEAE0); color:var(--lms-ink-soft, #69727D) }
  .mg-cen-op.is-correct { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32) }
  .mg-cen-op.is-correct .mg-cen-op-badge { background:var(--lms-success, #2E7D32); color:#fff }
  .mg-cen-op.is-wrong { background:var(--lms-error-soft, #FBE4E4); border-color:var(--lms-error, #C62828) }
  .mg-cen-op.is-wrong .mg-cen-op-badge { background:var(--lms-error, #C62828); color:#fff }
  @media (max-width: 560px) { .mg-cena { flex-direction:column } .mg-cena-ico { width:100%; height:74px; font-size:38px } }

  /* ── Caça no Grid (células maiores e com brilho) ── */
  .mg-caca-grid { grid-template-columns:repeat(4, 84px) !important; gap:12px }
  .mg-celula { width:84px !important; height:84px !important; font-size:26px;
    background:linear-gradient(160deg, #E7F4FB, var(--lms-sky, #D3ECF9)); border-width:2px;
    box-shadow:0 4px 0 rgba(30,37,67,.14); position:relative; overflow:hidden }
  .mg-celula::before { content:''; position:absolute; inset:0; background:linear-gradient(120deg, rgba(255,255,255,.5), transparent 55%); pointer-events:none }
  .mg-celula.is-hit { background:var(--lms-success-soft, #DFF3E4) !important; box-shadow:0 4px 0 rgba(46,125,50,.25); font-size:30px }
  @media (max-width: 700px) { .mg-caca-grid { grid-template-columns:repeat(3, 76px) !important } .mg-celula { width:76px !important; height:76px !important } }

  /* ── Caça-Palavras (grade com profundidade) ── */
  .mg-cp-cel { border:1.5px solid var(--lms-border, #ddd); box-shadow:0 2px 0 rgba(30,37,67,.08) }
  .mg-cp-cel.is-found { box-shadow:none; transform:scale(1) }
  .mg-cp-palavra { padding:9px 12px; box-shadow:var(--lms-shadow-crisp-sm); font-size:13.5px }
  @media (min-width: 701px) { .mg-cp-cel { width:38px; height:38px; font-size:16px } }

  /* ── Jogo dos Erros (imagem bem maior, quebra o container) ── */
  .mg-se-wrap { width:min(96vw, 1040px); margin-left:50%; transform:translateX(-50%); box-shadow:var(--lms-shadow-crisp) }
  .mg-se-topo { display:flex; justify-content:center; margin-bottom:12px }
</style>`));

/* ══ KIT DE JUICE ═══════════════════════════════════════════
   Efeitos compartilhados que deixam TODO minigame vivo:
   SFX sintetizados (WebAudio, sem arquivos, com botão de mudo),
   mini-confete no ponto do acerto, texto "+⭐" voando e shakes. */

document.head.appendChild(el(`<style>
  @keyframes jc-fly { from { opacity:1; transform:translate(-50%, 0) scale(1) } to { opacity:0; transform:translate(-50%, -46px) scale(1.25) } }
  .jc-fly { position:fixed; z-index:98; pointer-events:none; font-family:var(--lms-font-ui);
    font-weight:800; font-size:17px; color:var(--lms-success, #2E7D32);
    text-shadow:0 0 4px #fff, 0 0 8px #fff; animation:jc-fly .9s ease-out forwards }
  .jc-fly.is-erro { color:var(--lms-error, #C62828) }

  @keyframes jc-pop { 0% { transform:scale(.4); opacity:0 } 65% { transform:scale(1.18) } 100% { transform:scale(1); opacity:1 } }
  .jc-pop { animation:jc-pop .35s var(--lms-ease, ease) both }
  @keyframes jc-pousa { 0% { transform:scale(1.25) } 55% { transform:scale(.92) } 100% { transform:scale(1) } }
  .jc-pousa { animation:jc-pousa .3s ease }
  @keyframes jc-festa { 0%,100% { transform:none } 30% { transform:rotate(-3deg) scale(1.06) } 60% { transform:rotate(3deg) scale(1.06) } }
  .jc-festa { animation:jc-festa .45s ease }
  @keyframes jc-sai { to { opacity:0; transform:translateY(26px) scale(.85) } }
  .jc-sai { animation:jc-sai .3s ease forwards }

  /* carimbo do Verdadeiro ou Falso */
  .jc-carimbo {
    position:absolute; right:14px; top:50%; transform:translateY(-50%) rotate(-14deg) scale(3); opacity:0;
    font-family:var(--lms-font-ui); font-weight:900; font-size:clamp(20px, 4vw, 30px); letter-spacing:.06em;
    padding:4px 14px; border:4px solid currentColor; border-radius:10px; pointer-events:none;
    animation:jc-carimba .35s cubic-bezier(.2, 1.6, .4, 1) forwards;
  }
  @keyframes jc-carimba { to { opacity:.92; transform:translateY(-50%) rotate(-14deg) scale(1) } }
  .jc-carimbo.is-verdade { color:var(--lms-success, #2E7D32) }
  .jc-carimbo.is-mito { color:var(--lms-error, #C62828) }

  /* memória: virada 3D de verdade */
  .mg-carta { perspective:600px; background:transparent !important; border:none !important; padding:0 !important }
  .mg-carta .mem-flip { position:relative; width:100%; height:100%; transform-style:preserve-3d;
    transition:transform .4s cubic-bezier(.4, 1.4, .6, 1) }
  .mg-carta.is-aberta .mem-flip, .mg-carta.is-par .mem-flip { transform:rotateY(180deg) }
  .mg-carta .mem-face { position:absolute; inset:0; backface-visibility:hidden; border-radius:var(--lms-radius-md, 12px);
    border:var(--lms-border-game); display:flex; align-items:center; justify-content:center; text-align:center; padding:8px }
  .mg-carta .mem-frente { background:var(--lms-primary, #1CAE8D); color:#fff; font-size:26px; font-family:var(--lms-font-ui) }
  .mg-carta .mem-verso { background:var(--lms-white, #fff); color:var(--lms-ink, #272C30); font-size:13px; font-weight:500;
    transform:rotateY(180deg) }
  .mg-carta.is-par .mem-verso { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success, #2E7D32) }
  .mg-carta.is-par { animation:jc-festa .5s ease }

  /* botão de som (canto do player) */
  .jc-som { margin-left:auto; width:34px; height:34px; border-radius:50%; border:var(--lms-border-game);
    background:var(--lms-white, #fff); cursor:pointer; font-size:15px; flex:none;
    box-shadow:var(--lms-shadow-crisp-sm); transition:transform .15s var(--lms-ease, ease) }
  .jc-som:hover { transform:scale(1.12) }
</style>`));

const SFX = (() => {
  let ctx = null;
  const ligado = () => localStorage.getItem('lms-sfx') !== 'off';
  const toca = (notas, dur = 0.1, tipo = 'sine', vol = 0.1) => {
    if (!ligado()) return;
    try {
      ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
      if (ctx.state === 'suspended') ctx.resume();
      const t0 = ctx.currentTime;
      notas.forEach((f, i) => {
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.type = tipo; o.frequency.value = f;
        g.gain.setValueAtTime(vol, t0 + i * dur);
        g.gain.exponentialRampToValueAtTime(0.001, t0 + (i + 1) * dur);
        o.connect(g).connect(ctx.destination);
        o.start(t0 + i * dur); o.stop(t0 + (i + 1) * dur + 0.02);
      });
    } catch (e) { /* sem áudio, sem drama */ }
  };
  return {
    ok:   () => toca([523.25, 659.25], 0.09),
    err:  () => toca([185, 147], 0.13, 'square', 0.05),
    win:  () => toca([523.25, 659.25, 783.99, 1046.5], 0.11),
    flip: () => toca([880], 0.05, 'sine', 0.05),
    dado: () => toca([300, 360, 420], 0.05, 'square', 0.04),
    alterna() { localStorage.setItem('lms-sfx', ligado() ? 'off' : 'on'); return ligado(); },
    ligado,
  };
})();

/* mini-confete no ponto do acerto (px da viewport) */
function confeteMini(x, y, qtd = 16) {
  const cv = document.createElement('canvas');
  cv.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:97';
  cv.width = innerWidth; cv.height = innerHeight;
  document.body.appendChild(cv);
  const c2 = cv.getContext('2d');
  const cores = ['#1CAE8D', '#F0B429', '#E8641B', '#2E7D32'];
  const ps = Array.from({ length: qtd }, () => ({
    x, y, vx: (Math.random() - .5) * 7, vy: -(2 + Math.random() * 5),
    w: 4 + Math.random() * 4, h: 5 + Math.random() * 5,
    cor: cores[Math.floor(Math.random() * cores.length)],
    rot: Math.random() * Math.PI, vr: (Math.random() - .5) * .4,
  }));
  let t = 0;
  (function anima() {
    c2.clearRect(0, 0, cv.width, cv.height);
    ps.forEach(p => {
      p.x += p.vx; p.y += p.vy; p.vy += .32; p.rot += p.vr;
      c2.save(); c2.translate(p.x, p.y); c2.rotate(p.rot);
      c2.fillStyle = p.cor; c2.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      c2.restore();
    });
    if (++t < 55) requestAnimationFrame(anima); else cv.remove();
  })();
}

/* texto flutuante ("+1 ⭐") subindo do elemento */
function flyText(alvo, txt, erro = false) {
  const r = alvo.getBoundingClientRect();
  const d = el(`<span class="jc-fly ${erro ? 'is-erro' : ''}">${txt}</span>`);
  d.style.left = (r.left + r.width / 2) + 'px';
  d.style.top = (r.top - 6) + 'px';
  document.body.appendChild(d);
  setTimeout(() => d.remove(), 950);
}

/* acerto e erro com a festa completa */
function juiceOk(alvo, txt = '+1 ⭐') {
  const r = alvo.getBoundingClientRect();
  confeteMini(r.left + r.width / 2, r.top + r.height / 2);
  flyText(alvo, txt);
  alvo.classList.add('jc-festa');
  SFX.ok();
}
function juiceErr(alvo, txt = '') {
  if (txt) flyText(alvo, txt, true);
  SFX.err();
}

/* ── confete (celebração — "solta as bombinhas") ── */
function confete(qtd = 90) {
  const cv = document.createElement('canvas');
  cv.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:99';
  cv.width = innerWidth; cv.height = innerHeight;
  document.body.appendChild(cv);
  const ctx = cv.getContext('2d');
  const cores = ['#1CAE8D', '#F0B429', '#E8641B', '#2E7D32', '#2A4971', '#E4572E'];
  const ps = Array.from({ length: qtd }, () => ({
    x: cv.width / 2 + (Math.random() - .5) * cv.width * .5,
    y: cv.height * .35 + (Math.random() - .5) * 80,
    vx: (Math.random() - .5) * 11, vy: -(4 + Math.random() * 9),
    w: 6 + Math.random() * 6, h: 8 + Math.random() * 8,
    cor: cores[Math.floor(Math.random() * cores.length)],
    rot: Math.random() * Math.PI, vr: (Math.random() - .5) * .3,
  }));
  let t = 0;
  (function anima() {
    ctx.clearRect(0, 0, cv.width, cv.height);
    ps.forEach(p => {
      p.x += p.vx; p.y += p.vy; p.vy += .28; p.rot += p.vr;
      ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
      ctx.fillStyle = p.cor; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      ctx.restore();
    });
    if (++t < 150) requestAnimationFrame(anima); else cv.remove();
  })();
}

/* ════════ COMO JOGAR ════════
   Instrução curta e objetiva da mecânica de cada jogo — não é manual,
   é o "como jogar" que aparece acima do minigame. Só para as dinâmicas
   interativas; leituras/vídeos/áudios/HQ são autoexplicativos. */
const COMO_JOGAR = {
  quiz:          ['❓', 'Toque na alternativa correta. Se pedir “marque todas”, selecione várias e confirme.'],
  mito_verdade:  ['🐉', 'Leia a afirmação e decida: é <strong>Mito</strong> ou <strong>Verdade</strong>?'],
  ligue_pontos:  ['🔗', 'Toque num item da esquerda e depois no par dele à direita — os pontos se conectam.'],
  classificacao: ['🗂️', 'Veja o item em destaque e toque na categoria a que ele pertence.'],
  arrastar:      ['🧳', 'Toque num item para selecioná-lo e depois na zona a que ele pertence.'],
  sequencia:     ['🔢', 'Toque nos passos na ordem em que acontecem — do primeiro ao último.'],
  memoria:       ['🃏', 'Vire duas cartas por vez e encontre os pares que combinam.'],
  cenario:       ['🎬', 'Leia a cena e toque na decisão que você tomaria.'],
  investigacao:  ['🕵️', 'Siga as pistas: cada escolha abre um caminho. Chegue à conclusão certa.'],
  caca:          ['🎯', 'Toque nas casas para revelar o que cada uma esconde — encontre todas.'],
  sete_erros:    ['🔎', 'Observe a imagem e clique nos pontos de risco escondidos na cena.'],
  caca_palavras: ['🔤', 'Ache as palavras da lista: clique na <strong>primeira</strong> e na <strong>última</strong> letra de cada uma. A 💡 dá dica.'],
  cruzadinha:    ['✏️', 'Digite as letras na grade seguindo as dicas. Há tempo no relógio — confira ao final.'],
  tabuleiro:     ['🎲', 'Jogue o dado, ande pelas casas e responda ao cair. Errou? Volta uma casa.'],
};

/* ════════ MINIGAMES ════════ */
const Minigames = {

  /* M1 · Leitura com confirmação ativa — em cards ilustrados por tema
     em vez de parede de texto. Detecta a estrutura que o conteúdo já
     usa (título em <strong>, opcionalmente seguido de corpo, ou uma
     lista "• item<br>• item") e monta cabeçalhos de seção, cards com
     ícone e listas com marcador — tudo por cima do MESMO texto, sem
     inventar conteúdo novo. */
  mgLeituraTema(titulo) {
    const t = titulo.toLowerCase();
    if (/proibiç/.test(t))                      return { icone: '⛔', cor: 'rose',  tom: 'error' };
    if (/restriç/.test(t))                       return { icone: '⚠️', cor: 'sand',  tom: 'warning' };
    if (/obrigaç/.test(t))                       return { icone: '✅', cor: 'mint',  tom: 'success' };
    if (/h.?2.?s|sulfídrico|gás/.test(t))         return { icone: '☠️', cor: 'rose',  tom: 'error' };
    if (/elétric/.test(t))                       return { icone: '⚡', cor: 'sand',  tom: 'warning' };
    if (/resolução|n-37|anp|norma/.test(t))       return { icone: '📜', cor: 'sky',   tom: 'primary' };
    if (/responsabilidade/.test(t))               return { icone: '🛡️', cor: 'sky',   tom: 'primary' };
    if (/autorização/.test(t))                    return { icone: '🔐', cor: 'sky',   tom: 'primary' };
    if (/comunicaç/.test(t))                      return { icone: '📢', cor: 'sand',  tom: 'warning' };
    if (/intervenç|procedimento/.test(t))         return { icone: '🔧', cor: 'sky',   tom: 'primary' };
    if (/emergência|abandono|evacuaç|resgate/.test(t)) return { icone: '🚨', cor: 'rose', tom: 'error' };
    if (/primeiro embarque|diferente/.test(t))    return { icone: '🎒', cor: 'sky',   tom: 'primary' };
    if (/por que|saber fazer|escopo|aplica/.test(t)) return { icone: '🎯', cor: 'mint', tom: 'success' };
    if (/ação|ações/.test(t))                     return { icone: '🧭', cor: 'sky',   tom: 'primary' };
    if (/acionado|plano/.test(t))                 return { icone: '📶', cor: 'sand',  tom: 'warning' };
    return { icone: '💡', cor: 'sky', tom: 'primary' };
  },

  // ilustrações grandes (SVG próprio, não emoji) pro banner de abertura
  // de cada leitura — um ativo visual de verdade, não um ícone pequeno
  mgLeituraSvg: {
    success: `<svg viewBox="0 0 100 100" fill="none">
      <path d="M50 6 L90 20 V48 C90 71 73 89 50 96 C27 89 10 71 10 48 V20 Z" fill="var(--lms-success)" opacity=".16"/>
      <path d="M50 6 L90 20 V48 C90 71 73 89 50 96 C27 89 10 71 10 48 V20 Z" stroke="var(--lms-success)" stroke-width="4.5"/>
      <path d="M30 50 L44 64 L72 34" stroke="var(--lms-success)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`,
    error: `<svg viewBox="0 0 100 100" fill="none">
      <path d="M50 8 L94 86 H6 Z" fill="var(--lms-error)" opacity=".16"/>
      <path d="M50 8 L94 86 H6 Z" stroke="var(--lms-error)" stroke-width="4.5" stroke-linejoin="round"/>
      <line x1="50" y1="36" x2="50" y2="60" stroke="var(--lms-error)" stroke-width="7" stroke-linecap="round"/>
      <circle cx="50" cy="73" r="4" fill="var(--lms-error)"/>
    </svg>`,
    warning: `<svg viewBox="0 0 100 100" fill="none">
      <circle cx="50" cy="50" r="45" fill="var(--lms-warning)" opacity=".16"/>
      <path d="M55 8 L26 56 H45 L38 92 L76 40 H55 Z" fill="var(--lms-warning)"/>
    </svg>`,
    primary: `<svg viewBox="0 0 100 100" fill="none">
      <rect x="18" y="8" width="64" height="84" rx="8" fill="var(--lms-primary)" opacity=".14"/>
      <rect x="18" y="8" width="64" height="84" rx="8" stroke="var(--lms-primary)" stroke-width="4.5"/>
      <line x1="30" y1="30" x2="70" y2="30" stroke="var(--lms-primary)" stroke-width="5" stroke-linecap="round"/>
      <line x1="30" y1="47" x2="70" y2="47" stroke="var(--lms-primary)" stroke-width="5" stroke-linecap="round"/>
      <line x1="30" y1="64" x2="55" y2="64" stroke="var(--lms-primary)" stroke-width="5" stroke-linecap="round"/>
    </svg>`,
  },

  mgLeituraHtml(texto) {
    // remove símbolo/emoji que o próprio conteúdo já usa como marcador
    // (✔ ✕ △ ⚡ etc.) — o card já ganha um ícone consistente pelo tema,
    // manter os dois juntos duplicava o ícone
    const semSimboloProprio = (s) => s.replace(/^[\s]*[✔✕△⚡☠️🔥⚠️✅⛔📜🛡️🔐📢🔧📋🚨🎒🎯🧭📶💡]+\s*/u, '').trim();

    return texto.map((bloco) => {
      const listaBullet = bloco.match(/^•\s?/);
      if (listaBullet) {
        const itens = bloco.split(/<br>\s*•\s?/).map(s => s.replace(/^•\s?/, ''));
        return `<ul class="mg-lc-lista">${itens.map(it => `<li><span class="mg-lc-li-icone">▸</span><span>${it}</span></li>`).join('')}</ul>`;
      }
      // título em <strong>, com ou sem corpo na sequência — o <br> entre
      // os dois é opcional (parte do conteúdo já vem "Rótulo: texto" na
      // mesma linha, sem <br>)
      const m = bloco.match(/^<strong>(.+?)<\/strong>(?:<br>)?\s*(.*)$/s);
      if (m) {
        const titulo = semSimboloProprio(m[1]);
        const corpo = m[2].trim();
        const { icone, tom, cor } = Minigames.mgLeituraTema(titulo);
        if (!corpo) {
          return `<div class="mg-lc-secao is-${tom}"><span class="mg-lc-secao-icone">${icone}</span><h3 class="mg-lc-secao-titulo">${titulo}</h3></div>`;
        }
        return `<div class="mg-lc-card is-${cor}">
          <span class="mg-lc-card-icone">${icone}</span>
          <div class="mg-lc-card-corpo"><p class="mg-lc-card-titulo">${titulo}</p><p class="mg-lc-card-texto">${corpo}</p></div>
        </div>`;
      }
      return `<p class="mg-lc-paragrafo">${bloco}</p>`;
    }).join('');
  },

  leitura(cfg, host, done, revisao) {
    const blocos = Minigames.mgLeituraHtml(cfg.texto);
    // capa/banner de abertura: ilustração SVG grande + manchete grande —
    // o "ativo visual" que dá respiro antes da parede de cards. Só
    // aparece se o conteúdo definir um gancho (frase de efeito curta);
    // tema explícito (cfg.tema) ou, na falta, o tema do 1º bloco.
    let capa = '';
    if (cfg.gancho) {
      const tom = cfg.tema || 'primary';
      capa = `<div class="mg-lc-capa is-${tom}">
        <div class="mg-lc-capa-ilustra">${Minigames.mgLeituraSvg[tom]}</div>
        <div class="mg-lc-capa-texto">
          ${cfg.kicker ? `<p class="mg-lc-capa-kicker">${cfg.kicker}</p>` : ''}
          <h2 class="mg-lc-capa-titulo">${cfg.gancho}</h2>
        </div>
      </div>`;
    }
    const pontos = cfg.pontos_confirmacao.map((p, i) => `
      <label class="lms-check" data-i="${i}">
        <input type="checkbox" ${revisao ? 'checked disabled' : ''}> ${p.label}
      </label>`).join('');
    host.appendChild(el(`
      <div class="lms-stack-4">
        ${capa}
        <div class="mg-lc-wrap">${blocos}</div>
        <div>
          <h4 style="margin-bottom:10px">✋ ${revisao ? 'Pontos confirmados na conclusão:' : 'Confirme os pontos críticos para avançar:'}</h4>
          <div class="lms-stack-2">${pontos}</div>
        </div>
        ${revisao ? '' : '<button class="lms-btn lms-btn-primary lms-btn-lg" disabled style="width:100%">Confirmei todos os pontos ✓</button>'}
      </div>`));
    if (revisao) {
      host.querySelectorAll('.lms-check').forEach(l => l.classList.add('is-checked'));
      return;
    }
    const btn = host.querySelector('button');
    const checks = [...host.querySelectorAll('input[type=checkbox]')];
    checks.forEach(c => c.addEventListener('change', () => {
      c.closest('.lms-check').classList.toggle('is-checked', c.checked);
      btn.disabled = !checks.every(x => x.checked);
    }));
    btn.addEventListener('click', () => done({ acertos: checks.length, total: checks.length }));
  },

  /* M2 · Quiz (uma ou várias corretas, feedback imediato) */
  quiz(cfg, host, done, revisao) {
    let idx = 0, acertos = 0;
    const total = cfg.perguntas.length;
    const respostas = [];   // captura: índices marcados em cada pergunta (na ordem)

    // ── modo revisão: mesma paginação, resposta já marcada, sem poder mudar ──
    if (revisao) {
      const guardadas = revisao.resposta || [];
      const mostra = (i) => {
        const q = cfg.perguntas[i];
        const marcadas = new Set(guardadas[i] || []);
        host.innerHTML = '';
        host.appendChild(el(`
          <div class="lms-stack-4">
            ${i === 0 && cfg.intro ? `<div class="lms-card" style="font-size:14.5px; line-height:1.65">${cfg.intro}</div>` : ''}
            <span class="lms-pill is-primary">Pergunta ${i + 1} de ${total}</span>
            <h3>${q.enunciado}</h3>
            <div class="lms-stack-2" id="alts"></div>
            ${q.explicacao ? `<div class="lms-alert is-info">💡 <div>${q.explicacao}</div></div>` : ''}
            <div id="mg-acao"></div>
          </div>`));
        const alts = host.querySelector('#alts');
        q.alternativas.forEach((a, ai) => {
          const b = el(`<button class="lms-answer" disabled>${a}</button>`);
          if (q.corretas.includes(ai)) b.classList.add('is-correct');
          else if (marcadas.has(ai)) b.classList.add('is-wrong');
          alts.appendChild(b);
        });
        if (i + 1 < total) {
          const nx = el(`<button class="lms-btn lms-btn-outline" style="width:100%">Próxima →</button>`);
          nx.addEventListener('click', () => mostra(i + 1));
          host.querySelector('#mg-acao').appendChild(nx);
        }
      };
      mostra(0);
      return;
    }

    const proxima = () => {
      if (idx >= total) return done({ acertos, total, resposta: respostas });
      const q = cfg.perguntas[idx];
      const multi = q.corretas.length > 1;
      host.innerHTML = '';
      host.appendChild(el(`
        <div class="lms-stack-4">
          ${idx === 0 && cfg.intro ? `<div class="lms-card" style="font-size:14.5px; line-height:1.65">${cfg.intro}</div>` : ''}
          <div class="lms-row" style="justify-content:space-between">
            <span class="lms-pill is-primary">Pergunta ${idx + 1} de ${total}</span>
            ${multi ? '<span class="lms-pill">marque todas as corretas</span>' : ''}
          </div>
          <h3>${q.enunciado}</h3>
          <div class="lms-stack-2" id="alts"></div>
          <div id="mg-acao"></div>
        </div>`));
      const alts = host.querySelector('#alts'), acao = host.querySelector('#mg-acao');
      const sel = new Set();
      let travado = false;

      q.alternativas.forEach((a, i) => {
        const b = el(`<button class="lms-answer">${a}</button>`);
        b.addEventListener('click', () => {
          if (travado) return;
          if (multi) {
            sel.has(i) ? sel.delete(i) : sel.add(i);
            b.classList.toggle('is-sel');
            b.style.background = sel.has(i) ? 'var(--lms-sky)' : '';
            return;
          }
          travado = true;
          const ok = q.corretas.includes(i);
          respostas[idx] = [i];
          b.classList.add(ok ? 'is-correct' : 'is-wrong');
          if (!ok) alts.children[q.corretas[0]].classList.add('is-correct');
          else b.classList.add('lms-pop');
          if (ok) { acertos++; juiceOk(b); } else juiceErr(b);
          if (q.explicacao) acao.appendChild(el(`<div class="lms-alert ${ok ? 'is-success' : 'is-info'} lms-pop">${ok ? '✅' : '💡'} <div>${q.explicacao}</div></div>`));
          acao.appendChild(el(`<button class="lms-btn lms-btn-primary" style="width:100%">${idx + 1 === total ? 'Finalizar' : 'Próxima'} →</button>`));
          acao.lastElementChild.addEventListener('click', () => { idx++; proxima(); });  // último filho: a explicação (se houver) entra antes do botão
        });
        alts.appendChild(b);
      });

      if (multi) {
        const conf = el(`<button class="lms-btn lms-btn-primary" style="width:100%">Confirmar resposta</button>`);
        conf.addEventListener('click', () => {
          if (travado) return;
          travado = true;
          const ok = q.corretas.length === sel.size && q.corretas.every(c => sel.has(c));
          respostas[idx] = [...sel];
          [...alts.children].forEach((b, i) => {
            if (q.corretas.includes(i)) b.classList.add('is-correct');
            else if (sel.has(i)) b.classList.add('is-wrong');
          });
          if (ok) { acertos++; juiceOk(conf); } else juiceErr(conf);
          if (q.explicacao) acao.insertBefore(el(`<div class="lms-alert ${ok ? 'is-success' : 'is-info'} lms-pop">${ok ? '✅' : '💡'} <div>${q.explicacao}</div></div>`), conf);
          conf.textContent = (idx + 1 === total ? 'Finalizar' : 'Próxima') + ' →';
          conf.addEventListener('click', () => { idx++; proxima(); }, { once: true });
        }, { once: true });
        acao.appendChild(conf);
      }
    };
    proxima();
  },

  /* M3 · Mito ou Verdade */
  mito_verdade(cfg, host, done, revisao) {
    let idx = 0, acertos = 0;
    const total = cfg.itens.length;
    const respostas = []; // captura: true/false marcado em cada item

    if (revisao) {
      const guardadas = revisao.resposta || [];
      const mostra = (i) => {
        const item = cfg.itens[i];
        const marcou = guardadas[i];
        host.innerHTML = '';
        host.appendChild(el(`
          <div class="lms-stack-4">
            <span class="lms-pill is-primary">Afirmação ${i + 1} de ${total}</span>
            <div class="lms-card-game lms-center" id="mg-mv-card" style="padding:36px 28px; position:relative; overflow:hidden">
              <h3 style="font-weight:500">“${item.afirmacao}”</h3>
              <span class="jc-carimbo ${item.resposta ? 'is-verdade' : 'is-mito'}" style="opacity:1; transform:translateY(-50%) rotate(-14deg) scale(1)">${item.resposta ? 'VERDADE' : 'MITO'}</span>
            </div>
            <div class="lms-row" style="gap:14px">
              <button class="lms-btn lms-btn-game is-rose lms-btn-lg" disabled style="flex:1; ${marcou === false ? 'outline:3px solid var(--lms-heading)' : ''}">🐉 Mito</button>
              <button class="lms-btn lms-btn-game is-mint lms-btn-lg" disabled style="flex:1; ${marcou === true ? 'outline:3px solid var(--lms-heading)' : ''}">✅ Verdade</button>
            </div>
            <div class="lms-alert is-info">${item.explicacao}</div>
            <div id="mg-acao"></div>
          </div>`));
        if (i + 1 < total) {
          const nx = el(`<button class="lms-btn lms-btn-outline" style="width:100%">Próxima →</button>`);
          nx.addEventListener('click', () => mostra(i + 1));
          host.querySelector('#mg-acao').appendChild(nx);
        }
      };
      mostra(0);
      return;
    }

    const proxima = () => {
      if (idx >= total) return done({ acertos, total, resposta: respostas });
      const item = cfg.itens[idx];
      host.innerHTML = '';
      host.appendChild(el(`
        <div class="lms-stack-4">
          <span class="lms-pill is-primary">Afirmação ${idx + 1} de ${total}</span>
          <div class="lms-card-game lms-center" id="mg-mv-card" style="padding:36px 28px; position:relative; overflow:hidden">
            <h3 style="font-weight:500">“${item.afirmacao}”</h3>
          </div>
          <div class="lms-row" style="gap:14px">
            <button class="lms-btn lms-btn-game is-rose lms-btn-lg" data-v="false" style="flex:1">🐉 Mito</button>
            <button class="lms-btn lms-btn-game is-mint lms-btn-lg" data-v="true" style="flex:1">✅ Verdade</button>
          </div>
          <div id="mg-fb"></div>
        </div>`));
      let travado = false;
      host.querySelectorAll('[data-v]').forEach(b => b.addEventListener('click', () => {
        if (travado) return;
        travado = true;
        const marcou = b.dataset.v === 'true';
        respostas[idx] = marcou;
        const ok = marcou === item.resposta;
        if (ok) acertos++;
        // carimbo estampado no cartão com o veredito real da afirmação
        host.querySelector('#mg-mv-card').appendChild(el(
          `<span class="jc-carimbo ${item.resposta ? 'is-verdade' : 'is-mito'}">${item.resposta ? 'VERDADE' : 'MITO'}</span>`));
        if (ok) juiceOk(b); else juiceErr(b);
        const fb = host.querySelector('#mg-fb');
        fb.appendChild(el(`
          <div class="lms-stack-2">
            <div class="lms-alert ${ok ? 'is-success' : 'is-error'} lms-pop">${ok ? '🎉' : '❌'} <div><strong>${ok ? 'Acertou!' : 'Não é bem assim.'}</strong> ${item.explicacao}</div></div>
            <button class="lms-btn lms-btn-primary" style="width:100%">${idx + 1 === total ? 'Finalizar' : 'Próxima'} →</button>
          </div>`));
        fb.querySelector('button').addEventListener('click', () => { idx++; proxima(); });
      }));
    };
    proxima();
  },

  /* M4 · Ligue os pontos (clique esquerda → direita) */
  ligue_pontos(cfg, host, done, revisao) {
    const pares = cfg.pares;
    const total = pares.length;

    // conclui SÓ quando tudo está certo — não há "escolha errada" que
    // sobreviva ao final, então revisão é sempre o estado 100% pareado,
    // direto do config (nenhuma resposta guardada precisa ser lida)
    if (revisao) {
      host.appendChild(el(`
        <div class="lms-stack-4">
          <p class="lms-muted lms-small">Todos os pares ficaram assim ao concluir:</p>
          <div class="mg-liga-grid">
            <div class="mg-liga-col">${pares.map(p => `<div class="mg-liga-item is-par"><span>${p.esquerda}</span><span class="mg-liga-no"></span></div>`).join('')}</div>
            <div class="mg-liga-col">${pares.map(p => `<div class="mg-liga-item is-par is-dir"><span>${p.direita}</span><span class="mg-liga-no"></span></div>`).join('')}</div>
          </div>
        </div>`));
      return;
    }

    let acertos = 0, feitos = 0, selEsq = null;
    const tentadoErrado = new Set(); // par esquerda já errado 1x não pontua

    const esq = shuffle(pares.map((p, i) => ({ t: p.esquerda, i })));
    const dir = shuffle(pares.map((p, i) => ({ t: p.direita, i })));

    host.appendChild(el(`
      <div class="lms-stack-4">
        <div class="mg-liga-top">
          <p class="lms-muted lms-small">${cfg.instrucao || 'Toque em um item da esquerda e depois no par dele à direita.'}</p>
          <span class="mg-liga-cont" id="mg-liga-cont">0 / ${total} 🔗</span>
        </div>
        <div class="mg-liga-grid">
          <div class="mg-liga-col" id="col-esq"></div>
          <div class="mg-liga-col" id="col-dir"></div>
        </div>
      </div>`));
    const colE = host.querySelector('#col-esq'), colD = host.querySelector('#col-dir');
    const cont = host.querySelector('#mg-liga-cont');

    esq.forEach(o => {
      const b = el(`<button class="mg-liga-item is-esq" data-i="${o.i}"><span>${o.t}</span><span class="mg-liga-no"></span></button>`);
      b.addEventListener('click', () => {
        if (b.classList.contains('is-par')) return;
        colE.querySelectorAll('.is-sel').forEach(x => x.classList.remove('is-sel'));
        b.classList.add('is-sel');
        selEsq = b;
      });
      colE.appendChild(b);
    });
    dir.forEach(o => {
      const b = el(`<button class="mg-liga-item is-dir" data-i="${o.i}"><span>${o.t}</span><span class="mg-liga-no"></span></button>`);
      b.addEventListener('click', () => {
        if (!selEsq || b.classList.contains('is-par')) return;
        const iE = +selEsq.dataset.i, iD = +b.dataset.i;
        if (iE === iD) {
          selEsq.classList.remove('is-sel');
          selEsq.classList.add('is-par', 'jc-pousa'); b.classList.add('is-par', 'jc-pousa');
          if (!tentadoErrado.has(iE)) { acertos++; juiceOk(b, '+1 🔗'); } else SFX.flip();
          feitos++; selEsq = null;
          cont.textContent = `${feitos} / ${total} 🔗`;
          if (feitos === total) { SFX.win(); setTimeout(() => done({ acertos, total }), 500); }
        } else {
          tentadoErrado.add(iE);
          juiceErr(b);
          b.classList.add('lms-shake'); selEsq.classList.add('lms-shake');
          setTimeout(() => host.querySelectorAll('.lms-shake').forEach(x => x.classList.remove('lms-shake')), 700);
        }
      });
      colD.appendChild(b);
    });
  },

  /* M5 · Classificação (pode / não pode) */
  classificacao(cfg, host, done, revisao) {
    const total = cfg.itens.length;

    // ── revisão: itens na ordem original (não precisa reembaralhar),
    // resposta guardada por índice original (a ordem mudava a cada
    // partida por causa do shuffle) ──
    if (revisao) {
      const guardadas = revisao.resposta || [];
      const mostra = (idx) => {
        const item = cfg.itens[idx];
        const escolhida = guardadas[idx];
        host.innerHTML = '';
        host.appendChild(el(`
          <div class="lms-stack-4">
            <span class="lms-pill is-primary">Item ${idx + 1} de ${total}</span>
            <div class="mg-cl-carta ${escolhida === item.categoria_correta ? 'is-ok' : 'is-err'}">
              <span class="mg-cl-tag">📦 Onde este item entra?</span>
              <h3>${item.item}</h3>
            </div>
            <div class="mg-cl-cats" id="cats"></div>
            <div id="mg-acao"></div>
          </div>`));
        const cats = host.querySelector('#cats');
        cfg.categorias.forEach((c, ci) => {
          const b = el(`<button class="mg-cl-cat c${ci % 4}" disabled>${c}</button>`);
          if (ci === item.categoria_correta) b.classList.add('is-right');
          else if (ci === escolhida) b.classList.add('is-badpick');
          cats.appendChild(b);
        });
        if (idx + 1 < total) {
          const nx = el(`<button class="lms-btn lms-btn-outline" style="width:100%">Próximo item →</button>`);
          nx.addEventListener('click', () => mostra(idx + 1));
          host.querySelector('#mg-acao').appendChild(nx);
        }
      };
      mostra(0);
      return;
    }

    const itens = shuffle(cfg.itens.map((o, i) => ({ ...o, i })));
    let idx = 0, acertos = 0;
    const respostas = []; // captura por índice ORIGINAL (cfg.itens), não o embaralhado

    const render = () => {
      if (idx >= total) return done({ acertos, total, resposta: respostas });
      const item = itens[idx];
      host.innerHTML = '';
      host.appendChild(el(`
        <div class="lms-stack-4">
          <div class="lms-row" style="justify-content:space-between">
            <span class="lms-pill is-primary">Item ${idx + 1} de ${total}</span>
            <span class="lms-small lms-muted">${cfg.instrucao || ''}</span>
          </div>
          <div class="mg-cl-carta lms-pop" id="mg-cl-carta">
            <span class="mg-cl-tag">📦 Onde este item entra?</span>
            <h3>${item.item}</h3>
          </div>
          <div class="mg-cl-cats" id="cats"></div>
          <div id="mg-fb"></div>
        </div>`));
      const cats = host.querySelector('#cats');
      const carta = host.querySelector('#mg-cl-carta');
      cfg.categorias.forEach((c, ci) => {
        const b = el(`<button class="mg-cl-cat c${ci % 4}">${c}</button>`);
        b.addEventListener('click', () => {
          const ok = ci === item.categoria_correta;
          respostas[item.i] = ci;
          if (ok) { acertos++; juiceOk(b); } else juiceErr(b);
          // a carta PERMANECE visível, ganhando o anel do veredito
          carta.classList.add(ok ? 'is-ok' : 'is-err');
          cats.querySelectorAll('button').forEach((x, xi) => {
            x.disabled = true;
            if (xi === item.categoria_correta) x.classList.add('is-right');
            else if (x === b) x.classList.add('is-badpick');
          });
          const fb = host.querySelector('#mg-fb');
          fb.innerHTML = '';
          fb.appendChild(el(`
            <div class="lms-stack-2">
              <div class="lms-alert ${ok ? 'is-success' : 'is-error'} lms-pop">
                ${ok ? '✅' : '❌'} <div><strong>${ok ? 'Correto!' : 'Ops!'}</strong> “${item.item}” → <strong>${cfg.categorias[item.categoria_correta]}</strong></div></div>
              <button class="lms-btn lms-btn-primary" style="width:100%">${idx + 1 === total ? 'Finalizar' : 'Próximo item'} →</button>
            </div>`));
          fb.querySelector('button').addEventListener('click', () => { idx++; render(); });
        });
        cats.appendChild(b);
      });
    };
    render();
  },

  /* M6 · Jogo da memória (layout randomizado a cada partida) */
  memoria(cfg, host, done, revisao) {
    const totalPares = cfg.cartas.length / 2;

    // conclui só quando todos os pares são achados — revisão mostra os
    // pares já casados, direto do config (deterministico, sem escolha
    // errada persistente pra guardar)
    if (revisao) {
      const porPar = {};
      cfg.cartas.forEach(c => { (porPar[c.par] ??= []).push(c.texto); });
      host.appendChild(el(`
        <div class="lms-stack-4">
          <p class="lms-muted lms-small">Os ${totalPares} pares encontrados:</p>
          <div class="lms-stack-2">${Object.values(porPar).map(([a, b]) => `
            <div class="mg-liga-item is-par" style="justify-content:space-between"><span>${a}</span><span>↔</span><span>${b}</span></div>
          `).join('')}</div>
        </div>`));
      return;
    }

    const cartas = shuffle(cfg.cartas.map((c, i) => ({ ...c, uid: i })));
    let aberta = null, travado = false, pares = 0, jogadas = 0;

    host.appendChild(el(`
      <div class="lms-stack-4">
        <div class="lms-row" style="justify-content:space-between">
          <p class="lms-muted lms-small">${cfg.instrucao || 'Encontre os pares.'}</p>
          <span class="lms-pill" id="mg-pares">0 / ${totalPares} pares</span>
        </div>
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px" id="grade"></div>
      </div>`));
    const grade = host.querySelector('#grade');

    cartas.forEach(c => {
      // carta com duas faces e virada 3D de verdade
      const d = el(`<div class="mg-carta" data-par="${c.par}">
        <div class="mem-flip">
          <div class="mem-face mem-frente">?</div>
          <div class="mem-face mem-verso">${c.texto}</div>
        </div>
      </div>`);
      d.addEventListener('click', () => {
        if (travado || d.classList.contains('is-aberta') || d.classList.contains('is-par')) return;
        d.classList.add('is-aberta');
        SFX.flip();
        if (!aberta) { aberta = d; return; }
        jogadas++;
        if (aberta.dataset.par === d.dataset.par) {
          aberta.classList.add('is-par'); d.classList.add('is-par');
          juiceOk(d, '+1 🃏');
          aberta = null; pares++;
          host.querySelector('#mg-pares').textContent = `${pares} / ${totalPares} pares`;
          if (pares === totalPares) { SFX.win(); setTimeout(() => done({ acertos: totalPares, total: totalPares }), 600); }
        } else {
          travado = true;
          SFX.err();
          const a = aberta;
          setTimeout(() => {
            a.classList.remove('is-aberta');
            d.classList.remove('is-aberta');
            aberta = null; travado = false;
          }, 900);
        }
      });
      grade.appendChild(d);
    });
  },

  /* M7 · Cenário — "O que você faria?" (decisões narrativas) */
  cenario(cfg, host, done, revisao) {
    let idx = 0, acertos = 0;
    const total = cfg.situacoes.length;
    const respostas = []; // captura: índice da opção escolhida em cada cena
    const letras = 'ABCDEFGH';

    if (revisao) {
      const guardadas = revisao.resposta || [];
      const mostra = (i) => {
        const sc = cfg.situacoes[i];
        const escolhida = guardadas[i];
        host.innerHTML = '';
        host.appendChild(el(`
          <div class="lms-stack-4">
            <div class="mg-cena">
              <div class="mg-cena-ico">${sc.icone || '🎬'}</div>
              <div class="mg-cena-corpo">
                <span class="mg-cena-lbl">Cena ${i + 1} de ${total}</span>
                <p class="mg-cena-txt">${sc.contexto}</p>
              </div>
            </div>
            <div class="lms-stack-2" id="mg-ops"></div>
            <div class="lms-alert is-info">${sc.feedback || ''}</div>
            <div id="mg-acao"></div>
          </div>`));
        const ops = host.querySelector('#mg-ops');
        sc.opcoes.forEach((o, oi) => {
          const b = el(`<button class="mg-cen-op" data-lock="1"><span class="mg-cen-op-badge">${letras[oi]}</span><span>${o.texto}</span></button>`);
          if (o.correta) b.classList.add('is-correct');
          else if (oi === escolhida) b.classList.add('is-wrong');
          ops.appendChild(b);
        });
        if (i + 1 < total) {
          const nx = el(`<button class="lms-btn lms-btn-outline" style="width:100%">Próxima cena →</button>`);
          nx.addEventListener('click', () => mostra(i + 1));
          host.querySelector('#mg-acao').appendChild(nx);
        }
      };
      mostra(0);
      return;
    }

    const proxima = () => {
      if (idx >= total) return done({ acertos, total, resposta: respostas });
      const sc = cfg.situacoes[idx];
      host.innerHTML = '';
      host.appendChild(el(`
        <div class="lms-stack-4 mg-slide">
          <div class="mg-dots">${cfg.situacoes.map((_, i) =>
            `<span class="mg-dot ${i < idx ? 'is-done' : (i === idx ? 'is-active' : '')}"></span>`).join('')}</div>
          <div class="mg-cena">
            <div class="mg-cena-ico">${sc.icone || '🎬'}</div>
            <div class="mg-cena-corpo">
              <span class="mg-cena-lbl">Cena ${idx + 1} de ${total}</span>
              <p class="mg-cena-txt">${sc.contexto}</p>
            </div>
          </div>
          <p class="mg-cena-perg">E você, o que faria?</p>
          <div class="lms-stack-2" id="mg-ops"></div>
          <div id="mg-fb"></div>
        </div>`));
      const ops = host.querySelector('#mg-ops');
      let travado = false;
      sc.opcoes.forEach((o, oi) => {
        const b = el(`<button class="mg-cen-op"><span class="mg-cen-op-badge">${letras[oi]}</span><span>${o.texto}</span></button>`);
        b.addEventListener('click', () => {
          if (travado) return;
          travado = true;
          respostas[idx] = oi;
          const ok = !!o.correta;
          if (ok) { acertos++; juiceOk(b, 'Boa decisão! ⭐'); } else juiceErr(b);
          [...ops.children].forEach((x, xi) => {
            x.setAttribute('data-lock', '1');
            if (sc.opcoes[xi].correta) x.classList.add('is-correct');
            else if (x === b) x.classList.add('is-wrong');
          });
          const fb = host.querySelector('#mg-fb');
          fb.appendChild(el(`
            <div class="lms-stack-2">
              <div class="lms-alert ${ok ? 'is-success' : 'is-error'} lms-pop">${ok ? '🎉' : '❌'} <div><strong>${ok ? 'Boa decisão!' : 'Cuidado!'}</strong> ${sc.feedback || ''}</div></div>
              <button class="lms-btn lms-btn-primary" style="width:100%">${idx + 1 === total ? 'Finalizar' : 'Próxima cena'} →</button>
            </div>`));
          fb.querySelector('button').addEventListener('click', () => { idx++; proxima(); });
        });
        ops.appendChild(b);
      });
    };
    proxima();
  },

  /* M8 · Arrastar — classificar itens em 2-4 zonas (toque: item → zona) */
  arrastar(cfg, host, done, revisao) {
    const total = cfg.itens.length;

    // só conclui com tudo classificado certo — revisão mostra o
    // agrupamento final direto do config, sem escolha errada pra guardar
    if (revisao) {
      host.appendChild(el(`
        <div class="lms-stack-4">
          <p class="lms-muted lms-small">Como cada item ficou classificado ao concluir:</p>
          <div class="mg-zonas" style="grid-template-columns:repeat(${cfg.zonas.length}, 1fr)">
            ${cfg.zonas.map((z, zi) => `
              <div class="mg-zona is-${z.cor || 'sky'}">
                <div class="mg-zona-titulo">${z.titulo}</div>
                ${cfg.itens.filter(it => (+it.zona) === zi).map(it => `<div class="mg-chip is-ok" style="margin-top:6px">${it.texto}</div>`).join('')}
              </div>`).join('')}
          </div>
        </div>`));
      return;
    }

    const itens = shuffle(cfg.itens.map((o, i) => ({ ...o, i })));
    let acertos = 0, feitos = 0, sel = null;
    const errouAntes = new Set();

    host.appendChild(el(`
      <div class="lms-stack-4">
        <p class="lms-muted lms-small">${cfg.instrucao || 'Toque em um item e depois na zona correta.'}</p>
        <div class="mg-pool" id="mg-pool"></div>
        <div class="mg-zonas" style="grid-template-columns:repeat(${cfg.zonas.length}, 1fr)" id="mg-zonas"></div>
        <p class="lms-small lms-muted" id="mg-conta">0 de ${total} classificados</p>
      </div>`));
    const pool = host.querySelector('#mg-pool'), zonas = host.querySelector('#mg-zonas');

    cfg.zonas.forEach((z, zi) => {
      const zona = el(`<div class="mg-zona is-${z.cor || 'sky'}" data-z="${zi}">
        <div class="mg-zona-titulo">${z.titulo}</div></div>`);
      zona.addEventListener('click', () => {
        if (!sel) return;
        const item = itens[+sel.dataset.i];
        if ((+item.zona) === zi) {
          sel.classList.remove('is-sel');
          sel.classList.add('is-ok', 'jc-pousa');
          zona.appendChild(sel);
          if (!errouAntes.has(item.i)) { acertos++; juiceOk(sel, '+1 ✓'); } else SFX.flip();
          feitos++; sel = null;
          host.querySelector('#mg-conta').textContent = `${feitos} de ${total} classificados`;
          if (feitos === total) { SFX.win(); setTimeout(() => done({ acertos, total }), 500); }
        } else {
          errouAntes.add(item.i);
          juiceErr(zona);
          sel.classList.add('lms-shake'); zona.classList.add('lms-shake');
          setTimeout(() => host.querySelectorAll('.lms-shake').forEach(x => x.classList.remove('lms-shake')), 600);
        }
      });
      zonas.appendChild(zona);
    });

    itens.forEach((o, i) => {
      const chip = el(`<button class="mg-chip" data-i="${i}">${o.texto}</button>`);
      chip.addEventListener('click', (ev) => {
        if (chip.classList.contains('is-ok')) return;
        ev.stopPropagation();
        pool.parentElement.querySelectorAll('.mg-chip.is-sel').forEach(x => x.classList.remove('is-sel'));
        chip.classList.add('is-sel');
        sel = chip;
      });
      pool.appendChild(chip);
    });
  },

  /* M9 · História — cenas sequenciais; cada cena pode ter imagem (HQ) */
  historia(cfg, host, done, revisao) {
    let idx = 0;
    const total = cfg.cenas.length;
    const proxima = () => {
      // em revisão não há "concluir" — só relê as cenas até a última
      if (revisao && idx >= total) return;
      if (!revisao && idx >= total) return done({ acertos: total, total });
      const sc = cfg.cenas[idx];
      host.innerHTML = '';
      host.appendChild(el(`
        <div class="lms-stack-4 mg-slide">
          <div class="mg-dots">${cfg.cenas.map((_, i) =>
            `<span class="mg-dot ${i < idx ? 'is-done' : (i === idx ? 'is-active' : '')}"></span>`).join('')}</div>
          ${sc.imagem ? `<img class="mg-hq-img" src="${sc.imagem}" alt="${sc.titulo || 'Cena ' + (idx + 1)}">` : ''}
          <div class="mg-quadro">
            <div class="mg-quadro-icone">${sc.icone || '📖'}</div>
            <div>
              ${sc.titulo ? `<p class="mg-quadro-titulo">${sc.titulo}</p>` : ''}
              <div class="mg-quadro-texto">${sc.texto}</div>
            </div>
          </div>
          ${revisao && idx + 1 === total ? '' : `<button class="lms-btn lms-btn-game lms-btn-lg" style="width:100%">${idx + 1 === total ? 'Concluir' : 'Próxima cena'} →</button>`}
        </div>`));
      host.querySelector('button')?.addEventListener('click', () => { idx++; proxima(); });
    };
    proxima();
  },

  /* M10 · Caça — grid de revelação (varra as células e descubra as ações) */
  caca(cfg, host, done, revisao) {
    const total = cfg.celulas.length;

    // só conclui achando todas — revisão mostra todas já reveladas
    if (revisao) {
      host.appendChild(el(`
        <div class="lms-stack-4">
          ${cfg.destaque || ''}
          <p class="lms-muted lms-small">As ${total} ações encontradas ao concluir:</p>
          <div class="mg-caca-painel" style="flex:none; width:100%">
            ${cfg.celulas.map(c => `<div class="mg-caca-card"><strong>${c.titulo}</strong><br><span class="lms-small">${c.descricao || ''}</span></div>`).join('')}
          </div>
        </div>`));
      return;
    }

    const celulas = shuffle(cfg.celulas.map((c, i) => ({ ...c, i })));
    let achadas = 0;
    host.appendChild(el(`
      <div class="lms-stack-4">
        ${cfg.destaque || ''}
        <p class="lms-muted lms-small">${cfg.instrucao || 'Clique nas células para revelar.'}</p>
        <div class="mg-caca-wrap">
          <div class="mg-caca-grid" id="mg-grid"></div>
          <div class="mg-caca-painel" id="mg-painel"><p class="lms-small lms-muted" id="mg-conta">0 / ${total} localizadas</p></div>
        </div>
      </div>`));
    const grid = host.querySelector('#mg-grid'), painel = host.querySelector('#mg-painel');
    celulas.forEach((c) => {
      const cel = el(`<button class="mg-celula">◌</button>`);
      cel.addEventListener('click', () => {
        if (cel.classList.contains('is-hit')) return;
        cel.classList.add('is-hit', 'jc-pousa');
        cel.textContent = '✓';
        achadas++;
        juiceOk(cel, '🔎');
        host.querySelector('#mg-conta').textContent = `${achadas} / ${total} localizadas`;
        painel.appendChild(el(`<div class="mg-caca-card lms-pop jc-pop"><strong>${c.titulo}</strong><br><span class="lms-small">${c.descricao || ''}</span></div>`));
        if (achadas === total) { SFX.win(); setTimeout(() => done({ acertos: total, total }), 600); }
      });
      grid.appendChild(cel);
    });
  },

  /* M11 · Podcast — transcrição narrada com player ilustrativo */
  podcast(cfg, host, done, revisao) {
    const barras = Array.from({ length: 26 }, (_, i) => `<span style="height:${6 + ((i * 7) % 15)}px"></span>`).join('');
    host.appendChild(el(`
      <div class="lms-stack-4">
        <div class="mg-podcast">
          <button class="mg-play" id="mg-play">▶</button>
          <div style="flex:1">
            <p class="mg-podcast-titulo">${cfg.titulo || 'Áudio do treinamento'}</p>
            <p class="lms-small lms-muted">${cfg.subtitulo || ''}</p>
          </div>
          <div class="mg-wave" id="mg-wave">${barras}</div>
        </div>
        <div class="lms-card" style="font-size:15px; line-height:1.7">
          ${cfg.linhas.map(l => `<p style="margin-bottom:12px"><span class="mg-quem">${l.quem}</span>${l.texto}</p>`).join('')}
        </div>
        ${revisao ? '' : '<button class="lms-btn lms-btn-primary lms-btn-lg" style="width:100%">Marquei como ouvido ✓</button>'}
      </div>`));
    const play = host.querySelector('#mg-play'), wave = host.querySelector('#mg-wave');
    play.addEventListener('click', () => {
      const tocando = play.textContent === '❚❚';
      play.textContent = tocando ? '▶' : '❚❚';
      wave.classList.toggle('is-on', !tocando);
    });
    if (revisao) return;
    host.querySelector('.lms-btn-primary').addEventListener('click', () => done({ acertos: 1, total: 1 }));
  },

  /* M12 · Sequência — toque nos passos na ordem correta. Cada passo é
     texto puro ou { texto, imagem, descricao }: com imagem o pool vira
     cartas (só a imagem, se cfg.so_imagem) e o acerto revela a descrição
     — a dinâmica de "passo a passo" com produtos. */
  sequencia(cfg, host, done, revisao) {
    const passos = cfg.passos.map(p => typeof p === 'string' ? { texto: p } : p);
    const total = passos.length;
    const comImg = passos.some(p => p.imagem);
    const soImg = comImg && !!cfg.so_imagem;
    const linha = (p, i) => `<div class="mg-passo is-ok"><span class="mg-passo-num">${i + 1}</span>${p.imagem ? `<img class="mg-passo-thumb" src="${p.imagem}" alt="">` : ''}${p.texto}${p.descricao ? `<span class="mg-passo-desc">${p.descricao}</span>` : ''}</div>`;
    if (revisao) {
      host.appendChild(el(`
        <div class="lms-stack-4">
          <p class="lms-muted lms-small">A ordem certa dos passos:</p>
          <div class="lms-stack-2">${passos.map(linha).join('')}</div>
        </div>`));
      return;
    }
    let proximo = 0, acertos = 0;
    const errouAntes = new Set();
    const embaralhados = shuffle(passos.map((p, i) => ({ ...p, i })));
    host.appendChild(el(`
      <div class="lms-stack-4">
        <p class="lms-muted lms-small">${cfg.instrucao || 'Toque nos passos na ordem correta.'}</p>
        <div class="lms-stack-2" id="mg-ordenados"></div>
        <div class="${comImg ? 'mg-passo-grid' : 'lms-stack-2'}" id="mg-restantes"></div>
      </div>`));
    const ordenados = host.querySelector('#mg-ordenados'), restantes = host.querySelector('#mg-restantes');
    embaralhados.forEach((p) => {
      const b = comImg
        ? el(`<button class="mg-passo is-card" aria-label="${p.texto}">${p.imagem ? `<img class="mg-passo-img" src="${p.imagem}" alt="${p.texto}">` : '<span class="mg-passo-img">🔹</span>'}${soImg && p.imagem ? '' : `<span>${p.texto}</span>`}</button>`)
        : el(`<button class="mg-passo">${p.texto}</button>`);
      b.addEventListener('click', () => {
        if (p.i === proximo) {
          if (!errouAntes.has(p.i)) { acertos++; juiceOk(b, `${proximo + 1}º ✓`); } else SFX.flip();
          b.remove();
          const ok = el(linha(p, proximo));
          ok.classList.add('jc-pousa');
          ordenados.appendChild(ok);
          proximo++;
          if (proximo === total) { SFX.win(); setTimeout(() => done({ acertos, total }), 500); }
        } else {
          errouAntes.add(proximo);
          juiceErr(b);
          b.classList.add('lms-shake');
          setTimeout(() => b.classList.remove('lms-shake'), 600);
        }
      });
      restantes.appendChild(b);
    });
  },

  /* M13 · Vídeo — YouTube, Vimeo ou arquivo direto.
     SEM timeline (nada de pular para o fim): a etapa conclui SOZINHA
     quando o vídeo termina. Se o player falhar, libera o botão manual.
     Controles próprios (volume, velocidade, modo palco) substituem os
     nativos — de propósito sem barra de progresso, pra não dar pra
     adiantar o vídeo. */
  video(cfg, host, done) {
    const url = cfg.url || '';
    const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([\w-]{6,20})/);
    const vm = url.match(/vimeo\.com\/(\d+)/);

    host.appendChild(el(`
      <div class="lms-stack-4">
        ${cfg.descricao ? `<p class="lms-muted lms-small">${cfg.descricao}</p>` : ''}
        <div class="mg-video" id="mg-vid">
          <div class="mg-vid-frame" id="mg-vid-frame"></div>
          <div class="mg-vid-controles">
            <button type="button" class="mg-vid-ctl" id="mg-vid-mute" title="Ativar/silenciar som">🔊</button>
            <input type="range" class="mg-vid-vol-slider" id="mg-vid-vol" min="0" max="100" value="100" title="Volume">
            <select class="mg-vid-vel" id="mg-vid-vel" title="Velocidade de reprodução">
              <option value="0.5">0.5x</option>
              <option value="0.75">0.75x</option>
              <option value="1" selected>1x</option>
              <option value="1.25">1.25x</option>
              <option value="1.5">1.5x</option>
              <option value="2">2x</option>
            </select>
            <span class="mg-vid-spacer"></span>
            <button type="button" class="mg-vid-ctl" id="mg-vid-palco" title="Ampliar — modo palco (sem sair da página)">⛶</button>
          </div>
        </div>
        <button class="lms-btn lms-btn-primary lms-btn-lg" disabled style="width:100%" id="mg-vid-btn">▶ Assista até o fim — a etapa conclui sozinha</button>
      </div>`));
    const box   = host.querySelector('#mg-vid');
    const frame = host.querySelector('#mg-vid-frame');
    const btn   = host.querySelector('#mg-vid-btn');
    const btnMute  = host.querySelector('#mg-vid-mute');
    const sliderVol = host.querySelector('#mg-vid-vol');
    const selVel    = host.querySelector('#mg-vid-vel');
    const btnPalco  = host.querySelector('#mg-vid-palco');
    let fim = false;
    const conclui = () => {
      if (fim) return;
      fim = true;
      btn.disabled = false;
      btn.textContent = '🎉 Vídeo concluído!';
      setTimeout(() => done({ acertos: 1, total: 1 }), 900);
    };
    const fallback = () => {          // player indisponível: não prende o aluno
      if (fim) return;
      btn.disabled = false;
      btn.textContent = 'Não consegui acompanhar o vídeo — continuar assim mesmo →';
    };
    btn.addEventListener('click', () => { if (!fim) done({ acertos: 1, total: 1 }); });

    // modo palco: overlay maior na própria página — nunca sai da plataforma.
    // "position:fixed" só cobre a viewport de verdade se nenhum ancestral
    // tiver transform — a animação de entrada da etapa (.mg-slide) deixa
    // um transform residual mesmo já tendo terminado visualmente, então
    // zera só ele (nunca mexe no iframe do vídeo em si).
    let noPalco = false;
    let ancoraTransform = null;
    const alternaPalco = () => {
      noPalco = !noPalco;
      if (noPalco && !ancoraTransform) {
        let p = box.parentElement;
        while (p && p !== document.body) {
          if (getComputedStyle(p).transform !== 'none') {
            ancoraTransform = p;
            // "animation" (não só "transform") porque uma animação ainda
            // ativa sobrescreve o transform inline enquanto estiver rodando
            p.style.animation = 'none';
            p.style.transform = 'none';
            break;
          }
          p = p.parentElement;
        }
      }
      box.classList.toggle('is-palco', noPalco);
      btnPalco.textContent = noPalco ? '🗗' : '⛶';
      btnPalco.title = noPalco ? 'Encolher' : 'Ampliar — modo palco (sem sair da página)';
    };
    btnPalco.addEventListener('click', alternaPalco);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && noPalco) alternaPalco(); });

    // ── liga volume/velocidade da UI a um "controlador" concreto ──
    // cada ramo (YT/Vimeo/arquivo) só precisa expor {volume, mudo, taxa}
    let ctl = null;
    let mudo = false;
    btnMute.addEventListener('click', () => {
      mudo = !mudo;
      btnMute.textContent = mudo ? '🔇' : '🔊';
      ctl?.mudo(mudo);
    });
    sliderVol.addEventListener('input', () => {
      const v = Number(sliderVol.value);
      if (v > 0 && mudo) { mudo = false; btnMute.textContent = '🔊'; }
      ctl?.volume(v);
    });
    selVel.addEventListener('change', () => ctl?.taxa(Number(selVel.value)));

    if (yt) {
      // YouTube IFrame API: controls=0 esconde a timeline; ENDED conclui
      const alvo = document.createElement('div');
      frame.appendChild(alvo);
      let player = null;
      const monta = () => { player = new YT.Player(alvo, {
        videoId: yt[1],
        playerVars: { controls: 0, disablekb: 1, rel: 0, playsinline: 1, fs: 0 },
        events: {
          onReady: () => {
            ctl = {
              volume: (v) => player.setVolume(v),
              mudo: (m) => (m ? player.mute() : player.unMute()),
              taxa: (t) => player.setPlaybackRate(t),
            };
          },
          onStateChange: (ev) => { if (ev.data === YT.PlayerState.ENDED) conclui(); },
          onError: fallback,
        },
      }); };
      if (window.YT && window.YT.Player) monta();
      else {
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        const antes = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => { if (antes) antes(); monta(); };
        s.onerror = fallback;
        document.head.appendChild(s);
        setTimeout(() => { if (!window.YT || !window.YT.Player) fallback(); }, 8000);
      }
    } else if (vm) {
      // Vimeo Player SDK: dá acesso real a volume/velocidade, além do
      // evento "ended" (antes era só postMessage manual, sem controle).
      const f = el(`<iframe src="https://player.vimeo.com/video/${vm[1]}" allow="autoplay; fullscreen; picture-in-picture"></iframe>`);
      frame.appendChild(f);
      const monta = () => {
        const player = new window.Vimeo.Player(f);
        ctl = {
          volume: (v) => player.setVolume(v / 100).catch(() => {}),
          mudo: (m) => player.setVolume(m ? 0 : Number(sliderVol.value) / 100).catch(() => {}),
          taxa: (t) => player.setPlaybackRate(t).catch(() => {}),
        };
        player.on('ended', conclui);
        player.on('error', fallback);
      };
      if (window.Vimeo && window.Vimeo.Player) monta();
      else {
        const s = document.createElement('script');
        s.src = 'https://player.vimeo.com/api/player.js';
        s.onload = monta;
        s.onerror = fallback;
        document.head.appendChild(s);
        setTimeout(() => { if (!window.Vimeo) fallback(); }, 8000);
      }
    } else {
      // arquivo direto: player próprio sem barra de progresso (sem pulos)
      const v = el(`<video src="${url}" playsinline preload="metadata"></video>`);
      // vídeo em pé (9:16, gravado no celular): o quadro acompanha a proporção
      v.addEventListener('loadedmetadata', () => { if (v.videoHeight > v.videoWidth) frame.classList.add('is-vertical'); }, { once: true });
      const play = el(`<button type="button" class="mg-vid-play" aria-label="Reproduzir">▶</button>`);
      frame.appendChild(v);
      frame.appendChild(play);
      ctl = {
        volume: (val) => { v.volume = val / 100; },
        mudo: (m) => { v.muted = m; },
        taxa: (t) => { v.playbackRate = t; },
      };
      const alterna = () => { v.paused ? v.play() : v.pause(); };
      play.addEventListener('click', alterna);
      v.addEventListener('click', alterna);
      v.addEventListener('play',  () => { play.textContent = '❚❚'; play.classList.add('is-tocando'); });
      v.addEventListener('pause', () => { play.textContent = '▶'; play.classList.remove('is-tocando'); });
      v.addEventListener('ended', () => { play.style.display = 'none'; conclui(); });
      v.addEventListener('error', fallback);
    }
  },

  /* M14 · Áudio real — player nativo; concluir libera após dar o play */
  audio(cfg, host, done) {
    host.appendChild(el(`
      <div class="lms-stack-4">
        <div class="mg-podcast" style="flex-wrap:wrap">
          <div style="flex:1; min-width:200px">
            <p class="mg-podcast-titulo">${cfg.titulo || 'Áudio do treinamento'}</p>
            <p class="lms-small lms-muted">${cfg.subtitulo || ''}</p>
          </div>
          <audio class="mg-audio-el" controls preload="metadata" src="${cfg.url}"></audio>
        </div>
        ${cfg.transcricao && cfg.transcricao.length ? `
        <details class="lms-card" style="font-size:14.5px; line-height:1.65">
          <summary style="cursor:pointer; font-family:var(--lms-font-ui); font-weight:600">📄 Ver transcrição</summary>
          <div style="margin-top:12px">${cfg.transcricao.map(l => `<p style="margin-bottom:10px">${l.quem ? `<span class="mg-quem">${l.quem}</span>` : ''}${l.texto}</p>`).join('')}</div>
        </details>` : ''}
        <button class="lms-btn lms-btn-primary lms-btn-lg" disabled style="width:100%" title="Dê o play para liberar">Marquei como ouvido ✓</button>
      </div>`));
    const btn = host.querySelector('.lms-btn-primary');
    host.querySelector('audio').addEventListener('play', () => { btn.disabled = false; }, { once: true });
    btn.addEventListener('click', () => done({ acertos: 1, total: 1 }));
  },

  /* M15 · Caça-palavras — grade gerada no construtor; seleção por
     clique na primeira e na última letra. Dica 💡 revela, mas não pontua. */
  caca_palavras(cfg, host, done) {
    const tam = cfg.tam;
    const palavras = cfg.palavras.map((p, i) => ({ ...p, i, achada: false, comDica: false }));
    const total = palavras.length;
    let inicio = null, achadas = 0, acertos = 0;

    host.appendChild(el(`
      <div class="lms-stack-4">
        <p class="lms-muted lms-small">${cfg.instrucao || 'Clique na primeira e na última letra de cada palavra.'}</p>
        <div class="mg-cp-wrap">
          <div class="mg-cp-grade" id="cp-grade" style="grid-template-columns:repeat(${tam}, auto)"></div>
          <div class="mg-cp-lista" id="cp-lista"></div>
        </div>
      </div>`));
    const grade = host.querySelector('#cp-grade'), lista = host.querySelector('#cp-lista');
    const cel = (l, c) => grade.children[l * tam + c];

    // células da palavra i (da posição inicial na direção dir)
    const celulasDe = (p) => Array.from({ length: p.palavra.length }, (_, k) =>
      p.dir === 'h' ? [p.linha, p.col + k] : [p.linha + k, p.col]);

    const marcaAchada = (p, pontua) => {
      p.achada = true; achadas++;
      if (pontua) acertos++;
      // cascata: as letras da palavra pipocam em sequência
      celulasDe(p).forEach(([l, c], k) => {
        const celula = cel(l, c);
        celula.classList.add('is-found');
        celula.style.animation = `jc-pop .3s ease ${k * 55}ms both`;
      });
      const fimCel = celulasDe(p).slice(-1)[0];
      juiceOk(cel(fimCel[0], fimCel[1]), p.palavra + ' ✓');
      lista.children[p.i].classList.add('is-found');
      if (achadas === total) { SFX.win(); setTimeout(() => done({ acertos, total }), 700); }
    };

    for (let l = 0; l < tam; l++) for (let c = 0; c < tam; c++) {
      const d = el(`<div class="mg-cp-cel" data-l="${l}" data-c="${c}">${cfg.grade[l][c]}</div>`);
      d.addEventListener('click', () => {
        if (!inicio) { inicio = d; d.classList.add('is-sel'); return; }
        const [l0, c0] = [+inicio.dataset.l, +inicio.dataset.c];
        inicio.classList.remove('is-sel');
        // acha a palavra cujo par início/fim bate (nos dois sentidos)
        const alvo = palavras.find(p => {
          if (p.achada) return false;
          const cs = celulasDe(p);
          const [a, b] = [cs[0], cs[cs.length - 1]];
          return (a[0] === l0 && a[1] === c0 && b[0] === l && b[1] === c)
              || (b[0] === l0 && b[1] === c0 && a[0] === l && a[1] === c);
        });
        inicio = null;
        if (alvo) marcaAchada(alvo, !alvo.comDica);
        else { SFX.err(); d.classList.add('lms-shake'); setTimeout(() => d.classList.remove('lms-shake'), 500); }
      });
      grade.appendChild(d);
    }

    palavras.forEach(p => {
      const linha = el(`<div class="mg-cp-palavra">${p.palavra}<button type="button" class="mg-cp-dica" title="Piscar a palavra no caça (não pontua)">💡</button></div>`);
      linha.querySelector('.mg-cp-dica').addEventListener('click', () => {
        if (p.achada) return;
        p.comDica = true;
        celulasDe(p).forEach(([l, c]) => {
          cel(l, c).classList.add('is-hint');
          setTimeout(() => cel(l, c).classList.remove('is-hint'), 2100);
        });
      });
      lista.appendChild(linha);
    });
  },

  /* M16 · Cruzadinha — lousa escura com contagem de 3 minutos.
     O tempo esgotado libera o "👀 Ver respostas" (pontua o que estava
     certo até ali). Para o ADMIN o botão já nasce ativo e o gabarito
     é plotado nos lugares certos, em cascata. */
  cruzadinha(cfg, host, done) {
    const { linhas, cols } = cfg;
    const palavras = cfg.palavras;
    const total = palavras.length;
    const admin = typeof EU !== 'undefined' && EU.papel === 'admin';
    const norm = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase();

    // mapa das células usadas: chave "l,c" → letra correta
    const letras = {}, numeros = {};
    palavras.forEach(p => {
      numeros[p.linha + ',' + p.col] = numeros[p.linha + ',' + p.col] || p.n;
      [...p.palavra].forEach((ch, k) => {
        const l = p.dir === 'h' ? p.linha : p.linha + k;
        const c = p.dir === 'h' ? p.col + k : p.col;
        letras[l + ',' + c] = ch;
      });
    });

    const bloco = (dir) => {
      const ps = palavras.filter(p => p.dir === dir);
      if (!ps.length) return '';
      return `<div class="mg-cz-bloco"><p>${dir === 'h' ? '→ Horizontais' : '↓ Verticais'}</p>
        ${ps.map(p => `<div class="mg-cz-dica" data-n="${p.n}"><span class="cz-dnum">${p.n}</span><span>${p.dica}</span></div>`).join('')}</div>`;
    };

    host.appendChild(el(`
      <div class="lms-stack-4">
        <div class="lms-row" style="justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap">
          <p class="lms-muted lms-small" style="flex:1; min-width:200px; margin:0">${cfg.instrucao || 'Preencha a cruzadinha com as dicas abaixo.'}</p>
          <span class="mg-cz-timer" id="cz-timer">⏱ 3:00</span>
        </div>
        <div class="mg-cz-tabua">
          <div class="mg-cz-grade" id="cz-grade" style="grid-template-columns:repeat(${cols}, auto)"></div>
        </div>
        <div class="mg-cz-mesa">${bloco('h')}${bloco('v')}</div>
        <div class="lms-row" style="gap:10px; flex-wrap:wrap">
          <button class="lms-btn lms-btn-primary lms-btn-lg" style="flex:1; min-width:200px" id="cz-conferir">Conferir respostas ✓</button>
          <button class="lms-btn lms-btn-outline lms-btn-lg ${admin ? '' : 'lms-hide'}" id="cz-revelar">${admin ? '🛠 Ver respostas' : '👀 Ver respostas'}</button>
        </div>
      </div>`));
    const grade = host.querySelector('#cz-grade');
    const btnConferir = host.querySelector('#cz-conferir');
    const btnRevelar = host.querySelector('#cz-revelar');
    const tEl = host.querySelector('#cz-timer');

    for (let l = 0; l < linhas; l++) for (let c = 0; c < cols; c++) {
      const k = l + ',' + c;
      if (letras[k] === undefined) { grade.appendChild(el('<div class="cz-vazio" style="width:40px;height:40px"></div>')); continue; }
      const d = el(`<div class="mg-cz-cel" data-k="${k}">${numeros[k] ? `<span class="mg-cz-num">${numeros[k]}</span>` : ''}<input maxlength="1" autocomplete="off" ${numeros[k] ? '' : ''}></div>`);
      // pular para a próxima célula ao digitar
      d.querySelector('input').addEventListener('input', (ev) => {
        if (!ev.target.value) return;
        const inputs = [...grade.querySelectorAll('input:not([disabled])')];
        const prox = inputs[inputs.indexOf(ev.target) + 1];
        if (prox) prox.focus();
      });
      grade.appendChild(d);
    }
    const celInput = (k) => grade.querySelector(`[data-k="${k}"]`);

    // avalia as palavras; com marcar=true pinta certo/errado e risca dicas
    const avalia = (marcar) => {
      let acertos = 0;
      palavras.forEach(p => {
        let ok = true;
        const celulasP = [];
        [...p.palavra].forEach((ch, i) => {
          const l = p.dir === 'h' ? p.linha : p.linha + i;
          const c = p.dir === 'h' ? p.col + i : p.col;
          const celula = celInput(l + ',' + c);
          const certo = norm(celula.querySelector('input').value) === norm(ch);
          if (!certo) ok = false;
          if (marcar) {
            celula.classList.toggle('is-ok', certo);
            celula.classList.toggle('is-erro', !certo);
          }
          celulasP.push(celula);
        });
        if (ok) {
          acertos++;
          if (marcar) {
            celulasP.forEach((celula, k) => celula.style.animation = `jc-pop .3s ease ${k * 45}ms both`);
            host.querySelector(`.mg-cz-mesa [data-n="${p.n}"]`)?.classList.add('is-ok');
          }
        }
      });
      return acertos;
    };

    // ⏱ contagem de 3 minutos: esgotou, o aluno ganha o "ver respostas"
    let restam = 180;
    const relogio = setInterval(() => {
      if (!tEl.isConnected) return clearInterval(relogio);
      restam--;
      tEl.textContent = `⏱ ${Math.floor(restam / 60)}:${String(restam % 60).padStart(2, '0')}`;
      if (restam <= 30) tEl.classList.add('is-urgente');
      if (restam <= 0) {
        clearInterval(relogio);
        tEl.textContent = '⏱ 0:00';
        if (btnRevelar.classList.contains('lms-hide')) {
          btnRevelar.classList.remove('lms-hide');
          btnRevelar.classList.add('jc-pop');
          SFX.flip();
        }
      }
    }, 1000);
    const encerra = (acertos) => { clearInterval(relogio); setTimeout(() => done({ acertos, total }), 900); };

    let conferiu = false, primeiraNota = 0, revelado = false;

    btnConferir.addEventListener('click', () => {
      if (revelado) return encerra(primeiraNota);
      const acertos = avalia(true);
      if (acertos === total) SFX.win(); else if (acertos > 0) SFX.ok(); else SFX.err();
      if (!conferiu) {
        conferiu = true;
        primeiraNota = acertos;                       // a 1ª conferência vale a nota
        if (acertos === total) { juiceOk(btnConferir, 'Cruzadinha completa! 🏆'); return encerra(acertos); }
        btnConferir.textContent = 'Revelar gabarito e finalizar →';
      } else {
        revela(primeiraNota, true);
      }
    });

    // plota o gabarito em cascata; encerraDepois=true finaliza sozinho
    const revela = (nota, encerraDepois) => {
      revelado = true;
      clearInterval(relogio);
      const chaves = Object.keys(letras);
      chaves.forEach((k, i) => {
        const celula = celInput(k);
        const inp = celula.querySelector('input');
        setTimeout(() => {
          inp.value = letras[k];
          inp.disabled = true;
          celula.classList.remove('is-erro');
          celula.classList.add('is-ok');
          celula.style.animation = 'jc-pop .28s ease both';
          if (i % 4 === 0) SFX.flip();
        }, i * 45);
      });
      host.querySelectorAll('.mg-cz-mesa .mg-cz-dica').forEach(d2 => d2.classList.add('is-ok'));
      btnRevelar.classList.add('lms-hide');
      if (encerraDepois) setTimeout(() => encerra(nota), chaves.length * 45 + 800);
      else {
        btnConferir.textContent = 'Continuar →';
        btnConferir.onclick = null;
      }
    };

    btnRevelar.addEventListener('click', () => {
      // pontua o que estava certo até aqui (ou a 1ª conferência, se houve)
      const nota = conferiu ? primeiraNota : avalia(false);
      primeiraNota = nota;
      revela(nota, false);
      btnConferir.textContent = 'Continuar →';
    });
  },

  /* M17 · Jogo dos erros — encontre os pontos na imagem.
     Cada 2 cliques errados custam 1 ponto da nota. */
  sete_erros(cfg, host, done) {
    const pontos = cfg.pontos.map((p, i) => ({ ...p, i, achado: false }));
    const total = pontos.length;
    const raio = +cfg.raio || 6;   // % da largura
    let achados = 0, errados = 0;

    host.appendChild(el(`
      <div class="lms-stack-4">
        <div class="lms-row" style="justify-content:space-between">
          <p class="lms-muted lms-small">${cfg.instrucao || 'Clique nos pontos escondidos na imagem.'}</p>
          <span class="lms-pill" id="se-conta">0 / ${total}</span>
        </div>
        <div class="mg-se-wrap" id="se-wrap"><img src="${cfg.imagem}" alt="Encontre os pontos" draggable="false"></div>
        <div id="se-rotulos" class="lms-stack-2"></div>
      </div>`));
    const wrap = host.querySelector('#se-wrap');

    wrap.addEventListener('click', (ev) => {
      const r = wrap.getBoundingClientRect();
      const x = (ev.clientX - r.left) / r.width * 100;
      const y = (ev.clientY - r.top) / r.height * 100;
      const alvo = pontos.find(p => !p.achado &&
        Math.hypot(x - p.x, (y - p.y) * (r.height / r.width)) <= raio);
      if (alvo) {
        alvo.achado = true; achados++;
        const d = 2 * raio;
        wrap.appendChild(el(`<div class="mg-se-marca" style="left:${alvo.x}%; top:${alvo.y}%; width:${d}%; aspect-ratio:1"></div>`));
        confeteMini(ev.clientX, ev.clientY, 14);
        SFX.ok();
        if (alvo.rotulo) host.querySelector('#se-rotulos').appendChild(el(
          `<div class="lms-alert is-success lms-pop jc-pop" style="padding:8px 14px">✔ <div>${alvo.rotulo}</div></div>`));
        host.querySelector('#se-conta').textContent = `${achados} / ${total}`;
        if (achados === total) {
          const acertos = Math.max(0, total - Math.floor(errados / 2));
          SFX.win();
          setTimeout(() => done({ acertos, total }), 800);
        }
      } else {
        errados++;
        SFX.err();
        const x2 = el(`<div class="mg-se-x" style="left:${x}%; top:${y}%">✕</div>`);
        wrap.appendChild(x2);
        setTimeout(() => x2.remove(), 1000);
      }
    });
  },

  /* M18 · Investigação — narrativa ramificada: a escolha define o caminho.
     Cada carta pode ter título, pergunta e ÁUDIO (fala de um personagem).
     Modo "dominio": errou → feedback corretivo, a opção sai do jogo e o
     aluno escolhe de novo; só avança acertando. Conta como acerto só a
     escolha certa de primeira (mastery learning: prática com feedback,
     sem punição por tentar; a nota reflete o domínio real). */
  investigacao(cfg, host, done) {
    let acertos = 0, total = 0, passos = 0;
    const dominio = cfg.modo === 'dominio';
    const abre = (idx) => {
      if (passos++ > 40) return done({ acertos, total: Math.max(1, total) });  // trava anti-loop
      const carta = cfg.cartas[idx];
      if (!carta) return done({ acertos, total: Math.max(1, total) });
      host.innerHTML = '';
      host.appendChild(el(`
        <div class="lms-stack-4 mg-slide">
          <div class="mg-quadro mg-inv-pista">
            <div class="mg-quadro-icone">${carta.icone || '🕵️'}</div>
            <div style="flex:1; min-width:0">
              <p class="mg-quadro-titulo">${carta.titulo || `Pista ${idx + 1}`}</p>
              <div class="mg-quadro-texto">${carta.pista}</div>
              ${carta.audio ? `<div class="mg-inv-audio">${carta.audio_quem ? `<span class="mg-quem" style="margin:0">${carta.audio_quem}</span>` : ''}<audio class="mg-audio-el" controls preload="metadata" src="${carta.audio}"></audio></div>` : ''}
            </div>
          </div>
          ${carta.pergunta ? `<p style="font-family:var(--lms-font-ui); font-weight:600">${carta.pergunta}</p>` : ''}
          <div class="lms-stack-2" id="inv-ops"></div>
          <div id="inv-fb"></div>
        </div>`));
      const ops = host.querySelector('#inv-ops');
      let travado = false, errou = false;
      total++;
      carta.opcoes.forEach(o => {
        const b = el(`<button class="lms-answer">${o.texto}</button>`);
        b.addEventListener('click', () => {
          if (travado || b.disabled) return;
          const ok = !!o.correta;
          const fb = host.querySelector('#inv-fb');
          fb.innerHTML = '';
          if (dominio && !ok) {
            errou = true;
            juiceErr(b);
            b.classList.add('is-wrong');
            b.disabled = true;
            fb.appendChild(el(`<div class="lms-alert is-error lms-pop">⚠️ <div>${o.feedback || 'Não é por aí — escolha outra opção.'}</div></div>`));
            return;
          }
          travado = true;
          if (ok && !errou) acertos++;
          if (ok) juiceOk(b, '🔎 pista!'); else juiceErr(b);
          b.classList.add(ok ? 'is-correct' : 'is-wrong');
          [...ops.children].forEach(x => { x.disabled = true; });
          const fim = o.vai === null || o.vai === undefined || o.vai < 0;
          fb.appendChild(el(`
            <div class="lms-stack-2">
              ${o.feedback ? `<div class="lms-alert ${ok ? 'is-success' : 'is-error'} lms-pop">${ok ? '🔎' : '⚠️'} <div>${o.feedback}</div></div>` : ''}
              <button class="lms-btn lms-btn-primary" style="width:100%">${fim ? (cfg.rotulo_fim || 'Encerrar investigação') : (cfg.rotulo_seguir || 'Seguir a pista')} →</button>
            </div>`));
          fb.querySelector('.lms-btn-primary').addEventListener('click', () =>
            fim ? done({ acertos, total: Math.max(1, total) }) : abre(+o.vai));
        });
        ops.appendChild(b);
      });
    };
    abre(0);
  },

  /* M19 · Tabuleiro — trilha serpenteada estilo jogo de mesa: casas de
     pergunta intercaladas com casas livres, peão que anda casa a casa e
     dado com pips. Errou a pergunta? Volta uma casa. Chegou ao 🏁? Fim. */
  tabuleiro(cfg, host, done) {
    const casas = cfg.casas;
    const livres = ['🌿', '🌸', '🍃', '🧭', '🌼', '🐝', '☁️', '🍀'];
    const flavor = ['Caminho livre — jogue de novo!', 'Vento a favor! Mais uma jogada.',
                    'Boa! 🎲 de novo.', 'Rota certa — role o dado!'];

    // trilha: início → pergunta, livre, pergunta, livre… → chegada
    const trilha = [{ tipo: 'inicio' }];
    casas.forEach((_, i) => {
      trilha.push({ tipo: 'pergunta', idx: i });
      trilha.push({ tipo: 'livre', emoji: livres[i % livres.length] });
    });
    trilha.push({ tipo: 'fim' });
    const len = trilha.length;
    const fim = len - 1;
    const cols = window.innerWidth <= 700 ? 4 : Math.min(7, Math.max(5, Math.ceil(len / 2)));

    let pos = 0, acertos = 0, total = 0, animando = false;
    const respondidas = new Set();

    host.appendChild(el(`
      <div class="lms-stack-4">
        <p class="lms-muted lms-small">${cfg.instrucao || 'Jogue o dado, ande as casas e responda onde cair. Errou? Volta uma casa. Chegue ao 🏁!'}</p>
        <div class="mg-tab-mar">
          <div class="mg-tab-board" id="tab-board" style="grid-template-columns:repeat(${cols}, 1fr)"></div>
        </div>
        <div class="mg-tab-mesa">
          <button class="mg-dado" id="tab-dado" title="Jogar o dado" aria-label="Jogar o dado">
            ${Array.from({ length: 9 }, (_, i) => `<span class="pip" data-p="${i}"></span>`).join('')}
          </button>
          <span class="mg-tab-status" id="tab-status">🎲 Jogue o dado para começar!</span>
        </div>
        <div id="tab-pergunta"></div>
      </div>`));
    const board = host.querySelector('#tab-board');
    const dado = host.querySelector('#tab-dado');
    const status = host.querySelector('#tab-status');

    // pips do dado: padrões para 1, 2 e 3 no grid 3×3 (índices 0-8)
    const PIPS = { 0: [], 1: [4], 2: [0, 8], 3: [0, 4, 8] };
    const mostraDado = (v) => host.querySelectorAll('.mg-dado .pip').forEach((p, i) =>
      p.classList.toggle('on', PIPS[v].includes(i)));
    mostraDado(0);

    // células na serpentina: linha ímpar corre da direita para a esquerda
    const celulas = [];
    trilha.forEach((t, i) => {
      const linha = Math.floor(i / cols);
      const coluna = linha % 2 ? cols - 1 - (i % cols) : i % cols;
      const rotulo = t.tipo === 'inicio' ? '🚩' : t.tipo === 'fim' ? '🏁' : t.tipo === 'livre' ? t.emoji : '❓';
      const d = el(`<div class="mg-tab-casa is-${t.tipo}">${i > 0 && i < fim ? `<span class="mg-tab-num">${i}</span>` : ''}${rotulo}</div>`);
      d.style.gridRowStart = linha + 1;
      d.style.gridColumnStart = coluna + 1;
      board.appendChild(d);
      celulas.push(d);
    });
    const peao = el('<span class="mg-tab-peao">🚶</span>');
    board.appendChild(peao);

    const centraPeao = (i) => {
      const c = celulas[i];
      peao.style.left = (c.offsetLeft + c.offsetWidth / 2) + 'px';
      peao.style.top = (c.offsetTop + c.offsetHeight * 0.32) + 'px';
      celulas.forEach((x, xi) => x.classList.toggle('is-atual', xi === i));
    };
    centraPeao(0);                                   // offsetLeft força o layout na hora
    setTimeout(() => centraPeao(pos), 300);          // re-centra após fontes/reflow
    window.addEventListener('resize', () => centraPeao(pos));

    // anda casa a casa, com pulinho a cada passo
    const anda = (destino, aoChegar) => {
      animando = true;
      const passo = destino > pos ? 1 : -1;
      const tick = () => {
        pos += passo;
        peao.classList.remove('is-hop');
        void peao.offsetWidth;                 // reinicia a animação
        peao.classList.add('is-hop');
        centraPeao(pos);
        if (pos !== destino) setTimeout(tick, 240);
        else { animando = false; aoChegar(); }
      };
      setTimeout(tick, 120);
    };

    const chegou = () => {
      status.textContent = '🏁 Chegou! Travessia completa!';
      SFX.win();
      const r = celulas[fim].getBoundingClientRect();
      confeteMini(r.left + r.width / 2, r.top + r.height / 2, 26);
      setTimeout(() => done({ acertos, total: Math.max(1, total) }), 900);
    };

    const pergunta = (idx, celula) => {
      const q = casas[idx];
      const hostP = host.querySelector('#tab-pergunta');
      hostP.innerHTML = '';
      hostP.appendChild(el(`
        <div class="lms-card-game lms-stack-2 lms-pop" style="padding:22px; border-left:6px solid var(--lms-primary)">
          <span class="lms-pill is-primary">❓ Desafio da casa</span>
          <h4 style="margin:6px 0 4px">${q.pergunta}</h4>
          <div class="lms-stack-2" id="tab-ops"></div>
        </div>`));
      const ops = hostP.querySelector('#tab-ops');
      let travado = false;
      q.alternativas.forEach((a, ai) => {
        const b = el(`<button class="lms-answer">${a}</button>`);
        b.addEventListener('click', () => {
          if (travado) return;
          travado = true;
          total++;
          const ok = q.corretas.includes(ai);
          b.classList.add(ok ? 'is-correct' : 'is-wrong');
          if (!ok) ops.children[q.corretas[0]]?.classList.add('is-correct');
          respondidas.add(idx);
          celula.classList.add(ok ? 'is-feita' : 'is-errada');
          celula.appendChild(el(`<span class="mg-tab-check">${ok ? '✅' : '❌'}</span>`));
          if (ok) { acertos++; juiceOk(b); status.textContent = '✅ Boa! Jogue o dado de novo.'; }
          else { juiceErr(b); status.textContent = '❌ Errou — o navio recua uma casa. Jogue de novo.'; }
          setTimeout(() => {
            hostP.innerHTML = '';
            if (!ok && pos > 0) anda(pos - 1, () => { dado.disabled = false; });
            else dado.disabled = false;
          }, 1300);
        });
        ops.appendChild(b);
      });
    };

    dado.addEventListener('click', () => {
      if (dado.disabled || animando) return;
      dado.disabled = true;
      dado.classList.add('is-rolando');
      SFX.dado();
      const valor = 1 + Math.floor(Math.random() * 3);
      let flicker = 0;
      const gira = setInterval(() => mostraDado(1 + (flicker++ % 3)), 90);
      setTimeout(() => {
        clearInterval(gira);
        dado.classList.remove('is-rolando');
        mostraDado(valor);
        const destino = Math.min(pos + valor, fim);
        anda(destino, () => {
          if (pos === fim) return chegou();
          const casa = trilha[pos];
          if (casa.tipo === 'pergunta' && !respondidas.has(casa.idx)) {
            status.textContent = '❓ Responda o desafio para seguir viagem:';
            pergunta(casa.idx, celulas[pos]);
          } else {
            status.textContent = '🎲 ' + flavor[Math.floor(Math.random() * flavor.length)];
            dado.disabled = false;
          }
        });
      }, 620);
    });
  },
};

/* ════════ PLAYER ════════ */
const Player = {
  idx: 0,
  revisando: null,   // índice sendo revisado (null = jogando normalmente)
  inicioEtapa: 0,
  eventos: [],
  resultados: [],

  passos() {
    const wrap = document.getElementById('pl-passos');
    wrap.innerHTML = '';
    // botão de som dos minigames (preferência lembrada) — fica no topo da espinha
    const som = el(`<button type="button" class="jc-som pl-som-vert" title="Sons dos minigames">${SFX.ligado() ? '🔊' : '🔇'}</button>`);
    som.addEventListener('click', () => { som.textContent = SFX.alterna() ? '🔊' : '🔇'; SFX.ok(); });
    wrap.appendChild(som);
    ETAPAS.forEach((e2, i) => {
      if (i > 0) wrap.appendChild(el('<div class="pl-linha-vert"></div>'));
      const emRevisao = this.revisando === i;
      const cls = (i < this.idx ? 'is-done' : (i === this.idx && this.revisando === null ? 'is-current' : '')) + (emRevisao ? ' is-revisando' : '');
      const passo = el(`
        <div class="pl-passo-vert ${cls}" title="${e2.titulo}">
          <div class="pl-passo-num">${i < this.idx ? '✓' : i + 1}</div>
          <div class="pl-passo-titulo">${e2.titulo}</div>
        </div>`);
      // etapas já concluídas dão pra reabrir — só pra REVER o que foi
      // respondido, nunca pra mudar (ver Player.revisar)
      if (e2.concluida) {
        passo.classList.add('pl-passo-clicavel');
        passo.title += ' — clique pra rever';
        passo.addEventListener('click', () => this.revisar(i));
      }
      wrap.appendChild(passo);
    });
    wrap.querySelector('.is-current, .is-revisando')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
  },

  etapa() {
    this.revisando = null;
    if (this.idx >= ETAPAS.length) return this.final();
    this.passos();
    const e2 = ETAPAS[this.idx];
    const host = document.getElementById('pl-etapa');
    host.innerHTML = '';
    const slug = e2.minigame || e2.tipo;
    // investigação em modo domínio joga diferente: a dica precisa dizer isso
    const dica = (slug === 'investigacao' && e2.config?.modo === 'dominio')
      ? ['🎧', 'Ouça a cena e escolha. Errou? Leia o feedback e escolha de novo — só avança acertando.']
      : COMO_JOGAR[slug];
    host.appendChild(el(`
      <div class="lms-stack-4 mg-slide">
        <div class="lms-row" style="justify-content:space-between">
          <h3>${e2.titulo}</h3>
          ${e2.obrigatoria ? '<span class="lms-pill is-warning">obrigatória</span>' : ''}
        </div>
        ${dica ? `<div class="mg-howto"><span class="mg-howto-ico">${dica[0]}</span>
          <span><span class="mg-howto-lbl">Como jogar:</span><span class="mg-howto-txt">${dica[1]}</span></span></div>` : ''}
        <div id="mg-host"></div>
      </div>`));
    this.inicioEtapa = Date.now();
    const mg = Minigames[slug];
    if (!mg) { console.error('minigame desconhecido', e2); this.idx++; return this.etapa(); }
    mg(e2.config, host.querySelector('#mg-host'), (r) => this.registrar(e2, r));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  },

  async registrar(e2, r) {
    const tempo = Math.round((Date.now() - this.inicioEtapa) / 1000);
    this.resultados.push({ ...r, tempo });
    try {
      const resp = await fetch('/boticario/api/progresso', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ etapa_id: e2.id, acertos: r.acertos, total: r.total, tempo_seg: tempo, resposta: r.resposta ?? null }),
      });
      const dados = await resp.json();
      if (dados.ok) this.eventos.push(...dados.events);
    } catch (err) { console.error('falha ao registrar progresso', err); }
    this.idx++;
    this.etapa();
  },

  /* modo revisão: reabre uma etapa JÁ CONCLUÍDA mostrando o que foi
     respondido, sem deixar mudar nada — não conta de novo, não chama
     a API, não mexe no índice real de progresso (this.idx). */
  revisar(idx) {
    this.revisando = idx;
    this.passos();
    const e2 = ETAPAS[idx];
    const host = document.getElementById('pl-etapa');
    host.innerHTML = '';
    host.appendChild(el(`
      <div class="lms-stack-4 mg-slide">
        <div class="lms-alert is-success" style="align-items:center">
          <span>👁️</span>
          <div style="flex:1">Modo revisão — você já concluiu esta etapa. Dá pra rever, não pra mudar a resposta.</div>
          <button type="button" class="lms-btn lms-btn-primary lms-btn-sm" id="rev-voltar">← Voltar de onde parei</button>
        </div>
        <h3>${e2.titulo}</h3>
        <div id="mg-host"></div>
      </div>`));
    host.querySelector('#rev-voltar').addEventListener('click', () => this.etapa());
    const slug = e2.minigame || e2.tipo;
    const mg = Minigames[slug];
    if (mg) mg(e2.config, host.querySelector('#mg-host'), () => {}, { resposta: e2.resposta ?? null });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  },

  final() {
    // a trilha de bolinhas já cumpriu o papel — na tela final ela só polui
    document.getElementById('pl-passos').classList.add('lms-hide');
    document.getElementById('pl-etapa').classList.add('lms-hide');
    const fin = document.getElementById('pl-final');
    fin.classList.remove('lms-hide');

    // meta de aprovação não atingida → tela de "refazer" (sem festa)
    const reprova = this.eventos.find(e2 => e2.tipo === 'reprovado');
    if (reprova) SFX.err(); else SFX.win();
    if (reprova) {
      fin.appendChild(el(`
        <div class="lms-card-pastel is-sand lms-pop fim-card">
          <div class="fim-emoji">🎯</div>
          <h2 style="font-size:var(--lms-title-md); margin-top:18px">Quase lá!</h2>
          <p class="lms-small lms-muted" style="margin-top:4px">${reprova.nome}</p>
          <div class="fim-stats">
            <div class="fim-stat"><b style="color:#B07A10">${reprova.pct}%</b><span>seu resultado</span></div>
            <div class="fim-stat"><b style="color:var(--lms-success)">${reprova.minimo}%</b><span>meta para aprovação</span></div>
          </div>
          <div class="fim-barra" title="Você fez ${reprova.pct}% — a meta é ${reprova.minimo}%">
            <div class="fim-barra-fill" style="width:${Math.min(100, reprova.pct)}%"></div>
            <div class="fim-meta" style="left:${Math.min(100, reprova.minimo)}%"><i>meta</i></div>
          </div>
          <p class="fim-txt">
            Esta fase exige <strong>${reprova.minimo}% de acerto</strong> — segurança não aceita "mais ou menos".
            Revise o conteúdo com calma e tente de novo: o progresso recomeça do início.
          </p>
        </div>`));
      fin.appendChild(el(`
        <div class="lms-row" style="justify-content:center; gap:12px; flex-wrap:wrap; margin-top:20px">
          <a class="lms-btn lms-btn-primary lms-btn-lg" href="${location.pathname + location.search}">Refazer fase 🔄</a>
          <a class="lms-btn lms-btn-outline lms-btn-lg" href="/boticario/mapa">Voltar ao mapa</a>
        </div>`));
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    const acertos = this.resultados.reduce((s, r) => s + r.acertos, 0);
    const total = this.resultados.reduce((s, r) => s + r.total, 0);
    const tempo = this.resultados.reduce((s, r) => s + r.tempo, 0);
    const pct = total ? Math.round(acertos / total * 100) : 100;
    const min = Math.floor(tempo / 60), seg = tempo % 60;

    const badges = this.eventos.filter(e2 => e2.tipo === 'badge');
    const trein = this.eventos.find(e2 => e2.tipo === 'treinamento_concluido');

    // solta as bombinhas 🎉
    confete(90);

    const tempoTxt = min > 0 ? `${min}m${String(seg).padStart(2, '0')}s` : `${seg}s`;
    fin.appendChild(el(`
      <div class="lms-card-pastel is-mint lms-pop fim-card">
        <div class="fim-emoji">🎉</div>
        <h2 style="font-size:var(--lms-title-md); margin-top:18px">Fase concluída!</h2>
        <p class="lms-small lms-muted" style="margin-top:4px">${FASE.nome}</p>
        <div class="fim-stats">
          <div class="fim-stat"><b style="color:var(--lms-success)">${pct}%</b><span>de acerto</span></div>
          <div class="fim-stat"><b>${tempoTxt}</b><span>tempo total</span></div>
        </div>
      </div>`));

    if (trein) {
      fin.appendChild(el(`<div class="lms-alert is-info lms-pop" style="text-align:left">🏅 <div><strong>Treinamento “${trein.nome}” completo!</strong> Todas as fases do mapa foram concluídas.</div></div>`));
    }

    if (badges.length) {
      const lista = badges.map(b => `
        <div class="lms-center" style="width:110px">
          <span class="lms-badge lms-pop">${b.badge.icone}</span>
          <p class="lms-small" style="font-weight:600; margin-top:6px">${b.badge.nome}</p>
          <p style="font-size:11px; color:var(--lms-ink-soft)">${b.badge.descricao}</p>
        </div>`).join('');
      fin.appendChild(el(`
        <div class="lms-card lms-stack-2" style="padding:28px">
          <h3>Novas conquistas</h3>
          <div class="lms-row" style="justify-content:center; gap:12px">${lista}</div>
        </div>`));
    }

    fin.appendChild(el(`<a class="lms-btn lms-btn-primary lms-btn-lg" href="/boticario/mapa">Voltar ao mapa 🗺️</a>`));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  },
};

/* revisão começa do zero; primeira vez retoma da 1ª etapa não concluída */
const pendente = ETAPAS.findIndex(e2 => !e2.concluida);
Player.idx = pendente === -1 ? 0 : pendente;
Player.etapa();
