<?php
ob_start();
require_once __DIR__ . '/includes/config.php';

// Get lab slug from URL or session
$slug = preg_replace('/[^a-z0-9_]/', '', $_GET['lab'] ?? '');
$labInfo = null;
if ($slug) {
    $labInfo = MasterDB::getInstance()->fetch("SELECT * FROM labs WHERE slug=?", [$slug]);
}

if (labIsLoggedIn()) {
    header('Location: ' . LAB_APP_URL . '/index.php?lab=' . ($_SESSION['lab_slug'] ?? $slug));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginSlug = trim($_POST['lab_slug'] ?? $slug);
    $result    = labLogin($loginSlug, $_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) {
        header('Location: ' . LAB_APP_URL . '/index.php?lab=' . $loginSlug);
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $labInfo ? labClean($labInfo['name']) : 'Lab Login' ?> — <?= PLATFORM_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#f1f5f3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-wrap{display:grid;grid-template-columns:1fr 1fr;max-width:900px;width:100%;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)}
.login-left{background:linear-gradient(145deg,#0d3d28 0%,#1a6b4a 60%,#2d9e6e 100%);padding:50px 40px;display:flex;flex-direction:column;justify-content:space-between;color:#fff;position:relative;overflow:hidden}
.login-left::before{content:'';position:absolute;width:300px;height:300px;background:rgba(255,255,255,.04);border-radius:50%;bottom:-80px;left:-80px}
.brand-mark{display:flex;align-items:center;gap:12px;margin-bottom:32px}
.brand-mark .icon{width:48px;height:48px;background:rgba(255,255,255,.15);border-radius:12px;display:grid;place-items:center;font-size:24px}
.brand-mark .name strong{display:block;font-size:18px}
.brand-mark .name span{font-size:12px;opacity:.6;letter-spacing:1px}
.login-right{padding:50px 40px}
.login-right h2{font-size:24px;font-weight:700;margin-bottom:6px}
.login-right p{color:#64748b;font-size:14px;margin-bottom:28px}
.form-group{margin-bottom:18px}
.form-label{font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;display:block}
.input-wrap{position:relative}
.input-wrap .icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:16px}
.input-wrap input{width:100%;padding:11px 14px 11px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:inherit;outline:none;transition:border .2s}
.input-wrap input:focus{border-color:#1a6b4a;box-shadow:0 0 0 3px rgba(26,107,74,.1)}
.btn-login{width:100%;background:#1a6b4a;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;transition:background .2s;margin-top:6px}
.btn-login:hover{background:#134f37}
.alert-error{background:#fee2e2;border-left:3px solid #ef4444;color:#991b1b;border-radius:8px;padding:12px 14px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.powered{font-size:11px;opacity:.5;margin-top:20px;text-align:center}
@media(max-width:640px){.login-wrap{grid-template-columns:1fr}.login-left{display:none}}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-left">
        <div>
            <div class="brand-mark">
                <div class="icon"><i class="bi bi-activity"></i></div>
                <div class="name">
                    <strong><?= $labInfo ? labClean($labInfo['name']) : 'LondonLab' ?></strong>
                    <span>LABORATORY SYSTEM</span>
                </div>
            </div>
            <div style="font-size:30px;font-weight:700;line-height:1.2;margin-bottom:16px;">
                Welcome to your<br><span style="color:#6ee7b7;">Lab Portal</span>
            </div>
            <p style="font-size:14px;opacity:.7;line-height:1.6;">
                Manage patients, orders, results, billing and reports — all in one place.
            </p>
        </div>
        <div style="font-size:12px;opacity:.4;">Powered by <?= PLATFORM_NAME ?></div>
    </div>
    <div class="login-right">
        <h2>Sign In 👋</h2>
        <p>Access your laboratory management system</p>

        <?php if ($error): ?>
        <div class="alert-error"><i class="bi bi-exclamation-triangle-fill"></i><?= labClean($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php if (!$slug): ?>
            <div class="form-group">
                <label class="form-label">Lab ID <span class="text-danger">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-building icon"></i>
                    <input type="text" name="lab_slug" required placeholder="your-lab-id"
                           value="<?= labClean($_POST['lab_slug'] ?? '') ?>">
                </div>
                <small class="text-muted">Your lab ID was provided when you signed up.</small>
            </div>
            <?php else: ?>
            <input type="hidden" name="lab_slug" value="<?= labClean($slug) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrap">
                    <i class="bi bi-envelope icon"></i>
                    <input type="email" name="email" required placeholder="you@lab.com"
                           value="<?= labClean($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrap">
                    <i class="bi bi-lock icon"></i>
                    <input type="password" name="password" required placeholder="Enter your password">
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>
        <div class="powered">Powered by <?= PLATFORM_NAME ?></div>
    </div>
</div>
</body>
</html>
