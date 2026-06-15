<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';

$db      = MasterDB::getInstance();
$errors  = [];
$profile = $db->fetch("SELECT * FROM super_admins WHERE id=?", [$saUser['id']]);

// ── UPDATE PROFILE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    saVerifyCsrf();
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$name)              $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    // Email uniqueness (exclude self)
    if ($email) {
        $dup = $db->fetch("SELECT id FROM super_admins WHERE email=? AND id != ?", [$email, $saUser['id']]);
        if ($dup) $errors[] = 'That email is already used by another admin.';
    }

    if (empty($errors)) {
        $db->execute("UPDATE super_admins SET name=?, email=? WHERE id=?", [$name, $email, $saUser['id']]);
        $_SESSION['sa_name']  = $name;
        $_SESSION['sa_email'] = $email;
        saSetFlash('success', 'Profile updated successfully.');
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
}

// ── CHANGE PASSWORD ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    saVerifyCsrf();
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (!$current)                           $errors[] = 'Current password is required.';
    if (strlen($new) < 8)                    $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $confirm)                   $errors[] = 'New passwords do not match.';
    if ($current && !password_verify($current, $profile['password']))
                                             $errors[] = 'Current password is incorrect.';

    if (empty($errors)) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $db->execute("UPDATE super_admins SET password=? WHERE id=?", [$hash, $saUser['id']]);
        saSetFlash('success', 'Password changed successfully.');
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
}

// Reload after possible update
$profile = $db->fetch("SELECT * FROM super_admins WHERE id=?", [$saUser['id']]);
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-person-gear me-2" style="color:var(--sa-primary)"></i>My Profile</h2>
        <p>Manage your account details and password</p>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= saClean($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── LEFT: avatar + meta ── -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center py-4">
                <div style="width:80px;height:80px;background:var(--sa-primary);border-radius:50%;display:grid;place-items:center;margin:0 auto 16px;font-size:32px;color:#fff;font-weight:700;">
                    <?= strtoupper(substr($profile['name'], 0, 1)) ?>
                </div>
                <div style="font-size:18px;font-weight:700;"><?= saClean($profile['name']) ?></div>
                <div class="mt-1">
                    <span class="badge-status status-active"><?= ucfirst($profile['role']) ?></span>
                </div>
                <div class="text-muted small mt-2"><?= saClean($profile['email']) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Account Info</div>
            <div class="card-body">
                <table class="table table-sm mb-0" style="font-size:13px;">
                    <tr>
                        <td class="text-muted">Admin ID</td>
                        <td class="fw-semibold">#<?= $profile['id'] ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Role</td>
                        <td class="fw-semibold"><?= ucfirst($profile['role']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td><span class="badge-status status-active">Active</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Last Login</td>
                        <td class="fw-semibold">
                            <?= $profile['last_login'] ? date('d M Y, H:i', strtotime($profile['last_login'])) : '—' ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Member Since</td>
                        <td class="fw-semibold">
                            <?= $profile['created_at'] ? date('d M Y', strtotime($profile['created_at'])) : '—' ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: forms ── -->
    <div class="col-lg-8">

        <!-- Update Profile -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Update Profile</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= saCsrfToken() ?>">
                    <input type="hidden" name="action"     value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= saClean($profile['name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?= saClean($profile['email']) ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Change Password</div>
            <div class="card-body">
                <form method="POST" id="pwForm">
                    <input type="hidden" name="csrf_token" value="<?= saCsrfToken() ?>">
                    <input type="hidden" name="action"     value="change_password">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Current Password *</label>
                            <div class="input-group">
                                <input type="password" name="current_password" id="cur" class="form-control" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggle('cur','eyeCur')">
                                    <i class="bi bi-eye" id="eyeCur"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password *</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="np" class="form-control"
                                       required minlength="8" oninput="checkStrength(this.value)">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggle('np','eyeNp')">
                                    <i class="bi bi-eye" id="eyeNp"></i>
                                </button>
                            </div>
                            <div class="progress mt-2" style="height:4px;">
                                <div class="progress-bar" id="strengthBar" style="width:0%;transition:width .3s,background .3s;"></div>
                            </div>
                            <div id="strengthLabel" class="form-text"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password *</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="cp" class="form-control" required minlength="8">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggle('cp','eyeCp')">
                                    <i class="bi bi-eye" id="eyeCp"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-shield-check me-2"></i>Change Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /col -->
</div><!-- /row -->

<?php
$extraJs = <<<'JS'
<script>
function toggle(id, iconId) {
    const el = document.getElementById(id);
    const ic = document.getElementById(iconId);
    el.type = (el.type === 'password') ? 'text' : 'password';
    ic.className = (el.type === 'password') ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function checkStrength(val) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { pct: '20%', bg: '#ef4444', text: 'Very weak' },
        { pct: '40%', bg: '#f97316', text: 'Weak' },
        { pct: '60%', bg: '#eab308', text: 'Fair' },
        { pct: '80%', bg: '#22c55e', text: 'Strong' },
        { pct: '100%',bg: '#16a34a', text: 'Very strong' },
    ];
    const l = levels[Math.min(score, 4)];
    bar.style.width      = l.pct;
    bar.style.background = l.bg;
    label.textContent    = l.text;
    label.style.color    = l.bg;
}
</script>
JS;
require_once __DIR__ . '/includes/footer.php';
?>