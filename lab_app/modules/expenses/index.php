<?php
$pageTitle = 'Expenses';
require_once __DIR__ . '/../../includes/header.php';
labRequireAccess('expenses');

if (isset($_GET['delete']) && labIsAdmin()) {
    $labDb->execute("DELETE FROM expenses WHERE id=?", [(int)$_GET['delete']]);
    labSetFlash('success','Expense deleted.');
    header('Location: '.$_SERVER['PHP_SELF'].'?lab='.$slug); exit;
}

$expenses = $labDb->fetchAll("SELECT e.*,u.name as by_name FROM expenses e LEFT JOIN users u ON e.created_by=u.id ORDER BY e.expense_date DESC,e.id DESC");
$total    = array_sum(array_column($expenses,'amount'));
?>
<div class="page-header">
    <div><h2><i class="bi bi-cash-stack me-2 text-danger"></i>Expenses</h2><p>Track all lab expenditures</p></div>
    <a href="<?= LAB_APP_URL ?>/modules/expenses/create.php?lab=<?= $slug ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle-fill me-2"></i>Add Expense
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover dt-table mb-0">
                <thead><tr><th>Date</th><th>Title</th><th>Category</th><th>Vendor</th><th>Amount</th><th>Method</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td><?= date('d M Y',strtotime($e['expense_date'])) ?></td>
                        <td class="fw-semibold"><?= labClean($e['title']) ?></td>
                        <td><span class="badge bg-warning-subtle text-warning-emphasis text-capitalize"><?= labClean($e['category']) ?></span></td>
                        <td><?= $e['vendor'] ? labClean($e['vendor']) : '—' ?></td>
                        <td><strong class="text-danger"><?= labMoney($e['amount']) ?></strong></td>
                        <td><?= ucfirst(str_replace('_',' ',$e['payment_method'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= LAB_APP_URL ?>/modules/expenses/edit.php?lab=<?= $slug ?>&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <?php if (labIsAdmin()): ?>
                                <button onclick="confirmDelete('<?= LAB_APP_URL ?>/modules/expenses/index.php?lab=<?= $slug ?>&delete=<?= $e['id'] ?>')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold"><td colspan="4" class="text-end">Total:</td><td class="text-danger"><?= labMoney($total) ?></td><td colspan="2"></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>