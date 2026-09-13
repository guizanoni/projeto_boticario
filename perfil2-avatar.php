<?php
// Skope LMS · EDITOR DE AVATAR V2 (PREVIEW) — estilo Duolingo: abas de
// tom de pele, olhos/expressão, cabelo, óculos, barba, chapéu, roupa e
// fundo, com preview ao vivo. Salva JSON em usuario.avatar_v2 — o avatar
// v1 (gênero/pele) e todas as telas atuais ficam intactos.
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate(['aluno']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'avatar2_salvar') {
    $bruto = json_decode((string) ($_POST['cfg'] ?? ''), true) ?: [];
    // whitelist de chaves; valores são chaves de catálogo (curtas, [a-zA-Z0-9]).
    // Valor desconhecido não quebra nada: o renderer cai no padrão.
    $chaves = ['pele', 'olhos', 'expressao', 'cabelo', 'cabeloCor', 'oculos', 'oculosCor',
               'barba', 'barbaCor', 'chapeu', 'chapeuCor', 'roupa', 'fundo'];
    $cfg = [];
    foreach ($chaves as $k) {
        if (isset($bruto[$k]) && is_string($bruto[$k]) && preg_match('/^[a-zA-Z0-9]{1,24}$/', $bruto[$k])) {
            $cfg[$k] = $bruto[$k];
        }
    }
    db_run('UPDATE usuario SET avatar_v2 = ? WHERE id = ?', [json_encode($cfg, JSON_UNESCAPED_UNICODE), (int) $u['id']]);
    lms_flash_set(true, 'Avatar atualizado! 🎨');
    header('Location: /boticario/perfil2'); exit;
}

$cfg = json_decode((string) ($u['avatar_v2'] ?? ''), true) ?: null;
$peleMapa = ['1' => 'p8', '2' => 'p6', '3' => 'p4', '4' => 'p2', '5' => 'p1'];
$cfgBase  = $cfg ?: ['pele' => $peleMapa[$u['avatar_cor_pele'] ?? '2'] ?? 'p6'];

lms_head('Editar avatar · v2', $u);
lms_topbar($u);
?>
<style>
  .ed-wrap { max-width: 1080px; margin: 0 auto; padding: 22px 18px 80px; }
  .ed-titulo { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
  .ed-titulo a { font-size: 24px; text-decoration: none; color: var(--lms-ink-soft, #777); }
  .ed-titulo h1 { font-size: 22px; font-weight: 800; color: var(--lms-heading, #0B3B2A);
                  font-family: var(--lms-font-ui, inherit); }

  .ed-grid { display: grid; grid-template-columns: minmax(280px, 460px) minmax(0, 1fr); gap: 0;
             border: 2px solid var(--lms-border, #E5E5E5); border-radius: var(--lms-radius-lg, 16px);
             overflow: hidden; background: var(--lms-surface, #fff); }
  @media (max-width: 820px) { .ed-grid { grid-template-columns: 1fr; } .ed-preview { min-height: 300px; } }

  .ed-preview { display: grid; place-items: center; padding: 26px; min-height: 480px; transition: background .25s; }
  .ed-preview svg { width: min(320px, 80%); height: auto; }

  .ed-painel { border-left: 2px solid var(--lms-border, #E5E5E5); display: flex; flex-direction: column; min-height: 480px; }
  @media (max-width: 820px) { .ed-painel { border-left: 0; border-top: 2px solid var(--lms-border, #E5E5E5); } }

  .ed-abas { display: flex; border-bottom: 2px solid var(--lms-border, #E5E5E5); }
  .ed-aba { flex: 1; text-align: center; padding: 13px 4px 11px; font-size: 20px; cursor: pointer;
            border: 0; background: none; border-bottom: 3px solid transparent; filter: grayscale(1); opacity: .55; }
  .ed-aba.is-on { border-bottom-color: var(--lms-primary, #1CB0F6); filter: none; opacity: 1; }
  .ed-aba:hover { opacity: 1; }

  .ed-corpo { padding: 20px 22px; overflow-y: auto; max-height: 560px; flex: 1; }
  .ed-sec { font-size: 17px; font-weight: 800; color: var(--lms-heading, #0B3B2A);
            font-family: var(--lms-font-ui, inherit); margin: 6px 0 12px; }
  .ed-sec + .ed-sec, .ed-grid-op + .ed-sec, .ed-cores + .ed-sec { margin-top: 22px; }

  .ed-cores { display: flex; flex-wrap: wrap; gap: 10px; }
  .ed-cor { width: 46px; height: 46px; border-radius: 12px; cursor: pointer; border: 2px solid var(--lms-border, #E5E5E5);
            box-shadow: 0 2px 0 var(--lms-border, #E5E5E5); padding: 3px; background: var(--lms-surface, #fff); }
  .ed-cor i { display: block; width: 100%; height: 100%; border-radius: 8px; }
  .ed-cor.is-sel { border-color: var(--lms-primary, #1CB0F6); box-shadow: 0 0 0 3px rgba(28,176,246,.25); }

  .ed-grid-op { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 10px; }
  .ed-op { border: 2px solid var(--lms-border, #E5E5E5); border-radius: 14px; background: var(--lms-surface, #fff);
           cursor: pointer; padding: 8px 4px 6px; box-shadow: 0 2px 0 var(--lms-border, #E5E5E5); }
  .ed-op svg { display: block; margin: 0 auto; }
  .ed-op small { display: block; text-align: center; font-size: 11px; color: var(--lms-ink-soft, #777); margin-top: 4px; }
  .ed-op.is-sel { border-color: var(--lms-primary, #1CB0F6); background: var(--lms-sky, #EDF7FF);
                  box-shadow: 0 0 0 3px rgba(28,176,246,.2); }

  .ed-rodape { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
  .ed-pronto { border: 0; border-radius: 14px; padding: 13px 34px; font-size: 15px; font-weight: 800;
               letter-spacing: .6px; cursor: pointer; font-family: var(--lms-font-ui, inherit);
               background: var(--lms-primary, #1CB0F6); color: var(--lms-primary-ink, #fff);
               box-shadow: 0 3px 0 var(--lms-primary-deep, #1899d6); }
  .ed-pronto:disabled { background: var(--lms-locked, #E5E5E5); color: var(--lms-locked-ink, #AFAFAF);
                        box-shadow: none; cursor: default; }
  .ed-pronto:not(:disabled):active { transform: translateY(3px); box-shadow: none; }
</style>

<main class="ed-wrap">
  <div class="ed-titulo">
    <a href="/boticario/perfil2" title="Voltar ao perfil v2">←</a>
    <h1>Edite o seu avatar <span class="lms-pill" style="margin-left:6px">prévia v2</span></h1>
  </div>

  <div class="ed-grid">
    <div class="ed-preview" id="ed-preview"></div>
    <div class="ed-painel">
      <div class="ed-abas" id="ed-abas"></div>
      <div class="ed-corpo" id="ed-corpo"></div>
    </div>
  </div>

  <form method="post" class="ed-rodape" id="ed-form">
    <input type="hidden" name="acao" value="avatar2_salvar">
    <input type="hidden" name="cfg" id="ed-cfg">
    <button class="ed-pronto" id="ed-pronto" disabled>PRONTO</button>
  </form>
</main>

<script src="/boticario/assets/js/avatar2.js?v=<?= filemtime(__DIR__ . '/assets/js/avatar2.js') ?>"></script>
<script>
(function () {
  const CAT = Avatar2.CAT;
  let cfg = Avatar2.normaliza(<?= json_encode($cfgBase, JSON_UNESCAPED_UNICODE) ?>);
  let mudou = false;

  /* Abas: [id, emoji, título, seções]. Cada seção é
     { titulo, tipo: 'cor'|'opcao', chave, tabela, previewCfg(key) } */
  const ABAS = [
    { id: 'pele', ico: '🖐️', secoes: [
      { titulo: 'Tom de pele', tipo: 'cor', chave: 'pele', tabela: CAT.PELE },
    ]},
    { id: 'olhos', ico: '👀', secoes: [
      { titulo: 'Cor dos olhos', tipo: 'cor', chave: 'olhos', tabela: CAT.OLHOS },
      { titulo: 'Expressão', tipo: 'opcao', chave: 'expressao',
        tabela: Object.fromEntries(Object.entries(CAT.EXPRESSOES).map(([k, v]) => [k, v.rotulo])),
        previewCfg: (k) => ({ expressao: k }) },
    ]},
    { id: 'cabelo', ico: '💇', secoes: [
      { titulo: 'Cor do cabelo', tipo: 'cor', chave: 'cabeloCor', tabela: CAT.CABELO_COR },
      { titulo: 'Cabelo', tipo: 'opcao', chave: 'cabelo', tabela: CAT.CABELOS,
        previewCfg: (k) => ({ cabelo: k, chapeu: 'nenhum', oculos: 'nenhum' }) },
    ]},
    { id: 'oculos', ico: '🕶️', secoes: [
      { titulo: 'Cor dos óculos', tipo: 'cor', chave: 'oculosCor', tabela: CAT.OCULOS_COR },
      { titulo: 'Óculos', tipo: 'opcao', chave: 'oculos', tabela: CAT.OCULOS,
        previewCfg: (k) => ({ oculos: k }) },
    ]},
    { id: 'barba', ico: '🧔', secoes: [
      { titulo: 'Cor da barba ou bigode', tipo: 'cor', chave: 'barbaCor', tabela: CAT.BARBA_COR },
      { titulo: 'Barba ou bigode', tipo: 'opcao', chave: 'barba', tabela: CAT.BARBAS,
        previewCfg: (k) => ({ barba: k, chapeu: 'nenhum', oculos: 'nenhum' }) },
    ]},
    { id: 'chapeu', ico: '🧢', secoes: [
      { titulo: 'Cor do acessório', tipo: 'cor', chave: 'chapeuCor', tabela: CAT.CHAPEU_COR },
      { titulo: 'Chapéus e afins', tipo: 'opcao', chave: 'chapeu', tabela: CAT.CHAPEUS,
        previewCfg: (k) => ({ chapeu: k, oculos: 'nenhum' }) },
    ]},
    { id: 'roupa', ico: '👕', secoes: [
      { titulo: 'Cor da roupa', tipo: 'cor', chave: 'roupa', tabela: CAT.ROUPA_COR },
    ]},
    { id: 'fundo', ico: '🖼️', secoes: [
      { titulo: 'Cor do fundo', tipo: 'cor', chave: 'fundo', tabela: CAT.FUNDO },
    ]},
  ];
  let abaAtiva = 'pele';

  const $preview = document.getElementById('ed-preview');
  const $abas = document.getElementById('ed-abas');
  const $corpo = document.getElementById('ed-corpo');
  const $pronto = document.getElementById('ed-pronto');

  function pintaPreview() {
    $preview.style.background = CAT.FUNDO[cfg.fundo];
    $preview.innerHTML = Avatar2.render(cfg, { tamanho: 300 });
  }

  function montaAbas() {
    $abas.innerHTML = ABAS.map((a) =>
      `<button type="button" class="ed-aba ${a.id === abaAtiva ? 'is-on' : ''}" data-aba="${a.id}" title="${a.id}">${a.ico}</button>`
    ).join('');
    $abas.querySelectorAll('[data-aba]').forEach((b) =>
      b.addEventListener('click', () => { abaAtiva = b.dataset.aba; montaAbas(); montaCorpo(); }));
  }

  function montaCorpo() {
    const aba = ABAS.find((a) => a.id === abaAtiva);
    let html = '';
    for (const s of aba.secoes) {
      html += `<p class="ed-sec">${s.titulo}</p>`;
      if (s.tipo === 'cor') {
        html += `<div class="ed-cores">` + Object.entries(s.tabela).map(([k, hex]) =>
          `<button type="button" class="ed-cor ${cfg[s.chave] === k ? 'is-sel' : ''}" data-chave="${s.chave}" data-valor="${k}" title="${k}"><i style="background:${hex}"></i></button>`
        ).join('') + `</div>`;
      } else {
        html += `<div class="ed-grid-op">` + Object.entries(s.tabela).map(([k, rotulo]) => {
          const mini = Avatar2.renderCabeca(Object.assign({}, cfg, s.previewCfg(k)), 74);
          return `<button type="button" class="ed-op ${cfg[s.chave] === k ? 'is-sel' : ''}" data-chave="${s.chave}" data-valor="${k}">${mini}<small>${rotulo}</small></button>`;
        }).join('') + `</div>`;
      }
    }
    $corpo.innerHTML = html;
    $corpo.querySelectorAll('[data-chave]').forEach((b) =>
      b.addEventListener('click', () => {
        cfg[b.dataset.chave] = b.dataset.valor;
        mudou = true;
        $pronto.disabled = false;
        pintaPreview();
        montaCorpo();   // re-render marca a seleção e atualiza miniaturas
      }));
  }

  document.getElementById('ed-form').addEventListener('submit', () => {
    document.getElementById('ed-cfg').value = JSON.stringify(cfg);
  });

  pintaPreview();
  montaAbas();
  montaCorpo();
})();
</script>
</body></html>
