<?php
$pageTitle = 'Users';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('users');
if (!labIsAdmin()) { labSetFlash('error','Admins only.'); header('Location: '.LAB_APP_URL.'/index.php?lab='.$slug); exit; }

if (isset($_GET['toggle']) && (int)$_GET['toggle'] !== $labUser['id']) {
    $uid  = (int)$_GET['toggle'];
    $curr = $labDb->fetch("SELECT is_active FROM users WHERE id=?",[$uid]);
    if ($curr) $labDb->execute("UPDATE users SET is_active=? WHERE id=?",[$curr['is_active']?0:1,$uid]);
    labSetFlash('success','User status updated.');
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
}
if (isset($_GET['delete']) && (int)$_GET['delete'] !== $labUser['id']) {
    $labDb->execute("DELETE FROM users WHERE id=?",[(int)$_GET['delete']]);
    labSetFlash('success','User deleted.');
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
}

$users = $labDb->fetchAll("SELECT * FROM users ORDER BY role,name");
$roleColors = ['admin'=>'danger','receptionist'=>'primary','technician'=>'info','accountant'=>'warning'];
?>
<div class="page-header">
    <div><h2><i class="bi bi-person-gear me-2 text-danger"></i>Users</h2><p>Manage system users</p></div>
    <a href="<?= LAB_APP_URL ?>/modules/users/create.php?lab=<?= $slug ?>" class="btn btn-primary">
        <i class="bi bi-person-plus-fill me-2"></i>Add User
    </a>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover dt-table mb-0">
                <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $i=>$u): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:34px;height:34px;border-radius:50%;background:var(--lc-primary-l);color:var(--lc-primary);font-weight:700;font-size:14px;display:grid;place-items:center;flex-shrink:0;">
                                    <?= strtoupper(substr($u['name'],0,1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= labClean($u['name']) ?></div>
                                    <?php if ($u['id']===$labUser['id']): ?><small class="text-success">● You</small><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= labClean($u['email']) ?></td>
                        <td><span class="badge bg-<?= $roleColors[$u['role']]??'secondary' ?>-subtle text-<?= $roleColors[$u['role']]??'secondary' ?> text-capitalize"><?= ucfirst($u['role']) ?></span></td>
                        <td><?= $u['last_login'] ? date('d M Y, H:i',strtotime($u['last_login'])) : '<span class="text-muted">Never</span>' ?></td>
                        <td><?= $u['is_active'] ? '<span class="badge-status status-completed">Active</span>' : '<span class="badge-status status-cancelled">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= LAB_APP_URL ?>/modules/users/edit.php?lab=<?= $slug ?>&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <?php if ($u['id']!==$labUser['id']): ?>
                                <a href="?lab=<?= $slug ?>&toggle=<?= $u['id'] ?>" class="btn btn-sm btn-outline-<?= $u['is_active']?'warning':'success' ?>" onclick="return confirm('Toggle status?')">
                                    <i class="bi bi-<?= $u['is_active']?'pause':'play' ?>-circle"></i>
                                </a>
                                <a href="?lab=<?= $slug ?>&delete=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete user?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>