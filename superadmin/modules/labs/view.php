<?php
$pageTitle = 'Lab Details';
require_once __DIR__ . '/../../includes/header.php';

$db  = MasterDB::getInstance();
$id  = (int)($_GET['id'] ?? 0);
$lab = $db->fetch("SELECT l.*, p.name as plan_name, p.slug as plan_slug, p.price as plan_price
                   FROM labs l LEFT JOIN plans p ON l.plan_id=p.id WHERE l.id=?", [$id]);
if (!$lab) { saSetFlash('error','Lab not found.'); header('Location: '.SUPERADMIN_URL.'/modules/labs/index.php'); exit; }

// Handle status change
if (isset($_GET['action'])) {
    $action    = $_GET['action'];
    $statusMap = ['suspend'=>'suspended','activate'=>'active','cancel'=>'cancelled'];
    if (isset($statusMap[$action])) {
        $db->execute("UPDATE labs SET status=? WHERE id=?", [$statusMap[$action], $id]);
        saSetFlash('success', 'Status updated to ' . $statusMap[$action]);
        header('Location: ' . SUPERADMIN_URL . '/modules/labs/view.php?id=' . $id); exit;
    }
}

$invoices      = $db->fetchAll("SELECT bi.*, p.name as plan_name FROM billing_invoices bi LEFT JOIN plans p ON bi.plan_id=p.id WHERE bi.lab_id=? ORDER BY bi.created_at DESC", [$id]);
$subscriptions = $db->fetchAll("SELECT s.*, p.name as plan_name FROM subscriptions s JOIN plans p ON s.plan_id=p.id WHERE s.lab_id=? ORDER BY s.created_at DESC", [$id]);
$totalPaid     = array_sum(array_map(fn($i) => $i['status']==='paid' ? $i['total_amount'] : 0, $invoices));
$totalPending  = array_sum(array_map(fn($i) => $i['status']==='pending' ? $i['total_amount'] : 0, $invoices));
$pageTitle     = saClean($lab['name']);
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-building me-2" style="color:#7c3aed;"></i><?= saClean($lab['name']) ?></h2>
        <p><?= saClean($lab['city'] ?? '') ?><?= $lab['state'] ? ', '.saClean($lab['state']) : '' ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="badge-status status-<?= $lab['status'] ?> fs-6"><?= ucfirst($lab['status']) ?></span>
        <a href="<?= SUPERADMIN_URL ?>/modules/billing/create.php?lab_id=<?= $id ?>" class="btn btn-primary">
            <i class="bi bi-receipt me-2"></i>Generate Invoice
        </a>
        <a href="<?= SUPERADMIN_URL ?>/modules/labs/edit.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil me-2"></i>Edit
        </a>
        <?php if (in_array($lab['status'],['active','trial'])): ?>
        <a href="?id=<?= $id ?>&action=suspend"
           class="btn btn-outline-warning"
           onclick="return confirm('Suspend this lab?')">
            <i class="bi bi-pause-circle me-2"></i>Suspend
        </a>
        <?php elseif ($lab['status']==='suspended'): ?>
        <a href="?id=<?= $id ?>&action=activate"
           class="btn btn-outline-success"
           onclick="return confirm('Reactivate this lab?')">
            <i class="bi bi-play-circle me-2"></i>Activate
        </a>
        <?php endif; ?>
        <a href="<?= SUPERADMIN_URL ?>/modules/labs/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column -->
    <div class="col-lg-4">

        <!-- Lab Profile -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="text-center mb-3">
                    <div style="width:72px;height:72px;border-radius:16px;background:#ede9fe;
                        color:#7c3aed;font-size:30px;font-weight:700;
                        display:grid;place-items:center;margin:0 auto 12px;">
                        <?= strtoupper(substr($lab['name'],0,1)) ?>
                    </div>
                    <h5 class="mb-1"><?= saClean($lab['name']) ?></h5>
                    <span class="badge bg-<?= planBadgeColor($lab['plan_slug']??'') ?>-subtle
                        text-<?= planBadgeColor($lab['plan_slug']??'') ?>">
                        <?= saClean($lab['plan_name'] ?? 'No Plan') ?>
                    </span>
                </div>
                <div class="divider"></div>
                <?php
                $info = [
                    'Owner'    => $lab['owner_name'],
                    'Email'    => $lab['owner_email'],
                    'Phone'    => $lab['owner_phone'] ?? '—',
                    'City'     => ($lab['city'] ?? '') . ($lab['state'] ? ', '.$lab['state'] : ''),
                    'GSTIN'    => $lab['gstin'] ?: '—',
                    'Database' => $lab['db_name'],
                    'Joined'   => date('d M Y', strtotime($lab['created_at'])),
                ];
                foreach ($info as $k=>$v): ?>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <small class="text-muted"><?= $k ?></small>
                    <small class="fw-semibold text-end" style="max-width:180px;"><?= saClean($v) ?></small>
                </div>
                <?php endforeach; ?>
                <!-- Lab ID + Login Link -->
                <div class="mt-3 p-3 rounded-2" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="fw-bold text-success">🔑 Lab ID (for login)</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <code style="background:#dcfce7;padding:4px 10px;border-radius:6px;font-size:14px;font-weight:700;color:#166534;flex:1;">
                            <?= saClean($lab['slug']) ?>
                        </code>
                        <button onclick="navigator.clipboard.writeText('<?= saClean($lab['slug']) ?>');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1500);"
                                class="btn btn-sm btn-outline-success">Copy</button>
                    </div>
                    <div class="mt-2">
                        <a href="<?= LAB_APP_URL ?>/login.php?lab=<?= saClean($lab['slug']) ?>"
                           target="_blank" class="btn btn-sm btn-success w-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Open Lab Login Page
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trial / Subscription Info -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-calendar-check"></i> Subscription</div>
            <div class="card-body">
                <?php if ($lab['status'] === 'trial'): ?>
                <div class="text-center p-3" style="background:#e0f2fe;border-radius:10px;">
                    <div style="font-size:36px;font-weight:800;color:#0369a1;">
                        <?= daysLeft($lab['trial_ends_at']) ?>
                    </div>
                    <div style="font-size:13px;color:#0369a1;">days left in trial</div>
                    <div class="text-muted small mt-1">Ends: <?= date('d M Y', strtotime($lab['trial_ends_at'])) ?></div>
                </div>
                <?php elseif ($lab['subscription_ends_at']): ?>
                <div class="text-center p-3" style="background:#f0fdf4;border-radius:10px;">
                    <div style="font-size:36px;font-weight:800;color:#16a34a;">
                        <?= daysLeft($lab['subscription_ends_at']) ?>
                    </div>
                    <div style="font-size:13px;color:#16a34a;">days remaining</div>
                    <div class="text-muted small mt-1">Renews: <?= date('d M Y', strtotime($lab['subscription_ends_at'])) ?></div>
                </div>
                <?php else: ?>
                <p class="text-muted text-center small py-2">No active subscription</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Revenue Summary -->
        <div class="card">
            <div class="card-header"><i class="bi bi-currency-rupee"></i> Revenue from Lab</div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Total Paid</span>
                    <strong class="text-success"><?= money($totalPaid) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Pending</span>
                    <strong class="text-warning"><?= money($totalPending) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-8">

        <!-- Invoices -->
        <div class="card mb-4">
            <div class="card-header justify-content-between">
                <span><i class="bi bi-receipt"></i> Billing Invoices</span>
                <a href="<?= SUPERADMIN_URL ?>/modules/billing/create.php?lab_id=<?= $id ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus me-1"></i>New Invoice
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($invoices)): ?>
                <p class="text-center text-muted py-4 small">No invoices yet.</p>
                <?php else: ?>
                <table class="table mb-0">
                    <thead>
                        <tr><th>Invoice</th><th>Plan</th><th>Amount</th><th>Due</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><code><?= saClean($inv['invoice_no']) ?></code>
                                <br><small class="text-muted"><?= date('d M Y',strtotime($inv['created_at'])) ?></small></td>
                            <td><?= saClean($inv['plan_name'] ?? '—') ?></td>
                            <td><strong><?= money($inv['total_amount']) ?></strong></td>
                            <td><?= $inv['due_date'] ? date('d M Y',strtotime($inv['due_date'])) : '—' ?></td>
                            <td><span class="badge-status status-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span></td>
                            <td>
                                <a href="<?= SUPERADMIN_URL ?>/modules/billing/view.php?id=<?= $inv['id'] ?>"
                                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subscription History -->
        <div class="card">
            <div class="card-header justify-content-between">
                <span><i class="bi bi-calendar-range"></i> Subscription History</span>
                <a href="<?= SUPERADMIN_URL ?>/modules/subscriptions/create.php?lab_id=<?= $id ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus me-1"></i>Add Subscription
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($subscriptions)): ?>
                <p class="text-center text-muted py-4 small">No subscriptions recorded.</p>
                <?php else: ?>
                <table class="table mb-0">
                    <thead><tr><th>Plan</th><th>From</th><th>To</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($subscriptions as $sub): ?>
                        <tr>
                            <td class="fw-semibold"><?= saClean($sub['plan_name']) ?></td>
                            <td><?= date('d M Y',strtotime($sub['start_date'])) ?></td>
                            <td><?= date('d M Y',strtotime($sub['end_date'])) ?></td>
                            <td><?= money($sub['amount']) ?></td>
                            <td><span class="badge-status status-<?= $sub['status'] ?>"><?= ucfirst($sub['status']) ?></span></td>
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
