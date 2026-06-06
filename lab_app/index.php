<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// KPI stats
$totalPatients  = $labDb->fetch("SELECT COUNT(*) as c FROM patients WHERE is_active=1")['c'];
$totalOrders    = $labDb->fetch("SELECT COUNT(*) as c FROM orders")['c'];
$totalRevenue   = (float)$labDb->fetch("SELECT COALESCE(SUM(amount),0) as s FROM payments WHERE status='completed'")['s'];
$totalExpenses  = (float)$labDb->fetch("SELECT COALESCE(SUM(amount),0) as s FROM expenses")['s'];
$netProfit      = $totalRevenue - $totalExpenses;
$pendingOrders  = $labDb->fetch("SELECT COUNT(*) as c FROM orders WHERE status IN ('pending','sample_collected','processing')")['c'];

$todayPatients  = $labDb->fetch("SELECT COUNT(*) as c FROM patients WHERE DATE(created_at)=CURDATE()")['c'];
$todayOrders    = $labDb->fetch("SELECT COUNT(*) as c FROM orders WHERE DATE(order_date)=CURDATE()")['c'];
$todayRevenue   = (float)$labDb->fetch("SELECT COALESCE(SUM(amount),0) as s FROM payments WHERE DATE(paid_at)=CURDATE() AND status='completed'")['s'];

// Monthly revenue chart
$monthlyRevenue = $labDb->fetchAll("
    SELECT DATE_FORMAT(paid_at,'%b %Y') as month, DATE_FORMAT(paid_at,'%Y-%m') as ym,
           SUM(amount) as revenue
    FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym ASC
");

// Recent orders
$recentOrders = $labDb->fetchAll("
    SELECT o.*, p.name as patient_name, p.patient_id as pid
    FROM orders o JOIN patients p ON o.patient_id=p.id
    ORDER BY o.created_at DESC LIMIT 8
");

// Test distribution
$catDist = $labDb->fetchAll("
    SELECT tc.name, COUNT(oi.id) as cnt
    FROM order_items oi
    JOIN tests t ON oi.test_id=t.id
    JOIN test_categories tc ON t.category_id=tc.id
    GROUP BY tc.name ORDER BY cnt DESC LIMIT 6
");
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info">
                <div class="label">Total Patients</div>
                <div class="value"><?= number_format($totalPatients) ?></div>
                <div class="change up"><i class="bi bi-plus"></i><?= $todayPatients ?> today</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-clipboard2-pulse-fill"></i></div>
            <div class="stat-info">
                <div class="label">Total Orders</div>
                <div class="value"><?= number_format($totalOrders) ?></div>
                <div class="change up"><?= $todayOrders ?> today</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-info">
                <div class="label">Total Revenue</div>
                <div class="value"><?= labMoney($totalRevenue) ?></div>
                <div class="change up"><?= labMoney($todayRevenue) ?> today</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon <?= $netProfit >= 0 ? 'green' : 'red' ?>"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-info">
                <div class="label">Net Profit</div>
                <div class="value"><?= labMoney($netProfit) ?></div>
                <div class="change">Exp: <?= labMoney($totalExpenses) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($pendingOrders > 0): ?>
<div class="alert d-flex align-items-center gap-3 mb-4"
     style="background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;padding:14px 18px;">
    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
    <div>
        <strong><?= $pendingOrders ?> pending order(s)</strong> —
        <a href="<?= LAB_APP_URL ?>/modules/orders/index.php?lab=<?= $slug ?>&status=pending" class="text-dark">View now →</a>
    </div>
</div>
<?php endif; ?>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart-fill text-success"></i> Revenue Trend (Last 6 Months)</div>
            <div class="card-body"><canvas id="revenueChart" height="100"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill text-info"></i> Tests by Category</div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="catChart" width="200" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-lightning-fill text-warning"></i> Quick Actions</div>
    <div class="card-body d-flex flex-wrap gap-3">
        <a href="<?= LAB_APP_URL ?>/modules/patients/create.php?lab=<?= $slug ?>" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-2"></i>New Patient
        </a>
        <a href="<?= LAB_APP_URL ?>/modules/orders/create.php?lab=<?= $slug ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle me-2"></i>New Order
        </a>
        <a href="<?= LAB_APP_URL ?>/modules/expenses/create.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">
            <i class="bi bi-receipt me-2"></i>Add Expense
        </a>
        <a href="<?= LAB_APP_URL ?>/modules/reports/index.php?lab=<?= $slug ?>" class="btn btn-outline-info">
            <i class="bi bi-file-earmark-bar-graph me-2"></i>Reports
        </a>
    </div>
</div>

<!-- Recent Orders -->
<div class="card">
    <div class="card-header justify-content-between">
        <span><i class="bi bi-clock-history"></i> Recent Orders</span>
        <a href="<?= LAB_APP_URL ?>/modules/orders/index.php?lab=<?= $slug ?>" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Order No.</th><th>Patient</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td><code><?= labClean($o['order_no']) ?></code></td>
                        <td>
                            <div class="fw-semibold"><?= labClean($o['patient_name']) ?></div>
                            <small class="text-muted"><?= labClean($o['pid']) ?></small>
                        </td>
                        <td><?= date('d M Y', strtotime($o['order_date'])) ?></td>
                        <td><strong><?= labMoney($o['net_amount']) ?></strong></td>
                        <td>
                            <span class="badge-status status-<?= $o['status'] ?>">
                                <?= ucwords(str_replace('_',' ',$o['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= LAB_APP_URL ?>/modules/orders/view.php?lab=<?= $slug ?>&id=<?= $o['id'] ?>"
                               class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$revLabels = json_encode(array_column($monthlyRevenue,'month'));
$revValues = json_encode(array_map(fn($r)=>(float)$r['revenue'], $monthlyRevenue));
$catLabels = json_encode(array_column($catDist,'name'));
$catValues = json_encode(array_map(fn($r)=>(int)$r['cnt'], $catDist));
$extraJs = <<<'JS'
<script>
new Chart(document.getElementById('revenueChart'),{
    type:'bar',
    data:{labels:RLABELS,datasets:[{label:'Revenue (₹)',data:RVALUES,
        backgroundColor:'rgba(26,107,74,0.15)',borderColor:'#1a6b4a',borderWidth:2,borderRadius:8}]},
    options:{responsive:true,plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true,ticks:{callback:v=>'₹'+v.toLocaleString()}},x:{grid:{display:false}}}}
});
new Chart(document.getElementById('catChart'),{
    type:'doughnut',
    data:{labels:CLABELS,datasets:[{data:CVALUES,
        backgroundColor:['#1a6b4a','#3b82f6','#f59e0b','#8b5cf6','#ef4444','#06b6d4'],borderWidth:2,borderColor:'#fff'}]},
    options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}}}
});
</script>
JS;
$extraJs = str_replace(['RLABELS','RVALUES','CLABELS','CVALUES'],[$revLabels,$revValues,$catLabels,$catValues],$extraJs);
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
