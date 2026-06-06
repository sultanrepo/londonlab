<?php
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../../includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$revenue      = (float)$labDb->fetch("SELECT COALESCE(SUM(amount),0) as s FROM payments WHERE status='completed' AND DATE(paid_at) BETWEEN ? AND ?",[$from,$to])['s'];
$expenses     = (float)$labDb->fetch("SELECT COALESCE(SUM(amount),0) as s FROM expenses WHERE expense_date BETWEEN ? AND ?",[$from,$to])['s'];
$netProfit    = $revenue - $expenses;
$newOrders    = (int)$labDb->fetch("SELECT COUNT(*) as c FROM orders WHERE DATE(order_date) BETWEEN ? AND ?",[$from,$to])['c'];
$newPatients  = (int)$labDb->fetch("SELECT COUNT(*) as c FROM patients WHERE DATE(created_at) BETWEEN ? AND ?",[$from,$to])['c'];
$avgOrder     = $newOrders > 0 ? (float)$labDb->fetch("SELECT AVG(net_amount) as a FROM orders WHERE DATE(order_date) BETWEEN ? AND ?",[$from,$to])['a'] : 0;

$daily = $labDb->fetchAll("SELECT DATE(paid_at) as d, SUM(amount) as rev FROM payments WHERE status='completed' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY DATE(paid_at) ORDER BY d",[$from,$to]);
$topTests = $labDb->fetchAll("SELECT t.name,t.code,COUNT(oi.id) as cnt,SUM(oi.price) as rev FROM order_items oi JOIN tests t ON oi.test_id=t.id JOIN orders o ON oi.order_id=o.id WHERE DATE(o.order_date) BETWEEN ? AND ? GROUP BY t.id ORDER BY cnt DESC LIMIT 10",[$from,$to]);
$payMethods  = $labDb->fetchAll("SELECT method,COUNT(*) as cnt,SUM(amount) as total FROM payments WHERE status='completed' AND DATE(paid_at) BETWEEN ? AND ? GROUP BY method ORDER BY total DESC",[$from,$to]);
$expByCat    = $labDb->fetchAll("SELECT category,SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC",[$from,$to]);
?>
<div class="page-header">
    <div><h2><i class="bi bi-bar-chart-fill me-2 text-info"></i>Reports & Analytics</h2></div>
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-2"></i>Print</button>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex align-items-end gap-3 flex-wrap">
            <input type="hidden" name="lab" value="<?= $slug ?>">
            <div><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= labClean($from) ?>"></div>
            <div><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= labClean($to) ?>"></div>
            <div class="d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a href="?lab=<?= $slug ?>&from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">This Month</a>
                <a href="?lab=<?= $slug ?>&from=<?= date('Y-01-01') ?>&to=<?= date('Y-12-31') ?>" class="btn btn-outline-secondary btn-sm">This Year</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4"><div class="stat-card"><div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div><div class="stat-info"><div class="label">Revenue</div><div class="value"><?= labMoney($revenue) ?></div><div class="change"><?= $newOrders ?> orders</div></div></div></div>
    <div class="col-sm-6 col-xl-4"><div class="stat-card"><div class="stat-icon red"><i class="bi bi-cash-stack"></i></div><div class="stat-info"><div class="label">Expenses</div><div class="value"><?= labMoney($expenses) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-4"><div class="stat-card"><div class="stat-icon <?= $netProfit>=0?'green':'red' ?>"><i class="bi bi-graph-up-arrow"></i></div><div class="stat-info"><div class="label">Net Profit</div><div class="value <?= $netProfit<0?'text-danger':'' ?>"><?= labMoney($netProfit) ?></div><div class="change">Margin: <?= $revenue>0?number_format($netProfit/$revenue*100,1):0 ?>%</div></div></div></div>
    <div class="col-sm-6 col-xl-4"><div class="stat-card"><div class="stat-icon blue"><i class="bi bi-clipboard2-pulse-fill"></i></div><div class="stat-info"><div class="label">Orders</div><div class="value"><?= $newOrders ?></div><div class="change">Avg <?= labMoney($avgOrder) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-4"><div class="stat-card"><div class="stat-icon purple"><i class="bi bi-people-fill"></i></div><div class="stat-info"><div class="label">New Patients</div><div class="value"><?= $newPatients ?></div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8"><div class="card h-100">
        <div class="card-header"><i class="bi bi-activity text-success"></i> Daily Revenue</div>
        <div class="card-body"><canvas id="dailyChart" height="90"></canvas></div>
    </div></div>
    <div class="col-lg-4"><div class="card h-100">
        <div class="card-header"><i class="bi bi-pie-chart-fill text-danger"></i> Expenses by Category</div>
        <div class="card-body d-flex align-items-center justify-content-center"><canvas id="expChart" width="200" height="200"></canvas></div>
    </div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7"><div class="card">
        <div class="card-header"><i class="bi bi-trophy-fill text-warning"></i> Top Tests</div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr><th>#</th><th>Test</th><th>Count</th><th>Revenue</th></tr></thead>
                <tbody>
                    <?php foreach ($topTests as $i=>$t): ?>
                    <tr><td><?= $i+1 ?></td><td><?= labClean($t['name']) ?></td><td><span class="badge bg-info-subtle text-info"><?= $t['cnt'] ?></span></td><td><strong><?= labMoney($t['rev']) ?></strong></td></tr>
                    <?php endforeach; ?>
                    <?php if(empty($topTests)): ?><tr><td colspan="4" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div></div>
    <div class="col-lg-5"><div class="card">
        <div class="card-header"><i class="bi bi-credit-card-fill text-info"></i> Payment Methods</div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr><th>Method</th><th>Count</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($payMethods as $pm): ?>
                    <tr><td class="text-capitalize"><?= ucfirst(str_replace('_',' ',$pm['method'])) ?></td><td><?= $pm['cnt'] ?></td><td><strong><?= labMoney($pm['total']) ?></strong></td></tr>
                    <?php endforeach; ?>
                    <?php if(empty($payMethods)): ?><tr><td colspan="3" class="text-center text-muted py-3">No payments.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<?php
$dLabels = json_encode(array_map(fn($r)=>date('d M',strtotime($r['d'])), $daily));
$dValues = json_encode(array_map(fn($r)=>(float)$r['rev'], $daily));
$eLabels = json_encode(array_map(fn($r)=>ucfirst($r['category']), $expByCat));
$eValues = json_encode(array_map(fn($r)=>(float)$r['total'], $expByCat));
$extraJs = <<<'JS'
<script>
new Chart(document.getElementById('dailyChart'),{type:'line',data:{labels:DL,datasets:[{label:'Revenue',data:DV,borderColor:'#1a6b4a',backgroundColor:'rgba(26,107,74,0.07)',tension:0.4,fill:true,pointRadius:4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>'₹'+v.toLocaleString()}},x:{grid:{display:false}}}}});
new Chart(document.getElementById('expChart'),{type:'doughnut',data:{labels:EL,datasets:[{data:EV,backgroundColor:['#ef4444','#f97316','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#64748b'],borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,cutout:'60%',plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}}}});
</script>
JS;
$extraJs = str_replace(['DL','DV','EL','EV'],[$dLabels,$dValues,$eLabels,$eValues],$extraJs);
?>
<style>@media print{#sidebar,#topbar,.page-header .btn,form{display:none!important}#main-wrapper{margin-left:0!important}}</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
