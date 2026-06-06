<?php
$pageTitle = 'Revenue Reports';
require_once __DIR__ . '/../../includes/header.php';

$db   = MasterDB::getInstance();
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// KPIs
$totalRevenue  = (float)$db->fetch("SELECT COALESCE(SUM(total_amount),0) as s FROM billing_invoices WHERE status='paid' AND DATE(paid_at) BETWEEN ? AND ?", [$from,$to])['s'];
$totalInvoices = (int)$db->fetch("SELECT COUNT(*) as c FROM billing_invoices WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to])['c'];
$paidInvoices  = (int)$db->fetch("SELECT COUNT(*) as c FROM billing_invoices WHERE status='paid' AND DATE(paid_at) BETWEEN ? AND ?", [$from,$to])['c'];
$newLabs       = (int)$db->fetch("SELECT COUNT(*) as c FROM labs WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to])['c'];
$activeLabs    = (int)$db->fetch("SELECT COUNT(*) as c FROM labs WHERE status='active'")['c'];

// Monthly breakdown
$monthly = $db->fetchAll("
    SELECT DATE_FORMAT(paid_at,'%b %Y') as month, DATE_FORMAT(paid_at,'%Y-%m') as ym,
           COUNT(*) as invoices, SUM(total_amount) as revenue
    FROM billing_invoices WHERE status='paid' AND DATE(paid_at) BETWEEN ? AND ?
    GROUP BY ym ORDER BY ym ASC
", [$from,$to]);

// Revenue by plan
$byPlan = $db->fetchAll("
    SELECT p.name as plan_name, COUNT(bi.id) as count, SUM(bi.total_amount) as revenue
    FROM billing_invoices bi JOIN plans p ON bi.plan_id=p.id
    WHERE bi.status='paid' AND DATE(bi.paid_at) BETWEEN ? AND ?
    GROUP BY p.id ORDER BY revenue DESC
", [$from,$to]);

// Top paying labs
$topLabs = $db->fetchAll("
    SELECT l.name, SUM(bi.total_amount) as total_paid, COUNT(bi.id) as invoices
    FROM billing_invoices bi JOIN labs l ON bi.lab_id=l.id
    WHERE bi.status='paid' AND DATE(bi.paid_at) BETWEEN ? AND ?
    GROUP BY l.id ORDER BY total_paid DESC LIMIT 10
", [$from,$to]);
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-bar-chart-fill me-2" style="color:#7c3aed;"></i>Revenue Reports</h2>
        <p>Platform-wide financial analytics</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-2"></i>Print
    </button>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex align-items-end gap-3 flex-wrap">
            <div><label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= saClean($from) ?>">
            </div>
            <div><label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= saClean($to) ?>">
            </div>
            <div class="d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Month</a>
                <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-12-31') ?>" class="btn btn-outline-secondary btn-sm">This Year</a>
            </div>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-info"><div class="label">Revenue Collected</div><div class="value"><?= money($totalRevenue) ?></div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-receipt"></i></div>
            <div class="stat-info">
                <div class="label">Invoices</div>
                <div class="value"><?= $totalInvoices ?></div>
                <div class="sub"><?= $paidInvoices ?> paid</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-building-add"></i></div>
            <div class="stat-info"><div class="label">New Labs</div><div class="value"><?= $newLabs ?></div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-building-check"></i></div>
            <div class="stat-info"><div class="label">Active Labs</div><div class="value"><?= $activeLabs ?></div></div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart-fill text-success"></i> Monthly Revenue</div>
            <div class="card-body"><canvas id="revenueChart" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill" style="color:#7c3aed;"></i> Revenue by Plan</div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="planChart" width="200" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Monthly Table -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-table"></i> Monthly Breakdown</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Month</th><th>Invoices</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($monthly as $m): ?>
                        <tr>
                            <td><?= saClean($m['month']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary"><?= $m['invoices'] ?></span></td>
                            <td><strong><?= money($m['revenue']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($monthly)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No data in range.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Labs -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-trophy-fill text-warning"></i> Top Paying Labs</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>#</th><th>Lab</th><th>Invoices</th><th>Total Paid</th></tr></thead>
                    <tbody>
                        <?php foreach ($topLabs as $i=>$lab): ?>
                        <tr>
                            <td><span class="badge bg-secondary-subtle text-secondary"><?= $i+1 ?></span></td>
                            <td class="fw-semibold"><?= saClean($lab['name']) ?></td>
                            <td><?= $lab['invoices'] ?></td>
                            <td><strong class="text-success"><?= money($lab['total_paid']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topLabs)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No data.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$revLabels  = json_encode(array_column($monthly,'month'));
$revValues  = json_encode(array_map(fn($r)=>(float)$r['revenue'],$monthly));
$planLabels = json_encode(array_column($byPlan,'plan_name'));
$planValues = json_encode(array_map(fn($r)=>(float)$r['revenue'],$byPlan));

$extraJs = <<<'JS'
<script>
new Chart(document.getElementById('revenueChart'),{
    type:'bar',
    data:{labels:REVLABELS,datasets:[{label:'Revenue (₹)',data:REVVALUES,
        backgroundColor:'rgba(124,58,237,0.15)',borderColor:'#7c3aed',borderWidth:2,borderRadius:8}]},
    options:{responsive:true,plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true,ticks:{callback:v=>'₹'+v.toLocaleString()}},x:{grid:{display:false}}}}
});
new Chart(document.getElementById('planChart'),{
    type:'doughnut',
    data:{labels:PLANLABELS,datasets:[{data:PLANVALUES,
        backgroundColor:['#94a3b8','#7c3aed','#f59e0b'],borderWidth:2,borderColor:'#fff'}]},
    options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}}}
});
</script>
JS;
$extraJs = str_replace(['REVLABELS','REVVALUES','PLANLABELS','PLANVALUES'],[$revLabels,$revValues,$planLabels,$planValues],$extraJs);
?>

<style>@media print{#sidebar,#topbar,.page-header .btn,form{display:none!important}#main-wrapper{margin-left:0!important}}</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
