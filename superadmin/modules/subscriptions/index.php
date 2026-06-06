<?php
$pageTitle = 'Subscriptions';
require_once __DIR__ . '/../../includes/header.php';

$db = MasterDB::getInstance();

$subscriptions = $db->fetchAll("
    SELECT s.*, l.name as lab_name, l.status as lab_status, p.name as plan_name, p.price
    FROM subscriptions s
    JOIN labs l ON s.lab_id = l.id
    JOIN plans p ON s.plan_id = p.id
    ORDER BY s.created_at DESC
");

// Labs expiring within 7 days
$expiring = $db->fetchAll("
    SELECT l.*, p.name as plan_name
    FROM labs l LEFT JOIN plans p ON l.plan_id=p.id
    WHERE l.status IN ('active','trial')
      AND (
          (l.status='trial' AND l.trial_ends_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
          OR (l.status='active' AND l.subscription_ends_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
      )
    ORDER BY COALESCE(l.trial_ends_at, l.subscription_ends_at) ASC
");
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-calendar-check me-2" style="color:#7c3aed;"></i>Subscriptions</h2>
        <p>Track all lab subscription periods</p>
    </div>
    <a href="<?= SUPERADMIN_URL ?>/modules/subscriptions/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle-fill me-2"></i>Add Subscription
    </a>
</div>

<!-- Expiring Soon Alert -->
<?php if (!empty($expiring)): ?>
<div class="card mb-4" style="border-color:#ef4444;">
    <div class="card-header" style="background:#fee2e2;border-color:#ef4444;color:#991b1b;">
        <i class="bi bi-alarm-fill me-2"></i>
        <strong><?= count($expiring) ?> lab(s) expiring within 7 days — action needed!</strong>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>Lab</th><th>Plan</th><th>Status</th><th>Expires</th><th>Days Left</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($expiring as $lab): ?>
                <?php $expDate = $lab['status']==='trial' ? $lab['trial_ends_at'] : $lab['subscription_ends_at']; ?>
                <tr>
                    <td class="fw-semibold"><?= saClean($lab['name']) ?></td>
                    <td><?= saClean($lab['plan_name'] ?? '—') ?></td>
                    <td><span class="badge-status status-<?= $lab['status'] ?>"><?= ucfirst($lab['status']) ?></span></td>
                    <td><?= date('d M Y', strtotime($expDate)) ?></td>
                    <td>
                        <span class="fw-bold <?= daysLeft($expDate) <= 3 ? 'text-danger' : 'text-warning' ?>">
                            <?= daysLeft($expDate) ?> day(s)
                        </span>
                    </td>
                    <td>
                        <a href="<?= SUPERADMIN_URL ?>/modules/billing/create.php?lab_id=<?= $lab['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-receipt me-1"></i>Invoice
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover dt-table mb-0">
                <thead>
                    <tr><th>Lab</th><th>Plan</th><th>Price</th><th>From</th><th>To</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td class="fw-semibold"><?= saClean($sub['lab_name']) ?></td>
                        <td><?= saClean($sub['plan_name']) ?></td>
                        <td><?= money($sub['price']) ?>/mo</td>
                        <td><?= date('d M Y', strtotime($sub['start_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($sub['end_date'])) ?></td>
                        <td>
                            <span class="badge-status status-<?= $sub['status'] ?>">
                                <?= ucfirst($sub['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($subscriptions)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No subscriptions yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
