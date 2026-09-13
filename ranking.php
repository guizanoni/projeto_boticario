<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Ranking da equipe (3Fs — Friends).
// Pódio visual + próximo alvo + comparação com a média + lista
// completa. Comparação saudável: destaque para quem está na
// frente (aspiracional), sem constranger quem está atrás.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate(['aluno']);
$ranking = lms_ranking((int) $u['cliente_id']);
[$pos, $total, $media, $meuXp] = lms_posicao($ranking, (int) $u['id']);

$maxXp = max(1, (int) ($ranking[0]['xp'] ?? 1), $media);
$aFrente = max(0, $total - $pos);
$noPodio = $pos > 0 && $pos <= 3;

// próximo alvo: quem está imediatamente acima (gamificação do "quase lá")
$alvo = null;
if ($pos > 1) {
    $alvo = $ranking[$pos - 2];
    $alvo['falta'] = (int) $alvo['xp'] - $meuXp + 10; // +10: precisa passar
}

$podio = array_slice($ranking, 0, 3);
$resto = array_slice($ranking, 3);

lms_head('Ranking da equipe', $u);
lms_topbar($u);
?>
<main class="lms-container" style="max-width:860px; padding-top:28px; padding-bottom:64px">
  <a class="lms-small" href="/boticario/mapa">← Voltar ao mapa</a>

  <div class="lms-row" style="justify-content:space-between; align-items:flex-end; margin-top:10px">
    <div>
      <h1 style="font-size:var(--lms-title-md)">🏆 Ranking da equipe</h1>
      <p class="lms-muted lms-small" style="margin-top:4px">XP conquistado na jornada · <?= $total ?> participantes</p>
    </div>
    <span class="lms-pill <?= $noPodio ? 'is-success' : 'is-primary' ?>"><?= $noPodio ? '🎉 Você está no pódio!' : 'Você: ' . $pos . 'º lugar' ?></span>
  </div>

  <!-- ── O PÓDIO ─────────────────────────────────────────── -->
  <div class="podio-palco">
    <span class="podio-brilho" style="top:18px; left:12%">✦</span>
    <span class="podio-brilho" style="top:40px; right:14%; animation-delay:.7s">✧</span>
    <span class="podio-brilho" style="top:80px; left:28%; animation-delay:1.3s">✦</span>
    <span class="podio-brilho" style="top:26px; right:34%; animation-delay:1.9s">✧</span>

    <div class="podio">
      <?php
      // ordem visual: 2º · 1º · 3º
      $ordem = [[1, 'prata', '🥈'], [0, 'ouro', '🥇'], [2, 'bronze', '🥉']];
      foreach ($ordem as [$i, $cor, $medalha]):
          $r = $podio[$i] ?? null; if (!$r) { echo '<div class="podio-col"></div>'; continue; }
          $souEu = (int) $r['id'] === (int) $u['id']; ?>
      <div class="podio-col is-<?= $cor ?>">
        <?php if ($i === 0): ?><div class="podio-coroa">👑</div><?php endif; ?>
        <div class="podio-avatar"><?= lms_avatar_html($r['avatar_genero'], $r['avatar_cor_pele'], $i === 0 ? 84 : 66, $r['foto_url'] ?? null) ?></div>
        <p class="podio-nome"><?= e($r['nome']) ?><?= $souEu ? ' <span class="lms-pill is-primary">você</span>' : '' ?></p>
        <span class="lms-xp"><span class="lms-xp-coin"></span> <?= number_format((int) $r['xp'], 0, ',', '.') ?></span>
        <div class="podio-bloco">
          <span class="podio-medalha"><?= $medalha ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── PRÓXIMO ALVO (o "quase lá") ─────────────────────── -->
  <?php if ($alvo && $alvo['falta'] > 0): ?>
  <div class="lms-card-pastel is-sand lms-row" style="justify-content:space-between; margin-top:18px; padding:20px 26px; gap:12px; flex-wrap:wrap">
    <div class="lms-row" style="gap:12px; flex-wrap:nowrap">
      <?= lms_avatar_html($alvo['avatar_genero'], $alvo['avatar_cor_pele'], 44, $alvo['foto_url'] ?? null) ?>
      <div>
        <p class="lms-small" style="font-family:var(--lms-font-ui); font-weight:700">🎯 Próximo alvo: <?= e($alvo['nome']) ?> (<?= $pos - 1 ?>º)</p>
        <p class="lms-small lms-muted">Faltam <strong><?= number_format($alvo['falta'], 0, ',', '.') ?> XP</strong> para ultrapassar — cada etapa vale até 60 XP.</p>
      </div>
    </div>
    <a class="lms-btn lms-btn-game" href="/boticario/mapa">Bora treinar ▶</a>
  </div>
  <?php elseif ($pos === 1 && $total > 1): ?>
  <div class="lms-card-pastel is-mint lms-center" style="margin-top:18px; padding:20px">
    <p style="font-family:var(--lms-font-ui); font-weight:700">👑 Você lidera a equipe — mantenha a posição concluindo as próximas etapas!</p>
  </div>
  <?php endif; ?>

  <!-- ── VOCÊ × MÉDIA ────────────────────────────────────── -->
  <div class="lms-card-game" style="margin-top:18px; padding:24px">
    <div class="lms-row" style="justify-content:space-between; margin-bottom:6px">
      <span class="lms-small" style="font-weight:700">Você · <?= number_format($meuXp, 0, ',', '.') ?> XP</span>
      <span class="lms-pill <?= $meuXp >= $media ? 'is-success' : 'is-warning' ?>"><?= $meuXp >= $media ? '▲ acima da média' : '▼ abaixo da média' ?></span>
    </div>
    <div class="lms-progress" style="height:14px"><div class="lms-progress-fill" style="width:<?= round($meuXp / $maxXp * 100) ?>%"></div></div>
    <p class="lms-small lms-muted" style="margin:12px 0 6px">Média da equipe · <?= number_format($media, 0, ',', '.') ?> XP</p>
    <div class="lms-progress" style="height:14px"><div class="lms-progress-fill" style="width:<?= round($media / $maxXp * 100) ?>%; background:var(--lms-ink-soft, #69727D)"></div></div>
    <p class="lms-small lms-muted" style="margin-top:12px">
      Você é o <strong><?= $pos ?>º de <?= $total ?></strong><?= $aFrente > 0 ? ' — à frente de <strong>' . $aFrente . ' colega' . ($aFrente > 1 ? 's' : '') . '</strong>' : '' ?>. Evolua no seu ritmo. 💪
    </p>
  </div>

  <!-- ── LISTA COMPLETA (4º em diante) ───────────────────── -->
  <?php if ($resto): ?>
  <div class="lms-card-game" style="margin-top:18px; padding:18px 22px">
    <div class="lms-stack-2">
      <?php foreach ($resto as $i => $r): $p = $i + 4; $souEu = (int) $r['id'] === (int) $u['id']; ?>
      <div class="lms-row" style="gap:10px; flex-wrap:nowrap; padding:8px 12px; border-radius:var(--lms-radius-md); <?= $souEu ? 'background:var(--lms-sky); box-shadow:var(--lms-shadow-crisp-sm)' : '' ?>">
        <span style="width:30px; text-align:center; font-family:var(--lms-font-ui); font-weight:700"><?= $p ?>º</span>
        <?= lms_avatar_html($r['avatar_genero'], $r['avatar_cor_pele'], 30, $r['foto_url'] ?? null) ?>
        <span style="flex:1; font-family:var(--lms-font-ui); font-weight:600; font-size:13.5px"><?= e($r['nome']) ?><?= $souEu ? ' <span class="lms-pill is-primary">você</span>' : '' ?></span>
        <span class="lms-xp" style="flex:none"><span class="lms-xp-coin"></span> <?= number_format((int) $r['xp'], 0, ',', '.') ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</main>

<style>
  .podio-palco {
    position:relative; margin-top:18px; padding:34px 20px 0;
    background:linear-gradient(180deg, var(--lms-sky, #D3ECF9) 0%, #EAF6FC 100%);
    border:var(--lms-border-game); border-radius:var(--lms-radius-xl);
    box-shadow:var(--lms-shadow-crisp); overflow:hidden;
  }
  .podio-brilho { position:absolute; font-size:18px; color:#F0B429; animation:brilha 2.6s ease-in-out infinite }
  @keyframes brilha { 0%,100% { opacity:.25; transform:scale(.85) } 50% { opacity:1; transform:scale(1.15) } }

  .podio { display:flex; align-items:flex-end; justify-content:center; gap:14px; max-width:560px; margin:0 auto }
  .podio-col { flex:1; max-width:170px; display:flex; flex-direction:column; align-items:center; gap:6px }
  .podio-coroa { font-size:30px; animation:coroa 2.2s ease-in-out infinite }
  @keyframes coroa { 0%,100% { transform:translateY(0) rotate(-4deg) } 50% { transform:translateY(-6px) rotate(4deg) } }
  .podio-avatar { animation:podio-pop .5s var(--lms-ease, ease) .55s both }
  @keyframes podio-pop { from { opacity:0; transform:scale(.4) } to { opacity:1; transform:scale(1) } }
  .podio-nome { font-family:var(--lms-font-ui); font-weight:700; font-size:13.5px; text-align:center; line-height:1.25 }

  .podio-bloco {
    width:100%; margin-top:8px; border:var(--lms-border-game);
    border-bottom:0; border-radius:var(--lms-radius-md) var(--lms-radius-md) 0 0;
    display:flex; align-items:center; justify-content:center;
    transform-origin:bottom; animation:podio-sobe .6s var(--lms-ease, ease) both;
  }
  .is-ouro   .podio-bloco { height:118px; background:linear-gradient(180deg, #F7CE58, #F0B429); animation-delay:.15s }
  .is-prata  .podio-bloco { height:88px;  background:linear-gradient(180deg, #E8ECF1, #CDD5DE); animation-delay:0s }
  .is-bronze .podio-bloco { height:66px;  background:linear-gradient(180deg, #E8B583, #D89B5E); animation-delay:.3s }
  @keyframes podio-sobe { from { transform:scaleY(0) } to { transform:scaleY(1) } }
  .podio-medalha { font-size:32px; filter:drop-shadow(0 2px 2px rgba(30,37,67,.25)) }

  @media (max-width: 620px) {
    .podio { gap:8px }
    .podio-nome { font-size:12px }
    .is-ouro .podio-bloco { height:96px } .is-prata .podio-bloco { height:70px } .is-bronze .podio-bloco { height:52px }
  }
</style>

<?php if ($noPodio): ?>
<script>
/* bombinhas para quem chega ao pódio 🎉 */
(function () {
  const cv = document.createElement('canvas');
  cv.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:99';
  cv.width = innerWidth; cv.height = innerHeight;
  document.body.appendChild(cv);
  const ctx = cv.getContext('2d');
  const cores = ['#1CAE8D', '#F0B429', '#E8641B', '#2E7D32', '#2A4971'];
  const ps = Array.from({ length: 110 }, () => ({
    x: cv.width / 2 + (Math.random() - .5) * cv.width * .6,
    y: cv.height * .3 + (Math.random() - .5) * 60,
    vx: (Math.random() - .5) * 10, vy: -(4 + Math.random() * 8),
    w: 6 + Math.random() * 6, h: 8 + Math.random() * 7,
    cor: cores[Math.floor(Math.random() * cores.length)],
    rot: Math.random() * Math.PI, vr: (Math.random() - .5) * .3,
  }));
  let t = 0;
  (function anima() {
    ctx.clearRect(0, 0, cv.width, cv.height);
    ps.forEach(p => {
      p.x += p.vx; p.y += p.vy; p.vy += .27; p.rot += p.vr;
      ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
      ctx.fillStyle = p.cor; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      ctx.restore();
    });
    if (++t < 140) requestAnimationFrame(anima); else cv.remove();
  })();
})();
</script>
<?php endif; ?>
</body></html>
