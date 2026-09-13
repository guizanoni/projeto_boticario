<?php
// ──────────────────────────────────────────────────────────────
// Boticário LMS · Apresentação da trilha (onboarding da trilha).
// Mostrada 1× por trilha: para o usuário NOVO (logo após montar o
// avatar) traz a boas-vindas da plataforma + a trilha; para quem já
// tem avatar e entra numa trilha nova, só a apresentação da trilha.
// "Começar" marca a trilha como vista (usuario.trilhas_vistas) e leva
// ao mapa.
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/_lib/auth.php';
require __DIR__ . '/_lib/helpers.php';

$u = lms_gate();
if ($u['papel'] !== 'aluno' || empty($u['trilha_id'])) { header('Location: ' . lms_home($u)); exit; }

$vistas = json_decode((string) ($u['trilhas_vistas'] ?? ''), true) ?: [];
$tr = (int) $u['trilha_id'];

// "Começar": marca a trilha como vista e vai pro mapa.
if (isset($_GET['go'])) {
    if (!in_array($tr, $vistas, true)) {
        $vistas[] = $tr;
        db_run('UPDATE usuario SET trilhas_vistas = ? WHERE id = ?', [json_encode(array_values(array_unique($vistas))), (int) $u['id']]);
    }
    header('Location: /boticario/mapa'); exit;
}

$primeiraVez = count($vistas) === 0;   // novo na plataforma
$trein = db_one("SELECT t.nome, t.descricao,
                        (SELECT COUNT(*) FROM fase f WHERE f.treinamento_id = t.id) AS fases
                 FROM treinamento t WHERE t.id = ?", [(int) $u['treinamento_id']]);
$fasesLista = db_all("SELECT nome, ordem FROM fase WHERE treinamento_id = ? ORDER BY ordem", [(int) $u['treinamento_id']]);
$bg = $u['cenario_mapa_url'] ? e(lms_mapa_do_periodo($u['cenario_mapa_url'], 'dia', $tr)) : '';

lms_head('Apresentação', $u);
?>
<main class="apr-wrap">
  <div class="apr-topo">
    <?= lms_logo_icon(30) ?>
    <span class="apr-marca"><?= e($u['cliente_nome']) ?></span>
    <a class="lms-btn lms-btn-outline lms-btn-sm" href="/boticario/logout" style="margin-left:auto">Sair</a>
  </div>

  <div class="apr-hero" style="<?= $bg ? "--bg:url('{$bg}')" : '' ?>">
    <div class="apr-hero-tint"></div>
    <div class="apr-hero-txt">
      <span class="apr-kicker"><?= $primeiraVez ? '👋 Boas-vindas' : '🧭 Nova trilha' ?></span>
      <h1><?= e($u['trilha_nome']) ?></h1>
      <?php if ($u['trilha_narrativa']): ?><p class="apr-narr"><?= e($u['trilha_narrativa']) ?></p><?php endif; ?>
    </div>
  </div>

  <div class="apr-corpo">
    <?php if ($primeiraVez): ?>
    <div class="apr-bloco">
      <h2>👋 Bem-vindo à plataforma</h2>
      <p class="apr-desc">Aqui o treinamento é uma jornada, não uma lista de vídeos. Você avança por um mapa, no seu ritmo, e cada passo conta.</p>
      <ul class="apr-lista">
        <li><span class="apr-ico">🗺️</span><div><strong>Você vive um mapa.</strong> Cada pin é uma fase — clique para entrar e seu avatar caminha até lá.</div></li>
        <li><span class="apr-ico">✅</span><div><strong>Fases com leitura e minigames.</strong> Concluir uma libera a próxima e rende XP.</div></li>
        <li><span class="apr-ico">🏅</span><div><strong>Você coleciona conquistas.</strong> Cada avanço rende XP e badges — veja tudo no seu perfil.</div></li>
        <li><span class="apr-ico">🏆</span><div><strong>Equipe junta.</strong> Acompanhe o ranking e as conquistas dos colegas.</div></li>
      </ul>
    </div>
    <?php endif; ?>

    <div class="apr-bloco">
      <h2>📁 <?= e($trein['nome'] ?? 'Seu treinamento') ?></h2>
      <?php if (!empty($trein['descricao'])): ?><p class="apr-desc"><?= e($trein['descricao']) ?></p><?php endif; ?>
      <p class="apr-fases"><strong><?= (int) ($trein['fases'] ?? 0) ?></strong> fase<?= (int) ($trein['fases'] ?? 0) === 1 ? '' : 's' ?> pela frente — no seu ritmo.</p>
    </div>

    <?php if ($fasesLista): ?>
    <div class="apr-bloco">
      <h2>🧭 Sua jornada</h2>
      <p class="apr-desc" style="margin-bottom:14px">O caminho que você vai percorrer. As fases liberam na sequência, conforme você avança.</p>
      <ol class="apr-jornada">
        <?php foreach ($fasesLista as $f): ?>
        <li><span class="apr-jornada-n"><?= (int) $f['ordem'] ?></span><span class="apr-jornada-nome"><?= e($f['nome']) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php endif; ?>

    <a class="lms-btn lms-btn-primary lms-btn-lg apr-cta" href="/boticario/apresentacao?go=1"><?= $primeiraVez ? 'Começar ▶' : 'Continuar ▶' ?></a>
  </div>
</main>

<style>
  body { background: var(--lms-bg); }
  .apr-wrap { max-width: 760px; margin: 0 auto; padding: 18px 20px 64px; }
  .apr-topo { display: flex; align-items: center; gap: 10px; padding: 6px 0 22px; }
  .apr-marca { font-family: var(--lms-font-display, var(--lms-font-ui)); font-weight: 700; color: var(--lms-heading); }
  .apr-hero {
    position: relative; border-radius: var(--lms-radius-xl); overflow: hidden;
    min-height: 240px; display: flex; align-items: flex-end;
    background: var(--bg, linear-gradient(135deg, var(--lms-primary), var(--lms-accent, #0B3B2A))) center/cover;
    border: var(--lms-border-game); box-shadow: var(--lms-shadow-crisp);
  }
  .apr-hero-tint { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(8,12,24,.30) 0%, rgba(8,12,24,.55) 55%, rgba(8,12,24,.88) 100%); }
  .apr-hero-txt { position: relative; padding: 26px; color: #fff; text-shadow: 0 1px 12px rgba(0,0,0,.45); }
  .apr-hero-txt h1, .apr-hero-txt .apr-narr, .apr-hero-txt .apr-kicker { color: #fff; }
  .apr-kicker { display: inline-block; font-family: var(--lms-font-ui); font-weight: 700; font-size: 12px;
    letter-spacing: .06em; background: rgba(255,255,255,.22); padding: 5px 13px; border-radius: 999px; backdrop-filter: blur(4px); }
  .apr-hero-txt h1 { font-size: 30px; margin-top: 12px; line-height: 1.1; }
  .apr-narr { margin-top: 8px; max-width: 560px; opacity: .92; line-height: 1.5; }

  .apr-corpo { margin-top: 22px; }
  .apr-bloco { background: var(--lms-white); border: var(--lms-border-game); border-radius: var(--lms-radius-lg);
    padding: 20px 22px; margin-bottom: 16px; box-shadow: var(--lms-shadow-crisp-sm); }
  .apr-bloco h2 { font-size: 18px; color: var(--lms-heading); margin-bottom: 10px; }
  .apr-lista { list-style: none; display: flex; flex-direction: column; gap: 12px; margin-top: 12px; }
  .apr-lista li { display: flex; gap: 12px; align-items: flex-start; color: var(--lms-ink); line-height: 1.5; }
  .apr-ico { font-size: 20px; flex: none; line-height: 1.4; }
  .apr-jornada { list-style: none; display: flex; flex-direction: column; gap: 8px; }
  .apr-jornada li { display: flex; align-items: center; gap: 12px; padding: 10px 12px;
    border: 1px solid var(--lms-border); border-radius: var(--lms-radius-md); background: var(--lms-bg); }
  .apr-jornada-n { width: 26px; height: 26px; border-radius: 50%; flex: none; display: flex; align-items: center;
    justify-content: center; font-family: var(--lms-font-ui); font-weight: 800; font-size: 13px;
    background: var(--lms-surface-2); color: var(--lms-ink-soft); }
  .apr-jornada-nome { color: var(--lms-ink); font-weight: 600; }
  .apr-desc { color: var(--lms-ink-soft); line-height: 1.55; }
  .apr-fases { margin-top: 8px; color: var(--lms-ink); }
  .apr-cta { width: 100%; justify-content: center; margin-top: 4px; }
</style>
</body></html>
