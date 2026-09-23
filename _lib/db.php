<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Conexão e bootstrap do banco (SQLite via PDO).
// Primeiro acesso cria o schema e roda o seed (_lib/seed.php).
// O arquivo do banco fica em boticario/data/ (negado ao público
// via .htaccess). Banco próprio — independente do /atlantica e do /yeah.
//
// WHITELABEL do engine Skope LMS (nascido em /atlantica, 2026-09-13).
// O schema já nasce na versão 31 — o histórico de migrações do
// Atlântica (conteúdo SSMS, vitrine de minigames, correções de FK) era
// específico daquele tenant e NÃO veio junto. Migrações novas deste
// tenant entram em lms_migrar() a partir da v32.
// ──────────────────────────────────────────────────────────────

// Grava fatais não tratados em boticario/data/erros.log (mesma pasta do
// banco, já bloqueada ao público pelo .htaccess) — dá visibilidade em
// produção sem depender de acesso ao painel da hospedagem. Passivo: só
// observa e grava, nunca muda o que a página exibe ou como se comporta.
function lms_registrar_erro(string $msg): void {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $uri = $_SERVER['REQUEST_URI'] ?? '(cli)';
    @file_put_contents($dir . '/erros.log', '[' . date('Y-m-d H:i:s') . "] {$uri}\n{$msg}\n\n", FILE_APPEND | LOCK_EX);
}
if (!defined('LMS_SHUTDOWN_REGISTRADO')) {
    define('LMS_SHUTDOWN_REGISTRADO', true);
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            lms_registrar_erro($err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        }
    });
}

function lms_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");

    $file = $dir . '/lms.sqlite';

    $fresh = !is_file($file);

    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    // requisições concorrentes (ex.: logo após um deploy) esperam até 5s
    // pelo lock em vez de estourar "database is locked" na hora.
    $pdo->exec('PRAGMA busy_timeout = 5000');

    if ($fresh) {
        require __DIR__ . '/seed.php';
        lms_seed($pdo);
        $pdo->exec('PRAGMA user_version = ' . LMS_SCHEMA_VERSAO);
    } else {
        lms_migrar($pdo);
    }
    return $pdo;
}

// Versão atual do schema. Bancos já existentes são levados até ela por
// lms_migrar() — assim a estrutura evolui sem perder dados de produção
// (o seed só roda em banco novo). Parte da 31 pra ficar alinhada ao
// engine de origem (/atlantica): uma correção de schema feita lá pode
// ser replicada aqui com o mesmo número.
const LMS_SCHEMA_VERSAO = 35;

function lms_migrar(PDO $db): void {
    $v = (int) $db->query('PRAGMA user_version')->fetchColumn();
    if ($v >= LMS_SCHEMA_VERSAO) return;

    // Cada bloco é idempotente e aditivo, sem perder dado. Antes de
    // renomear/recriar tabela no SQLite, ler o MEMORY.md da raiz: as FKs
    // do Atlântica quebraram exatamente nesse movimento.

    if ($v < 32) {
        // v32 · Conteúdo real: trilha "Universo da Maquiagem" com o
        // treinamento "Da Base ao Look Completo" (as 11 etapas do HTML do
        // cliente). Entra só se a trilha não existir. Quem está sem
        // treinamento ativo (o admin do seed anterior) passa a apontar pra
        // ele — a "Visão do aluno" passa a mostrar o conteúdo.
        require_once __DIR__ . '/conteudo/servico-make.php';
        $cli = (int) $db->query("SELECT id FROM cliente WHERE slug = 'boticario'")->fetchColumn();
        if ($cli) {
            $tid = conteudo_servico_make_inserir($db, $cli);
            $db->exec("UPDATE usuario SET treinamento_id = {$tid} WHERE cliente_id = {$cli} AND treinamento_id IS NULL");
            // paleta do treinamento do cliente substitui o verde provisório
            $db->exec("UPDATE cliente SET cor_primaria = '#1CAE8D', cor_secundaria = '#2A4971' WHERE id = {$cli} AND cor_primaria = '#00A859'");
        }
    }

    if ($v < 33) {
        // v33 · Design system do O Boticário (site oficial): verde #007A54 como
        // primária e quase-preto nos títulos substituem a paleta do treinamento.
        $db->exec("UPDATE cliente SET cor_primaria = '#007A54', cor_secundaria = '#1F2A25' WHERE slug = 'boticario' AND cor_primaria IN ('#00A859', '#1CAE8D')");
    }

    if ($v < 34) {
        // v34 · Arte final do mapa da loja (gerada a partir do briefing): os pins
        // das 5 fases passam pros pontos verdes desenhados no chão da arte.
        // Só mexe nas fases do treinamento inaugural, pela ordem.
        $cli = (int) $db->query("SELECT id FROM cliente WHERE slug = 'boticario'")->fetchColumn();
        $tid = (int) $db->query("SELECT t.id FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id WHERE tr.cliente_id = {$cli} AND tr.nome = 'Universo da Maquiagem' ORDER BY t.ordem, t.id LIMIT 1")->fetchColumn();
        if ($tid) {
            $st = $db->prepare('UPDATE fase SET pin_x = ?, pin_y = ? WHERE treinamento_id = ? AND ordem = ?');
            foreach ([1 => [22, 69], 2 => [40, 39], 3 => [57, 71.5], 4 => [75, 37.5], 5 => [85.5, 75.5]] as $ordem => [$x, $y]) $st->execute([$x, $y, $tid, $ordem]);
        }
    }

    if ($v < 35) {
        // v35 · Fase "Simulação de atendimento" (dois atendimentos em chat —
        // minigame `atendimento`) entra no fim do treinamento inaugural.
        // Material novo do cliente (2026-09-23). Idempotente pelo nome da fase.
        require_once __DIR__ . '/conteudo/simulacao-atendimento.php';
        $cli = (int) $db->query("SELECT id FROM cliente WHERE slug = 'boticario'")->fetchColumn();
        if ($cli) conteudo_sim_atendimento_inserir($db, $cli);
    }

    $db->exec('PRAGMA user_version = ' . LMS_SCHEMA_VERSAO);
}

// Atalhos de consulta.
function db_all(string $sql, array $p = []): array {
    $st = lms_db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}
function db_one(string $sql, array $p = []): ?array {
    $st = lms_db()->prepare($sql); $st->execute($p); $r = $st->fetch(); return $r === false ? null : $r;
}
function db_run(string $sql, array $p = []): void {
    $st = lms_db()->prepare($sql); $st->execute($p);
}
