<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';

$count = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$message = '';

if ($count > 0) {
    exit('Setup is already completed. Delete setup.php from the server.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        $message = 'Use a name, valid email and password of at least 10 characters.';
    } else {
        $stmt = db()->prepare("INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,'admin')");
        $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
        audit((int)db()->lastInsertId(), 'initial_admin_created', 'user', (int)db()->lastInsertId());
        exit('Administrator created. Delete setup.php now, then sign in.');
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>WorkerLedger Setup</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="auth-page"><div class="auth-card">
<div class="logo">WL</div><h1>First-time setup</h1>
<?php if ($message): ?><div class="alert danger"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
<label>Administrator name<input name="name" required></label>
<label>Email<input type="email" name="email" required></label>
<label>Password<input type="password" name="password" minlength="10" required></label>
<button class="primary full">Create administrator</button>
</form>
</div></body></html>
