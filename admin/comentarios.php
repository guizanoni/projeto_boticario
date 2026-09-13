<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Moderação da comunidade — todos os comentários
// em um lugar só. Mensagens de alunos nascem 'pendente' e só
// aparecem para a equipe depois de aprovadas aqui (ou inline
// no treinamento). Gestor e admin moderam.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['gestor', 'admin']);
if (!in_array($u['papel'], ['gestor', 'admin'], true)) { header('Location: ' . lms_home($u)); exit; }

// ── Ações (POST) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $c = db_one('SELECT * FROM comentario WHERE id = ? AND cliente_id = ?', [(int) ($_POST['id'] ?? 0), $u['cliente_id']]);
    if (!$c) {
        lms_flash_set(false, 'Comentário não encontrado.');
    } elseif ($acao === 'aprovar') {
        $badge = lms_comentario_aprovar($c);
        lms_flash_set(true, 'Mensagem aprovada e publicada para a equipe. ✔️'
            . ($badge ? " O autor ganhou a badge {$badge['icone']} {$badge['nome']}!" : ''));
    } elseif ($acao === 'recusar') {
        db_run('UPDATE comentario SET status = \'oculto\', oficial = 0 WHERE id = ?', [$c['id']]);
        lms_flash_set(true, 'Mensagem ' . ($c['status'] === 'pendente' ? 'recusada' : 'ocultada') . ' — ninguém além de você a vê. 🚫');
    } elseif ($acao === 'oficial') {
        db_run('UPDATE comentario SET oficial = CASE oficial WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [$c['id']]);
        lms_flash_set(true, $c['oficial'] ? 'Selo de resposta oficial removido.' : 'Marcada como resposta oficial. ✅');
    } elseif ($acao === 'excluir' && $u['papel'] === 'admin') {
        db_run('DELETE FROM comentario WHERE id = ? OR parent_id = ?', [$c['id'], $c['id']]);
        lms_flash_set(true, 'Mensagem excluída definitivamente' . ($c['parent_id'] ? '.' : ' (com as respostas dela).') . ' 🗑');
    }
    header('Location: /boticario/admin/comentarios'); exit;
}
$flash = lms_flash_get();

$coments = db_all("SELECT c.*, u2.nome AS autor, u2.papel AS autor_papel, u2.avatar_genero, u2.avatar_cor_pele,
                          u2.foto_url, f.nome AS fase_nome
                   FROM comentario c
                   JOIN usuario u2 ON u2.id = c.usuario_id
                   JOIN fase f ON f.id = c.fase_id
                   WHERE c.cliente_id = ? ORDER BY c.id DESC", [$u['cliente_id']]);
$grupos = ['pendente' => [], 'ativo' => [], 'oculto' => []];
foreach ($coments as $c) $grupos[$c['status']][] = $c;

function com_quando(string $ts): string {
    $s = max(0, time() - (new DateTime($ts, new DateTimeZone('UTC')))->getTimestamp());
    if ($s < 60)    return 'agora mesmo';
    if ($s < 3600)  return 'há ' . intdiv($s, 60) . ' min';
    if ($s < 86400) return 'há ' . intdiv($s, 3600) . ' h';
    return 'há ' . intdiv($s, 86400) . ' d';
}

function com_linha(array $c, bool $mostraAprovar): void { ?>
  <div class="modc-item">
    <?= lms_avatar_html($c['avatar_genero'], $c['avatar_cor_pele'], 38, $c['foto_url'] ?? null) ?>
    <div style="flex:1; min-width:0">
      <div class="lms-row" style="gap:8px; flex-wrap:wrap">
        <strong style="font-family:var(--lms-font-ui); font-size:14px"><?= e($c['autor']) ?></strong>
        <?php if (in_array($c['autor_papel'], ['gestor', 'admin'], true)): ?><span class="lms-pill" style="background:var(--lms-sky); font-size:11px; padding:2px 8px">🛡️ Equipe</span><?php endif; ?>
        <?php if ($c['oficial']): ?><span class="lms-pill is-success" style="font-size:11px; padding:2px 8px">✅ Resposta oficial</span><?php endif; ?>
        <?php if ($c['parent_id']): ?><span class="lms-small lms-muted">↩︎ resposta</span><?php endif; ?>
        <span class="lms-small lms-muted"><?= e(com_quando($c['criado_em'])) ?></span>
      </div>
      <p style="font-size:14px; line-height:1.5; margin:4px 0 6px; overflow-wrap:break-word"><?= nl2br(e($c['texto'])) ?></p>
      <p class="lms-small lms-muted">em <a href="/boticario/fase?id=<?= (int) $c['fase_id'] ?>#comunidade"><?= e($c['fase_nome']) ?></a></p>
    </div>
    <div class="lms-row" style="gap:6px; flex:none; flex-wrap:nowrap; align-self:center">
      <?php if ($mostraAprovar): ?>
      <form method="post"><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button class="lms-btn lms-btn-game lms-btn-sm">✔️ Aprovar</button></form>
      <form method="post"><input type="hidden" name="acao" value="recusar"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button class="lms-btn lms-btn-outline lms-btn-sm">🚫 Recusar</button></form>
      <?php elseif ($c['status'] === 'ativo'): ?>
      <form method="post"><input type="hidden" name="acao" value="oficial"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button class="lms-btn lms-btn-outline lms-btn-sm" title="<?= $c['oficial'] ? 'Remover selo de resposta oficial' : 'Marcar como resposta oficial' ?>"><?= $c['oficial'] ? 'Remover selo' : '✅ Oficial' ?></button></form>
      <form method="post"><input type="hidden" name="acao" value="recusar"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button class="lms-btn lms-btn-outline lms-btn-sm" title="Esconder da equipe">🙈 Ocultar</button></form>
      <?php else: ?>
      <form method="post"><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button class="lms-btn lms-btn-outline lms-btn-sm" title="Publicar novamente">♻️ Republicar</button></form>
      <?php endif; ?>
      <?php if ($GLOBALS['u']['papel'] === 'admin'): ?>
      <form method="post" data-confirmar-titulo="Excluir esta mensagem definitivamente?"
            data-confirmar="Não tem volta.<?= $c['parent_id'] ? '' : ' As respostas dela vão junto.' ?>" data-confirmar-perigo>
        <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button class="lms-btn lms-btn-outline lms-btn-sm" style="color:var(--lms-error)" title="Excluir definitivamente">🗑</button></form>
      <?php endif; ?>
    </div>
  </div>
<?php }

lms_head('Moderação da comunidade', $u);
lms_topbar($u);
?>
<main class="lms-container" style="max-width:860px; padding-top:28px; padding-bottom:64px">

  <h1 style="font-size:var(--lms-title-md)">💬 Comunidade — moderação</h1>
  <p class="lms-muted lms-small" style="margin-top:4px">
    Toda mensagem de aluno passa por aqui antes de aparecer para a equipe.
    Aprovar publica (e libera o XP e a badge de participação do autor); recusar esconde sem alarde.
  </p>

  <?php if ($flash): ?>
  <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:14px">
    <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div>
  </div>
  <?php endif; ?>

  <?php lms_busca('.modc-item', 'Buscar por autor, texto da mensagem, fase…', count($coments), 'mensagens', 'c-busca'); ?>

  <!-- ── fila de moderação ─────────────────────────────────── -->
  <div class="lms-card-pastel is-sand" style="margin-top:14px; padding:22px">
    <div class="lms-row" style="justify-content:space-between">
      <h3>⏳ Aguardando aprovação</h3>
      <span class="lms-pill <?= $grupos['pendente'] ? 'is-warning' : 'is-success' ?>"><?= count($grupos['pendente']) ?></span>
    </div>
    <?php if (!$grupos['pendente']): ?>
    <p class="lms-small lms-muted" style="margin-top:8px">Fila limpa — nenhuma mensagem esperando. 🙌</p>
    <?php else: foreach ($grupos['pendente'] as $c) com_linha($c, true); endif; ?>
  </div>

  <!-- ── publicados ────────────────────────────────────────── -->
  <div class="lms-card-game" style="margin-top:18px">
    <div class="lms-row" style="justify-content:space-between">
      <h3>📣 Publicados</h3>
      <span class="lms-pill"><?= count($grupos['ativo']) ?></span>
    </div>
    <?php if (!$grupos['ativo']): ?>
    <p class="lms-small lms-muted" style="margin-top:8px">Nenhuma mensagem publicada ainda.</p>
    <?php else: foreach ($grupos['ativo'] as $c) com_linha($c, false); endif; ?>
  </div>

  <!-- ── ocultos ───────────────────────────────────────────── -->
  <?php if ($grupos['oculto']): ?>
  <div class="lms-card-game" style="margin-top:18px; opacity:.75">
    <div class="lms-row" style="justify-content:space-between">
      <h3>🙈 Ocultos e recusados</h3>
      <span class="lms-pill"><?= count($grupos['oculto']) ?></span>
    </div>
    <?php foreach ($grupos['oculto'] as $c) com_linha($c, false); ?>
  </div>
  <?php endif; ?>

</main>

<style>
  .modc-item { display:flex; gap:12px; padding:14px 0; border-top:1px dashed rgba(31,42,37,.15) }
  .modc-item:first-of-type { border-top:none }
</style>
</body></html>
