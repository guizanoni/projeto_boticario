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
const LMS_SCHEMA_VERSAO = 31;

function lms_migrar(PDO $db): void {
    $v = (int) $db->query('PRAGMA user_version')->fetchColumn();
    if ($v >= LMS_SCHEMA_VERSAO) return;

    // Migrações futuras entram aqui, no padrão do engine — cada bloco
    // idempotente e aditivo, sem perder dado:
    //   if ($v < 32) { $cols = …; if (!in_array('x', $cols, true)) $db->exec('ALTER TABLE … ADD COLUMN x …'); }
    // Antes de renomear/recriar tabela no SQLite, ler o MEMORY.md da raiz:
    // as FKs do Atlântica quebraram exatamente nesse movimento.

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
