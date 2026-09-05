<?php
require_once __DIR__ . '/../app/auth.php';
if (current_user()) audit((int)$_SESSION['user_id'], 'logout', 'auth', null);
logout_user();
header('Location: index.php');
exit;
