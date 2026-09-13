/* ══════════════════════════════════════════════════════════════
   Boticário LMS · AVATAR V2 (preview) — renderer estilo Duolingo.
   Fonte única de verdade do avatar rico: o editor (perfil2-avatar)
   e o perfil v2 (perfil2) montam o SVG a partir do mesmo catálogo.
   Config = objeto simples { pele, olhos, expressao, cabelo, cabeloCor,
   oculos, oculosCor, barba, barbaCor, chapeu, chapeuCor, roupa, fundo }
   — sempre CHAVES do catálogo, nunca cores soltas (validável no server).
   ══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  /* ── Catálogo de opções ──────────────────────────────────── */
  const PELE = {
    p1: '#5C3A21', p2: '#8D5A3B', p3: '#A9714B', p4: '#C68863', p5: '#D89B70',
    p6: '#EAB08C', p7: '#F2C29B', p8: '#F6D0B1', p9: '#FBE2C9',
  };
  const OLHOS = {
    preto: '#3C4249', castanho: '#A0501E', mel: '#8B6914', verde: '#5A8F0E',
    teal: '#0E7F86', azul: '#0A87D8', cinza: '#7284A0', roxo: '#9161F2',
    magenta: '#C81EC8', rosa: '#F03CB4', vermelho: '#E8283C', laranja: '#EE7D00',
    dourado: '#E0A000',
  };
  const EXPRESSOES = {
    brilho:  { rotulo: 'Encantado',  olhos: 'brilho',  boca: 'aberta'  },
    sorriso: { rotulo: 'Sorridente', olhos: 'normal',  boca: 'sorriso' },
    riso:    { rotulo: 'Risada',     olhos: 'feliz',   boca: 'aberta'  },
    sereno:  { rotulo: 'Sereno',     olhos: 'normal',  boca: 'leve'    },
    lingua:  { rotulo: 'Brincalhão', olhos: 'feliz',   boca: 'lingua'  },
    uau:     { rotulo: 'Uau!',       olhos: 'brilho',  boca: 'oh'      },
  };
  const CABELO_COR = {
    preto: '#3C4249', marrom: '#4A3123', castanho: '#6B4226', chocolate: '#7B3F2A',
    vinho: '#8C3A3F', cinza: '#8A7A72', ruivo: '#B4571E', cobre: '#C1502E',
    loiro: '#E8A94F', claro: '#F6DCB4', grisalho: '#D9D2CB', branco: '#EEF1F2',
    rosa: '#F58AD1', roxo: '#8C63F0', azul: '#3F7BDE', verde: '#2E9A72',
  };
  const CABELOS = {
    nenhum:   'Careca',
    laterais: 'Laterais',
    franja:   'Franja curta',
    fio:      'Fio rebelde',
    topete:   'Topete',
    liso:     'Liso caído',
  };
  const OCULOS_COR = {
    roxo: '#8C63F0', azul: '#1E56A8', verde: '#5F8428', laranja: '#EE7D00',
    vermelho: '#F04350', rosa: '#F58AD1', bege: '#E3D5C3', preto: '#3C4249',
  };
  const OCULOS = {
    nenhum:     'Sem óculos',
    retangular: 'Retangular',
    fino:       'Aro fino',
    redondo:    'Redondo',
    sol:        'De sol',
    esportivo:  'Sol quadrado',
  };
  const BARBA_COR = CABELO_COR;
  const BARBAS = {
    nenhuma:    'Sem barba',
    bigode:     'Bigode',
    cheia:      'Barba cheia',
    cavanhaque: 'Cavanhaque',
    contorno:   'Contorno',
  };
  const CHAPEU_COR = {
    roxo: '#8C63F0', azul: '#1D96D2', verde: '#58A700', amarelo: '#FFC200',
    laranja: '#FF9600', vermelho: '#C82446', rosa: '#F9B7EA', gelo: '#ECF0F1',
    grafite: '#4B4B4B',
  };
  const CHAPEUS = {
    nenhum:   'Sem chapéu',
    bone:     'Boné',
    boneTras: 'Boné virado',
    gorro:    'Gorro',
    bandana:  'Bandana',
    capacete: 'Capacete de obra',
  };
  const ROUPA_COR = {
    roxo: '#A468D8', azul: '#3FA0D8', verde: '#7BB837', amarelo: '#EFC93F',
    laranja: '#F49000', framboesa: '#C8375F', rosa: '#F9C7E4', gelo: '#ECF0F1',
    grafite: '#4B4B4B',
  };
  const FUNDO = {
    cinzaClaro: '#E9E9E9', cinza: '#A6A6A6', grafite: '#4B4B4B',
    lilasClaro: '#F2E7FB', lilas: '#CD9EF5', roxo: '#8B62E9',
    azulClaro: '#C0E6FA', azul: '#79D0F7', azulForte: '#1E56A8',
    mentaClaro: '#C8F7DC', menta: '#54E8A2', verde: '#3CAF7C',
    verdeClaro: '#D8FBB4', verdeLima: '#B4F272', verdeMato: '#6DA637',
    amareloClaro: '#FAF0C8', amarelo: '#FBD379', ambar: '#E5A43C',
    rosaClaro: '#FDE0E4', salmao: '#FCA8AC', coral: '#E05B5B',
    rosaBebe: '#FCD8F3', rosa: '#F893DC', pink: '#D9509F',
  };

  const PADRAO = {
    pele: 'p6', olhos: 'preto', expressao: 'brilho',
    cabelo: 'laterais', cabeloCor: 'preto',
    oculos: 'nenhum', oculosCor: 'azul',
    barba: 'nenhuma', barbaCor: 'preto',
    chapeu: 'nenhum', chapeuCor: 'verde',
    roupa: 'verde', fundo: 'mentaClaro',
  };

  /* ── Utilidades ──────────────────────────────────────────── */
  const escurece = (hex, f) => {
    const n = parseInt(hex.slice(1), 16);
    const c = (v) => Math.max(0, Math.round(v * f));
    return '#' + [c(n >> 16 & 255), c(n >> 8 & 255), c(n & 255)]
      .map((v) => v.toString(16).padStart(2, '0')).join('');
  };

  const normaliza = (cfg) => {
    const c = Object.assign({}, PADRAO, cfg || {});
    const em = (v, tab, alt) => (v in tab ? v : alt);
    c.pele = em(c.pele, PELE, PADRAO.pele);
    c.olhos = em(c.olhos, OLHOS, PADRAO.olhos);
    c.expressao = em(c.expressao, EXPRESSOES, PADRAO.expressao);
    c.cabelo = em(c.cabelo, CABELOS, PADRAO.cabelo);
    c.cabeloCor = em(c.cabeloCor, CABELO_COR, PADRAO.cabeloCor);
    c.oculos = em(c.oculos, OCULOS, PADRAO.oculos);
    c.oculosCor = em(c.oculosCor, OCULOS_COR, PADRAO.oculosCor);
    c.barba = em(c.barba, BARBAS, PADRAO.barba);
    c.barbaCor = em(c.barbaCor, BARBA_COR, PADRAO.barbaCor);
    c.chapeu = em(c.chapeu, CHAPEUS, PADRAO.chapeu);
    c.chapeuCor = em(c.chapeuCor, CHAPEU_COR, PADRAO.chapeuCor);
    c.roupa = em(c.roupa, ROUPA_COR, PADRAO.roupa);
    c.fundo = em(c.fundo, FUNDO, PADRAO.fundo);
    return c;
  };

  /* ── Partes do desenho (viewBox 0 0 200 210) ─────────────── */

  function svgCorpo(c) {
    const roupa = ROUPA_COR[c.roupa];
    const macacao = escurece(roupa, 0.78);
    const pele = PELE[c.pele];
    return `
      <rect x="86" y="128" width="28" height="26" rx="9" fill="${escurece(pele, 0.9)}"/>
      <path d="M30 210 L30 186 Q30 152 66 150 L134 150 Q170 152 170 186 L170 210 Z" fill="${roupa}"/>
      <rect x="27" y="168" width="22" height="42" rx="11" fill="${roupa}"/>
      <rect x="151" y="168" width="22" height="42" rx="11" fill="${roupa}"/>
      <circle cx="38" cy="207" r="9" fill="${pele}"/>
      <circle cx="162" cy="207" r="9" fill="${pele}"/>
      <path d="M68 185 L132 185 L132 210 L68 210 Z" fill="${macacao}"/>
      <rect x="76" y="181" width="5" height="12" rx="2.5" fill="${escurece(roupa, 0.6)}"/>
      <rect x="119" y="181" width="5" height="12" rx="2.5" fill="${escurece(roupa, 0.6)}"/>
      <path d="M98 198 L102 198 L102 205 L98 205 Z" fill="${escurece(roupa, 0.6)}"/>`;
  }

  function svgCabeca(c) {
    const pele = PELE[c.pele];
    return `
      <circle cx="45" cy="92" r="11" fill="${pele}"/>
      <circle cx="155" cy="92" r="11" fill="${pele}"/>
      <rect x="48" y="36" width="104" height="102" rx="34" fill="${pele}"/>`;
  }

  // cabelo desenhado SOB o chapéu (chapéu cobre o topo)
  function svgCabelo(c) {
    if (c.cabelo === 'nenhum') return '';
    const cor = CABELO_COR[c.cabeloCor];
    switch (c.cabelo) {
      case 'laterais':
        return `<path d="M48 78 Q46 64 54 56 L58 74 Q52 78 48 86 Z" fill="${cor}"/>
                <path d="M152 78 Q154 64 146 56 L142 74 Q148 78 152 86 Z" fill="${cor}"/>`;
      case 'franja':
        return `<path d="M48 70 Q48 36 100 36 Q152 36 152 70 L152 62 Q148 44 128 46 Q100 50 72 46 Q52 44 48 62 Z" fill="${cor}"/>`;
      case 'fio':
        return `<path d="M100 38 Q100 26 110 26 Q118 26 116 33 Q114 39 107 37 Q103 36 103 40 Z" fill="${cor}"/>`;
      case 'topete':
        return `<path d="M62 50 Q64 30 92 32 Q120 34 118 44 Q140 38 138 52 Q152 54 152 70 L48 70 Q48 56 62 50 Z" fill="${cor}"/>`;
      case 'liso':
        return `<path d="M48 92 Q44 38 100 36 Q156 38 152 92 L146 92 Q150 52 100 50 Q50 52 54 92 Z" fill="${cor}"/>`;
    }
    return '';
  }

  function svgOlhos(c) {
    const cor = OLHOS[c.olhos];
    const tipo = EXPRESSOES[c.expressao].olhos;
    const olho = (cx) => {
      if (tipo === 'feliz') {
        return `<path d="M${cx - 13} 92 Q${cx} 76 ${cx + 13} 92" stroke="#2B3135" stroke-width="6" stroke-linecap="round" fill="none"/>`;
      }
      const brilho = tipo === 'brilho'
        ? `<circle cx="${cx + 4.5}" cy="82" r="3.4" fill="#fff"/>
           <circle cx="${cx - 6}" cy="95" r="2.6" fill="#D9F2FF"/>
           <path d="M${cx - 4} 84 l2.6 5 5 2.6 -5 2.6 -2.6 5 -2.6 -5 -5 -2.6 5 -2.6 Z" fill="#fff"/>`
        : `<circle cx="${cx + 4}" cy="83" r="2.8" fill="#fff"/>`;
      const raio = tipo === 'brilho' ? 12.5 : 10;
      return `
        <ellipse cx="${cx}" cy="89" rx="16.5" ry="17.5" fill="#fff"/>
        <circle cx="${cx}" cy="90" r="${raio}" fill="${cor}"/>
        <circle cx="${cx}" cy="90" r="${raio * 0.55}" fill="${escurece(cor, 0.45)}"/>
        ${brilho}`;
    };
    return `<g>${olho(81)}${olho(119)}</g>`;
  }

  function svgNarizBoca(c) {
    const pele = PELE[c.pele];
    const nariz = `<path d="M92 97 Q100 88 108 97 Q112 108 100 108 Q88 108 92 97 Z" fill="${escurece(pele, 0.8)}"/>`;
    const boca = EXPRESSOES[c.expressao].boca;
    let m = '';
    if (boca === 'aberta') {
      m = `<path d="M84 112 Q100 118 116 110 Q118 132 100 132 Q86 132 84 112 Z" fill="#8F2B2B"/>
           <path d="M92 128 Q100 133 108 128 Q104 124 100 124 Q96 124 92 128 Z" fill="#E2606B"/>
           <path d="M96 112 L106 111 L105 119 Q104 121 101 120.6 L98 120 Q96 119 96 117 Z" fill="#fff"/>`;
    } else if (boca === 'sorriso') {
      m = `<path d="M86 114 Q100 126 114 114" stroke="#7A3B32" stroke-width="5.5" stroke-linecap="round" fill="none"/>`;
    } else if (boca === 'leve') {
      m = `<path d="M90 117 Q100 123 110 117" stroke="#7A3B32" stroke-width="5" stroke-linecap="round" fill="none"/>`;
    } else if (boca === 'lingua') {
      m = `<path d="M84 112 Q100 122 116 110 Q114 126 102 128 Q88 128 84 112 Z" fill="#8F2B2B"/>
           <path d="M98 122 Q108 118 112 124 Q114 132 106 133 Q98 133 98 122 Z" fill="#E2606B"/>`;
    } else if (boca === 'oh') {
      m = `<ellipse cx="100" cy="120" rx="9" ry="11" fill="#8F2B2B"/>
           <ellipse cx="100" cy="124" rx="4.5" ry="4" fill="#E2606B"/>`;
    }
    return nariz + m;
  }

  function svgBarba(c) {
    if (c.barba === 'nenhuma') return '';
    const cor = CABELO_COR[c.barbaCor];
    switch (c.barba) {
      case 'bigode':
        return `<path d="M83 109 Q92 103 100 108 Q108 103 117 109 Q113 116 100 112 Q87 116 83 109 Z" fill="${cor}"/>`;
      case 'cheia':
        return `<path d="M52 96 Q54 132 76 137 Q100 141 124 137 Q146 132 148 96 L148 112 Q148 140 124 143 Q100 147 76 143 Q52 140 52 112 Z
                 M80 112 Q100 120 120 112 Q118 130 100 130 Q82 130 80 112 Z" fill="${cor}" fill-rule="evenodd"/>
                <path d="M52 96 Q54 134 78 139 Q100 144 122 139 Q146 134 148 96 L148 110 Q146 141 122 144 Q100 148 78 144 Q54 141 52 110 Z" fill="${cor}"/>`;
      case 'cavanhaque':
        return `<path d="M92 126 Q100 130 108 126 Q108 137 100 137 Q92 137 92 126 Z" fill="${cor}"/>`;
      case 'contorno':
        return `<path d="M50 98 Q52 132 74 136 Q100 141 126 136 Q148 132 150 98 L150 104 Q149 136 126 141 Q100 146 74 141 Q51 136 50 104 Z" fill="${cor}" stroke="${cor}" stroke-width="3" stroke-linejoin="round"/>`;
    }
    return '';
  }

  function svgOculos(c) {
    if (c.oculos === 'nenhum') return '';
    const cor = OCULOS_COR[c.oculosCor];
    const haste = `<rect x="44" y="84" width="14" height="5" rx="2.5" fill="${cor}"/>
                   <rect x="142" y="84" width="14" height="5" rx="2.5" fill="${cor}"/>
                   <rect x="95" y="84" width="10" height="5" rx="2.5" fill="${cor}"/>`;
    const lenteEscura = 'rgba(43,49,53,.92)';
    switch (c.oculos) {
      case 'retangular':
        return `${haste}
          <rect x="59" y="72" width="36" height="32" rx="9" fill="none" stroke="${cor}" stroke-width="6"/>
          <rect x="105" y="72" width="36" height="32" rx="9" fill="none" stroke="${cor}" stroke-width="6"/>`;
      case 'fino':
        return `${haste}
          <rect x="60" y="73" width="34" height="30" rx="8" fill="none" stroke="${cor}" stroke-width="3.5"/>
          <rect x="106" y="73" width="34" height="30" rx="8" fill="none" stroke="${cor}" stroke-width="3.5"/>`;
      case 'redondo':
        return `${haste}
          <circle cx="79" cy="89" r="17" fill="none" stroke="${cor}" stroke-width="5"/>
          <circle cx="121" cy="89" r="17" fill="none" stroke="${cor}" stroke-width="5"/>`;
      case 'sol':
        return `${haste}
          <circle cx="79" cy="89" r="17" fill="${lenteEscura}" stroke="${cor}" stroke-width="5"/>
          <circle cx="121" cy="89" r="17" fill="${lenteEscura}" stroke="${cor}" stroke-width="5"/>
          <circle cx="73" cy="83" r="4" fill="rgba(255,255,255,.35)"/>
          <circle cx="115" cy="83" r="4" fill="rgba(255,255,255,.35)"/>`;
      case 'esportivo':
        return `${haste}
          <rect x="58" y="72" width="38" height="32" rx="8" fill="${lenteEscura}" stroke="${cor}" stroke-width="5"/>
          <rect x="104" y="72" width="38" height="32" rx="8" fill="${lenteEscura}" stroke="${cor}" stroke-width="5"/>
          <rect x="64" y="77" width="10" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/>
          <rect x="110" y="77" width="10" height="5" rx="2.5" fill="rgba(255,255,255,.35)"/>`;
    }
    return '';
  }

  function svgChapeu(c) {
    if (c.chapeu === 'nenhum') return '';
    const cor = CHAPEU_COR[c.chapeuCor];
    const sombra = escurece(cor, 0.8);
    switch (c.chapeu) {
      case 'bone':
        return `
          <path d="M52 62 Q52 22 100 22 Q148 22 148 62 L148 66 L52 66 Z" fill="${cor}"/>
          <path d="M96 22 Q100 14 106 18 Q112 22 106 26 Q100 28 96 22 Z" fill="${sombra}"/>
          <path d="M74 24 Q88 18 100 22 L100 66 L84 66 Q76 44 74 24 Z" fill="${sombra}" opacity=".35"/>
          <path d="M40 68 Q100 52 160 68 Q162 78 152 77 Q100 66 48 77 Q38 78 40 68 Z" fill="${sombra}"/>`;
      case 'boneTras':
        return `
          <path d="M52 62 Q52 22 100 22 Q148 22 148 62 L148 66 L52 66 Z" fill="${cor}"/>
          <path d="M96 22 Q100 14 106 18 Q112 22 106 26 Q100 28 96 22 Z" fill="${sombra}"/>
          <path d="M146 58 Q168 58 166 70 Q164 78 150 74 Q146 68 146 58 Z" fill="${sombra}"/>
          <rect x="88" y="58" width="24" height="9" rx="4.5" fill="${sombra}"/>`;
      case 'gorro':
        return `
          <path d="M52 64 Q52 24 100 24 Q148 24 148 64 L148 70 L52 70 Z" fill="${cor}"/>
          <rect x="50" y="60" width="100" height="14" rx="7" fill="${sombra}"/>
          <circle cx="100" cy="22" r="9" fill="${sombra}"/>`;
      case 'bandana':
        return `
          <path d="M50 60 Q52 28 100 28 Q148 28 150 60 L150 68 L50 68 Z" fill="${cor}"/>
          <path d="M148 60 Q166 64 162 78 Q158 90 150 84 Q146 74 148 60 Z" fill="${sombra}"/>
          <path d="M60 34 Q80 26 100 28 L100 68 L54 68 Q54 46 60 34 Z" fill="${sombra}" opacity=".3"/>`;
      case 'capacete':
        return `
          <path d="M54 56 Q54 20 100 20 Q146 20 146 56 L146 60 L54 60 Z" fill="${cor}"/>
          <rect x="90" y="14" width="20" height="12" rx="6" fill="${cor}"/>
          <rect x="90" y="20" width="8" height="40" fill="${sombra}" opacity=".4"/>
          <rect x="46" y="56" width="108" height="12" rx="6" fill="${cor}" stroke="${sombra}" stroke-width="2.5"/>`;
    }
    return '';
  }

  /* ── API ─────────────────────────────────────────────────── */

  // SVG completo. opts: { tamanho: px, fundo: bool (pinta o quadro),
  // classe: css extra, arredonda: raio do quadro }
  function render(cfg, opts) {
    const c = normaliza(cfg);
    const o = Object.assign({ tamanho: 200, fundo: false, arredonda: 0 }, opts || {});
    const bg = o.fundo
      ? `<rect x="0" y="0" width="200" height="210" rx="${o.arredonda}" fill="${FUNDO[c.fundo]}"/>` : '';
    return `<svg viewBox="0 0 200 210" width="${o.tamanho}" height="${Math.round(o.tamanho * 1.05)}"
      xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Avatar" ${o.classe ? `class="${o.classe}"` : ''}>
      ${bg}${svgCorpo(c)}${svgCabeca(c)}${svgCabelo(c)}${svgOlhos(c)}${svgNarizBoca(c)}${svgBarba(c)}${svgOculos(c)}${svgChapeu(c)}
    </svg>`;
  }

  // Miniatura só da cabeça (thumbs do editor, estilo Duolingo).
  function renderCabeca(cfg, tamanho) {
    const c = normaliza(cfg);
    return `<svg viewBox="26 6 148 148" width="${tamanho}" height="${tamanho}" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      ${svgCabeca(c)}${svgCabelo(c)}${svgOlhos(c)}${svgNarizBoca(c)}${svgBarba(c)}${svgOculos(c)}${svgChapeu(c)}
    </svg>`;
  }

  window.Avatar2 = {
    render, renderCabeca, normaliza,
    PADRAO,
    CAT: {
      PELE, OLHOS, EXPRESSOES, CABELOS, CABELO_COR,
      OCULOS, OCULOS_COR, BARBAS, BARBA_COR,
      CHAPEUS, CHAPEU_COR, ROUPA_COR, FUNDO,
    },
  };
})();
