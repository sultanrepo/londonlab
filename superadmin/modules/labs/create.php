<?php
$pageTitle = 'Add New Lab';
require_once __DIR__ . '/../../includes/header.php';

$db     = MasterDB::getInstance();
$plans  = $db->fetchAll("SELECT * FROM plans WHERE is_active=1 ORDER BY price ASC");
$errors = [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    saVerifyCsrf();
    $name        = trim($_POST['name']        ?? '');
    $owner_name  = trim($_POST['owner_name']  ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');
    $owner_phone = trim($_POST['owner_phone'] ?? '');
    $address     = trim($_POST['address']     ?? '');
    $city        = trim($_POST['city']        ?? '');
    $state       = trim($_POST['state']       ?? '');
    $pincode     = trim($_POST['pincode']     ?? '');
    $gstin       = trim($_POST['gstin']       ?? '');
    $plan_id     = (int)($_POST['plan_id']    ?? 1);
    $password    = trim($_POST['password']    ?? '');

    if (!$name)        $errors[] = 'Lab name is required.';
    if (!$owner_name)  $errors[] = 'Owner name is required.';
    if (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$owner_phone) $errors[] = 'Phone number is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    // Check duplicate email
    if (empty($errors)) {
        $exists = $db->fetch("SELECT id FROM labs WHERE owner_email=?", [$owner_email]);
        if ($exists) $errors[] = 'A lab with this email already exists.';
    }

    if (empty($errors)) {
        $result = LabProvisioner::provision([
            'name'        => $name,
            'owner_name'  => $owner_name,
            'owner_email' => $owner_email,
            'owner_phone' => $owner_phone,
            'address'     => $address,
            'city'        => $city,
            'state'       => $state,
            'pincode'     => $pincode,
            'gstin'       => $gstin,
            'plan_id'     => $plan_id,
            'password'    => $password,
        ]);

        if ($result['success']) {
            saSetFlash('success', "Lab '{$name}' provisioned! Lab ID: <strong>{$result['slug']}</strong> — Share this ID with the lab owner for login.");
            exit;
        } else {
            $errors[] = 'Provisioning failed: ' . $result['message'];
        }
    }
}
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-plus-circle-fill me-2" style="color:#7c3aed;"></i>Add New Lab</h2>
        <p>Register a new laboratory and provision their database automatically</p>
    </div>
    <a href="<?= SUPERADMIN_URL ?>/modules/labs/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= saClean($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row g-4">
<div class="col-lg-8">

<!-- Lab Info -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-building"></i> Laboratory Information</div>
    <div class="card-body">
        <form method="POST" id="labForm">
            <input type="hidden" name="csrf_token" value="<?= saCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Lab / Clinic Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           placeholder="e.g. Sharma Diagnostics Pvt Ltd"
                           value="<?= saClean($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner / Admin Name <span class="text-danger">*</span></label>
                    <input type="text" name="owner_name" class="form-control" required
                           placeholder="e.g. Dr. Rajesh Sharma"
                           value="<?= saClean($_POST['owner_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Phone <span class="text-danger">*</span></label>
                    <input type="text" name="owner_phone" class="form-control" required
                           placeholder="+91-XXXXXXXXXX"
                           value="<?= saClean($_POST['owner_phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Email <span class="text-danger">*</span></label>
                    <input type="email" name="owner_email" class="form-control" required
                           placeholder="owner@lab.com"
                           value="<?= saClean($_POST['owner_email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">GSTIN</label>
                    <input type="text" name="gstin" class="form-control"
                           placeholder="07AABCL1234M1Z5"
                           value="<?= saClean($_POST['gstin'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"
                              placeholder="Lab full address"><?= saClean($_POST['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" placeholder="City"
                           value="<?= saClean($_POST['city'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" placeholder="State"
                           value="<?= saClean($_POST['state'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" class="form-control" placeholder="110001"
                           value="<?= saClean($_POST['pincode'] ?? '') ?>">
                </div>

                <div class="col-12"><div class="divider"></div></div>
                <div class="col-12"><h6 class="fw-bold">Login Credentials for Lab Admin</h6>
                <p class="text-muted small">The lab owner will use these to log in.</p></div>

                <div class="col-md-6">
                    <label class="form-label">Initial Password <span class="text-danger">*</span></label>
                    <input type="text" name="password" class="form-control" required
                           placeholder="Min. 6 characters"
                           value="<?= saClean($_POST['password'] ?? '') ?>">
                    <small class="text-muted">Share this with the lab owner securely.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subscription Plan <span class="text-danger">*</span></label>
                    <select name="plan_id" class="form-select">
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>"
                            <?= ($_POST['plan_id'] ?? 1) == $plan['id'] ? 'selected' : '' ?>>
                            <?= saClean($plan['name']) ?> — <?= money($plan['price']) ?>/mo
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= SUPERADMIN_URL ?>/modules/labs/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-database-fill-add me-2"></i>Provision Lab
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- What happens box -->
<div class="col-lg-4">
    <div class="card mb-4" style="border-color:#7c3aed;">
        <div class="card-header" style="background:#f5f3ff;border-color:#7c3aed;color:#7c3aed;">
            <i class="bi bi-magic"></i> What happens when you click Provision?
        </div>
        <div class="card-body">
            <div class="d-flex flex-column gap-3">
                <?php
                $steps = [
                    ['bi-database-fill-add','A dedicated database is created','e.g. londonlab_sharma_diagnostics'],
                    ['bi-table','All tables are created automatically','patients, orders, tests, expenses...'],
                    ['bi-person-check-fill','Lab admin account is created','With the email & password you provide'],
                    ['bi-gear-fill','Default settings are configured','Lab name, currency, report footer'],
                    ['bi-calendar-check-fill','14-day free trial starts','Lab can use all features immediately'],
                    ['bi-shield-check','Lab is isolated from others','Full data privacy & security'],
                ];
                foreach ($steps as [$icon, $title, $desc]): ?>
                <div class="d-flex gap-3">
                    <div style="width:36px;height:36px;border-radius:8px;background:#ede9fe;
                        color:#7c3aed;display:grid;place-items:center;flex-shrink:0;">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <div>
                        <div class="fw-semibold" style="font-size:13px;"><?= $title ?></div>
                        <div class="text-muted" style="font-size:12px;"><?= $desc ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Plan comparison -->
    <div class="card">
        <div class="card-header"><i class="bi bi-boxes"></i> Plan Comparison</div>
        <div class="card-body p-0">
            <table class="table mb-0" style="font-size:13px;">
                <thead><tr><th>Plan</th><th>Price</th><th>Users</th><th>Patients/mo</th></tr></thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td class="fw-semibold"><?= saClean($plan['name']) ?></td>
                        <td><?= money($plan['price']) ?></td>
                        <td><?= $plan['max_users'] ?: '∞' ?></td>
                        <td><?= $plan['max_patients_per_month'] ?: '∞' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
