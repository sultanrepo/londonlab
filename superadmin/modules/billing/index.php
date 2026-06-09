<?php
$pageTitle = 'Billing & Invoices';
require_once __DIR__ . '/../../includes/header.php';

$db = MasterDB::getInstance();

// Handle mark paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    saVerifyCsrf();
    $iid    = (int)$_POST['invoice_id'];
    $method = $_POST['payment_method'] ?? 'upi';
    $ref    = trim($_POST['transaction_ref'] ?? '');
    $db->execute("UPDATE billing_invoices SET status='paid',paid_at=NOW(),payment_method=?,transaction_ref=? WHERE id=?",
        [$method, $ref, $iid]);

    // Activate lab subscription for 30 days from today (payment date)
    $inv = $db->fetch("SELECT * FROM billing_invoices WHERE id=?", [$iid]);
    if ($inv) {
        $startDate = date('Y-m-d');               // always today
        $newEnd    = date('Y-m-d', strtotime('+30 days'));
        $db->execute("UPDATE labs SET status='active',subscription_ends_at=?,last_payment_at=NOW() WHERE id=?",
            [$newEnd, $inv['lab_id']]);
        // Record subscription period starting today
        $db->execute("INSERT INTO subscriptions (lab_id,plan_id,start_date,end_date,amount,status) VALUES (?,?,?,?,?,'active')",
            [$inv['lab_id'], $inv['plan_id'], $startDate, $newEnd, $inv['amount']]);
    }
    saSetFlash('success', 'Invoice marked as paid. Subscription active until ' . date('d M Y', strtotime('+30 days')) . '.');
    header('Location: ' . SUPERADMIN_URL . '/modules/billing/index.php'); exit;
}

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT bi.*, l.name as lab_name, p.name as plan_name
        FROM billing_invoices bi
        JOIN labs l ON bi.lab_id = l.id
        LEFT JOIN plans p ON bi.plan_id = p.id
        WHERE 1=1";
$params = [];
if ($statusFilter) { $sql .= " AND bi.status=?"; $params[] = $statusFilter; }
$sql .= " ORDER BY bi.created_at DESC";
$invoices = $db->fetchAll($sql, $params);

$totalPaid    = $db->fetch("SELECT COALESCE(SUM(total_amount),0) as s FROM billing_invoices WHERE status='paid'")['s'];
$totalPending = $db->fetch("SELECT COALESCE(SUM(total_amount),0) as s FROM billing_invoices WHERE status='pending'")['s'];
$totalOverdue = $db->fetch("SELECT COALESCE(SUM(total_amount),0) as s FROM billing_invoices WHERE status='overdue'")['s'];
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-receipt me-2" style="color:#7c3aed;"></i>Billing & Invoices</h2>
        <p>Track all subscription payments</p>
    </div>
    <a href="<?= SUPERADMIN_URL ?>/modules/billing/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle-fill me-2"></i>Generate Invoice
    </a>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-info"><div class="label">Total Collected</div><div class="value"><?= money($totalPaid) ?></div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info"><div class="label">Pending</div><div class="value"><?= money($totalPending) ?></div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div class="stat-info"><div class="label">Overdue</div><div class="value"><?= money($totalOverdue) ?></div></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="?" class="btn btn-sm <?= !$statusFilter?'btn-dark':'btn-outline-secondary' ?>">All</a>
    <?php foreach (['pending','paid','overdue','cancelled'] as $s): ?>
    <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter===$s?'btn-dark':'btn-outline-secondary' ?>">
        <?= ucfirst($s) ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover dt-table mb-0">
                <thead>
                    <tr><th>Invoice No.</th><th>Lab</th><th>Plan</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><code><?= saClean($inv['invoice_no']) ?></code>
                            <br><small class="text-muted"><?= date('d M Y',strtotime($inv['created_at'])) ?></small></td>
                        <td class="fw-semibold"><?= saClean($inv['lab_name']) ?></td>
                        <td><?= saClean($inv['plan_name'] ?? '—') ?></td>
                        <td><strong><?= money($inv['total_amount']) ?></strong></td>
                        <td>
                            <?php if ($inv['due_date']): ?>
                            <span class="<?= strtotime($inv['due_date']) < time() && $inv['status']==='pending' ? 'text-danger fw-bold' : '' ?>">
                                <?= date('d M Y',strtotime($inv['due_date'])) ?>
                            </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><span class="badge-status status-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= SUPERADMIN_URL ?>/modules/billing/view.php?id=<?= $inv['id'] ?>"
                                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <?php if ($inv['status'] === 'pending' || $inv['status'] === 'overdue'): ?>
                                <button class="btn btn-sm btn-outline-success"
                                        data-bs-toggle="modal" data-bs-target="#paidModal"
                                        data-id="<?= $inv['id'] ?>" data-inv="<?= saClean($inv['invoice_no']) ?>"
                                        data-amt="<?= money($inv['total_amount']) ?>">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Mark Paid Modal -->
<div class="modal fade" id="paidModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= saCsrfToken() ?>">
                <input type="hidden" name="mark_paid" value="1">
                <input type="hidden" name="invoice_id" id="modalInvoiceId">
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Paid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Invoice: <strong id="modalInvNo"></strong><br>Amount: <strong id="modalAmt"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="upi">UPI</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Transaction Ref</label>
                        <input type="text" name="transaction_ref" class="form-control" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check me-2"></i>Confirm Paid</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
document.getElementById('paidModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modalInvoiceId').value = btn.dataset.id;
    document.getElementById('modalInvNo').textContent = btn.dataset.inv;
    document.getElementById('modalAmt').textContent   = btn.dataset.amt;
});
</script>
JS;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>