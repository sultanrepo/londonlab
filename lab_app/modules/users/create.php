<?php
$pageTitle = 'Add User';
require_once __DIR__ . '/../../includes/header.php';
if (!labIsAdmin()) { labSetFlash('error','Admins only.'); header('Location: '.LAB_APP_URL.'/index.php?lab='.$slug); exit; }

$roles  = ['admin','receptionist','technician','accountant'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    labVerifyCsrf();
    $name    = trim($_POST['name']     ?? '');
    $email   = trim($_POST['email']    ?? '');
    $phone   = trim($_POST['phone']    ?? '');
    $role    = $_POST['role']          ?? 'receptionist';
    $pwd     = $_POST['password']      ?? '';
    $confirm = $_POST['confirm']       ?? '';

    if (!$name)  $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (strlen($pwd) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($pwd !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $exists = $labDb->fetch("SELECT id FROM users WHERE email=?",[$email]);
        if ($exists) $errors[] = 'Email already in use.';
    }
    if (empty($errors)) {
        $labDb->execute("INSERT INTO users (name,email,password,role,phone) VALUES (?,?,?,?,?)",
            [$name,$email,password_hash($pwd,PASSWORD_DEFAULT),$role,$phone]);
        labSetFlash('success',"User '$name' created!");
        header('Location: '.LAB_APP_URL.'/modules/users/index.php?lab='.$slug); exit;
    }
}
?>
<div class="page-header">
    <div><h2><i class="bi bi-person-plus-fill me-2 text-danger"></i>Add User</h2></div>
    <a href="<?= LAB_APP_URL ?>/modules/users/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
</div>
<?php if ($errors): ?><div class="alert alert-danger mb-4"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= labClean($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row justify-content-center"><div class="col-lg-7">
<div class="card">
    <div class="card-header"><i class="bi bi-person-badge"></i> User Details</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required value="<?= labClean($_POST['name']??'') ?>"></div>
                <div class="col-md-5"><label class="form-label">Role *</label>
                    <select name="role" class="form-select">
                        <?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= ($_POST['role']??'receptionist')===$r?'selected':'' ?>><?= ucfirst($r) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-7"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required value="<?= labClean($_POST['email']??'') ?>"></div>
                <div class="col-md-5"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= labClean($_POST['phone']??'') ?>"></div>
                <div class="col-12"><div class="divider"></div></div>
                <div class="col-md-6"><label class="form-label">Password *</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwd" class="form-control" required minlength="6">
                        <button type="button" class="btn btn-outline-secondary" onclick="const f=document.getElementById('pwd');f.type=f.type==='password'?'text':'password';"><i class="bi bi-eye"></i></button>
                    </div></div>
                <div class="col-md-6"><label class="form-label">Confirm Password *</label>
                    <input type="password" name="confirm" class="form-control" required minlength="6"></div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= LAB_APP_URL ?>/modules/users/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Create User</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
