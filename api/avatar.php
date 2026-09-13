<?php
// Renderiza o SVG do avatar (preview do onboarding).
require __DIR__ . '/../_lib/auth.php';
require __DIR__ . '/../_lib/helpers.php';
lms_gate_api();
header('Content-Type: image/svg+xml; charset=utf-8');
echo lms_avatar_svg(
    ($_GET['genero'] ?? 'm') === 'f' ? 'f' : 'm',
    array_key_exists($_GET['pele'] ?? '', lms_tons_pele()) ? $_GET['pele'] : '2',
    min(300, max(40, (int) ($_GET['size'] ?? 130)))
);
