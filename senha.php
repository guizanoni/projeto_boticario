<?php
// Skope LMS · Alterar a própria senha (qualquer usuário logado).
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate();

$flash = null;
// Aparência (skin trocável) — só admin escolhe; persiste em cookie (por navegador).
$skinsDisponiveis = ['' => 'Padrão', 'garden' => 'Garden', 'mint' => 'Mint', 'car' => 'Car', 'theus' => 'Theus'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'skin' && $u['papel'] === 'admin') {
    $novo = array_key_exists($_POST['skin'] ?? '', $skinsDisponiveis) ? (string) $_POST['skin'] : '';
    setcookie('lms_skin', $novo, ['expires' => time() + 31536000, 'path' => '/boticario/', 'samesite' => 'Lax']);
    lms_flash_set(true, $novo === '' ? '🎨 Design padrão restaurado.' : '🎨 Design ' . $skinsDisponiveis[$novo] . ' ativado.');
    header('Location: /boticario/senha'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') !== 'skin') {
    $atual = (string) ($_POST['senha_atual'] ?? '');
    $nova  = (string) ($_POST['senha_nova'] ?? '');
    $conf  = (string) ($_POST['senha_confirma'] ?? '');
    if (!password_verify($atual, $u['senha_hash'])) {
        $flash = [false, 'A senha atual não confere.'];
    } elseif (strlen($nova) < 4) {
        $flash = [false, 'A nova senha precisa ter ao menos 4 caracteres.'];
    } elseif ($nova !== $conf) {
        $flash = [false, 'A confirmação não bate com a nova senha.'];
    } else {
        db_run('UPDATE usuario SET senha_hash = ? WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
        $flash = [true, 'Senha alterada! Use a nova no próximo login.'];
    }
}

if (!$flash) { $fg = lms_flash_get(); if ($fg) $flash = [$fg['ok'], $fg['msg']]; }

lms_head('Minha conta', $u);
lms_topbar($u);
?>
<main class="lms-container" style="max-width:520px; padding-top:28px; padding-bottom:64px">
  <a class="lms-small" href="<?= e(lms_home($u)) ?>">← Voltar</a>

  <div class="lms-card-game" style="margin-top:14px">
    <h1 style="font-size:var(--lms-title-md); margin-bottom:4px">🔑 Alterar senha</h1>
    <p class="lms-muted lms-small" style="margin-bottom:18px"><?= e($u['nome']) ?> · <?= e($u['email']) ?></p>

    <?php if ($flash): ?>
    <div class="lms-alert <?= $flash[0] ? 'is-success' : 'is-error' ?>" style="margin-bottom:16px">
      <?= $flash[0] ? '✅' : '⚠️' ?> <div><?= e($flash[1]) ?></div>
    </div>
    <?php endif; ?>

    <form method="post" class="lms-stack-4">
      <div>
        <label class="lms-label" for="s-atual">Senha atual</label>
        <input class="lms-input" type="password" id="s-atual" name="senha_atual" required autocomplete="current-password">
      </div>
      <div>
        <label class="lms-label" for="s-nova">Nova senha</label>
        <input class="lms-input" type="password" id="s-nova" name="senha_nova" required minlength="4" autocomplete="new-password" placeholder="mín. 4 caracteres">
      </div>
      <div>
        <label class="lms-label" for="s-conf">Confirmar nova senha</label>
        <input class="lms-input" type="password" id="s-conf" name="senha_confirma" required minlength="4" autocomplete="new-password">
      </div>
      <button class="lms-btn lms-btn-primary lms-btn-lg" style="width:100%">Salvar nova senha</button>
    </form>
  </div>

  <?php if ($u['papel'] === 'admin'):
    $skinAtual = array_key_exists($_COOKIE['lms_skin'] ?? '', $skinsDisponiveis) ? (string) $_COOKIE['lms_skin'] : '';
    $skinDesc = [
      '' => 'Tema padrão — o design atual da plataforma.',
      'garden' => 'Tema 1',
      'mint' => 'Tema 2',
      'car' => 'Tema 3',
      'theus' => 'Tema 4',
    ]; ?>
  <div class="lms-card-game" style="margin-top:16px">
    <h2 style="font-size:var(--lms-title-sm); margin-bottom:4px">🎨 Aparência (design)</h2>
    <p class="lms-muted lms-small" style="margin-bottom:14px">Alterna o design system da plataforma para você pré-visualizar. Afeta só o seu navegador — não muda para os outros usuários.</p>
    <form method="post" class="lms-stack-4">
      <input type="hidden" name="acao" value="skin">
      <?php foreach ($skinsDisponiveis as $k => $nome): ?>
      <label class="lms-row" style="gap:10px; cursor:pointer; align-items:flex-start">
        <input type="radio" name="skin" value="<?= e($k) ?>" <?= $skinAtual === $k ? 'checked' : '' ?> style="margin-top:3px">
        <span><strong><?= e($nome) ?></strong><br><span class="lms-small lms-muted"><?= e($skinDesc[$k] ?? '') ?></span></span>
      </label>
      <?php endforeach; ?>
      <button class="lms-btn lms-btn-primary">Aplicar</button>
    </form>
  </div>
  <?php endif; ?>
</main>
</body></html>
