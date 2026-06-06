<?php
$pageTitle = 'Orders / Tests';
require_once __DIR__ . '/../../includes/header.php';

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT o.*, p.name as patient_name, p.patient_id as pid,
               COUNT(oi.id) as item_count,
               MAX(CASE WHEN py.id IS NOT NULL THEN 1 ELSE 0 END) as paid
        FROM orders o
        JOIN patients p ON o.patient_id=p.id
        LEFT JOIN order_items oi ON oi.order_id=o.id
        LEFT JOIN payments py ON py.order_id=o.id AND py.status='completed'
        WHERE 1=1";
$params = [];
if ($statusFilter) { $sql .= " AND o.status=?"; $params[] = $statusFilter; }
$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";
$orders = $labDb->fetchAll($sql, $params);
$statuses = ['pending','sample_collected','processing','completed','delivered','cancelled'];
?>
<div class="page-header">
    <div><h2><i class="bi bi-clipboard2-pulse-fill me-2 text-warning"></i>Orders / Tests</h2><p>All lab test orders</p></div>
    <?php if (labCanEdit()): ?>
    <a href="<?= LAB_APP_URL ?>/modules/orders/create.php?lab=<?= $slug ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle-fill me-2"></i>New Order
    </a>
    <?php endif; ?>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="?lab=<?= $slug ?>" class="btn btn-sm <?= !$statusFilter?'btn-dark':'btn-outline-secondary' ?>">All</a>
    <?php foreach ($statuses as $s): ?>
    <a href="?lab=<?= $slug ?>&status=<?= $s ?>" class="btn btn-sm <?= $statusFilter===$s?'btn-dark':'btn-outline-secondary' ?>">
        <?= ucwords(str_replace('_',' ',$s)) ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover dt-table mb-0">
                <thead>
                    <tr><th>Order No.</th><th>Patient</th><th>Date</th><th>Tests</th><th>Amount</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><code><?= labClean($o['order_no']) ?></code></td>
                        <td>
                            <div class="fw-semibold"><?= labClean($o['patient_name']) ?></div>
                            <small class="text-muted"><?= labClean($o['pid']) ?></small>
                        </td>
                        <td><?= date('d M Y',strtotime($o['order_date'])) ?></td>
                        <td><span class="badge bg-info-subtle text-info"><?= $o['item_count'] ?></span></td>
                        <td><strong><?= labMoney($o['net_amount']) ?></strong></td>
                        <td>
                            <?= $o['paid']
                                ? '<span class="badge-status status-completed">Paid</span>'
                                : '<span class="badge-status status-pending">Unpaid</span>' ?>
                        </td>
                        <td><span class="badge-status status-<?= $o['status'] ?>"><?= ucwords(str_replace('_',' ',$o['status'])) ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= LAB_APP_URL ?>/modules/orders/view.php?lab=<?= $slug ?>&id=<?= $o['id'] ?>"
                                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
