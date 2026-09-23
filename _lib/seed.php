<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS (V3) · Schema + seed base + conteúdo real do cliente.
// Roda uma única vez, quando boticario/data/lms.sqlite não existe.
//
// Whitelabel do engine Skope LMS (/atlantica). Mesma hierarquia:
//   Trilha → Treinamento → Fase → Etapa.
//   · Trilha    = pasta-mãe, dona do MAPA/cenário e da narrativa.
//   · Treinamento = pasta dentro da trilha; só agrupa e dá nome.
//   · Fase      = o JOGÁVEL: etapas (leituras/minigames), PIN livre no
//                 mapa e desbloqueio configurável: sequência | data | xp.
//   · Cada aluno tem 1 treinamento ativo (usuario.treinamento_id).
//
// O que este seed cria:
//   1. o tenant (linha em `cliente`) com a identidade visual;
//   2. o catálogo de badges — gamificação do engine, independe do conteúdo;
//   3. o conteúdo real: trilha "Universo da Maquiagem" com o treinamento
//      "Da Base ao Look Completo" (_lib/conteudo/servico-make.php);
//   4. UM usuário admin, já apontando pro treinamento (Visão do aluno).
// Gestores e alunos entram pelo admin.
// ──────────────────────────────────────────────────────────────

function lms_seed(PDO $db): void {
    $db->exec(<<<'SQL'
    CREATE TABLE cliente (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        nome TEXT NOT NULL,
        logo_url TEXT,
        cor_primaria TEXT,
        cor_secundaria TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE trilha (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER NOT NULL REFERENCES cliente(id),
        nome TEXT NOT NULL,
        narrativa TEXT,
        cenario_mapa_url TEXT,
        ordem INTEGER DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE treinamento (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        trilha_id INTEGER NOT NULL REFERENCES trilha(id),
        nome TEXT NOT NULL,
        descricao TEXT,
        ordem INTEGER NOT NULL DEFAULT 1,
        status TEXT DEFAULT 'ativo' CHECK (status IN ('ativo','arquivado'))
    );
    CREATE INDEX idx_trein_trilha ON treinamento(trilha_id);
    CREATE TABLE fase (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        treinamento_id INTEGER NOT NULL REFERENCES treinamento(id),
        nome TEXT NOT NULL,
        ordem INTEGER NOT NULL DEFAULT 1,
        pin_x REAL NOT NULL DEFAULT 50,
        pin_y REAL NOT NULL DEFAULT 50,
        desbloqueio TEXT DEFAULT 'sequencia' CHECK (desbloqueio IN ('sequencia','data','xp')),
        desbloqueio_valor TEXT,
        nota_minima INTEGER DEFAULT 80
    );
    CREATE INDEX idx_fase_trein ON fase(treinamento_id);
    CREATE TABLE etapa (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fase_id INTEGER NOT NULL REFERENCES fase(id),
        ordem INTEGER NOT NULL,
        tipo TEXT NOT NULL CHECK (tipo IN ('leitura','minigame')),
        minigame_tipo TEXT,
        obrigatoria INTEGER DEFAULT 1,
        titulo TEXT,
        config TEXT NOT NULL
    );
    CREATE INDEX idx_etapa_fase ON etapa(fase_id);
    CREATE TABLE usuario (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER NOT NULL REFERENCES cliente(id),
        nome TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        senha_hash TEXT NOT NULL,
        papel TEXT NOT NULL DEFAULT 'aluno' CHECK (papel IN ('aluno','gestor','admin')),
        avatar_genero TEXT,
        avatar_cor_pele TEXT,
        foto_url TEXT,
        lembrete_em TEXT,
        treinamento_id INTEGER REFERENCES treinamento(id),
        ultimo_login TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        acessos INTEGER NOT NULL DEFAULT 0,
        gestor_id INTEGER REFERENCES usuario(id),
        aceite_em TEXT,
        trilhas_vistas TEXT,
        treinamento_id_2 INTEGER REFERENCES treinamento(id),
        avatar_v2 TEXT
    );
    CREATE INDEX idx_usuario_cliente ON usuario(cliente_id);
    CREATE TABLE comentario (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER NOT NULL REFERENCES cliente(id),
        fase_id INTEGER NOT NULL REFERENCES fase(id),
        usuario_id INTEGER NOT NULL REFERENCES usuario(id),
        parent_id INTEGER REFERENCES comentario(id),
        texto TEXT NOT NULL,
        oficial INTEGER DEFAULT 0,
        status TEXT DEFAULT 'pendente' CHECK (status IN ('pendente','ativo','oculto')),
        criado_em TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX idx_coment_fase ON comentario(fase_id);
    CREATE TABLE badge (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER NOT NULL REFERENCES cliente(id),
        nome TEXT NOT NULL,
        descricao TEXT,
        icone TEXT,
        criterio TEXT NOT NULL CHECK (criterio IN ('conclusao','tempo','acerto','estendida')),
        config TEXT
    );
    CREATE TABLE progresso_usuario (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL REFERENCES usuario(id),
        etapa_id INTEGER NOT NULL REFERENCES etapa(id),
        status TEXT DEFAULT 'nao_iniciado' CHECK (status IN ('nao_iniciado','em_andamento','concluido')),
        acertos INTEGER DEFAULT 0,
        total INTEGER DEFAULT 0,
        tempo_seg INTEGER DEFAULT 0,
        concluido_em TEXT,
        resposta_json TEXT,
        UNIQUE (usuario_id, etapa_id)
    );
    CREATE INDEX idx_prog_usuario ON progresso_usuario(usuario_id);
    CREATE TABLE conquista_usuario (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL REFERENCES usuario(id),
        badge_id INTEGER NOT NULL REFERENCES badge(id),
        ganho_em TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (usuario_id, badge_id)
    );
SQL);

    $j = fn($a) => json_encode($a, JSON_UNESCAPED_UNICODE);

    // ── Tenant ──────────────────────────────────────────────
    // Cores do site do O Boticário (verde #007A54 + quase-preto) — ver CONTEUDO.md:
    // cor_primaria vira --lms-primary em toda tela (ver lms_head).
    $db->prepare('INSERT INTO cliente (slug, nome, logo_url, cor_primaria, cor_secundaria) VALUES (?,?,?,?,?)')
       ->execute(['boticario', 'Grupo Boticário', '/admin/logos/boticario.svg', '#007A54', '#1F2A25']);
    $cli = (int) $db->lastInsertId();

    // ── Badges (gamificação do engine — independem do conteúdo) ──
    // Nomes herdados do engine; ajustar junto com a narrativa do cliente.
    $stB = $db->prepare('INSERT INTO badge (cliente_id, nome, descricao, icone, criterio, config) VALUES (?,?,?,?,?,?)');
    $stB->execute([$cli, 'Primeiro Passo', 'Concluiu a primeira fase', '🥾', 'conclusao', $j(['gatilho' => 'primeira_fase'])]);
    $stB->execute([$cli, 'Na Mosca', '90% de acerto ou mais em uma fase', '🎯', 'acerto', $j(['min_pct' => 90])]);
    $stB->execute([$cli, 'Relâmpago', 'Concluiu uma fase em menos de 4 minutos', '⚡', 'tempo', $j(['max_seg' => 240])]);
    $stB->execute([$cli, 'Missão Cumprida', 'Concluiu um treinamento completo', '🏅', 'conclusao', $j(['gatilho' => 'treinamento'])]);
    $stB->execute([$cli, 'Trilha Completa', 'Concluiu todos os treinamentos de uma trilha', '🏆', 'conclusao', $j(['gatilho' => 'trilha'])]);
    $stB->execute([$cli, 'Voz da Equipe', 'Participou da discussão de uma fase', '🗣️', 'estendida', $j(['gatilho' => 'comentario'])]);

    // ── Conteúdo real (Serviço Completo de Make) ────────────
    require_once __DIR__ . '/conteudo/servico-make.php';
    $trein = conteudo_servico_make_inserir($db, $cli);
    require_once __DIR__ . '/conteudo/simulacao-atendimento.php';
    conteudo_sim_atendimento_inserir($db, $cli);   // fase 6: simulação de atendimento em chat

    // ── Acesso inicial ──────────────────────────────────────
    // Um único admin, já no treinamento (pra Visão do aluno). Senha
    // provisória "1234" — trocar em /boticario/senha no primeiro acesso.
    // Gestores e alunos são criados pelo admin em /boticario/admin.
    $db->prepare('INSERT INTO usuario (cliente_id, nome, email, senha_hash, papel, avatar_genero, avatar_cor_pele, treinamento_id) VALUES (?,?,?,?,?,?,?,?)')
       ->execute([$cli, 'Admin Boticário', 'admin@boticario.com.br', password_hash('1234', PASSWORD_DEFAULT), 'admin', 'm', '3', $trein]);
}
