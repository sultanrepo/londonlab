<?php
$pageTitle = 'Generate Invoice';
require_once __DIR__ . '/../../includes/header.php';

$db     = MasterDB::getInstance();
$labs   = $db->fetchAll("SELECT * FROM labs WHERE status IN ('active','trial') ORDER BY name");
$plans  = $db->fetchAll("SELECT * FROM plans WHERE is_active=1 ORDER BY price");
$errors = [];

// Pre-select lab if passed via URL
$preLab = (int)($_GET['lab_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    saVerifyCsrf();
    $lab_id   = (int)$_POST['lab_id'];
    $plan_id  = (int)$_POST['plan_id'];
    $amount   = (float)$_POST['amount'];
    $gst_amt  = round($amount * GST_PERCENT / 100, 2);
    $total    = $amount + $gst_amt;
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $notes    = trim($_POST['notes'] ?? '');

    if (!$lab_id)  $errors[] = 'Please select a lab.';
    if (!$plan_id) $errors[] = 'Please select a plan.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

    if (empty($errors)) {
        $invNo = generateInvoiceNo();
        $db->execute("
            INSERT INTO billing_invoices (invoice_no,lab_id,plan_id,amount,gst_amount,total_amount,status,due_date,notes)
            VALUES (?,?,?,?,?,?,'pending',?,?)
        ", [$invNo, $lab_id, $plan_id, $amount, $gst_amt, $total, $due_date, $notes]);
        $invId = $db->lastInsertId();
        saSetFlash('success', "Invoice $invNo generated successfully!");
        header('Location: ' . SUPERADMIN_URL . '/modules/billing/view.php?id=' . $invId); exit;
    }
}
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-receipt me-2" style="color:#7c3aed;"></i>Generate Invoice</h2>
        <p>Create a new billing invoice for a lab</p>
    </div>
    <a href="<?= SUPERADMIN_URL ?>/modules/billing/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= saClean($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
    <div class="card-header"><i class="bi bi-file-earmark-text"></i> Invoice Details</div>
    <div class="card-body">
        <form method="POST" id="invForm">
            <input type="hidden" name="csrf_token" value="<?= saCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Laboratory <span class="text-danger">*</span></label>
                    <select name="lab_id" id="labSelect" class="form-select" required>
                        <option value="">— Select Lab —</option>
                        <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>"
                            data-plan="<?= $lab['plan_id'] ?>"
                            <?= ($preLab === (int)$lab['id'] || ($_POST['lab_id'] ?? 0) == $lab['id']) ? 'selected' : '' ?>>
                            <?= saClean($lab['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Plan <span class="text-danger">*</span></label>
                    <select name="plan_id" id="planSelect" class="form-select" required>
                        <option value="">— Select Plan —</option>
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>"
                            data-price="<?= $plan['price'] ?>"
                            <?= ($_POST['plan_id'] ?? 0) == $plan['id'] ? 'selected' : '' ?>>
                            <?= saClean($plan['name']) ?> — <?= money($plan['price']) ?>/mo
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Base Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" id="amountInput" class="form-control"
                           min="1" step="0.01" required
                           value="<?= saClean($_POST['amount'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">GST (<?= GST_PERCENT ?>%)</label>
                    <input type="text" id="gstDisplay" class="form-control bg-light" readonly value="₹0.00">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Amount</label>
                    <input type="text" id="totalDisplay" class="form-control bg-light fw-bold text-primary" readonly value="₹0.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control"
                           value="<?= saClean($_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'))) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"
                              placeholder="e.g. Monthly subscription for June 2026"><?= saClean($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= SUPERADMIN_URL ?>/modules/billing/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-file-earmark-plus me-2"></i>Generate Invoice
                </button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<?php
$gstPct = GST_PERCENT;
$extraJs = <<<'JS'
<script>
const amtInput   = document.getElementById('amountInput');
const gstDisplay = document.getElementById('gstDisplay');
const totDisplay = document.getElementById('totalDisplay');
const planSelect = document.getElementById('planSelect');

function fmt(n){ return '₹' + parseFloat(n).toFixed(2).replace(/\d(?=(\d{3})+\.)/g,'$&,'); }

function recalc() {
    const amt = parseFloat(amtInput.value) || 0;
    const gst = Math.round(amt * GSTPCT) / 100;
    gstDisplay.value = fmt(gst);
    totDisplay.value = fmt(amt + gst);
}

// Auto-fill amount from plan price
planSelect.addEventListener('change', function() {
    const price = this.options[this.selectedIndex]?.dataset?.price;
    if (price) { amtInput.value = parseFloat(price).toFixed(2); recalc(); }
});

amtInput.addEventListener('input', recalc);

// Auto-select plan when lab is chosen
document.getElementById('labSelect').addEventListener('change', function() {
    const planId = this.options[this.selectedIndex]?.dataset?.plan;
    if (planId) {
        planSelect.value = planId;
        planSelect.dispatchEvent(new Event('change'));
    }
});

recalc();
</script>
JS;
$extraJs = str_replace('GSTPCT', $gstPct, $extraJs);
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
