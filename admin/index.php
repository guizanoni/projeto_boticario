<?php
// ──────────────────────────────────────────────────────────────
// Skope LMS · Gestão de usuários.
// Admin: tudo (criar, editar, redefinir senha, zerar progresso, remover).
// Gestor: só criar e editar usuários (inclui treinamento ativo) — NÃO pode
// zerar progresso, excluir usuários, nem mexer em contas admin (anti-escalada).
// ──────────────────────────────────────────────────────────────
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';

$u = lms_gate(['gestor', 'admin']);
$ehAdmin = $u['papel'] === 'admin';

$papeisValidos = ['aluno' => 'Aluno (operador)', 'gestor' => 'Gestor', 'admin' => 'Admin'];
// Gestor só cria/edita ALUNOS (do próprio time); admin atribui qualquer papel.
$papeisAtribuiveis = $ehAdmin ? $papeisValidos : ['aluno' => 'Aluno (operador)'];
// lista de gestores (times) — o admin escolhe a qual time o aluno pertence.
$gestoresLista = db_all("SELECT id, nome FROM usuario WHERE cliente_id = ? AND papel = 'gestor' ORDER BY nome", [$u['cliente_id']]);

// treinamentos atribuíveis (ativos), agrupados por trilha — o admin
// define QUAL treinamento cada usuário vive (a trilha vem junto).
$treinamentos = db_all('SELECT t.id, t.nome, t.status, tr.nome AS trilha_nome
                        FROM treinamento t JOIN trilha tr ON tr.id = t.trilha_id
                        WHERE tr.cliente_id = ? ORDER BY tr.ordem, tr.id, t.ordem', [$u['cliente_id']]);
$treinIds = array_map(fn($t) => (int) $t['id'], array_filter($treinamentos, fn($t) => $t['status'] === 'ativo'));
$porTrilha = [];
foreach ($treinamentos as $t) if ($t['status'] === 'ativo') $porTrilha[$t['trilha_nome']][] = $t;

// ── Ações (POST) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    // alvo precisa ser do mesmo tenant
    $alvo = $id ? db_one('SELECT * FROM usuario WHERE id = ? AND cliente_id = ?', [$id, $u['cliente_id']]) : null;

    if ($acao === 'salvar') {
        $nome  = trim((string) ($_POST['nome'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $papel = array_key_exists($_POST['papel'] ?? '', $papeisAtribuiveis) ? $_POST['papel'] : 'aluno';
        $senha = (string) ($_POST['senha'] ?? '');
        $trein = (int) ($_POST['treinamento_id'] ?? 0);
        $trein = in_array($trein, $treinIds, true) ? $trein : null;   // null = sem treinamento ativo
        // 2ª trilha (opcional): o aluno escolhe entre as duas ao entrar.
        $trein2 = (int) ($_POST['treinamento_id_2'] ?? 0);
        $trein2 = ($trein2 && in_array($trein2, $treinIds, true) && $trein2 !== $trein) ? $trein2 : null;

        // gestor dono (time): admin escolhe; gestor sempre atribui a si mesmo.
        if ($ehAdmin) {
            $gestorId = (int) ($_POST['gestor_id'] ?? 0);
            $gestorId = ($gestorId && db_one("SELECT 1 AS x FROM usuario WHERE id = ? AND cliente_id = ? AND papel = 'gestor'", [$gestorId, $u['cliente_id']])) ? $gestorId : null;
        } else {
            $gestorId = (int) $u['id'];
        }
        $gestorId = $papel === 'aluno' ? $gestorId : null;   // só aluno tem time

        if (!$ehAdmin && $alvo && ($alvo['papel'] !== 'aluno' || (int) $alvo['gestor_id'] !== (int) $u['id'])) {
            lms_flash_set(false, 'Você só pode editar alunos da sua própria equipe.');
        } elseif (!$ehAdmin && $alvo && $alvo['papel'] === 'admin') {
            lms_flash_set(false, 'Apenas um admin pode editar uma conta de admin.');
        } elseif ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            lms_flash_set(false, 'Preencha nome e um e-mail válido.');
        } elseif (db_one('SELECT 1 AS x FROM usuario WHERE email = ? AND id != ?', [$email, $id])) {
            lms_flash_set(false, 'Já existe um usuário com este e-mail.');
        } elseif (!$alvo && strlen($senha) < 4) {
            lms_flash_set(false, 'Defina uma senha com ao menos 4 caracteres para o novo usuário.');
        } elseif ($senha !== '' && strlen($senha) < 4) {
            lms_flash_set(false, 'A nova senha precisa ter ao menos 4 caracteres.');
        } elseif ($alvo && (int) $alvo['id'] === (int) $u['id'] && $papel !== 'admin') {
            lms_flash_set(false, 'Você não pode rebaixar o próprio papel.');
        } else {
            if ($alvo) {
                db_run('UPDATE usuario SET nome = ?, email = ?, papel = ?, treinamento_id = ?, treinamento_id_2 = ?, gestor_id = ? WHERE id = ?', [$nome, $email, $papel, $trein, $trein2, $gestorId, $id]);
                if ($senha !== '') db_run('UPDATE usuario SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $id]);
                lms_flash_set(true, "Usuário {$nome} atualizado.");
            } else {
                db_run('INSERT INTO usuario (cliente_id, nome, email, senha_hash, papel, treinamento_id, treinamento_id_2, gestor_id) VALUES (?,?,?,?,?,?,?,?)',
                       [$u['cliente_id'], $nome, $email, password_hash($senha, PASSWORD_DEFAULT), $papel, $trein, $trein2, $gestorId]);
                lms_flash_set(true, "Usuário {$nome} criado.");
            }
        }
    } elseif ($acao === 'zerar' && $alvo) {
        if (!$ehAdmin) {
            lms_flash_set(false, 'Apenas um admin pode zerar o progresso de um usuário.');
        } else {
            db_run('DELETE FROM progresso_usuario WHERE usuario_id = ?', [$id]);
            db_run('DELETE FROM conquista_usuario WHERE usuario_id = ?', [$id]);
            db_run('UPDATE usuario SET avatar_genero = NULL, avatar_cor_pele = NULL WHERE id = ?', [$id]);
            lms_flash_set(true, "Progresso de {$alvo['nome']} zerado — jornada recomeça do onboarding.");
        }
    } elseif ($acao === 'excluir' && $alvo) {
        if (!$ehAdmin) {
            lms_flash_set(false, 'Apenas um admin pode excluir usuários.');
        } elseif ((int) $alvo['id'] === (int) $u['id']) {
            lms_flash_set(false, 'Você não pode excluir a si mesmo.');
        } else {
            db_run('DELETE FROM progresso_usuario WHERE usuario_id = ?', [$id]);
            db_run('DELETE FROM conquista_usuario WHERE usuario_id = ?', [$id]);
            db_run('DELETE FROM usuario WHERE id = ?', [$id]);
            lms_flash_set(true, "Usuário {$alvo['nome']} removido.");
        }
    }
    header('Location: /boticario/admin/'); exit;
}

// ── Dados da tela ────────────────────────────────────────────
$editando = null;
if (!empty($_GET['editar'])) {
    $editando = db_one('SELECT * FROM usuario WHERE id = ? AND cliente_id = ?', [(int) $_GET['editar'], $u['cliente_id']]);
}

// gestor enxerga só a própria equipe (alunos com gestor_id = ele); admin vê tudo.
$escopoTime = $ehAdmin ? '' : " AND us.papel = 'aluno' AND us.gestor_id = " . (int) $u['id'];
// progresso de cada usuário é relativo ao SEU treinamento ativo
$usuarios = db_all("SELECT us.*, t.nome AS trein_nome, tr.nome AS trilha_nome,
        g.nome AS gestor_nome,
        (SELECT COUNT(*) FROM progresso_usuario p JOIN etapa e ON e.id = p.etapa_id JOIN fase f ON f.id = e.fase_id
         WHERE p.usuario_id = us.id AND p.status = 'concluido' AND f.treinamento_id = us.treinamento_id) AS etapas_feitas,
        (SELECT COUNT(*) FROM etapa e JOIN fase f ON f.id = e.fase_id
         WHERE f.treinamento_id = us.treinamento_id) AS etapas_total,
        (SELECT COUNT(*) FROM comentario c WHERE c.usuario_id = us.id AND c.status = 'ativo') AS n_coment
    FROM usuario us
    LEFT JOIN treinamento t ON t.id = us.treinamento_id
    LEFT JOIN trilha tr ON tr.id = t.trilha_id
    LEFT JOIN usuario g ON g.id = us.gestor_id
    WHERE us.cliente_id = ?{$escopoTime} ORDER BY us.papel != 'admin', us.nome", [$u['cliente_id']]);

$flash = lms_flash_get();
$papelPill = [
    'admin'  => '<span class="lms-pill is-primary">Admin</span>',
    'gestor' => '<span class="lms-pill is-warning">Gestor</span>',
    'aluno'  => '<span class="lms-pill">Aluno</span>',
];

lms_head('Gestão de usuários', $u);
lms_topbar($u);
?>
<main class="lms-container" style="padding-top:28px; padding-bottom:64px">

  <div class="lms-row" style="justify-content:space-between; align-items:flex-end">
    <div>
      <h1 style="font-size:var(--lms-title-md)">Gestão de usuários</h1>
      <p class="lms-muted lms-small" style="margin-top:4px"><?= e($u['cliente_nome']) ?> · <?= count($usuarios) ?> usuários</p>
    </div>
    <button type="button" class="lms-btn lms-btn-primary" id="btn-novo-usuario">+ Novo usuário</button>
  </div>

  <?php if ($flash): ?>
  <div class="lms-alert <?= $flash['ok'] ? 'is-success' : 'is-error' ?> lms-pop" style="margin-top:18px">
    <?= $flash['ok'] ? '✅' : '⚠️' ?> <div><?= e($flash['msg']) ?></div>
  </div>
  <?php endif; ?>

  <?php lms_busca('.lms-table tbody tr', 'Buscar em tudo: nome, e-mail, papel, treinamento, data…', count($usuarios), 'usuários', 'u-busca'); ?>

  <!-- lista -->
  <style>
    .lms-btn-sm.lms-btn-ico { padding:6px 9px; font-size:15px; line-height:1 }
    /* tabela orgânica e elegante */
    .lms-table th, .lms-table td { padding:12px 14px; vertical-align:middle }
    .lms-table thead th { text-transform:uppercase; letter-spacing:.04em; font-size:11px;
      font-weight:700; color:var(--lms-muted); background:transparent; border-bottom:1px solid rgba(30,37,67,.1) }
    .lms-table tbody td { border-top-color:rgba(30,37,67,.07) }
    .lms-table tbody tr { transition:background .12s ease }
    .lms-table tbody tr:hover td { background:rgba(41,173,220,.05) }
    .lms-table .lms-progress { border-radius:999px }
    /* ações agrupadas, com respiro entre os ícones */
    .u-acoes { display:inline-flex; gap:6px; align-items:center; justify-content:flex-end }
  </style>
  <div class="lms-table-wrap" style="margin-top:14px">
    <table class="lms-table">
      <thead><tr><th>Usuário</th><th>Papel</th><th>Treinamento ativo</th><th style="min-width:150px">Progresso</th><th>Engajamento</th><th>Último acesso</th><th style="text-align:right">Ações</th></tr></thead>
      <tbody>
        <?php $engInfo = lms_engaj_info();
        foreach ($usuarios as $x): $pct = lms_pct((int) $x['etapas_feitas'], (int) $x['etapas_total']); ?>
        <tr>
          <td>
            <div class="lms-row" style="gap:10px; flex-wrap:nowrap">
              <?= lms_avatar_html($x['avatar_genero'], $x['avatar_cor_pele'], 34, $x['foto_url'] ?? null) ?>
              <div><strong style="font-family:var(--lms-font-ui); font-weight:600"><?= e($x['nome']) ?></strong>
                <?= (int) $x['id'] === (int) $u['id'] ? '<span class="lms-pill" style="margin-left:6px">você</span>' : '' ?><br>
                <span class="lms-muted" style="font-size:12px"><?= e($x['email']) ?></span>
                <?php if ($ehAdmin && $x['papel'] === 'aluno'): ?><br><span class="lms-muted" style="font-size:11.5px">🧑‍🤝‍🧑 <?= $x['gestor_nome'] ? e($x['gestor_nome']) : 'sem time' ?></span><?php endif; ?></div>
            </div>
          </td>
          <td><?= $papelPill[$x['papel']] ?? e($x['papel']) ?></td>
          <td class="lms-small">
            <?php if ($x['trein_nome']): ?>
            <strong style="font-family:var(--lms-font-ui); font-weight:600"><?= e($x['trein_nome']) ?></strong><br>
            <span class="lms-muted" style="font-size:11.5px">🧭 <?= e($x['trilha_nome']) ?></span>
            <?php else: ?><span class="lms-pill is-warning">sem treinamento</span><?php endif; ?>
          </td>
          <td>
            <?php if ($x['papel'] === 'gestor'): ?><span class="lms-muted lms-small">—</span>
            <?php else: ?>
            <div class="lms-row" style="gap:8px; flex-wrap:nowrap">
              <div class="lms-progress <?= $pct === 100 ? 'is-success' : '' ?>" style="height:10px; flex:1">
                <div class="lms-progress-fill" style="width:<?= $pct ?>%; <?= (int) $x['etapas_feitas'] === 0 ? 'min-width:0' : '' ?>"></div>
              </div>
              <span class="lms-small lms-muted"><?= $pct ?>%</span>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($x['papel'] === 'aluno'):
              $eg = lms_engajamento($pct, (int) ($x['acessos'] ?? 0), (int) ($x['n_coment'] ?? 0));
              [$egLbl, $egIco, $egCls] = $engInfo[$eg['nivel']]; ?>
            <span class="lms-pill <?= $egCls ?>" title="<?= e(lms_engaj_tip($eg)) ?>"><?= $egIco ?> <?= $egLbl ?></span>
            <?php else: ?><span class="lms-muted lms-small">—</span><?php endif; ?>
          </td>
          <td class="lms-muted lms-small"><?= $x['ultimo_login'] ? e(date('d/m/Y H:i', strtotime($x['ultimo_login']))) : 'nunca' ?></td>
          <td style="text-align:right; white-space:nowrap">
            <div class="u-acoes">
            <?php if ($x['papel'] === 'aluno'): ?>
            <a class="lms-btn lms-btn-outline lms-btn-sm lms-btn-ico" href="/boticario/gestor/?aluno=<?= (int) $x['id'] ?>" title="Ver report do aluno">👁</a>
            <?php endif; ?>
            <?php if ($ehAdmin || $x['papel'] !== 'admin'): ?>
            <a class="lms-btn lms-btn-outline lms-btn-sm lms-btn-ico" href="/boticario/admin/?editar=<?= (int) $x['id'] ?>#form-usuario" title="Editar usuário">✏️</a>
            <?php endif; ?>
            <?php if ($ehAdmin && $x['papel'] !== 'gestor'): ?>
            <form method="post" style="display:inline"
                  data-confirmar-titulo="Zerar o progresso de <?= e($x['nome']) ?>?"
                  data-confirmar="A jornada recomeça do zero." data-confirmar-perigo>
              <input type="hidden" name="acao" value="zerar"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
              <button class="lms-btn lms-btn-outline lms-btn-sm lms-btn-ico" title="Zerar progresso">↺</button>
            </form>
            <?php endif; ?>
            <?php if ($ehAdmin && (int) $x['id'] !== (int) $u['id']): ?>
            <form method="post" style="display:inline"
                  data-confirmar-titulo="Excluir <?= e($x['nome']) ?>?"
                  data-confirmar="Progresso e conquistas serão apagados. Essa ação não tem volta." data-confirmar-perigo>
              <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
              <button class="lms-btn lms-btn-outline lms-btn-sm lms-btn-ico" style="color:var(--lms-error); border-color:var(--lms-error)" title="Excluir usuário">🗑</button>
            </form>
            <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="lms-small lms-muted" style="margin-top:14px">💡 Importação de alunos em lote (CSV) entra no fast-follow (RF-14).</p>
</main>

<?php lms_popup_abre('fu', $editando ? '✏️ Editar: ' . e($editando['nome']) : '➕ Novo usuário', '', 760); ?>
  <form method="post">
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; align-items:end">
      <div>
        <label class="lms-label" for="u-nome">Nome</label>
        <input class="lms-input" id="u-nome" name="nome" required value="<?= e($editando['nome'] ?? '') ?>" placeholder="Maria Silva">
      </div>
      <div>
        <label class="lms-label" for="u-email">E-mail</label>
        <input class="lms-input" id="u-email" name="email" type="email" required value="<?= e($editando['email'] ?? '') ?>" placeholder="maria@boticario.com.br">
      </div>
      <div>
        <label class="lms-label" for="u-papel">Papel</label>
        <select class="lms-select" id="u-papel" name="papel">
          <?php foreach ($papeisAtribuiveis as $k => $lbl): ?>
          <option value="<?= e($k) ?>" <?= ($editando['papel'] ?? 'aluno') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="lms-label" for="u-senha" style="white-space:nowrap"><?= $editando ? 'Nova senha' : 'Senha' ?></label>
        <input class="lms-input" id="u-senha" name="senha" type="text" autocomplete="off" placeholder="<?= $editando ? 'deixe vazio p/ manter' : 'mín. 4 caracteres' ?>" <?= $editando ? '' : 'required' ?>>
      </div>
      <div>
        <label class="lms-label" for="u-trein" style="white-space:nowrap" title="O treinamento que este usuário vê ao logar — a trilha (mapa e narrativa) vem junto.">🗺️ Treinamento ativo</label>
        <select class="lms-select" id="u-trein" name="treinamento_id">
          <option value="0">— nenhum</option>
          <?php foreach ($porTrilha as $trilhaNome => $ts): ?>
          <optgroup label="<?= e($trilhaNome) ?>">
            <?php foreach ($ts as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= (int) ($editando['treinamento_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
            <?php endforeach; ?>
          </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="lms-label" for="u-trein2" style="white-space:nowrap" title="Trilha opcional: se preenchida, o aluno escolhe entre as duas ao entrar.">🗺️ 2ª trilha (opcional)</label>
        <select class="lms-select" id="u-trein2" name="treinamento_id_2">
          <option value="0">— nenhuma</option>
          <?php foreach ($porTrilha as $trilhaNome => $ts): ?>
          <optgroup label="<?= e($trilhaNome) ?>">
            <?php foreach ($ts as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= (int) ($editando['treinamento_id_2'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
            <?php endforeach; ?>
          </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($ehAdmin): ?>
      <div>
        <label class="lms-label" for="u-gestor" style="white-space:nowrap" title="A qual gestor/time este aluno pertence (só se aplica a alunos).">🧑‍🤝‍🧑 Gestor (time)</label>
        <select class="lms-select" id="u-gestor" name="gestor_id">
          <option value="0">— sem time</option>
          <?php foreach ($gestoresLista as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= (int) ($editando['gestor_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
    <div class="lms-row" style="margin-top:20px">
      <button class="lms-btn lms-btn-primary"><?= $editando ? 'Salvar alterações' : 'Criar usuário' ?></button>
      <button type="button" class="lms-btn lms-btn-outline" data-fecha="fu">Cancelar</button>
    </div>
  </form>
<?php lms_popup_fecha('fu', 'btn-novo-usuario', (bool) $editando, '/boticario/admin/'); ?>

</body></html>
