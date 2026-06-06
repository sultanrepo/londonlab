<?php
$pageTitle = 'Edit User';
require_once __DIR__ . '/../../includes/header.php';
if (!labIsAdmin()) { labSetFlash('error','Admins only.'); header('Location: '.LAB_APP_URL.'/index.php?lab='.$slug); exit; }

$id     = (int)($_GET['id'] ?? 0);
$u      = $labDb->fetch("SELECT * FROM users WHERE id=?",[$id]);
$roles  = ['admin','receptionist','technician','accountant'];
$errors = [];

if (!$u) { labSetFlash('error','User not found.'); header('Location: '.LAB_APP_URL.'/modules/users/index.php?lab='.$slug); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    labVerifyCsrf();
    $name    = trim($_POST['name']  ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $role    = $_POST['role']       ?? 'receptionist';
    $pwd     = $_POST['password']   ?? '';
    $confirm = $_POST['confirm']    ?? '';

    if (!$name) $errors[] = 'Name required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if ($pwd && strlen($pwd) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($pwd && $pwd !== $confirm) $errors[] = 'Passwords do not match.';
    if (empty($errors)) {
        $exists = $labDb->fetch("SELECT id FROM users WHERE email=? AND id!=?",[$email,$id]);
        if ($exists) $errors[] = 'Email already in use.';
    }
    if (empty($errors)) {
        if ($pwd) {
            $labDb->execute("UPDATE users SET name=?,email=?,phone=?,role=?,password=? WHERE id=?",
                [$name,$email,$phone,$role,password_hash($pwd,PASSWORD_DEFAULT),$id]);
        } else {
            $labDb->execute("UPDATE users SET name=?,email=?,phone=?,role=? WHERE id=?",[$name,$email,$phone,$role,$id]);
        }
        labSetFlash('success','User updated!');
        header('Location: '.LAB_APP_URL.'/modules/users/index.php?lab='.$slug); exit;
    }
    $u = array_merge($u,['name'=>$name,'email'=>$email,'phone'=>$phone,'role'=>$role]);
}
?>
<div class="page-header">
    <div><h2><i class="bi bi-pencil-square me-2 text-danger"></i>Edit User</h2><p><?= labClean($u['name']) ?></p></div>
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
                <div class="col-md-7"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required value="<?= labClean($u['name']) ?>"></div>
                <div class="col-md-5"><label class="form-label">Role *</label>
                    <select name="role" class="form-select" <?= $u['id']===$labUser['id']?'disabled':'' ?>>
                        <?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($u['id']===$labUser['id']): ?><input type="hidden" name="role" value="<?= labClean($u['role']) ?>"><small class="text-muted">You cannot change your own role.</small><?php endif; ?>
                </div>
                <div class="col-md-7"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required value="<?= labClean($u['email']) ?>"></div>
                <div class="col-md-5"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= labClean($u['phone']??'') ?>"></div>
                <div class="col-12"><div class="divider"></div><small class="text-muted">Leave password blank to keep current.</small></div>
                <div class="col-md-6"><label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" minlength="6" placeholder="Leave blank to keep"></div>
                <div class="col-md-6"><label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm" class="form-control" minlength="6"></div>
            </div>
            <div class="divider"></div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="<?= LAB_APP_URL ?>/modules/users/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
