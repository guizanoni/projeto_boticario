<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Aceite de uso (1º acesso do aluno).
// Termo de uso da ferramenta, uso da imagem/avatar e registro de
// progresso. Só aparece uma vez, ANTES do onboarding do avatar.
// "Aceito jogar" carimba usuario.aceite_em e segue o fluxo.
// Sem comentários / sem topbar — é uma tela de consentimento.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate(['aluno']);
if (!empty($u['aceite_em'])) { header('Location: ' . lms_home($u)); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db_run('UPDATE usuario SET aceite_em = CURRENT_TIMESTAMP WHERE id = ?', [(int) $u['id']]);
    // o gate cuida do próximo passo (onboarding do avatar / trilha / mapa)
    header('Location: /boticario/mapa'); exit;
}

$itens = [
    ['🛟', 'Uso da plataforma', 'Vou usar a plataforma para o meu treinamento e seguir as regras de uso e segurança da informação.'],
    ['🧍', 'Uso da minha imagem', 'Autorizo o uso do meu avatar e da minha foto de perfil dentro da plataforma — no meu perfil, no mapa e no ranking da equipe.'],
    ['📊', 'Registro de progresso', 'Entendo que meu progresso, acertos, tempo e participação são registrados para acompanhamento do treinamento pela minha empresa.'],
];

lms_head('Aceite de uso', $u);
?>
<main class="ace-wrap">
  <div class="ace-topo">
    <?= lms_logo_icon(30) ?>
    <span class="ace-marca"><?= e($u['cliente_nome']) ?></span>
    <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/logout" style="margin-left:auto">Sair</a>
  </div>

  <div class="lms-center" style="margin-bottom:22px">
    <span class="ace-kicker">✋ Antes de começar</span>
    <h1 style="font-size:var(--lms-title-md); margin-top:12px">Aceite de uso da plataforma</h1>
    <p class="lms-muted" style="margin-top:8px; max-width:520px; margin-inline:auto">
      Rapidinho: confirme os pontos abaixo para começar. Isso vale só no seu primeiro acesso.</p>
  </div>

  <form method="post" class="lms-card-game" style="padding:24px">
    <p class="ace-instr">👉 Marque cada ponto para liberar o botão:</p>
    <div class="ace-lista">
      <?php foreach ($itens as $i => [$ico, $tit, $txt]): ?>
      <label class="ace-item">
        <input type="checkbox" class="ace-check" required>
        <span class="ace-box">✓</span>
        <span class="ace-ico"><?= $ico ?></span>
        <span class="ace-txt"><strong><?= e($tit) ?></strong><br><?= e($txt) ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="lms-btn lms-btn-primary lms-btn-lg ace-cta" disabled>✋ Aceito jogar</button>
    <p class="lms-small lms-muted lms-center" style="margin-top:12px">Ao aceitar, você concorda com os termos de uso da plataforma.</p>
  </form>
</main>

<style>
  body { background: var(--lms-bg); }
  .ace-wrap { max-width: 640px; margin: 0 auto; padding: 18px 20px 64px; }
  .ace-topo { display: flex; align-items: center; gap: 10px; padding: 6px 0 22px; }
  .ace-marca { font-family: var(--lms-font-display, var(--lms-font-ui)); font-weight: 700; color: var(--lms-heading); }
  .ace-kicker { display: inline-block; font-family: var(--lms-font-ui); font-weight: 700; font-size: 12px;
    letter-spacing: .06em; background: var(--lms-sand, #FBEED3); color: var(--lms-heading);
    padding: 5px 13px; border-radius: 999px; border: var(--lms-border-game); }
  .ace-instr { font-size: 13.5px; color: var(--lms-ink-soft); margin-bottom: 14px; }
  .ace-lista { display: flex; flex-direction: column; gap: 12px; }
  .ace-item { display: flex; gap: 12px; align-items: flex-start; cursor: pointer;
    border: 2px solid var(--lms-border); border-radius: var(--lms-radius-md); padding: 14px 16px;
    transition: border-color .15s ease, background .15s ease; }
  .ace-item:hover { border-color: var(--lms-ink-faint); }
  .ace-check { position: absolute; opacity: 0; width: 0; height: 0; }
  .ace-box { flex: none; width: 24px; height: 24px; border-radius: 7px; border: 2px solid var(--lms-border);
    display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800;
    color: transparent; background: var(--lms-white); transition: all .15s ease; }
  .ace-item:has(.ace-check:checked) { border-color: var(--lms-success); background: var(--lms-success-soft, #DFF3E4); }
  .ace-item:has(.ace-check:checked) .ace-box { background: var(--lms-success); border-color: var(--lms-success); color: #fff; }
  .ace-ico { flex: none; font-size: 22px; line-height: 1.2; }
  .ace-txt { font-size: 14px; line-height: 1.55; color: var(--lms-ink); }
  .ace-txt strong { color: var(--lms-heading); }
  .ace-cta { width: 100%; justify-content: center; margin-top: 20px; }
  .ace-cta:disabled { opacity: .45; cursor: not-allowed; }
</style>

<script>
// libera o "Aceito jogar" só quando todos os pontos estão marcados
const form = document.querySelector('form');
const checks = [...form.querySelectorAll('.ace-check')];
const cta = form.querySelector('.ace-cta');
const sync = () => { cta.disabled = !checks.every(c => c.checked); };
checks.forEach(c => c.addEventListener('change', sync));
sync();
</script>
</body></html>
