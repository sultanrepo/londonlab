<?php
$pageTitle = 'New Patient';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('patients');
if (!labCanEdit()) { labSetFlash('error','Access denied.'); header('Location: '.LAB_APP_URL.'/modules/patients/index.php?lab='.$slug); exit; }

$doctors = $labDb->fetchAll("SELECT id,name,specialty,clinic_name FROM doctors WHERE is_active=1 ORDER BY name");
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    labVerifyCsrf();
    $name          = trim($_POST['name']          ?? '');
    $age           = (int)($_POST['age']          ?? 0);
    $gender        = $_POST['gender']             ?? 'Male';
    $blood_group   = $_POST['blood_group']        ?? 'Unknown';
    $phone         = trim($_POST['phone']         ?? '');
    $email         = trim($_POST['email']         ?? '');
    $address       = trim($_POST['address']       ?? '');
    $referral_type = $_POST['referral_type']      ?? 'self';
    $doctor_id     = ($referral_type === 'doctor') ? (int)($_POST['doctor_id'] ?? 0) : null;
    $referred_by   = '';
    if ($referral_type === 'doctor' && $doctor_id) {
        $doc = $labDb->fetch("SELECT name FROM doctors WHERE id=?", [$doctor_id]);
        $referred_by = $doc['name'] ?? '';
    }
    if (!$name)  $errors[] = 'Patient name is required.';
    if (!$phone) $errors[] = 'Phone is required.';
    if ($referral_type === 'doctor' && !$doctor_id) $errors[] = 'Please select a doctor.';

    if (empty($errors)) {
        $pid = labGeneratePatientId($labDb);
        $labDb->execute("
            INSERT INTO patients (patient_id,name,age,gender,blood_group,phone,email,address,referral_type,doctor_id,referred_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ", [$pid,$name,$age?:null,$gender,$blood_group,$phone,$email,$address,$referral_type,$doctor_id,$referred_by]);
        $newId = $labDb->lastInsertId();
        labSetFlash('success',"Patient $pid registered!");
        header('Location: '.LAB_APP_URL.'/modules/patients/view.php?lab='.$slug.'&id='.$newId); exit;
    }
}
$selRef = $_POST['referral_type'] ?? 'self';
$selDoc = (int)($_POST['doctor_id'] ?? 0);
?>
<div class="page-header">
    <div><h2><i class="bi bi-person-plus-fill me-2 text-primary"></i>New Patient</h2></div>
    <a href="<?= LAB_APP_URL ?>/modules/patients/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>
<?php if ($errors): ?>
<div class="alert alert-danger mb-4"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= labClean($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row justify-content-center"><div class="col-lg-9">
<div class="card">
    <div class="card-header"><i class="bi bi-person-badge"></i> Patient Information</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= labClean($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Age <span class="text-danger">*</span></label>
                    <input type="number" name="age" class="form-control" required min="0" max="150" placeholder="e.g. 35" value="<?= (int)($_POST['age'] ?? 0) ?: '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($_POST['gender']??'Male')===$g?'selected':'' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'] as $bg): ?>
                        <option value="<?= $bg ?>" <?= ($_POST['blood_group']??'Unknown')===$bg?'selected':'' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" required value="<?= labClean($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= labClean($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= labClean($_POST['address'] ?? '') ?></textarea>
                </div>
                <!-- Referral -->
                <div class="col-12"><div class="divider"></div><h6 class="fw-bold mb-3"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Referral</h6></div>
                <div class="col-12">
                    <div class="d-flex gap-3">
                        <label id="optDoctor" style="flex:1;border:2px solid <?= $selRef==='doctor'?'#1a6b4a':'#e2e8f0' ?>;border-radius:10px;padding:14px 18px;cursor:pointer;background:<?= $selRef==='doctor'?'#f0fdf4':'#fff' ?>;transition:all .2s;" onclick="setRef('doctor')">
                            <input type="radio" name="referral_type" value="doctor" style="display:none;" <?= $selRef==='doctor'?'checked':'' ?>>
                            <div class="d-flex align-items-center gap-3">
                                <div id="docIcon" style="width:44px;height:44px;border-radius:10px;background:<?= $selRef==='doctor'?'#dcfce7':'#f1f5f9' ?>;display:grid;place-items:center;font-size:22px;">🩺</div>
                                <div><div class="fw-bold" style="font-size:14px;">Doctor Referral</div><div class="text-muted" style="font-size:12px;">Commission applicable</div></div>
                            </div>
                        </label>
                        <label id="optSelf" style="flex:1;border:2px solid <?= $selRef==='self'?'#1a6b4a':'#e2e8f0' ?>;border-radius:10px;padding:14px 18px;cursor:pointer;background:<?= $selRef==='self'?'#f0fdf4':'#fff' ?>;transition:all .2s;" onclick="setRef('self')">
                            <input type="radio" name="referral_type" value="self" style="display:none;" <?= $selRef==='self'?'checked':'' ?>>
                            <div class="d-flex align-items-center gap-3">
                                <div id="selfIcon" style="width:44px;height:44px;border-radius:10px;background:<?= $selRef==='self'?'#dcfce7':'#f1f5f9' ?>;display:grid;place-items:center;font-size:22px;">🚶</div>
                                <div><div class="fw-bold" style="font-size:14px;">Self / Walk-in</div><div class="text-muted" style="font-size:12px;">No commission</div></div>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="col-12" id="doctorSection" style="display:<?= $selRef==='doctor'?'block':'none' ?>;">
                    <label class="form-label">Select Doctor <span class="text-danger">*</span></label>
                    <select name="doctor_id" id="doctorSelect" class="form-select form-select-lg">
                        <option value="">— Choose a doctor —</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>" <?= $selDoc===$doc['id']?'selected':'' ?>>
                            <?= labClean($doc['name']) ?><?= $doc['specialty']?' — '.labClean($doc['specialty']):'' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted"><a href="<?= LAB_APP_URL ?>/modules/doctors/create.php?lab=<?= $slug ?>" target="_blank"><i class="bi bi-plus-circle me-1"></i>Add new doctor</a></small>
                </div>
                <div class="col-12" id="selfSection" style="display:<?= $selRef==='self'?'block':'none' ?>;">
                    <div class="p-3 rounded-2 d-flex align-items-center gap-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                        <i class="bi bi-info-circle-fill text-success fs-5"></i>
                        <span style="font-size:13px;">Walk-in — <strong>no commission</strong> deducted.</span>
                    </div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= LAB_APP_URL ?>/modules/patients/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Register Patient</button>
            </div>
        </form>
    </div>
</div>
</div></div>

<?php
$extraJs = <<<'JS'
<script>
function setRef(type) {
    const isDoc = type==='doctor';
    document.getElementById('doctorSection').style.display = isDoc?'block':'none';
    document.getElementById('selfSection').style.display   = isDoc?'none':'block';
    document.getElementById('doctorSelect').required = isDoc;
    const od=document.getElementById('optDoctor'),os=document.getElementById('optSelf');
    const di=document.getElementById('docIcon'),si=document.getElementById('selfIcon');
    od.style.borderColor=isDoc?'#1a6b4a':'#e2e8f0'; od.style.background=isDoc?'#f0fdf4':'#fff'; di.style.background=isDoc?'#dcfce7':'#f1f5f9';
    os.style.borderColor=!isDoc?'#1a6b4a':'#e2e8f0'; os.style.background=!isDoc?'#f0fdf4':'#fff'; si.style.background=!isDoc?'#dcfce7':'#f1f5f9';
}
document.addEventListener('DOMContentLoaded',()=>{ const c=document.querySelector('[name="referral_type"]:checked'); if(c) setRef(c.value); });
</script>
JS;
?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>