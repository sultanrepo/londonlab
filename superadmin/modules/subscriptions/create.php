<?php
$pageTitle = 'Add Subscription';
require_once __DIR__ . '/../../includes/header.php';

$db     = MasterDB::getInstance();
$labs   = $db->fetchAll("SELECT * FROM labs ORDER BY name");
$plans  = $db->fetchAll("SELECT * FROM plans WHERE is_active=1 ORDER BY price");
$errors = [];
$preLab = (int)($_GET['lab_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    saVerifyCsrf();
    $lab_id     = (int)($_POST['lab_id']     ?? 0);
    $plan_id    = (int)($_POST['plan_id']    ?? 0);
    $start_date = $_POST['start_date']       ?? date('Y-m-d');
    $end_date   = $_POST['end_date']         ?? '';
    $amount     = (float)($_POST['amount']   ?? 0);

    if (!$lab_id)    $errors[] = 'Please select a lab.';
    if (!$plan_id)   $errors[] = 'Please select a plan.';
    if (!$end_date)  $errors[] = 'End date is required.';
    if ($amount <=0) $errors[] = 'Amount must be greater than zero.';

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            // Add subscription record
            $db->execute("INSERT INTO subscriptions (lab_id,plan_id,start_date,end_date,amount,status) VALUES (?,?,?,?,?,'active')",
                [$lab_id,$plan_id,$start_date,$end_date,$amount]);

            // Update lab status and subscription end date
            $db->execute("UPDATE labs SET status='active',subscription_ends_at=?,plan_id=? WHERE id=?",
                [$end_date,$plan_id,$lab_id]);

            $db->commit();
            saSetFlash('success','Subscription added and lab activated!');
            header('Location: '.SUPERADMIN_URL.'/modules/subscriptions/index.php'); exit;
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Failed: '.$e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-calendar-plus me-2" style="color:#7c3aed;"></i>Add Subscription</h2>
        <p>Manually record a subscription period for a lab</p>
    </div>
    <a href="<?= SUPERADMIN_URL ?>/modules/subscriptions/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= saClean($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card">
    <div class="card-header"><i class="bi bi-calendar-check"></i> Subscription Details</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= saCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Laboratory <span class="text-danger">*</span></label>
                    <select name="lab_id" class="form-select" required>
                        <option value="">— Select Lab —</option>
                        <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>" <?= $preLab===$lab['id']?'selected':'' ?>>
                            <?= saClean($lab['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Plan <span class="text-danger">*</span></label>
                    <select name="plan_id" id="planSel" class="form-select" required>
                        <option value="">— Select Plan —</option>
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>" data-price="<?= $plan['price'] ?>">
                            <?= saClean($plan['name']) ?> — <?= money($plan['price']) ?>/mo
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control"
                           value="<?= saClean($_POST['start_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" required
                           value="<?= saClean($_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Amount Paid (₹)</label>
                    <input type="number" name="amount" id="amtInput" class="form-control"
                           min="1" step="0.01" value="<?= saClean($_POST['amount'] ?? '') ?>">
                </div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= SUPERADMIN_URL ?>/modules/subscriptions/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Add Subscription</button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<?php
$extraJs = <<<'JS'
<script>
document.getElementById('planSel').addEventListener('change', function(){
    const price = this.options[this.selectedIndex]?.dataset?.price;
    if (price) document.getElementById('amtInput').value = parseFloat(price).toFixed(2);
});
</script>
JS;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
