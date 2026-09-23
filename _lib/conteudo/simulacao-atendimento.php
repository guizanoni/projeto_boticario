<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Conteúdo REAL: "Simulação de atendimento" —
// fase nova do treinamento "Da Base ao Look Completo" (2026-09-23).
//
// Material do cliente em boticario/materiais/nova-dinamica/ (fora do repo):
//   00. Modelo de Atendimento Botileza + Beautybox (slides pra lideranças):
//       as 4 etapas, as 3 bandeiras, as perguntas do Cliente Oculto,
//       Boti Recicla, NPS, alto fluxo.
//   01. Roteiro NÃO linear: 7 personas de entrada, bandeiras obrigatórias,
//       critérios de transição, motivo da não compra, diálogos de exemplo.
//   02. Roteiro LINEAR "Amanda": compra de base que evolui pro Serviço
//       Completo de Make, fala a fala, nas 4 etapas.
//
// Vira o minigame `atendimento` (player.js M20): um chat em que a cliente
// fala e o aluno escolhe a própria resposta. Dois atendimentos:
//   1. Amanda — o roteiro linear inteiro, fala a fala (opções erradas
//      são os anti-padrões que o próprio material aponta);
//   2. Bia — persona "uso próprio, explorando" que vira objeção de preço
//      e sai sem comprar (o resultado válido do roteiro não linear:
//      motivo da não compra capturado, porta aberta, mimo de saída).
// Regras de jogo (mesma metodologia das outras fases): modo domínio —
// errou, a cliente reage, o coach explica, escolhe de novo; conta acerto,
// bandeira e checklist só de primeira. Fase formativa: sem nota mínima.
// ──────────────────────────────────────────────────────────────

const CONTEUDO_SIM_ATENDIMENTO_FASE = 'Simulação de atendimento';

function conteudo_sim_atendimento_comum(): array {
    return [
        'etapas'    => ['Acolha', 'Personalize', 'Potencialize', 'Encante'],
        'bandeiras' => [
            ['id' => 'identificacao',  'icone' => '🆔', 'nome' => 'Identificação'],
            ['id' => 'experimentacao', 'icone' => '🧪', 'nome' => 'Experimentação'],
            ['id' => 'beautybox',      'icone' => '💚', 'nome' => 'Beautybox'],
        ],
        'modo'          => 'dominio',
        'humor_inicial' => 50,
        'loja'          => 'Loja O Boticário',
        'nota_precos'   => 'Valores ilustrativos, só pra simulação — na loja vale a tabela vigente.',
    ];
}

// Perguntas do Cliente Oculto (slides 14–17) + os dois itens de fechamento
// que o material manda reforçar (NPS e Boti Recicla).
function conteudo_sim_atendimento_checklist(array $ids): array {
    $todos = [
        'apresentou'        => 'A pessoa que o atendeu se apresentou falando o próprio nome?',
        'nome'              => 'Perguntou qual era o seu nome ou te chamou pelo nome?',
        'motivo'            => 'Perguntou por que você foi até a loja? (uso próprio, presente ou serviço)',
        'cpf'               => 'Pediu o seu CPF ao longo do atendimento?',
        'cpf_motivo'        => 'Explicou o motivo da solicitação do CPF, comentando o Programa de Fidelidade (benefícios, pontos, promoções ou descontos)? — pergunta obrigatória da auditoria',
        'experimentar'      => 'Estimulou você a experimentar algum produto?',
        'aplicacao'         => 'Ofereceu a aplicação do produto na pele? (ritual de make, rosto ou corpo)',
        'adicional'         => 'Ofereceu algum produto ou promoção adicional além do que você buscava?',
        'fragrancia'        => 'Borrifou alguma fragrância na sua sacola?',
        'recicla'           => 'Mencionou o programa de reciclagem de embalagens (Boti Recicla)?',
        'nps'               => 'Avisou sobre a pesquisa de satisfação (NPS) e pediu a avaliação do atendimento?',
        'motivo_nao_compra' => 'Registrou o motivo da não compra no ID Cliente?',
        'mimo'              => 'Ofereceu um mimo de saída (amostra ou cupom) e convidou a voltar?',
    ];
    $out = [];
    foreach ($ids as $id) $out[] = ['id' => $id, 'texto' => $todos[$id]];
    return $out;
}

// ── Atendimento 1 · Amanda (roteiro linear, fala a fala) ──────────
function conteudo_sim_atendimento_amanda(): array {
    $c = conteudo_sim_atendimento_comum();
    $c['cliente'] = [
        'nome'      => 'Amanda',
        'avatar'    => '👩🏽',
        'persona'   => 'Uso próprio · item definido',
        'descricao' => 'Entrou na loja procurando uma base. A dela acabou e ela precisa repor — mas não tem certeza se usava a cor certa.',
    ];
    $c['briefing'] = [
        'titulo' => 'Sua vez de atender',
        'texto'  => 'Terça à tarde, loja tranquila. Amanda entra e para em frente às bases. Daqui pra frente, cada fala é sua: leia o que ela diz e escolha como responder. O objetivo é levar uma reposição de base até o Serviço Completo de Make — do jeito que o modelo de atendimento pede.',
        'botao'  => 'Receber a Amanda',
    ];
    $c['checklist'] = conteudo_sim_atendimento_checklist(['apresentou', 'nome', 'motivo', 'cpf', 'cpf_motivo', 'experimentar', 'aplicacao', 'adicional', 'fragrancia', 'recicla', 'nps']);
    $c['final'] = [
        'titulo' => 'Atendimento concluído',
        'texto'  => 'Uma reposição de base virou Serviço Completo, sacola cheia e uma cliente que quer voltar. É o que acontece quando as 4 etapas e as 3 bandeiras aparecem no atendimento — e não só na teoria.',
        'botao'  => 'Concluir atendimento',
    ];
    $c['turnos'] = [
        // ── ACOLHA ──
        ['etapa' => 0,
         'cena' => 'Amanda entra na loja, olha em volta e para em frente ao expositor de bases.',
         'pergunta' => 'Como você recebe a Amanda?',
         'opcoes' => [
            ['texto' => 'Olá, boa tarde! Seja muito bem-vinda à nossa loja! Meu nome é {nome}, e o seu? Me conta: hoje você está buscando algum produto específico para você, quer presentear alguém especial ou veio realizar um dos nossos serviços de beleza?',
             'correta' => true, 'oculto' => ['apresentou', 'nome', 'motivo'],
             'reacao' => ['Oi, {nome}! Eu sou a Amanda.', 'Na verdade, vim buscar uma base para mim. A minha acabou de terminar e preciso de uma reposição urgente.'],
             'feedback' => 'Três perguntas do Cliente Oculto respondidas numa fala só: se apresentou, perguntou o nome e entendeu o motivo da visita (uso próprio, presente ou serviço).'],
            ['texto' => 'Oi! Pode ficar à vontade, se precisar de alguma coisa é só me chamar.',
             'correta' => false,
             'reacao' => 'Tá bom… <i>(Amanda continua olhando as bases sozinha.)</i>',
             'feedback' => 'No Acolha, o Cliente Oculto pergunta: a pessoa se apresentou? perguntou o seu nome? perguntou por que você foi à loja? "Fica à vontade" pula as três. Se apresente, pergunte o nome e o motivo da visita.'],
            ['texto' => 'Boa tarde! Procurando base? Essa aqui é a mais vendida da loja, quer levar?',
             'correta' => false,
             'reacao' => 'Hum… eu nem sei se essa é a minha cor.',
             'feedback' => 'Vender antes de entender. Acolha é conexão e identificação da necessidade: se apresentar, perguntar o nome e o motivo da visita. O produto vem depois, no Personalize.'],
         ]],
        ['etapa' => 0,
         'pergunta' => 'Amanda quer repor a base. Como você segue?',
         'opcoes' => [
            ['texto' => 'Você quer repor com a mesma base ou quer testar outro acabamento? Temos opções incríveis que, além de deixar a pele linda, tratam também.',
             'correta' => true,
             'reacao' => 'Quero testar alguma base com uma cobertura mais natural para o dia a dia. Até porque não tenho certeza se estava usando a cor certa.',
             'feedback' => 'Abrir a escolha entre repor e testar é o que transforma reposição em consultoria. E ela acabou de te dar a pista principal: dúvida sobre a cor.'],
            ['texto' => 'Qual era a sua base? Vou pegar a mesma pra você.',
             'correta' => false,
             'reacao' => 'Era… ah, não lembro o nome. Acho que era a do frasco rosa?',
             'feedback' => 'Repor "a mesma" fecha a porta: a cliente pode nem saber se a cor estava certa. Ofereça a escolha entre repor e testar outro acabamento — é o que abre o Personalize.'],
            ['texto' => 'Ok! Me passa o seu CPF pra eu já cadastrar a venda.',
             'correta' => false,
             'reacao' => 'Ué, mas eu nem escolhi ainda…',
             'feedback' => 'Pedir o CPF "pra cadastrar a venda" é exatamente o que a bandeira 1 manda trocar: "pedir CPF" vira "oferecer benefício". E ainda nem entendemos o que ela precisa.'],
         ]],
        ['etapa' => 0,
         'pergunta' => 'Hora de identificar a cliente. Como você pede o CPF?',
         'opcoes' => [
            ['texto' => 'Perfeito! Vou te mostrar algumas opções e te ajudar com a cor correta também. Você poderia me informar o seu CPF? Assim eu já dou uma olhadinha no seu perfil no nosso Programa de Fidelidade pra ver se você tem algum benefício exclusivo ou mimo especial hoje!',
             'correta' => true, 'bandeira' => 'identificacao', 'oculto' => ['cpf', 'cpf_motivo'],
             'reacao' => ['Claro! É 123.456.789-00.', '<i>(Você digita o CPF no Mobshop: Amanda é cliente fidelidade.)</i>'],
             'feedback' => 'Bandeira 1 capturada: o CPF entrou como benefício, não como burocracia. É a pergunta obrigatória do Cliente Oculto — e é ela que liga o ID Cliente ao resto do atendimento.'],
            ['texto' => 'Me passa o seu CPF? É pra nota.',
             'correta' => false,
             'reacao' => 'Ah, pode ser sem CPF mesmo.',
             'feedback' => '"Pra nota" não dá motivo nenhum. A pergunta obrigatória do Cliente Oculto é: explicou o motivo da solicitação do CPF, comentando o Programa de Fidelidade (benefícios, pontos, promoções ou descontos)?'],
            ['texto' => 'Vamos direto pra bancada testar a cor! Depois a gente vê o cadastro.',
             'correta' => false,
             'reacao' => 'Combinado!',
             'feedback' => 'Sem o CPF agora, o ID Cliente fica fora da conversa: sem histórico, sem promoção personalizada, sem cashback. A identificação vem antes da experimentação porque alimenta tudo que vem depois.'],
         ]],
        ['etapa' => 0,
         'pergunta' => 'Cliente identificada. Como você fecha o Acolha?',
         'opcoes' => [
            ['texto' => 'Excelente, Amanda! Vi aqui que você é nossa cliente fidelidade. Pra garantir o tom exato e a textura perfeita pra sua pele, vou te convidar a sentar na nossa cadeira pra uma experimentação rápida e acharmos a sua base ideal!',
             'correta' => true, 'oculto' => ['experimentar'],
             'reacao' => 'Ah, ótimo!',
             'feedback' => 'Convite à experimentação feito — o Cliente Oculto pergunta exatamente isso: "estimulou a experimentar algum produto?". Transição limpa pro Personalize.'],
            ['texto' => 'Você é cliente fidelidade! Tem 320 pontos acumulados, quer usar hoje?',
             'correta' => false,
             'reacao' => 'Ah, legal… mas e a base?',
             'feedback' => 'Beautybox entra na conversa, sim — mas o momento agora é convidar pra experimentar. Sem experimentação não existe recomendação segura; é ela que sustenta o Personalize.'],
            ['texto' => 'Cliente fidelidade! Então a base que você usava era a mesma de sempre, vou pegar o mesmo tom.',
             'correta' => false,
             'reacao' => 'Mas eu falei que não sei se a cor estava certa…',
             'feedback' => 'Escuta ativa: ela disse que tem dúvida sobre a cor. Repetir o histórico sem testar ignora a necessidade que ela mesma trouxe.'],
         ]],

        // ── PERSONALIZE ──
        ['etapa' => 1,
         'cena' => 'Bancada de experimentação. Amanda senta na cadeira.',
         'pergunta' => 'Antes de tocar no rosto da Amanda, qual é o primeiro passo?',
         'opcoes' => [
            ['texto' => '<i>(Higienizo as mãos com álcool 70%, esterilizo a espátula e separo pincéis limpos e aplicadores descartáveis, à vista dela.)</i> Antes de testarmos a base, me conta: como é a sua rotina de cuidados com a pele? E qual acabamento você prefere — mais matte e sequinho ou mais luminoso e hidratado?',
             'correta' => true,
             'reacao' => ['Minha pele é de normal a seca, e às vezes descama um pouco no frio. Por isso morro de medo de base que craquela ou marca as linhas.', 'No dia a dia eu só passo protetor solar, mas queria uma cobertura bonita pra quando saio ou tenho eventos.'],
             'feedback' => 'Biossegurança visível + diagnóstico 360°: rotina, tipo de pele e acabamento preferido. Agora a recomendação tem base.'],
            ['texto' => '<i>(Pego a base do expositor e aplico direto no rosto dela com o dedo.)</i> Olha só, essa é ótima.',
             'correta' => false,
             'reacao' => '<i>(Amanda recua um pouco.)</i> Você não vai lavar a mão?',
             'feedback' => 'Biossegurança não é detalhe: álcool 70%, espátula esterilizada, aplicadores descartáveis — sempre à vista da cliente. É o que dá segurança pra ela deixar você tocar no rosto.'],
            ['texto' => '<i>(Higienizo tudo.)</i> Pronto! Essa base aqui é a mais vendida, vou aplicar.',
             'correta' => false,
             'reacao' => 'Tá… mas ela é boa pra pele seca?',
             'feedback' => 'Higiene ok, diagnóstico zero. Personalize começa perguntando rotina, tipo de pele e acabamento preferido. Sem isso, a recomendação é chute.'],
         ]],
        ['etapa' => 1,
         'pergunta' => 'Com o diagnóstico feito, qual base você recomenda — e como?',
         'opcoes' => [
            ['texto' => 'Pensando no que você me contou, a opção mais adequada é a nossa Base Make B., com tecnologia prebiótica e Ácido Hialurônico vetorizado. Ela trata a pele enquanto maquia, dá o efeito radiante e natural que você procura e ainda tem fator de proteção solar.',
             'correta' => true, 'sacola' => [['item' => 'Base Make B.', 'preco' => 99.90]],
             'reacao' => 'Você falando assim, parece que é exatamente o que eu preciso. Gostei da textura também!',
             'feedback' => 'Recomendação amarrada ao diagnóstico: pele seca → ácido hialurônico, medo de craquelar → trata enquanto maquia, eventos → efeito radiante. Benefício, diferencial e segredinho.'],
            ['texto' => 'A Base Make B. é a melhor, todo mundo leva.',
             'correta' => false,
             'reacao' => 'Todo mundo? Mas minha pele é seca…',
             'feedback' => 'Recomende conectando ao que ela contou: pele seca → hidratação, medo de craquelar → ácido hialurônico, eventos → efeito radiante. Benefício e diferencial na linguagem dela, não "todo mundo leva".'],
            ['texto' => 'Temos umas 12 bases. Vou te mostrar todas e você escolhe.',
             'correta' => false,
             'reacao' => 'Nossa, são muitas… não sei.',
             'feedback' => 'Consultoria é escolher por ela, com argumento. Mostrar tudo devolve a decisão pra cliente e joga fora o valor da experimentação.'],
         ]],
        ['etapa' => 1,
         'falas' => 'Nossa, eu nunca passo hidratante antes da base, com medo de ficar pesado, sabia?',
         'pergunta' => 'Como você responde — e conduz o teste da cor?',
         'opcoes' => [
            ['texto' => 'Esse é o maior mito da maquiagem! A hidratação correta é o que garante que a base assente perfeitamente e dure muito mais. Mas antes, vamos testar a cor: <i>(separo o tom que acho ideal, um acima e um abaixo, faço três riscos no maxilar perto do queixo e esfumo com batidinhas até fundir na pele.)</i> Qual das três se aproxima mais do seu tom?',
             'correta' => true, 'bandeira' => 'experimentacao', 'oculto' => ['aplicacao'],
             'reacao' => 'Achei que a do meio ficou exatamente da cor da minha pele!',
             'feedback' => 'Bandeira 2 capturada: ritual de experimentação feito do jeito certo — mito desmontado com segurança técnica e teste de cor no maxilar, com três tons.'],
            ['texto' => 'Realmente pode pesar um pouco. Vamos pular o hidratante, então. <i>(Testo a cor no dorso da mão dela.)</i>',
             'correta' => false,
             'reacao' => 'Hum… na mão ficou meio escura, né?',
             'feedback' => 'Dois erros: validou um mito (hidratação é o que faz a base durar) e testou no lugar errado. O teste de cor é no maxilar, perto do queixo, com três tons — o ideal, um acima, um abaixo — esfumado até fundir.'],
            ['texto' => '<i>(Aplico a base direto, sem testar o tom.)</i> Confia em mim, essa é a sua cor.',
             'correta' => false,
             'reacao' => 'Será? A última que comprei eu também achei que era a minha cor…',
             'feedback' => 'Ela entrou com dúvida sobre a cor. O ritual de experimentação existe pra resolver exatamente isso — e é o que o Cliente Oculto verifica: "ofereceu a aplicação do produto na pele?".'],
         ]],
        ['etapa' => 1,
         'cena' => 'Você aplica o tônico, o creme de ácido hialurônico e a base no tom do meio — Princípio Make Express: primer antes, base como produto principal, corretivo depois na região dos olhos.',
         'falas' => 'Gente, que pele maravilhosa! Parece que eu nem estou de base, mas cobriu tudo e ficou super natural. Não craquelou nada!',
         'pergunta' => 'Amanda comentou antes que tem um jantar à noite. O que você faz com essa informação?',
         'opcoes' => [
            ['texto' => 'Ficou deslumbrante! E olha que fizemos só uma experimentação rápida de 15 minutos. Amanda, você tem um compromisso à noite, certo? Que tal aproveitar que já está aqui e fazer o nosso Serviço Completo de Make? Dura uns 45 minutos, tem 3 opções no cardápio — e eu vou te ensinando os truques passo a passo pra você fazer em casa!',
             'correta' => true,
             'reacao' => 'Nossa, eu adoraria! Mas quanto custa esse serviço completo? Tenho medo de sair do meu orçamento.',
             'feedback' => 'Escuta ativa virou oportunidade: a ocasião (jantar) pede o Serviço Completo. Você ofereceu experiência, não "mais um produto".'],
            ['texto' => 'Ficou linda! Vamos pro caixa fechar a base?',
             'correta' => false,
             'reacao' => 'Vamos… <i>(Amanda olha o relógio, pensando no jantar.)</i>',
             'feedback' => 'Ela falou do jantar — a pista estava na conversa. Personalize é onde a gente evolui o atendimento pro Serviço Completo quando a ocasião pede.'],
            ['texto' => 'Ficou linda! Aproveita e leva o tônico e o creme também, foram eles que fizeram a diferença.',
             'correta' => false,
             'reacao' => 'Ah, mas eu vim só pra base…',
             'feedback' => 'Empurrar produto antes de mostrar valor gera resistência. A ocasião pede o Serviço Completo: experiência primeiro, venda depois.'],
         ]],
        ['etapa' => 1,
         'pergunta' => 'Amanda tem medo do orçamento. Como você responde?',
         'opcoes' => [
            ['texto' => 'O valor do serviço é R$ 139,90, mas ele é 100% convertido em produtos da loja! Como você já vai levar a base, que custa R$ 99,90, você completa o valor escolhendo mais itens que usamos aqui — pra ter esse mesmo resultado em casa. O que acha?',
             'correta' => true, 'sacola' => [['item' => 'Serviço Completo de Make (valor convertido em produtos)', 'preco' => 0, 'brinde' => true]],
             'reacao' => ['Sério? Essa conversão é sensacional!', 'Eu tenho tempo sim, e hoje à noite tenho um jantar especial. Quero fazer!'],
             'feedback' => 'Objeção de dinheiro respondida com dinheiro: o valor não é gasto, vira produto. E ela já ia levar a base.'],
            ['texto' => 'É R$ 139,90. Mas se está apertado, deixa pra próxima, sem problema!',
             'correta' => false,
             'reacao' => 'Ah, então deixa… fico só com a base mesmo.',
             'feedback' => 'Desistiu na primeira objeção. O argumento que resolve o medo do orçamento já existe: o valor do serviço é 100% convertido em produtos. Ela não gasta a mais — ela escolhe o que leva.'],
            ['texto' => 'Fica R$ 139,90 e vale muito a pena, você vai amar. Fechado?',
             'correta' => false,
             'reacao' => 'Hum… vou pensar, tá?',
             'feedback' => '"Vale a pena" não responde "tenho medo do orçamento". A objeção era de dinheiro; a resposta precisa ser de dinheiro: conversão total em produtos, e ela já ia levar a base.'],
         ]],
        ['etapa' => 1,
         'pergunta' => 'Amanda topou o Serviço Completo. Antes de começar a make, o que não pode faltar?',
         'opcoes' => [
            ['texto' => 'Perfeito, Amanda! Vou registrar o seu serviço na nossa plataforma pra fazer o check-in oficial, vinculado ao seu CPF. Prontinho, agendamento confirmado e iniciado! <i>(Organizo os produtos na bancada na ordem de aplicação.)</i>',
             'correta' => true,
             'reacao' => 'Que organização! Assim eu já vou entendendo a ordem dos produtos.',
             'feedback' => 'Check-in na plataforma (o serviço existe no sistema, vinculado ao CPF) e bancada na ordem de uso — a cliente aprende a sequência olhando.'],
            ['texto' => 'Vamos começar! <i>(Pego os produtos conforme vou lembrando.)</i>',
             'correta' => false,
             'reacao' => '<i>(Amanda tenta acompanhar, mas se perde na ordem dos produtos.)</i>',
             'feedback' => 'Sem check-in na plataforma, o serviço não existe no sistema: não vincula ao CPF, não conta pra loja, não gera histórico. E a bancada organizada na ordem de uso é o que faz a cliente aprender — e querer levar — a sequência.'],
            ['texto' => '<i>(Faço o check-in.)</i> Agora relaxa e deixa comigo — no final você vê o resultado.',
             'correta' => false,
             'reacao' => 'Tá bom… <i>(Amanda fecha os olhos e perde o passo a passo.)</i>',
             'feedback' => 'A promessa foi "vou te ensinando truques passo a passo pra fazer em casa". Explicar cada produto durante o serviço é o que transforma make em venda: ela só leva o que entendeu.'],
         ]],

        // ── POTENCIALIZE ──
        ['etapa' => 2,
         'cena' => '45 minutos depois. A cada produto você explicou ativos, benefícios e características, validando cada etapa. Amanda se olha no espelho, encantada, e vocês tiram a foto.',
         'falas' => ['Estou encantada com o passo a passo. Meu Deus, {nome}, que maquiagem perfeita!', 'Muito obrigada por essa experiência maravilhosa. Aprendi demais!'],
         'pergunta' => 'Hora de potencializar. O que você faz?',
         'opcoes' => [
            ['texto' => 'Você merece brilhar! Organizei aqui na bancada tudo que usamos, incluindo o que você mais amou: a Base Make B., a Palette de Sombras e o Batom Hidratante. Ficou alguma dúvida da sequência de uso? A base já está garantida — vou consultar seus benefícios no ID Cliente pra ver as ofertas personalizadas e as melhores condições pra você.',
             'correta' => true, 'sacola' => [['item' => 'Palette de Sombras', 'preco' => 79.90], ['item' => 'Batom Hidratante', 'preco' => 39.90]],
             'reacao' => 'Se tiver pagamento facilitado, eu quero levar toda a parte de preparação de pele também!',
             'feedback' => 'A bancada organizada virou vitrine, a dúvida de uso foi checada e o ID Cliente entrou antes da proposta. Potencializar é oferecer com base no que ela viveu.'],
            ['texto' => 'Que bom que gostou! Então é só a base mesmo? Vou passar no caixa.',
             'correta' => false,
             'reacao' => 'É… acho que sim.',
             'feedback' => 'Potencialize é oferecer, com base no histórico e na conversa, os itens que complementam. Ela acabou de usar dez produtos e amou; a bancada organizada é a vitrine. O Cliente Oculto pergunta: ofereceu algum produto ou promoção adicional?'],
            ['texto' => 'Aproveita e leva tudo que a gente usou! São 9 produtos, dá uns R$ 470.',
             'correta' => false,
             'reacao' => 'Nossa, R$ 470?! Calma…',
             'feedback' => 'Sem consultar o ID Cliente antes, a oferta vem sem condição e sem personalização. Primeiro os benefícios e promoções do perfil dela; a proposta chega com o valor já facilitado.'],
         ]],
        ['etapa' => 2,
         'cena' => 'No ID Cliente: uma condição exclusiva pro perfil dela e a ação "compre e ganhe" do ciclo.',
         'pergunta' => 'Como você apresenta os benefícios?',
         'opcoes' => [
            ['texto' => 'Encontrei uma condição exclusiva pro seu perfil, e ela combina exatamente com os produtos que você mais gostou. Além disso, neste ciclo temos uma ação de compre e ganhe: a partir de R$ 300, você leva de graça o Primer Facial Antioleosidade. Assim você leva outro item do look e ainda garante o primer — e dá pra parcelar em até 10x sem juros.',
             'correta' => true, 'bandeira' => 'beautybox', 'oculto' => ['adicional'],
             'sacola' => [['item' => 'Tônico Facial Botik', 'preco' => 59.90], ['item' => 'Creme Ácido Hialurônico Botik', 'preco' => 89.90], ['item' => 'Corretivo', 'preco' => 49.90], ['item' => 'Perfume (reposição)', 'preco' => 149.90], ['item' => 'Primer Facial Antioleosidade', 'preco' => 0, 'brinde' => true]],
             'reacao' => ['Então vou levar o corretivo e o hidratante facial também!', 'E aproveitar pra repor meu perfume, que está quase acabando.'],
             'feedback' => 'Bandeira 3 capturada: condição exclusiva do perfil, compre e ganhe e parcelamento — explicados com clareza. É isso que traz a cliente de volta.'],
            ['texto' => 'Tem uma promoção aí, mas é complicada de explicar. Quer que eu aplique o desconto e pronto?',
             'correta' => false,
             'reacao' => 'Pode ser… <i>(Amanda não entendeu o que ganhou.)</i>',
             'feedback' => 'Benefício não explicado não fideliza. Beautybox é a bandeira 3: pontos, cashback, desafios e condições exclusivas precisam ser ditos com clareza — é o que faz a cliente voltar.'],
            ['texto' => 'Você tem 10x sem juros. Fecha tudo?',
             'correta' => false,
             'reacao' => 'Hum… e a promoção que apareceu na sua tela?',
             'feedback' => 'Parcelamento é só uma alavanca. Faltou a condição exclusiva do perfil e o compre e ganhe (primer grátis a partir de R$ 300) — é o que puxa o item extra do look.'],
         ]],

        // ── ENCANTE ──
        ['etapa' => 3,
         'cena' => 'Caixa. A sacola está pronta com a curadoria que Amanda escolheu.',
         'pergunta' => 'Como você entrega a sacola?',
         'opcoes' => [
            ['texto' => 'Antes de ir, quero deixar a sua experiência ainda mais especial: preparei a sua sacola com muito carinho, borrifei uma fragrância pra você levar o cheirinho da nossa loja e fiz questão de gravar essa fita personalizada com o seu nome, Amanda!',
             'correta' => true, 'oculto' => ['fragrancia'],
             'reacao' => 'Nossa, que mimo mais lindo! Amei o detalhe da fita com o meu nome — que capricho!',
             'feedback' => 'Entregar e celebrar: fragrância na sacola (pergunta do Cliente Oculto), embalagem cuidada e um detalhe com o nome dela.'],
            ['texto' => 'Prontinho, aqui está a sua sacola. Obrigada!',
             'correta' => false,
             'reacao' => 'Obrigada! <i>(Amanda sai como sairia de qualquer loja.)</i>',
             'feedback' => 'Encante é entregar e celebrar: fragrância borrifada na sacola, embalagem cuidada, um detalhe com o nome. O Cliente Oculto pergunta: borrifou alguma fragrância na sua sacola?'],
            ['texto' => '<i>(Entrego a sacola.)</i> Ah, e você tem que baixar o app, tá? Tem promoção lá.',
             'correta' => false,
             'reacao' => 'Tá… depois eu vejo.',
             'feedback' => 'O app entra como benefício, não como tarefa. E o mimo de fechamento (fragrância, fita com o nome) ficou de fora.'],
         ]],
        ['etapa' => 3,
         'pergunta' => 'Última fala. Como você se despede?',
         'opcoes' => [
            ['texto' => 'Você é muito especial pra nós, Amanda! Nos próximos dias você vai receber uma pesquisa rápida de satisfação — se puder avaliar o meu atendimento, ficamos muito gratos. E quando as embalagens acabarem, traz três de volta pro Boti Recicla: dá R$ 15 de desconto a partir de R$ 150. Tenha um jantar incrível e volte pra resgatar os pontos dessa compra!',
             'correta' => true, 'oculto' => ['nps', 'recicla'],
             'reacao' => ['Pode deixar! Vou responder sim — vocês merecem nota 10 com louvor!', 'Até breve, {nome}! 💚'],
             'feedback' => 'NPS anunciado, Boti Recicla explicado, pontos lembrados: as três frases que fecham o ciclo e trazem a cliente de volta.'],
            ['texto' => 'Obrigada, Amanda! Bom jantar!',
             'correta' => false,
             'reacao' => 'Obrigada, tchau!',
             'feedback' => 'Simpático, mas incompleto: sem NPS anunciado, sem Boti Recicla, sem lembrar dos pontos gerados. São as três frases que fecham o ciclo e trazem a cliente de volta.'],
            ['texto' => 'Você vai receber uma pesquisa — responde nota 10 pra mim, tá? Conto com você!',
             'correta' => false,
             'reacao' => 'Ha, tá bom… <i>(meio sem graça)</i>',
             'feedback' => 'Pedir a nota constrange e distorce o NPS. Anuncie a pesquisa e peça a avaliação sincera do atendimento: a nota vem do encantamento, não do pedido.'],
         ]],
    ];
    return $c;
}

// ── Atendimento 2 · Bia (persona "explorando" → objeção → sem venda) ──
function conteudo_sim_atendimento_bia(): array {
    $c = conteudo_sim_atendimento_comum();
    $c['cliente'] = [
        'nome'      => 'Bia',
        'avatar'    => '👩🏻‍🦱',
        'persona'   => 'Uso próprio · explorando',
        'descricao' => 'Entrou "só dando uma olhada", sem alvo. É a maior parte do fluxo real da loja — e o roteiro linear não cobre.',
    ];
    $c['briefing'] = [
        'titulo' => 'Nem todo atendimento termina em venda',
        'texto'  => 'Sábado, loja cheia. Uma cliente entra e passeia pelas prateleiras de make sem parar em nada. Aqui a jornada é a mesma — Acolha, Personalize, Potencialize, Encante — mas o objetivo muda: entender, não empurrar. Sair sem venda pode ser um resultado válido, desde que o atendimento termine do jeito certo.',
        'botao'  => 'Receber a Bia',
    ];
    $c['checklist'] = conteudo_sim_atendimento_checklist(['apresentou', 'nome', 'motivo', 'cpf', 'cpf_motivo', 'experimentar', 'aplicacao', 'motivo_nao_compra', 'mimo', 'nps']);
    $c['final'] = [
        'titulo'            => 'Atendimento concluído — sem venda hoje',
        'texto'             => 'Bia não comprou, e mesmo assim o atendimento entregou resultado: motivo registrado, lembrete no Beautybox, amostra na mão e uma cliente que sabe onde voltar. É a diferença entre "só olhando" e cliente que retorna.',
        'sem_venda'         => true,
        'motivo_nao_compra' => 'preço (gostou do produto, vai esperar uma condição especial)',
        'botao'             => 'Concluir atendimento',
    ];
    $c['turnos'] = [
        // ── ACOLHA ──
        ['etapa' => 0,
         'cena' => 'Sábado, loja cheia. Uma cliente entra e olha as prateleiras de make sem parar em nada.',
         'pergunta' => 'Como você a recebe?',
         'opcoes' => [
            ['texto' => 'Oi! Seja bem-vinda 💕 Meu nome é {nome}, e o seu? Fica super à vontade pra dar uma olhada — e se quiser, me conta o que te trouxe aqui hoje, mesmo que seja só curiosidade, que eu te ajudo a encontrar.',
             'correta' => true, 'oculto' => ['apresentou', 'nome', 'motivo'],
             'reacao' => ['Oi! Sou a Bia.', 'Ah, na verdade eu só tava vendo mesmo, sem muito objetivo.'],
             'feedback' => 'Acolheu sem pressionar: se apresentou, perguntou o nome e abriu uma pergunta leve. A persona "explorando" precisa exatamente disso.'],
            ['texto' => 'Posso ajudar? Está procurando o quê?',
             'correta' => false,
             'reacao' => 'Só olhando, obrigada. <i>(Bia se afasta.)</i>',
             'feedback' => '"Posso ajudar?" quase sempre recebe "só olhando". Acolha sem pressionar: se apresente, pergunte o nome e abra uma pergunta leve. Quem está explorando é a maior parte do fluxo real da loja.'],
            ['texto' => 'Olá! Hoje temos promoção nas bases, quer ver?',
             'correta' => false,
             'reacao' => 'Não, obrigada, tô só olhando.',
             'feedback' => 'Oferta antes da conexão. Quem está explorando não tem alvo; empurrar produto encerra a conversa. Primeiro acolhimento e curiosidade, depois o filtro.'],
         ]],
        ['etapa' => 0,
         'pergunta' => 'Bia está "só olhando". Como você segue?',
         'opcoes' => [
            ['texto' => 'Sem problema nenhum! Posso te mostrar o que está bombando essa semana, ou, se preferir, me conta um pouco do seu estilo — mais natural, mais marcante… — que eu já filtro umas sugestões pra você.',
             'correta' => true,
             'reacao' => ['Ah, eu sou bem natural, quase não uso make.', 'Mas ando curiosa com esses iluminadores que vejo nos vídeos!'],
             'feedback' => 'Curiosidade virou gancho: em vez de largar a cliente, você deu duas portas fáceis ("o que está bombando" ou o estilo dela). A necessidade latente apareceu sozinha.'],
            ['texto' => 'Tá bom! Qualquer coisa me chama.',
             'correta' => false,
             'reacao' => '<i>(Bia dá mais uma volta e vai em direção à porta.)</i>',
             'feedback' => 'Largar a cliente é perder a persona mais comum da loja. Use curiosidade como gancho: "o que está bombando" ou uma pergunta leve sobre o estilo dela.'],
            ['texto' => 'Você já é cadastrada no Beautybox? Me passa o CPF que eu vejo seus benefícios.',
             'correta' => false,
             'reacao' => 'Hum… eu nem sei se vou comprar alguma coisa.',
             'feedback' => 'A identificação é bandeira obrigatória, mas o momento importa: ela ainda não disse o que despertou interesse. Primeiro a pergunta leve; o CPF vem como benefício, um pouco depois.'],
         ]],

        // ── PERSONALIZE ──
        ['etapa' => 1,
         'pergunta' => 'Iluminador, estilo natural. E agora?',
         'opcoes' => [
            ['texto' => 'Adoro! Iluminador em pele natural fica lindo quando é sutil. Quer experimentar? Passo um pouquinho aqui no dorso da mão e no topo da maçã do rosto pra você ver o efeito na luz. E deixa eu ver se você já tem algum mimo no nosso Programa de Fidelidade — é só me passar o seu CPF.',
             'correta' => true, 'bandeira' => ['experimentacao', 'identificacao'], 'oculto' => ['cpf', 'cpf_motivo', 'experimentar', 'aplicacao'],
             'reacao' => ['Pode ser! Meu CPF é 987.654.321-00.', '<i>(Bia se surpreende com o brilho na pele.)</i> Nossa, ficou bonito — e nem exagerado!'],
             'feedback' => 'Duas bandeiras numa fala: experimentação como ponte pro benefício, e o CPF oferecido como mimo, não como cadastro.'],
            ['texto' => 'Esse iluminador aqui é o que mais sai. R$ 79,90.',
             'correta' => false,
             'reacao' => 'Ah, tá. <i>(Bia olha o preço e devolve na prateleira.)</i>',
             'feedback' => 'Preço antes da experiência. A experimentação é a ponte pra argumentar benefício e gerar desejo; sem ela, iluminador é só um número na etiqueta.'],
            ['texto' => 'Iluminador de vídeo é muito forte. Você, que é natural, não vai gostar.',
             'correta' => false,
             'reacao' => 'Ah… tá.',
             'feedback' => 'Decidiu por ela, contra a curiosidade que ela mesma trouxe. Personalize é mostrar como o produto cabe no estilo dela (sutil, natural), não vetar.'],
         ]],

        // ── POTENCIALIZE ──
        ['etapa' => 2,
         'falas' => 'Nossa, gostei muito! Mas acho que tá acima do que eu queria gastar hoje.',
         'pergunta' => 'Objeção de preço. Como você responde?',
         'opcoes' => [
            ['texto' => 'Entendo super! Faz sentido pensar no orçamento. Posso te mostrar uma opção de entrada com o mesmo benefício principal — ou prefere que eu guarde esse aqui pra você, com um lembrete de quando tivermos uma condição especial?',
             'correta' => true,
             'reacao' => 'Acho que prefiro esperar uma promoção.',
             'feedback' => 'Sem insistir, com alternativa de menor fricção: opção de entrada ou guardar com lembrete. É o que a persona "objeção" pede.'],
            ['texto' => 'Mas é só R$ 79,90! Dá pra parcelar em 3x.',
             'correta' => false,
             'reacao' => 'Não, hoje não… valeu.',
             'feedback' => 'Insistir na objeção de preço gera fuga. A persona "objeção" pede alternativa de menor fricção: opção de entrada, amostra, ou guardar com lembrete de condição futura.'],
            ['texto' => 'Ah, tudo bem então! Qualquer coisa a gente tá aí.',
             'correta' => false,
             'reacao' => 'Valeu! <i>(Bia sai.)</i>',
             'feedback' => 'Fechou a porta rápido demais: nem alternativa nem registro. Sem intenção de compra, o atendimento muda de "vender" pra "entender o motivo" e deixar uma porta aberta.'],
         ]],
        ['etapa' => 2,
         'pergunta' => 'Bia vai esperar uma promoção. O que você faz antes de encerrar?',
         'opcoes' => [
            ['texto' => 'Combinado, vou anotar aqui pra te avisar. Só pra eu entender melhor: foi mais o valor mesmo, ou ficou em dúvida se era o produto certo pra você?',
             'correta' => true, 'oculto' => ['motivo_nao_compra'],
             'reacao' => 'Foi o valor mesmo. O produto eu gostei de verdade!',
             'feedback' => 'Motivo da não compra capturado de forma leve. Esse dado alimenta o ID Cliente e as missões da Loja Digital — a próxima oferta chega certa.'],
            ['texto' => 'Beleza! <i>(Encerro o atendimento.)</i>',
             'correta' => false,
             'reacao' => '<i>(Bia sai sem deixar nada registrado.)</i>',
             'feedback' => 'O motivo da não compra é dado de CRM: alimenta as missões da Loja Digital e a promoção certa depois. Sem ele, a loja não sabe por que não vendeu.'],
            ['texto' => 'Que pena, você ia amar! Tem certeza?',
             'correta' => false,
             'reacao' => 'Tenho… <i>(Bia fica constrangida.)</i>',
             'feedback' => 'Pressão no final destrói o NPS e não recupera a venda. Capture o motivo de forma leve; o dado vale mais que a insistência.'],
         ]],

        // ── ENCANTE ──
        ['etapa' => 3,
         'pergunta' => 'Encerrar bem, mesmo sem venda. Como?',
         'opcoes' => [
            ['texto' => 'Registrei aqui no seu cadastro: quando esse iluminador entrar em condição especial, você recebe o aviso pelo app do Beautybox. Leva essa amostrinha pra testar em casa — e volta pra me contar! Ah, você ainda vai receber uma pesquisa rápida de satisfação; se puder avaliar, ajuda muito.',
             'correta' => true, 'bandeira' => 'beautybox', 'oculto' => ['mimo', 'nps'],
             'reacao' => ['Ai, que fofa! Vou testar sim.', 'Obrigada, {nome}! 💕'],
             'feedback' => 'Bandeira 3 mesmo sem venda: Beautybox como canal do lembrete, mimo de saída, convite de retorno e NPS. Fechou bem.'],
            ['texto' => 'Tá bom, tchau!',
             'correta' => false,
             'reacao' => 'Tchau.',
             'feedback' => 'Encante vale sem compra: mimo pequeno de saída (amostra ou cupom), convite de retorno e a pesquisa. É o que transforma "só olhando" em cliente que volta.'],
            ['texto' => 'Se você levar hoje eu consigo 5% de desconto — última chance!',
             'correta' => false,
             'reacao' => 'Não… obrigada. <i>(Bia sai apressada.)</i>',
             'feedback' => 'Desconto de última hora depois de "vou esperar" soa como pressão. A regra: fechar bem mesmo sem venda, capturar o motivo e deixar a porta aberta.'],
         ]],
    ];
    return $c;
}

// Fase + etapas, prontas pra inserir no treinamento "Da Base ao Look Completo".
function conteudo_sim_atendimento(): array {
    $fase = ['nome' => CONTEUDO_SIM_ATENDIMENTO_FASE, 'pin_x' => 40, 'pin_y' => 80, 'nota_minima' => 0];
    $etapas = [
        ['Atendimento 1: Amanda — da base ao Serviço Completo', 'minigame', 'atendimento', conteudo_sim_atendimento_amanda()],
        ['Atendimento 2: Bia — "só dando uma olhada"',           'minigame', 'atendimento', conteudo_sim_atendimento_bia()],
    ];
    return [$fase, $etapas];
}

// Insere a fase no treinamento inaugural do cliente. Idempotente: se a fase
// já existir (mesmo nome, mesmo treinamento), não repete. Devolve o id da
// fase ou null se o treinamento não existir (tenant sem conteúdo).
function conteudo_sim_atendimento_inserir(PDO $db, int $clienteId): ?int {
    $st = $db->prepare("SELECT t.id FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id
                        WHERE tr.cliente_id = ? AND tr.nome = 'Universo da Maquiagem' ORDER BY t.ordem, t.id LIMIT 1");
    $st->execute([$clienteId]);
    $tid = (int) $st->fetchColumn();
    if (!$tid) return null;

    [$fase, $etapas] = conteudo_sim_atendimento();
    $st = $db->prepare('SELECT id FROM fase WHERE treinamento_id = ? AND nome = ?');
    $st->execute([$tid, $fase['nome']]);
    $fid = (int) $st->fetchColumn();
    if ($fid) return $fid;

    $j = fn($a) => json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $emTransacao = !$db->inTransaction();
    if ($emTransacao) $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT COALESCE(MAX(ordem), 0) FROM fase WHERE treinamento_id = ?');
        $st->execute([$tid]);
        $ordem = (int) $st->fetchColumn() + 1;
        $db->prepare('INSERT INTO fase (treinamento_id, nome, ordem, pin_x, pin_y, desbloqueio, nota_minima) VALUES (?,?,?,?,?,\'sequencia\',?)')
           ->execute([$tid, $fase['nome'], $ordem, $fase['pin_x'], $fase['pin_y'], $fase['nota_minima']]);
        $fid = (int) $db->lastInsertId();
        $stE = $db->prepare('INSERT INTO etapa (fase_id, ordem, tipo, minigame_tipo, obrigatoria, titulo, config) VALUES (?,?,?,?,1,?,?)');
        foreach ($etapas as $ei => [$titulo, $tipo, $mg, $cfg]) $stE->execute([$fid, $ei + 1, $tipo, $mg, $titulo, $j($cfg)]);
        if ($emTransacao) $db->commit();
    } catch (Throwable $e) {
        if ($emTransacao && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return $fid;
}
