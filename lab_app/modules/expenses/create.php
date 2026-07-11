<?php
$pageTitle = 'Add Expense';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('expenses');
$cats    = ['reagents','equipment','salary','rent','utilities','maintenance','consumables','other'];
$methods = ['cash','card','bank_transfer','cheque'];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    labVerifyCsrf();
    $title  = trim($_POST['title']??''); $cat   = $_POST['category']??'other';
    $amount = (float)($_POST['amount']??0); $vendor = trim($_POST['vendor']??'');
    $inv    = trim($_POST['invoice_no']??''); $date  = $_POST['expense_date']??date('Y-m-d');
    $mth    = $_POST['payment_method']??'cash'; $notes = trim($_POST['notes']??'');
    if (!$title)      $errors[] = 'Title required.';
    if ($amount <= 0) $errors[] = 'Amount must be > 0.';
    if (empty($errors)) {
        $labDb->execute("INSERT INTO expenses (category,title,amount,vendor,invoice_no,expense_date,payment_method,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)",
            [$cat,$title,$amount,$vendor,$inv,$date,$mth,$notes,$labUser['id']]);
        labSetFlash('success','Expense recorded!');
        header('Location: '.LAB_APP_URL.'/modules/expenses/index.php?lab='.$slug); exit;
    }
}
?>
<div class="page-header">
    <div><h2><i class="bi bi-receipt me-2 text-danger"></i>Add Expense</h2></div>
    <a href="<?= LAB_APP_URL ?>/modules/expenses/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>
<?php if ($errors): ?><div class="alert alert-danger mb-4"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= labClean($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row justify-content-center"><div class="col-lg-8">
<div class="card">
    <div class="card-header"><i class="bi bi-cash"></i> Expense Details</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required value="<?= labClean($_POST['title']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">Category</label>
                    <select name="category" class="form-select"><?php foreach($cats as $c): ?><option value="<?= $c ?>" <?= ($_POST['category']??'other')===$c?'selected':'' ?>><?= ucfirst($c) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Amount (₹) *</label><input type="number" name="amount" class="form-control" min="0.01" step="0.01" required value="<?= labClean($_POST['amount']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?= labClean($_POST['expense_date']??date('Y-m-d')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select"><?php foreach($methods as $m): ?><option value="<?= $m ?>" <?= ($_POST['payment_method']??'cash')===$m?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Vendor</label><input type="text" name="vendor" class="form-control" value="<?= labClean($_POST['vendor']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Invoice No.</label><input type="text" name="invoice_no" class="form-control" value="<?= labClean($_POST['invoice_no']??'') ?>"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= labClean($_POST['notes']??'') ?></textarea></div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= LAB_APP_URL ?>/modules/expenses/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Save Expense</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>