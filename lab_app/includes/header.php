<?php
ob_start();
require_once __DIR__ . '/config.php';
labRequireLogin();

$labUser    = labCurrentUser();
$labFlash   = labGetFlash();
$labDb      = getLabDb();
$labInfo    = getLabInfo();
$currentPage= basename($_SERVER['PHP_SELF'],'.php');
$slug       = $labUser['slug'];

if (!$labDb) {
    session_destroy();
    header('Location: ' . LAB_APP_URL . '/login.php');
    exit;
}

// Lab settings
$labName    = labGetSetting($labDb, 'lab_name', $labInfo['name'] ?? 'Lab');
$labAddress = labGetSetting($labDb, 'lab_address', '');

// Subscription warning
$showSubWarning = false;
$subDaysLeft    = 0;
if ($labInfo) {
    if ($labInfo['status'] === 'trial' && $labInfo['trial_ends_at']) {
        $subDaysLeft    = daysLeft($labInfo['trial_ends_at']);
        $showSubWarning = $subDaysLeft <= 5;
    } elseif ($labInfo['status'] === 'active' && $labInfo['subscription_ends_at']) {
        $subDaysLeft    = daysLeft($labInfo['subscription_ends_at']);
        $showSubWarning = $subDaysLeft <= 7;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= labClean($labName) ?> — <?= $pageTitle ?? 'Dashboard' ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔬</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
:root{--lc-primary:#1a6b4a;--lc-primary-d:#134f37;--lc-primary-l:#e8f5ef;--lc-accent:#f59e0b;--lc-sidebar:#0d3d28;--lc-bg:#f1f5f3;--lc-card:#ffffff;--lc-border:#e2e8f0;--lc-text:#1e293b;--lc-muted:#64748b;--sidebar-w:260px;--topbar-h:64px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--lc-bg);color:var(--lc-text);display:flex;min-height:100vh}
#sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--lc-sidebar);position:fixed;top:0;left:0;display:flex;flex-direction:column;z-index:1000;transition:transform .25s ease}
.sidebar-brand{display:flex;align-items:center;gap:12px;padding:20px;border-bottom:1px solid rgba(255,255,255,.08)}
.brand-icon{width:40px;height:40px;background:var(--lc-primary);border-radius:10px;display:grid;place-items:center;font-size:20px;color:#fff;flex-shrink:0}
.brand-text strong{display:block;color:#fff;font-size:14px;line-height:1.2}
.brand-text span{color:rgba(255,255,255,.4);font-size:10px;letter-spacing:.5px}
.sidebar-nav{flex:1;padding:16px 0;overflow-y:auto}
.nav-label{font-size:10px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:14px 20px 6px}
.sidebar-link{display:flex;align-items:center;gap:12px;padding:10px 20px;color:rgba(255,255,255,.65);text-decoration:none;font-size:14px;font-weight:500;transition:all .15s;position:relative}
.sidebar-link:hover{background:rgba(255,255,255,.06);color:#fff}
.sidebar-link.active{background:var(--lc-primary);color:#fff}
.sidebar-link.active::before{content:'';position:absolute;right:0;top:0;height:100%;width:3px;background:var(--lc-accent);border-radius:3px 0 0 3px}
.sidebar-link i{font-size:18px;width:22px}
.sidebar-footer{padding:16px;border-top:1px solid rgba(255,255,255,.08)}
.user-card{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,255,255,.07);border-radius:10px}
.user-avatar{width:36px;height:36px;background:var(--lc-primary);border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;font-size:15px;flex-shrink:0}
.user-info strong{display:block;color:#fff;font-size:13px}
.user-info span{color:rgba(255,255,255,.45);font-size:11px}
#main-wrapper{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
#topbar{height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--lc-border);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:900}
.topbar-title{font-size:18px;font-weight:600;flex:1}
.topbar-title span{color:var(--lc-muted);font-weight:400;font-size:14px;margin-left:8px}
#page-content{flex:1;padding:28px}
.card{background:var(--lc-card);border:1px solid var(--lc-border);border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.card-header{background:transparent;border-bottom:1px solid var(--lc-border);padding:18px 22px;font-weight:600;font-size:15px;display:flex;align-items:center;gap:8px}
.card-body{padding:22px}
.stat-card{background:#fff;border:1px solid var(--lc-border);border-radius:14px;padding:22px;display:flex;align-items:flex-start;gap:16px;transition:box-shadow .2s}
.stat-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.08)}
.stat-icon{width:52px;height:52px;border-radius:12px;display:grid;place-items:center;font-size:22px;flex-shrink:0}
.stat-icon.green{background:var(--lc-primary-l);color:var(--lc-primary)}
.stat-icon.amber{background:#fef3c7;color:#d97706}
.stat-icon.blue{background:#dbeafe;color:#2563eb}
.stat-icon.red{background:#fee2e2;color:#dc2626}
.stat-icon.purple{background:#ede9fe;color:#7c3aed}
.stat-info .label{font-size:13px;color:var(--lc-muted);margin-bottom:4px}
.stat-info .value{font-size:28px;font-weight:700;line-height:1}
.stat-info .change{font-size:12px;margin-top:4px}
.change.up{color:var(--lc-primary)}.change.down{color:#dc2626}
.btn-primary{background:var(--lc-primary);border-color:var(--lc-primary)}
.btn-primary:hover{background:var(--lc-primary-d);border-color:var(--lc-primary-d)}
.btn-outline-primary{color:var(--lc-primary);border-color:var(--lc-primary)}
.btn-outline-primary:hover{background:var(--lc-primary);color:#fff}
.table thead th{background:#f8faf9;color:var(--lc-muted);font-size:12px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;border-bottom:2px solid var(--lc-border);padding:12px 16px}
.table td{vertical-align:middle;padding:12px 16px;font-size:14px}
.form-control,.form-select{border-color:var(--lc-border);font-size:14px;border-radius:8px;padding:10px 14px}
.form-control:focus,.form-select:focus{border-color:var(--lc-primary);box-shadow:0 0 0 3px rgba(26,107,74,.12)}
.form-label{font-size:13px;font-weight:600;margin-bottom:6px}
.badge-status{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap}
.status-pending{background:#fef9c3;color:#854d0e}.status-sample_collected{background:#e0f2fe;color:#0369a1}
.status-processing{background:#ede9fe;color:#6d28d9}.status-completed{background:#dcfce7;color:#166534}
.status-delivered{background:#d1fae5;color:#065f46}.status-cancelled{background:#fee2e2;color:#991b1b}
.status-normal{background:#dcfce7;color:#166534}.status-abnormal{background:#fff7ed;color:#9a3412}.status-critical{background:#fee2e2;color:#991b1b}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-header h2{font-size:22px;font-weight:700}
.page-header p{color:var(--lc-muted);font-size:14px;margin:0}
.flash-toast{position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,.12);animation:slideIn .3s ease}
@keyframes slideIn{from{transform:translateX(60px);opacity:0}to{transform:translateX(0);opacity:1}}
.flash-toast.success{background:#dcfce7;border-left:4px solid var(--lc-primary);color:#166534}
.flash-toast.error{background:#fee2e2;border-left:4px solid #dc2626;color:#991b1b}
.flash-toast.info{background:#dbeafe;border-left:4px solid #2563eb;color:#1e40af}
.divider{height:1px;background:var(--lc-border);margin:20px 0}
@media(max-width:992px){#sidebar{transform:translateX(-100%)}#sidebar.open{transform:translateX(0)}#main-wrapper{margin-left:0}}
</style>
</head>
<body>

<aside id="sidebar">
    <div class="sidebar-brand">
        <img src="<?= LAB_APP_URL ?>/assets/logos/LondonLab_Logo---modified.png" alt="Logo" height="60">
        <div class="brand-text">
            <strong><?= labClean($labName) ?></strong>
            <span>LABORATORY SYSTEM</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="<?= LAB_APP_URL ?>/index.php?lab=<?= $slug ?>" class="sidebar-link <?= $currentPage==='index'?'active':'' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        <?php if (labCanAccess('patients') || labCanAccess('orders')): ?>
        <div class="nav-label">Operations</div>
        <?php endif; ?>
        <?php if (labCanAccess('patients')): ?>
        <a href="<?= LAB_APP_URL ?>/modules/patients/index.php?lab=<?= $slug ?>" class="sidebar-link <?= strpos($currentPage,'patient')!==false?'active':'' ?>">
            <i class="bi bi-people-fill"></i> Patients
        </a>
        <?php endif; ?>
        <?php if (labCanAccess('doctors')): ?>
        <a href="<?= LAB_APP_URL ?>/modules/doctors/index.php?lab=<?= $slug ?>" class="sidebar-link <?= strpos($currentPage,'doctor')!==false?'active':'' ?>">
            <i class="bi bi-person-vcard-fill"></i> Doctors
        </a>
        <?php endif; ?>
        <?php if (labCanAccess('orders')): ?>
        <a href="<?= LAB_APP_URL ?>/modules/orders/index.php?lab=<?= $slug ?>" class="sidebar-link <?= strpos($currentPage,'order')!==false?'active':'' ?>">
            <i class="bi bi-clipboard2-pulse-fill"></i> Orders / Tests
        </a>
        <?php endif; ?>
        <?php if (labCanAccess('tests')): ?>
        <a href="<?= LAB_APP_URL ?>/modules/tests/index.php?lab=<?= $slug ?>" class="sidebar-link <?= strpos($currentPage,'test')!==false?'active':'' ?>">
            <i class="bi bi-droplet-half"></i> Test Catalog
        </a>
        <?php endif; ?>
        <?php if (labCanAccess('expenses') || labCanAccess('reports')): ?>
        <div class="nav-label">Finance</div>
        <?php endif; ?>
        <?php if (labCanAccess('expenses')): ?>
        <a href="<?= LAB_APP_URL ?>/modules/expenses/index.php?lab=<?= $slug ?>" class="sidebar-link <?= strpos($currentPage,'expense')!==false?'active':'' ?>">
            <i class="bi bi-cash-stack"></i> Expenses
        </a>
        <?php endif; ?>
        <?php if (labCanAccess('reports')): ?>
        <a href="<?= LAB_APP_URL ?>/modules/reports/index.php?lab=<?= $slug ?>" class="sidebar-link <?= strpos($currentPage,'report')!==false?'active':'' ?>">
            <i class="bi bi-bar-chart-fill"></i> Reports
        </a>
        <?php endif; ?>
        <?php if (labCanAccess('users') || labCanAccess('settings')): ?>
        <div class="nav-label">System</div>
        <?php endif; ?>
        <?php if (labCanAccess('users')): ?>
        <a href="<?= LAB_APP_URL ?>/modules/users/index.php?lab=<?= $slug ?>" class="sidebar-link <?= strpos($currentPage,'user')!==false?'active':'' ?>">
            <i class="bi bi-person-gear"></i> Users
        </a>
        <?php endif; ?>
        <?php if (labCanAccess('settings')): ?>
        <a href="<?= LAB_APP_URL ?>/modules/settings/index.php?lab=<?= $slug ?>" class="sidebar-link <?= strpos($currentPage,'setting')!==false?'active':'' ?>">
            <i class="bi bi-gear-fill"></i> Settings
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?= strtoupper(substr($labUser['name'],0,1)) ?></div>
            <div class="user-info" style="flex:1;min-width:0;">
                <strong><?= labClean($labUser['name']) ?></strong>
                <span><?= ucfirst($labUser['role']) ?></span>
            </div>
            <a href="<?= LAB_APP_URL ?>/logout.php?lab=<?= $slug ?>" title="Logout" style="color:rgba(255,255,255,.4);">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </div>
</aside>

<div id="main-wrapper">
    <header id="topbar">
        <button class="btn btn-sm d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="topbar-title">
            <?= $pageTitle ?? 'Dashboard' ?>
            <span><?= labClean($labName) ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($showSubWarning): ?>
            <span class="badge bg-warning text-dark px-3 py-2">
                ⚠️ <?= $subDaysLeft ?> day(s) left
            </span>
            <?php endif; ?>
            <?php if ($labInfo && $labInfo['status'] === 'trial'): ?>
            <span class="badge bg-info-subtle text-info px-3 py-2">Trial</span>
            <?php endif; ?>
            <div class="text-muted small"><?= date('D, d M Y') ?></div>
        </div>
    </header>

    <div id="page-content">
        <?php if ($labFlash): ?>
        <div class="flash-toast <?= $labFlash['type'] ?>" id="labFlash">
            <i class="bi bi-<?= $labFlash['type']==='success'?'check-circle-fill':'x-circle-fill' ?>"></i>
            <?= labClean($labFlash['message']) ?>
        </div>
        <script>setTimeout(()=>{const t=document.getElementById('labFlash');if(t){t.style.opacity='0';t.style.transform='translateX(60px)';t.style.transition='all .3s';setTimeout(()=>t.remove(),300);}},4000);</script>
        <?php endif; ?>