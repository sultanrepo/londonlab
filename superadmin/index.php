<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = MasterDB::getInstance();

// ── KPI STATS ──
$totalLabs      = $db->fetch("SELECT COUNT(*) as c FROM labs")['c'];
$activeLabs     = $db->fetch("SELECT COUNT(*) as c FROM labs WHERE status='active'")['c'];
$trialLabs      = $db->fetch("SELECT COUNT(*) as c FROM labs WHERE status='trial'")['c'];
$suspendedLabs  = $db->fetch("SELECT COUNT(*) as c FROM labs WHERE status='suspended'")['c'];
$totalRevenue   = $db->fetch("SELECT COALESCE(SUM(total_amount),0) as s FROM billing_invoices WHERE status='paid'")['s'];
$monthRevenue   = $db->fetch("SELECT COALESCE(SUM(total_amount),0) as s FROM billing_invoices WHERE status='paid' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())")['s'];
$pendingAmount  = $db->fetch("SELECT COALESCE(SUM(total_amount),0) as s FROM billing_invoices WHERE status IN ('pending','overdue')")['s'];
$overdueCount   = $db->fetch("SELECT COUNT(*) as c FROM billing_invoices WHERE status='overdue'")['c'];

// ── EXPIRING TRIALS (next 7 days) ──
$expiringTrials = $db->fetchAll("
    SELECT l.*, p.name as plan_name
    FROM labs l LEFT JOIN plans p ON l.plan_id=p.id
    WHERE l.status='trial' AND l.trial_ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY l.trial_ends_at ASC
");

// ── RECENT LABS ──
$recentLabs = $db->fetchAll("
    SELECT l.*, p.name as plan_name
    FROM labs l LEFT JOIN plans p ON l.plan_id=p.id
    ORDER BY l.created_at DESC LIMIT 8
");

// ── MONTHLY REVENUE (last 6 months) ──
$monthlyRevData = $db->fetchAll("
    SELECT DATE_FORMAT(paid_at,'%b %Y') as month,
           DATE_FORMAT(paid_at,'%Y-%m') as ym,
           SUM(total_amount) as revenue,
           COUNT(*) as invoices
    FROM billing_invoices
    WHERE status='paid' AND paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym ASC
");

// ── LABS BY PLAN ──
$planDist = $db->fetchAll("
    SELECT p.name, p.slug, COUNT(l.id) as cnt
    FROM plans p LEFT JOIN labs l ON l.plan_id=p.id AND l.status IN ('active','trial')
    GROUP BY p.id ORDER BY p.price ASC
");
?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-building"></i></div>
            <div class="stat-info">
                <div class="label">Total Labs</div>
                <div class="value"><?= $totalLabs ?></div>
                <div class="sub"><?= $activeLabs ?> active · <?= $trialLabs ?> trial</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-info">
                <div class="label">This Month</div>
                <div class="value"><?= money($monthRevenue) ?></div>
                <div class="sub">Total: <?= money($totalRevenue) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info">
                <div class="label">Pending Revenue</div>
                <div class="value"><?= money($pendingAmount) ?></div>
                <div class="sub"><?= $overdueCount ?> overdue invoice(s)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-info">
                <div class="label">Expiring Trials</div>
                <div class="value"><?= count($expiringTrials) ?></div>
                <div class="sub">Within next 7 days</div>
            </div>
        </div>
    </div>
</div>

<!-- Expiring Trials Alert -->
<?php if (!empty($expiringTrials)): ?>
<div class="alert mb-4 d-flex align-items-start gap-3"
     style="background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;padding:16px 20px;">
    <i class="bi bi-exclamation-triangle-fill text-warning fs-5 mt-1"></i>
    <div class="flex-grow-1">
        <strong>⚠️ <?= count($expiringTrials) ?> lab trial(s) expiring soon — follow up now!</strong>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php foreach ($expiringTrials as $lab): ?>
            <a href="<?= SUPERADMIN_URL ?>/modules/labs/view.php?id=<?= $lab['id'] ?>"
               class="badge bg-warning text-dark text-decoration-none px-3 py-2" style="font-size:12px;">
                <?= saClean($lab['name']) ?> — <?= daysLeft($lab['trial_ends_at']) ?> day(s) left
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart-fill text-success"></i> Monthly Revenue</div>
            <div class="card-body"><canvas id="revenueChart" height="100"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill text-purple"></i> Labs by Plan</div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="planChart" width="200" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-lightning-fill text-warning"></i> Quick Actions</div>
    <div class="card-body d-flex flex-wrap gap-3">
        <a href="<?= SUPERADMIN_URL ?>/modules/labs/create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle-fill me-2"></i>Add New Lab
        </a>
        <a href="<?= SUPERADMIN_URL ?>/modules/billing/create.php" class="btn btn-outline-primary">
            <i class="bi bi-receipt me-2"></i>Generate Invoice
        </a>
        <a href="<?= SUPERADMIN_URL ?>/modules/subscriptions/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-calendar-check me-2"></i>Manage Subscriptions
        </a>
        <a href="<?= SUPERADMIN_URL ?>/modules/reports/index.php" class="btn btn-outline-info">
            <i class="bi bi-bar-chart me-2"></i>Revenue Reports
        </a>
    </div>
</div>

<!-- Recent Labs -->
<div class="card">
    <div class="card-header justify-content-between">
        <span><i class="bi bi-clock-history"></i> Recently Added Labs</span>
        <a href="<?= SUPERADMIN_URL ?>/modules/labs/index.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Lab Name</th><th>Owner</th><th>Plan</th><th>Status</th><th>Joined</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLabs as $lab): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= saClean($lab['name']) ?></div>
                            <small class="text-muted"><?= saClean($lab['city'] ?? '') ?></small>
                        </td>
                        <td>
                            <div><?= saClean($lab['owner_name']) ?></div>
                            <small class="text-muted"><?= saClean($lab['owner_email']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?= planBadgeColor($lab['slug'] ?? '') ?>-subtle
                                text-<?= planBadgeColor($lab['slug'] ?? '') ?> px-2">
                                <?= saClean($lab['plan_name'] ?? 'Trial') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status status-<?= $lab['status'] ?>">
                                <?= ucfirst($lab['status']) ?>
                            </span>
                        </td>
                        <td><?= date('d M Y', strtotime($lab['created_at'])) ?></td>
                        <td>
                            <a href="<?= SUPERADMIN_URL ?>/modules/labs/view.php?id=<?= $lab['id'] ?>"
                               class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$revLabels  = json_encode(array_column($monthlyRevData, 'month'));
$revValues  = json_encode(array_map(fn($r)=>(float)$r['revenue'], $monthlyRevData));
$planLabels = json_encode(array_column($planDist, 'name'));
$planValues = json_encode(array_map(fn($r)=>(int)$r['cnt'], $planDist));

$extraJs = <<<'JS'
<script>
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: REVLABELS,
        datasets: [{ label: 'Revenue (₹)', data: REVVALUES,
            backgroundColor: 'rgba(124,58,237,0.15)', borderColor: '#7c3aed',
            borderWidth: 2, borderRadius: 8 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}},
        scales: { y:{beginAtZero:true,ticks:{callback:v=>'₹'+v.toLocaleString()}},
                  x:{grid:{display:false}} } }
});
new Chart(document.getElementById('planChart'), {
    type: 'doughnut',
    data: { labels: PLANLABELS, datasets: [{ data: PLANVALUES,
        backgroundColor: ['#94a3b8','#7c3aed','#f59e0b'], borderWidth:2, borderColor:'#fff' }] },
    options: { responsive:true, cutout:'65%', plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}} }
});
</script>
JS;

$extraJs = str_replace(
    ['REVLABELS','REVVALUES','PLANLABELS','PLANVALUES'],
    [$revLabels, $revValues, $planLabels, $planValues],
    $extraJs
);
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
