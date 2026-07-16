<?php
// LAB REPORT — results + normal ranges. NO prices shown.
require_once __DIR__ . '/../../includes/config.php';
labRequireLogin();

$id    = (int)($_GET['id'] ?? 0);
$order = $labDb->fetch("
    SELECT o.*, p.name as patient_name, p.patient_id as pid,
           p.phone, p.gender, p.age, p.address, p.blood_group,
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
           oi.id as item_id,
           COALESCE(tc.id, 0)         as category_id,
           COALESCE(tc.name, 'Other') as category_name
    FROM order_items oi
    JOIN tests t ON oi.test_id = t.id
    LEFT JOIN test_categories tc ON t.category_id = tc.id
    WHERE oi.order_id = ?
    ORDER BY COALESCE(tc.name, 'Other'), t.name
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

// Group items by category
$itemsByCategory = [];
foreach ($items as $item) {
    $cat = $item['category_name'];
    if (!isset($itemsByCategory[$cat])) $itemsByCategory[$cat] = [];
    $itemsByCategory[$cat][] = $item;
}

$labName    = labGetSetting($labDb, 'lab_name',      $labInfo['name'] ?? 'Lab');
$labAddress = labGetSetting($labDb, 'lab_address',   '');
$labPhone   = labGetSetting($labDb, 'lab_phone',     '');
$labEmail   = labGetSetting($labDb, 'lab_email',     '');
$labLogo    = labGetSetting($labDb, 'lab_logo',      '');
$labSig     = labGetSetting($labDb, 'lab_signature', '');
$labWm      = labGetSetting($labDb, 'lab_watermark', '');
$footer     = labGetSetting($labDb, 'report_footer', 'Results are for clinical guidance only. Consult your physician.');

$age = $order['age'] ? $order['age'] : null;

$totalCategories = count($itemsByCategory);

// Build patient strip data once, reused in every header
$strip = [
    'Patient'      => $order['patient_name'],
    'Patient ID'   => $order['pid'],
    'Age / Gender' => ($age ? $age . ' yrs' : '—') . ' / ' . $order['gender'],
    'Blood Group'  => $order['blood_group'],
    'Phone'        => $order['phone'],
];
if ($order['referred_by']) $strip['Ref. By'] = $order['referred_by'];
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
* { box-sizing: border-box; margin: 0px; padding: 0px; }
body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; }

/* ── PRINT BAR (screen only) ── */
.pbar { display: flex; gap: 10px; justify-content: center; padding: 16px; background: #fff; max-width: 860px; margin: 0 auto; }
.pbar button, .pbar a { padding: 10px 24px; border-radius: 8px; font-size: 14px; font-family: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn-print { background: #1a6b4a; color: #fff; border: none; }
.btn-back  { background: #fff; color: #1a6b4a; border: 1.5px solid #1a6b4a; }

/* ── CATEGORY PAGE (screen) ── */
.cat-page {
    max-width: 820px;
    margin: 0 auto 40px;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.1);
    display: flex;
    flex-direction: column;
    min-height: 297mm;
}

/* ── WATERMARK ── */
.cat-page  { position: relative; }
.watermark {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%) ;
    width: 70%; max-width: 420px;
    opacity: 0.07;
    pointer-events: none;
    z-index: 0;
    user-select: none;
}

/* ── HEADER ── */
.head { background: linear-gradient(135deg, #d5de4e, #d5de4e); color: #000000; padding: 0; flex-shrink: 0; }
.head-top { padding: 20px 32px; display: flex; justify-content: space-between; align-items: flex-start; }
.lab-logo   { max-height: 60px; max-width: 190px; object-fit: contain; display: block; margin-bottom: 6px; }
.lab-name   { font-size: 20px; font-weight: 700; }
.lab-sub    { font-size: 11px; opacity: .6; letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }
.lab-detail { font-size: 12px; opacity: .75; margin-top: 6px; line-height: 1.8; }
.rep-badge  { text-align: right; }
.rep-badge .lbl { font-size: 11px; opacity: .6; letter-spacing: 1px; text-transform: uppercase; }
.rep-badge .val { font-size: 18px; font-weight: 700; margin-top: 2px; }
.rep-badge .dt  { font-size: 12px; opacity: .7; margin-top: 4px; }
.rep-badge .pg  { font-size: 11px; opacity: .55; margin-top: 3px; }

/* ── PATIENT STRIP ── */
.pstrip { background: #d4d3d3; padding: 10px 32px; display: flex; gap: 24px; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,.15); }
.ps-item .ps-lbl { font-size: 10px; opacity: .55; letter-spacing: .5px; text-transform: uppercase; }
.ps-item .ps-val { font-size: 13px; font-weight: 600; margin-top: 1px; }

/* ── CATEGORY BANNER ── */
.cat-banner { background: #ededed; color: #000000; padding: 8px 32px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.cat-banner-name  { font-size: 13px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
.cat-banner-count { font-size: 11px; opacity: .75; }

/* ── BODY (grows to fill page) ── */
.body { padding: 20px 32px; flex: 1; }

/* ── TEST BLOCK ── */
.test-block { margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.test-block:last-child { margin-bottom: 0; }
.test-block-head { background: #f8faf9; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; }
.test-block-name   { font-size: 14px; font-weight: 700; color: #1e293b; }
.test-block-code   { font-family: 'DM Mono', monospace; font-size: 11px; background: #ede9fe; color: #7c3aed; padding: 2px 8px; border-radius: 4px; margin-left: 8px; }
.test-block-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.st-normal   { background: #dcfce7; color: #166534; }
.st-abnormal { background: #fff7ed; color: #9a3412; }
.st-critical { background: #fee2e2; color: #991b1b; }
.st-pending  { background: #f1f5f9; color: #64748b; }

/* ── SUB-PARAMETER TABLE ── */
table { width: 100%; border-collapse: collapse; }
.sub-head th { background: #fff; color: #54AAC3; font-size: 10px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; padding: 8px 12px; border-bottom: 2px solid #8BD5D0; text-align: left; }
.sub-body td { padding: 9px 12px; font-size: 13px; border-bottom: 1px solid #f1f5f3; vertical-align: middle; }
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
.legend { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 20px; padding: 10px 14px; background: #f8faf9; border-radius: 8px; }
.leg-item { display: flex; align-items: center; gap: 6px; font-size: 12px; }

/* ── BOTTOM BLOCK (signatures + footer merged) ── */
.rep-foot     { background: #f8faf9; border-top: 1px solid #e2e8f0; padding: 16px 32px 14px; flex-shrink: 0; }
.sig          { display: flex; justify-content: space-between; margin-bottom: 4px; }
.sig-box      { text-align: center; }
.sig-line     { width: 160px; border-top: 1px solid #cbd5e1; margin: 8px auto 5px; }
.sig-img      { max-height: 50px; max-width: 160px; object-fit: contain; display: block; margin: 0 auto; }
.sig-spacer   { height: 42px; }
.sig small    { font-size: 11px; color: #64748b; }
.foot-divider { border: none; border-top: 1px dashed #e2e8f0; margin: 10px 0; }
.disc         { font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.6; }
.gen-info     { display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; margin-top: 5px; }

/* ══════════════════════════════════════════════════════
   PRINT STYLES
   ── A4: 210mm × 297mm, zero browser margins so the
      browser's own date/URL/page header+footer vanish.
   ── Each .cat-page fills exactly one A4 sheet.
   ── flex-column layout pins footer to the bottom.
   ── Compact font sizes so CBC (19 rows) fits with header.
══════════════════════════════════════════════════════ */
@page {
    size: A4 portrait;
    margin: 4mm 3mm;
}

@media print {
    html, body { background: #fff; width: 210mm; margin: 0; padding: 0; }

    .pbar,
    .no-print { display: none !important; }

    .cat-page {
        width: 210mm;
        height: 297mm;
        min-height: unset;
        max-width: unset;
        margin: 0;
        padding: 0;
        border-radius: 0;
        box-shadow: none;
        overflow: hidden;
        page-break-after: always;
        break-after: page;
        display: flex;
        flex-direction: column;
    }

    .cat-page:last-child {
        page-break-after: avoid;
        break-after: avoid;
    }

    .head-top   { padding: 10px 20px; }
    .lab-logo   { max-height: 44px; }
    .lab-name   { font-size: 21px; }
    .lab-sub    { font-size: 13px; }
    .lab-detail { font-size: 13px; margin-top: 3px; line-height: 1.5; }
    .rep-badge .lbl { font-size: 13px; }
    .rep-badge .val { font-size: 20px; }
    .rep-badge .dt  { font-size: 13px; }
    .rep-badge .pg  { font-size: 13px; }
    .pstrip   { padding: 6px 20px; gap: 16px; }
    .ps-item .ps-lbl { font-size: 11px; }
    .ps-item .ps-val { font-size: 14px; }
    .cat-banner       { padding: 5px 20px; }
    .cat-banner-name  { font-size: 14px; }
    .cat-banner-count { font-size: 13px; }
    .body { padding: 10px 20px; flex: 1; overflow: hidden; }
    .watermark { opacity: 0.06; }
    .test-block        { margin-bottom: 8px; border-radius: 4px; break-inside: avoid; }
    .test-block-head   { padding: 5px 10px; break-after: avoid; }
    .test-block-name   { font-size: 15px; }
    .test-block-code   { font-size: 13px; padding: 1px 5px; }
    .test-block-status { font-size: 13px; padding: 2px 7px; }
    .sub-head th { font-size: 11px; padding: 5px 8px; }
    .sub-body td { font-size: 13px; padding: 4px 8px; }
    .simple-result       { padding: 6px 10px; gap: 20px; font-size: 14px; }
    .simple-result .sr-label { font-size: 11px; }
    .legend   { padding: 6px 10px; margin-top: 8px; gap: 8px; }
    .leg-item { font-size: 13px; }
    .legend .test-block-status { font-size: 11px !important; padding: 1px 5px !important; }
    .rep-foot     { padding: 8px 20px 10px; margin-top: auto; }
    .sig          { margin-bottom: 2px; }
    .sig-spacer   { height: 24px; }
    .sig-img      { max-height: 28px; }
    .sig-line     { width: 110px; margin: 5px auto 3px; }
    .sig small    { font-size: 11px; }
    .foot-divider { margin: 6px 0; }
    .disc         { font-size: 13px; }
    .gen-info     { font-size: 13px; }
}
</style>
</head>
<body>

<div class="pbar">
    <a href="<?= LAB_APP_URL ?>/modules/orders/view.php?lab=<?= $slug ?>&id=<?= $id ?>" class="btn-back">← Back</a>
    <button class="btn-print" onclick="window.print()">🖨️ Print Report</button>
</div>

<?php
$catIndex = 0;
foreach ($itemsByCategory as $catName => $catItems):
    $catIndex++;
?>

<!-- ══ CATEGORY PAGE <?= $catIndex ?>/<?= $totalCategories ?> — <?= labClean($catName) ?> ══ -->
<div class="cat-page">

    <?php if ($labWm): ?>
    <img src="<?= LAB_APP_URL . labClean($labWm) ?>"
         alt="" class="watermark" aria-hidden="true">
    <?php endif; ?>

    <!-- ── HEADER (full, on every page) ── -->
    <div class="head">
        <div class="head-top">
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
                </div>
            </div>
            <div class="rep-badge">
                <div class="lbl">Lab Report</div>
                <div class="val"><?= labClean($order['order_no']) ?></div>
                <div class="dt"><?= date('d M Y', strtotime($order['order_date'])) ?></div>
                <div class="pg">Page <?= $catIndex ?> of <?= $totalCategories ?></div>
            </div>
        </div>

        <!--  Patient Strip  -->
        <div class="pstrip">
            <?php foreach ($strip as $lbl => $val): ?>
            <div class="ps-item">
                <div class="ps-lbl"><?= $lbl ?></div>
                <div class="ps-val"><?= labClean($val) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div><!-- /head -->

    <!-- ── CATEGORY BANNER ── -->
    <div class="cat-banner">
        <span class="cat-banner-name"><?= labClean($catName) ?></span>
        <span class="cat-banner-count"><?= count($catItems) ?> test<?= count($catItems) !== 1 ? 's' : '' ?> in this category</span>
    </div>

    <!-- ── TEST BLOCKS ── -->
    <div class="body">

        <?php foreach ($catItems as $item):
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
            <!-- Panel test: sub-parameter table -->
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
                        $rv     = $sp['result_value']  ?? '';
                        $rst    = $sp['result_status'] ?? 'pending';
                        $nr     = ($order['gender'] === 'Female') ? $sp['normal_range_female'] : $sp['normal_range_male'];
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
            <!-- Simple test: single result row -->
            <div class="simple-result">
                <?php if ($item['code'] !== 'BGABORH'): ?>
                <div>
                    <div class="sr-label">Normal Range</div>
                    <div>
                        <?= labClean($item['normal_range'] ?? '—') ?>
                        <?php if ($item['unit'] && $item['unit'] !== 'Multiple' && $item['unit'] !== '—'): ?>
                        &nbsp;<?= labClean($item['unit']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div>
                    <div class="sr-label">Result</div>
                    <div>
                        <?php if ($item['result_value'] && $item['result_value'] !== 'pending'): ?>
                        <?php if ($item['code'] === 'BGABORH'):
                            $bgParts = explode(' | ', $item['result_value']);
                            $bgAbo = $bgParts[0] ?? $item['result_value'];
                            $bgRh  = $bgParts[1] ?? '';
                        ?>
                        <span class="rv-<?= $overallSt ?>" style="font-size:15px;display:block;">
                            ABO &nbsp;–&nbsp; <?= labClean(preg_replace('/^ABO\s*/i','',$bgAbo)) ?>
                        </span>
                        <?php if ($bgRh): ?>
                        <span class="rv-<?= $overallSt ?>" style="font-size:15px;display:block;margin-top:6px;">
                            Rh &nbsp;–&nbsp; <?= labClean(preg_replace('/^Rh\s*/i','',$bgRh)) ?>
                        </span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="rv-<?= $overallSt ?>" style="font-size:15px;">
                            <?= labClean($item['result_value']) ?>
                        </span>
                        <?php endif; ?>
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

        <?php endforeach; // end tests in category ?>

        <!-- Legend (on every page) -->
        <div class="legend">
            <strong style="font-size:12px;color:#64748b;">Legend:</strong>
            <span class="leg-item"><span class="test-block-status st-normal"   style="font-size:11px;padding:2px 8px;">Normal</span> Within reference range</span>
            <span class="leg-item"><span class="test-block-status st-abnormal" style="font-size:11px;padding:2px 8px;">Abnormal</span> Outside reference range</span>
            <span class="leg-item"><span class="test-block-status st-critical" style="font-size:11px;padding:2px 8px;">Critical</span> Requires immediate attention</span>
            <span class="leg-item"><span class="test-block-status st-pending"  style="font-size:11px;padding:2px 8px;">Pending</span> Result not yet entered</span>
        </div>

    </div><!-- /body -->

    <!-- ── BOTTOM BLOCK: signatures + footer, pinned to bottom of page ── -->
    <div class="rep-foot">

        <!-- Signatures -->
        <div class="sig" style="justify-content:flex-end;">
            <div class="sig-box">
                <?php if ($labSig): ?>
                <img src="<?= LAB_APP_URL . labClean($labSig) ?>"
                     alt="Authorised Signature"
                     class="sig-img">
                <?php else: ?>
                <div class="sig-spacer"></div>
                <?php endif; ?>
                <div class="sig-line"></div>
                <small>Authorized Signatory</small>
            </div>
        </div>

        <!-- Divider -->
        <div class="foot-divider"></div>

        <!-- Disclaimer + report meta -->
        <div class="disc">
            <?= labClean($footer) ?><br>
            This report is generated electronically and is valid without a physical signature.
        </div>
        <div class="gen-info">
            <span>Report: <?= labClean($order['order_no']) ?></span>
            <span>Generated: <?= date('d M Y, H:i') ?></span>
            <span>&copy; <?= date('Y') ?> LondonLab. All rights reserved.</span>
        </div>

    </div>

</div><!-- /cat-page -->

<?php endforeach; // end categories ?>

</body>
</html>