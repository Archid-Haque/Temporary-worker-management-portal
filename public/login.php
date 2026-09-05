<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/site.php';

$site = site_settings();

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? '');

    if (login_user($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        audit((int)$_SESSION['user_id'], 'login', 'auth', null);
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid email or password.';
}

$siteName = trim((string)($site['site_name'] ?? 'WorkerLedger'));
$siteLogo = trim((string)($site['site_logo'] ?? ''));

if ($siteName === '') {
    $siteName = 'WorkerLedger';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title><?= htmlspecialchars($siteName) ?> — Sign in</title>

<link rel="stylesheet" href="assets/style.css?v=4">

<style>
.auth-brand{
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:58px;
    margin-bottom:14px;
}

.auth-brand-logo{
    display:block;
    width:300px;
    height:58px;
    max-width:80%;
    object-fit:contain;
    object-position:center;
    background:transparent;
    padding:0;
    border:0;
    border-radius:0;
}

.auth-brand-name{
    font-size:30px;
    font-weight:800;
    color:var(--ink);
    letter-spacing:-.5px;
}

@media(max-width:650px){
    .auth-brand-logo{
        width:220px;
        height:48px;
    }

    .auth-brand-name{
        font-size:26px;
    }
}
</style>
</head>

<body class="auth-page">

<div class="auth-card">

  <div class="auth-brand">
    <?php if ($siteLogo): ?>
      <img
        src="<?= htmlspecialchars($siteLogo) ?>"
        alt="<?= htmlspecialchars($siteName) ?>"
        class="auth-brand-logo"
      >
    <?php else: ?>
      <div class="auth-brand-name">
        <?= htmlspecialchars($siteName) ?>
      </div>
    <?php endif; ?>
  </div>

  <p class="muted">Work, attendance & payment records</p>

  <?php if ($error): ?>
    <div class="alert danger">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="post">

    <input
      type="hidden"
      name="csrf"
      value="<?= htmlspecialchars(csrf_token()) ?>"
    >

    <label>
      Email
      <input
        type="email"
        name="email"
        required
        autocomplete="email"
      >
    </label>

    <label>
      Password
      <input
        type="password"
        name="password"
        required
        autocomplete="current-password"
      >
    </label>

    <button class="primary full" type="submit">
      Sign in
    </button>

  </form>

  <p class="tiny">
    Secure company record-keeping portal.
    Not a payment service or legal authority.
  </p>

</div>

<footer class="auth-footer">
  <?php if ($siteLogo): ?>
    <strong><?= htmlspecialchars($siteName) ?></strong>
  <?php else: ?>
    <strong><?= htmlspecialchars($siteName) ?></strong>
  <?php endif; ?>

  <span>Secure work & payment record-keeping</span>
  <span>© <?= date('Y') ?></span>
</footer>

</body>
</html>