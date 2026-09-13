<?php
// Skope LMS · Perfil do aluno: avatar, XP e conquistas (RF-03, RF-09)
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate(['aluno']);

// ── Edição do avatar e foto de perfil ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $uid = (int) $u['id'];

    if ($acao === 'avatar_salvar') {
        $gen  = in_array($_POST['genero'] ?? '', ['m', 'f'], true) ? $_POST['genero'] : 'm';
        $pele = array_key_exists($_POST['pele'] ?? '', lms_tons_pele()) ? $_POST['pele'] : '2';
        db_run('UPDATE usuario SET avatar_genero = ?, avatar_cor_pele = ? WHERE id = ?', [$gen, $pele, $uid]);
        lms_flash_set(true, 'Avatar atualizado!');

    } elseif ($acao === 'foto_enviar') {
        $f = $_FILES['foto'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            lms_flash_set(false, 'Escolha uma imagem para enviar.');
        } elseif ($f['size'] > 8 * 1024 * 1024) {
            lms_flash_set(false, 'Foto muito grande (máx. 8 MB).');
        } else {
            $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
            if (!$img) {
                lms_flash_set(false, 'Formato não reconhecido — use JPG, PNG ou WebP.');
            } else {
                // recorte central quadrado + 256px (GD)
                $w = imagesx($img); $h = imagesy($img); $lado = min($w, $h);
                $quad = imagecreatetruecolor(256, 256);
                imagecopyresampled($quad, $img, 0, 0, (int) (($w - $lado) / 2), (int) (($h - $lado) / 2), 256, 256, $lado, $lado);
                $dir = __DIR__ . '/data/fotos';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                imagejpeg($quad, "$dir/$uid.jpg", 88);
                db_run('UPDATE usuario SET foto_url = ? WHERE id = ?', ['/boticario/foto?u=' . $uid . '&v=' . time(), $uid]);
                lms_flash_set(true, 'Foto de perfil atualizada! 📸');
            }
        }

    } elseif ($acao === 'foto_remover') {
        @unlink(__DIR__ . '/data/fotos/' . $uid . '.jpg');
        db_run('UPDATE usuario SET foto_url = NULL WHERE id = ?', [$uid]);
        lms_flash_set(true, 'Foto removida — de volta ao avatar.');
    }
    header('Location: /boticario/perfil'); exit;
}
$flash = lms_flash_get();
$j = lms_jornada($u);
$xp = lms_xp((int) $u['id']);

$badges = db_all('SELECT b.*, c.ganho_em FROM badge b
                  LEFT JOIN conquista_usuario c ON c.badge_id = b.id AND c.usuario_id = ?
                  WHERE b.cliente_id = ? ORDER BY c.ganho_em IS NULL, b.id', [$u['id'], $u['cliente_id']]);

$totalEtapas = array_sum(array_column($j['fases'], 'total'));
$feitas      = array_sum(array_column($j['fases'], 'feitas'));

$stats = db_one('SELECT COALESCE(SUM(acertos),0) AS a, COALESCE(SUM(total),0) AS t, COALESCE(SUM(tempo_seg),0) AS s
                 FROM progresso_usuario WHERE usuario_id = ? AND status = \'concluido\'', [$u['id']]);
$pctAcerto = (int) $stats['t'] > 0 ? round($stats['a'] / $stats['t'] * 100) : 0;

// Friends (3Fs): ranking da equipe e posição do aluno
$ranking = lms_ranking((int) $u['cliente_id']);
[$rkPos, $rkTotal, $rkMedia, $meuXp] = lms_posicao($ranking, (int) $u['id']);
$aFrente = max(0, $rkTotal - $rkPos);   // quantos colegas o aluno está à frente
$maxXp = max(1, (int) ($ranking[0]['xp'] ?? 1), $rkMedia);

lms_head('Meu perfil', $u);
lms_topbar($u);
?>
<main class="lms-container" style="max-width:860px; padding-top:28px; padding-bottom:64px">
  <a class="lms-small" href="/boticario/mapa">← Voltar ao mapa</a>

  <?php if ($flash): ?>
  <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:14px">
    <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div>
  </div>
  <?php endif; ?>

  <div class="lms-card-pastel is-sky lms-row" style="gap:28px; margin-top:14px; padding:32px">
    <?= lms_avatar_html($u['avatar_genero'], $u['avatar_cor_pele'], 110, $u['foto_url']) ?>
    <div style="flex:1; min-width:220px">
      <h1 style="font-size:var(--lms-title-md)"><?= e($u['nome']) ?></h1>
      <div class="lms-row" style="margin-top:10px">
        <span class="lms-xp"><span class="lms-xp-coin"></span> <?= number_format($xp, 0, ',', '.') ?> XP</span>
      </div>
      <div style="margin-top:16px; max-width:360px">
        <div class="lms-row" style="justify-content:space-between; margin-bottom:6px">
          <span class="lms-small lms-muted">Jornada</span>
          <span class="lms-small" style="font-weight:600"><?= $feitas ?>/<?= $totalEtapas ?> etapas</span>
        </div>
        <div class="lms-progress"><div class="lms-progress-fill" style="width:<?= lms_pct($feitas, $totalEtapas) ?>%"></div></div>
      </div>
    </div>
    <div class="lms-stat lms-center" style="min-width:130px">
      <div class="lms-stat-value"><?= $pctAcerto ?>%</div>
      <div class="lms-stat-label">acerto geral</div>
    </div>
  </div>

  <!-- ── editar avatar e foto ─────────────────────────────── -->
  <details class="lms-card" style="margin-top:14px; padding:20px 24px">
    <summary style="cursor:pointer; font-family:var(--lms-font-ui); font-weight:700">✏️ Editar avatar e foto</summary>
    <div class="lms-row" style="gap:28px; margin-top:18px; align-items:flex-start; flex-wrap:wrap">

      <form method="post" class="lms-stack-2" style="flex:1; min-width:250px">
        <input type="hidden" name="acao" value="avatar_salvar">
        <p class="lms-small" style="font-weight:700; font-family:var(--lms-font-ui)">Personagem</p>
        <div class="lms-row" style="gap:8px">
          <label class="lms-check" style="flex:1; justify-content:center"><input type="radio" name="genero" value="m" <?= ($u['avatar_genero'] ?? 'm') !== 'f' ? 'checked' : '' ?>> 👨 Masculino</label>
          <label class="lms-check" style="flex:1; justify-content:center"><input type="radio" name="genero" value="f" <?= ($u['avatar_genero'] ?? '') === 'f' ? 'checked' : '' ?>> 👩 Feminino</label>
        </div>
        <p class="lms-small" style="font-weight:700; font-family:var(--lms-font-ui); margin-top:8px">Tom de pele</p>
        <div class="lms-row" style="gap:8px">
          <?php foreach (lms_tons_pele() as $k => $cor): ?>
          <label title="Tom <?= e($k) ?>" style="cursor:pointer">
            <input type="radio" name="pele" value="<?= e($k) ?>" <?= ($u['avatar_cor_pele'] ?? '2') === $k ? 'checked' : '' ?> style="display:none">
            <span style="display:block; width:34px; height:34px; border-radius:50%; background:<?= e($cor) ?>; border:3px solid <?= ($u['avatar_cor_pele'] ?? '2') === $k ? 'var(--lms-primary)' : 'rgba(31,42,37,.3)' ?>"></span>
          </label>
          <?php endforeach; ?>
        </div>
        <p class="lms-small lms-muted">O personagem aparece no mapa e nas listas quando você não tem foto.</p>
        <button class="lms-btn lms-btn-primary lms-btn-sm" style="align-self:flex-start">Salvar personagem</button>
      </form>

      <div class="lms-stack-2" style="flex:1; min-width:250px">
        <p class="lms-small" style="font-weight:700; font-family:var(--lms-font-ui)">Foto de perfil</p>
        <form method="post" enctype="multipart/form-data" class="lms-row" style="gap:10px">
          <input type="hidden" name="acao" value="foto_enviar">
          <input class="lms-input" type="file" name="foto" accept="image/*" required style="flex:1; padding:9px">
          <button class="lms-btn lms-btn-primary lms-btn-sm">Enviar 📸</button>
        </form>
        <?php if ($u['foto_url']): ?>
        <form method="post">
          <input type="hidden" name="acao" value="foto_remover">
          <button class="lms-btn lms-btn-outline lms-btn-sm">Remover foto (voltar ao avatar)</button>
        </form>
        <?php endif; ?>
        <p class="lms-small lms-muted">A foto substitui o avatar no topo, no perfil e no ranking. No mapa, quem caminha é sempre o seu personagem. 😉</p>
      </div>
    </div>
  </details>

  <?php if ($rkTotal > 1): ?>
  <h2 id="equipe" style="font-size:var(--lms-title-sm); margin:32px 0 16px; font-family:var(--lms-font-ui); font-weight:600">🧭 Você e a equipe</h2>
  <div class="lms-card-game" style="padding:26px">

    <!-- comparação com a média (o termômetro do "onde eu estou") -->
    <div class="lms-row" style="justify-content:space-between; margin-bottom:6px">
      <span class="lms-small" style="font-weight:700">Você · <?= number_format($meuXp, 0, ',', '.') ?> XP</span>
      <span class="lms-pill <?= $meuXp >= $rkMedia ? 'is-success' : 'is-warning' ?>">
        <?= $meuXp >= $rkMedia ? '▲ acima da média' : '▼ abaixo da média' ?>
      </span>
    </div>
    <div class="lms-progress" style="height:14px"><div class="lms-progress-fill" style="width:<?= round($meuXp / $maxXp * 100) ?>%"></div></div>
    <div class="lms-row" style="justify-content:space-between; margin:12px 0 6px">
      <span class="lms-small lms-muted">Média da equipe · <?= number_format($rkMedia, 0, ',', '.') ?> XP</span>
    </div>
    <div class="lms-progress" style="height:14px"><div class="lms-progress-fill" style="width:<?= round($rkMedia / $maxXp * 100) ?>%; background:var(--lms-ink-soft, #69727D)"></div></div>
    <div class="lms-row" style="justify-content:space-between; margin-top:14px; gap:10px; flex-wrap:wrap">
      <p class="lms-small lms-muted" style="flex:1; min-width:220px">
        Você é o <strong><?= $rkPos ?>º de <?= $rkTotal ?></strong> participantes<?= $aFrente > 0 ? ' — à frente de <strong>' . $aFrente . ' colega' . ($aFrente > 1 ? 's' : '') . '</strong>' : '' ?>.
        Evolua no seu ritmo: cada etapa concluída soma XP. 💪
      </p>
      <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/ranking">🏆 Ver ranking completo</a>
    </div>
  </div>
  <?php endif; ?>

  <h2 style="font-size:var(--lms-title-sm); margin:32px 0 16px; font-family:var(--lms-font-ui); font-weight:600">🏅 Conquistas</h2>
  <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:14px">
    <?php foreach ($badges as $b): $tem = (bool) $b['ganho_em']; ?>
    <div class="lms-card lms-center" style="padding:22px 14px; <?= $tem ? '' : 'opacity:.75' ?>">
      <span class="lms-badge <?= $tem ? '' : 'is-locked' ?>"><?= $tem ? e($b['icone']) : '🔒' ?></span>
      <p class="lms-small" style="font-weight:600; font-family:var(--lms-font-ui); margin-top:10px"><?= e($b['nome']) ?></p>
      <p style="font-size:12px; color:var(--lms-ink-soft); margin-top:2px"><?= e($b['descricao']) ?></p>
      <?php if ($tem): ?><p class="lms-pill is-success" style="margin-top:8px; display:inline-flex">conquistada</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <h2 style="font-size:var(--lms-title-sm); margin:32px 0 16px; font-family:var(--lms-font-ui); font-weight:600">📚 Minhas fases<?= $j['treinamento'] ? ' · ' . e($j['treinamento']['nome']) : '' ?></h2>
  <div class="lms-stack-2">
    <?php foreach ($j['fases'] as $f): ?>
    <div class="lms-card lms-row" style="justify-content:space-between; padding:18px 24px">
      <div>
        <h4><?= e($f['nome']) ?></h4>
        <p class="lms-small lms-muted">Fase <?= (int) $f['ordem'] ?> · <?= e($u['trilha_nome'] ?? '') ?></p>
      </div>
      <?php if ($f['estado'] === 'done'): ?><span class="lms-pill is-success">Concluída</span>
      <?php elseif ($f['feitas'] > 0): ?><span class="lms-pill is-warning">Em andamento · <?= $f['feitas'] ?>/<?= $f['total'] ?></span>
      <?php elseif ($f['estado'] === 'locked'): ?><span class="lms-pill">🔒 <?= e($f['motivo_bloqueio'] ?? 'Bloqueada') ?></span>
      <?php else: ?><span class="lms-pill">Não iniciada</span><?php endif; ?>
    </div>
    <?php endforeach; if (!$j['fases']): ?>
    <p class="lms-small lms-muted">Seu treinamento está sendo preparado — as fases aparecem aqui em breve. ⏳</p>
    <?php endif; ?>
  </div>
</main>
</body></html>
