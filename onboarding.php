<?php
// Skope LMS · Primeiro acesso: montar o avatar (RF-03)
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate(['aluno']);
if ($u['avatar_genero']) { header('Location: /boticario/mapa'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gen = ($_POST['genero'] ?? '') === 'f' ? 'f' : 'm';
    $pele = array_key_exists($_POST['pele'] ?? '', lms_tons_pele()) ? $_POST['pele'] : '2';
    db_run('UPDATE usuario SET avatar_genero = ?, avatar_cor_pele = ? WHERE id = ?', [$gen, $pele, $u['id']]);
    header('Location: /boticario/mapa'); exit;
}

lms_head('Monte seu avatar', $u);
?>
<main style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px">
  <form method="post" style="width:100%; max-width:520px">
    <div class="lms-center" style="margin-bottom:24px">
      <h1 style="font-size:var(--lms-title-md)">Boas-vindas, <?= e(explode(' ', $u['nome'])[0]) ?>! 👋</h1>
      <p class="lms-muted" style="margin-top:8px">Monte seu avatar antes de começar. Ele é você no mapa da jornada.</p>
    </div>

    <div class="lms-card-game lms-stack-6">
      <div class="lms-center" id="ob-preview"><?= lms_avatar_svg('m', '2', 130) ?></div>

      <div>
        <p class="lms-label">Avatar</p>
        <div class="lms-row" style="gap:12px">
          <label class="lms-check" style="flex:1; justify-content:center" data-gen="m">
            <input type="radio" name="genero" value="m" checked style="display:none"> 👷 Masculino
          </label>
          <label class="lms-check" style="flex:1; justify-content:center" data-gen="f">
            <input type="radio" name="genero" value="f" style="display:none"> 👷‍♀️ Feminino
          </label>
        </div>
      </div>

      <div>
        <p class="lms-label">Tom de pele</p>
        <div class="lms-row" style="gap:10px">
          <?php foreach (lms_tons_pele() as $k => $cor): ?>
          <label data-pele="<?= e($k) ?>" style="cursor:pointer">
            <input type="radio" name="pele" value="<?= e($k) ?>" <?= $k === '2' ? 'checked' : '' ?> style="display:none">
            <span class="ob-tom" style="display:block; width:44px; height:44px; border-radius:50%; background:<?= e($cor) ?>; border:2px solid var(--lms-line); box-shadow:<?= $k === '2' ? 'var(--lms-shadow-crisp-sm)' : 'none' ?>"></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="lms-btn lms-btn-primary lms-btn-lg" style="width:100%">Começar ▶</button>
    </div>
  </form>
</main>

<script>
// preview vivo do avatar (busca o SVG re-renderizado no servidor)
const form = document.querySelector('form'), preview = document.getElementById('ob-preview');
function marcar() {
  document.querySelectorAll('[data-gen]').forEach(l => l.classList.toggle('is-checked', l.querySelector('input').checked));
  document.querySelectorAll('[data-pele] .ob-tom').forEach(s => {
    const on = s.parentElement.querySelector('input').checked;
    s.style.boxShadow = on ? 'var(--lms-shadow-crisp-sm)' : 'none';
    s.style.transform = on ? 'scale(1.12)' : 'none';
  });
}
form.addEventListener('change', async () => {
  marcar();
  const g = form.genero.value, p = form.pele.value;
  const r = await fetch(`/boticario/api/avatar?genero=${g}&pele=${p}&size=130`);
  preview.innerHTML = await r.text();
});
marcar();
</script>
</body></html>
