<?php
ob_start();
require_once __DIR__ . '/../master/config.php';
require_once __DIR__ . '/includes/auth.php';

if (saIsLoggedIn()) { header('Location: ' . SUPERADMIN_URL . '/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = saLogin($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) { header('Location: ' . SUPERADMIN_URL . '/index.php'); exit; }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= PLATFORM_NAME ?> — Super Admin Login</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔬</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#f5f3ff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-wrap{max-width:420px;width:100%;background:#fff;border-radius:20px;padding:40px;box-shadow:0 20px 60px rgba(124,58,237,.12)}
.logo{width:56px;height:56px;background:#7c3aed;border-radius:14px;display:grid;place-items:center;font-size:26px;color:#fff;margin:0 auto 20px}
h2{text-align:center;font-size:22px;font-weight:700;margin-bottom:6px}
.subtitle{text-align:center;color:#64748b;font-size:13px;margin-bottom:28px}
.form-group{margin-bottom:18px}
.form-label{font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;display:block}
.input-wrap{position:relative}
.input-wrap .icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:16px}
.input-wrap input{width:100%;padding:11px 14px 11px 42px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;outline:none;transition:border .2s}
.input-wrap input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.1)}
.btn-login{width:100%;background:#7c3aed;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;transition:background .2s;margin-top:6px}
.btn-login:hover{background:#5b21b6}
.alert-error{background:#fee2e2;border-left:3px solid #ef4444;color:#991b1b;border-radius:8px;padding:12px 14px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.hint{background:#f5f3ff;border:1px solid #ddd6fe;border-radius:10px;padding:14px;margin-top:20px;font-size:12px;color:#6d28d9}
.hint strong{display:block;margin-bottom:4px}
.hint code{background:rgba(109,40,217,.1);padding:2px 6px;border-radius:4px}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="logo"><img src="/londonlab/superadmin/assets/logos/LondonLab_Logo.png" alt="Logo" height="60" ></div>
    <h2><?= PLATFORM_NAME ?></h2>
    <p class="subtitle">Super Admin Panel — Restricted Access</p>

    <?php if ($error): ?>
    <div class="alert-error"><i class="bi bi-exclamation-triangle-fill"></i><?= saClean($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <div class="input-wrap">
                <i class="bi bi-envelope icon"></i>
                <input type="email" name="email" required placeholder="admin@londonlab.com"
                       value="<?= saClean($_POST['email'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock icon"></i>
                <input type="password" name="password" required placeholder="Enter password">
            </div>
        </div>
        <button type="submit" class="btn-login">
            <i class="bi bi-shield-lock-fill me-2"></i>Sign In to Admin Panel
        </button>
    </form>

    <div class="hint">
        <strong>🔑 Default Credentials</strong>
        Email: <code>admin@londonlab.com</code> &nbsp; Password: <code>password</code>
    </div>
</div>
</body>
</html>
