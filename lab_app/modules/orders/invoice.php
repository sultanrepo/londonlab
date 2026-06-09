<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// PRICE INVOICE — test names + prices + totals. NO results.
require_once __DIR__ . '/../../includes/config.php';
labRequireLogin();

$id    = (int)($_GET['id'] ?? 0);
$slug  = $_GET['lab'] ?? '';
$order = $labDb->fetch("SELECT o.*,p.name as patient_name,p.patient_id as pid,p.phone,p.gender,p.dob,p.address,p.blood_group,p.referred_by,u.name as by_name FROM orders o JOIN patients p ON o.patient_id=p.id LEFT JOIN users u ON o.created_by=u.id WHERE o.id=?",[$id]);
if (!$order) die('Order not found.');

$items     = $labDb->fetchAll("SELECT oi.price,t.name as test_name,t.code FROM order_items oi JOIN tests t ON oi.test_id=t.id WHERE oi.order_id=?",[$id]);
$payments  = $labDb->fetchAll("SELECT * FROM payments WHERE order_id=? AND status='completed' ORDER BY paid_at",[$id]);
$totalPaid = array_sum(array_column($payments,'amount'));
$balance   = $order['net_amount'] - $totalPaid;

$labName    = labGetSetting($labDb,'lab_name',$labInfo['name']??'Lab');
$labAddress = labGetSetting($labDb,'lab_address','');
$labPhone   = labGetSetting($labDb,'lab_phone','');
$labEmail   = labGetSetting($labDb,'lab_email','');
$labGstin   = labGetSetting($labDb,'lab_gstin','');
$labLogo    = labGetSetting($labDb,'lab_logo','');
$labSig     = labGetSetting($labDb,'lab_signature','');
$footer     = labGetSetting($labDb,'report_footer','Results are for clinical guidance only. Consult your physician.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Invoice — <?= labClean($order['order_no']) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🖨️</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'DM Sans',sans-serif;background:#f5f5f5;color:#1e293b}
.wrap{max-width:780px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}
.head{background:linear-gradient(135deg,#50a7c2,#b7f8db);color:#1e293b;padding:28px 36px;display:flex;justify-content:space-between;align-items:flex-start}
.lab-logo{max-height:60px;max-width:190px;object-fit:contain;display:block;margin-bottom:6px}
.lab-name{font-size:22px;font-weight:700}.lab-sub{font-size:11px;opacity:.6;letter-spacing:1px;text-transform:uppercase;margin-top:2px}
.lab-detail{font-size:12px;opacity:.75;margin-top:8px;line-height:1.7}
.inv-badge{text-align:right}.inv-badge .lbl{font-size:11px;opacity:.6;letter-spacing:1px;text-transform:uppercase}
.inv-badge .val{font-size:22px;font-weight:700;margin-top:2px}
.inv-badge .stat{display:inline-block;margin-top:8px;background:rgba(255,255,255,.18);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
.body{padding:28px 36px}
.sec{font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #e2e8f0}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
.mr{display:flex;justify-content:space-between;padding:5px 0;font-size:13px;border-bottom:1px solid #f8fafc}
.mk{color:#64748b}.mv{font-weight:600;text-align:right}
table{width:100%;border-collapse:collapse}
thead th{background:#f8faf9;color:#64748b;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;padding:10px 12px;border-bottom:2px solid #e2e8f0;text-align:left}
tbody td{padding:11px 12px;font-size:13px;border-bottom:1px solid #f1f5f3}
tbody tr:last-child td{border-bottom:none}
.totals{display:flex;justify-content:flex-end;margin-top:16px}
.tbox{width:280px}
.tr{display:flex;justify-content:space-between;padding:7px 0;font-size:13px;border-bottom:1px solid #f1f5f3}
.tr.final{font-size:16px;font-weight:700;border-top:2px solid #1a6b4a;border-bottom:none;color:#1a6b4a;padding-top:10px;margin-top:4px}
.pay-chip{display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;margin:4px}
.sig{display:flex;justify-content:space-between;margin-top:44px}
.sig-box{text-align:center}.sig-img{max-height:48px;max-width:140px;object-fit:contain;display:block;margin:0 auto}.sig-spacer{height:40px}.sig-line{width:140px;border-top:1px solid #cbd5e1;margin:8px auto 6px}
.foot{background:#f8faf9;border-top:1px solid #e2e8f0;padding:16px 36px;display:flex;justify-content:space-between;font-size:12px;color:#64748b}
.pbar{display:flex;gap:10px;justify-content:center;padding:16px;background:#fff;max-width:780px;margin:0 auto}
.pbar button,.pbar a{padding:10px 24px;border-radius:8px;font-size:14px;font-family:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.btn-print{background:#1a6b4a;color:#fff;border:none}.btn-back{background:#fff;color:#1a6b4a;border:1.5px solid #1a6b4a}
@media print{.pbar{display:none!important}body{background:#fff}.wrap{box-shadow:none;margin:0;border-radius:0}}
</style>
</head>
<body>
<div class="pbar">
    <a href="<?= LAB_APP_URL ?>/modules/orders/view.php?lab=<?= $slug ?>&id=<?= $id ?>" class="btn-back">← Back</a>
    <button class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
</div>
<div class="wrap">
    <div class="head">
        <div>
            <?php if ($labLogo): ?>
            <img src="<?= LAB_APP_URL . labClean($labLogo) ?>"
                 alt="<?= labClean($labName) ?>"
                 class="lab-logo">
            <?php else: ?>
            <div class="lab-name">🔬 <?= labClean($labName) ?></div>
            <?php endif; ?>
            <div class="lab-sub">
                <?= $labLogo ? labClean($labName) : 'Clinical Diagnostic Laboratory' ?>
            </div>
            <div class="lab-detail">
                <?= labClean($labAddress) ?><br>
                📞 <?= labClean($labPhone) ?> &nbsp;|&nbsp; ✉️ <?= labClean($labEmail) ?>
                <?php if ($labGstin): ?><br>GSTIN: <?= labClean($labGstin) ?><?php endif; ?>
            </div>
        </div>
        <div class="inv-badge">
            <div class="lbl">Invoice No.</div>
            <div class="val"><?= labClean($order['order_no']) ?></div>
            <div class="stat"><?= ucwords(str_replace('_',' ',$order['status'])) ?></div>
        </div>
    </div>
    <div class="body">
        <div class="meta-grid">
            <div>
                <div class="sec">Patient Information</div>
                <?php
                $pmeta=['Patient ID'=>$order['pid'],'Name'=>$order['patient_name'],'Gender'=>$order['gender'],'Blood Group'=>$order['blood_group'],'Phone'=>$order['phone']];
                if ($order['dob']) $pmeta['Age']=floor((time()-strtotime($order['dob']))/31557600).' yrs';
                if ($order['referred_by']) $pmeta['Referred By']=$order['referred_by'];
                foreach ($pmeta as $k=>$v): ?>
                <div class="mr"><span class="mk"><?= $k ?></span><span class="mv"><?= labClean($v) ?></span></div>
                <?php endforeach; ?>
            </div>
            <div>
                <div class="sec">Invoice Details</div>
                <?php foreach (['Invoice Date'=>date('d M Y',strtotime($order['order_date'])),'Time'=>date('H:i',strtotime($order['order_date'])),'Created By'=>$order['by_name']??'—'] as $k=>$v): ?>
                <div class="mr"><span class="mk"><?= $k ?></span><span class="mv"><?= labClean($v) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sec">Tests Ordered</div>
        <table>
            <thead><tr><th>#</th><th>Test Name</th><th>Code</th><th style="text-align:right;">Price</th></tr></thead>
            <tbody>
                <?php foreach ($items as $i=>$item): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td style="font-weight:600;"><?= labClean($item['test_name']) ?></td>
                    <td><span style="font-family:'DM Mono',monospace;font-size:12px;"><?= labClean($item['code']) ?></span></td>
                    <td style="text-align:right;font-weight:600;"><?= labMoney($item['price']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="totals">
            <div class="tbox">
                <div class="tr"><span>Subtotal</span><span><?= labMoney($order['total_amount']) ?></span></div>
                <?php if ($order['discount']>0): ?><div class="tr" style="color:#1a6b4a;"><span>Discount</span><span>− <?= labMoney($order['discount']) ?></span></div><?php endif; ?>
                <div class="tr final"><span>Net Total</span><span><?= labMoney($order['net_amount']) ?></span></div>
                <div class="tr"><span>Amount Paid</span><span style="color:#166534;"><?= labMoney($totalPaid) ?></span></div>
                <div class="tr" style="color:<?= $balance>0?'#ef4444':'#166534' ?>;font-weight:600;"><span>Balance</span><span><?= $balance>0?labMoney($balance):'NIL' ?></span></div>
            </div>
        </div>
        <?php if (!empty($payments)): ?>
        <div style="margin-top:16px;"><div class="sec">Payments Received</div>
            <?php foreach ($payments as $pay): ?>
            <span class="pay-chip">✓ <?= labMoney($pay['amount']) ?> via <?= ucfirst(str_replace('_',' ',$pay['method'])) ?> — <?= date('d M Y',strtotime($pay['paid_at'])) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($order['notes']): ?>
        <div style="margin-top:20px;padding:12px;background:#fafafa;border-radius:8px;font-size:13px;"><strong>Notes:</strong> <?= labClean($order['notes']) ?></div>
        <?php endif; ?>
        <div class="sig">
            <div class="sig-box">
                <div class="sig-spacer"></div>
                <div class="sig-line"></div>
                <small style="color:#64748b;">Patient / Attendee</small>
            </div>
            <div class="sig-box">
                <?php if ($labSig): ?>
                <img src="<?= LAB_APP_URL . labClean($labSig) ?>"
                     alt="Authorised Signature"
                     class="sig-img">
                <?php else: ?>
                <div class="sig-spacer"></div>
                <?php endif; ?>
                <div class="sig-line"></div>
                <small style="color:#64748b;">Authorized Signatory</small>
            </div>
        </div>
    </div>
    <div class="foot"><div><?= labClean($footer) ?></div><div>Generated: <?= date('d M Y H:i') ?></div></div>
</div>
</body></html>