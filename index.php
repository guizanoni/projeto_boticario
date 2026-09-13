<?php
// Skope LMS — roteia para a tela do papel do usuário (ou login).
require __DIR__ . '/_lib/auth.php';
$u = lms_user();
header('Location: ' . ($u ? lms_home($u) : '/boticario/login'));
exit;
