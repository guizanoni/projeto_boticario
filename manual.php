<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · MANUAL INTERATIVO — um por papel.
//   /boticario/manual            → o manual do papel de quem entrou
//   /boticario/manual?papel=…    → admin espia os outros manuais
//
// Não é um PDF disfarçado: tem navegação com scroll-spy, busca que
// varre todo o conteúdo, progresso de leitura salvo no navegador e
// peças interativas (mapa mental clicável, catálogo das dinâmicas,
// simulador de desbloqueio, anatomia das telas com hotspots).
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate();

// admin pode ler qualquer manual; os demais leem o seu
$papel = $u['papel'];
if ($u['papel'] === 'admin' && !empty($_GET['papel']) && in_array($_GET['papel'], ['admin', 'gestor', 'aluno'], true)) {
    $papel = $_GET['papel'];
}

$capas = [
    'admin'  => ['🛠', 'Manual do Admin', 'O construtor: você desenha trilhas, mapas, fases e minigames — sem tocar em código.'],
    'gestor' => ['📊', 'Manual do Gestor', 'O radar: você enxerga a equipe inteira, encontra quem precisa de ajuda e age.'],
    'aluno'  => ['🎓', 'Manual do Participante', 'A jornada: seu mapa, suas fases, seus minigames — e como concluir a trilha.'],
];
[$capaIco, $capaTit, $capaSub] = $capas[$papel];

// catálogo das dinâmicas (o mesmo do construtor de etapas), com a
// régua de QUANDO usar cada uma — o coração do manual do admin
$dinamicas = [
    ['🧾', 'Leitura com confirmação', 'leitura', 'Texto + pontos críticos que o aluno confirma um a um.',
     'Norma, procedimento, política — conteúdo denso que precisa ser lido de verdade. Nada avança até o último ponto ser confirmado.'],
    ['❓', 'Quiz', 'quiz', 'Perguntas com uma ou várias corretas, com feedback na hora.',
     'Verificar entendimento objetivo. Aceita múltipla escolha real ("marque todas") e introdução de contexto.'],
    ['🐉', 'Verdadeiro ou Falso', 'mito_verdade', 'Afirmações para julgar, com carimbo e explicação.',
     'Derrubar mitos e "achismos" do dia a dia. O carimbo VERDADE/MITO fixa a resposta na memória.'],
    ['🔗', 'Ligue os Pontos', 'ligue_pontos', 'Conectar termos às definições.',
     'Vocabulário, siglas, papéis e responsabilidades — quando o par "o quê ↔ o que faz" é o conteúdo.'],
    ['🗂️', 'Classificação', 'classificacao', 'Um item por vez em 2 a 4 categorias.',
     'Poucos itens, decisão consciente. Ideal para "pode / não pode / depende".'],
    ['🧳', 'Arrastar para Zonas', 'arrastar', 'Vários itens de uma vez em 2 a 4 zonas.',
     'Volume grande de itens (o embarque usa 20). O painel cheio dá a visão do todo.'],
    ['🔢', 'Ordenação', 'sequencia', 'Passos na ordem em que acontecem.',
     'Fluxos e procedimentos passo a passo — quando a ORDEM é o que importa.'],
    ['🃏', 'Jogo da Memória', 'memoria', 'Pares relacionados, com virada 3D.',
     'Decoreba necessária: cores de coletores, equipamento ↔ função, sinal ↔ ação.'],
    ['🎬', 'Cenário', 'cenario', 'Situação narrativa com decisão e feedback.',
     'Regras de comportamento. "O que você faria?" ensina mais que "não faça X".'],
    ['🕵️', 'Investigação', 'investigacao', 'Pistas ramificadas: cada escolha abre um caminho.',
     'Raciocínio e método. Diferente do quiz: a escolha errada leva a outro caminho (ou encerra o caso).'],
    ['🎯', 'Caça no Grid', 'caca', 'Grid para varrer e revelar.',
     'Listas de ações/itens que o aluno descobre explorando, sem ordem obrigatória.'],
    ['🔎', 'Jogo dos Erros', 'sete_erros', 'Pontos escondidos numa imagem.',
     'Percepção de risco visual: achar o inseguro na cena. Você sobe a imagem e marca os pontos clicando.'],
    ['🔤', 'Caça-Palavras', 'caca_palavras', 'Grade gerada das suas palavras.',
     'Fixar vocabulário de forma leve. Você só digita as palavras — a grade nasce sozinha.'],
    ['✏️', 'Cruzadinha', 'cruzadinha', 'Cruzamento gerado de palavra + dica.',
     'Vocabulário com definição. Tem contagem de 3 minutos e "ver respostas" ao final.'],
    ['📖', 'História / HQ', 'historia', 'Cenas sequenciais com imagem.',
     'Narrativa e contexto. Suba quadrinhos e conte a história antes de cobrar a decisão.'],
    ['🎞️', 'Vídeo', 'video', 'YouTube, Vimeo ou arquivo.',
     'Conteúdo audiovisual pronto. Sem barra de avanço: conclui sozinho ao terminar.'],
    ['🔊', 'Áudio real', 'audio', 'MP3/M4A/OGG com player nativo.',
     'Depoimentos e podcasts reais. O concluir só libera depois do play.'],
    ['🎧', 'Podcast ilustrativo', 'podcast', 'Transcrição narrada, sem arquivo.',
     'Quando o áudio ainda não existe: a transcrição com vozes já entrega o conteúdo.'],
    ['🎲', 'Tabuleiro', 'tabuleiro', 'Dado, casas e perguntas.',
     'Revisão geral divertida. Errou, volta uma casa — o grand finale de um treinamento.'],
];

lms_head('Manual · ' . $capaTit, $u);
lms_topbar($u);
?>
<main class="man-wrap">

  <!-- ── navegação lateral ─────────────────────────────────── -->
  <aside class="man-nav" id="man-nav">
    <div class="man-nav-topo">
      <span class="man-nav-ico"><?= $capaIco ?></span>
      <div>
        <strong><?= e($capaTit) ?></strong>
        <span class="man-prog-txt" id="man-prog-txt">0% lido</span>
      </div>
    </div>
    <div class="man-prog"><div class="man-prog-fill" id="man-prog-fill"></div></div>

    <input class="lms-input man-busca" id="man-busca" type="search" placeholder="🔎 Buscar no manual…" autocomplete="off">

    <nav id="man-links"></nav>

    <?php if ($u['papel'] === 'admin'): ?>
    <div class="man-troca">
      <p class="lms-small lms-muted" style="margin-bottom:6px">👁 Ver outro manual:</p>
      <?php foreach ($capas as $k => [$ico, $tit]): ?>
      <a class="man-troca-btn <?= $k === $papel ? 'is-ativo' : '' ?>" href="/boticario/manual?papel=<?= e($k) ?>"><?= $ico ?> <?= e(explode(' do ', $tit)[1] ?? $tit) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </aside>

  <!-- ── conteúdo ──────────────────────────────────────────── -->
  <div class="man-doc" id="man-doc">

    <header class="man-capa">
      <span class="man-capa-ico"><?= $capaIco ?></span>
      <div>
        <h1><?= e($capaTit) ?></h1>
        <p><?= e($capaSub) ?></p>
      </div>
    </header>

    <p class="man-dica">💡 Este manual é interativo: <strong>clique nas peças</strong> para explorar. O que você lê fica marcado ✓ e a barra ao lado mostra seu avanço.</p>

<?php if ($papel === 'admin'): /* ══════════ MANUAL DO ADMIN ══════════ */ ?>

    <section class="man-sec" id="s-mapa-mental" data-titulo="O mapa mental">
      <h2>🧠 O mapa mental</h2>
      <p>Todo o conteúdo da plataforma vive em quatro camadas encaixadas, como pastas. Entendeu isso, entendeu o sistema inteiro. <strong>Clique em cada peça:</strong></p>

      <div class="man-arv">
        <?php
        $camadas = [
            ['trilha', '🧭', 'Trilha', 'O universo', 'A trilha é dona do MAPA e da narrativa. Uma trilha pirata tem mapa pirata; uma de loja tem a loja. É a pasta-mãe — o cliente pode ter várias, cada uma com sua arte (e até com variantes de dia, tarde, noite e madrugada).'],
            ['trein', '📁', 'Treinamento', 'A pasta', 'Agrupa e dá nome ao conjunto ("Diretrizes de SSMS", "Integração 2026"). Não tem conteúdo próprio: é a pasta que o aluno recebe. Cada aluno vive UM treinamento por vez — você escolhe qual, em Usuários.'],
            ['fase', '📍', 'Fase', 'O jogável', 'É o que o aluno clica no mapa. Tem PIN livre (você escolhe o ponto), regra de quando abre (evolução, data ou XP) e nota mínima. É aqui que a jornada acontece.'],
            ['etapa', '🧩', 'Etapa', 'O minigame', 'O conteúdo de verdade: uma leitura, um quiz, um tabuleiro. A fase é uma sequência de etapas — o aluno percorre na ordem que você montar.'],
        ];
        foreach ($camadas as $i => [$k, $ico, $nome, $tag, $txt]): ?>
        <?php if ($i): ?><div class="man-arv-seta">▼</div><?php endif; ?>
        <button type="button" class="man-arv-item" data-camada="<?= e($k) ?>">
          <span class="man-arv-ico"><?= $ico ?></span>
          <span class="man-arv-nome"><?= e($nome) ?></span>
          <span class="man-arv-tag"><?= e($tag) ?></span>
        </button>
        <div class="man-arv-txt lms-hide" id="cam-<?= e($k) ?>"><?= e($txt) ?></div>
        <?php endforeach; ?>
      </div>

      <div class="man-caixa is-dica">
        <strong>A regra de ouro:</strong> o aluno nunca vê "treinamento" nem "trilha" escrito na tela dele — ele vê um mapa com pins. Essas camadas são a SUA organização.
      </div>
    </section>

    <section class="man-sec" id="s-receita" data-titulo="Receita: do zero ao aluno jogando">
      <h2>🚀 Receita: do zero ao aluno jogando</h2>
      <p>O caminho completo, em 6 passos. Marque conforme fizer:</p>
      <div class="man-passos">
        <?php
        $passos = [
            ['Criar a trilha', 'Menu <strong>🧭 Trilhas → + Nova trilha</strong>. Dê o nome, a narrativa e suba a <strong>arte do mapa</strong> (proporção larga, tipo 1024×572). Opcional: as variantes de tarde/noite/madrugada e os avatares (masculino, feminino ou um pet).'],
            ['Criar o treinamento', 'Abra a trilha → <strong>+ Novo treinamento</strong>. É só nome e descrição — a pasta que o aluno vai receber.'],
            ['Colocar as fases no mapa', 'No treinamento, <strong>+ Nova fase</strong> (ou clique direto no ponto do mapa). Ajuste a posição pelos campos X/Y ou arrastando o pin.'],
            ['Definir as regras da fase', 'Em cada card: <strong>quando abre</strong> (🔗 evolução · 📅 data · ⭐ XP) e a <strong>nota mínima</strong>. Abaixo da nota, o aluno refaz a fase.'],
            ['Montar as etapas', 'No card da fase → <strong>Etapas ▶ → + Nova etapa</strong>. Escolha a dinâmica, preencha o conteúdo, salve. Repita — a ordem da lista é a ordem do jogo.'],
            ['Entregar ao aluno', 'Menu <strong>👥 Usuários</strong> → edite a pessoa → <strong>🗺️ Treinamento ativo</strong>. Pronto: ela loga e cai no mapa com as fases liberadas.'],
        ];
        foreach ($passos as $i => [$tit, $txt]): ?>
        <label class="man-passo">
          <input type="checkbox" class="man-check" data-passo="r<?= $i ?>">
          <span class="man-passo-n"><?= $i + 1 ?></span>
          <span class="man-passo-txt"><strong><?= e($tit) ?></strong><br><?= $txt ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="man-sec" id="s-dinamicas" data-titulo="As 19 dinâmicas">
      <h2>🎮 As 19 dinâmicas — e quando usar cada uma</h2>
      <p>O maior erro é jogar tudo em quiz. Cada conteúdo tem a dinâmica que o ensina melhor. <strong>Clique numa dinâmica</strong> para ver a régua:</p>

      <div class="man-din-grid">
        <?php foreach ($dinamicas as $i => [$ico, $nome, $slug, $oque, $quando]): ?>
        <button type="button" class="man-din" data-din="<?= $i ?>">
          <span class="man-din-ico"><?= $ico ?></span>
          <span class="man-din-nome"><?= e($nome) ?></span>
        </button>
        <?php endforeach; ?>
      </div>

      <div class="man-din-painel" id="man-din-painel">
        <p class="lms-muted lms-center" style="padding:14px">👆 Escolha uma dinâmica acima.</p>
      </div>

      <div class="man-caixa is-dica">
        <strong>Regra prática:</strong> comece pela pergunta "o que o aluno precisa SABER FAZER depois?".
        Decidir bem → 🎬 Cenário · Investigar → 🕵️ · Lembrar de cor → 🃏 Memória · Ordem correta → 🔢 · Ler a norma → 🧾 Leitura.
      </div>
    </section>

    <section class="man-sec" id="s-desbloqueio" data-titulo="Quando a fase abre">
      <h2>🔓 Quando a fase abre — simulador</h2>
      <p>Cada fase tem uma regra de abertura. Configure no card da fase (ou no ✏️). <strong>Experimente:</strong></p>

      <div class="man-sim">
        <div class="man-sim-botoes">
          <button type="button" class="man-sim-btn is-ativo" data-regra="sequencia">🔗 Evolução</button>
          <button type="button" class="man-sim-btn" data-regra="data">📅 Data</button>
          <button type="button" class="man-sim-btn" data-regra="xp">⭐ XP</button>
          <button type="button" class="man-sim-btn" data-regra="livre">🔓 Livre</button>
        </div>
        <div class="man-sim-palco">
          <div class="man-sim-pin" id="sim-pin">🔒</div>
          <p class="man-sim-txt" id="sim-txt"></p>
        </div>
      </div>

      <div class="man-caixa is-atencao">
        <strong>🎯 A nota mínima é outra coisa.</strong> O desbloqueio decide <em>quando a fase aparece liberada</em>; a nota mínima decide <em>se o aluno passou</em> depois de jogar. Abaixo dela, o progresso da fase é zerado e ele refaz. Use <strong>0</strong> para não exigir nota.
      </div>
    </section>

    <section class="man-sec" id="s-mapa" data-titulo="O editor de mapa">
      <h2>🗺️ O editor de mapa</h2>
      <p>A arte da trilha vira prancheta. Três formas de posicionar um pin:</p>
      <ul class="man-lista">
        <li><strong>Clique na arte</strong> — cria a fase naquele ponto exato.</li>
        <li><strong>Arraste o pin</strong> — salva a posição nova sozinho.</li>
        <li><strong>Digite X e Y</strong> — no modal, em % da largura/altura, com as setas ↑↓ do teclado. O pin acompanha ao vivo (fase nova mostra um fantasma verde). O botão ⌖ centraliza.</li>
      </ul>
      <div class="man-caixa is-dica">
        Passe o mouse no mapa para ver a <strong>grade de 10%</strong> — ajuda a mirar. E passar o mouse num pin acende o card dele lá embaixo (e vice-versa).
      </div>
    </section>

    <section class="man-sec" id="s-usuarios" data-titulo="Usuários e times">
      <h2>👥 Usuários e times</h2>
      <ul class="man-lista">
        <li><strong>Papéis:</strong> <em>Aluno</em> vive o mapa · <em>Gestor</em> gere a própria equipe · <em>Admin</em> faz tudo · <em>RH</em> é um admin (vê tudo).</li>
        <li><strong>🧑‍🤝‍🧑 Gestor (time):</strong> cada aluno pertence a um gestor. Você escolhe/troca o time no formulário; o gestor só enxerga e edita a equipe dele.</li>
        <li><strong>🗺️ Treinamento ativo:</strong> o que a pessoa vê ao logar. A trilha (mapa e narrativa) vem junto, pelo treinamento escolhido.</li>
        <li><strong>➕ Segunda trilha:</strong> dá para atribuir um 2º treinamento ao aluno. Com dois, ele escolhe em qual entrar ao logar e ganha o botão <strong>🗺️ Trocar trilha</strong> no mapa — cada trilha guarda o próprio progresso.</li>
        <li><strong>Ações:</strong> 👁 abre o report do aluno · ✏️ edita · ↺ zera o progresso (só admin) · 🗑 exclui (só admin). O gestor adiciona e edita alunos do time dele, mas <strong>não</strong> exclui nem zera.</li>
        <li><strong>Engajamento e busca:</strong> a lista mostra o selo de engajamento de cada aluno; a busca varre tudo — nome, e-mail, papel, treinamento, data, progresso.</li>
      </ul>
    </section>

    <section class="man-sec" id="s-times" data-titulo="A aba Times">
      <h2>🧑‍🤝‍🧑 A aba Times</h2>
      <p>Só para admin/RH. Uma visão de <strong>todas as equipes</strong> de uma vez — escala com muitos gestores (tem busca):</p>
      <ul class="man-lista">
        <li>Cada time mostra a <strong>conformidade média</strong> (vs a média geral) e a <strong>distribuição de engajamento</strong> (🟢/🟡/🔴).</li>
        <li><strong>Clique num nível de engajamento</strong> → popup com os alunos daquele nível do time, cada um com "Ver".</li>
        <li><strong>Ver dashboard do time</strong> → abre o dashboard inteiro filtrado naquele gestor: é o "como está a equipe do fulano?".</li>
      </ul>
    </section>

    <section class="man-sec" id="s-teste" data-titulo="Ferramentas de teste">
      <h2>🛠 Ferramentas de teste (só suas)</h2>
      <ul class="man-lista">
        <li><strong>👁 Visão do aluno</strong> (topo) — entra no mapa como um participante, com o SEU treinamento ativo.</li>
        <li><strong>Bypass de fase</strong> — dentro de qualquer fase, a barrinha no canto: <em>✓ Passar fase</em> conclui tudo na hora, <em>✕ Reprovar</em> zera. Só afeta você; os alunos ficam intactos.</li>
        <li><strong>Simulador de período</strong> — no mapa, os emojis ☀️🌅🌃🌙 forçam o horário para conferir as artes do ciclo do dia.</li>
        <li><strong>Cruzadinha</strong> — para você, o "ver respostas" já nasce liberado (o aluno espera 3 minutos).</li>
      </ul>
    </section>

    <section class="man-sec" id="s-onboarding" data-titulo="1º acesso do aluno">
      <h2>🚀 O primeiro acesso do aluno</h2>
      <p>Antes de cair no mapa, todo aluno novo passa por três telas — uma vez só:</p>
      <ul class="man-lista">
        <li><strong>✋ Aceite de uso:</strong> ele confirma o uso da plataforma, da imagem/avatar e do registro de progresso, e toca em “Aceito jogar”. Fica carimbado no cadastro.</li>
        <li><strong>🧍 Avatar:</strong> escolhe personagem e tom de pele.</li>
        <li><strong>🧭 Apresentação da trilha:</strong> boas-vindas + o que a jornada traz. A cada trilha nova que ele entra, essa apresentação reaparece (só a da trilha).</li>
      </ul>
      <div class="man-caixa is-dica">
        Essas telas são do aluno — você (admin/RH/gestor) nunca passa por elas. Para ver como fica, use a <strong>👁 Visão do aluno</strong> no topo.
      </div>
    </section>

    <section class="man-sec" id="s-temas" data-titulo="Temas visuais">
      <h2>🎨 Temas visuais (skins)</h2>
      <p>A plataforma tem <strong>temas trocáveis</strong> — o visual muda, os componentes e o conteúdo continuam iguais. A troca fica na sua conta (botão <strong>🔑</strong> do topo → seção de tema):</p>
      <ul class="man-lista">
        <li><strong>Padrão</strong> é o tema oficial e o que todos veem por default.</li>
        <li>Os demais (Garden, Mint, Car, Theus) são variações de design para experimentar.</li>
        <li>A escolha vale para a sua sessão, via preferência salva no navegador — não muda o que o resto da equipe vê.</li>
      </ul>
    </section>

    <section class="man-sec" id="s-comunidade" data-titulo="Comunidade">
      <h2>💬 Comunidade</h2>
      <p>Cada fase tem uma conversa. Mensagem de aluno nasce <strong>pendente</strong> e só aparece para a equipe depois de aprovada — e é a aprovação que libera os +15 XP e a badge 🗣️ Voz da Equipe do autor.</p>
      <ul class="man-lista">
        <li><strong>✔️ Aprovar</strong> publica · <strong>🚫 Recusar</strong> esconde sem alarde.</li>
        <li><strong>✅ Marcar oficial</strong> destaca a resposta da equipe com selo verde.</li>
        <li>O menu 💬 Comentários mostra um contador vermelho quando há fila.</li>
      </ul>
    </section>

<?php elseif ($papel === 'gestor'): /* ══════════ MANUAL DO GESTOR ══════════ */ ?>

    <section class="man-sec" id="s-bussola" data-titulo="A bússola">
      <h2>🧭 O que este dashboard responde</h2>
      <p>Cinco perguntas, sempre nesta ordem de urgência. <strong>Clique para ir direto ao ponto:</strong></p>
      <div class="man-perg">
        <?php
        $perguntas = [
            ['A equipe está em dia?', 'Conformidade média', 's-kpis'],
            ['Quem está travado ou sumido?', 'Radar de risco', 's-risco'],
            ['Onde a equipe empaca?', 'Conformidade por fase', 's-funil'],
            ['O ritmo está caindo?', 'Ritmo de treinamento', 's-kpis'],
            ['Tem alguém precisando de resposta?', 'Comunidade', 's-comunidade-g'],
        ];
        foreach ($perguntas as [$p, $onde, $anc]): ?>
        <a class="man-perg-item" href="#<?= e($anc) ?>">
          <span class="man-perg-q">“<?= e($p) ?>”</span>
          <span class="man-perg-r">→ <?= e($onde) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="man-sec" id="s-kpis" data-titulo="Os KPIs">
      <h2>📊 Os números do topo</h2>
      <p>Passe o mouse em cada card para ver a explicação. <strong>Clique em cada um aqui</strong> para saber o que ele mede de verdade:</p>
      <div class="man-din-grid">
        <?php
        $kpis = [
            ['👥', 'Alunos cadastrados', 'O total de alunos do seu time — tenham acessado ou não. É a base sobre a qual os outros números são lidos.'],
            ['✅', 'Conformidade média', 'É a MÉDIA do avanço individual — cada pessoa medida dentro do treinamento dela. Não é "quantos terminaram": é o quanto a equipe caminhou. Verde ≥ 80%, vermelho < 50%.'],
            ['🔵', 'Ativos em 7 dias', 'Quantos logaram na última semana. Caindo? O engajamento está esfriando antes do progresso cair.'],
            ['🚫', 'Nunca acessaram', 'Nunca entraram na plataforma. É o número mais acionável — clique no card e abre um popup com a lista de quem nunca entrou, com "Ver" para agir na hora.'],
            ['🎯', 'Aproveitamento médio', 'Acertos ÷ questões respondidas por toda a equipe. Baixo aqui = conteúdo difícil ou mal explicado, não preguiça.'],
            ['⏱', 'Horas de capacitação', 'Tempo real somado dentro das etapas. É o número que a auditoria pede.'],
        ];
        foreach ($kpis as $i => [$ico, $nome, $txt]): ?>
        <button type="button" class="man-din" data-din="<?= $i ?>">
          <span class="man-din-ico"><?= $ico ?></span>
          <span class="man-din-nome"><?= e($nome) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
      <div class="man-din-painel" id="man-din-painel">
        <p class="lms-muted lms-center" style="padding:14px">👆 Escolha um indicador acima.</p>
      </div>
    </section>

    <section class="man-sec" id="s-time" data-titulo="Seu time">
      <h2>🧑‍🤝‍🧑 Seu time</h2>
      <p>Cada aluno pertence a um <strong>gestor</strong>. Você vê e edita <strong>apenas a sua equipe</strong> — todos os números do dashboard, o radar e a lista já vêm filtrados só para o seu time. Quem define quem é de qual time é o admin/RH, na tela de Usuários.</p>
      <div class="man-caixa is-dica">
        <strong>Meu time vs. os outros:</strong> o card no topo compara a conformidade média da sua equipe com a média dos outros times — com um selo <em>▲ acima</em> / <em>▼ abaixo</em>. É o termômetro rápido de como você está em relação aos demais.
      </div>
    </section>

    <section class="man-sec" id="s-engajamento" data-titulo="Engajamento">
      <h2>🎯 Engajamento (alto / médio / baixo)</h2>
      <p>Um selo colorido por aluno que resume o envolvimento dele, combinando três sinais:</p>
      <ul class="man-lista">
        <li><strong>Evolução no treinamento</strong> (peso 50%) — o quanto já concluiu.</li>
        <li><strong>Acessos</strong> (peso 30%) — quantas vezes voltou à plataforma.</li>
        <li><strong>Participação</strong> (peso 20%) — comentários publicados na comunidade.</li>
      </ul>
      <p>Vira um score de 0 a 100 → <span style="color:var(--lms-success);font-weight:700">🟢 Alto (≥66)</span> · <span style="color:#9A6A0D;font-weight:700">🟡 Médio (33–65)</span> · <span style="color:var(--lms-error);font-weight:700">🔴 Baixo (&lt;33)</span>. Aparece como coluna na lista de colaboradores e como selo no report individual (passe o mouse para ver os componentes).</p>
      <div class="man-caixa is-dica">
        O engajamento também decide o <strong>tom do e-mail</strong> na visão individual: alto → parabéns, médio → incentivo, baixo/sumido → resgate.
      </div>
    </section>

    <section class="man-sec" id="s-funil" data-titulo="Jornada e conformidade">
      <h2>🗺️ Onde a equipe está</h2>
      <ul class="man-lista">
        <li><strong>Painel da jornada:</strong> uma coluna por treinamento ativo, com o avanço médio de quem vive nele. Barra baixa = o time inteiro travou ali.</li>
        <li><strong>Conformidade por fase:</strong> o heatmap. Verde = a fase flui; vermelho = todo mundo empaca nela. <em>Uma fase vermelha isolada quase nunca é problema de gente — é problema de conteúdo.</em></li>
        <li><strong>Ritmo (14 dias):</strong> etapas concluídas por dia, com a linha de média. Serve para ver o efeito de um e-mail ou de uma campanha.</li>
      </ul>
      <div class="man-caixa is-dica">
        <strong>Leitura combinada:</strong> aproveitamento baixo + fase vermelha = revisar o conteúdo. Aproveitamento alto + ritmo baixo = falta de tempo/lembrete, não de capacidade.
      </div>
    </section>

    <section class="man-sec" id="s-risco" data-titulo="Radar de risco">
      <h2>🚨 Radar de risco — e o que fazer</h2>
      <p>O radar classifica em três tipos. Cada um pede uma ação diferente:</p>
      <div class="man-passos">
        <?php
        $riscos = [
            ['🚫', 'Nunca acessou', 'Não é desinteresse — quase sempre é acesso: e-mail que não chegou, senha perdida. <strong>Ação:</strong> abra a pessoa (Ver) e mande o e-mail de resgate.'],
            ['⏸️', 'Parado há 4+ dias', 'Começou e sumiu no meio. <strong>Ação:</strong> o e-mail de resgate já vem com o progresso e o próximo passo dela — é o que traz de volta.'],
            ['📉', 'Aproveitamento < 70%', 'Está tentando e errando. <strong>Ação:</strong> aqui e-mail não resolve — veja QUAL fase está vermelha e converse sobre o conteúdo.'],
        ];
        foreach ($riscos as [$ico, $tit, $txt]): ?>
        <div class="man-passo" style="cursor:default">
          <span class="man-passo-n" style="background:var(--lms-error)"><?= $ico ?></span>
          <span class="man-passo-txt"><strong><?= e($tit) ?></strong><br><?= $txt ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="man-caixa is-atencao">
        <strong>✉️ O e-mail vem escrito para você</strong> — com o nome, o % de avanço e o próximo passo da pessoa. O tom se adapta ao engajamento: <strong>🎉 parabéns</strong> (alto), <strong>💪 incentivo</strong> (médio) e <strong>✉️ resgate</strong> (sumido/baixo). Você revisa, ajusta e envia. Trava de 24h por pessoa — ninguém recebe spam.
      </div>
    </section>

    <section class="man-sec" id="s-individual" data-titulo="Visão individual">
      <h2>👤 Visão individual</h2>
      <p>Busque a pessoa no campo do topo (ou clique no 👁 <em>Ver</em>). O dashboard inteiro vira ela: KPIs próprios, engajamento, jornada por fase, aproveitamento, posição no ranking, o <strong>gestor (time)</strong> dela e a situação de risco.</p>
      <ul class="man-lista">
        <li><strong>✏️ Editar aluno</strong> (no topo, ao lado de "Gerar relatório") abre o cadastro para trocar nome, e-mail, senha e treinamento — sem sair para a aba Usuários.</li>
        <li><strong>E-mail</strong> (parabéns / incentivo / resgate, conforme o engajamento) no card de situação.</li>
        <li>Para sair, o ✕ do pill "Visão individual".</li>
      </ul>
    </section>

    <section class="man-sec" id="s-relatorio" data-titulo="Relatórios">
      <h2>📄 Relatórios</h2>
      <ul class="man-lista">
        <li><strong>📄 Gerar relatório</strong> monta a peça da equipe — ou da pessoa em foco, se você estiver na visão individual.</li>
        <li><strong>🖨 Salvar PDF</strong> usa a impressão do navegador (escolha "Salvar como PDF").</li>
        <li><strong>📧 Enviar por e-mail</strong> aceita vários destinatários (vírgula) — o e-mail chega igual à página.</li>
      </ul>
    </section>

    <section class="man-sec" id="s-comunidade-g" data-titulo="Comunidade">
      <h2>💬 Comunidade</h2>
      <p>O card do dashboard mostra duas filas: <strong>⏳ aguardando aprovação</strong> (mensagens de aluno não aparecem para ninguém até você aprovar) e <strong>🗨 publicadas sem resposta</strong> (alguém perguntou e ninguém respondeu).</p>
      <p>Sua resposta entra no ar direto, com o selo <strong>🛡️ Equipe</strong>. Aprovar uma mensagem libera +15 XP e a badge 🗣️ do autor — aprovar é incentivo, não só moderação.</p>
    </section>

<?php else: /* ══════════ MANUAL DO ALUNO ══════════ */ ?>

    <section class="man-sec" id="s-mapa-a" data-titulo="O mapa é sua jornada">
      <h2>🗺️ O mapa é sua jornada</h2>
      <p>Você não navega por menus: você vive um mapa. Cada pin é uma fase. <strong>Clique nos pins abaixo</strong> para entender o que cada cor quer dizer:</p>

      <div class="man-pins">
        <?php
        $pins = [
            ['available', '!', 'Azul pulsante = liberada', 'Pode jogar agora! Clique e seu avatar caminha até lá. É a sua próxima missão.'],
            ['done', '✓', 'Verde = concluída', 'Você já fechou essa fase. Pode voltar e rever quando quiser — sem perder o que ganhou.'],
            ['locked', '🔒', 'Cinza com cadeado = bloqueada', 'Ainda não é a hora. Passe o mouse: o balão diz o porquê — concluir a fase anterior, esperar uma data ou juntar XP.'],
        ];
        foreach ($pins as $i => [$cls, $ico, $tit, $txt]): ?>
        <button type="button" class="man-pin-demo is-<?= e($cls) ?>" data-pin="<?= $i ?>"><?= $ico ?></button>
        <?php endforeach; ?>
      </div>
      <div class="man-din-painel" id="man-pin-painel">
        <p class="lms-muted lms-center" style="padding:14px">👆 Toque num pin.</p>
      </div>

      <div class="man-caixa is-dica">
        🌅 Repare no cenário: a plataforma <strong>vive o horário real</strong> — dia, fim de tarde, noite e madrugada. Entre de madrugada e veja. 😉
      </div>
    </section>

    <section class="man-sec" id="s-primeiro-a" data-titulo="Seu primeiro acesso">
      <h2>🚀 Seu primeiro acesso</h2>
      <p>Da primeira vez que você entra, são só três telas rápidas antes do mapa:</p>
      <div class="man-passos">
        <?php
        $primeiro = [
            ['✋', 'Aceite de uso', 'Você confirma alguns pontos — uso da plataforma, da sua imagem/avatar e do registro do seu progresso — e toca em <strong>“Aceito jogar”</strong>. Aparece uma vez só.'],
            ['🧍', 'Monte seu avatar', 'Escolha o personagem e o tom de pele. Ele é você no mapa.'],
            ['🧭', 'Apresentação da jornada', 'Uma tela de boas-vindas com o que te espera na trilha — e o botão <strong>Começar ▶</strong> te leva ao mapa.'],
        ];
        foreach ($primeiro as [$ico, $tit, $txt]): ?>
        <div class="man-passo" style="cursor:default">
          <span class="man-passo-n"><?= $ico ?></span>
          <span class="man-passo-txt"><strong><?= e($tit) ?></strong><br><?= $txt ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="man-sec" id="s-trilhas-a" data-titulo="Trocar de trilha">
      <h2>🗺️ Sua(s) trilha(s) — e como trocar</h2>
      <p>Às vezes você recebe <strong>mais de uma trilha</strong> (por exemplo, SSMS e Administrativo). Cada uma tem o seu próprio mapa e guarda o seu próprio progresso — o que você faz numa não mexe na outra.</p>
      <ul class="man-lista">
        <li><strong>Escolher qual jogar:</strong> se você tem duas trilhas, ao entrar aparece uma tela para escolher em qual quer jogar hoje.</li>
        <li><strong>Trocar a qualquer momento:</strong> já dentro do mapa, use o botão <strong>🗺️ Trocar trilha</strong> no topo. Você volta para a escolha e entra na outra.</li>
        <li><strong>Trilha nova:</strong> a primeira vez que você entra numa trilha, ela te mostra uma apresentação antes do mapa — depois é só continuar de onde parou.</li>
      </ul>
      <div class="man-caixa is-dica">
        Não achou o botão de trocar? Ele só aparece quando você tem mais de uma trilha ativa. Com uma só, o mapa já é a sua casa.
      </div>
    </section>

    <section class="man-sec" id="s-fase-a" data-titulo="Dentro de uma fase">
      <h2>🎮 Dentro de uma fase</h2>
      <ul class="man-lista">
        <li>Uma fase é uma sequência de <strong>etapas</strong>: leituras e minigames. As bolinhas no topo mostram onde você está.</li>
        <li><strong>🎮 Como jogar:</strong> todo minigame abre com uma faixa curta explicando a mecânica daquele jogo. Bateu o olho, já sabe o que fazer.</li>
        <li><strong>Feedback com calma:</strong> ao responder, aparece se você acertou ou errou <em>e a fase espera você clicar em “Próxima”</em> — dá tempo de ler e entender antes de seguir.</li>
        <li><strong>Seu progresso salva sozinho</strong>, etapa por etapa. Feche quando quiser — você volta exatamente de onde parou.</li>
        <li>Algumas fases têm <strong>nota mínima</strong>. Se você ficar abaixo, aparece a tela "Quase lá!" e a fase recomeça — não é castigo, é segurança.</li>
        <li>🔊 O botão de som (ao lado das bolinhas) liga/desliga os efeitos dos jogos.</li>
      </ul>
      <div class="man-caixa is-dica">
        Vai encontrar de tudo: quiz, verdadeiro ou falso, memória, caça-palavras, cruzadinha, jogo dos erros, investigação, tabuleiro com dado, vídeo, áudio e histórias em quadrinhos. Cada conteúdo tem o jogo que combina com ele.
      </div>
    </section>

    <section class="man-sec" id="s-xp" data-titulo="XP, badges e ranking">
      <h2>⭐ XP, badges e ranking</h2>
      <p><strong>Como você ganha XP:</strong></p>
      <div class="man-xp">
        <div class="man-xp-item"><b>+20</b><span>por etapa concluída</span></div>
        <div class="man-xp-item"><b>+10</b><span>por acerto</span></div>
        <div class="man-xp-item"><b>+15</b><span>por fase em que você participou da conversa</span></div>
      </div>
      <p style="margin-top:14px"><strong>Badges</strong> são conquistas: 🥾 Primeiro Passo, 🎯 Na Mosca (90%+), ⚡ Relâmpago (fase em menos de 4 min), 🏅 Missão Cumprida, 🏆 Trilha Completa e 🗣️ Voz da Equipe. Veja as suas no <strong>Perfil</strong>.</p>
      <p>O <strong>🏆 Ranking</strong> mostra a equipe por XP. É comparação saudável: quem está na frente inspira, e ninguém é exposto por estar atrás. Você evolui no seu ritmo.</p>
    </section>

    <section class="man-sec" id="s-comunidade-a" data-titulo="A conversa da equipe">
      <h2>💬 A conversa da equipe</h2>
      <p>No fim de cada fase tem uma conversa. Puxe assunto, tire dúvida, conte o que acontece na sua rotina — quem chega depois de você lê.</p>
      <ul class="man-lista">
        <li>Sua mensagem passa por <strong>moderação</strong> antes de aparecer para todos. Quando é aprovada, você ganha <strong>+15 XP</strong> e a badge 🗣️.</li>
        <li>Respostas com selo <strong>🛡️ Equipe</strong> vêm da gestão. As marcadas com <strong>✅ Resposta oficial</strong> são a palavra final sobre o assunto.</li>
      </ul>
    </section>

    <section class="man-sec" id="s-perfil-a" data-titulo="Seu perfil">
      <h2>🧍 Seu perfil e seu avatar</h2>
      <ul class="man-lista">
        <li><strong>Avatar:</strong> escolha personagem e tom de pele. Ele é você no mapa.</li>
        <li><strong>Foto:</strong> pode subir a sua; ela aparece no topo, no perfil e no ranking (no mapa continua o personagem).</li>
        <li><strong>🔑 Senha:</strong> troque quando quiser, no botão do topo.</li>
        <li><strong>🎧 / 🐛 Ajuda e suporte:</strong> no mapa, os botões ❓ (como usar) e 🎧 (suporte); nas demais telas, o canto inferior esquerdo traz 📖 Manual e 🐛 Reportar bug — sempre à mão, para falar com a equipe.</li>
      </ul>
    </section>

<?php endif; ?>

    <footer class="man-fim">
      <div class="man-fim-badge">🎓</div>
      <h3>Chegou ao fim!</h3>
      <p class="lms-small lms-muted">Qualquer dúvida que este manual não resolveu, fale com a gente — <?= $papel === 'aluno' ? 'use o 🎧 Suporte no mapa' : 'e a gente melhora este manual' ?>.</p>
      <a class="lms-btn lms-btn-primary" href="<?= e(lms_home($u)) ?>" style="margin-top:14px">Voltar para a plataforma ▶</a>
    </footer>
  </div>
</main>

<style>
  .man-wrap { display:grid; grid-template-columns:264px 1fr; gap:26px; align-items:start;
    max-width:1180px; margin:0 auto; padding:24px 20px 64px }

  /* ── navegação ── */
  .man-nav { position:sticky; top:16px; background:var(--lms-white); border:var(--lms-border-game);
    border-radius:var(--lms-radius-lg, 16px); box-shadow:var(--lms-shadow-crisp-sm); padding:16px;
    max-height:calc(100vh - 32px); overflow:auto }
  .man-nav-topo { display:flex; gap:10px; align-items:center; margin-bottom:10px }
  .man-nav-ico { font-size:26px; flex:none }
  .man-nav-topo strong { display:block; font-family:var(--lms-font-ui); font-size:13.5px; color:var(--lms-heading); line-height:1.25 }
  .man-prog-txt { font-size:11px; color:var(--lms-ink-soft); font-family:var(--lms-font-ui); font-weight:600 }
  .man-prog { height:6px; background:var(--lms-border); border-radius:999px; overflow:hidden; margin-bottom:12px }
  .man-prog-fill { height:100%; width:0; border-radius:999px;
    background:linear-gradient(90deg, var(--lms-primary), var(--lms-success)); transition:width .35s var(--lms-ease, ease) }
  .man-busca { font-size:13px; padding:9px 12px; margin-bottom:10px }
  #man-links { display:flex; flex-direction:column; gap:2px }
  .man-link { display:flex; align-items:center; gap:7px; padding:7px 9px; border-radius:8px; cursor:pointer;
    font-size:12.5px; color:var(--lms-ink-soft); text-decoration:none; line-height:1.3;
    border:1.5px solid transparent; transition:background .15s ease }
  .man-link:hover { background:var(--lms-bg) }
  .man-link.is-atual { background:var(--lms-sky, #D3ECF9); border-color:var(--lms-primary); color:var(--lms-heading); font-weight:700 }
  .man-link-tick { flex:none; width:15px; height:15px; border-radius:50%; border:1.5px solid var(--lms-border);
    display:flex; align-items:center; justify-content:center; font-size:8px; color:transparent }
  .man-link.is-lido .man-link-tick { background:var(--lms-success); border-color:var(--lms-success); color:#fff }
  .man-troca { margin-top:14px; padding-top:12px; border-top:var(--lms-border) }
  .man-troca-btn { display:block; padding:6px 9px; border-radius:8px; font-size:12px; text-decoration:none;
    color:var(--lms-ink-soft); font-family:var(--lms-font-ui) }
  .man-troca-btn:hover { background:var(--lms-bg) }
  .man-troca-btn.is-ativo { background:var(--lms-heading); color:#fff; font-weight:700 }

  /* ── documento ── */
  .man-doc { min-width:0 }
  .man-capa { display:flex; gap:18px; align-items:center; padding:28px 30px; border-radius:var(--lms-radius-lg, 16px);
    border:var(--lms-border-game); box-shadow:var(--lms-shadow-crisp); position:relative; overflow:hidden;
    background:radial-gradient(600px 200px at 90% -20%, rgba(41,173,220,.3), transparent 60%),
               linear-gradient(140deg, #2A4971, #2B3558) }
  .man-capa-ico { font-size:48px; flex:none; filter:drop-shadow(0 3px 6px rgba(0,0,0,.4)) }
  .man-capa h1 { color:#fff; font-size:var(--lms-title-md); margin:0 }
  .man-capa p { color:rgba(255,255,255,.82); font-size:14px; margin:6px 0 0; line-height:1.5 }
  .man-dica { background:var(--lms-sand, #FBEED3); border:var(--lms-border-game); border-radius:var(--lms-radius-md);
    padding:12px 16px; font-size:13.5px; margin:16px 0 0; line-height:1.55 }

  .man-sec { background:var(--lms-white); border:var(--lms-border-game); border-radius:var(--lms-radius-lg, 16px);
    box-shadow:var(--lms-shadow-crisp-sm); padding:26px 30px; margin-top:18px; scroll-margin-top:16px }
  .man-sec h2 { font-size:20px; margin:0 0 10px }
  .man-sec p { font-size:14.5px; line-height:1.65; color:var(--lms-ink) }
  .man-lista { padding-left:20px; margin:10px 0 0; font-size:14.5px; line-height:1.75 }
  .man-lista li { margin-bottom:7px }
  .man-caixa { border-radius:var(--lms-radius-md); padding:13px 16px; margin-top:16px; font-size:13.5px; line-height:1.6 }
  .man-caixa.is-dica { background:var(--lms-sky, #D3ECF9); border-left:4px solid var(--lms-primary) }
  .man-caixa.is-atencao { background:var(--lms-sand, #FBEED3); border-left:4px solid #C98A12 }

  /* ── mapa mental (árvore clicável) ── */
  .man-arv { margin:16px 0 0 }
  .man-arv-item { display:flex; align-items:center; gap:12px; width:100%; text-align:left; cursor:pointer;
    background:var(--lms-white); border:2.5px solid var(--lms-heading); border-radius:var(--lms-radius-md);
    padding:12px 16px; font-family:inherit; box-shadow:0 3px 0 rgba(30,37,67,.25);
    transition:transform .15s var(--lms-ease, ease) }
  .man-arv-item:hover { transform:translateX(4px) }
  .man-arv-item.is-aberto { background:var(--lms-sky, #D3ECF9) }
  .man-arv-ico { font-size:22px; flex:none }
  .man-arv-nome { font-family:var(--lms-font-ui); font-weight:800; font-size:15px; color:var(--lms-heading) }
  .man-arv-tag { margin-left:auto; font-size:11.5px; color:var(--lms-ink-soft); font-family:var(--lms-font-ui) }
  .man-arv-seta { text-align:center; color:var(--lms-primary); font-size:13px; padding:3px 0 }
  .man-arv-txt { font-size:13.5px; line-height:1.6; color:var(--lms-ink-soft); padding:10px 16px 4px 50px }

  /* ── passos / receita ── */
  .man-passos { display:flex; flex-direction:column; gap:9px; margin-top:14px }
  .man-passo { display:flex; gap:12px; align-items:flex-start; padding:12px 14px; cursor:pointer;
    border:var(--lms-border); border-radius:var(--lms-radius-md); background:var(--lms-white);
    transition:background .15s ease }
  .man-passo:hover { background:var(--lms-bg) }
  .man-passo .man-check { display:none }
  .man-passo-n { flex:none; width:26px; height:26px; border-radius:50%; background:var(--lms-primary); color:#fff;
    font-family:var(--lms-font-ui); font-weight:800; font-size:12.5px;
    display:flex; align-items:center; justify-content:center; transition:background .2s ease }
  .man-passo-txt { font-size:13.5px; line-height:1.6 }
  .man-passo.is-feito { background:var(--lms-success-soft, #DFF3E4); border-color:var(--lms-success) }
  .man-passo.is-feito .man-passo-n { background:var(--lms-success) }
  .man-passo.is-feito .man-passo-n::after { content:'✓' }
  .man-passo.is-feito .man-passo-n { font-size:0 }
  .man-passo.is-feito .man-passo-n::after { font-size:13px }

  /* ── grid de dinâmicas / KPIs ── */
  .man-din-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(128px, 1fr)); gap:8px; margin:14px 0 }
  .man-din { display:flex; flex-direction:column; align-items:center; gap:5px; padding:12px 8px; cursor:pointer;
    background:var(--lms-white); border:var(--lms-border-game); border-radius:var(--lms-radius-md);
    font-family:inherit; text-align:center; transition:transform .15s var(--lms-ease, ease), background .15s ease }
  .man-din:hover { transform:translateY(-3px); background:var(--lms-sky, #D3ECF9) }
  .man-din.is-ativo { background:var(--lms-heading); color:#fff }
  .man-din-ico { font-size:22px }
  .man-din-nome { font-family:var(--lms-font-ui); font-weight:700; font-size:11.5px; line-height:1.25 }
  .man-din-painel { border:2px dashed var(--lms-border); border-radius:var(--lms-radius-md); min-height:96px;
    background:var(--lms-bg); padding:4px }
  .man-din-card { padding:16px 18px }
  .man-din-card h4 { font-size:16px; margin:0 0 4px }
  .man-din-card .man-oq { font-size:13.5px; color:var(--lms-ink-soft); margin:0 0 10px }
  .man-din-card .man-qd { font-size:13.5px; line-height:1.6; background:var(--lms-white);
    border-left:4px solid var(--lms-success); border-radius:6px; padding:10px 13px }
  .man-din-card .man-qd b { color:var(--lms-success) }

  /* ── simulador de desbloqueio ── */
  .man-sim { border:var(--lms-border-game); border-radius:var(--lms-radius-md); overflow:hidden; margin-top:14px }
  .man-sim-botoes { display:flex; flex-wrap:wrap; gap:6px; padding:12px; background:var(--lms-bg);
    border-bottom:var(--lms-border) }
  .man-sim-btn { padding:8px 13px; border-radius:999px; cursor:pointer; font-family:var(--lms-font-ui);
    font-weight:700; font-size:12.5px; background:var(--lms-white); border:var(--lms-border-game) }
  .man-sim-btn.is-ativo { background:var(--lms-primary); color:#fff; border-color:var(--lms-primary) }
  .man-sim-palco { display:flex; gap:16px; align-items:center; padding:20px;
    background:linear-gradient(180deg, #CDE9F5, #A9D9E8) }
  .man-sim-pin { flex:none; width:52px; height:52px; border-radius:50%; border:3px solid #fff;
    display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:800;
    background:rgba(43,52,80,.92); color:#fff; box-shadow:0 3px 10px rgba(16,24,40,.5);
    transition:background .25s ease }
  .man-sim-pin.is-livre { background:var(--lms-primary); animation:man-pulsa 2s ease-in-out infinite }
  @keyframes man-pulsa { 0%,100% { box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 0 rgba(41,173,220,.6) }
                         50% { box-shadow:0 3px 10px rgba(16,24,40,.5), 0 0 0 14px rgba(41,173,220,0) } }
  .man-sim-txt { font-size:13.5px; line-height:1.55; color:var(--lms-heading); margin:0; font-weight:600 }

  /* ── perguntas do gestor ── */
  .man-perg { display:flex; flex-direction:column; gap:8px; margin-top:14px }
  .man-perg-item { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;
    padding:12px 16px; border:var(--lms-border); border-radius:var(--lms-radius-md); text-decoration:none;
    background:var(--lms-white); transition:transform .15s var(--lms-ease, ease), background .15s ease }
  .man-perg-item:hover { transform:translateX(4px); background:var(--lms-sky, #D3ECF9) }
  .man-perg-q { font-size:14px; color:var(--lms-heading); font-style:italic }
  .man-perg-r { font-family:var(--lms-font-ui); font-weight:700; font-size:12.5px; color:var(--lms-primary) }

  /* ── pins demo (aluno) ── */
  .man-pins { display:flex; gap:22px; justify-content:center; padding:22px; border-radius:var(--lms-radius-md);
    background:linear-gradient(180deg, #DCEEE9, #97D5C9); margin:14px 0 }
  .man-pin-demo { width:44px; height:44px; border-radius:50%; border:3px solid #fff; cursor:pointer;
    font-family:var(--lms-font-ui); font-weight:800; font-size:16px; color:#fff;
    box-shadow:0 3px 10px rgba(16,24,40,.5); transition:transform .15s var(--lms-ease, ease) }
  .man-pin-demo:hover { transform:scale(1.15) }
  .man-pin-demo.is-available { background:var(--lms-primary); animation:man-pulsa 2s ease-in-out infinite }
  .man-pin-demo.is-done { background:var(--lms-success) }
  .man-pin-demo.is-locked { background:rgba(43,52,80,.92) }

  /* ── XP (aluno) ── */
  .man-xp { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px }
  .man-xp-item { flex:1; min-width:130px; text-align:center; padding:14px 12px; border-radius:var(--lms-radius-md);
    background:var(--lms-sand, #FBEED3); border:var(--lms-border-game) }
  .man-xp-item b { display:block; font-family:var(--lms-font-ui); font-size:24px; color:#8A6D1B }
  .man-xp-item span { font-size:12px; color:var(--lms-ink-soft); line-height:1.4; display:block; margin-top:3px }

  /* ── fim ── */
  .man-fim { text-align:center; padding:34px 20px; margin-top:18px; border-radius:var(--lms-radius-lg, 16px);
    border:var(--lms-border-game); background:var(--lms-white); box-shadow:var(--lms-shadow-crisp-sm) }
  .man-fim-badge { width:70px; height:70px; margin:0 auto 12px; border-radius:50%; font-size:32px;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg, var(--lms-primary), var(--lms-success)) }

  @media (max-width: 900px) {
    .man-wrap { grid-template-columns:1fr; padding:16px 12px 40px }
    .man-nav { position:static; max-height:none }
    .man-sec { padding:20px 18px }
    .man-capa { padding:20px }
    .man-capa-ico { font-size:36px }
  }
</style>

<script>
const DINAMICAS = <?= json_encode(array_map(fn($d) => ['ico' => $d[0], 'nome' => $d[1], 'oque' => $d[3], 'quando' => $d[4]],
      $papel === 'gestor' ? array_map(fn($k) => [$k[0], $k[1], '', '', $k[2]], $kpis ?? []) : $dinamicas), JSON_UNESCAPED_UNICODE) ?>;

/* ── navegação lateral: monta dos <section>, com scroll-spy ── */
const secoes = [...document.querySelectorAll('.man-sec')];
const links = document.getElementById('man-links');
secoes.forEach(s => {
  const a = document.createElement('a');
  a.className = 'man-link';
  a.href = '#' + s.id;
  a.dataset.sec = s.id;
  a.innerHTML = `<span class="man-link-tick">✓</span><span>${s.dataset.titulo}</span>`;
  links.appendChild(a);
});

/* ── progresso de leitura: uma seção conta como lida ao ser vista ── */
const CHAVE = 'lms-manual-<?= e($papel) ?>';
const lidas = new Set(JSON.parse(localStorage.getItem(CHAVE) || '[]'));
function pinta() {
  let n = 0;
  secoes.forEach(s => {
    const lida = lidas.has(s.id);
    if (lida) n++;
    document.querySelector(`.man-link[data-sec="${s.id}"]`)?.classList.toggle('is-lido', lida);
  });
  const pct = Math.round(n / secoes.length * 100);
  document.getElementById('man-prog-fill').style.width = pct + '%';
  document.getElementById('man-prog-txt').textContent = pct + '% lido';
}
pinta();

// scroll-spy: a seção que ocupa o meio da tela vira a "atual" no menu;
// tudo que já passou pelo olhar conta como lido (e fica salvo)
function spy() {
  const linha = window.innerHeight * 0.75;   // "linha de leitura"
  let atual = null, mudou = false;
  secoes.forEach(s => {
    if (s.classList.contains('lms-hide')) return;
    const r = s.getBoundingClientRect();
    if (r.top <= window.innerHeight * 0.4 && r.bottom > 60) atual = s.id;   // domina a tela
    // lida = já cruzou a linha de leitura (inclui as que ficaram acima)
    if (r.top < linha && !lidas.has(s.id)) { lidas.add(s.id); mudou = true; }
  });
  if (atual) document.querySelectorAll('.man-link').forEach(l => l.classList.toggle('is-atual', l.dataset.sec === atual));
  if (mudou) { localStorage.setItem(CHAVE, JSON.stringify([...lidas])); pinta(); }
}
addEventListener('scroll', spy, { passive: true });
addEventListener('resize', spy);
spy();

/* ── busca: filtra as seções pelo texto visível ── */
const busca = document.getElementById('man-busca');
const limpa = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
secoes.forEach(s => s.dataset.busca = limpa(s.textContent));
busca.addEventListener('input', () => {
  const q = limpa(busca.value.trim());
  secoes.forEach(s => {
    const bate = !q || s.dataset.busca.includes(q);
    s.classList.toggle('lms-hide', !bate);
    document.querySelector(`.man-link[data-sec="${s.id}"]`)?.classList.toggle('lms-hide', !bate);
  });
});

/* ── mapa mental: abre a explicação da camada ── */
document.querySelectorAll('.man-arv-item').forEach(b => b.addEventListener('click', () => {
  const alvo = document.getElementById('cam-' + b.dataset.camada);
  const abrindo = alvo.classList.contains('lms-hide');
  document.querySelectorAll('.man-arv-txt').forEach(t => t.classList.add('lms-hide'));
  document.querySelectorAll('.man-arv-item').forEach(i => i.classList.remove('is-aberto'));
  if (abrindo) { alvo.classList.remove('lms-hide'); b.classList.add('is-aberto'); }
}));

/* ── receita: checklist com progresso salvo ── */
const CH_PASSOS = 'lms-manual-passos-<?= e($papel) ?>';
const feitos = new Set(JSON.parse(localStorage.getItem(CH_PASSOS) || '[]'));
document.querySelectorAll('.man-check').forEach(c => {
  const lbl = c.closest('.man-passo');
  c.checked = feitos.has(c.dataset.passo);
  lbl.classList.toggle('is-feito', c.checked);
  c.addEventListener('change', () => {
    c.checked ? feitos.add(c.dataset.passo) : feitos.delete(c.dataset.passo);
    lbl.classList.toggle('is-feito', c.checked);
    localStorage.setItem(CH_PASSOS, JSON.stringify([...feitos]));
  });
});

/* ── catálogo (dinâmicas do admin / KPIs do gestor) ── */
const painel = document.getElementById('man-din-painel');
document.querySelectorAll('.man-din').forEach(b => b.addEventListener('click', () => {
  const d = DINAMICAS[+b.dataset.din];
  if (!d) return;
  document.querySelectorAll('.man-din').forEach(x => x.classList.toggle('is-ativo', x === b));
  painel.innerHTML = `<div class="man-din-card lms-pop">
    <h4>${d.ico} ${d.nome}</h4>
    ${d.oque ? `<p class="man-oq">${d.oque}</p>` : ''}
    <div class="man-qd"><b>${d.oque ? 'Quando usar:' : 'O que significa:'}</b> ${d.quando}</div>
  </div>`;
}));

/* ── pins demo (aluno) ── */
const PINS = <?= json_encode(array_map(fn($p) => ['tit' => $p[2], 'txt' => $p[3]], $pins ?? []), JSON_UNESCAPED_UNICODE) ?>;
const pinPainel = document.getElementById('man-pin-painel');
document.querySelectorAll('.man-pin-demo').forEach(b => b.addEventListener('click', () => {
  const p = PINS[+b.dataset.pin];
  pinPainel.innerHTML = `<div class="man-din-card lms-pop"><h4>${p.tit}</h4><p class="man-oq" style="margin:0">${p.txt}</p></div>`;
}));

/* ── simulador de desbloqueio (admin) ── */
const SIM = {
  sequencia: ['🔒', false, '<strong>🔗 Evolução:</strong> a fase abre quando o aluno conclui a ANTERIOR na ordem. É o padrão — a jornada clássica, uma fase de cada vez.'],
  data: ['📅', false, '<strong>📅 Data:</strong> fica fechada até o dia marcado, mesmo que o aluno já tenha feito tudo. Serve para conteúdo com data (campanha, reciclagem anual, lançamento).'],
  xp: ['⭐', false, '<strong>⭐ XP:</strong> abre quando o aluno junta X pontos de experiência, não importa por onde. Cria conteúdo "de elite", conquistado pelo esforço total.'],
  livre: ['!', true, '<strong>🔓 Sempre aberta</strong> (XP = 0): liberada desde o primeiro dia. Use em vitrines, conteúdo opcional ou quando a ordem não importa.'],
};
const simPin = document.getElementById('sim-pin'), simTxt = document.getElementById('sim-txt');
function simMostra(regra) {
  const [ico, livre, txt] = SIM[regra];
  simPin.textContent = ico;
  simPin.classList.toggle('is-livre', livre);
  simTxt.innerHTML = txt;
}
document.querySelectorAll('.man-sim-btn').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('.man-sim-btn').forEach(x => x.classList.toggle('is-ativo', x === b));
  simMostra(b.dataset.regra);
}));
if (simPin) simMostra('sequencia');
</script>
</body></html>
