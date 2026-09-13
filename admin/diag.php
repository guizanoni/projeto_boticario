<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Diagnóstico (só leitura, só admin).
// Existe pra dar visibilidade de erros em produção sem depender de
// acesso ao painel da hospedagem — lê o log passivo gravado por
// lms_registrar_erro() (_lib/db.php) e faz checagens básicas de
// saúde do banco. Não escreve nada no banco.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['admin']);
if ($u['papel'] !== 'admin') { header('Location: ' . lms_home($u)); exit; }

$dir = __DIR__ . '/../data';
$logFile = $dir . '/erros.log';

$erros = '';
if (is_file($logFile)) {
    $tam = filesize($logFile);
    $fh = fopen($logFile, 'r');
    fseek($fh, max(0, $tam - 20000));
    $erros = fread($fh, 20000);
    fclose($fh);
}

$dbFile = $dir . '/lms.sqlite';
$integridade = [];
$contagens = [];
try {
    $db = lms_db();
    $integridade = $db->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['trilha', 'treinamento', 'fase', 'etapa', 'usuario', 'progresso_usuario', 'comentario'] as $t) {
        $contagens[$t] = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    }
} catch (Throwable $e) {
    $integridade = ['ERRO: ' . $e->getMessage()];
}

lms_head('Diagnóstico', $u);
lms_topbar($u);
?>
<main class="lms-container" style="max-width:900px; padding-top:24px; padding-bottom:64px">
  <h1 style="font-size:var(--lms-title-md)">🩺 Diagnóstico</h1>
  <p class="lms-small lms-muted">Só leitura — não altera nada no banco. Página temporária de apoio a incidente.</p>

  <div class="lms-card-game" style="margin-top:18px">
    <h3 style="margin-top:0">Banco</h3>
    <p class="lms-small">Arquivo: <?= e($dbFile) ?><br>
    Tamanho: <?= is_file($dbFile) ? number_format(filesize($dbFile)) . ' bytes' : 'não existe' ?><br>
    user_version: <?= (int) ($db->query('PRAGMA user_version')->fetchColumn() ?? -1) ?><br>
    PHP: <?= PHP_VERSION ?> · SQLite: <?= (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn() ?></p>

    <h3>Integrity check</h3>
    <pre style="white-space:pre-wrap; font-size:12.5px"><?= e(implode("\n", $integridade)) ?></pre>

    <h3>Contagens</h3>
    <pre style="white-space:pre-wrap; font-size:12.5px"><?php foreach ($contagens as $t => $n) echo e($t) . ': ' . (int) $n . "\n"; ?></pre>
  </div>

  <div class="lms-card-game" style="margin-top:18px">
    <h3 style="margin-top:0">Últimos erros capturados (erros.log)</h3>
    <?php if ($erros === ''): ?>
      <p class="lms-small lms-muted">Vazio — nenhum fatal capturado ainda (ou o arquivo não existe).</p>
    <?php else: ?>
      <pre style="white-space:pre-wrap; font-size:12px; max-height:500px; overflow:auto"><?= e($erros) ?></pre>
    <?php endif; ?>
  </div>
</main>
</body></html>
