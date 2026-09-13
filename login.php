<?php
// Boticário LMS · Login (RF-01)
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_user();
if ($u) { header('Location: ' . lms_home($u)); exit; }

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logado = lms_login($_POST['email'] ?? '', $_POST['senha'] ?? '');
    if ($logado) { header('Location: /boticario/'); exit; }
    $erro = 'E-mail ou senha incorretos. Confira e tente de novo.';
}

lms_head('Entrar');
?>
<main style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px">
  <div style="width:100%; max-width:420px">
    <div class="lms-center" style="margin-bottom:28px">
      <img src="/admin/logos/boticario.svg" alt="O Boticário" style="width:min(320px, 80%); height:auto; margin:0 auto">
      <p class="lms-muted lms-small" style="margin-top:12px">Plataforma de treinamento · powered by Skope LMS</p>
    </div>

    <div class="lms-card-game lms-stack-4">
      <?php if ($erro): ?>
        <div class="lms-alert is-error">⚠️ <div><?= e($erro) ?></div></div>
      <?php endif; ?>
      <form method="post" class="lms-stack-4">
        <div>
          <label class="lms-label" for="email">E-mail</label>
          <input class="lms-input" type="email" id="email" name="email" placeholder="voce@boticario.com.br"
                 value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div>
          <label class="lms-label" for="senha">Senha</label>
          <input class="lms-input" type="password" id="senha" name="senha" required>
        </div>
        <button class="lms-btn lms-btn-primary lms-btn-lg" style="width:100%">Entrar</button>
      </form>
    </div>

    <!-- sem card de "acessos de demonstração": este tenant não tem usuários
         demo — o único acesso inicial é o admin criado pelo seed. -->
  </div>
</main>
</body></html>
