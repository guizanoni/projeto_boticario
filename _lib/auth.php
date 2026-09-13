<?php
// ──────────────────────────────────────────────────────────────
// Skope LMS · Autenticação e sessão (RF-01).
// Sessão própria do /lms (não conflita com a do /admin).
// Papéis: aluno (jornada) · gestor (dashboard) · admin (tudo).
// ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/db.php';

function lms_session_start(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    $ttl = 60 * 60 * 24 * 30;
    $dir = __DIR__ . '/.sessions';
    if ((is_dir($dir) || @mkdir($dir, 0700, true)) && is_writable($dir)) {
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        @ini_set('session.save_path', $dir);
    }
    @ini_set('session.gc_maxlifetime', (string) $ttl);
    session_name('BOTSESS');
    session_set_cookie_params(['lifetime' => $ttl, 'path' => '/boticario/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function lms_user(): ?array {
    lms_session_start();
    if (empty($_SESSION['lms_uid'])) return null;
    // já traz o contexto ativo do aluno: treinamento definido pelo admin
    // e a trilha dona dele (mapa/narrativa vêm da trilha).
    return db_one('SELECT u.*, c.nome AS cliente_nome, c.slug AS cliente_slug, c.cor_primaria, c.cor_secundaria, c.logo_url AS cliente_logo,
                          t.nome AS trein_nome, t.status AS trein_status,
                          tr.id AS trilha_id, tr.nome AS trilha_nome, tr.narrativa AS trilha_narrativa, tr.cenario_mapa_url
                   FROM usuario u
                   JOIN cliente c ON c.id = u.cliente_id
                   LEFT JOIN treinamento t ON t.id = u.treinamento_id
                   LEFT JOIN trilha tr ON tr.id = t.trilha_id
                   WHERE u.id = ?', [$_SESSION['lms_uid']]);
}

function lms_login(string $email, string $senha): ?array {
    $u = db_one('SELECT * FROM usuario WHERE email = ?', [strtolower(trim($email))]);
    if (!$u || !password_verify($senha, $u['senha_hash'])) return null;
    lms_session_start();
    session_regenerate_id(true);
    $_SESSION['lms_uid'] = $u['id'];
    db_run('UPDATE usuario SET ultimo_login = CURRENT_TIMESTAMP, acessos = acessos + 1 WHERE id = ?', [$u['id']]);
    return $u;
}

function lms_logout(): void {
    lms_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly']);
    }
    session_destroy();
}

// Gate de página. $papeis vazio = qualquer usuário logado.
function lms_gate(array $papeis = []): array {
    $u = lms_user();
    if (!$u) { header('Location: /boticario/login'); exit; }
    if ($papeis && !in_array($u['papel'], $papeis, true) && $u['papel'] !== 'admin') {
        header('Location: ' . lms_home($u)); exit;
    }
    $self = basename($_SERVER['SCRIPT_NAME']);
    // 1º acesso do aluno: aceite de uso da plataforma (termo/imagem/dados),
    // antes de qualquer coisa. gestor e admin não passam por isso.
    if ($u['papel'] === 'aluno' && empty($u['aceite_em']) && !in_array($self, ['aceite.php', 'logout.php', 'senha.php'], true)) {
        header('Location: /boticario/aceite'); exit;
    }
    // aluno sem avatar definido vai para o onboarding (RF-03) —
    // gestor e admin não são alunos: nunca passam por onboarding.
    if ($u['papel'] === 'aluno' && !$u['avatar_genero'] && !in_array($self, ['aceite.php', 'onboarding.php', 'logout.php', 'senha.php'], true)) {
        header('Location: /boticario/onboarding'); exit;
    }
    // aluno com DUAS trilhas escolhe qual entrar (uma vez por sessão).
    if ($u['papel'] === 'aluno' && !empty($u['treinamento_id_2']) && empty($_SESSION['trilha_ok'])
        && !in_array($self, ['aceite.php', 'escolher.php', 'onboarding.php', 'logout.php', 'senha.php'], true)) {
        header('Location: /boticario/escolher'); exit;
    }
    // 1ª vez numa trilha (já tem avatar): apresentação da plataforma/trilha.
    if ($u['papel'] === 'aluno' && $u['avatar_genero'] && !empty($u['trilha_id'])
        && !in_array($self, ['aceite.php', 'apresentacao.php', 'escolher.php', 'onboarding.php', 'logout.php', 'senha.php'], true)) {
        $vistas = json_decode((string) ($u['trilhas_vistas'] ?? ''), true) ?: [];
        if (!in_array((int) $u['trilha_id'], $vistas, true)) { header('Location: /boticario/apresentacao'); exit; }
    }
    return $u;
}

// Gate de endpoint JSON.
function lms_gate_api(): array {
    $u = lms_user();
    if (!$u) { http_response_code(401); header('Content-Type: application/json'); echo '{"ok":false,"msg":"Sessão expirada"}'; exit; }
    return $u;
}

// Tela inicial de cada papel: aluno vive no mapa; gestor e admin
// entram pelo dashboard (visão estratégica primeiro).
function lms_home(array $u): string {
    if (in_array($u['papel'], ['gestor', 'admin'], true)) return '/boticario/gestor/';
    return '/boticario/mapa';
}

// Mensagens flash (pós-redirect).
function lms_flash_set(bool $ok, string $msg): void {
    lms_session_start();
    $_SESSION['lms_flash'] = ['ok' => $ok, 'msg' => $msg];
}
function lms_flash_get(): ?array {
    lms_session_start();
    $f = $_SESSION['lms_flash'] ?? null;
    unset($_SESSION['lms_flash']);
    return $f;
}
