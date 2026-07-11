<?php
// Sultan Ashraf
$pageTitle = 'Add Doctor';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('doctors');
if (!labCanEdit()) { labSetFlash('error','Access denied.'); header('Location: '.LAB_APP_URL.'/modules/doctors/index.php?lab='.$slug); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    labVerifyCsrf();
    $name  = trim($_POST['name']??''); $spec  = trim($_POST['specialty']??'');
    $clinic= trim($_POST['clinic_name']??''); $phone = trim($_POST['phone']??'');
    $email = trim($_POST['email']??''); $addr  = trim($_POST['address']??'');
    $ctype = $_POST['commission_type']??'percentage';
    $cval  = (float)($_POST['commission_value']??0);
    if (!$name)    $errors[] = 'Doctor name is required.';
    if ($cval < 0) $errors[] = 'Commission cannot be negative.';
    if ($ctype==='percentage' && $cval > 100) $errors[] = 'Percentage cannot exceed 100.';
    if (empty($errors)) {
        $labDb->execute("INSERT INTO doctors (name,specialty,clinic_name,phone,email,address,commission_type,commission_value) VALUES (?,?,?,?,?,?,?,?)",
            [$name,$spec,$clinic,$phone,$email,$addr,$ctype,$cval]);
        labSetFlash('success',"Dr. $name added!");
        header('Location: '.LAB_APP_URL.'/modules/doctors/index.php?lab='.$slug); exit;
    }
}
?>
<div class="page-header">
    <div><h2><i class="bi bi-person-plus-fill me-2" style="color:#7c3aed;"></i>Add Doctor</h2></div>
    <a href="<?= LAB_APP_URL ?>/modules/doctors/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>
<?php if ($errors): ?><div class="alert alert-danger mb-4"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= labClean($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row justify-content-center"><div class="col-lg-8">
<div class="card">
    <div class="card-header"><i class="bi bi-person-vcard"></i> Doctor Details</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Dr. Name" value="<?= labClean($_POST['name']??'') ?>"></div>
                <div class="col-md-5"><label class="form-label">Specialty</label>
                    <input type="text" name="specialty" class="form-control" placeholder="e.g. General Physician" value="<?= labClean($_POST['specialty']??'') ?>"></div>
                <div class="col-md-7"><label class="form-label">Clinic / Hospital</label>
                    <input type="text" name="clinic_name" class="form-control" value="<?= labClean($_POST['clinic_name']??'') ?>"></div>
                <div class="col-md-5"><label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= labClean($_POST['phone']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= labClean($_POST['email']??'') ?>"></div>
                <div class="col-12"><label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= labClean($_POST['address']??'') ?></textarea></div>
                <div class="col-12"><div class="divider"></div><h6 class="fw-bold">Commission Settings</h6></div>
                <div class="col-md-5"><label class="form-label">Commission Type</label>
                    <select name="commission_type" id="ctype" class="form-select">
                        <option value="percentage">Percentage (%) of order</option>
                        <option value="fixed">Fixed Amount (₹) per order</option>
                    </select></div>
                <div class="col-md-4"><label class="form-label">Value</label>
                    <div class="input-group"><span class="input-group-text" id="cpfx">%</span>
                    <input type="number" name="commission_value" id="cval" class="form-control" min="0" step="0.01" value="<?= labClean($_POST['commission_value']??'0') ?>"></div></div>
                <div class="col-12">
                    <div class="p-3 rounded-2" style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:13px;">
                        <strong>On ₹1,000 order → </strong><strong id="cprev"></strong>
                    </div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= LAB_APP_URL ?>/modules/doctors/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Add Doctor</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php
$extraJs = <<<'JS'
<script>
const ct=document.getElementById('ctype'),cv=document.getElementById('cval'),pfx=document.getElementById('cpfx'),prv=document.getElementById('cprev');
function upd(){const t=ct.value,v=parseFloat(cv.value)||0;pfx.textContent=t==='percentage'?'%':'₹';prv.textContent=t==='percentage'?'₹'+(1000*v/100).toFixed(2)+' ('+v+'%)':'₹'+v.toFixed(2)+' flat';}
ct.addEventListener('change',upd); cv.addEventListener('input',upd); upd();
</script>
JS;
?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>