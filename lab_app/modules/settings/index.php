<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../../includes/header.php';
if (!labIsAdmin()) { labSetFlash('error','Admins only.'); header('Location: '.LAB_APP_URL.'/index.php?lab='.$slug); exit; }

$errors  = [];
$logoUrl = labGetSetting($labDb, 'lab_logo', '');

// ── LOGO UPLOAD ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_logo') {
    labVerifyCsrf();
    if (!labIsAdmin()) { labSetFlash('error', 'Admins only.'); header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit; }

    // Handle logo deletion
    if (isset($_POST['delete_logo'])) {
        $oldPath = ROOT_PATH . '/lab_app' . parse_url($logoUrl, PHP_URL_PATH);
        if ($logoUrl && file_exists($oldPath)) @unlink($oldPath);
        $labDb->execute("INSERT INTO settings (setting_key,setting_value) VALUES ('lab_logo','') ON DUPLICATE KEY UPDATE setting_value=''");
        labSetFlash('success', 'Logo removed.');
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
    }

    // Handle logo upload
    if (!empty($_FILES['lab_logo']['tmp_name'])) {
        $file     = $_FILES['lab_logo'];
        $allowed  = ['image/png','image/jpeg','image/jpg','image/gif','image/webp','image/svg+xml'];
        $maxBytes = 2 * 1024 * 1024; // 2 MB

        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Invalid file type. Please upload PNG, JPG, GIF, WEBP, or SVG.';
        } elseif ($file['size'] > $maxBytes) {
            $errors[] = 'File too large. Maximum size is 2 MB.';
        } else {
            // Create per-lab upload directory
            $uploadDir = ROOT_PATH . '/lab_app/assets/logos/' . $slug . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Delete old logo file if exists
            if ($logoUrl) {
                $oldPath = ROOT_PATH . '/lab_app' . parse_url($logoUrl, PHP_URL_PATH);
                if (file_exists($oldPath)) @unlink($oldPath);
            }

            // Save new file with cache-busting suffix
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'logo_' . time() . '.' . $ext;
            $savePath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $savePath)) {
                $relUrl = '/assets/logos/' . $slug . '/' . $filename;
                $labDb->execute("INSERT INTO settings (setting_key,setting_value) VALUES ('lab_logo',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$relUrl]);
                labSetFlash('success', 'Logo uploaded successfully!');
                header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
            } else {
                $errors[] = 'Upload failed. Please check folder permissions.';
            }
        }
    } else {
        $errors[] = 'Please select a file to upload.';
    }
}

// Reload logo after possible upload
$logoUrl = labGetSetting($labDb, 'lab_logo', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    labVerifyCsrf();
    $settings = [
        'lab_name'       => trim($_POST['lab_name']       ?? ''),
        'lab_address'    => trim($_POST['lab_address']     ?? ''),
        'lab_phone'      => trim($_POST['lab_phone']       ?? ''),
        'lab_email'      => trim($_POST['lab_email']       ?? ''),
        'lab_gstin'      => trim($_POST['lab_gstin']       ?? ''),
        'report_footer'  => trim($_POST['report_footer']   ?? ''),
        'order_prefix'   => trim($_POST['order_prefix']    ?? 'ORD'),
        'currency_symbol'=> trim($_POST['currency_symbol'] ?? '₹'),
    ];

    if (!$settings['lab_name']) $errors[] = 'Lab name is required.';

    if (empty($errors)) {
        $stmt = $labDb->getConnection()->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        foreach ($settings as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        labSetFlash('success', 'Settings saved successfully!');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?lab=' . $slug);
        exit;
    }
}  // end save_settings

// Load current settings
$allSettings = $labDb->fetchAll("SELECT setting_key, setting_value FROM settings");
$cfg = array_column($allSettings, 'setting_value', 'setting_key');

// Merge POST values on error
if (!empty($errors) && ($_POST['action'] ?? '') === 'save_settings') {
    foreach ($_POST as $k => $v) $cfg[$k] = $v;
}

// Lab info from master
$masterDb   = MasterDB::getInstance();
$labInfo    = $masterDb->fetch("SELECT l.*, p.name as plan_name, p.max_users, p.max_patients_per_month FROM labs l LEFT JOIN plans p ON l.plan_id=p.id WHERE l.slug=?", [$slug]);
$userCount  = $labDb->fetch("SELECT COUNT(*) as c FROM users WHERE is_active=1")['c'];
?>

<div class="page-header">
    <div><h2><i class="bi bi-gear-fill me-2"></i>Settings</h2><p>Lab configuration and preferences</p></div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-4"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= labClean($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">

        <!-- Lab Information -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-building"></i> Laboratory Information</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Lab / Clinic Name <span class="text-danger">*</span></label>
                            <input type="text" name="lab_name" class="form-control" required
                                   value="<?= labClean($cfg['lab_name'] ?? '') ?>"
                                   placeholder="e.g. LondonLab">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="lab_address" class="form-control" rows="2"
                                      placeholder="Full lab address"><?= labClean($cfg['lab_address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="lab_phone" class="form-control"
                                   value="<?= labClean($cfg['lab_phone'] ?? '') ?>"
                                   placeholder="+91-XXXXXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="lab_email" class="form-control"
                                   value="<?= labClean($cfg['lab_email'] ?? '') ?>"
                                   placeholder="lab@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">GSTIN</label>
                            <input type="text" name="lab_gstin" class="form-control"
                                   value="<?= labClean($cfg['lab_gstin'] ?? '') ?>"
                                   placeholder="07AABCL1234M1Z5">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Order Prefix</label>
                            <input type="text" name="order_prefix" class="form-control"
                                   value="<?= labClean($cfg['order_prefix'] ?? 'ORD') ?>"
                                   placeholder="ORD">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control"
                                   value="<?= labClean($cfg['currency_symbol'] ?? '₹') ?>"
                                   placeholder="₹">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Report / Invoice Footer Note</label>
                            <textarea name="report_footer" class="form-control" rows="2"
                                      placeholder="e.g. Results are for clinical guidance only."><?= labClean($cfg['report_footer'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="divider"></div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        <!-- Logo Upload -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-image-fill"></i> Lab Logo</div>
            <div class="card-body">

                <?php if ($logoUrl): ?>
                <!-- Current logo preview -->
                <div class="text-center mb-3 p-3 rounded-2" style="background:#f8faf9;border:1px dashed #d1d5db;">
                    <img src="<?= LAB_APP_URL . labClean($logoUrl) ?>"
                         alt="Lab Logo"
                         style="max-height:90px;max-width:100%;object-fit:contain;">
                    <div class="mt-2" style="font-size:12px;color:#64748b;">Current logo</div>
                </div>
                <?php else: ?>
                <div class="text-center mb-3 p-4 rounded-2" style="background:#f8faf9;border:2px dashed #d1d5db;color:#94a3b8;">
                    <i class="bi bi-image" style="font-size:32px;"></i>
                    <div class="mt-2" style="font-size:13px;">No logo uploaded yet</div>
                </div>
                <?php endif; ?>

                <!-- Upload form -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
                    <input type="hidden" name="action"     value="upload_logo">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;">Upload new logo</label>
                        <input type="file" name="lab_logo" class="form-control form-control-sm"
                               accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml">
                        <div class="form-text">PNG, JPG, SVG or WebP · Max 2 MB · Recommended: 300×80 px</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-upload me-2"></i>Upload Logo
                    </button>
                </form>

                <?php if ($logoUrl): ?>
                <!-- Delete logo -->
                <form method="POST" class="mt-2"
                      onsubmit="return confirm('Remove the current logo?')">
                    <input type="hidden" name="csrf_token"  value="<?= labCsrfToken() ?>">
                    <input type="hidden" name="action"      value="upload_logo">
                    <input type="hidden" name="delete_logo" value="1">
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                        <i class="bi bi-trash me-2"></i>Remove Logo
                    </button>
                </form>
                <?php endif; ?>

            </div>
        </div>

        <!-- Subscription Info -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-calendar-check"></i> Your Subscription</div>
            <div class="card-body">
                <?php if ($labInfo): ?>
                <div class="text-center mb-3">
                    <span class="badge bg-<?= planBadgeColor($labInfo['plan_name']??'') ?>-subtle
                        text-<?= planBadgeColor($labInfo['plan_name']??'') ?> px-3 py-2 fs-6">
                        <?= labClean($labInfo['plan_name'] ?? 'Trial') ?> Plan
                    </span>
                </div>
                <?php
                $statusItems = [
                    'Status'    => ucfirst($labInfo['status']),
                    'Max Users' => $labInfo['max_users'] ? $labInfo['max_users'] : 'Unlimited',
                    'Current Users' => $userCount,
                    'Patients/mo' => $labInfo['max_patients_per_month'] ? number_format($labInfo['max_patients_per_month']) : 'Unlimited',
                ];
                if ($labInfo['status']==='trial' && $labInfo['trial_ends_at']) {
                    $statusItems['Trial Ends'] = date('d M Y',strtotime($labInfo['trial_ends_at']));
                    $statusItems['Days Left']  = daysLeft($labInfo['trial_ends_at']).' days';
                }
                if ($labInfo['subscription_ends_at']) {
                    $statusItems['Sub. Ends']  = date('d M Y',strtotime($labInfo['subscription_ends_at']));
                }
                foreach ($statusItems as $k=>$v): ?>
                <div class="d-flex justify-content-between py-2 border-bottom" style="font-size:13px;">
                    <span class="text-muted"><?= $k ?></span>
                    <span class="fw-semibold"><?= labClean($v) ?></span>
                </div>
                <?php endforeach; ?>

                <?php if ($labInfo['status']==='trial'): ?>
                <div class="mt-3 p-3 rounded-2 text-center" style="background:#e0f2fe;font-size:13px;color:#0369a1;">
                    <strong>⏳ Trial Mode</strong><br>
                    Contact your service provider to activate a subscription.
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart"></i> Quick Stats</div>
            <div class="card-body p-0">
                <?php
                $stats = [
                    ['Total Patients',  $labDb->fetch("SELECT COUNT(*) as c FROM patients WHERE is_active=1")['c'],     'bi-people-fill',         'text-primary'],
                    ['Total Tests',     $labDb->fetch("SELECT COUNT(*) as c FROM tests WHERE is_active=1")['c'],        'bi-droplet-half',        'text-info'],
                    ['Total Doctors',   $labDb->fetch("SELECT COUNT(*) as c FROM doctors WHERE is_active=1")['c'],      'bi-person-vcard-fill',   'text-purple'],
                    ['Total Orders',    $labDb->fetch("SELECT COUNT(*) as c FROM orders")['c'],                         'bi-clipboard2-pulse-fill','text-warning'],
                ];
                foreach ($stats as [$label, $val, $icon, $color]): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?= $icon ?> <?= $color ?>"></i>
                        <span style="font-size:13px;"><?= $label ?></span>
                    </div>
                    <strong><?= number_format($val) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>