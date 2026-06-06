<?php
$pageTitle = 'Manage Plans';
require_once __DIR__ . '/../../includes/header.php';

$db     = MasterDB::getInstance();
$errors = [];

// Handle add/edit plan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    saVerifyCsrf();
    $pid   = (int)($_POST['plan_id'] ?? 0);
    $name  = trim($_POST['name']  ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $max_u = (int)($_POST['max_users'] ?? 0);
    $max_p = (int)($_POST['max_patients'] ?? 0);
    $whatsapp = isset($_POST['whatsapp']) ? 'true' : 'false';
    $logo     = isset($_POST['custom_logo']) ? 'true' : 'false';
    $backup   = $_POST['backup'] ?? 'weekly';
    $support  = $_POST['support'] ?? 'email';

    if (!$name)     $errors[] = 'Plan name required.';
    if ($price <= 0) $errors[] = 'Price must be > 0.';

    $features = json_encode(['whatsapp_reports'=>$whatsapp==='true','custom_logo'=>$logo==='true','backup'=>$backup,'support'=>$support]);

    if (empty($errors)) {
        if ($pid) {
            $db->execute("UPDATE plans SET name=?,price=?,max_users=?,max_patients_per_month=?,features=? WHERE id=?",
                [$name,$price,$max_u,$max_p,$features,$pid]);
            saSetFlash('success','Plan updated!');
        } else {
            $slug = strtolower(preg_replace('/\s+/','_',$name));
            $db->execute("INSERT INTO plans (name,slug,price,max_users,max_patients_per_month,features) VALUES (?,?,?,?,?,?)",
                [$name,$slug,$price,$max_u,$max_p,$features]);
            saSetFlash('success','Plan created!');
        }
        header('Location: '.SUPERADMIN_URL.'/modules/plans/index.php'); exit;
    }
}

// Toggle active
if (isset($_GET['toggle'])) {
    $pid  = (int)$_GET['toggle'];
    $curr = $db->fetch("SELECT is_active FROM plans WHERE id=?",[$pid]);
    $db->execute("UPDATE plans SET is_active=? WHERE id=?", [$curr['is_active']?0:1, $pid]);
    header('Location: '.SUPERADMIN_URL.'/modules/plans/index.php'); exit;
}

$plans = $db->fetchAll("SELECT p.*, COUNT(l.id) as lab_count FROM plans p LEFT JOIN labs l ON l.plan_id=p.id AND l.status IN ('active','trial') GROUP BY p.id ORDER BY p.price");
?>

<div class="page-header">
    <div><h2><i class="bi bi-boxes me-2" style="color:#7c3aed;"></i>Manage Plans</h2><p>Define subscription plans</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planModal" onclick="resetForm()">
        <i class="bi bi-plus-circle-fill me-2"></i>New Plan
    </button>
</div>

<div class="row g-4 mb-4">
    <?php foreach ($plans as $plan):
        $feat = json_decode($plan['features'] ?? '{}', true);
    ?>
    <div class="col-md-4">
        <div class="card h-100" style="border-color:<?= $plan['is_active']?'#7c3aed':'#e5e7eb' ?>;">
            <div class="card-body text-center py-4">
                <span class="badge bg-<?= planBadgeColor($plan['slug']) ?>-subtle text-<?= planBadgeColor($plan['slug']) ?> mb-2">
                    <?= $plan['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
                <div class="fw-bold fs-5 mb-1"><?= saClean($plan['name']) ?></div>
                <div style="font-size:36px;font-weight:800;color:#7c3aed;line-height:1;">
                    <?= money($plan['price']) ?>
                </div>
                <div class="text-muted small mb-3">per month</div>
                <div class="text-start">
                    <?php
                    $features = [
                        'Users'      => $plan['max_users'] ?: 'Unlimited',
                        'Patients/mo'=> $plan['max_patients_per_month'] ?: 'Unlimited',
                        'WhatsApp'   => ($feat['whatsapp_reports'] ?? false) ? '✅' : '❌',
                        'Custom Logo'=> ($feat['custom_logo'] ?? false) ? '✅' : '❌',
                        'Backup'     => ucfirst($feat['backup'] ?? 'weekly'),
                        'Support'    => ucfirst(str_replace('_',' ',$feat['support'] ?? 'email')),
                    ];
                    foreach ($features as $k=>$v): ?>
                    <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:13px;">
                        <span class="text-muted"><?= $k ?></span>
                        <span class="fw-semibold"><?= $v ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <span class="badge bg-primary-subtle text-primary"><?= $plan['lab_count'] ?> lab(s)</span>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#planModal"
                        onclick="editPlan(<?= htmlspecialchars(json_encode($plan), ENT_QUOTES) ?>)">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <a href="?toggle=<?= $plan['id'] ?>" class="btn btn-sm btn-outline-<?= $plan['is_active']?'warning':'success' ?>"
                       onclick="return confirm('Toggle plan status?')">
                        <i class="bi bi-<?= $plan['is_active']?'pause':'play' ?>-circle"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Plan Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= saCsrfToken() ?>">
                <input type="hidden" name="plan_id" id="planId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="planModalTitle">New Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Plan Name</label>
                            <input type="text" name="name" id="planName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monthly Price (₹)</label>
                            <input type="number" name="price" id="planPrice" class="form-control" min="1" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Users (0=unlimited)</label>
                            <input type="number" name="max_users" id="planMaxU" class="form-control" min="0" value="3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Patients/month (0=unlimited)</label>
                            <input type="number" name="max_patients" id="planMaxP" class="form-control" min="0" value="500">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Backup Frequency</label>
                            <select name="backup" id="planBackup" class="form-select">
                                <option value="weekly">Weekly</option>
                                <option value="daily">Daily</option>
                                <option value="realtime">Real-time</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Support Level</label>
                            <select name="support" id="planSupport" class="form-select">
                                <option value="email">Email</option>
                                <option value="email_phone">Email + Phone</option>
                                <option value="dedicated">Dedicated</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="whatsapp" id="planWA">
                                <label class="form-check-label" for="planWA">WhatsApp Reports</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="custom_logo" id="planLogo">
                                <label class="form-check-label" for="planLogo">Custom Logo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check me-2"></i>Save Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
function resetForm() {
    document.getElementById('planId').value = 0;
    document.getElementById('planModalTitle').textContent = 'New Plan';
    document.getElementById('planName').value = '';
    document.getElementById('planPrice').value = '';
    document.getElementById('planMaxU').value = 3;
    document.getElementById('planMaxP').value = 500;
    document.getElementById('planWA').checked = false;
    document.getElementById('planLogo').checked = false;
}
function editPlan(plan) {
    const feat = plan.features ? JSON.parse(plan.features) : {};
    document.getElementById('planId').value    = plan.id;
    document.getElementById('planModalTitle').textContent = 'Edit Plan: ' + plan.name;
    document.getElementById('planName').value  = plan.name;
    document.getElementById('planPrice').value = plan.price;
    document.getElementById('planMaxU').value  = plan.max_users;
    document.getElementById('planMaxP').value  = plan.max_patients_per_month;
    document.getElementById('planBackup').value   = feat.backup   || 'weekly';
    document.getElementById('planSupport').value  = feat.support  || 'email';
    document.getElementById('planWA').checked    = feat.whatsapp_reports === true;
    document.getElementById('planLogo').checked  = feat.custom_logo === true;
}
</script>
JS;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
