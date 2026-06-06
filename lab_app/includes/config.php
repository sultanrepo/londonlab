<?php
// ================================================================
// Lab App Config — connects to the correct lab database
// ================================================================
require_once __DIR__ . '/../../master/config.php';

$labDb = getLabDb();

if (session_status() === PHP_SESSION_NONE) {
    if (ob_get_level() === 0) ob_start();
    session_start();
}

// ── RESOLVE WHICH LAB THIS REQUEST IS FOR ──
// From session (after login) or from URL ?lab=slug
function resolveLabSlug(): ?string {
    if (!empty($_SESSION['lab_slug'])) return $_SESSION['lab_slug'];
    if (!empty($_GET['lab']))           return preg_replace('/[^a-z0-9_]/', '', $_GET['lab']);
    return null;
}

function getLabDb(): ?LabDB {
    $slug = resolveLabSlug();
    if (!$slug) return null;
    $master = MasterDB::getInstance();
    $lab    = $master->fetch("SELECT db_name, status FROM labs WHERE slug=?", [$slug]);
    if (!$lab) return null;
    if ($lab['status'] === 'suspended') return null;
    try {
        return LabDB::getInstance($lab['db_name']);
    } catch (Exception $e) {
        return null;
    }
}

function getLabInfo(): ?array {
    $slug = resolveLabSlug();
    if (!$slug) return null;
    return MasterDB::getInstance()->fetch("
        SELECT l.*, p.name as plan_name, p.max_users, p.max_patients_per_month,
               p.features as plan_features
        FROM labs l LEFT JOIN plans p ON l.plan_id=p.id
        WHERE l.slug=?
    ", [$slug]);
}

// ── AUTH ──
function labIsLoggedIn(): bool {
    return isset($_SESSION['lab_user_id']) && !empty($_SESSION['lab_user_id']);
}

function labRequireLogin(): void {
    if (!labIsLoggedIn()) {
        $slug = resolveLabSlug();
        $url  = LAB_APP_URL . '/login.php' . ($slug ? '?lab='.$slug : '');
        header('Location: ' . $url);
        exit;
    }
}

function labCurrentUser(): ?array {
    if (!labIsLoggedIn()) return null;
    return [
        'id'    => $_SESSION['lab_user_id'],
        'name'  => $_SESSION['lab_user_name'],
        'email' => $_SESSION['lab_user_email'],
        'role'  => $_SESSION['lab_user_role'],
        'slug'  => $_SESSION['lab_slug'],
    ];
}

function labLogin(string $slug, string $email, string $password): array {
    $master = MasterDB::getInstance();
    $lab    = $master->fetch("SELECT * FROM labs WHERE slug=?", [$slug]);
    if (!$lab) return ['success'=>false,'message'=>'Lab not found.'];
    if ($lab['status'] === 'suspended') return ['success'=>false,'message'=>'This lab account is suspended. Please contact support.'];
    if ($lab['status'] === 'cancelled') return ['success'=>false,'message'=>'This lab account has been cancelled.'];

    // Check trial/subscription expiry
    if ($lab['status'] === 'trial' && $lab['trial_ends_at'] && strtotime($lab['trial_ends_at']) < time()) {
        return ['success'=>false,'message'=>'Your trial has expired. Please contact support to activate your subscription.'];
    }
    if ($lab['status'] === 'active' && $lab['subscription_ends_at'] && strtotime($lab['subscription_ends_at']) < time()) {
        $master->execute("UPDATE labs SET status='suspended' WHERE id=?", [$lab['id']]);
        return ['success'=>false,'message'=>'Your subscription has expired. Please renew to continue.'];
    }

    try {
        $db   = LabDB::getInstance($lab['db_name']);
        $user = $db->fetch("SELECT * FROM users WHERE email=? AND is_active=1", [$email]);
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success'=>false,'message'=>'Invalid email or password.'];
        }
        $_SESSION['lab_user_id']    = $user['id'];
        $_SESSION['lab_user_name']  = $user['name'];
        $_SESSION['lab_user_email'] = $user['email'];
        $_SESSION['lab_user_role']  = $user['role'];
        $_SESSION['lab_slug']       = $slug;
        $_SESSION['lab_db']         = $lab['db_name'];
        $_SESSION['lab_name']       = $lab['name'];
        $db->execute("UPDATE users SET last_login=NOW() WHERE id=?", [$user['id']]);
        return ['success'=>true];
    } catch (Exception $e) {
        return ['success'=>false,'message'=>'Database error. Please try again.'];
    }
}

function labLogout(): void {
    $slug = $_SESSION['lab_slug'] ?? '';
    session_destroy();
    header('Location: ' . LAB_APP_URL . '/login.php' . ($slug ? '?lab='.$slug : ''));
    exit;
}

// ── HELPERS ──
function labHasRole(string ...$roles): bool { return in_array($_SESSION['lab_user_role']??'', $roles, true); }
function labIsAdmin(): bool  { return labHasRole('admin'); }
function labCanEdit(): bool  { return labHasRole('admin','receptionist'); }
function labCanReport(): bool{ return labHasRole('admin','technician'); }

function labClean(string $v): string { return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8'); }

function labCsrfToken(): string {
    if (empty($_SESSION['lab_csrf'])) $_SESSION['lab_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['lab_csrf'];
}

function labVerifyCsrf(): void {
    if (!hash_equals($_SESSION['lab_csrf']??'', $_POST['csrf_token']??'')) {
        http_response_code(403); die('CSRF validation failed.');
    }
}

function labSetFlash(string $type, string $msg): void { $_SESSION['lab_flash'] = ['type'=>$type,'message'=>$msg]; }
function labGetFlash(): ?array { $f=$_SESSION['lab_flash']??null; unset($_SESSION['lab_flash']); return $f; }

function labMoney(float $amount): string { return '₹' . number_format($amount,2); }

function labGeneratePatientId(LabDB $db): string {
    $last = $db->fetch("SELECT patient_id FROM patients ORDER BY id DESC LIMIT 1");
    if ($last) { preg_match('/(\d+)$/',$last['patient_id'],$m); $next=(int)($m[1]??0)+1; } else $next=1;
    return 'LL-'.date('Y').'-'.str_pad($next,4,'0',STR_PAD_LEFT);
}

function labGenerateOrderNo(LabDB $db): string {
    $last = $db->fetch("SELECT order_no FROM orders ORDER BY id DESC LIMIT 1");
    if ($last) { preg_match('/(\d+)$/',$last['order_no'],$m); $next=(int)($m[1]??0)+1; } else $next=1;
    return 'ORD-'.date('Y').'-'.str_pad($next,4,'0',STR_PAD_LEFT);
}

function labGetSetting(LabDB $db, string $key, string $default=''): string {
    $row = $db->fetch("SELECT setting_value FROM settings WHERE setting_key=?", [$key]);
    return $row ? $row['setting_value'] : $default;
}
