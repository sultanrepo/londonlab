<?php
$pageTitle = 'All Labs';
require_once __DIR__ . '/../../includes/header.php';

$db = MasterDB::getInstance();

// Handle suspend / activate / cancel
if (isset($_GET['action'], $_GET['id'])) {
    $lid    = (int)$_GET['id'];
    $action = $_GET['action'];
    $statusMap = ['suspend'=>'suspended','activate'=>'active','cancel'=>'cancelled'];
    if (isset($statusMap[$action])) {
        $db->execute("UPDATE labs SET status=? WHERE id=?", [$statusMap[$action], $lid]);
        saSetFlash('success', "Lab status updated to " . $statusMap[$action]);
    }
    header('Location: ' . SUPERADMIN_URL . '/modules/labs/index.php'); exit;
}

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT l.*, p.name as plan_name, p.slug as plan_slug FROM labs l LEFT JOIN plans p ON l.plan_id=p.id WHERE 1=1";
$params = [];
if ($statusFilter) { $sql .= " AND l.status=?"; $params[] = $statusFilter; }
$sql .= " ORDER BY l.created_at DESC";
$labs = $db->fetchAll($sql, $params);

$statuses = ['trial','active','suspended','cancelled'];
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-building me-2" style="color:#7c3aed;"></i>All Labs</h2>
        <p>Manage all registered laboratories</p>
    </div>
    <a href="<?= SUPERADMIN_URL ?>/modules/labs/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle-fill me-2"></i>Add New Lab
    </a>
</div>

<!-- Filter pills -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="?" class="btn btn-sm <?= !$statusFilter?'btn-dark':'btn-outline-secondary' ?>">All (<?= count($labs) ?>)</a>
    <?php foreach ($statuses as $s): ?>
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
                    <tr>
                        <th>#</th>
                        <th>Lab Name</th>
                        <th>Owner</th>
                        <th>City</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Trial / Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($labs as $i => $lab): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <div class="fw-semibold"><?= saClean($lab['name']) ?></div>
                            <small class="text-success fw-semibold">Lab ID: <?= saClean($lab['slug']) ?></small><br>
                            <small class="text-muted" style="font-family:monospace;"><?= saClean($lab['db_name']) ?></small>
                        </td>
                        <td>
                            <div><?= saClean($lab['owner_name']) ?></div>
                            <small class="text-muted"><?= saClean($lab['owner_phone'] ?? '') ?></small>
                        </td>
                        <td><?= saClean($lab['city'] ?? '—') ?></td>
                        <td>
                            <span class="badge bg-<?= planBadgeColor($lab['plan_slug'] ?? '') ?>-subtle
                                text-<?= planBadgeColor($lab['plan_slug'] ?? '') ?>">
                                <?= saClean($lab['plan_name'] ?? 'None') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status status-<?= $lab['status'] ?>">
                                <?= ucfirst($lab['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($lab['status'] === 'trial' && $lab['trial_ends_at']): ?>
                                <?php $days = daysLeft($lab['trial_ends_at']); ?>
                                <span class="<?= $days <= 3 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= $days ?> day(s) left
                                </span>
                                <br><small class="text-muted"><?= date('d M Y', strtotime($lab['trial_ends_at'])) ?></small>
                            <?php elseif ($lab['subscription_ends_at']): ?>
                                <?php $days = daysLeft($lab['subscription_ends_at']); ?>
                                <span class="<?= $days <= 7 ? 'text-warning' : 'text-muted' ?>">
                                    <?= $days ?> day(s)
                                </span>
                                <br><small class="text-muted"><?= date('d M Y', strtotime($lab['subscription_ends_at'])) ?></small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= SUPERADMIN_URL ?>/modules/labs/view.php?id=<?= $lab['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= SUPERADMIN_URL ?>/modules/labs/edit.php?id=<?= $lab['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($lab['status'] === 'active' || $lab['status'] === 'trial'): ?>
                                <a href="?action=suspend&id=<?= $lab['id'] ?>"
                                   class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Suspend"
                                   onclick="return confirm('Suspend this lab? They will lose access.')">
                                    <i class="bi bi-pause-circle"></i>
                                </a>
                                <?php elseif ($lab['status'] === 'suspended'): ?>
                                <a href="?action=activate&id=<?= $lab['id'] ?>"
                                   class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Activate"
                                   onclick="return confirm('Activate this lab?')">
                                    <i class="bi bi-play-circle"></i>
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
