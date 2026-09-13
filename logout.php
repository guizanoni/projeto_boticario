<?php
require __DIR__ . '/_lib/auth.php';
lms_logout();
header('Location: /boticario/login');
exit;
