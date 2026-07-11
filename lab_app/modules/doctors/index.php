<?php
$pageTitle = 'Doctors';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('doctors');

if (isset($_GET['toggle'])) {
    $did  = (int)$_GET['toggle'];
    $curr = $labDb->fetch("SELECT is_active FROM doctors WHERE id=?",[$did]);
    $labDb->execute("UPDATE doctors SET is_active=? WHERE id=?",[$curr['is_active']?0:1,$did]);
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
}

$doctors = $labDb->fetchAll("
    SELECT d.*,
           COUNT(DISTINCT p.id) as total_patients,
           COALESCE(SUM(CASE WHEN dc.status='pending' THEN dc.commission_amount ELSE 0 END),0) as pending_comm,
           COALESCE(SUM(CASE WHEN dc.status='paid'    THEN dc.commission_amount ELSE 0 END),0) as paid_comm
    FROM doctors d
    LEFT JOIN patients p ON p.doctor_id=d.id AND p.is_active=1
    LEFT JOIN doctor_commissions dc ON dc.doctor_id=d.id
    GROUP BY d.id ORDER BY d.name
");
$totalPending = array_sum(array_column($doctors,'pending_comm'));
?>
<div class="page-header">
    <div><h2><i class="bi bi-person-vcard-fill me-2" style="color:#7c3aed;"></i>Doctors</h2><p>Referring doctors and commissions</p></div>
    <?php if (labCanEdit()): ?>
    <a href="<?= LAB_APP_URL ?>/modules/doctors/create.php?lab=<?= $slug ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle-fill me-2"></i>Add Doctor
    </a>
    <?php endif; ?>
</div>

<?php if ($totalPending > 0): ?>
<div class="alert mb-4 d-flex align-items-center gap-3" style="background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;padding:14px 18px;">
    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
    <div><strong><?= labMoney($totalPending) ?> total commission pending</strong> — visit each doctor to mark as paid.</div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover dt-table mb-0">
                <thead><tr><th>#</th><th>Doctor</th><th>Specialty</th><th>Phone</th><th>Commission</th><th>Patients</th><th>Pending</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($doctors as $i=>$d): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:34px;height:34px;border-radius:50%;background:#ede9fe;color:#7c3aed;font-weight:700;font-size:13px;display:grid;place-items:center;flex-shrink:0;">
                                    <?= strtoupper(substr($d['name'],4,1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= labClean($d['name']) ?></div>
                                    <?php if ($d['clinic_name']): ?><small class="text-muted"><?= labClean($d['clinic_name']) ?></small><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= labClean($d['specialty']??'—') ?></td>
                        <td><?= labClean($d['phone']??'—') ?></td>
                        <td>
                            <?php if ($d['commission_type']==='percentage'): ?>
                            <span class="badge bg-info-subtle text-info"><?= number_format($d['commission_value'],1) ?>%</span>
                            <?php else: ?>
                            <span class="badge bg-purple-subtle" style="background:#ede9fe;color:#7c3aed;"><?= labMoney($d['commission_value']) ?> flat</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-primary-subtle text-primary"><?= $d['total_patients'] ?></span></td>
                        <td><?= $d['pending_comm']>0 ? '<strong class="text-warning">'.labMoney($d['pending_comm']).'</strong>' : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $d['is_active'] ? '<span class="badge-status status-completed">Active</span>' : '<span class="badge-status status-cancelled">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= LAB_APP_URL ?>/modules/doctors/view.php?lab=<?= $slug ?>&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <?php if (labCanEdit()): ?>
                                <a href="<?= LAB_APP_URL ?>/modules/doctors/edit.php?lab=<?= $slug ?>&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <a href="?lab=<?= $slug ?>&toggle=<?= $d['id'] ?>" class="btn btn-sm btn-outline-<?= $d['is_active']?'warning':'success' ?>" onclick="return confirm('Toggle status?')">
                                    <i class="bi bi-<?= $d['is_active']?'pause':'play' ?>-circle"></i>
                                </a>
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
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>