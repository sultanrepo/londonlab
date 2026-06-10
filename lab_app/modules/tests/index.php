<?php
$pageTitle = 'Test Catalog';
require_once __DIR__ . '/../../includes/header.php';

// Add test
if ($_SERVER['REQUEST_METHOD']==='POST' && labIsAdmin()) {
    labVerifyCsrf();
    $name  = trim($_POST['name']??''); $code = strtoupper(trim($_POST['code']??''));
    $cat   = (int)($_POST['category_id']??0); $price = (float)($_POST['price']??0);
    $range = trim($_POST['normal_range']??''); $unit  = trim($_POST['unit']??'');
    $tat   = (int)($_POST['turnaround_hours']??24);
    if ($name && $code && $price > 0) {
        $labDb->execute("INSERT INTO tests (category_id,code,name,price,normal_range,unit,turnaround_hours) VALUES (?,?,?,?,?,?,?)",
            [$cat?:null,$code,$name,$price,$range,$unit,$tat]);
        labSetFlash('success',"Test '$name' added.");
        header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
    }
}
// Delete test
if (isset($_GET['delete']) && labIsAdmin()) {
    $labDb->execute("UPDATE tests SET is_active=0 WHERE id=?",[(int)$_GET['delete']]);
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
}

$tests      = $labDb->fetchAll("SELECT t.*,tc.name as cat_name FROM tests t LEFT JOIN test_categories tc ON t.category_id=tc.id WHERE t.is_active=1 ORDER BY tc.name,t.name");
$categories = $labDb->fetchAll("SELECT * FROM test_categories WHERE is_active=1 ORDER BY name");
?>
<div class="page-header">
    <div><h2><i class="bi bi-droplet-half me-2 text-info"></i>Test Catalog</h2><p>Available tests and pricing</p></div>
    <?php if (labIsAdmin()): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTestModal">
        <i class="bi bi-plus-circle-fill me-2"></i>Add Test
    </button>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover dt-table mb-0">
                <thead><tr><th>Code</th><th>Test Name</th><th>Category</th><th>Price</th><th>Normal Range</th><th>Unit</th><th>TAT</th><?php if(labIsAdmin()): ?><th>Action</th><?php endif; ?></tr></thead>
                <tbody>
                    <?php foreach ($tests as $t): ?>
                    <tr>
                        <td><code><?= labClean($t['code']) ?></code></td>
                        <td class="fw-semibold"><?= labClean($t['name']) ?></td>
                        <td><span class="badge bg-secondary-subtle text-secondary"><?= labClean($t['cat_name']??'—') ?></span></td>
                        <td><strong class="text-primary"><?= labMoney($t['price']) ?></strong></td>
                        <td><small><?= labClean($t['normal_range']??'—') ?></small></td>
                        <td><small><?= labClean($t['unit']??'—') ?></small></td>
                        <td><small><?= $t['turnaround_hours'] ?>h</small></td>
                        <?php if (labIsAdmin()): ?>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= LAB_APP_URL ?>/modules/tests/edit.php?lab=<?= $slug ?>&id=<?= $t['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit test">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button onclick="confirmDelete('<?= LAB_APP_URL ?>/modules/tests/index.php?lab=<?= $slug ?>&delete=<?= $t['id'] ?>','Remove test ' + '<?= labClean(addslashes($t['name'])) ?>' + '?')"
                                        class="btn btn-sm btn-outline-danger" title="Delete test">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addTestModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
            <div class="modal-header"><h5 class="modal-title">Add Test</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Test Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" required style="text-transform:uppercase;"></div>
                    <div class="col-md-6"><label class="form-label">Category</label>
                        <select name="category_id" class="form-select"><option value="">Uncategorized</option>
                        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= labClean($c['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Price (₹) *</label><input type="number" name="price" class="form-control" required min="1" step="0.01"></div>
                    <div class="col-md-6"><label class="form-label">Normal Range</label><input type="text" name="normal_range" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Unit</label><input type="text" name="unit" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">TAT (hrs)</label><input type="number" name="turnaround_hours" class="form-control" value="24" min="1"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check me-2"></i>Add Test</button>
            </div>
        </form>
    </div></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>