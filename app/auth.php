<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(string $token): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid request token.');
    }
}

function current_user(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    if (empty($_SESSION['user_id'])) return $user = null;

    $stmt = db()->prepare("SELECT id, full_name, email, phone, role, status FROM users WHERE id=? LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user || $user['status'] !== 'active') {
        logout_user();
        return null;
    }
    return $user;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function require_role(array $roles): array {
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
    return $user;
}

function login_user(string $email, string $password): bool {
    $stmt = db()->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    return true;
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function audit(?int $actorId, string $action, string $entityType, ?int $entityId, array $details=[]): void {
    $stmt = db()->prepare("INSERT INTO audit_logs (actor_id,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?)");
    $stmt->execute([
        $actorId, $action, $entityType, $entityId,
        json_encode($details, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
