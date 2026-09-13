<?php
// ──────────────────────────────────────────────────────────────
// Skope LMS · Helpers de domínio: progressão, XP, avatar, chrome.
// ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/db.php';

date_default_timezone_set('America/Sao_Paulo');

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/* ── Ciclo do dia (horário de São Paulo) ─────────────────── */
// O cenário acompanha o relógio real do aluno: a arte do mapa e o
// céu do hero mudam em 4 blocos — imersão lúdica (reunião 14/07).

function lms_periodos(): array {
    return [
        'dia'       => ['emoji' => '☀️', 'nome' => 'Dia',
                        'ceu' => 'linear-gradient(180deg, #DCEEE9 0%, #CDE9E2 44%, #97D5C9 46%, #7CC2B5 100%)'],
        'tarde'     => ['emoji' => '🌅', 'nome' => 'Fim de tarde',
                        'ceu' => 'linear-gradient(180deg, #7D6CAE 0%, #C98BA0 24%, #F0A878 40%, #E9A18C 46%, #C98487 62%, #3E8C8C 100%)'],
        'noite'     => ['emoji' => '🌃', 'nome' => 'Noite',
                        'ceu' => 'linear-gradient(180deg, #111D31 0%, #1B2E47 44%, #174050 46%, #103440 100%)'],
        'madrugada' => ['emoji' => '🌙', 'nome' => 'Madrugada',
                        'ceu' => 'linear-gradient(180deg, #0E1A2E 0%, #16263F 44%, #143C50 46%, #0E2C3A 100%)'],
    ];
}

// dia 06–16:59 · tarde 17–19:59 · noite 20–23:59 · madrugada 00–05:59
function lms_periodo_atual(): string {
    $h = (int) date('G');
    if ($h >= 6 && $h < 17) return 'dia';
    if ($h >= 17 && $h < 20) return 'tarde';
    if ($h >= 20) return 'noite';
    return 'madrugada';
}

// Arte do cenário para o período: se existir a variante do período,
// usa; senão mantém a arte base (dia). Ordem de busca:
//   1. upload do admin: data/mapas/{trilha}-{periodo}.{ext} (mapa-arte)
//   2. assets/: {mapa}-{periodo}.webp ao lado do arquivo base
function lms_mapa_do_periodo(string $url, string $periodo, ?int $trilhaId = null): string {
    if ($periodo === 'dia') return $url;

    // id da trilha: explícito ou extraído de uma URL mapa-arte
    if ($trilhaId === null && str_starts_with($url, '/boticario/mapa-arte')) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $trilhaId = (int) ($q['tr'] ?? 0);
    }
    if ($trilhaId) {
        foreach (['webp', 'png', 'jpg'] as $ext) {
            if (is_file(dirname(__DIR__) . "/data/mapas/{$trilhaId}-{$periodo}.{$ext}")) {
                return '/boticario/mapa-arte?tr=' . $trilhaId . '&p=' . $periodo;
            }
        }
    }

    if (!str_starts_with($url, '/boticario/assets/')) return $url;
    $variante = preg_replace('/\.(webp|png|jpe?g)$/i', '-' . $periodo . '.$1', $url);
    $arquivo = dirname(__DIR__) . preg_replace('~^/boticario~', '', $variante);
    return is_file($arquivo) ? $variante : $url;
}

// Existe FOTO própria configurada para este período? 'dia' = a arte base;
// os demais só contam se houver um arquivo dedicado (sem cair no fallback).
function lms_periodo_tem_arte(string $url, string $periodo, ?int $trilhaId = null): bool {
    if ($url === '') return false;
    if ($periodo === 'dia') return true;
    if ($trilhaId === null && str_starts_with($url, '/boticario/mapa-arte')) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $trilhaId = (int) ($q['tr'] ?? 0);
    }
    if ($trilhaId) {
        foreach (['webp', 'png', 'jpg'] as $ext) {
            if (is_file(dirname(__DIR__) . "/data/mapas/{$trilhaId}-{$periodo}.{$ext}")) return true;
        }
    }
    if (!str_starts_with($url, '/boticario/assets/')) return false;
    $variante = preg_replace('/\.(webp|png|jpe?g)$/i', '-' . $periodo . '.$1', $url);
    return is_file(dirname(__DIR__) . preg_replace('~^/boticario~', '', $variante));
}

/* ── Progressão ──────────────────────────────────────────── */

// Jornada do aluno = as fases do seu treinamento ATIVO (definido pelo
// admin), com estado calculado (done / available / locked) segundo o
// desbloqueio de cada fase: sequência (anterior concluída), data ou XP.
function lms_jornada(array $u): array {
    $vazio = ['treinamento' => null, 'fases' => [], 'progresso' => []];
    if (empty($u['treinamento_id'])) return $vazio;
    $trein = db_one('SELECT t.*, tr.nome AS trilha_nome, tr.cenario_mapa_url, tr.cliente_id
                     FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id WHERE t.id = ?', [$u['treinamento_id']]);
    if (!$trein || (int) $trein['cliente_id'] !== (int) $u['cliente_id']) return $vazio;

    $prog = [];
    foreach (db_all('SELECT * FROM progresso_usuario WHERE usuario_id = ?', [$u['id']]) as $p) $prog[$p['etapa_id']] = $p;
    $xp = lms_xp((int) $u['id']);

    $fases = db_all('SELECT * FROM fase WHERE treinamento_id = ? ORDER BY ordem', [$trein['id']]);
    $anteriorConcluida = true; // primeira fase sequencial sempre aberta
    foreach ($fases as &$f) {
        $f['etapas'] = db_all('SELECT * FROM etapa WHERE fase_id = ? ORDER BY ordem', [$f['id']]);
        $f['total'] = count($f['etapas']);
        $f['feitas'] = 0;
        foreach ($f['etapas'] as $et) {
            if (($prog[$et['id']]['status'] ?? '') === 'concluido') $f['feitas']++;
        }
        $f['concluida'] = $f['total'] > 0 && $f['feitas'] >= $f['total'];

        [$aberta, $motivo] = lms_fase_desbloqueio($f, $anteriorConcluida, $xp);
        $f['estado'] = $f['concluida'] ? 'done' : ($aberta ? 'available' : 'locked');
        $f['motivo_bloqueio'] = $f['estado'] === 'locked' ? $motivo : null;
        $anteriorConcluida = $f['concluida'];
    }
    unset($f);
    return ['treinamento' => $trein, 'fases' => $fases, 'progresso' => $prog];
}

// Regra de desbloqueio de uma fase → [aberta?, motivo se fechada].
function lms_fase_desbloqueio(array $f, bool $anteriorConcluida, int $xp): array {
    switch ($f['desbloqueio'] ?? 'sequencia') {
        case 'data':
            $dia = (string) ($f['desbloqueio_valor'] ?? '');
            return [$dia !== '' && date('Y-m-d') >= $dia,
                    'Abre em ' . ($dia !== '' ? date('d/m', strtotime($dia)) : 'breve')];
        case 'xp':
            $min = (int) ($f['desbloqueio_valor'] ?? 0);
            return [$xp >= $min, "Requer {$min} XP"];
        default: // sequência
            return [$anteriorConcluida, 'Conclua a fase anterior'];
    }
}

// XP: 20 por etapa concluída + 10 por acerto
// + 15 por fase em que participou da discussão (Comunidade).
function lms_xp(int $uid): int {
    $r = db_one('SELECT COUNT(*) AS n, COALESCE(SUM(acertos),0) AS a FROM progresso_usuario WHERE usuario_id = ? AND status = \'concluido\'', [$uid]);
    $c = db_one('SELECT COUNT(DISTINCT fase_id) AS n FROM comentario WHERE usuario_id = ? AND status = \'ativo\'', [$uid]);
    return (int) $r['n'] * 20 + (int) $r['a'] * 10 + (int) $c['n'] * 15;
}

/* ── Friends (método 3Fs): comparação social ─────────────── */

// Ranking de XP dos alunos do cliente (desc). Base do "F de Friends":
// o aluno se enxerga em relação à equipe — comparação saudável
// com a média, sem exposição de quem está atrás.
function lms_ranking(int $clienteId): array {
    return db_all("SELECT u.id, u.nome, u.avatar_genero, u.avatar_cor_pele, u.foto_url,
                          COALESCE(SUM(CASE WHEN p.status = 'concluido' THEN 20 + p.acertos * 10 ELSE 0 END), 0)
                          + (SELECT COUNT(DISTINCT c.fase_id) FROM comentario c
                             WHERE c.usuario_id = u.id AND c.status = 'ativo') * 15 AS xp
                   FROM usuario u
                   LEFT JOIN progresso_usuario p ON p.usuario_id = u.id
                   WHERE u.cliente_id = ? AND u.papel = 'aluno'
                   GROUP BY u.id ORDER BY xp DESC, u.nome", [$clienteId]);
}

// Posição do aluno + média de XP da equipe: [posicao, total, mediaXp, meuXp].
function lms_posicao(array $rank, int $uid): array {
    $pos = 0; $soma = 0; $meu = 0;
    foreach ($rank as $i => $r) {
        $soma += (int) $r['xp'];
        if ((int) $r['id'] === $uid) { $pos = $i + 1; $meu = (int) $r['xp']; }
    }
    $n = count($rank);
    return [$pos, $n, $n ? (int) round($soma / $n) : 0, $meu];
}

function lms_pct(int $feitas, int $total): int {
    return $total > 0 ? (int) round($feitas / $total * 100) : 0;
}

/* ── Engajamento do aluno ────────────────────────────────────
   Score 0–100 = evolução do treinamento (50%) + acessos (30%) +
   comentários (20%). Faixas: alto ≥66 · médio 33–65 · baixo <33.
   Usado no dashboard do gestor e na gestão de usuários. */
function lms_engajamento(int $evoPct, int $acessos, int $nComent): array {
    $acS = $acessos >= 8 ? 100 : ($acessos >= 4 ? 70 : ($acessos >= 2 ? 45 : ($acessos >= 1 ? 25 : 0)));
    $coS = $nComent >= 2 ? 100 : ($nComent === 1 ? 60 : 0);
    $score = (int) round(0.5 * $evoPct + 0.3 * $acS + 0.2 * $coS);
    $nivel = $score >= 66 ? 'alto' : ($score >= 33 ? 'medio' : 'baixo');
    return ['score' => $score, 'nivel' => $nivel, 'evo' => $evoPct, 'acessos' => $acessos, 'coment' => $nComent];
}
// nivel => [rótulo, ícone, classe da pílula, cor do texto]
function lms_engaj_info(): array {
    return [
        'alto'  => ['Alto',  '🟢', 'is-success', 'var(--lms-success)'],
        'medio' => ['Médio', '🟡', 'is-warning', '#9A6A0D'],
        'baixo' => ['Baixo', '🔴', 'is-error',   'var(--lms-error)'],
    ];
}
function lms_engaj_tip(array $e): string {
    return "Engajamento {$e['score']}/100 · evolução {$e['evo']}% · "
        . "{$e['acessos']} acesso" . ($e['acessos'] === 1 ? '' : 's') . " · "
        . "{$e['coment']} comentário" . ($e['coment'] === 1 ? '' : 's');
}

// Aprova um comentário pendente (moderação prévia). A badge Voz da
// Equipe só nasce aqui — na primeira mensagem APROVADA do aluno.
// Retorna a badge concedida (para exibir) ou null.
function lms_comentario_aprovar(array $c): ?array {
    db_run('UPDATE comentario SET status = \'ativo\' WHERE id = ?', [$c['id']]);
    $autor = db_one('SELECT * FROM usuario WHERE id = ?', [$c['usuario_id']]);
    if (!$autor || $autor['papel'] !== 'aluno') return null;
    $b = db_one('SELECT * FROM badge WHERE cliente_id = ? AND nome = \'Voz da Equipe\'', [$c['cliente_id']]);
    if (!$b || db_one('SELECT 1 AS x FROM conquista_usuario WHERE usuario_id = ? AND badge_id = ?', [$autor['id'], $b['id']])) return null;
    db_run('INSERT INTO conquista_usuario (usuario_id, badge_id) VALUES (?,?)', [$autor['id'], $b['id']]);
    return ['nome' => $b['nome'], 'icone' => $b['icone'], 'descricao' => $b['descricao']];
}

/* ── Avatar (RF-03) ──────────────────────────────────────── */

function lms_tons_pele(): array {
    return ['1' => '#F6D0B1', '2' => '#EAB08C', '3' => '#C68863', '4' => '#8D5A3B', '5' => '#5C3A21'];
}

// Imagem do avatar sobre o MAPA. Prioridade:
//   1. avatar enviado pelo admin para a TRILHA (masc/fem conforme o
//      aluno, ou o pet/mascote como coringa) — data/mapas/{id}-avatar-*
//   2. o próprio avatar SVG do aluno (gênero + tom de pele), servido por
//      api/avatar. Este tenant não embute arte de personagem no engine.
function lms_avatar_mapa(?string $genero = null, ?string $pele = null, ?int $trilhaId = null): string {
    if ($trilhaId) {
        $slots = $genero === 'f' ? ['f', 'pet'] : ['m', 'pet'];
        foreach ($slots as $slot) {
            foreach (['png', 'webp'] as $ext) {
                if (is_file(dirname(__DIR__) . "/data/mapas/{$trilhaId}-avatar-{$slot}.{$ext}")) {
                    return "/boticario/mapa-arte?tr={$trilhaId}&av={$slot}";
                }
            }
        }
    }
    return '/boticario/api/avatar?genero=' . ($genero === 'f' ? 'f' : 'm') . '&pele=' . urlencode((string) ($pele ?: '2')) . '&size=160';
}

// Avatar para listas/chrome: foto própria (círculo) quando enviada;
// senão o avatar SVG de gênero/pele.
function lms_avatar_html(?string $genero, ?string $pele, int $size = 44, ?string $foto = null): string {
    if ($foto) {
        return '<img src="' . e($foto) . '" alt="Foto de perfil" loading="lazy" style="width:' . $size . 'px; height:' . $size . 'px; border-radius:50%; object-fit:cover; border:2px solid var(--lms-heading, #2A4971); display:block; flex:none">';
    }
    return lms_avatar_svg($genero, $pele, $size);
}

// Avatar SVG: personagem neutro (gênero + tom de pele), roupa na cor do
// tenant. Sem patente nem capacete — este tenant não tem progressão de
// papéis; a evolução é só XP, fases e badges.
function lms_avatar_svg(?string $genero, ?string $pele, int $size = 64): string {
    $tons = lms_tons_pele();
    $skin = $tons[$pele ?? '2'] ?? '#EAB08C';
    $cabeloCor = '#3B2E23';
    // cabelo: masculino curto · feminino com laterais
    $cabelo = ($genero === 'f')
        ? '<path d="M14 34 Q12 52 18 56 L24 50 Q20 44 21 34 Z" fill="' . $cabeloCor . '"/><path d="M50 34 Q52 52 46 56 L40 50 Q44 44 43 34 Z" fill="' . $cabeloCor . '"/>'
        : '';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Avatar">
      <circle cx="32" cy="32" r="31" fill="#E7F9F3" stroke="#272C30" stroke-width="2"/>
      <path d="M12 58 Q32 44 52 58 L52 64 L12 64 Z" fill="#1CAE8D"/>
      <rect x="26" y="46" width="12" height="8" rx="3" fill="' . $skin . '"/>
      ' . $cabelo . '
      <circle cx="32" cy="34" r="14" fill="' . $skin . '"/>
      <circle cx="27" cy="33" r="1.8" fill="#272C30"/>
      <circle cx="37" cy="33" r="1.8" fill="#272C30"/>
      <path d="M28 40 Q32 43 36 40" stroke="#272C30" stroke-width="1.6" fill="none" stroke-linecap="round"/>
      <path d="M17 30 Q17 14 32 14 Q47 14 47 30 Q42 22 32 23 Q22 22 17 30 Z" fill="' . $cabeloCor . '" stroke="#272C30" stroke-width="1.6"/>
    </svg>';
}

/* ── Chrome comum ────────────────────────────────────────── */

// Ícone da marca do tenant (PROVISÓRIO do whitelabel): disco magenta com B,
// na paleta do treinamento do cliente. Trocar pela marca oficial quando vier
// o brandbook. SVG próprio — sem emoji.
function lms_logo_icon(int $size = 24): string {
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;flex:none" aria-hidden="true">
      <circle cx="32" cy="32" r="30" fill="#A84E84"/>
      <text x="32" y="44" text-anchor="middle" font-family="Poppins, Avenir Next, Arial, sans-serif" font-size="34" font-weight="800" fill="#FFFFFF">B</text>
    </svg>';
}

/* ── Busca (padrão do sistema) ───────────────────────────────
   Toda lista do sistema tem busca, e ela varre TUDO que está visível
   no item — nome, e-mail, papel, treinamento, datas, percentuais,
   qualquer coluna — porque lê o texto renderizado, não uma lista de
   campos escolhida a dedo (que sempre esquece algum). Acentos são
   ignorados nos dois lados: "patricia" acha "Patrícia".

   Uso: lms_busca('.lms-table tbody tr', 'Buscar…', count($itens), 'usuários');
   $alvo — seletor CSS dos itens filtráveis (linhas, cards…)
*/
function lms_busca(string $alvo, string $placeholder, int $total, string $unidade = 'itens', string $id = 'busca'): void {
    $j = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);
    echo '<div class="lms-row" style="margin-top:22px; gap:10px; flex-wrap:wrap">
      <div style="position:relative; flex:1; min-width:260px">
        <span style="position:absolute; left:14px; top:50%; transform:translateY(-50%); pointer-events:none; opacity:.5">🔎</span>
        <input class="lms-input" id="' . e($id) . '" type="search" autocomplete="off"
               placeholder="' . e($placeholder) . '" style="padding-left:40px">
      </div>
      <span class="lms-pill" id="' . e($id) . '-conta" style="align-self:center">' . $total . ' ' . e($unidade) . '</span>
    </div>
    <p class="lms-muted lms-center lms-hide" id="' . e($id) . '-vazio" style="padding:26px 10px">🔎 Nada encontrado com esse termo.</p>
    <script>
    // espera o DOM completo: a barra costuma vir ANTES da lista que filtra
    document.addEventListener("DOMContentLoaded", () => {
      const campo = document.getElementById(' . $j($id) . ');
      const conta = document.getElementById(' . $j($id . '-conta') . ');
      const vazio = document.getElementById(' . $j($id . '-vazio') . ');
      const itens = [...document.querySelectorAll(' . $j($alvo) . ')];
      const limpa = (s) => s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/\s+/g, " ");
      // o índice é o texto VISÍVEL do item: toda coluna entra de graça
      itens.forEach(it => it.dataset.busca = limpa(it.textContent));
      campo.addEventListener("input", () => {
        const q = limpa(campo.value.trim());
        let n = 0;
        itens.forEach(it => {
          const bate = !q || it.dataset.busca.includes(q);
          it.classList.toggle("lms-hide", !bate);
          if (bate) n++;
        });
        conta.textContent = q ? n + " de " + itens.length : itens.length + " " + ' . $j($unidade) . ';
        vazio.classList.toggle("lms-hide", n > 0);
      });
    });
    </script>';
}

/* ── Popup de formulário (padrão do admin) ───────────────────
   Todo formulário de criar/editar do sistema vive num popup, aberto
   por um botão "+ Novo…" — nunca solto na página. Uso:

     lms_popup_abre('trilha', '➕ Nova trilha', 'Subtítulo…', $editando, '/boticario/admin/trilhas');
       … campos do formulário (a tag <form> é sua) …
     lms_popup_fecha('trilha', 'btn-nova-trilha', (bool) $editando, '/boticario/admin/trilhas');

   $id      — prefixo único dos elementos (fundo/modal)
   $voltar  — URL sem ?editar, usada por ✕/Cancelar/fundo
   $largura — largura máxima do modal em px
*/
function lms_popup_abre(string $id, string $titulo, string $sub = '', int $largura = 760): void {
    echo '<div id="' . e($id) . '-fundo" class="lms-hide lms-modal-fundo"></div>
    <div id="' . e($id) . '-modal" class="lms-hide lms-modal-caixa" style="--modal-w:' . $largura . 'px">
      <div class="lms-row" style="justify-content:space-between; margin-bottom:' . ($sub ? '4px' : '16px') . '">
        <h3>' . $titulo . '</h3>
        <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" data-fecha="' . e($id) . '">✕</button>
      </div>'
      . ($sub ? '<p class="lms-small lms-muted" style="margin-bottom:16px">' . $sub . '</p>' : '');
}

function lms_popup_fecha(string $id, string $botaoAbre, bool $editando, string $voltar): void {
    echo '</div>
    <script>
    (() => {
      const abre = () => {
        document.getElementById("' . e($id) . '-fundo").classList.remove("lms-hide");
        const m = document.getElementById("' . e($id) . '-modal");
        m.classList.remove("lms-hide");
        m.querySelector("input:not([type=hidden]), select, textarea")?.focus();
      };
      const fecha = () => {' .
        ($editando
          ? 'window.location.href = ' . json_encode($voltar) . ';'   // limpa o ?editar
          : 'document.getElementById("' . e($id) . '-fundo").classList.add("lms-hide");
             document.getElementById("' . e($id) . '-modal").classList.add("lms-hide");') . '
      };
      document.getElementById(' . json_encode($botaoAbre) . ')?.addEventListener("click", abre);
      document.getElementById("' . e($id) . '-fundo").addEventListener("click", fecha);
      document.querySelectorAll("[data-fecha=\'' . e($id) . '\']").forEach(b => b.addEventListener("click", fecha));
      document.addEventListener("keydown", (ev) => {
        if (ev.key === "Escape" && !document.getElementById("' . e($id) . '-modal").classList.contains("lms-hide")) fecha();
      });' . ($editando ? 'abre();' : '') . '
    })();
    </script>';
}

function lms_head(string $titulo, ?array $u = null): void {
    $tema = '';
    if ($u && !empty($u['cor_primaria'])) {
        $tema = '<style>:root{--lms-primary:' . e($u['cor_primaria']) . ';}</style>';
    }
    // Skin trocável: lms.css é sempre a base; a skin escolhida entra POR CIMA
    // como camada de override (lms-{skin}.css). O admin liga/desliga no
    // perfil → Aparência. Ex.: "garden" → assets/css/lms-garden.css.
    $skinLink = '';
    $skin = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($_COOKIE['lms_skin'] ?? '')));
    if ($skin !== '') {
        $skinFile = dirname(__DIR__) . "/assets/css/lms-{$skin}.css";
        if (is_file($skinFile)) {
            $skinLink = '<link rel="stylesheet" href="/boticario/assets/css/lms-' . $skin . '.css?v=' . filemtime($skinFile) . '">';
        }
    }
    echo '<!DOCTYPE html><html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . e($titulo) . ' · Grupo Boticário</title>
<link rel="icon" href="/boticario/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.png" type="image/png">
<link rel="stylesheet" href="/boticario/assets/css/lms.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/lms.css') . '">' . $tema . $skinLink . '
</head><body>';
    lms_confirmar_modal();
}

// ──────────────────────────────────────────────────────────────
// Modal de confirmação — substitui o confirm() nativo do navegador
// (feio, sem estilo, texto genérico "site diz") por um popup no
// mesmo visual do resto do app. Duas formas de usar:
//
// 1) Automática num <form>: adiciona data-confirmar="mensagem" (e,
//    opcionalmente, data-confirmar-titulo="..." e data-confirmar-perigo
//    pra botão vermelho) — o submit é interceptado sozinho.
// 2) Programática em JS: await lmsConfirmar('Título', 'Mensagem', {perigo:true})
//    devolve true/false.
// ──────────────────────────────────────────────────────────────
function lms_confirmar_modal(): void {
    echo <<<'HTML'
    <div class="lms-modal-fundo lms-hide" id="lms-cf-fundo"></div>
    <div class="lms-modal-caixa lms-hide" id="lms-cf-caixa" style="--modal-w:440px">
      <h3 id="lms-cf-titulo" style="margin-bottom:4px"></h3>
      <p class="lms-small" id="lms-cf-msg" style="white-space:pre-line; color:var(--lms-ink-soft); margin:10px 0 22px"></p>
      <div class="lms-row" style="justify-content:flex-end; gap:10px">
        <button type="button" class="lms-btn lms-btn-outline" id="lms-cf-cancelar">Cancelar</button>
        <button type="button" class="lms-btn lms-btn-primary" id="lms-cf-ok">Confirmar</button>
      </div>
    </div>
    <script>
    window.lmsConfirmar = function (titulo, mensagem, opts) {
      opts = opts || {};
      const fundo = document.getElementById('lms-cf-fundo');
      const caixa = document.getElementById('lms-cf-caixa');
      const btnOk = document.getElementById('lms-cf-ok');
      const btnCancelar = document.getElementById('lms-cf-cancelar');
      document.getElementById('lms-cf-titulo').textContent = titulo || 'Confirmar ação';
      document.getElementById('lms-cf-msg').textContent = mensagem || '';
      btnOk.textContent = opts.textoOk || (opts.perigo ? 'Sim, apagar' : 'Confirmar');
      btnOk.className = 'lms-btn ' + (opts.perigo ? 'lms-btn-outline' : 'lms-btn-primary');
      if (opts.perigo) btnOk.style.color = 'var(--lms-error)'; else btnOk.style.color = '';
      fundo.classList.remove('lms-hide');
      caixa.classList.remove('lms-hide');
      btnOk.focus();
      return new Promise((resolve) => {
        const limpa = (resultado) => {
          fundo.classList.add('lms-hide');
          caixa.classList.add('lms-hide');
          btnOk.removeEventListener('click', onOk);
          btnCancelar.removeEventListener('click', onCancelar);
          fundo.removeEventListener('click', onCancelar);
          document.removeEventListener('keydown', onEsc);
          resolve(resultado);
        };
        const onOk = () => limpa(true);
        const onCancelar = () => limpa(false);
        const onEsc = (e) => { if (e.key === 'Escape') limpa(false); };
        btnOk.addEventListener('click', onOk);
        btnCancelar.addEventListener('click', onCancelar);
        fundo.addEventListener('click', onCancelar);
        document.addEventListener('keydown', onEsc);
      });
    };

    // qualquer <form data-confirmar="..."> passa pelo modal antes de enviar
    document.addEventListener('submit', function (e) {
      const f = e.target;
      if (!(f instanceof HTMLFormElement) || !f.dataset.confirmar || f.dataset.confirmarOk) return;
      e.preventDefault();
      window.lmsConfirmar(f.dataset.confirmarTitulo || 'Confirmar ação', f.dataset.confirmar, {
        perigo: 'confirmarPerigo' in f.dataset,
      }).then((ok) => { if (ok) { f.dataset.confirmarOk = '1'; f.requestSubmit ? f.requestSubmit() : f.submit(); } });
    });
    </script>
    HTML;
}

function lms_topbar(array $u): void {
    // marca do tenant: logo oficial quando cadastrada; senão ícone + nome
    $marca = !empty($u['cliente_logo'])
        ? '<img src="' . e($u['cliente_logo']) . '" alt="' . e($u['cliente_nome']) . '" style="height:46px; width:auto; display:block">'
        : lms_logo_icon(35) . ' ' . e($u['cliente_nome']);
    echo '<header class="lms-topbar">
      <a class="lms-logo" href="' . e(lms_home($u)) . '" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">' . $marca . '</a>
      <nav class="lms-row" style="gap:10px">';

    if ($u['papel'] === 'aluno') {
        // chrome do ALUNO: jornada (XP, papel, avatar → perfil)
        $xp = lms_xp((int) $u['id']);
        echo '<span class="lms-xp"><span class="lms-xp-coin"></span> ' . number_format($xp, 0, ',', '.') . ' XP</span>
              <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/ranking" title="Ranking da equipe">🏆<span class="lms-so-desktop"> Ranking</span></a>
              <a class="lms-avatar-link" href="/boticario/perfil" title="Meu perfil" style="line-height:0">' . lms_avatar_html($u['avatar_genero'], $u['avatar_cor_pele'], 44, $u['foto_url'] ?? null) . '</a>';
    }

    if ($u['papel'] === 'admin') {
        // chrome do ADMIN: grupo administrativo destacado (não é aluno)
        $self = '/' . trim($_SERVER['SCRIPT_NAME'] ?? '', '/');
        $pend = (int) db_one('SELECT COUNT(*) AS n FROM comentario WHERE cliente_id = ? AND status = \'pendente\'', [$u['cliente_id']])['n'];
        $itens = [
            ['🧭', 'Trilhas',   '/boticario/admin/trilhas', str_contains($self, 'admin/trilhas') || str_contains($self, 'admin/treinamentos') || str_contains($self, 'admin/mapa') || str_contains($self, 'admin/etapas')],
            ['👥', 'Usuários',  '/boticario/admin/',  str_contains($self, 'admin/index')],
            ['💬', 'Comentários' . ($pend ? " <b style=\"color:var(--lms-error)\">·{$pend}</b>" : ''), '/boticario/admin/comentarios', str_contains($self, 'admin/comentarios')],
            ['📊', 'Dashboard', '/boticario/gestor/',          str_contains($self, 'gestor') && !str_contains($self, 'times')],
            ['🧑‍🤝‍🧑', 'Times', '/boticario/gestor/times',      str_contains($self, 'gestor/times')],
        ];
        echo '<span class="lms-nav-admin"><span class="lms-nav-admin-rotulo">Admin</span>';
        foreach ($itens as [$ico, $lbl, $url, $ativo]) {
            echo '<a href="' . e($url) . '"' . ($ativo ? ' class="is-ativo"' : '') . '>' . $ico . ' ' . $lbl . '</a>';
        }
        echo '</span>
              <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/mapa" title="Como o aluno vê a plataforma">👁 Visão do aluno</a>';
    }

    if ($u['papel'] === 'gestor') {
        // chrome do GESTOR: dashboard + gestão de usuários (sem edição de conteúdo)
        $self = '/' . trim($_SERVER['SCRIPT_NAME'] ?? '', '/');
        $itens = [
            ['📊', 'Dashboard', '/boticario/gestor/', str_contains($self, 'gestor')],
            ['👥', 'Usuários',  '/boticario/admin/',  str_contains($self, 'admin/index')],
        ];
        echo '<span class="lms-nav-admin"><span class="lms-nav-admin-rotulo">Gestor</span>';
        foreach ($itens as [$ico, $lbl, $url, $ativo]) {
            echo '<a href="' . e($url) . '"' . ($ativo ? ' class="is-ativo"' : '') . '>' . $ico . ' ' . $lbl . '</a>';
        }
        echo '</span>';
    }

    echo '<a class="lms-btn lms-btn-outline lms-btn-sm lms-so-desktop" href="/boticario/senha" title="Alterar minha senha">🔑</a>
      <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/logout">Sair</a>
      </nav></header>';

    // Dock flutuante (canto inferior esquerdo) — ajuda (manual) + reportar bug.
    // Acompanha TODOS os papéis em TODAS as telas. Exceções: o próprio manual
    // (o link do manual some) e o mapa, que já tem ❓ ajuda + 🎧 suporte/bug
    // próprios — evita botão duplicado.
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!str_contains($script, 'mapa')) {
        lms_dock(!str_contains($script, 'manual'));
    }

    // gestor/admin: painéis desenhados para tela grande — no celular,
    // convidamos a entrar pelo computador (aluno usa o mobile normalmente)
    if (in_array($u['papel'], ['gestor', 'admin'], true)) {
        echo '<div class="lms-so-pc">
          <div>
            <div style="font-size:56px">🖥️</div>
            <h2 style="font-size:22px; margin-top:14px">Este painel pede tela grande</h2>
            <p class="lms-small lms-muted" style="max-width:300px; margin:10px auto 0; line-height:1.6">
              O dashboard e a administração foram desenhados para o computador.
              No celular, entre com um perfil de <strong>aluno</strong> para viver a experiência da equipe.</p>
            <a class="lms-btn lms-btn-primary" href="/boticario/logout" style="margin-top:20px">Trocar de conta</a>
          </div>
        </div>';
    }
}

// Dock flutuante universal: Manual (ajuda) + Reportar bug. Presente em todas
// as telas, para todos os papéis. O bug vai por e-mail via /boticario/api/suporte.
function lms_dock(bool $comManual = true): void {
    echo '<div class="lms-dock">';
    if ($comManual) {
        echo '<a class="lms-dock-btn" href="/boticario/manual" title="Manual da plataforma — interativo">📖 <span class="lms-dock-lbl">Manual</span></a>';
    }
    echo '<button type="button" class="lms-dock-btn lms-dock-bug" id="bug-abrir" title="Reportar um problema ou enviar sugestão">🐛 <span class="lms-dock-lbl">Reportar bug</span></button>';
    echo '</div>';

    echo <<<'HTML'
<div id="bug-fundo" class="lms-hide" style="position:fixed;inset:0;background:rgba(30,37,67,.45);z-index:60"></div>
<div id="bug-modal" class="lms-hide" role="dialog" aria-label="Reportar um problema" style="position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(520px,calc(100vw - 32px));max-height:88vh;overflow:auto;z-index:61;background:var(--lms-bg);border:var(--lms-border-game);border-radius:var(--lms-radius-xl);box-shadow:var(--lms-shadow-crisp);padding:24px">
  <div class="lms-row" style="justify-content:space-between;margin-bottom:4px">
    <h3>🐛 Reportar um problema</h3>
    <button type="button" class="lms-btn lms-btn-outline lms-btn-sm" id="bug-fechar">Fechar ✕</button>
  </div>
  <p class="lms-small lms-muted" style="margin-bottom:14px">Conte o que aconteceu — a página e o seu contexto vão junto automaticamente.</p>
  <div id="bug-alerta"></div>
  <form id="bug-form" class="lms-stack-4">
    <div>
      <label class="lms-label" for="bug-cat">O que é?</label>
      <select class="lms-select" id="bug-cat" name="categoria" required>
        <option value="Erro na tela">🐞 Algo quebrou / erro na tela</option>
        <option value="Erro ao salvar">💾 Não salvou / não enviou</option>
        <option value="Conteúdo errado">📝 Conteúdo errado ou faltando</option>
        <option value="Sugestão">💡 Sugestão de melhoria</option>
        <option value="Outro">❓ Outro</option>
      </select>
    </div>
    <div>
      <label class="lms-label" for="bug-assunto">Resumo</label>
      <input class="lms-input" id="bug-assunto" name="assunto" maxlength="150" required placeholder="Ex.: o botão salvar não funciona na fase 3">
    </div>
    <div>
      <label class="lms-label" for="bug-msg">Detalhes</label>
      <textarea class="lms-input" id="bug-msg" name="mensagem" rows="5" maxlength="4000" required placeholder="O que você fez, o que esperava e o que aconteceu."></textarea>
    </div>
    <div class="lms-row" style="justify-content:flex-end;gap:8px">
      <button type="button" class="lms-btn lms-btn-outline" id="bug-cancelar">Cancelar</button>
      <button class="lms-btn lms-btn-primary" id="bug-enviar">Enviar 🐛</button>
    </div>
  </form>
</div>
<script>
(function(){
  var abrir=document.getElementById('bug-abrir'), modal=document.getElementById('bug-modal'), fundo=document.getElementById('bug-fundo');
  if(!abrir||!modal) return;
  var alterna=function(m){[modal,fundo].forEach(function(el){el.classList.toggle('lms-hide',!m);});};
  abrir.addEventListener('click',function(){document.getElementById('bug-alerta').innerHTML='';alterna(true);document.getElementById('bug-assunto').focus();});
  ['bug-fundo','bug-fechar','bug-cancelar'].forEach(function(id){document.getElementById(id).addEventListener('click',function(){alterna(false);});});
  document.getElementById('bug-form').addEventListener('submit',function(ev){
    ev.preventDefault();
    var btn=document.getElementById('bug-enviar'), al=document.getElementById('bug-alerta');
    btn.disabled=true; btn.textContent='Enviando…';
    fetch('/boticario/api/suporte',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      tipo:'bug',
      categoria:document.getElementById('bug-cat').value,
      assunto:document.getElementById('bug-assunto').value,
      mensagem:document.getElementById('bug-msg').value
    })}).then(function(r){return r.json();}).then(function(d){
      btn.disabled=false; btn.textContent='Enviar 🐛';
      if(d.ok){ al.innerHTML='<div class="lms-alert is-success" style="margin-bottom:12px">✅ Recebido! Obrigado — vamos olhar isso.</div>'; document.getElementById('bug-form').reset(); setTimeout(function(){alterna(false);},1400); }
      else { al.innerHTML='<div class="lms-alert is-error" style="margin-bottom:12px">⚠️ '+(d.msg||'Não consegui enviar agora.')+'</div>'; }
    }).catch(function(){ btn.disabled=false; btn.textContent='Enviar 🐛'; al.innerHTML='<div class="lms-alert is-error" style="margin-bottom:12px">⚠️ Falha de conexão. Tente de novo.</div>'; });
  });
})();
</script>
HTML;
}
