<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Seleção de trilha. Quando o aluno tem duas trilhas
// (usuario.treinamento_id + treinamento_id_2), escolhe em qual entrar.
// Escolher a segunda apenas TROCA os dois valores — o treinamento_id
// segue sendo o "ativo" que o resto do app usa. Progresso preservado.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate();
// sem segunda trilha não há o que escolher
if (empty($u['treinamento_id_2'])) { header('Location: ' . lms_home($u)); exit; }

// escolha: t = id do treinamento
if (!empty($_GET['t'])) {
    $t = (int) $_GET['t'];
    if ($t === (int) $u['treinamento_id'] || $t === (int) $u['treinamento_id_2']) {
        if ($t === (int) $u['treinamento_id_2']) {
            db_run('UPDATE usuario SET treinamento_id = ?, treinamento_id_2 = ? WHERE id = ?',
                   [(int) $u['treinamento_id_2'], (int) $u['treinamento_id'], (int) $u['id']]);
        }
        $_SESSION['trilha_ok'] = true;
        header('Location: /boticario/mapa'); exit;
    }
}

$opcao = function (int $treinId) use ($u): ?array {
    return db_one("SELECT t.id AS trein_id, t.nome AS trein_nome, tr.nome AS trilha_nome,
                          tr.narrativa, tr.cenario_mapa_url,
                          (SELECT COUNT(*) FROM etapa e JOIN fase f ON f.id = e.fase_id WHERE f.treinamento_id = t.id) AS total,
                          (SELECT COUNT(*) FROM progresso_usuario p JOIN etapa e ON e.id = p.etapa_id JOIN fase f ON f.id = e.fase_id
                           WHERE p.usuario_id = ? AND p.status = 'concluido' AND f.treinamento_id = t.id) AS feitas
                   FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id WHERE t.id = ?", [(int) $u['id'], $treinId]);
};
$cards = array_values(array_filter([$opcao((int) $u['treinamento_id']), $opcao((int) $u['treinamento_id_2'])]));
if (count($cards) < 2) { $_SESSION['trilha_ok'] = true; header('Location: /boticario/mapa'); exit; }

lms_head('Escolha sua trilha', $u);
?>
<main class="esc-wrap">
  <div class="esc-topo">
    <?= lms_logo_icon(30) ?>
    <span class="esc-marca"><?= e($u['cliente_nome']) ?></span>
    <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/logout" style="margin-left:auto">Sair</a>
  </div>

  <div class="esc-head">
    <h1>Escolha sua trilha</h1>
    <p>Você tem duas jornadas liberadas. Toque na que quer viver agora — pode trocar depois.</p>
  </div>

  <div class="esc-cards">
    <?php foreach ($cards as $c):
      $pct = (int) $c['total'] > 0 ? (int) round($c['feitas'] / $c['total'] * 100) : 0;
      $bg = $c['cenario_mapa_url'] ? e($c['cenario_mapa_url']) : ''; ?>
    <a class="esc-card" href="/boticario/escolher?t=<?= (int) $c['trein_id'] ?>"
       style="<?= $bg ? "--bg:url('{$bg}')" : '' ?>">
      <div class="esc-card-art"></div>
      <div class="esc-card-corpo">
        <span class="esc-card-tag">Trilha</span>
        <h2><?= e($c['trilha_nome']) ?></h2>
        <p class="esc-card-trein"><?= e($c['trein_nome']) ?></p>
        <div class="esc-card-prog"><div class="esc-card-prog-fill" style="width:<?= $pct ?>%"></div></div>
        <span class="esc-card-pct"><?= $pct ?>% concluído</span>
        <span class="esc-card-cta">Entrar ▶</span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</main>

<style>
  body { background: var(--lms-bg); }
  .esc-wrap { max-width: 960px; margin: 0 auto; padding: 20px 20px 60px; min-height: 100vh; }
  .esc-topo { display: flex; align-items: center; gap: 10px; padding: 6px 0 26px; }
  .esc-marca { font-family: var(--lms-font-display, var(--lms-font-ui)); font-weight: 700; color: var(--lms-heading); }
  .esc-head { text-align: center; margin-bottom: 28px; }
  .esc-head h1 { font-size: var(--lms-title-md); color: var(--lms-heading); }
  .esc-head p { color: var(--lms-ink-soft); margin-top: 6px; }
  .esc-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 22px; }
  @media (max-width: 640px) { .esc-cards { grid-template-columns: 1fr; } }

  .esc-card {
    display: block; text-decoration: none; color: inherit;
    border: var(--lms-border-game); border-radius: var(--lms-radius-xl);
    overflow: hidden; background: var(--lms-white); box-shadow: var(--lms-shadow-crisp);
    transition: transform .15s var(--lms-ease, ease), box-shadow .15s ease;
  }
  .esc-card:hover { transform: translateY(-4px); box-shadow: var(--lms-shadow-soft); }
  .esc-card-art {
    height: 180px; background: var(--bg, linear-gradient(135deg, var(--lms-primary), var(--lms-accent, #2A4971)));
    background-size: cover; background-position: center;
  }
  .esc-card-corpo { padding: 18px 20px 22px; }
  .esc-card-tag {
    display: inline-block; font-family: var(--lms-font-ui); font-size: 10.5px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase; color: var(--lms-primary);
    background: var(--lms-sky); padding: 3px 10px; border-radius: 999px; margin-bottom: 8px;
  }
  .esc-card-corpo h2 { font-size: 22px; color: var(--lms-heading); line-height: 1.15; }
  .esc-card-trein { color: var(--lms-ink-soft); font-size: 13.5px; margin-top: 4px; }
  .esc-card-prog { height: 8px; border-radius: 999px; background: var(--lms-surface-2); overflow: hidden; margin: 14px 0 6px; }
  .esc-card-prog-fill { height: 100%; border-radius: 999px; background: var(--lms-primary); }
  .esc-card-pct { display: block; font-size: 12px; color: var(--lms-ink-soft); }
  .esc-card-cta {
    display: inline-block; margin-top: 14px; font-family: var(--lms-font-ui); font-weight: 700;
    color: var(--lms-primary-ink, #fff); background: var(--lms-primary);
    padding: 9px 20px; border-radius: var(--lms-radius-md);
  }
</style>
</body></html>
