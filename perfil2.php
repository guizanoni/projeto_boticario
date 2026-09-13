<?php
// Skope LMS · PERFIL V2 (PREVIEW) — layout estilo Duolingo: banner com
// avatar rico, estatísticas em cards, feed da equipe e conquistas.
// Não substitui o perfil atual (/boticario/perfil): é uma prévia paralela.
// Aprovada → vira o perfil oficial; reprovada → apaga-se este arquivo,
// o editor (perfil2-avatar.php) e o assets/js/avatar2.js, e nada muda.
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate(['aluno']);

$flash = lms_flash_get();
$j  = lms_jornada($u);
$xp = lms_xp((int) $u['id']);

$badges = db_all('SELECT b.*, c.ganho_em FROM badge b
                  LEFT JOIN conquista_usuario c ON c.badge_id = b.id AND c.usuario_id = ?
                  WHERE b.cliente_id = ? ORDER BY c.ganho_em IS NULL, b.id', [$u['id'], $u['cliente_id']]);
$ganhas = count(array_filter($badges, fn($b) => $b['ganho_em']));

$totalEtapas = array_sum(array_column($j['fases'], 'total'));
$feitas      = array_sum(array_column($j['fases'], 'feitas'));

$stats = db_one('SELECT COALESCE(SUM(acertos),0) AS a, COALESCE(SUM(total),0) AS t
                 FROM progresso_usuario WHERE usuario_id = ? AND status = \'concluido\'', [$u['id']]);
$pctAcerto = (int) $stats['t'] > 0 ? round($stats['a'] / $stats['t'] * 100) : 0;

$ranking = lms_ranking((int) $u['cliente_id']);
[$rkPos, $rkTotal, $rkMedia, $meuXp] = lms_posicao($ranking, (int) $u['id']);

// feed da equipe: conquistas recentes dos colegas (estilo Duolingo)
$feed = db_all("SELECT us.id, us.nome, us.avatar_genero, us.avatar_cor_pele, us.foto_url,
                       b.nome AS badge_nome, b.icone, c.ganho_em
                FROM conquista_usuario c
                JOIN usuario us ON us.id = c.usuario_id
                JOIN badge b   ON b.id  = c.badge_id
                WHERE us.cliente_id = ? AND us.papel = 'aluno' AND us.id != ?
                ORDER BY c.ganho_em DESC LIMIT 4", [$u['cliente_id'], $u['id']]);

$desde = $u['created_at'] ? (function ($d) {
    $m = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho',
          'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $t = strtotime($d);
    return 'Por aqui desde ' . $m[(int) date('n', $t)] . ' de ' . date('Y', $t);
})($u['created_at']) : '';

$cfg   = json_decode((string) ($u['avatar_v2'] ?? ''), true) ?: null;
// aluno sem avatar v2 ainda: parte do avatar v1 (tom de pele) como base
$peleMapa = ['1' => 'p8', '2' => 'p6', '3' => 'p4', '4' => 'p2', '5' => 'p1'];
$cfgBase  = $cfg ?: ['pele' => $peleMapa[$u['avatar_cor_pele'] ?? '2'] ?? 'p6'];

lms_head('Meu perfil · v2', $u);
lms_topbar($u);
?>
<style>
  .av2-wrap { max-width: 1080px; margin: 0 auto; padding: 24px 18px 72px; }
  .av2-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 26px; align-items: start; }
  @media (max-width: 900px) { .av2-grid { grid-template-columns: 1fr; } }

  .av2-card { background: var(--lms-surface, #fff); border: 2px solid var(--lms-border, #E5E5E5);
              border-radius: var(--lms-radius-lg, 16px); }
  .av2-banner { position: relative; border-radius: var(--lms-radius-lg, 16px); overflow: hidden;
                display: flex; justify-content: center; align-items: flex-end; height: 260px; }
  .av2-banner svg { display: block; height: 245px; width: auto; }
  .av2-lapis { position: absolute; top: 14px; right: 14px; width: 44px; height: 44px;
               display: grid; place-items: center; background: var(--lms-surface, #fff);
               border: 2px solid var(--lms-border, #E5E5E5); border-radius: 12px;
               font-size: 19px; text-decoration: none; box-shadow: 0 2px 0 var(--lms-border, #E5E5E5); }
  .av2-lapis:hover { transform: translateY(-1px); }

  .av2-nome { font-size: 30px; font-weight: 800; color: var(--lms-heading, #1F2A25);
              font-family: var(--lms-font-display, inherit); margin-top: 18px; }
  .av2-sub { color: var(--lms-ink-soft, #777); font-size: 15px; margin-top: 2px; }

  .av2-h2 { font-size: 21px; font-weight: 800; color: var(--lms-heading, #1F2A25);
            font-family: var(--lms-font-ui, inherit); margin: 30px 0 14px; }
  .av2-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .av2-stat { display: flex; gap: 12px; align-items: center; padding: 14px 16px; }
  .av2-stat .ico { font-size: 26px; line-height: 1; }
  .av2-stat b { display: block; font-size: 19px; color: var(--lms-heading, #1F2A25); }
  .av2-stat span { display: block; font-size: 13px; color: var(--lms-ink-soft, #777); margin-top: 1px; }

  .av2-feed-item { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
  .av2-feed-item + .av2-feed-item { border-top: 2px solid var(--lms-border, #E5E5E5); }
  .av2-comemore { align-self: flex-start; border: 2px solid var(--lms-border, #E5E5E5); background: var(--lms-surface, #fff);
                  border-radius: 12px; padding: 8px 16px; font-weight: 800; font-size: 13px; letter-spacing: .4px;
                  cursor: pointer; color: var(--lms-heading, #1F2A25); box-shadow: 0 2px 0 var(--lms-border, #E5E5E5);
                  font-family: var(--lms-font-ui, inherit); }
  .av2-comemore:active { transform: translateY(2px); box-shadow: none; }

  .av2-trip-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; }
  .av2-trip-item + .av2-trip-item { border-top: 2px solid var(--lms-border, #E5E5E5); }
  .av2-aba { flex: 1; text-align: center; padding: 13px 0 11px; font-weight: 800; font-size: 13px;
             letter-spacing: .6px; color: var(--lms-ink-soft, #999); border-bottom: 2px solid var(--lms-border, #E5E5E5); }
  .av2-aba.is-on { color: var(--lms-primary, #1CB0F6); border-bottom-color: var(--lms-primary, #1CB0F6); }

  .av2-conq { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
  .av2-conq-item { padding: 18px 12px; text-align: center; }
  .av2-conq-ico { width: 62px; height: 62px; margin: 0 auto; display: grid; place-items: center;
                  font-size: 32px; border-radius: 16px; background: var(--lms-sand, #FFF3D8); }
  .av2-conq-item.is-locked { opacity: .6; }
  .av2-conq-item.is-locked .av2-conq-ico { background: var(--lms-locked, #E8E8E8); filter: grayscale(1); }

  @keyframes av2-conf { 0% { transform: translateY(0) rotate(0); opacity: 1; }
                        100% { transform: translateY(-90px) rotate(240deg); opacity: 0; } }
  .av2-conf { position: absolute; font-size: 18px; pointer-events: none; animation: av2-conf .9s ease-out forwards; }
</style>

<main class="av2-wrap">
  <?php if ($flash): ?>
  <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-bottom:14px">
    <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div>
  </div>
  <?php endif; ?>
  <div class="lms-alert" style="background:var(--lms-lilac, #F1EAFB); border-radius:12px; display:flex; gap:10px; padding:12px 16px; margin-bottom:18px">
    🧪 <div class="lms-small"><strong>Prévia · Perfil v2.</strong> Nada do perfil atual foi alterado —
    <a href="/boticario/perfil">a versão de hoje continua aqui</a>. Se a v2 for aprovada, ela assume o lugar.</div>
  </div>

  <div class="av2-grid">
    <!-- ── coluna principal ─────────────────────────────────── -->
    <section>
      <div class="av2-banner" id="av2-banner">
        <a class="av2-lapis" href="/boticario/perfil2-avatar" title="Editar avatar">✏️</a>
      </div>

      <h1 class="av2-nome"><?= e($u['nome']) ?></h1>
      <?php if ($desde): ?><p class="av2-sub"><?= e($desde) ?></p><?php endif; ?>
      <p class="av2-sub" style="margin-top:8px">
        <strong style="color:var(--lms-primary)">🧭 <?= e($u['trilha_nome'] ?? 'Grupo Boticário') ?></strong>
        <?php if ($rkTotal > 1): ?> · Equipe com <strong><?= $rkTotal ?> participantes</strong> — você é o <strong><?= $rkPos ?>º</strong><?php endif; ?>
      </p>

      <h2 class="av2-h2">Estatísticas</h2>
      <div class="av2-stats">
        <div class="av2-card av2-stat"><span class="ico">🔥</span><div><b><?= (int) $u['acessos'] ?></b><span><?= (int) $u['acessos'] === 1 ? 'dia de acesso' : 'dias de acesso' ?></span></div></div>
        <div class="av2-card av2-stat"><span class="ico">⚡</span><div><b><?= number_format($xp, 0, ',', '.') ?></b><span>Total de XP</span></div></div>
        <div class="av2-card av2-stat"><span class="ico">🎯</span><div><b><?= $pctAcerto ?>%</b><span>acerto geral</span></div></div>
      </div>

      <h2 class="av2-h2">Jornada<?= $j['treinamento'] ? ' · ' . e($j['treinamento']['nome']) : '' ?></h2>
      <div class="av2-card" style="padding:18px 20px">
        <div class="lms-row" style="justify-content:space-between; margin-bottom:8px">
          <span class="lms-small lms-muted">Progresso do treinamento</span>
          <span class="lms-small" style="font-weight:700"><?= $feitas ?>/<?= $totalEtapas ?> etapas</span>
        </div>
        <div class="lms-progress"><div class="lms-progress-fill" style="width:<?= lms_pct($feitas, $totalEtapas) ?>%"></div></div>
      </div>

      <div class="lms-row" style="justify-content:space-between; align-items:baseline">
        <h2 class="av2-h2">Conquistas</h2>
        <span class="lms-small" style="font-weight:700; color:var(--lms-primary)"><?= $ganhas ?>/<?= count($badges) ?></span>
      </div>
      <div class="av2-conq">
        <?php foreach ($badges as $b): $tem = (bool) $b['ganho_em']; ?>
        <div class="av2-card av2-conq-item <?= $tem ? '' : 'is-locked' ?>">
          <div class="av2-conq-ico"><?= $tem ? e($b['icone']) : '🔒' ?></div>
          <p class="lms-small" style="font-weight:700; margin-top:10px"><?= e($b['nome']) ?></p>
          <p style="font-size:12px; color:var(--lms-ink-soft); margin-top:2px"><?= e($b['descricao']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ── coluna lateral ───────────────────────────────────── -->
    <aside>
      <?php if ($feed): ?>
      <div class="av2-card" style="overflow:hidden">
        <?php foreach ($feed as $f): ?>
        <div class="av2-feed-item">
          <div class="lms-row" style="gap:10px; align-items:center">
            <?= lms_avatar_html($f['avatar_genero'], $f['avatar_cor_pele'], 40, $f['foto_url']) ?>
            <div style="flex:1; min-width:0">
              <b style="font-size:14px; color:var(--lms-heading)"><?= e($f['nome']) ?></b>
              <div class="lms-small lms-muted" style="font-size:12px"><?= e(date('d/m', strtotime($f['ganho_em']))) ?></div>
            </div>
            <span style="font-size:26px"><?= e($f['icone']) ?></span>
          </div>
          <p class="lms-small">Desbloqueou a conquista <strong><?= e($f['badge_nome']) ?></strong>!</p>
          <button class="av2-comemore" type="button" onclick="av2Comemora(this)">🎉 COMEMORE</button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($rkTotal > 1): ?>
      <div class="av2-card" style="overflow:hidden; margin-top:18px">
        <div style="display:flex">
          <div class="av2-aba is-on">EQUIPE</div>
        </div>
        <?php foreach (array_slice($ranking, 0, 5) as $i => $r): ?>
        <div class="av2-trip-item" <?= (int) $r['id'] === (int) $u['id'] ? 'style="background:var(--lms-sky, #EDF7FF)"' : '' ?>>
          <?= lms_avatar_html($r['avatar_genero'], $r['avatar_cor_pele'], 40, $r['foto_url']) ?>
          <div style="flex:1; min-width:0">
            <b style="font-size:14px; color:var(--lms-heading)"><?= e($r['nome']) ?><?= (int) $r['id'] === (int) $u['id'] ? ' (você)' : '' ?></b>
            <div class="lms-small lms-muted" style="font-size:12px"><?= number_format((int) $r['xp'], 0, ',', '.') ?> XP</div>
          </div>
          <span class="lms-small lms-muted" style="font-weight:700"><?= $i + 1 ?>º</span>
        </div>
        <?php endforeach; ?>
        <?php if ($rkTotal > 5): ?>
        <a class="av2-trip-item" href="/boticario/ranking" style="text-decoration:none; font-weight:700; color:var(--lms-primary); font-size:14px; justify-content:space-between">
          Ver mais <?= $rkTotal - 5 ?> <span>›</span>
        </a>
        <?php endif; ?>
      </div>
      <a class="lms-btn lms-btn-outline lms-btn-sm" style="margin-top:12px; width:100%; justify-content:center" href="/boticario/ranking">🏆 Ranking completo</a>
      <?php endif; ?>
    </aside>
  </div>
</main>

<script src="/boticario/assets/js/avatar2.js?v=<?= filemtime(__DIR__ . '/assets/js/avatar2.js') ?>"></script>
<script>
  (function () {
    const cfg = Avatar2.normaliza(<?= json_encode($cfgBase, JSON_UNESCAPED_UNICODE) ?>);
    const banner = document.getElementById('av2-banner');
    banner.style.background = Avatar2.CAT.FUNDO[cfg.fundo];
    banner.insertAdjacentHTML('afterbegin', Avatar2.render(cfg, { tamanho: 235 }));
  })();

  // festinha local do COMEMORE (só diversão — nada é gravado)
  function av2Comemora(btn) {
    const box = btn.closest('.av2-feed-item');
    box.style.position = 'relative';
    const emojis = ['🎉', '🎊', '⭐', '🥳', '✨'];
    for (let i = 0; i < 7; i++) {
      const s = document.createElement('span');
      s.className = 'av2-conf';
      s.textContent = emojis[i % emojis.length];
      s.style.left = (10 + Math.random() * 80) + '%';
      s.style.bottom = '20px';
      s.style.animationDelay = (Math.random() * .25) + 's';
      box.appendChild(s);
      setTimeout(() => s.remove(), 1300);
    }
    btn.textContent = '🎉 COMEMOROU!';
    btn.disabled = true;
    btn.style.opacity = .6;
  }
</script>
</body></html>
