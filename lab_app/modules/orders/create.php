<?php
$pageTitle = 'New Order';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('orders');
if (!labCanEdit()) { labSetFlash('error','Access denied.'); header('Location: '.LAB_APP_URL.'/modules/orders/index.php?lab='.$slug); exit; }

$prePatient = null;
if (!empty($_GET['patient_id'])) {
    $prePatient = $labDb->fetch("SELECT * FROM patients WHERE id=?", [(int)$_GET['patient_id']]);
}
$patients   = $labDb->fetchAll("SELECT id,patient_id,name,phone,referral_type,doctor_id FROM patients WHERE is_active=1 ORDER BY name");
$categories = $labDb->fetchAll("SELECT tc.*, GROUP_CONCAT(t.id,'|',t.name,'|',t.price,'|',t.code ORDER BY t.name SEPARATOR ';;') as tests FROM test_categories tc JOIN tests t ON t.category_id=tc.id WHERE t.is_active=1 GROUP BY tc.id ORDER BY tc.name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    labVerifyCsrf();
    $patient_id      = (int)($_POST['patient_id']      ?? 0);
    $test_ids        = $_POST['test_ids']               ?? [];
    $discount        = (float)($_POST['discount']       ?? 0);
    $doctor_discount = (float)($_POST['doctor_discount'] ?? 0);
    $notes           = trim($_POST['notes']             ?? '');
    $pay_method      = $_POST['pay_method']             ?? '';
    $pay_amount      = (float)($_POST['pay_amount']     ?? 0);

    if (!$patient_id)     $errors[] = 'Please select a patient.';
    if (empty($test_ids)) $errors[] = 'Select at least one test.';

    if (empty($errors)) {
        $labDb->beginTransaction();
        try {
            $orderNo      = labGenerateOrderNo($labDb);
            $placeholders = implode(',', array_fill(0, count($test_ids), '?'));
            $tests        = $labDb->fetchAll("SELECT id,price FROM tests WHERE id IN ($placeholders)", $test_ids);
            $total        = array_sum(array_column($tests, 'price'));
            $net          = max(0, $total - $discount - $doctor_discount);

            $labDb->execute(
                "INSERT INTO orders (order_no,patient_id,created_by,total_amount,discount,doctor_discount,net_amount,notes,status) VALUES (?,?,?,?,?,?,?,?,'pending')",
                [$orderNo,$patient_id,$labUser['id'],$total,$discount,$doctor_discount,$net,$notes]
            );
            $orderId = (int)$labDb->lastInsertId();

            foreach ($tests as $t) {
                $labDb->execute("INSERT INTO order_items (order_id,test_id,price) VALUES (?,?,?)", [$orderId,$t['id'],$t['price']]);
            }

            if ($pay_method && $pay_amount > 0) {
                $labDb->execute(
                    "INSERT INTO payments (order_id,amount,method,status,paid_at) VALUES (?,?,?,'completed',NOW())",
                    [$orderId,$pay_amount,$pay_method]
                );
            }

            // Auto commission
            $pat = $labDb->fetch("SELECT referral_type,doctor_id FROM patients WHERE id=?", [$patient_id]);
            if ($pat && $pat['referral_type']==='doctor' && $pat['doctor_id']) {
                $doc = $labDb->fetch("SELECT commission_type,commission_value FROM doctors WHERE id=? AND is_active=1", [$pat['doctor_id']]);
                if ($doc && $doc['commission_value'] > 0) {
                    // Commission calculated on ORIGINAL total (before any discount)
                    $commAmt = $doc['commission_type']==='percentage'
                        ? round($total * $doc['commission_value'] / 100, 2)
                        : $doc['commission_value'];
                    // Doctor's discount is deducted from their commission
                    $commAmt = max(0, $commAmt - $doctor_discount);
                    $labDb->execute(
                        "INSERT INTO doctor_commissions (doctor_id,order_id,order_amount,commission_type,commission_rate,commission_amount,status) VALUES (?,?,?,?,?,?,'pending')",
                        [$pat['doctor_id'],$orderId,$total,$doc['commission_type'],$doc['commission_value'],$commAmt]
                    );
                }
            }

            $labDb->commit();
            labSetFlash('success', "Order $orderNo created!");
            header('Location: '.LAB_APP_URL.'/modules/orders/view.php?lab='.$slug.'&id='.$orderId);
            exit;
        } catch (Exception $e) {
            $labDb->rollback();
            $errors[] = 'Failed: ' . $e->getMessage();
        }
    }
}
?>
<div class="page-header">
    <div><h2><i class="bi bi-plus-circle-fill me-2 text-warning"></i>New Order</h2></div>
    <a href="<?= LAB_APP_URL ?>/modules/orders/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach($errors as $e): ?><li><?= labClean($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="orderForm">
<input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
<div class="row g-4">

    <!-- LEFT: Patient + Tests -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person-fill"></i> Select Patient</div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Patient <span class="text-danger">*</span></label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">— Search Patient —</option>
                            <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                data-referral="<?= labClean($p['referral_type'] ?? 'self') ?>"
                                <?= ($prePatient && $prePatient['id']==$p['id']) || ($_POST['patient_id']??'')==$p['id'] ? 'selected' : '' ?>>
                                <?= labClean($p['name']) ?> (<?= labClean($p['patient_id']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <a href="<?= LAB_APP_URL ?>/modules/patients/create.php?lab=<?= $slug ?>" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus me-2"></i>New Patient
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-droplet-fill"></i> Select Tests</div>
            <div class="card-body">
                <div class="mb-4 position-relative">
                    <i class="bi bi-search position-absolute" style="left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;"></i>
                    <input type="text" id="testSearchInput" class="form-control" placeholder="Search tests by name or code..." style="padding-left:38px;" autocomplete="off">
                </div>
                <p id="noTestResults" class="text-muted text-center small py-3" style="display:none;">No tests match your search.</p>
                <?php foreach ($categories as $cat):
                    $catTests = [];
                    foreach (explode(';;', $cat['tests']) as $ts) {
                        [$tid,$tname,$tprice,$tcode] = array_pad(explode('|',$ts), 4, '');
                        $catTests[] = compact('tid','tname','tprice','tcode');
                    }
                ?>
                <div class="mb-4 test-category-block">
                    <h6 class="fw-bold mb-3 text-muted text-uppercase test-category-title" style="font-size:12px;letter-spacing:.5px;">
                        <?= labClean($cat['name']) ?>
                    </h6>
                    <div class="row g-2">
                        <?php foreach ($catTests as $t): ?>
                        <div class="col-md-6 test-item-col" data-search="<?= strtolower(labClean($t['tname'].' '.$t['tcode'])) ?>">
                            <label class="d-flex align-items-center gap-3 p-3 border rounded-2"
                                   data-price="<?= $t['tprice'] ?>"
                                   style="cursor:pointer;transition:all .15s;">
                                <input type="checkbox" name="test_ids[]" value="<?= $t['tid'] ?>"
                                       class="form-check-input test-checkbox mt-0"
                                       style="width:18px;height:18px;"
                                       <?= in_array($t['tid'], $_POST['test_ids']??[]) ? 'checked' : '' ?>>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold" style="font-size:13px;"><?= labClean($t['tname']) ?></div>
                                    <small class="text-muted"><?= labClean($t['tcode']) ?></small>
                                </div>
                                <span class="fw-bold text-primary" style="font-size:14px;white-space:nowrap;">
                                    <?= labMoney((float)$t['tprice']) ?>
                                </span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Summary + Payment -->
    <div class="col-lg-4">
        <div class="card sticky-top" style="top:84px;">
            <div class="card-header"><i class="bi bi-receipt"></i> Order Summary</div>
            <div class="card-body">

                <!-- Selected tests list -->
                <div id="selectedTests">
                    <p class="text-muted text-center small py-3">No tests selected</p>
                </div>

                <div class="divider"></div>

                <!-- Subtotal -->
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Subtotal</span>
                    <strong id="subtotalDisplay">₹0.00</strong>
                </div>

                <!-- Lab Discount -->
                <div class="mb-2">
                    <label class="form-label">
                        Lab Discount (₹)
                        <small class="text-muted fw-normal">— lab bears cost</small>
                    </label>
                    <input type="number" name="discount" id="discountInput"
                           class="form-control" min="0" step="0.01" value="0">
                </div>

                <!-- Doctor's Discount -->
                <div class="mb-3" id="doctorDiscountWrapper" style="display:none;">
                    <label class="form-label" style="color:#d97706;">
                        Doctor's Discount (₹)
                        <small class="text-muted fw-normal">— deducted from doctor's commission</small>
                    </label>
                    <input type="number" name="doctor_discount" id="doctorDiscountInput"
                           class="form-control" style="border-color:#f59e0b;" min="0" step="0.01" value="0">
                    <small style="color:#d97706;font-size:11px;">
                        <i class="bi bi-info-circle me-1"></i>
                        Commission = (Rate% × Total) − Doctor's Discount
                    </small>
                </div>

                <!-- Net Total -->
                <div class="d-flex justify-content-between mb-3 p-3 rounded-2" style="background:var(--lc-primary-l);">
                    <strong>Net Total</strong>
                    <strong id="netDisplay" class="text-success fs-5">₹0.00</strong>
                </div>

                <div class="divider"></div>
                <h6 class="fw-bold mb-3">Payment</h6>

                <div class="mb-3">
                    <label class="form-label">Method</label>
                    <select name="pay_method" class="form-select">
                        <option value="">— Pay later —</option>
                        <?php foreach (['cash'=>'Cash','card'=>'Card','upi'=>'UPI','bank_transfer'=>'Bank Transfer'] as $v=>$l): ?>
                        <option value="<?= $v ?>"><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount Paid</label>
                    <input type="number" name="pay_amount" id="payAmountInput"
                           class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="bi bi-check-circle-fill me-2"></i>Create Order
                </button>
            </div>
        </div>
    </div>

</div>
</form>

<?php
$extraJs = <<<'JS'
<script>
const checks       = document.querySelectorAll('.test-checkbox');
const subEl        = document.getElementById('subtotalDisplay');
const netEl        = document.getElementById('netDisplay');
const discEl       = document.getElementById('discountInput');
const docDiscEl    = document.getElementById('doctorDiscountInput');
const payEl        = document.getElementById('payAmountInput');
const selDiv       = document.getElementById('selectedTests');

function fmt(n) {
    return '₹' + n.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function update() {
    let total = 0, html = '';
    checks.forEach(c => {
        if (c.checked) {
            const lbl   = c.closest('label');
            const price = parseFloat(lbl.dataset.price);
            const name  = lbl.querySelector('.fw-semibold').innerText;
            total += price;
            html += `<div class="d-flex justify-content-between mb-2 small">
                        <span>${name}</span><strong>${fmt(price)}</strong>
                     </div>`;
            lbl.style.background   = '#f0fdf4';
            lbl.style.borderColor  = '#1a6b4a';
        } else {
            const lbl = c.closest('label');
            lbl.style.background  = '';
            lbl.style.borderColor = '';
        }
    });

    if (!html) html = '<p class="text-muted text-center small py-2">No tests selected</p>';
    selDiv.innerHTML = html;

    const disc    = parseFloat(discEl.value)    || 0;
    const docDisc = parseFloat(docDiscEl.value) || 0;
    const net     = Math.max(0, total - disc - docDisc);

    subEl.textContent = fmt(total);
    netEl.textContent = fmt(net);

    if (!payEl.dataset.modified) payEl.value = net.toFixed(2);
}

// ── EVENT LISTENERS ──
checks.forEach(c => c.addEventListener('change', update));

discEl.addEventListener('input', update);       // Lab Discount
docDiscEl.addEventListener('input', update);    // Doctor's Discount

payEl.addEventListener('input', () => { payEl.dataset.modified = '1'; });

// ── TEST SEARCH FILTER ──
const testSearchInput = document.getElementById('testSearchInput');
const testItemCols    = document.querySelectorAll('.test-item-col');
const catBlocks        = document.querySelectorAll('.test-category-block');
const noResultsEl      = document.getElementById('noTestResults');

function filterTests() {
    const q = testSearchInput.value.trim().toLowerCase();
    let anyVisible = false;

    catBlocks.forEach(block => {
        let catHasVisible = false;
        block.querySelectorAll('.test-item-col').forEach(col => {
            const match = !q || col.dataset.search.includes(q);
            col.style.display = match ? '' : 'none';
            if (match) { catHasVisible = true; anyVisible = true; }
        });
        block.style.display = catHasVisible ? '' : 'none';
    });

    noResultsEl.style.display = anyVisible ? 'none' : '';
}

function resetTestSearch() {
    testSearchInput.value = '';
    filterTests();
    testSearchInput.focus();
}

testSearchInput.addEventListener('input', filterTests);

// When a test is checked, clear the search and show all tests again,
// then return focus to the search box for the next lookup.
checks.forEach(c => c.addEventListener('change', () => {
    if (c.checked) resetTestSearch();
}));

// ── SHOW/HIDE DOCTOR DISCOUNT BASED ON PATIENT REFERRAL ──
const patientSelect       = document.querySelector('[name="patient_id"]');
const doctorDiscWrapper   = document.getElementById('doctorDiscountWrapper');

function toggleDoctorDiscount() {
    const selectedOption = patientSelect.options[patientSelect.selectedIndex];
    const referral       = selectedOption ? selectedOption.dataset.referral : '';
    if (referral === 'doctor') {
        doctorDiscWrapper.style.display = 'block';
    } else {
        doctorDiscWrapper.style.display = 'none';
        docDiscEl.value = 0;  // Reset doctor discount when hidden
        update();             // Recalculate net total
    }
}

patientSelect.addEventListener('change', toggleDoctorDiscount);

// Init on page load (handles pre-selected patient)
toggleDoctorDiscount();

// Init
update();
</script>
JS;
?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>