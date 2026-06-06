<?php
$pageTitle = 'Edit Lab';
require_once __DIR__ . '/../../includes/header.php';

$db     = MasterDB::getInstance();
$id     = (int)($_GET['id'] ?? 0);
$lab    = $db->fetch("SELECT * FROM labs WHERE id=?", [$id]);
$plans  = $db->fetchAll("SELECT * FROM plans WHERE is_active=1 ORDER BY price");
$errors = [];

if (!$lab) { saSetFlash('error','Lab not found.'); header('Location: '.SUPERADMIN_URL.'/modules/labs/index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    saVerifyCsrf();
    $name        = trim($_POST['name']        ?? '');
    $owner_name  = trim($_POST['owner_name']  ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');
    $owner_phone = trim($_POST['owner_phone'] ?? '');
    $address     = trim($_POST['address']     ?? '');
    $city        = trim($_POST['city']        ?? '');
    $state       = trim($_POST['state']       ?? '');
    $pincode     = trim($_POST['pincode']     ?? '');
    $gstin       = trim($_POST['gstin']       ?? '');
    $plan_id     = (int)($_POST['plan_id']    ?? 0);
    $status      = $_POST['status']           ?? $lab['status'];
    $trial_ends  = $_POST['trial_ends_at']    ?? $lab['trial_ends_at'];
    $sub_ends    = $_POST['subscription_ends_at'] ?? $lab['subscription_ends_at'];
    $notes       = trim($_POST['notes']       ?? '');

    if (!$name)       $errors[] = 'Lab name is required.';
    if (!$owner_name) $errors[] = 'Owner name is required.';
    if (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

    if (empty($errors)) {
        $db->execute("
            UPDATE labs SET name=?,owner_name=?,owner_email=?,owner_phone=?,address=?,
            city=?,state=?,pincode=?,gstin=?,plan_id=?,status=?,trial_ends_at=?,
            subscription_ends_at=?,notes=? WHERE id=?
        ", [$name,$owner_name,$owner_email,$owner_phone,$address,$city,$state,$pincode,
            $gstin,$plan_id?:null,$status,$trial_ends?:null,$sub_ends?:null,$notes,$id]);
        saSetFlash('success','Lab updated successfully!');
        header('Location: '.SUPERADMIN_URL.'/modules/labs/view.php?id='.$id); exit;
    }
    $lab = array_merge($lab, $_POST);
}
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-pencil-square me-2" style="color:#7c3aed;"></i>Edit Lab</h2>
        <p><?= saClean($lab['name']) ?></p>
    </div>
    <a href="<?= SUPERADMIN_URL ?>/modules/labs/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach($errors as $e): ?><li><?= saClean($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-9">
<div class="card">
    <div class="card-header"><i class="bi bi-building"></i> Lab Information</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= saCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Lab Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= saClean($lab['name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Name <span class="text-danger">*</span></label>
                    <input type="text" name="owner_name" class="form-control" required value="<?= saClean($lab['owner_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Phone</label>
                    <input type="text" name="owner_phone" class="form-control" value="<?= saClean($lab['owner_phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Email <span class="text-danger">*</span></label>
                    <input type="email" name="owner_email" class="form-control" required value="<?= saClean($lab['owner_email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">GSTIN</label>
                    <input type="text" name="gstin" class="form-control" value="<?= saClean($lab['gstin'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= saClean($lab['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= saClean($lab['city'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= saClean($lab['state'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?= saClean($lab['pincode'] ?? '') ?>">
                </div>

                <div class="col-12"><div class="divider"></div></div>
                <div class="col-12"><h6 class="fw-bold">Subscription Settings</h6></div>

                <div class="col-md-3">
                    <label class="form-label">Plan</label>
                    <select name="plan_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>" <?= $lab['plan_id']==$plan['id']?'selected':'' ?>>
                            <?= saClean($plan['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['trial','active','suspended','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $lab['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Trial Ends</label>
                    <input type="date" name="trial_ends_at" class="form-control"
                           value="<?= saClean($lab['trial_ends_at'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subscription Ends</label>
                    <input type="date" name="subscription_ends_at" class="form-control"
                           value="<?= saClean($lab['subscription_ends_at'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Internal Notes</label>
                    <textarea name="notes" class="form-control" rows="2"
                              placeholder="Internal notes about this lab..."><?= saClean($lab['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= SUPERADMIN_URL ?>/modules/labs/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
