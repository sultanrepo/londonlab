<?php
$pageTitle = 'Doctor Profile';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('doctors');

$id     = (int)($_GET['id'] ?? 0);
$doctor = $labDb->fetch("SELECT * FROM doctors WHERE id=?", [$id]);
if (!$doctor) { labSetFlash('error','Doctor not found.'); header('Location: '.LAB_APP_URL.'/modules/doctors/index.php?lab='.$slug); exit; }

// Mark paid
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mark_paid'])) {
    labVerifyCsrf();
    $ids   = array_map('intval', $_POST['comm_ids'] ?? []);
    $notes = trim($_POST['payment_notes'] ?? '');
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $labDb->execute("UPDATE doctor_commissions SET status='paid',paid_at=NOW(),payment_notes=? WHERE id IN ($ph) AND doctor_id=?",
            array_merge([$notes], $ids, [$id]));
        labSetFlash('success', count($ids).' commission(s) marked as paid.');
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id); exit;
    }
}

// Handle edit POST
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_doctor'])) {
    labVerifyCsrf();
    $labDb->execute("UPDATE doctors SET name=?,specialty=?,clinic_name=?,phone=?,email=?,address=?,commission_type=?,commission_value=? WHERE id=?",
        [trim($_POST['name']??''), trim($_POST['specialty']??''), trim($_POST['clinic_name']??''),
         trim($_POST['phone']??''), trim($_POST['email']??''), trim($_POST['address']??''),
         $_POST['commission_type']??'percentage', (float)($_POST['commission_value']??0), $id]);
    labSetFlash('success','Doctor updated!');
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id); exit;
}

$commissions = $labDb->fetchAll("
    SELECT dc.*, o.order_no, o.status as order_status, p.name as patient_name
    FROM doctor_commissions dc
    JOIN orders o ON dc.order_id = o.id
    JOIN patients p ON o.patient_id = p.id
    WHERE dc.doctor_id = ?
    ORDER BY dc.created_at DESC
", [$id]);
// Only show commissions where BOTH the commission and its parent order are not cancelled
$pending      = array_filter($commissions, fn($c) => $c['status'] === 'pending'   && $c['order_status'] !== 'cancelled');
$pendingTotal = array_sum(array_column(array_values($pending), 'commission_amount'));
$paidTotal    = array_sum(array_column(array_filter($commissions, fn($c) => $c['status'] === 'paid'), 'commission_amount'));
$patients     = $labDb->fetchAll("SELECT p.*,COUNT(o.id) as orders FROM patients p LEFT JOIN orders o ON o.patient_id=p.id WHERE p.doctor_id=? AND p.is_active=1 GROUP BY p.id ORDER BY p.created_at DESC LIMIT 10",[$id]);
$pageTitle    = labClean($doctor['name']);
?>
<div class="page-header">
    <div><h2><i class="bi bi-person-vcard-fill me-2" style="color:#7c3aed;"></i><?= labClean($doctor['name']) ?></h2>
    <p><?= labClean($doctor['specialty']??'') ?></p></div>
    <a href="<?= LAB_APP_URL ?>/modules/doctors/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <!-- Profile Card -->
        <div class="card mb-4">
            <div class="card-body text-center py-4">
                <div style="width:72px;height:72px;border-radius:50%;background:#ede9fe;color:#7c3aed;font-size:30px;font-weight:700;display:grid;place-items:center;margin:0 auto 12px;">
                    <?= strtoupper(substr($doctor['name'],4,1)) ?>
                </div>
                <h5 class="mb-1"><?= labClean($doctor['name']) ?></h5>
                <?php if ($doctor['specialty']): ?><span class="badge bg-info-subtle text-info"><?= labClean($doctor['specialty']) ?></span><?php endif; ?>
                <div class="divider"></div>
                <?php foreach (['Clinic'=>$doctor['clinic_name']??'—','Phone'=>$doctor['phone']??'—','Email'=>$doctor['email']??'—'] as $k=>$v): ?>
                <div class="d-flex justify-content-between py-2 border-bottom"><small class="text-muted"><?= $k ?></small><small class="fw-semibold"><?= labClean($v) ?></small></div>
                <?php endforeach; ?>
                <div class="row g-2 mt-3">
                    <div class="col-6"><div class="p-2 rounded-2" style="background:#fef3c7;"><div class="fw-bold text-warning"><?= labMoney($pendingTotal) ?></div><small class="text-muted">Pending</small></div></div>
                    <div class="col-6"><div class="p-2 rounded-2" style="background:#dcfce7;"><div class="fw-bold text-success"><?= labMoney($paidTotal) ?></div><small class="text-muted">Paid Out</small></div></div>
                </div>
            </div>
        </div>
        <!-- Commission Rate -->
        <div class="card">
            <div class="card-header"><i class="bi bi-percent"></i> Commission Rate</div>
            <div class="card-body text-center py-3">
                <div style="font-size:42px;font-weight:800;color:#7c3aed;line-height:1;">
                    <?php if ($doctor['commission_type']==='percentage'): ?>
                    <?= number_format($doctor['commission_value'],1) ?><span style="font-size:20px;">%</span>
                    <?php else: ?>
                    <span style="font-size:20px;">₹</span><?= number_format($doctor['commission_value'],0) ?>
                    <?php endif; ?>
                </div>
                <div class="text-muted small mt-1"><?= $doctor['commission_type']==='percentage'?'of net order':'flat per order' ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Pending Commissions -->
        <?php if (!empty($pending)): ?>
        <div class="card mb-4" style="border-color:#f59e0b;">
            <div class="card-header" style="background:#fef3c7;border-color:#f59e0b;">
                <i class="bi bi-hourglass-split text-warning"></i>
                <span class="fw-bold">Pending Commissions</span>
                <span class="badge bg-warning text-dark ms-2"><?= count($pending) ?></span>
                <strong class="ms-auto"><?= labMoney($pendingTotal) ?></strong>
            </div>
            <div class="card-body p-0">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
                    <input type="hidden" name="mark_paid" value="1">
                    <table class="table mb-0">
                        <thead><tr><th><input type="checkbox" id="selAll" class="form-check-input"></th><th>Order</th><th>Patient</th><th>Order Amt</th><th>Commission</th></tr></thead>
                        <tbody>
                            <?php foreach ($pending as $c): ?>
                            <tr>
                                <td><input type="checkbox" name="comm_ids[]" value="<?= $c['id'] ?>" class="form-check-input comm-chk"></td>
                                <td><code><?= labClean($c['order_no']) ?></code></td>
                                <td><?= labClean($c['patient_name']) ?></td>
                                <td><?= labMoney($c['order_amount']) ?></td>
                                <td><strong class="text-warning"><?= labMoney($c['commission_amount']) ?></strong><br>
                                    <small class="text-muted"><?= $c['commission_type']==='percentage'?$c['commission_rate'].'%':'₹'.number_format($c['commission_rate'],2).' flat' ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="p-3 border-top d-flex gap-3 align-items-center">
                        <input type="text" name="payment_notes" class="form-control" placeholder="Payment notes (e.g. Paid via UPI)">
                        <button type="submit" class="btn btn-success text-nowrap" onclick="return confirm('Mark selected as paid?')">
                            <i class="bi bi-check-circle-fill me-2"></i>Mark Paid
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Commission History -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-clock-history"></i> Commission History</div>
            <div class="card-body p-0">
                <?php if (empty($commissions)): ?>
                <p class="text-center text-muted py-4 small">No commissions yet.</p>
                <?php else: ?>
                <table class="table dt-table mb-0">
                    <thead><tr><th>Order</th><th>Patient</th><th>Order Amt</th><th>Commission</th><th>Status</th><th>Paid On</th></tr></thead>
                    <tbody>
                        <?php foreach ($commissions as $c): ?>
                        <tr>
                            <td><code><?= labClean($c['order_no']) ?></code></td>
                            <td><?= labClean($c['patient_name']) ?></td>
                            <td><?= labMoney($c['order_amount']) ?></td>
                            <td><strong><?= labMoney($c['commission_amount']) ?></strong></td>
                            <td><span class="badge-status status-<?= $c['status']==='paid'?'completed':($c['status']==='pending'?'pending':'cancelled') ?>"><?= ucfirst($c['status']) ?></span>
                                <?php if ($c['order_status'] === 'cancelled'): ?>
                                <span class="badge bg-danger-subtle text-danger ms-1" title="Parent order was cancelled">Order Cancelled</span>
                                <?php endif; ?></td>
                            <td><?= $c['paid_at'] ? date('d M Y',strtotime($c['paid_at'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Referred Patients -->
        <div class="card">
            <div class="card-header"><i class="bi bi-people-fill"></i> Referred Patients</div>
            <div class="card-body p-0">
                <?php if (empty($patients)): ?>
                <p class="text-center text-muted py-4 small">No patients referred yet.</p>
                <?php else: ?>
                <table class="table mb-0">
                    <thead><tr><th>Patient</th><th>ID</th><th>Phone</th><th>Orders</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($patients as $p): ?>
                        <tr>
                            <td class="fw-semibold"><?= labClean($p['name']) ?></td>
                            <td><code><?= labClean($p['patient_id']) ?></code></td>
                            <td><?= labClean($p['phone']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary"><?= $p['orders'] ?></span></td>
                            <td><a href="<?= LAB_APP_URL ?>/modules/patients/view.php?lab=<?= $slug ?>&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
const sa = document.getElementById('selAll');
if(sa) sa.addEventListener('change',function(){ document.querySelectorAll('.comm-chk').forEach(c=>c.checked=this.checked); });
</script>
JS;
?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>