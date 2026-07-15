<?php
$pageTitle = 'Order Details';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('orders');

$id    = (int)($_GET['id'] ?? 0);
$order = $labDb->fetch("
    SELECT o.*, p.name as patient_name, p.patient_id as pid,
           p.phone, p.gender, p.referral_type, p.doctor_id,
           u.name as by_name, doc.name as ref_doctor_name
    FROM orders o
    JOIN patients p ON o.patient_id = p.id
    LEFT JOIN users u ON o.created_by = u.id
    LEFT JOIN doctors doc ON p.doctor_id = doc.id
    WHERE o.id = ?
", [$id]);

if (!$order) {
    labSetFlash('error','Order not found.');
    header('Location: '.LAB_APP_URL.'/modules/orders/index.php?lab='.$slug);
    exit;
}

$items = $labDb->fetchAll("
    SELECT oi.*, t.name as test_name, t.code, t.normal_range, t.unit, t.id as test_db_id
    FROM order_items oi
    JOIN tests t ON oi.test_id = t.id
    WHERE oi.order_id = ?
    ORDER BY t.name
", [$id]);

$payments  = $labDb->fetchAll("SELECT * FROM payments WHERE order_id=? ORDER BY paid_at DESC", [$id]);
$totalPaid = array_sum(array_column($payments, 'amount'));
$balance   = $order['net_amount'] - $totalPaid;

// Can the current user remove a test from this order? Same permission tier as
// editing the discount (a billing-affecting action), and only while the order
// isn't already delivered/cancelled. At least one test must always remain.
$canManageItems = labCanEdit() && !in_array($order['status'], ['delivered','cancelled']);

// Load sub-parameters for each test item
$subParamsMap = [];
foreach ($items as $item) {
    $subs = $labDb->fetchAll("
        SELECT sp.*, tsr.result_value, tsr.result_status, tsr.id as sub_result_id
        FROM test_sub_parameters sp
        LEFT JOIN test_sub_results tsr
               ON tsr.sub_parameter_id = sp.id AND tsr.order_item_id = ?
        WHERE sp.test_id = ? AND sp.is_active = 1
        ORDER BY sp.sort_order
    ", [$item['id'], $item['test_db_id']]);
    if (!empty($subs)) $subParamsMap[$item['id']] = $subs;
}

// ── STATUS UPDATE ─────────────────────────────────────────────
if (isset($_GET['status']) && labCanEdit()) {
    $ns = $_GET['status'];
    if (in_array($ns, ['pending','sample_collected','processing','completed','delivered','cancelled'])) {
        $labDb->execute("UPDATE orders SET status=? WHERE id=?", [$ns, $id]);
        labSetFlash('success', 'Status updated to '.ucwords(str_replace('_',' ',$ns)));
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
        exit;
    }
}

// ── SAVE RESULTS (individual test) ────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_results'])) {
    labVerifyCsrf();
    $itemId = (int)($_POST['item_id'] ?? 0);

    // Panel test — save sub-parameter results
    if (!empty($_POST['sub_result'][$itemId])) {
        foreach ($_POST['sub_result'][$itemId] as $subParamId => $resultVal) {
            $resultVal = trim($resultVal);
            $sp = $labDb->fetch("SELECT normal_range_male, normal_range_female FROM test_sub_parameters WHERE id=?", [$subParamId]);
            $nRange = ($order['gender']==='Female') ? ($sp['normal_range_female']??'') : ($sp['normal_range_male']??'');
            $st = ($resultVal !== '') ? autoStatus($resultVal, $nRange) : 'pending';

            $exists = $labDb->fetch("SELECT id FROM test_sub_results WHERE order_item_id=? AND sub_parameter_id=?", [$itemId, $subParamId]);
            if ($exists) {
                $labDb->execute("UPDATE test_sub_results SET result_value=?,result_status=? WHERE id=?", [$resultVal,$st,$exists['id']]);
            } else {
                $labDb->execute("INSERT INTO test_sub_results (order_item_id,sub_parameter_id,result_value,result_status) VALUES (?,?,?,?)", [$itemId,$subParamId,$resultVal,$st]);
            }
        }

        // ── AUTO-CALCULATE FORMULA FIELDS ─────────────────────
        // Get test code for this order item
        $testCode = $labDb->fetch("SELECT t.code FROM order_items oi JOIN tests t ON oi.test_id=t.id WHERE oi.id=?", [$itemId])['code'] ?? '';

        // Helper: get a sub-result value by short_name for this order item
        $getVal = function(string $shortName) use ($labDb, $itemId): ?float {
            $row = $labDb->fetch("
                SELECT tsr.result_value FROM test_sub_results tsr
                JOIN test_sub_parameters sp ON tsr.sub_parameter_id = sp.id
                WHERE tsr.order_item_id = ? AND sp.short_name = ?
            ", [$itemId, $shortName]);
            return ($row && $row['result_value'] !== '') ? (float)$row['result_value'] : null;
        };

        // Helper: upsert a calculated sub-result
        $saveCalc = function(string $shortName, float $value) use ($labDb, $itemId, $order) {
            $sp = $labDb->fetch("
                SELECT sp.id, sp.normal_range_male, sp.normal_range_female
                FROM test_sub_parameters sp
                JOIN test_sub_results tsr ON tsr.sub_parameter_id = sp.id
                WHERE tsr.order_item_id = ? AND sp.short_name = ?
                UNION
                SELECT sp.id, sp.normal_range_male, sp.normal_range_female
                FROM test_sub_parameters sp
                JOIN order_items oi ON oi.id = ?
                JOIN tests t ON oi.test_id = t.id AND sp.test_id = t.id
                WHERE sp.short_name = ?
                LIMIT 1
            ", [$itemId, $shortName, $itemId, $shortName]);
            if (!$sp) return;
            $nRange = ($order['gender']==='Female') ? ($sp['normal_range_female']??'') : ($sp['normal_range_male']??'');
            $st = autoStatus((string)$value, $nRange);
            $rounded = round($value, 2);
            $exists = $labDb->fetch("SELECT id FROM test_sub_results WHERE order_item_id=? AND sub_parameter_id=?", [$itemId, $sp['id']]);
            if ($exists) {
                $labDb->execute("UPDATE test_sub_results SET result_value=?,result_status=? WHERE id=?", [$rounded,$st,$exists['id']]);
            } else {
                $labDb->execute("INSERT INTO test_sub_results (order_item_id,sub_parameter_id,result_value,result_status) VALUES (?,?,?,?)", [$itemId,$sp['id'],$rounded,$st]);
            }
        };

        // ── CBC Formulas ──────────────────────────────────────
        if ($testCode === 'CBC') {
            // HB% = Haemoglobin × 7
            $hgb = $getVal('HGB');
            if ($hgb !== null) {
                $saveCalc('HB%', $hgb * 7);
            }
        }

        // ── KFT Formulas ──────────────────────────────────────
        if ($testCode === 'KFT') {
            // BUN = Blood Urea × 0.467
            $urea = $getVal('UREA');
            if ($urea !== null) {
                $saveCalc('BUN', $urea * 0.467);
            }
        }

        // ── LFT Formulas ──────────────────────────────────────
        if ($testCode === 'LFT') {
            // Indirect Bilirubin = Total Bilirubin − Direct Bilirubin
            $tbili = $getVal('T.BILI');
            $dbili = $getVal('D.BILI');
            if ($tbili !== null && $dbili !== null) {
                $saveCalc('I.BILI', $tbili - $dbili);
            }
            // Globulin = Total Protein − Albumin
            $tprot = $getVal('T.PROT');
            $alb   = $getVal('ALB');
            if ($tprot !== null && $alb !== null) {
                $glob = $tprot - $alb;
                $saveCalc('GLOB', $glob);
                // A:G Ratio = Albumin ÷ Globulin
                if ($glob > 0) {
                    $saveCalc('A:G', round($alb / $glob, 2));
                }
            }
        }

        // ── PT/INR Formulas ───────────────────────────────────
        if ($testCode === 'PTINR') {
            // Prothrombin Ratio = PT Test ÷ Control Plasma
            $ptTest  = $getVal('PT TEST');
            $control = $getVal('CONTROL');
            if ($ptTest !== null && $control !== null && $control > 0) {
                $saveCalc('PT RATIO', round($ptTest / $control, 2));
            }
        }
        // ── END FORMULA CALCULATIONS ──────────────────────────

        // Determine overall status from sub-results
        $subStatuses = array_column($labDb->fetchAll("SELECT result_status FROM test_sub_results WHERE order_item_id=?", [$itemId]), 'result_status');
        if      (in_array('critical',  $subStatuses)) $overall = 'critical';
        elseif  (in_array('abnormal',  $subStatuses)) $overall = 'abnormal';
        elseif  (in_array('pending',   $subStatuses)) $overall = 'pending';
        else $overall = 'normal';
        $labDb->execute("UPDATE order_items SET result_status=?, completed_at=IF(?!='pending',NOW(),NULL) WHERE id=?", [$overall,$overall,$itemId]);

    } else {
        // Simple test — single result
        $val = trim($_POST['result_value'][$itemId] ?? '');
        $nt  = trim($_POST['result_notes'][$itemId] ?? '');
        // Status is auto-derived from the test's (gender-resolved) range —
        // never trust the client-submitted hidden field as the source of truth.
        $itemRange = '';
        foreach ($items as $it) {
            if ($it['id'] == $itemId) { $itemRange = $it['normal_range'] ?? ''; break; }
        }
        $resolvedRange = resolveRangeForGender($itemRange, $order['gender'] ?? '');
        $st = ($val !== '') ? autoStatus($val, $resolvedRange) : 'pending';
        $labDb->execute("UPDATE order_items SET result_value=?,result_status=?,result_notes=?,completed_at=IF(?!='pending',NOW(),NULL) WHERE id=? AND order_id=?",
            [$val,$st,$nt,$st,$itemId,$id]);
    }

    // Auto-complete order if all items done
    $pendingCount = $labDb->fetch("SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND result_status='pending'", [$id])['c'];
    if ($pendingCount == 0) $labDb->execute("UPDATE orders SET status='completed' WHERE id=?", [$id]);

    labSetFlash('success', 'Results saved successfully!');
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
    exit;
}

// ── ADD PAYMENT ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_payment'])) {
    labVerifyCsrf();
    $amt = (float)($_POST['amount'] ?? 0);
    $mth = $_POST['method'] ?? 'cash';
    $ref = trim($_POST['transaction_ref'] ?? '');
    if ($amt > 0) {
        $labDb->execute("INSERT INTO payments (order_id,amount,method,transaction_ref,status,paid_at) VALUES (?,?,?,?,'completed',NOW())",
            [$id,$amt,$mth,$ref]);
        labSetFlash('success', labMoney($amt).' payment recorded.');
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
        exit;
    }
}

// ── REMOVE TEST FROM ORDER (recalculates total, discount, commission) ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_test_item']) && labCanEdit()) {
    labVerifyCsrf();

    if (in_array($order['status'], ['delivered','cancelled'])) {
        labSetFlash('error', 'Cannot modify tests on a delivered or cancelled order.');
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
        exit;
    }

    $itemId = (int)($_POST['item_id'] ?? 0);
    $item   = $labDb->fetch("
        SELECT oi.*, t.name as test_name
        FROM order_items oi JOIN tests t ON oi.test_id = t.id
        WHERE oi.id=? AND oi.order_id=?
    ", [$itemId, $id]);

    if (!$item) {
        labSetFlash('error', 'Test item not found on this order.');
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
        exit;
    }

    $itemCount = (int)$labDb->fetch("SELECT COUNT(*) as c FROM order_items WHERE order_id=?", [$id])['c'];
    if ($itemCount <= 1) {
        labSetFlash('error', 'Cannot remove the only test on an order — cancel the whole order instead.');
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
        exit;
    }

    $labDb->beginTransaction();
    try {
        // test_sub_results rows for this item cascade-delete automatically (FK ON DELETE CASCADE)
        $labDb->execute("DELETE FROM order_items WHERE id=? AND order_id=?", [$itemId, $id]);

        // Recompute the subtotal from what's actually left in the DB —
        // never trust the old stored total_amount.
        $newTotal = (float)($labDb->fetch(
            "SELECT COALESCE(SUM(price),0) as t FROM order_items WHERE order_id=?", [$id]
        )['t'] ?? 0);

        $oldDiscount       = (float)$order['discount'];
        $oldDoctorDiscount = (float)$order['doctor_discount'];
        $oldTotalDiscount  = $oldDiscount + $oldDoctorDiscount;

        // If the remaining subtotal is now smaller than the existing discount
        // (e.g. the removed test was most of the order), scale BOTH discount
        // components down proportionally — preserving their ratio — so the
        // combined discount never exceeds the new subtotal. Same rule as the
        // create/edit-discount validation, just applied automatically here.
        if ($oldTotalDiscount > $newTotal + 0.0001 && $oldTotalDiscount > 0) {
            $scale             = $newTotal / $oldTotalDiscount;
            $newDiscount       = round($oldDiscount * $scale, 2);
            $newDoctorDiscount = round($oldDoctorDiscount * $scale, 2);
        } else {
            $newDiscount       = $oldDiscount;
            $newDoctorDiscount = $oldDoctorDiscount;
        }

        $newNet = max(0, $newTotal - $newDiscount - $newDoctorDiscount);

        $labDb->execute(
            "UPDATE orders SET total_amount=?, discount=?, doctor_discount=?, net_amount=? WHERE id=?",
            [$newTotal, $newDiscount, $newDoctorDiscount, $newNet, $id]
        );

        // Keep the doctor's commission in sync with the new order total and
        // doctor discount — but never silently rewrite a commission that's
        // already been paid out (same rule as the discount-edit handler).
        $isDoctorRef = ($order['referral_type'] === 'doctor' && $order['doctor_id']);
        $commNote = '';
        if ($isDoctorRef) {
            $comm = $labDb->fetch("SELECT * FROM doctor_commissions WHERE order_id=?", [$id]);
            if ($comm && $comm['status'] === 'pending') {
                $baseComm = ($comm['commission_type'] === 'percentage')
                    ? ($newTotal * $comm['commission_rate'] / 100)
                    : $comm['commission_rate'];
                $newCommAmt = max(0, round($baseComm - $newDoctorDiscount, 2));
                $labDb->execute(
                    "UPDATE doctor_commissions SET order_amount=?, commission_amount=? WHERE id=?",
                    [$newTotal, $newCommAmt, $comm['id']]
                );
            } elseif ($comm && $comm['status'] !== 'pending') {
                $commNote = ' Note: this doctor\'s commission was already '.$comm['status'].', so it was NOT recalculated — adjust it manually if needed.';
            }
        }

        // Re-evaluate order status: removing the last pending test can complete the order.
        $pendingCount = (int)$labDb->fetch(
            "SELECT COUNT(*) as c FROM order_items WHERE order_id=? AND result_status='pending'", [$id]
        )['c'];
        if ($pendingCount == 0 && !in_array($order['status'], ['delivered','cancelled'])) {
            $labDb->execute("UPDATE orders SET status='completed' WHERE id=?", [$id]);
        }

        $labDb->commit();

        $refundNote = '';
        if ($totalPaid > $newNet + 0.0001) {
            $refundNote = ' Note: ₹'.number_format($totalPaid, 2).' was already paid — a refund of '.labMoney($totalPaid - $newNet).' is now due to the patient.';
        }

        labSetFlash('success', '"'.$item['test_name'].'" removed. New order total: '.labMoney($newTotal).'.'.$refundNote.$commNote);
    } catch (Exception $e) {
        $labDb->rollback();
        labSetFlash('error', 'Failed to remove test: '.$e->getMessage());
    }

    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
    exit;
}

// ── UPDATE DISCOUNT (post-creation, e.g. after payment) ────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_discount']) && labCanEdit()) {
    labVerifyCsrf();
    $isDoctorRef = ($order['referral_type'] === 'doctor' && $order['doctor_id']);

    $newDiscount = max(0, (float)($_POST['discount'] ?? 0));
    // Doctor discount only ever applies to a tied-up doctor referral —
    // self / external referrals get lab discount only, regardless of what's posted.
    $newDoctorDiscount = $isDoctorRef ? max(0, (float)($_POST['doctor_discount'] ?? 0)) : 0;

    // Server-side guard: total discount can never exceed the order subtotal.
    // The client-side check can be bypassed (disabled JS, direct POST, replayed
    // form, etc.), so this is the authoritative check — never trust the client alone.
    if (($newDiscount + $newDoctorDiscount) > $order['total_amount'] + 0.0001) {
        labSetFlash('error', 'Total discount (₹'.number_format($newDiscount + $newDoctorDiscount, 2).') cannot be greater than the subtotal amount (₹'.number_format($order['total_amount'], 2).').');
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
        exit;
    }

    $newNet = max(0, $order['total_amount'] - $newDiscount - $newDoctorDiscount);
    $labDb->execute("UPDATE orders SET discount=?, doctor_discount=?, net_amount=? WHERE id=?",
        [$newDiscount, $newDoctorDiscount, $newNet, $id]);

    // Keep the doctor's commission in sync with the new doctor discount —
    // but never silently rewrite a commission that's already been paid out.
    if ($isDoctorRef) {
        $comm = $labDb->fetch("SELECT * FROM doctor_commissions WHERE order_id=?", [$id]);
        if ($comm && $comm['status'] === 'pending') {
            $baseComm = ($comm['commission_type'] === 'percentage')
                ? ($comm['order_amount'] * $comm['commission_rate'] / 100)
                : $comm['commission_rate'];
            $newCommAmt = max(0, $baseComm - $newDoctorDiscount);
            $labDb->execute("UPDATE doctor_commissions SET commission_amount=? WHERE id=?", [$newCommAmt, $comm['id']]);
        } elseif ($comm && $comm['status'] !== 'pending') {
            labSetFlash('success', 'Discount updated. Note: this doctor\'s commission was already '.$comm['status'].', so it was NOT recalculated — adjust it manually if needed.');
            header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
            exit;
        }
    }

    labSetFlash('success', 'Discount updated successfully.');
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug.'&id='.$id);
    exit;
}

// Auto-determine Normal / Abnormal from range string
function autoStatus(string $value, string $range): string {
    if ($value === '') return 'pending';
    if ($range==='' || stripos($range,'see report')!==false || stripos($range,'negative')!==false
        || stripos($range,'non-reactive')!==false || stripos($range,'no growth')!==false) return 'normal';
    $num = (float)$value;
    if (!is_numeric(trim($value))) return 'normal';
    if (preg_match('/^([\d.]+)\s*-\s*([\d.]+)$/', trim($range), $m))
        return ($num < (float)$m[1] || $num > (float)$m[2]) ? 'abnormal' : 'normal';
    if (preg_match('/^<\s*([\d.]+)$/', trim($range), $m))  return $num >= (float)$m[1] ? 'abnormal' : 'normal';
    if (preg_match('/^>\s*([\d.]+)$/', trim($range), $m))  return $num <= (float)$m[1] ? 'abnormal' : 'normal';
    return 'normal';
}

// Simple tests store a single normal_range column, but some are written as a
// compound gender string, e.g. "0-15 (M) / 0-20 (F) mm/hr" or
// "13.0-17.0 (M) / 12.0-15.5 (F) g/dL". Pull out the segment for the given
// gender so autoStatus() gets a clean "0-20" instead of the whole string
// (which would never match the dash regex and silently default to 'normal').
function resolveRangeForGender(string $range, ?string $gender): string {
    $range = trim($range);
    if ($range === '') return $range;
    $g = strtoupper(substr(trim((string)$gender), 0, 1)); // 'M' or 'F'
    if ($g !== 'M' && $g !== 'F') return $range;
    if (preg_match('/([<>]?[\d.]+(?:\s*-\s*[\d.]+)?)\s*\(\s*' . $g . '(?:ale)?\s*\)/i', $range, $m)) {
        return trim($m[1]);
    }
    return $range;
}

$pageTitle = labClean($order['order_no']);
?>

<style>
.result-status-normal   { color:#166534; font-weight:700; }
.result-status-abnormal { color:#c2410c; font-weight:700; }
.result-status-critical { color:#991b1b; font-weight:700; }
.result-status-pending  { color:#94a3b8; }

.sub-table th { background:#f8faf9; font-size:11px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; padding:8px 12px; color:#64748b; border-bottom:2px solid #e2e8f0; }
.sub-table td { padding:9px 12px; font-size:13px; border-bottom:1px solid #f1f5f3; vertical-align:middle; }
.sub-table tr:last-child td { border-bottom:none; }
.sub-table tr.row-abnormal { background:#fff7ed; }
.sub-table tr.row-critical { background:#fee2e2; }

.test-card { border:1px solid #e2e8f0; border-radius:12px; margin-bottom:16px; overflow:hidden; }
.test-card-header { background:#f8faf9; padding:12px 18px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e2e8f0; }
.test-card-body { padding:0; }
</style>

<div class="page-header">
    <div>
        <h2><i class="bi bi-clipboard2-check-fill me-2 text-warning"></i><?= labClean($order['order_no']) ?></h2>
        <p><?= labClean($order['patient_name']) ?> &bull; <?= date('d M Y H:i', strtotime($order['order_date'])) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="badge-status status-<?= $order['status'] ?> fs-6"><?= ucwords(str_replace('_',' ',$order['status'])) ?></span>
        <a href="<?= LAB_APP_URL ?>/modules/orders/invoice.php?lab=<?= $slug ?>&id=<?= $id ?>" target="_blank" class="btn btn-outline-success">
            <i class="bi bi-receipt me-2"></i>Invoice
        </a>
        <a href="<?= LAB_APP_URL ?>/modules/orders/report.php?lab=<?= $slug ?>&id=<?= $id ?>" target="_blank" class="btn btn-outline-info">
            <i class="bi bi-file-earmark-medical me-2"></i>Report
        </a>
        <a href="<?= LAB_APP_URL ?>/modules/orders/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">

    <!-- ── LEFT: TEST RESULTS ─────────────────────────── -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-droplet-half"></i> Test Results
                <small class="text-muted ms-2 fw-normal">Click "Enter Result" on each test individually</small>
            </div>
            <div class="card-body">

                <?php foreach ($items as $item):
                    $isPanel   = !empty($subParamsMap[$item['id']]);
                    $subParams = $subParamsMap[$item['id']] ?? [];
                    $overallSt = $item['result_status'] ?? 'pending';
                    $canEdit   = labCanReport() && !in_array($order['status'], ['delivered','cancelled']);

                    // Count filled sub-params for panels
                    $filledCount = 0;
                    $totalCount  = count($subParams);
                    if ($isPanel) {
                        foreach ($subParams as $sp) {
                            if ($sp['result_value'] !== null && $sp['result_value'] !== '') $filledCount++;
                        }
                    }
                ?>

                <div class="test-card">
                    <!-- Test Header -->
                    <div class="test-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <div class="fw-bold" style="font-size:15px;"><?= labClean($item['test_name']) ?></div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <code style="font-size:11px;background:#ede9fe;color:#7c3aed;padding:1px 6px;border-radius:4px;"><?= labClean($item['code']) ?></code>
                                    <?php if ($isPanel): ?>
                                    <span class="badge bg-info-subtle text-info" style="font-size:10px;">
                                        <?= $filledCount ?>/<?= $totalCount ?> filled
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-status status-<?= $overallSt ?>"><?= ucfirst($overallSt) ?></span>
                            <?php if ($canEdit): ?>
                            <button class="btn btn-sm btn-outline-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modal_<?= $item['id'] ?>">
                                <i class="bi bi-pencil-fill me-1"></i>Enter Result
                            </button>
                            <?php endif; ?>
                            <?php if ($canManageItems && count($items) > 1): ?>
                            <form method="POST" class="d-inline remove-test-form"
                                  data-test-name="<?= labClean($item['test_name']) ?>"
                                  data-test-price="<?= (float)$item['price'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
                                <input type="hidden" name="remove_test_item" value="1">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove this test from the order">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Test Body: show results if any entered -->
                    <?php if ($isPanel && $filledCount > 0): ?>
                    <div class="test-card-body">
                        <table class="table sub-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:35%">Parameter</th>
                                    <th style="width:25%">Normal Range (<?= $order['gender'] ?>)</th>
                                    <th style="width:12%">Unit</th>
                                    <th style="width:18%">Result</th>
                                    <th style="width:10%">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subParams as $sp):
                                    $rv  = $sp['result_value']  ?? '';
                                    $rst = $sp['result_status'] ?? 'pending';
                                    $nr  = ($order['gender']==='Female') ? $sp['normal_range_female'] : $sp['normal_range_male'];
                                    $rowCls = $rst==='abnormal' ? 'row-abnormal' : ($rst==='critical' ? 'row-critical' : '');
                                ?>
                                <tr class="<?= $rowCls ?>">
                                    <td>
                                        <div class="fw-semibold"><?= labClean($sp['parameter_name']) ?></div>
                                        <small class="text-muted"><?= labClean($sp['short_name']) ?></small>
                                    </td>
                                    <td class="text-muted" style="font-size:12px;"><?= labClean($nr ?? '—') ?></td>
                                    <td class="text-muted" style="font-size:12px;"><?= labClean($sp['unit'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($rv !== ''): ?>
                                        <span class="result-status-<?= $rst ?> fs-6"><?= labClean($rv) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status status-<?= $rst ?>" style="font-size:10px;"><?= ucfirst($rst) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php elseif (!$isPanel && $item['result_value'] && $item['result_value'] !== 'pending'): ?>
                    <!-- Simple test with result -->
                    <div class="px-4 py-3 d-flex gap-4" style="font-size:13px;">
                        <div>
                            <span class="text-muted">Normal Range: </span>
                            <span><?= labClean($item['normal_range']??'—') ?> <?= labClean($item['unit']??'') ?></span>
                        </div>
                        <div>
                            <span class="text-muted">Result: </span>
                            <span class="result-status-<?= $item['result_status'] ?> fs-6"><?= labClean($item['result_value']) ?></span>
                        </div>
                        <?php if ($item['result_notes']): ?>
                        <div><span class="text-muted">Notes: </span><?= labClean($item['result_notes']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php else: ?>
                    <!-- No result yet -->
                    <div class="px-4 py-3 text-muted" style="font-size:13px;">
                        <?php if ($isPanel): ?>
                        <i class="bi bi-info-circle me-1"></i>
                        <?= $totalCount ?> sub-parameters — click <strong>Enter Result</strong> to fill values.
                        <?php else: ?>
                        <i class="bi bi-info-circle me-1"></i>
                        Normal Range: <?= labClean($item['normal_range']??'—') ?> <?= labClean($item['unit']??'') ?> — not entered yet.
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div><!-- /test-card -->

                <?php endforeach; ?>

            </div>
        </div>

        <!-- Payments -->
        <div class="card">
            <div class="card-header justify-content-between">
                <span><i class="bi bi-cash"></i> Payments</span>
                <?php if (labCanEdit()): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#payModal">
                    <i class="bi bi-plus me-1"></i>Add Payment
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                <p class="text-center text-muted py-4 small">No payments yet.</p>
                <?php else: ?>
                <table class="table mb-0">
                    <thead><tr><th>Date</th><th>Method</th><th>Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><?= date('d M Y H:i',strtotime($pay['paid_at'])) ?></td>
                            <td><?= ucfirst(str_replace('_',' ',$pay['method'])) ?></td>
                            <td><strong><?= labMoney($pay['amount']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── RIGHT SIDEBAR ──────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Invoice Summary -->
        <div class="card mb-4">
            <div class="card-header justify-content-between">
                <span><i class="bi bi-receipt"></i> Summary</span>
                <?php if (labCanEdit()): ?>
                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#discountModal">
                    <i class="bi bi-tag me-1"></i>Edit Discount
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Subtotal</span><strong><?= labMoney($order['total_amount']) ?></strong>
                </div>
                <?php if ($order['discount'] > 0): ?>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Lab Discount</span><strong class="text-success">−<?= labMoney($order['discount']) ?></strong>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['doctor_discount']) && $order['doctor_discount'] > 0): ?>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span style="color:#d97706;">Doctor's Discount</span><strong class="text-warning">−<?= labMoney($order['doctor_discount']) ?></strong>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <strong>Net Total</strong><strong class="text-primary fs-5"><?= labMoney($order['net_amount']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Paid</span><strong class="text-success"><?= labMoney($totalPaid) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <strong><?= $balance < -0.0001 ? 'Refund Due' : 'Balance' ?></strong>
                    <?php if ($balance < -0.0001): ?>
                    <strong class="text-warning"><?= labMoney(abs($balance)) ?> overpaid</strong>
                    <?php else: ?>
                    <strong class="<?= $balance>0.0001?'text-danger':'text-success' ?>"><?= labMoney($balance) ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status Update -->
        <?php if (labCanEdit() && !in_array($order['status'],['delivered','cancelled'])): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-arrow-repeat"></i> Update Status</div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php
                    $nextMap = [
                        'pending'          => ['sample_collected'=>'✅ Sample Collected','cancelled'=>'❌ Cancel'],
                        'sample_collected' => ['processing'=>'🔬 Mark Processing'],
                        'processing'       => ['completed'=>'✔️ Mark Completed'],
                        'completed'        => ['delivered'=>'📦 Mark Delivered'],
                    ];
                    foreach ($nextMap[$order['status']]??[] as $ns=>$lbl): ?>
                    <a href="?lab=<?= $slug ?>&id=<?= $id ?>&status=<?= $ns ?>"
                       class="btn btn-sm <?= str_contains($ns,'cancel')?'btn-outline-danger':'btn-outline-success' ?>"
                       onclick="return confirm('Update to: <?= htmlspecialchars($lbl) ?>?')">
                        <?= $lbl ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Patient -->
        <div class="card">
            <div class="card-header"><i class="bi bi-person"></i> Patient</div>
            <div class="card-body">
                <div class="fw-bold"><?= labClean($order['patient_name']) ?></div>
                <div class="text-muted small mb-1"><?= labClean($order['pid']) ?></div>
                <div class="small mb-2"><?= labClean($order['phone']) ?></div>
                <div class="small text-muted mb-3">Gender: <strong><?= labClean($order['gender']) ?></strong></div>
                <a href="<?= LAB_APP_URL ?>/modules/patients/view.php?lab=<?= $slug ?>&id=<?= $order['patient_id'] ?>"
                   class="btn btn-sm btn-outline-primary">View Patient</a>
            </div>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════
     INDIVIDUAL RESULT MODALS (one per test)
     ═══════════════════════════════════════════════════════ -->
<?php foreach ($items as $item):
    $isPanel   = !empty($subParamsMap[$item['id']]);
    $subParams = $subParamsMap[$item['id']] ?? [];
?>
<div class="modal fade" id="modal_<?= $item['id'] ?>" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog <?= $isPanel ? 'modal-xl' : 'modal-md' ?>">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
                <input type="hidden" name="save_results" value="1">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">

                <div class="modal-header" style="background:#f8faf9;border-bottom:2px solid #e2e8f0;">
                    <div>
                        <h5 class="modal-title fw-bold mb-0"><?= labClean($item['test_name']) ?></h5>
                        <small class="text-muted">
                            <code><?= labClean($item['code']) ?></code>
                            &bull; Patient: <?= labClean($order['patient_name']) ?>
                            &bull; Gender: <strong><?= labClean($order['gender']) ?></strong>
                        </small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <?php if ($isPanel): ?>
                    <!-- ── PANEL TEST: sub-parameters grid ── -->
                    <div class="alert alert-info py-2 mb-3" style="font-size:13px;">
                        <i class="bi bi-info-circle me-1"></i>
                        Normal ranges shown for <strong><?= $order['gender'] ?></strong>.
                        Status (Normal/Abnormal) updates automatically as you type.
                    </div>
                    <div class="table-responsive">
                        <table class="table sub-table mb-0" style="font-size:13px;">
                            <thead>
                                <tr>
                                    <th style="width:28%">Parameter</th>
                                    <th style="width:22%">Normal Range</th>
                                    <th style="width:10%">Unit</th>
                                    <th style="width:25%">Enter Value</th>
                                    <th style="width:15%">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subParams as $sp):
                                    $nr  = ($order['gender']==='Female') ? $sp['normal_range_female'] : $sp['normal_range_male'];
                                    $rv  = $sp['result_value'] ?? '';
                                    $rst = $sp['result_status'] ?? 'pending';
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= labClean($sp['parameter_name']) ?></div>
                                        <small class="text-muted"><?= labClean($sp['short_name']) ?></small>
                                    </td>
                                    <td class="text-muted" style="font-size:12px;"><?= labClean($nr ?? '—') ?></td>
                                    <td class="text-muted" style="font-size:12px;"><?= labClean($sp['unit'] ?? '—') ?></td>
                                    <td>
                                        <input type="number"
                                               step="any"
                                               name="sub_result[<?= $item['id'] ?>][<?= $sp['id'] ?>]"
                                               class="form-control form-control-sm sub-input"
                                               data-range="<?= labClean($nr ?? '') ?>"
                                               data-itemid="<?= $item['id'] ?>"
                                               data-paramid="<?= $sp['id'] ?>"
                                               data-shortname="<?= labClean($sp['short_name']) ?>"
                                               data-testcode="<?= labClean($item['code']) ?>"
                                               value="<?= labClean($rv) ?>"
                                               placeholder="—"
                                               autocomplete="off">
                                    </td>
                                    <td>
                                        <?php
                                        $spQual = (trim($nr??'') === '' || !preg_match('/\d/', $nr??'')
                                            || preg_match('/see report|negative|non-reactive|no growth/i', $nr??''));
                                        ?>
                                        <?php if (!$spQual): ?>
                                        <span class="badge-status status-<?= $rst ?> live-status-<?= $item['id'] ?>_<?= $sp['id'] ?>"
                                              style="font-size:10px;">
                                            <?= ucfirst($rst) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted" style="font-size:11px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php else: ?>
                    <!-- ── SIMPLE TEST: single result ── -->
                    <div class="p-2">
                        <div class="alert alert-light py-2 mb-3" style="font-size:13px;border:1px solid #e2e8f0;">
                            <strong>Normal Range:</strong>
                            <?= labClean($item['normal_range']??'—') ?>
                            <?php if ($item['unit'] && $item['unit']!=='Multiple' && $item['unit']!=='—'): ?>
                            &nbsp;<?= labClean($item['unit']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Result Value</label>
                                <input type="text"
                                       name="result_value[<?= $item['id'] ?>]"
                                       class="form-control form-control-lg simple-result-input"
                                       data-itemid="<?= $item['id'] ?>"
                                       data-range="<?= labClean($item['normal_range']??'') ?>"
                                       data-gender="<?= labClean($order['gender']??'') ?>"
                                       value="<?= labClean($item['result_value']??'') ?>"
                                       placeholder="Enter result..."
                                       autofocus
                                       autocomplete="off">
                            </div>
                            <?php
                            $snr = trim($item['normal_range'] ?? '');
                            $sIsQual = ($snr === '' || !preg_match('/\d/', $snr)
                                || preg_match('/see report|negative|non-reactive|no growth/i', $snr));
                            ?>
                            <?php if (!$sIsQual): ?>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Status</label>
                                <div class="d-flex align-items-center" style="height:48px;">
                                    <span id="simple_badge_<?= $item['id'] ?>"
                                          class="badge-status status-<?= $item['result_status']??'pending' ?>"
                                          style="font-size:14px;padding:8px 16px;">
                                        <?= ucfirst($item['result_status']??'pending') ?>
                                    </span>
                                </div>
                                <input type="hidden"
                                       name="result_status[<?= $item['id'] ?>]"
                                       id="simple_status_<?= $item['id'] ?>"
                                       value="<?= $item['result_status']??'pending' ?>">
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="result_status[<?= $item['id'] ?>]" value="normal">
                            <?php endif; ?>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Notes</label>
                                <input type="text"
                                       name="result_notes[<?= $item['id'] ?>]"
                                       class="form-control form-control-lg"
                                       value="<?= labClean($item['result_notes']??'') ?>"
                                       placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

                <div class="modal-footer" style="background:#f8faf9;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success btn-lg px-4">
                        <i class="bi bi-check-lg me-2"></i>Save Results
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>


<!-- Payment Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
                <input type="hidden" name="add_payment" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Add Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount (₹)</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                               value="<?= max(0,$balance) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select">
                            <?php foreach (['cash'=>'Cash','upi'=>'UPI','card'=>'Card','bank_transfer'=>'Bank Transfer'] as $v=>$l): ?>
                            <option value="<?= $v ?>"><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference (Optional)</label>
                        <input type="text" name="transaction_ref" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i>Record</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Discount Modal -->
<?php $isDoctorRef = ($order['referral_type'] === 'doctor' && $order['doctor_id']); ?>
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" id="discountForm">
                <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
                <input type="hidden" name="update_discount" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Discount</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light py-2 mb-3" style="font-size:12px;border:1px solid #e2e8f0;">
                        Subtotal: <strong><?= labMoney($order['total_amount']) ?></strong>
                        <?php if ($isDoctorRef): ?>
                        <br>Referred by <strong><?= labClean($order['ref_doctor_name'] ?? 'tied-up doctor') ?></strong> — lab and doctor discount both apply.
                        <?php else: ?>
                        <br>Self / external referral — only lab discount applies.
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lab Discount (₹)</label>
                        <input type="number" name="discount" id="modalDiscountInput" class="form-control" step="0.01" min="0"
                               value="<?= $order['discount'] ?? 0 ?>">
                    </div>
                    <?php if ($isDoctorRef): ?>
                    <div class="mb-3">
                        <label class="form-label">Doctor's Discount (₹)</label>
                        <input type="number" name="doctor_discount" id="modalDoctorDiscountInput" class="form-control" step="0.01" min="0"
                               value="<?= $order['doctor_discount'] ?? 0 ?>">
                        <small class="text-muted">Reduces the doctor's commission on this order (if not already paid out).</small>
                    </div>
                    <?php endif; ?>
                    <div id="modalDiscountError" class="alert alert-danger py-2 px-3 mb-0" style="display:none;font-size:12px;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Total discount cannot be greater than the subtotal amount (<?= labMoney($order['total_amount']) ?>).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="modalDiscountSaveBtn" class="btn btn-success"><i class="bi bi-check me-1"></i>Save Discount</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php
$extraJs = <<<'JS'
<script>
// ── SHARED: parse range and return normal|abnormal|pending ────
function isQualitativeRange(range) {
    if (!range || range === '') return true;
    if (/see report|negative|non-reactive|no growth/i.test(range)) return true;
    if (!/\d/.test(range)) return true; // no digits = qualitative (Brown, Clear, Formed etc.)
    return false;
}

function calcStatus(val, range) {
    if (val === '' || val === null || val === undefined) return 'pending';
    if (isQualitativeRange(range)) return 'normal'; // qualitative — no status logic
    const num = parseFloat(val);
    if (isNaN(num)) return 'pending'; // non-numeric in a numeric field
    const dashM = range.match(/^([\d.]+)\s*-\s*([\d.]+)$/);
    if (dashM) return (num < parseFloat(dashM[1]) || num > parseFloat(dashM[2])) ? 'abnormal' : 'normal';
    const ltM = range.match(/^<\s*([\d.]+)$/);
    if (ltM) return num >= parseFloat(ltM[1]) ? 'abnormal' : 'normal';
    const gtM = range.match(/^>\s*([\d.]+)$/);
    if (gtM) return num <= parseFloat(gtM[1]) ? 'abnormal' : 'normal';
    return 'normal';
}

// ── SUB-PARAMETER: update badge for panel tests ───────────────
function updateBadge(itemId, paramId, val, range) {
    const badge = document.querySelector('.live-status-' + itemId + '_' + paramId);
    if (!badge) return;
    const status = calcStatus(val, range);
    badge.className = 'badge-status status-' + status + ' live-status-' + itemId + '_' + paramId;
    badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
}

// ── SIMPLE TEST: resolve compound gender ranges, e.g. "0-15 (M) / 0-20 (F) mm/hr"
function resolveRangeForGender(range, gender) {
    range = (range || '').trim();
    if (range === '') return range;
    const g = (gender || '').trim().charAt(0).toUpperCase(); // 'M' or 'F'
    if (g !== 'M' && g !== 'F') return range;
    const re = new RegExp('([<>]?[\\d.]+(?:\\s*-\\s*[\\d.]+)?)\\s*\\(\\s*' + g + '(?:ale)?\\s*\\)', 'i');
    const m = range.match(re);
    return m ? m[1].trim() : range;
}

// ── SIMPLE TEST: update badge + hidden status input ───────────
function updateSimpleBadge(itemId, val, range, gender) {
    const badge  = document.getElementById('simple_badge_'  + itemId);
    const hidden = document.getElementById('simple_status_' + itemId);
    if (!badge || !hidden) return;
    const resolvedRange = resolveRangeForGender(range, gender);
    const status = calcStatus(val, resolvedRange);
    badge.className   = 'badge-status status-' + status;
    badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    hidden.value      = status;
}

// ── HELPER: get input value by shortname within same modal ────
function getInputVal(itemId, shortName) {
    const inp = document.querySelector(
        '.sub-input[data-itemid="' + itemId + '"][data-shortname="' + shortName + '"]'
    );
    return inp ? parseFloat(inp.value) : NaN;
}

// ── HELPER: set a calculated field value ─────────────────────
function setCalcField(itemId, shortName, value) {
    const inp = document.querySelector(
        '.sub-input[data-itemid="' + itemId + '"][data-shortname="' + shortName + '"]'
    );
    if (!inp) return;
    if (!isNaN(value) && isFinite(value)) {
        inp.value = Math.round(value * 100) / 100;
        // Mark as auto-calculated
        inp.style.background = '#f0fdf4';
        inp.style.borderColor = '#16a34a';
        inp.title = 'Auto-calculated';
        // Update its status badge
        updateBadge(itemId, inp.dataset.paramid, inp.value, inp.dataset.range);
    }
}

// ── FORMULA RUNNER: runs all applicable formulas for a test ──
function runFormulas(itemId, testCode) {
    if (testCode === 'CBC') {
        // HB% = Haemoglobin (HGB) × 7
        const hgb = getInputVal(itemId, 'HGB');
        if (!isNaN(hgb)) setCalcField(itemId, 'HB%', hgb * 7);
    }

    if (testCode === 'KFT') {
        // BUN = Blood Urea (UREA) × 0.467
        const urea = getInputVal(itemId, 'UREA');
        if (!isNaN(urea)) setCalcField(itemId, 'BUN', urea * 0.467);
    }

    if (testCode === 'LFT') {
        // Indirect Bilirubin = Total Bilirubin − Direct Bilirubin
        const tbili = getInputVal(itemId, 'T.BILI');
        const dbili = getInputVal(itemId, 'D.BILI');
        if (!isNaN(tbili) && !isNaN(dbili)) setCalcField(itemId, 'I.BILI', tbili - dbili);

        // Globulin = Total Protein − Albumin
        const tprot = getInputVal(itemId, 'T.PROT');
        const alb   = getInputVal(itemId, 'ALB');
        if (!isNaN(tprot) && !isNaN(alb)) {
            const glob = tprot - alb;
            setCalcField(itemId, 'GLOB', glob);
            // A:G Ratio = Albumin ÷ Globulin
            if (glob > 0) setCalcField(itemId, 'A:G', alb / glob);
        }
    }

    if (testCode === 'PTINR') {
        // Prothrombin Ratio = PT Test ÷ Control Plasma
        const ptTest  = getInputVal(itemId, 'PT TEST');
        const control = getInputVal(itemId, 'CONTROL');
        if (!isNaN(ptTest) && !isNaN(control) && control > 0) {
            setCalcField(itemId, 'PT RATIO', ptTest / control);
        }
    }
}

// ── MARK CALCULATED FIELDS AS READ-ONLY WITH TOOLTIP ─────────
const calcFields = {
    'CBC':   ['HB%'],
    'KFT':   ['BUN'],
    'LFT':   ['I.BILI', 'GLOB', 'A:G'],
    'PTINR': ['PT RATIO']
};

document.addEventListener('DOMContentLoaded', function() {
    // Style auto-calc fields that already have values
    Object.entries(calcFields).forEach(([testCode, fields]) => {
        fields.forEach(shortName => {
            document.querySelectorAll(
                '.sub-input[data-testcode="' + testCode + '"][data-shortname="' + shortName + '"]'
            ).forEach(inp => {
                inp.style.background  = '#f0fdf4';
                inp.style.borderColor = '#16a34a';
                inp.title = 'Auto-calculated — do not edit manually';
                inp.setAttribute('readonly', 'readonly');
                inp.style.cursor = 'not-allowed';
                inp.style.opacity = '0.85';
            });
        });
    });

    // Run formulas on modal open to show current calculated values
    document.querySelectorAll('[id^="modal_"]').forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function() {
            const anyInput = modal.querySelector('.sub-input');
            if (!anyInput) return;
            const itemId   = anyInput.dataset.itemid;
            const testCode = anyInput.dataset.testcode;
            runFormulas(itemId, testCode);
        });
    });
});

// ── MAIN: fire on every sub-param input change ───────────────
document.querySelectorAll('.sub-input').forEach(function(input) {
    input.addEventListener('input', function() {
        const itemId   = this.dataset.itemid;
        const paramId  = this.dataset.paramid;
        const range    = this.dataset.range || '';
        const testCode = this.dataset.testcode;
        updateBadge(itemId, paramId, this.value, range);
        runFormulas(itemId, testCode);
    });
});

// ── MAIN: fire on every simple test input change ─────────────
document.querySelectorAll('.simple-result-input').forEach(function(input) {
    input.addEventListener('input', function() {
        updateSimpleBadge(this.dataset.itemid, this.value, this.dataset.range, this.dataset.gender);
    });
    // Run once on modal open so existing values show correct status
    const modalEl = input.closest('.modal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            updateSimpleBadge(input.dataset.itemid, input.value, input.dataset.range, input.dataset.gender);
        });
    }
});

// ── EDIT DISCOUNT MODAL: validate discount never exceeds subtotal ──
(function () {
    const subtotal    = __LAB_JS_SUBTOTAL__;
    const discEl      = document.getElementById('modalDiscountInput');
    const docDiscEl   = document.getElementById('modalDoctorDiscountInput'); // may not exist for non-doctor referrals
    const errorEl     = document.getElementById('modalDiscountError');
    const saveBtn     = document.getElementById('modalDiscountSaveBtn');
    const discountForm = document.getElementById('discountForm');

    if (!discEl || !discountForm) return;

    function isValid() {
        const disc    = parseFloat(discEl.value) || 0;
        const docDisc = docDiscEl ? (parseFloat(docDiscEl.value) || 0) : 0;
        // Small epsilon guards against floating point rounding
        return (disc + docDisc) <= subtotal + 0.0001;
    }

    function validateModal() {
        const valid = isValid();
        discEl.classList.toggle('is-invalid', !valid);
        if (docDiscEl) docDiscEl.classList.toggle('is-invalid', !valid);
        errorEl.style.display = valid ? 'none' : 'block';
        saveBtn.disabled = !valid;
        return valid;
    }

    discEl.addEventListener('input', validateModal);
    if (docDiscEl) docDiscEl.addEventListener('input', validateModal);

    discountForm.addEventListener('submit', function (e) {
        if (!validateModal()) {
            e.preventDefault();
        }
    });

    // Validate immediately whenever the modal is opened
    const discountModalEl = document.getElementById('discountModal');
    if (discountModalEl) {
        discountModalEl.addEventListener('shown.bs.modal', validateModal);
    }
})();

// ── REMOVE TEST: SweetAlert2 confirmation before submitting ──
(function () {
    // Current order figures, embedded server-side, used only to estimate
    // whether removing a test will leave the order overpaid — the real
    // calculation (with proportional discount scaling) always happens
    // authoritatively on the server; this is just a heads-up for the user.
    const orderTotalAmount = __LAB_JS_ORDER_TOTAL__;
    const orderDiscountSum = __LAB_JS_ORDER_DISCOUNT__;
    const orderTotalPaid   = __LAB_JS_ORDER_PAID__;

    function fmtMoney(n) {
        return '₹' + n.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    document.querySelectorAll('.remove-test-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const name  = form.dataset.testName || 'this test';
            const price = parseFloat(form.dataset.testPrice) || 0;

            const predictedTotal    = Math.max(0, orderTotalAmount - price);
            const predictedDiscount = Math.min(orderDiscountSum, predictedTotal);
            const predictedNet      = Math.max(0, predictedTotal - predictedDiscount);
            const predictedRefund   = orderTotalPaid - predictedNet;

            let msg = 'Remove "' + name + '" from this order? The order total, ' +
                      'discount, and any doctor commission will be recalculated ' +
                      'automatically. This cannot be undone.';
            if (predictedRefund > 0.0001) {
                msg += ' This order was already paid ' + fmtMoney(orderTotalPaid) +
                       ' — removing this test will leave a refund of ' +
                       fmtMoney(predictedRefund) + ' due to the patient.';
            }

            function doSubmit() {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) btn.disabled = true;
                HTMLFormElement.prototype.submit.call(form);
            }

            // If SweetAlert2 didn't load (blocked CDN, offline, ad-blocker, etc.)
            // fall back to a native confirm() instead of silently doing nothing.
            if (typeof Swal === 'undefined') {
                if (window.confirm(msg)) doSubmit();
                return;
            }

            Swal.fire({
                icon: predictedRefund > 0.0001 ? 'error' : 'warning',
                title: predictedRefund > 0.0001 ? 'Remove test? Refund will be due' : 'Remove this test?',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'Yes, remove it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) doSubmit();
            });
        });
    });
})();
</script>
JS;

// The block above is a NOWDOC (<<<'JS') so PHP never executes inside it —
// any <?= ?> written there would be dumped as literal, invalid JS and
// silently break the *entire* script (calcStatus, live status badges,
// formula runner, discount validation, remove-test confirm — all of it).
// Substitute the real server values in afterwards instead.
$extraJs = str_replace(
    ['__LAB_JS_SUBTOTAL__', '__LAB_JS_ORDER_TOTAL__', '__LAB_JS_ORDER_DISCOUNT__', '__LAB_JS_ORDER_PAID__'],
    [
        json_encode((float)$order['total_amount']),
        json_encode((float)$order['total_amount']),
        json_encode((float)$order['discount'] + (float)$order['doctor_discount']),
        json_encode((float)$totalPaid),
    ],
    $extraJs
);
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>