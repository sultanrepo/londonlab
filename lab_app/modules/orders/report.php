<?php
// LAB REPORT — results + normal ranges. NO prices shown.
require_once __DIR__ . '/../../includes/config.php';
labRequireLogin();

$id    = (int)($_GET['id'] ?? 0);
$order = $labDb->fetch("
    SELECT o.*, p.name as patient_name, p.patient_id as pid,
           p.phone, p.gender, p.dob, p.address, p.blood_group,
           p.referred_by, u.name as by_name
    FROM orders o
    JOIN patients p ON o.patient_id = p.id
    LEFT JOIN users u ON o.created_by = u.id
    WHERE o.id = ?
", [$id]);
if (!$order) die('Order not found.');

$items = $labDb->fetchAll("
    SELECT oi.result_value, oi.result_status, oi.result_notes, oi.completed_at,
           t.name as test_name, t.code, t.normal_range, t.unit, t.id as test_db_id,
           oi.id as item_id
    FROM order_items oi
    JOIN tests t ON oi.test_id = t.id
    WHERE oi.order_id = ?
    ORDER BY t.name
", [$id]);

// Load sub-parameters with results for each item
$subParamsMap = [];
foreach ($items as $item) {
    $subs = $labDb->fetchAll("
        SELECT sp.*, tsr.result_value, tsr.result_status
        FROM test_sub_parameters sp
        LEFT JOIN test_sub_results tsr
               ON tsr.sub_parameter_id = sp.id AND tsr.order_item_id = ?
        WHERE sp.test_id = ? AND sp.is_active = 1
        ORDER BY sp.sort_order
    ", [$item['item_id'], $item['test_db_id']]);
    if (!empty($subs)) $subParamsMap[$item['item_id']] = $subs;
}

$labName    = labGetSetting($labDb, 'lab_name',      $labInfo['name'] ?? 'Lab');
$labAddress = labGetSetting($labDb, 'lab_address',   '');
$labPhone   = labGetSetting($labDb, 'lab_phone',     '');
$labEmail   = labGetSetting($labDb, 'lab_email',     '');
$footer     = labGetSetting($labDb, 'report_footer', 'Results are for clinical guidance only. Consult your physician.');

$age = $order['dob'] ? floor((time() - strtotime($order['dob'])) / 31557600) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lab Report — <?= labClean($order['order_no']) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🖨️</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: #f5f5f5; color: #1e293b; }

.wrap { max-width: 820px; margin: 30px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.1); }

/* ── HEADER ── */
.head{background:linear-gradient(135deg,#50a7c2,#b7f8db);color:#1e293b;padding:0}
.head-top { padding: 24px 36px; display: flex; justify-content: space-between; align-items: flex-start; }
.lab-name { font-size: 22px; font-weight: 700; }
.lab-sub  { font-size: 11px; opacity: .6; letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }
.lab-detail { font-size: 12px; opacity: .75; margin-top: 8px; line-height: 1.8; }
.rep-badge { text-align: right; }
.rep-badge .lbl { font-size: 11px; opacity: .6; letter-spacing: 1px; text-transform: uppercase; }
.rep-badge .val { font-size: 20px; font-weight: 700; margin-top: 2px; }
.rep-badge .dt  { font-size: 12px; opacity: .7; margin-top: 4px; }

/* ── PATIENT STRIP ── */
.pstrip { background: rgba(255,255,255,.12); padding: 12px 36px; display: flex; gap: 28px; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,.12); }
.ps-item .ps-lbl { font-size: 10px; opacity: .55; letter-spacing: .5px; text-transform: uppercase; }
.ps-item .ps-val { font-size: 14px; font-weight: 600; margin-top: 1px; }

/* ── BODY ── */
.body { padding: 24px 36px; }

/* ── SECTION TITLE ── */
.sec-title { font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #64748b; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }

/* ── TEST BLOCK ── */
.test-block { margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.test-block-head {
    background: #f8faf9;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e2e8f0;
}
.test-block-name { font-size: 14px; font-weight: 700; color: #1e293b; }
.test-block-code { font-family: 'DM Mono', monospace; font-size: 11px; background: #ede9fe; color: #7c3aed; padding: 2px 8px; border-radius: 4px; margin-left: 8px; }
.test-block-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.st-normal   { background: #dcfce7; color: #166534; }
.st-abnormal { background: #fff7ed; color: #9a3412; }
.st-critical { background: #fee2e2; color: #991b1b; }
.st-pending  { background: #f1f5f9; color: #64748b; }

/* ── SUB-PARAMETER TABLE ── */
table { width: 100%; border-collapse: collapse; }
.sub-head th {
    background: #ffffff;
    color: #54AAC3;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .5px;
    text-transform: uppercase;
    padding: 8px 12px;
    border-bottom: 2px solid #8BD5D0;
    text-align: left;
}
.sub-body td {
    padding: 9px 12px;
    font-size: 13px;
    border-bottom: 1px solid #f1f5f3;
    vertical-align: middle;
}
.sub-body tr:last-child td { border-bottom: none; }
.sub-body tr.row-ab { background: #fff7ed; }
.sub-body tr.row-cr { background: #fee2e2; }

.rv-normal   { font-weight: 700; color: #166534; }
.rv-abnormal { font-weight: 700; color: #c2410c; }
.rv-critical { font-weight: 700; color: #b91c1c; }
.rv-pending  { color: #94a3b8; }

/* ── SIMPLE TEST ── */
.simple-result { padding: 12px 16px; display: flex; gap: 32px; font-size: 13px; }
.simple-result .sr-label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }

/* ── LEGEND ── */
.legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 20px; padding: 12px 16px; background: #f8faf9; border-radius: 8px; }
.leg-item { display: flex; align-items: center; gap: 6px; font-size: 12px; }

/* ── SIGNATURES ── */
.sig { display: flex; justify-content: space-between; margin-top: 36px; padding-top: 16px; }
.sig-box { text-align: center; }
.sig-line { width: 160px; border-top: 1px solid #cbd5e1; margin: 44px auto 6px; }

/* ── FOOTER ── */
.rep-foot { background: #f8faf9; border-top: 1px solid #e2e8f0; padding: 14px 36px; }
.disc { font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.6; }
.gen-info { display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; margin-top: 6px; }

/* ── PRINT BAR ── */
.pbar { display: flex; gap: 10px; justify-content: center; padding: 16px; background: #fff; max-width: 820px; margin: 0 auto; }
.pbar button, .pbar a { padding: 10px 24px; border-radius: 8px; font-size: 14px; font-family: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn-print { background: #1a6b4a; color: #fff; border: none; }
.btn-back  { background: #fff; color: #1a6b4a; border: 1.5px solid #1a6b4a; }

@media print {
    .pbar { display: none !important; }
    body { background: #fff; }
    .wrap { box-shadow: none; margin: 0; border-radius: 0; }
    .test-block { break-inside: avoid; }
}
</style>
</head>
<body>

<div class="pbar">
    <a href="<?= LAB_APP_URL ?>/modules/orders/view.php?lab=<?= $slug ?>&id=<?= $id ?>" class="btn-back">← Back</a>
    <button class="btn-print" onclick="window.print()">🖨️ Print Report</button>
</div>

<div class="wrap">

    <!-- ── HEADER ── -->
    <div class="head">
        <div class="head-top">
            <div>
                <div class="lab-name">🔬 <?= labClean($labName) ?></div>
                <div class="lab-sub">Clinical Diagnostic Laboratory</div>
                <div class="lab-detail">
                    <?= labClean($labAddress) ?><br>
                    📞 <?= labClean($labPhone) ?> &nbsp;|&nbsp; ✉️ <?= labClean($labEmail) ?>
                </div>
            </div>
            <div class="rep-badge">
                <div class="lbl">Lab Report</div>
                <div class="val"><?= labClean($order['order_no']) ?></div>
                <div class="dt"><?= date('d M Y', strtotime($order['order_date'])) ?></div>
            </div>
        </div>

        <!-- Patient Strip -->
        <div class="pstrip">
            <?php
            $strip = [
                'Patient'      => $order['patient_name'],
                'Patient ID'   => $order['pid'],
                'Age / Gender' => ($age ? $age . ' yrs' : '—') . ' / ' . $order['gender'],
                'Blood Group'  => $order['blood_group'],
                'Phone'        => $order['phone'],
            ];
            if ($order['referred_by']) $strip['Ref. By'] = $order['referred_by'];
            foreach ($strip as $lbl => $val):
            ?>
            <div class="ps-item">
                <div class="ps-lbl"><?= $lbl ?></div>
                <div class="ps-val"><?= labClean($val) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── BODY ── -->
    <div class="body">

        <?php foreach ($items as $item):
            $isPanel   = !empty($subParamsMap[$item['item_id']]);
            $subParams = $subParamsMap[$item['item_id']] ?? [];
            $overallSt = $item['result_status'] ?? 'pending';
        ?>

        <div class="test-block">

            <!-- Test Header -->
            <div class="test-block-head">
                <div>
                    <span class="test-block-name"><?= labClean($item['test_name']) ?></span>
                    <span class="test-block-code"><?= labClean($item['code']) ?></span>
                </div>
                <span class="test-block-status st-<?= $overallSt ?>"><?= ucfirst($overallSt) ?></span>
            </div>

            <?php if ($isPanel): ?>
            <!-- ── PANEL TEST: sub-parameter table ── -->
            <table>
                <thead class="sub-head">
                    <tr>
                        <th style="width:32%">Parameter</th>
                        <th style="width:10%">Short</th>
                        <th style="width:20%">Normal Range (<?= $order['gender'] ?>)</th>
                        <th style="width:10%">Unit</th>
                        <th style="width:16%">Result</th>
                        <th style="width:12%">Status</th>
                    </tr>
                </thead>
                <tbody class="sub-body">
                    <?php foreach ($subParams as $sp):
                        $rv  = $sp['result_value']  ?? '';
                        $rst = $sp['result_status'] ?? 'pending';
                        $nr  = ($order['gender'] === 'Female') ? $sp['normal_range_female'] : $sp['normal_range_male'];
                        $rowCls = match($rst) { 'abnormal' => 'row-ab', 'critical' => 'row-cr', default => '' };
                    ?>
                    <tr class="<?= $rowCls ?>">
                        <td style="font-weight:600;"><?= labClean($sp['parameter_name']) ?></td>
                        <td style="color:#64748b;font-family:'DM Mono',monospace;font-size:11px;"><?= labClean($sp['short_name']) ?></td>
                        <td style="color:#64748b;font-size:12px;"><?= labClean($nr ?? '—') ?></td>
                        <td style="color:#64748b;font-size:12px;"><?= labClean($sp['unit'] ?? '—') ?></td>
                        <td>
                            <?php if ($rv !== '' && $rv !== null): ?>
                            <span class="rv-<?= $rst ?>"><?= labClean($rv) ?></span>
                            <?php else: ?>
                            <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="test-block-status st-<?= $rst ?>" style="font-size:10px;padding:2px 8px;">
                                <?= ucfirst($rst) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php else: ?>
            <!-- ── SIMPLE TEST: single result row ── -->
            <div class="simple-result">
                <div>
                    <div class="sr-label">Normal Range</div>
                    <div>
                        <?= labClean($item['normal_range'] ?? '—') ?>
                        <?php if ($item['unit'] && $item['unit'] !== 'Multiple' && $item['unit'] !== '—'): ?>
                        &nbsp;<?= labClean($item['unit']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="sr-label">Result</div>
                    <div>
                        <?php if ($item['result_value'] && $item['result_value'] !== 'pending'): ?>
                        <span class="rv-<?= $overallSt ?>" style="font-size:15px;">
                            <?= labClean($item['result_value']) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#94a3b8;">Not entered</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($item['result_notes']): ?>
                <div>
                    <div class="sr-label">Notes</div>
                    <div><?= labClean($item['result_notes']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($item['completed_at']): ?>
                <div>
                    <div class="sr-label">Completed</div>
                    <div><?= date('d M Y', strtotime($item['completed_at'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /test-block -->

        <?php endforeach; ?>

        <!-- Legend -->
        <div class="legend">
            <strong style="font-size:12px;color:#64748b;">Legend:</strong>
            <span class="leg-item"><span class="test-block-status st-normal"   style="font-size:11px;padding:2px 8px;">Normal</span> Within reference range</span>
            <span class="leg-item"><span class="test-block-status st-abnormal" style="font-size:11px;padding:2px 8px;">Abnormal</span> Outside reference range</span>
            <span class="leg-item"><span class="test-block-status st-critical" style="font-size:11px;padding:2px 8px;">Critical</span> Requires immediate attention</span>
            <span class="leg-item"><span class="test-block-status st-pending"  style="font-size:11px;padding:2px 8px;">Pending</span> Result not yet entered</span>
        </div>

        <!-- Signatures -->
        <div class="sig">
            <div class="sig-box">
                <div class="sig-line"></div>
                <small style="color:#64748b;">Lab Technician</small>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <small style="color:#64748b;">Pathologist / Lab Director</small>
            </div>
        </div>

    </div><!-- /body -->

    <!-- Footer -->
    <div class="rep-foot">
        <div class="disc">
            <?= labClean($footer) ?><br>
            This report is generated electronically and is valid without a physical signature.
        </div>
        <div class="gen-info">
            <span>Report: <?= labClean($order['order_no']) ?></span>
            <span>Generated: <?= date('d M Y, H:i') ?></span>
            <span>By: <?= labClean($order['by_name'] ?? '—') ?></span>
        </div>
    </div>

</div><!-- /wrap -->
</body>
</html>
