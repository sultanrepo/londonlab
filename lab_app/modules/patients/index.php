<?php
$pageTitle = 'Patients';
require_once __DIR__ . '/../../includes/header.php';

// Handle deactivate
if (isset($_GET['delete']) && labIsAdmin()) {
    $labDb->execute("UPDATE patients SET is_active=0 WHERE id=?", [(int)$_GET['delete']]);
    labSetFlash('success','Patient deactivated.');
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
}

$patients = $labDb->fetchAll("
    SELECT p.*, d.name as doctor_name,
           COUNT(DISTINCT o.id) as order_count,
           MAX(o.order_date) as last_visit
    FROM patients p
    LEFT JOIN doctors d ON p.doctor_id=d.id
    LEFT JOIN orders o ON p.id=o.patient_id
    WHERE p.is_active=1
    GROUP BY p.id ORDER BY p.created_at DESC
");
?>
<div class="page-header">
    <div><h2><i class="bi bi-people-fill me-2 text-primary"></i>Patients</h2><p>All registered patients</p></div>
    <?php if (labCanEdit()): ?>
    <a href="<?= LAB_APP_URL ?>/modules/patients/create.php?lab=<?= $slug ?>" class="btn btn-primary">
        <i class="bi bi-person-plus-fill me-2"></i>New Patient
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover dt-table mb-0">
                <thead>
                    <tr><th>#</th><th>Patient ID</th><th>Name</th><th>Gender</th><th>Phone</th><th>Referral</th><th>Orders</th><th>Last Visit</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $i => $p): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><code><?= labClean($p['patient_id']) ?></code></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--lc-primary-l);color:var(--lc-primary);display:grid;place-items:center;font-weight:700;font-size:13px;">
                                    <?= strtoupper(substr($p['name'],0,1)) ?>
                                </div>
                                <div class="fw-semibold"><?= labClean($p['name']) ?></div>
                            </div>
                        </td>
                        <td><?= labClean($p['gender']) ?></td>
                        <td><?= labClean($p['phone']) ?></td>
                        <td>
                            <?php if ($p['referral_type'] === 'doctor' && $p['doctor_name']): ?>
                            <span class="badge bg-info-subtle text-info" style="font-size:11px;">
                                🩺 <?= labClean($p['doctor_name']) ?>
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size:11px;">🚶 Self</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-primary-subtle text-primary"><?= $p['order_count'] ?></span></td>
                        <td><?= $p['last_visit'] ? date('d M Y',strtotime($p['last_visit'])) : '—' ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= LAB_APP_URL ?>/modules/patients/view.php?lab=<?= $slug ?>&id=<?= $p['id'] ?>"
                                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <?php if (labCanEdit()): ?>
                                <a href="<?= LAB_APP_URL ?>/modules/patients/edit.php?lab=<?= $slug ?>&id=<?= $p['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <a href="<?= LAB_APP_URL ?>/modules/orders/create.php?lab=<?= $slug ?>&patient_id=<?= $p['id'] ?>"
                                   class="btn btn-sm btn-outline-success" title="New Order"><i class="bi bi-plus-circle"></i></a>
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
