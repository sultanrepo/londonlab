<?php
$pageTitle = 'Edit Test';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('tests');
if (!labIsAdmin()) {
    labSetFlash('error', 'Admins only.');
    header('Location: ' . LAB_APP_URL . '/modules/tests/index.php?lab=' . $slug); exit;
}

$id   = (int)($_GET['id'] ?? 0);
$test = $labDb->fetch("SELECT t.*, tc.name as cat_name FROM tests t LEFT JOIN test_categories tc ON t.category_id=tc.id WHERE t.id=? AND t.is_active=1", [$id]);
if (!$test) {
    labSetFlash('error', 'Test not found.');
    header('Location: ' . LAB_APP_URL . '/modules/tests/index.php?lab=' . $slug); exit;
}

$categories = $labDb->fetchAll("SELECT * FROM test_categories WHERE is_active=1 ORDER BY name");
$errors     = [];

// ── SAVE TEST DETAILS ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_test') {
    labVerifyCsrf();
    $name    = trim($_POST['name']             ?? '');
    $code    = strtoupper(trim($_POST['code']  ?? ''));
    $cat     = (int)($_POST['category_id']     ?? 0);
    $price   = (float)($_POST['price']         ?? 0);
    $range   = trim($_POST['normal_range']     ?? '');
    $unit    = trim($_POST['unit']             ?? '');
    $tat     = (int)($_POST['turnaround_hours']?? 24);
    $desc    = trim($_POST['description']      ?? '');

    if (!$name)      $errors[] = 'Test name is required.';
    if (!$code)      $errors[] = 'Test code is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';

    // Code uniqueness check (exclude self)
    if ($code) {
        $dup = $labDb->fetch("SELECT id FROM tests WHERE code=? AND id != ? AND is_active=1", [$code, $id]);
        if ($dup) $errors[] = "Code '$code' is already used by another test.";
    }

    if (empty($errors)) {
        $labDb->execute(
            "UPDATE tests SET name=?, code=?, category_id=?, price=?, normal_range=?, unit=?, turnaround_hours=?, description=? WHERE id=?",
            [$name, $code, $cat ?: null, $price, $range, $unit, $tat, $desc, $id]
        );
        labSetFlash('success', "Test '$name' updated successfully.");
        header('Location: ' . $_SERVER['PHP_SELF'] . '?lab=' . $slug . '&id=' . $id); exit;
    }
    // Re-merge on error
    $test = array_merge($test, $_POST);
}

// ── ADD SUB-PARAMETER ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_sub') {
    labVerifyCsrf();
    $pname   = trim($_POST['parameter_name']      ?? '');
    $short   = strtoupper(trim($_POST['short_name'] ?? ''));
    $rm      = trim($_POST['normal_range_male']   ?? '');
    $rf      = trim($_POST['normal_range_female'] ?? '');
    $unit    = trim($_POST['sub_unit']            ?? '');
    $sort    = (int)($_POST['sort_order']         ?? 0);

    if ($pname) {
        $labDb->execute(
            "INSERT INTO test_sub_parameters (test_id, parameter_name, short_name, normal_range_male, normal_range_female, unit, sort_order) VALUES (?,?,?,?,?,?,?)",
            [$id, $pname, $short, $rm, $rf, $unit, $sort]
        );
        labSetFlash('success', "Sub-parameter '$pname' added.");
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?lab=' . $slug . '&id=' . $id . '#sub-params'); exit;
}

// ── SAVE SUB-PARAMETER (inline edit) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_sub') {
    labVerifyCsrf();
    $sid   = (int)($_POST['sub_id']              ?? 0);
    $pname = trim($_POST['parameter_name']       ?? '');
    $short = strtoupper(trim($_POST['short_name']?? ''));
    $rm    = trim($_POST['normal_range_male']    ?? '');
    $rf    = trim($_POST['normal_range_female']  ?? '');
    $unit  = trim($_POST['sub_unit']             ?? '');
    $sort  = (int)($_POST['sort_order']          ?? 0);

    if ($sid && $pname) {
        $labDb->execute(
            "UPDATE test_sub_parameters SET parameter_name=?, short_name=?, normal_range_male=?, normal_range_female=?, unit=?, sort_order=? WHERE id=? AND test_id=?",
            [$pname, $short, $rm, $rf, $unit, $sort, $sid, $id]
        );
        labSetFlash('success', "Sub-parameter updated.");
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?lab=' . $slug . '&id=' . $id . '#sub-params'); exit;
}

// ── DELETE SUB-PARAMETER ──────────────────────────────────────
if (isset($_GET['del_sub'])) {
    $sid = (int)$_GET['del_sub'];
    $labDb->execute("UPDATE test_sub_parameters SET is_active=0 WHERE id=? AND test_id=?", [$sid, $id]);
    labSetFlash('success', 'Sub-parameter removed.');
    header('Location: ' . $_SERVER['PHP_SELF'] . '?lab=' . $slug . '&id=' . $id . '#sub-params'); exit;
}

// Load sub-parameters
$subParams = $labDb->fetchAll(
    "SELECT * FROM test_sub_parameters WHERE test_id=? AND is_active=1 ORDER BY sort_order, id",
    [$id]
);
$isPanel = !empty($subParams);

// Which sub-param is being inline-edited
$editSubId = (int)($_GET['edit_sub'] ?? 0);
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Test</h2>
        <p><code><?= labClean($test['code']) ?></code> &nbsp;·&nbsp; <?= labClean($test['cat_name'] ?? 'Uncategorized') ?></p>
    </div>
    <a href="<?= LAB_APP_URL ?>/modules/tests/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Tests
    </a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= labClean($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- ── TEST DETAILS FORM ── -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-droplet-half me-2 text-info"></i>Test Details</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
            <input type="hidden" name="action"     value="save_test">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Test Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= labClean($test['name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Test Code *</label>
                    <input type="text" name="code" class="form-control" required
                           style="text-transform:uppercase;"
                           value="<?= labClean($test['code']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">Uncategorized</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ((int)$test['category_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                            <?= labClean($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" name="price" class="form-control" required min="1" step="0.01"
                           value="<?= labClean($test['price']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">TAT (hours)</label>
                    <input type="number" name="turnaround_hours" class="form-control" min="1"
                           value="<?= labClean($test['turnaround_hours'] ?? 24) ?>">
                </div>
                <!-- Only show Normal Range / Unit for simple (non-panel) tests -->
                <?php if (!$isPanel): ?>
                <div class="col-md-6">
                    <label class="form-label">Normal Range <small class="text-muted">(for simple tests)</small></label>
                    <input type="text" name="normal_range" class="form-control" placeholder="e.g. 13.5–17.5"
                           value="<?= labClean($test['normal_range'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" placeholder="e.g. g/dL"
                           value="<?= labClean($test['unit'] ?? '') ?>">
                </div>
                <?php else: ?>
                <input type="hidden" name="normal_range" value="Multiple">
                <input type="hidden" name="unit"         value="Multiple">
                <div class="col-12">
                    <div class="alert alert-info mb-0 py-2">
                        <i class="bi bi-info-circle me-2"></i>
                        This is a <strong>panel test</strong> — normal ranges and units are defined per sub-parameter below.
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <label class="form-label">Description <small class="text-muted">(optional)</small></label>
                    <textarea name="description" class="form-control" rows="2"><?= labClean($test['description'] ?? '') ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Save Test Details
                    </button>
                    <a href="<?= LAB_APP_URL ?>/modules/tests/index.php?lab=<?= $slug ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── SUB-PARAMETERS SECTION ── -->
<div class="card" id="sub-params">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2 text-info"></i>Sub-Parameters
            <span class="badge bg-secondary-subtle text-secondary ms-2"><?= count($subParams) ?></span>
        </span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSubModal">
            <i class="bi bi-plus-circle me-1"></i>Add Sub-Parameter
        </button>
    </div>

    <?php if (empty($subParams)): ?>
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-list-ul" style="font-size:2rem;opacity:.3;"></i>
        <p class="mt-2 mb-0">No sub-parameters yet.</p>
        <p class="small">Add sub-parameters to make this a panel test (like CBC, LFT).</p>
    </div>

    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:5%">#</th>
                    <th style="width:25%">Parameter Name</th>
                    <th style="width:10%">Short</th>
                    <th style="width:18%">Normal Range (Male)</th>
                    <th style="width:18%">Normal Range (Female)</th>
                    <th style="width:10%">Unit</th>
                    <th style="width:7%">Sort</th>
                    <th style="width:7%">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($subParams as $i => $sp): ?>

                <?php if ($editSubId === (int)$sp['id']): ?>
                <!-- ── INLINE EDIT ROW ── -->
                <tr class="table-warning">
                    <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
                    <input type="hidden" name="action"     value="save_sub">
                    <input type="hidden" name="sub_id"     value="<?= $sp['id'] ?>">
                    <td><span class="text-muted"><?= $i+1 ?></span></td>
                    <td><input type="text" name="parameter_name" class="form-control form-control-sm" required
                               value="<?= labClean($sp['parameter_name']) ?>"></td>
                    <td><input type="text" name="short_name" class="form-control form-control-sm"
                               style="text-transform:uppercase;width:80px;"
                               value="<?= labClean($sp['short_name'] ?? '') ?>"></td>
                    <td><input type="text" name="normal_range_male" class="form-control form-control-sm"
                               value="<?= labClean($sp['normal_range_male'] ?? '') ?>"></td>
                    <td><input type="text" name="normal_range_female" class="form-control form-control-sm"
                               value="<?= labClean($sp['normal_range_female'] ?? '') ?>"></td>
                    <td><input type="text" name="sub_unit" class="form-control form-control-sm"
                               value="<?= labClean($sp['unit'] ?? '') ?>"></td>
                    <td><input type="number" name="sort_order" class="form-control form-control-sm"
                               style="width:60px;" value="<?= (int)$sp['sort_order'] ?>"></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-success" title="Save">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>?lab=<?= $slug ?>&id=<?= $id ?>#sub-params"
                               class="btn btn-sm btn-outline-secondary" title="Cancel">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </td>
                    </form>
                </tr>

                <?php else: ?>
                <!-- ── DISPLAY ROW ── -->
                <tr>
                    <td><span class="text-muted small"><?= $i+1 ?></span></td>
                    <td class="fw-semibold"><?= labClean($sp['parameter_name']) ?></td>
                    <td><code class="text-purple" style="background:#ede9fe;padding:2px 6px;border-radius:4px;"><?= labClean($sp['short_name'] ?? '—') ?></code></td>
                    <td><small class="text-muted"><?= labClean($sp['normal_range_male']   ?? '—') ?></small></td>
                    <td><small class="text-muted"><?= labClean($sp['normal_range_female'] ?? '—') ?></small></td>
                    <td><small><?= labClean($sp['unit'] ?? '—') ?></small></td>
                    <td><small class="text-muted"><?= (int)$sp['sort_order'] ?></small></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?= $_SERVER['PHP_SELF'] ?>?lab=<?= $slug ?>&id=<?= $id ?>&edit_sub=<?= $sp['id'] ?>#sub-params"
                               class="btn btn-sm btn-outline-primary" title="Edit this sub-parameter">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button onclick="confirmDelete('<?= $_SERVER['PHP_SELF'] ?>?lab=<?= $slug ?>&id=<?= $id ?>&del_sub=<?= $sp['id'] ?>','Remove sub-parameter <?= labClean(addslashes($sp['parameter_name'])) ?>?')"
                                    class="btn btn-sm btn-outline-danger" title="Remove">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── ADD SUB-PARAMETER MODAL ── -->
<div class="modal fade" id="addSubModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= labCsrfToken() ?>">
            <input type="hidden" name="action"     value="add_sub">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Sub-Parameter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Parameter Name *</label>
                        <input type="text" name="parameter_name" class="form-control" required placeholder="e.g. Haemoglobin">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Short Name</label>
                        <input type="text" name="short_name" class="form-control"
                               style="text-transform:uppercase;" placeholder="e.g. HGB">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control"
                               value="<?= count($subParams) * 10 ?>" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Normal Range (Male)</label>
                        <input type="text" name="normal_range_male" class="form-control" placeholder="e.g. 13.5–17.5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Normal Range (Female)</label>
                        <input type="text" name="normal_range_female" class="form-control" placeholder="e.g. 12.0–15.5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unit</label>
                        <input type="text" name="sub_unit" class="form-control" placeholder="e.g. g/dL">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check me-2"></i>Add Sub-Parameter</button>
            </div>
        </form>
    </div></div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>