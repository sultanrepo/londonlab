<?php
$pageTitle = 'Patient Details';
require_once __DIR__ . '/../../includes/header.php';

$id      = (int)($_GET['id'] ?? 0);
$patient = $labDb->fetch("SELECT p.*, d.name as doctor_name FROM patients p LEFT JOIN doctors d ON p.doctor_id=d.id WHERE p.id=?", [$id]);
if (!$patient) { labSetFlash('error','Patient not found.'); header('Location: '.LAB_APP_URL.'/modules/patients/index.php?lab='.$slug); exit; }

$orders    = $labDb->fetchAll("SELECT o.*, GROUP_CONCAT(t.name SEPARATOR ', ') as test_names FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id LEFT JOIN tests t ON oi.test_id=t.id WHERE o.patient_id=? GROUP BY o.id ORDER BY o.created_at DESC", [$id]);
$totalPaid = (float)$labDb->fetch("SELECT COALESCE(SUM(py.amount),0) as s FROM payments py JOIN orders o ON py.order_id=o.id WHERE o.patient_id=? AND py.status='completed'", [$id])['s'];
$pageTitle = labClean($patient['name']);
?>
<div class="page-header">
    <div>
        <h2><i class="bi bi-person-badge-fill me-2 text-primary"></i><?= labClean($patient['name']) ?></h2>
        <p>Patient ID: <code><?= labClean($patient['patient_id']) ?></code></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (labCanEdit()): ?>
        <a href="<?= LAB_APP_URL ?>/modules/orders/create.php?lab=<?= $slug ?>&patient_id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>New Order
        </a>
        <a href="<?= LAB_APP_URL ?>/modules/patients/edit.php?lab=<?= $slug ?>&id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil me-2"></i>Edit
        </a>
        <?php endif; ?>
        <a href="<?= LAB_APP_URL ?>/modules/patients/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center pt-4">
                <div style="width:80px;height:80px;border-radius:50%;background:var(--lc-primary-l);color:var(--lc-primary);font-size:32px;font-weight:700;display:grid;place-items:center;margin:0 auto 16px;">
                    <?= strtoupper(substr($patient['name'],0,1)) ?>
                </div>
                <h4 class="mb-1"><?= labClean($patient['name']) ?></h4>
                <span class="badge bg-secondary-subtle text-secondary"><?= labClean($patient['blood_group']) ?></span>
                <div class="divider"></div>
                <?php
                $info = [
                    'Gender'     => $patient['gender'],
                    'Age'        => $patient['age'] ? $patient['age'] . ' yrs' : '—',
                    'Phone'      => $patient['phone'],
                    'Email'      => $patient['email'] ?: '—',
                    'Referral'   => ucfirst($patient['referral_type'] ?? 'self'),
                    'Referred By'=> $patient['doctor_name'] ?? ($patient['referred_by'] ?: '—'),
                    'Registered' => date('d M Y',strtotime($patient['created_at'])),
                ];
                foreach ($info as $k=>$v): ?>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <small class="text-muted"><?= $k ?></small>
                    <small class="fw-semibold"><?= labClean($v) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-6"><div class="card text-center p-3"><div class="fs-4 fw-bold text-primary"><?= count($orders) ?></div><small class="text-muted">Orders</small></div></div>
            <div class="col-6"><div class="card text-center p-3"><div class="fs-5 fw-bold text-success"><?= labMoney($totalPaid) ?></div><small class="text-muted">Total Paid</small></div></div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> Order History</div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                <div class="text-center py-5 text-muted"><i class="bi bi-clipboard2-x fs-1 d-block mb-3"></i>No orders yet.</div>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead><tr><th>Order No.</th><th>Date</th><th>Tests</th><th>Amount</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><code><?= labClean($o['order_no']) ?></code></td>
                            <td><?= date('d M Y',strtotime($o['order_date'])) ?></td>
                            <td><small><?= $o['test_names'] ? labClean(mb_substr($o['test_names'],0,40)).(mb_strlen($o['test_names'])>40?'…':'') : '—' ?></small></td>
                            <td><?= labMoney($o['net_amount']) ?></td>
                            <td><span class="badge-status status-<?= $o['status'] ?>"><?= ucwords(str_replace('_',' ',$o['status'])) ?></span></td>
                            <td><a href="<?= LAB_APP_URL ?>/modules/orders/view.php?lab=<?= $slug ?>&id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>