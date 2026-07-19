<?php
$pageTitle = 'Invoice Details';
require_once __DIR__ . '/../../includes/header.php';

$db  = MasterDB::getInstance();
$id  = (int)($_GET['id'] ?? 0);
$inv = $db->fetch("
    SELECT bi.*, l.name as lab_name, l.owner_name, l.owner_email, l.owner_phone,
           l.address, l.city, l.state, l.gstin as lab_gstin,
           p.name as plan_name
    FROM billing_invoices bi
    JOIN labs l ON bi.lab_id = l.id
    LEFT JOIN plans p ON bi.plan_id = p.id
    WHERE bi.id = ?
", [$id]);

if (!$inv) { saSetFlash('error','Invoice not found.'); header('Location: '.SUPERADMIN_URL.'/modules/billing/index.php'); exit; }
$pageTitle = saClean($inv['invoice_no']);
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-receipt me-2" style="color:#7c3aed;"></i><?= saClean($inv['invoice_no']) ?></h2>
        <p><?= saClean($inv['lab_name']) ?> — <?= date('d M Y', strtotime($inv['created_at'])) ?></p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-2"></i>Print
        </button>
        <a href="<?= SUPERADMIN_URL ?>/modules/billing/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card" id="invoicePrint">
    <!-- Invoice Header -->
    <div style="background:linear-gradient(135deg,#1e1b4b,#7c3aed);color:#fff;padding:28px 32px;border-radius:13px 13px 0 0;display:flex;justify-content:space-between;align-items:flex-start;">
        <div>
            <div style="font-size:22px;font-weight:700;"><?= PLATFORM_NAME ?></div>
            <div style="font-size:11px;opacity:.6;letter-spacing:1px;text-transform:uppercase;margin-top:2px;">Lab Management Software</div>
            <div style="font-size:12px;opacity:.75;margin-top:8px;line-height:1.7;">
                <?= PLATFORM_URL ?><br>
                Billing Support: <?= BILLING_SUPPORT_EMAIL ?>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:11px;opacity:.6;letter-spacing:1px;text-transform:uppercase;">Invoice</div>
            <div style="font-size:22px;font-weight:700;margin-top:2px;"><?= saClean($inv['invoice_no']) ?></div>
            <div style="margin-top:8px;">
                <span style="background:rgba(255,255,255,.18);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">
                    <?= ucfirst($inv['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="card-body" style="padding:28px 32px;">
        <!-- Billed To / Invoice Info -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">
            <div>
                <div style="font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:8px;">Billed To</div>
                <div class="fw-bold fs-6"><?= saClean($inv['lab_name']) ?></div>
                <div class="text-muted small"><?= saClean($inv['owner_name']) ?></div>
                <div class="text-muted small"><?= saClean($inv['owner_email']) ?></div>
                <div class="text-muted small"><?= saClean($inv['owner_phone'] ?? '') ?></div>
                <?php if ($inv['address']): ?>
                <div class="text-muted small"><?= saClean($inv['address']) ?><?= $inv['city'] ? ', '.saClean($inv['city']) : '' ?></div>
                <?php endif; ?>
                <?php if ($inv['lab_gstin']): ?>
                <div class="text-muted small">GSTIN: <?= saClean($inv['lab_gstin']) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:8px;">Invoice Details</div>
                <?php
                $details = [
                    'Invoice No.' => $inv['invoice_no'],
                    'Date'        => date('d M Y', strtotime($inv['created_at'])),
                    'Due Date'    => $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '—',
                    'Plan'        => $inv['plan_name'] ?? '—',
                ];
                if ($inv['paid_at']) $details['Paid On'] = date('d M Y', strtotime($inv['paid_at']));
                foreach ($details as $k=>$v): ?>
                <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:13px;">
                    <span class="text-muted"><?= $k ?></span>
                    <span class="fw-semibold"><?= saClean($v) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Line Items -->
        <table class="table mb-3" style="font-size:14px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>
                        <div class="fw-semibold"><?= saClean($inv['plan_name'] ?? 'Subscription') ?> — Monthly Subscription</div>
                        <?php if ($inv['notes']): ?>
                        <small class="text-muted"><?= saClean($inv['notes']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= money($inv['amount']) ?></td>
                </tr>
                <?php if ($inv['gst_amount'] > 0): ?>
                <tr>
                    <td>2</td>
                    <td><span class="text-muted">GST (<?= GST_PERCENT ?>%)</span></td>
                    <td class="text-end"><?= money($inv['gst_amount']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (($inv['discount_amount'] ?? 0) > 0): ?>
                <tr style="background:#f8fafc;">
                    <td></td>
                    <td class="text-end fw-semibold">Sub Total</td>
                    <td class="text-end fw-semibold"><?= money($inv['amount'] + $inv['gst_amount']) ?></td>
                </tr>
                <tr>
                    <td>3</td>
                    <td><span class="text-muted">Discount (<?= rtrim(rtrim(number_format($inv['discount_percent'], 2), '0'), '.') ?>%)</span></td>
                    <td class="text-end text-success">&minus;<?= money($inv['discount_amount']) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f5f3ff;">
                    <td colspan="2" class="text-end fw-bold fs-6">Total</td>
                    <td class="text-end fw-bold fs-5" style="color:#7c3aed;"><?= money($inv['total_amount']) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Payment Info -->
        <?php if ($inv['status'] === 'paid'): ?>
        <div class="p-3 rounded-2 d-flex align-items-center gap-3" style="background:#dcfce7;border:1px solid #bbf7d0;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>
                <strong class="text-success">Payment Received</strong>
                <div class="text-muted small">
                    <?= money($inv['total_amount']) ?> via <?= ucfirst(str_replace('_',' ',$inv['payment_method'] ?? '')) ?>
                    on <?= date('d M Y', strtotime($inv['paid_at'])) ?>
                    <?= $inv['transaction_ref'] ? ' · Ref: '.saClean($inv['transaction_ref']) : '' ?>
                </div>
            </div>
        </div>
        <?php elseif ($inv['status'] === 'pending' || $inv['status'] === 'overdue'): ?>
        <div class="p-3 rounded-2" style="background:#fef3c7;border:1px solid #f59e0b;">
            <strong>Payment Instructions</strong>
            <div class="text-muted small mt-1">
                Please transfer <?= money($inv['total_amount']) ?> before <?= $inv['due_date'] ? date('d M Y',strtotime($inv['due_date'])) : 'due date' ?>.<br>
                UPI: yourname@upi &nbsp;|&nbsp; Bank: XXXX XXXX XXXX 1234 (HDFC Bank)
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer note -->
        <div class="text-center text-muted mt-4" style="font-size:12px;">
            Thank you for using <?= PLATFORM_NAME ?>. For billing queries, contact billing@londonlab.com
        </div>
    </div>
</div>
</div>
</div>

<style>
@media print {
    #sidebar,#topbar,.page-header .btn,.print-bar { display:none!important; }
    #main-wrapper { margin-left:0!important; }
    #invoicePrint { box-shadow:none!important; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>