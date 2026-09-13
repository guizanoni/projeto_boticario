<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Conteúdo REAL: "Da Base ao Look Completo" —
// categoria de maquiagem e Serviço Completo de Make.
//
// Transposição FIEL do treinamento entregue pelo cliente (HTML linear
// de 11 etapas, em boticario/materiais/) para a hierarquia do engine:
//   trilha "Universo da Maquiagem" → treinamento "Da Base ao Look
//   Completo" → 5 fases (pins no mapa) → 11 etapas, uma por etapa do
//   HTML, na mesma ordem e com os mesmos textos.
// Mídias do cliente em assets/make/ (imagens e áudios). O VÍDEO da
// etapa 3 é enviado pelo editor depois do deploy (fica em data/midia) —
// a etapa nasce sem URL e o admin cola/envia o arquivo.
//
// Regras de jogo (gamificação para adultos — aprendizagem por domínio):
//   · fases 1–4 são formativas: sem nota mínima, conclui quem completa;
//   · a simulação deixa errar com feedback e escolher de novo; conta
//     como acerto só a escolha certa de primeira;
//   · o quiz final (fase 5) é a verificação: nota mínima 80 % (4 de 5),
//     abaixo disso o aluno refaz a fase.
// ──────────────────────────────────────────────────────────────

function conteudo_servico_make_url(string $arquivo): string {
    return '/boticario/assets/make/' . $arquivo;
}

// Estrutura completa: [trilha, treinamento, fases[ etapas[] ]].
function conteudo_servico_make(): array {
    $u = 'conteudo_servico_make_url';

    $trilha = [
        'nome'      => 'Universo da Maquiagem',
        'narrativa' => 'Da base ao look completo: a jornada de quem transforma atendimento em experiência de beleza — do primeiro produto ao Serviço Completo de Make.',
        'mapa'      => $u('mapa-loja.webp'),
    ];
    $treinamento = [
        'nome'      => 'Da Base ao Look Completo',
        'descricao' => 'Trilha de aprendizado sobre a categoria de maquiagem e o Serviço Completo de Make.',
    ];

    // fase => [nome, pin_x, pin_y, nota_minima, etapas[]] — pins medidos sobre os
    // pontos verdes da arte final (materiais/mapa-final.png), em % da imagem
    // etapa => [titulo, tipo, minigame_tipo, config]
    $fases = [
        ['Bem-vindo ao universo da maquiagem', 22, 69, 0, [
            ['Vamos entrar no universo da maquiagem?', 'leitura', 'leitura', [
                'texto' => [
                    'Nesta jornada, você vai explorar diferentes estilos, descobrir como construir um look completo passo a passo e experimentar novas formas de conduzir o atendimento.',
                    'No final, você vai conectar tudo isso à jornada Botileza e ao Serviço Completo de Make, descobrindo mais possibilidades de beleza para cada cliente.',
                ],
                'pontos_confirmacao' => [['id' => 'p1', 'label' => 'Estou pronta(o) para entrar no universo da maquiagem.']],
            ]],
            ['Os 4 estilos de maquiagem', 'minigame', 'historia', [
                'cenas' => [
                    ['icone' => '💄', 'titulo' => 'Os 4 estilos de maquiagem', 'imagem' => '',
                     'texto' => 'Existem diferentes estilos de maquiagem, cada um com técnicas e objetivos próprios. Avance pelas cartas para descobrir onde cada um se aplica — e qual é o estilo em que você deve concentrar seu foco na venda.'],
                    ['icone' => '🎭', 'titulo' => 'Maquiagem Efeitos Especiais', 'imagem' => $u('estilo-efeitos-especiais.png'),
                     'texto' => 'Caracterização completa de um personagem, com produtos de alta cobertura e longa duração — inclusive próteses. Pense em filmes como "Alice no País das Maravilhas" e "O Máscara".'],
                    ['icone' => '🎬', 'titulo' => 'Maquiagem Cênica', 'imagem' => $u('estilo-cenica.png'),
                     'texto' => 'Busca a caracterização completa com produtos de poder de camuflagem — como nos filmes "Frida" e "Malévola".'],
                    ['icone' => '🎨', 'titulo' => 'Maquiagem Artística', 'imagem' => $u('estilo-artistica.png'),
                     'texto' => 'Estilo lúdico e criativo, focado em correção e caracterização, para looks divertidos e expressivos. Comum em passarelas, editoriais e transformações vistas nas redes sociais.'],
                    ['icone' => '✨', 'titulo' => 'Maquiagem Social — foco da venda', 'imagem' => $u('estilo-social.png'),
                     'texto' => 'Busca naturalidade e leveza, realçando a beleza de cada pessoa. É o "tapete vermelho da vida real" — serve para qualquer ocasião, de uma reunião de negócios a um encontro romântico. <strong>É o estilo em que você deve concentrar seu foco na venda.</strong>'],
                ],
            ]],
            ['Vídeo: entendendo a categoria de maquiagem', 'minigame', 'video', [
                'url'       => '',
                'descricao' => 'Que tal acompanhar uma maquiagem social do começo ao fim? Assista ao vídeo para conhecer o passo a passo, entender a sequência de aplicação de cada produto e se preparar para criar experiências de beleza que encantam cada cliente.',
            ]],
        ]],

        ['Os 16 produtos', 40, 39, 0, [
            ['Quantos produtos você imagina?', 'minigame', 'quiz', [
                'intro' => 'Pense em uma maquiagem social completa. Quantos produtos você acha que são necessários, sem contar preparação de pele, pincéis ou acessórios?',
                'perguntas' => [[
                    'enunciado'    => 'Quantos produtos, no mínimo, compõem uma maquiagem social completa?',
                    'alternativas' => ['Até 6', 'Entre 7 e 11', 'Entre 12 e 15', '16 ou mais'],
                    'corretas'     => [3],
                    'explicacao'   => 'A resposta pode surpreender: <strong>16 itens</strong> são necessários no mínimo para uma maquiagem social completa — 7 na pele, 5 nos olhos e 4 nos lábios. Uma maquiagem social pode ser feita com poucos produtos — mas na venda, mostrar a variedade do nosso portfólio é o que estimula o desejo de compra.',
                ]],
            ]],
            ['Correção de pele — 7 produtos', 'minigame', 'sequencia', [
                'instrucao' => 'Toque nas cartas na ordem correta de aplicação. A cada acerto, você descobre a função daquele produto.',
                'so_imagem' => false,
                'passos' => [
                    ['texto' => 'Primer',     'imagem' => $u('pele-1-primer.png'),     'descricao' => 'Uniformiza a textura da pele e fixa a maquiagem.'],
                    ['texto' => 'Corretivo',  'imagem' => $u('pele-2-corretivo.png'),  'descricao' => 'Suaviza imperfeições com pigmentação de cor diferente do tom da pele.'],
                    ['texto' => 'Base',       'imagem' => $u('pele-3-base.png'),       'descricao' => 'Uniformiza o tom da pele.'],
                    ['texto' => 'Pó',         'imagem' => $u('pele-4-po.png'),         'descricao' => 'Sela a maquiagem, garantindo durabilidade.'],
                    ['texto' => 'Contorno',   'imagem' => $u('pele-5-contorno.png'),   'descricao' => 'Suaviza e diminui regiões do rosto, trazendo formas mais harmoniosas.'],
                    ['texto' => 'Blush',      'imagem' => $u('pele-6-blush.png'),      'descricao' => 'Dá rubor à pele.'],
                    ['texto' => 'Iluminador', 'imagem' => $u('pele-7-iluminador.png'), 'descricao' => 'Ressalta e projeta regiões do rosto, trazendo luminosidade.'],
                ],
            ]],
            ['Realçando o olhar', 'minigame', 'sequencia', [
                'instrucao' => 'Toque nas cartas na ordem correta de aplicação. A cada acerto, você descobre a função daquele passo.',
                'so_imagem' => false,
                'passos' => [
                    ['texto' => 'Primer ou Corretivo', 'imagem' => $u('olhos-1-primer-ou-corretivo.png'), 'descricao' => 'Uniformiza a pálpebra e prepara a base para as sombras renderem melhor.'],
                    ['texto' => 'Sombra',             'imagem' => $u('olhos-2-sombra.png'),             'descricao' => 'Adiciona cor, profundidade e dimensão ao olhar.'],
                    ['texto' => 'Esfumar',            'imagem' => $u('olhos-3-esfumar.png'),            'descricao' => 'Difumina os limites da sombra, criando transição suave entre os tons.'],
                    ['texto' => 'Marcar',             'imagem' => $u('olhos-4-marcar.png'),             'descricao' => 'Define a linha do olho, criando a base para o desenho final.'],
                    ['texto' => 'Lápis',              'imagem' => $u('olhos-5-lapis.png'),              'descricao' => 'Contorna e intensifica o traço, dando precisão ao desenho do olhar.'],
                    ['texto' => 'Máscara de Cílios',  'imagem' => $u('olhos-6-mascara-de-cilios.png'),  'descricao' => 'Alonga, curva e realça os cílios, finalizando o olhar.'],
                ],
            ]],
            ['Realçando os lábios — 4 produtos', 'minigame', 'historia', [
                'cenas' => [
                    ['icone' => '💋', 'titulo' => 'Realçando os lábios — 4 produtos', 'imagem' => '', 'texto' => 'Avance pelas cartas para descobrir a função de cada produto.'],
                    ['icone' => '1️⃣', 'titulo' => 'Balm Hidratante', 'imagem' => $u('labios-1-balm-hidratante.png'), 'texto' => 'Hidrata e suaviza os lábios, preparando para o batom.'],
                    ['icone' => '2️⃣', 'titulo' => 'Contorno Labial', 'imagem' => $u('labios-2-contorno-labial.png'), 'texto' => 'Define o desenho dos lábios e impede que o batom borre.'],
                    ['icone' => '3️⃣', 'titulo' => 'Batom',           'imagem' => $u('labios-3-batom.png'),           'texto' => 'Traz cor e cobertura aos lábios.'],
                    ['icone' => '4️⃣', 'titulo' => 'Gloss',           'imagem' => $u('labios-4-gloss.png'),           'texto' => 'Adiciona brilho e volume aos lábios para um acabamento luminoso.'],
                ],
            ]],
        ]],

        ['Adaptando a venda', 57, 71.5, 0, [
            ['Versão resumida x versão completa', 'leitura', 'leitura', [
                'texto' => [
                    'Agora que você conhece os 16 itens, veja como adaptar a apresentação conforme o perfil da pessoa cliente.',
                    '<strong>Versão resumida</strong><br>Apresente produtos multifuncionais: para a pele, uma <b>base multi-benefícios</b> junto de uma <b>paleta multifuncional</b>; para os olhos, uma <b>máscara com efeito expressivo</b>; para os lábios, um <b>batom de longa duração</b>. Esses produtos já entregam uma transformação impactante com poucas peças.',
                    '<strong>Versão completa</strong><br>Apresente a sequência completa de no mínimo <b>16 produtos</b> que você aprendeu — pele, olhos e lábios. Pratique esse modelo de apresentar uma solução de beleza completa, junto com dicas de aplicação, benefícios, vantagens e características dos produtos.',
                ],
                'pontos_confirmacao' => [
                    ['id' => 'p1', 'label' => 'Sei apresentar a versão resumida, com produtos multifuncionais.'],
                    ['id' => 'p2', 'label' => 'Sei apresentar a versão completa, com os 16 produtos, dicas de aplicação, benefícios e características.'],
                ],
            ]],
        ]],

        ['Jornada Boti Beleza', 75, 37.5, 0, [
            ['Relembrando o Boti Beleza', 'minigame', 'historia', [
                'cenas' => [
                    ['icone' => '🧭', 'titulo' => 'Relembrando o Boti Beleza', 'imagem' => '', 'texto' => 'Toda venda de maquiagem acontece dentro da jornada de atendimento que você já conhece. Avance por cada etapa para ver a imagem e relembrar.'],
                    ['icone' => '🛎️', 'titulo' => 'Acolha',       'imagem' => $u('boti-1-acolha.webp'),       'texto' => 'Receber e conhecer: se apresentar, perguntar o nome e entender o motivo da visita.'],
                    ['icone' => '💬', 'titulo' => 'Personalize',  'imagem' => $u('boti-2-personalize.webp'),  'texto' => 'Demonstrar e experimentar: consultar o histórico no ID Cliente e convidar para experimentação.'],
                    ['icone' => '✨', 'titulo' => 'Potencialize', 'imagem' => $u('boti-3-potencialize.webp'), 'texto' => 'Oferecer benefícios e aumentar o boleto: sugerir itens complementares com base no perfil.'],
                    ['icone' => '🎁', 'titulo' => 'Encante',      'imagem' => $u('boti-4-encante.webp'),      'texto' => 'Entregar e celebrar: cuidar da embalagem e reforçar os benefícios do Beautybox.'],
                ],
            ]],
            ['Do diagnóstico ao encantamento', 'minigame', 'investigacao', [
                'modo'          => 'dominio',
                'rotulo_seguir' => 'Continuar atendimento',
                'rotulo_fim'    => 'Concluir simulação',
                'cartas' => [
                    ['icone' => '🛎️', 'titulo' => 'ETAPA: ACOLHA', 'audio' => $u('amanda-1-acolha.mp3'), 'audio_quem' => 'AMANDA',
                     'pista' => 'Amanda entrou na loja. Agora é com você: conduza o atendimento até o fechamento da compra. Ouça o que ela diz:',
                     'pergunta' => 'O que você faz agora?',
                     'opcoes' => [
                        ['texto' => 'Levo Amanda direto para a bancada, sem falar de fidelidade ainda.', 'correta' => false, 'vai' => null,
                         'feedback' => 'No processo Botileza, a identificação do cliente e a conexão com o Beautybox devem acontecer logo na etapa Acolha — pular esse passo faz a gente perder a chance de personalizar toda a jornada a partir do ID Cliente. Escolha outra opção.'],
                        ['texto' => 'Convido Amanda até a bancada de testes e já aproveito para pedir o CPF, explicando o Programa de Fidelidade.', 'correta' => true, 'vai' => 1,
                         'feedback' => 'Ótimo! Você já conecta o atendimento ao ID Cliente logo no início, abrindo espaço para uma jornada personalizada com base no histórico dela.'],
                        ['texto' => 'Pego a base que ela pediu e já encaminho para o caixa.', 'correta' => false, 'vai' => null,
                         'feedback' => 'Isso transforma o atendimento em uma simples troca de produto. O processo Botileza pede que a etapa Acolha sirva para criar conexão e entender a real necessidade da cliente antes de qualquer coisa. Escolha outra opção.'],
                     ]],
                    ['icone' => '💬', 'titulo' => 'ETAPA: PERSONALIZE', 'audio' => $u('amanda-2-personalize.mp3'), 'audio_quem' => 'AMANDA',
                     'pista' => 'Na bancada de testes, Amanda comenta sobre a preparação da pele. Ouça:',
                     'pergunta' => 'Como você responde?',
                     'opcoes' => [
                        ['texto' => 'Não comento nada e já aplico o creme antes da base.', 'correta' => false, 'vai' => null,
                         'feedback' => 'No Botileza, a etapa Personalize existe justamente para explicar benefícios e desfazer objeções da cliente. Aplicar sem explicar desperdiça a chance de gerar confiança e valor percebido. Escolha outra opção.'],
                        ['texto' => '"Realmente, pode pesar um pouco, mas vamos usar uma quantidade bem pequena."', 'correta' => false, 'vai' => null,
                         'feedback' => 'Reforçar um mito do produto vai contra o papel consultivo da etapa Personalize — o processo pede que a gente eduque a cliente com segurança técnica, não que valide informações incorretas. Escolha outra opção.'],
                        ['texto' => '"Esse é o maior mito da maquiagem! A hidratação correta é o que garante que a base assente perfeitamente e dure muito mais."', 'correta' => true, 'vai' => 2,
                         'feedback' => 'Perfeito! Você desmonta o mito com segurança e mostra domínio técnico — isso gera confiança para o próximo passo, o teste da cor.'],
                     ]],
                    ['icone' => '✨', 'titulo' => 'ETAPA: POTENCIALIZE', 'audio' => $u('amanda-3-potencialize.mp3'), 'audio_quem' => 'AMANDA',
                     'pista' => 'A cor foi aprovada e Amanda conta o que tem pela frente hoje. Ouça:',
                     'pergunta' => 'Como conduzir a oferta do Serviço Completo de Make?',
                     'opcoes' => [
                        ['texto' => 'Convido Amanda para o Serviço Completo, explicando que o valor é 100% convertido em produtos.', 'correta' => true, 'vai' => 3,
                         'feedback' => 'Excelente! Você aproveita o timing perfeito (o compromisso à noite) e mostra o valor real do serviço — sem parecer só uma venda a mais.'],
                        ['texto' => 'Ofereço mais alguns produtos avulsos, sem falar do serviço completo.', 'correta' => false, 'vai' => null,
                         'feedback' => 'A etapa Potencialize do Botileza existe para aumentar o boleto com base no que a cliente já demonstrou interesse. Ignorar o Serviço Completo — que tem conversão total em produto — é deixar a maior oportunidade de valor na mesa. Escolha outra opção.'],
                        ['texto' => 'Finalizo a venda só da base, sem oferecer mais nada.', 'correta' => false, 'vai' => null,
                         'feedback' => 'Isso pula completamente a etapa Potencialize. O processo Botileza pede que sempre haja ao menos uma tentativa de ampliar a experiência ou a venda antes do fechamento. Escolha outra opção.'],
                     ]],
                    ['icone' => '🎁', 'titulo' => 'ETAPA: ENCANTE', 'audio' => $u('amanda-4-encante.mp3'), 'audio_quem' => 'AMANDA',
                     'pista' => 'Hora de fechar o atendimento. Ouça a Amanda:',
                     'pergunta' => 'Como você finaliza o atendimento?',
                     'opcoes' => [
                        ['texto' => 'Finalizo a venda de forma rápida, sem nenhum mimo extra.', 'correta' => false, 'vai' => null,
                         'feedback' => 'A etapa Encante do Botileza é o que garante que a cliente queira voltar. Sem esse cuidado extra, a experiência fica incompleta e a fidelização fica mais fraca. Escolha outra opção.'],
                        ['texto' => 'Preparo a sacola com uma fragrância especial e uma fita com o nome dela, e comento sobre a pesquisa de satisfação.', 'correta' => true, 'vai' => null,
                         'feedback' => 'Perfeito! Esse cuidado no fechamento é o que faz Amanda querer voltar — e ainda garante uma boa nota na pesquisa de NPS.'],
                        ['texto' => 'Faço o mimo na sacola, mas esqueço de mencionar a pesquisa de satisfação.', 'correta' => false, 'vai' => null,
                         'feedback' => 'Quase lá! A etapa Encante do Botileza pede tanto o mimo quanto o reforço do NPS — a pesquisa de satisfação é o que fecha o ciclo de relacionamento com a cliente. Escolha outra opção.'],
                     ]],
                ],
            ]],
        ]],

        ['Quiz final', 85.5, 75.5, 80, [
            ['Quiz final', 'minigame', 'quiz', [
                'intro' => 'Vamos ver o que ficou da trilha!',
                'perguntas' => [
                    ['enunciado' => 'Qual estilo de maquiagem é o foco principal na venda do dia a dia da loja?',
                     'alternativas' => ['Cênica', 'Efeitos Especiais', 'Social', 'Artística'], 'corretas' => [2],
                     'explicacao' => 'A maquiagem social é o foco da venda porque busca naturalidade e é versátil para qualquer ocasião — do compromisso de trabalho a um encontro especial.'],
                    ['enunciado' => 'Quantos produtos, no mínimo, compõem uma maquiagem social completa?',
                     'alternativas' => ['10', '13', '16', '20'], 'corretas' => [2],
                     'explicacao' => 'São necessários no mínimo 16 itens: 7 para a pele, 5 para os olhos e 4 para os lábios, sem contar preparação, pincéis ou acessórios.'],
                    ['enunciado' => 'Qual a função do primer na pele?',
                     'alternativas' => ['Selar a maquiagem', 'Uniformizar a textura da pele e fixar a maquiagem', 'Dar rubor à pele', 'Corrigir olheiras'], 'corretas' => [1],
                     'explicacao' => 'O primer é sempre o primeiro passo porque prepara a pele para receber os demais produtos e ajuda a fixação durar mais.'],
                    ['enunciado' => 'Na etapa Personalize da jornada Botileza, o que a consultora deve fazer?',
                     'alternativas' => ['Só fechar a venda rapidamente', 'Consultar o histórico no ID Cliente e convidar para a experimentação', 'Pular direto para o Encante', 'Evitar falar sobre o Beautybox'], 'corretas' => [1],
                     'explicacao' => 'A etapa Personalize existe justamente para unir tecnologia (ID Cliente) e acolhimento (convite à experimentação), criando uma jornada personalizada.'],
                    ['enunciado' => 'Quando o Serviço Completo de Make é oferecido a uma cliente, o valor pago:',
                     'alternativas' => ['É perdido caso ela não compre nada', 'Não pode ser usado em produtos', 'É 100% convertido em produtos da loja', 'Só vale para maquiagem importada'], 'corretas' => [2],
                     'explicacao' => 'Esse é o principal argumento de venda do Serviço Completo: o valor pago não é "gasto", ele vira produto — o que reduz a objeção de preço.'],
                ],
            ]],
        ]],
    ];

    return [$trilha, $treinamento, $fases];
}

// Insere trilha → treinamento → fases → etapas pro cliente. Idempotente:
// se a trilha já existir (mesmo nome, mesmo cliente), não repete e devolve
// o id do treinamento que já está lá. Devolve o id do treinamento.
function conteudo_servico_make_inserir(PDO $db, int $clienteId): int {
    [$trilha, $trein, $fases] = conteudo_servico_make();

    $st = $db->prepare('SELECT id FROM trilha WHERE cliente_id = ? AND nome = ?');
    $st->execute([$clienteId, $trilha['nome']]);
    $trId = (int) $st->fetchColumn();
    if ($trId) {
        $st = $db->prepare('SELECT id FROM treinamento WHERE trilha_id = ? ORDER BY ordem, id LIMIT 1');
        $st->execute([$trId]);
        return (int) $st->fetchColumn();
    }

    $j = fn($a) => json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $emTransacao = !$db->inTransaction();
    if ($emTransacao) $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO trilha (cliente_id, nome, narrativa, cenario_mapa_url, ordem) VALUES (?,?,?,?,1)')
           ->execute([$clienteId, $trilha['nome'], $trilha['narrativa'], $trilha['mapa']]);
        $trId = (int) $db->lastInsertId();

        $db->prepare('INSERT INTO treinamento (trilha_id, nome, descricao, ordem) VALUES (?,?,?,1)')
           ->execute([$trId, $trein['nome'], $trein['descricao']]);
        $tid = (int) $db->lastInsertId();

        $stF = $db->prepare('INSERT INTO fase (treinamento_id, nome, ordem, pin_x, pin_y, desbloqueio, nota_minima) VALUES (?,?,?,?,?,\'sequencia\',?)');
        $stE = $db->prepare('INSERT INTO etapa (fase_id, ordem, tipo, minigame_tipo, obrigatoria, titulo, config) VALUES (?,?,?,?,1,?,?)');
        foreach ($fases as $fi => [$nome, $px, $py, $nota, $etapas]) {
            $stF->execute([$tid, $nome, $fi + 1, $px, $py, $nota]);
            $fid = (int) $db->lastInsertId();
            foreach ($etapas as $ei => [$titulo, $tipo, $mg, $cfg]) {
                $stE->execute([$fid, $ei + 1, $tipo, $mg, $titulo, $j($cfg)]);
            }
        }
        if ($emTransacao) $db->commit();
    } catch (Throwable $e) {
        if ($emTransacao && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return $tid;
}
