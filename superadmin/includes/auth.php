<?php
// ================================================================
// LondonLab Super Admin — Auth Helpers
// ================================================================
require_once __DIR__ . '/../../master/config.php';

if (session_status() === PHP_SESSION_NONE) {
    if (ob_get_level() === 0) ob_start();
    session_start();
}

function saIsLoggedIn(): bool {
    return isset($_SESSION['sa_id']) && !empty($_SESSION['sa_id']);
}

function saRequireLogin(): void {
    if (!saIsLoggedIn()) {
        header('Location: ' . SUPERADMIN_URL . '/login.php');
        exit;
    }
}

function saCurrentUser(): ?array {
    if (!saIsLoggedIn()) return null;
    return [
        'id'   => $_SESSION['sa_id'],
        'name' => $_SESSION['sa_name'],
        'email'=> $_SESSION['sa_email'],
        'role' => $_SESSION['sa_role'],
    ];
}

function saLogin(string $email, string $password): array {
    $db   = MasterDB::getInstance();
    $user = $db->fetch("SELECT * FROM super_admins WHERE email=? AND is_active=1", [$email]);
    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    $_SESSION['sa_id']    = $user['id'];
    $_SESSION['sa_name']  = $user['name'];
    $_SESSION['sa_email'] = $user['email'];
    $_SESSION['sa_role']  = $user['role'];
    $db->execute("UPDATE super_admins SET last_login=NOW() WHERE id=?", [$user['id']]);
    return ['success' => true];
}

function saLogout(): void {
    session_destroy();
    header('Location: ' . SUPERADMIN_URL . '/login.php');
    exit;
}

function saClean(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

function saCsrfToken(): string {
    if (empty($_SESSION['sa_csrf'])) $_SESSION['sa_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['sa_csrf'];
}

function saVerifyCsrf(): void {
    if (!hash_equals($_SESSION['sa_csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403); die('CSRF validation failed.');
    }
}

function saSetFlash(string $type, string $msg): void {
    $_SESSION['sa_flash'] = ['type' => $type, 'message' => $msg];
}

function saGetFlash(): ?array {
    $f = $_SESSION['sa_flash'] ?? null;
    unset($_SESSION['sa_flash']);
    return $f;
}

// Generate next invoice number
function generateInvoiceNo(): string {
    $db   = MasterDB::getInstance();
    $last = $db->fetch("SELECT invoice_no FROM billing_invoices ORDER BY id DESC LIMIT 1");
    if ($last) {
        preg_match('/(\d+)$/', $last['invoice_no'], $m);
        $next = (int)($m[1] ?? 0) + 1;
    } else {
        $next = 1;
    }
    return 'INV-' . date('Y') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}
