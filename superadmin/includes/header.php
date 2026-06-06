<?php
ob_start();
require_once __DIR__ . '/auth.php';
saRequireLogin();
$saUser      = saCurrentUser();
$saFlash     = saGetFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Stats for sidebar badges
$db = MasterDB::getInstance();
$pendingInvoices = $db->fetch("SELECT COUNT(*) as c FROM billing_invoices WHERE status='pending'")['c'];
$trialExpiring   = $db->fetch("SELECT COUNT(*) as c FROM labs WHERE status='trial' AND trial_ends_at <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)")['c'];
$openTickets     = $db->fetch("SELECT COUNT(*) as c FROM support_tickets WHERE status='open'")['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= PLATFORM_NAME ?> Admin — <?= $pageTitle ?? 'Dashboard' ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔬</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
:root {
    --sa-primary:   #7c3aed;
    --sa-primary-d: #5b21b6;
    --sa-primary-l: #ede9fe;
    --sa-accent:    #f59e0b;
    --sa-sidebar:   #1e1b4b;
    --sa-bg:        #f5f3ff;
    --sa-card:      #ffffff;
    --sa-border:    #e5e7eb;
    --sa-text:      #1e293b;
    --sa-muted:     #64748b;
    --sidebar-w:    260px;
    --topbar-h:     64px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--sa-bg); color: var(--sa-text); display: flex; min-height: 100vh; }

/* SIDEBAR */
#sidebar { width: var(--sidebar-w); min-height: 100vh; background: var(--sa-sidebar); position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 1000; }
.sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 20px; border-bottom: 1px solid rgba(255,255,255,.08); }
.brand-icon { width: 40px; height: 40px; background: var(--sa-primary); border-radius: 10px; display: grid; place-items: center; font-size: 20px; color: #fff; flex-shrink: 0; }
.brand-text strong { display: block; color: #fff; font-size: 15px; }
.brand-text span   { color: rgba(255,255,255,.4); font-size: 11px; letter-spacing: .5px; }
.sidebar-nav { flex: 1; padding: 16px 0; overflow-y: auto; }
.nav-label { font-size: 10px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase; color: rgba(255,255,255,.3); padding: 14px 20px 6px; }
.sidebar-link { display: flex; align-items: center; gap: 12px; padding: 10px 20px; color: rgba(255,255,255,.65); text-decoration: none; font-size: 14px; font-weight: 500; transition: all .15s; position: relative; }
.sidebar-link:hover { background: rgba(255,255,255,.06); color: #fff; }
.sidebar-link.active { background: var(--sa-primary); color: #fff; }
.sidebar-link.active::before { content:''; position:absolute; right:0; top:0; height:100%; width:3px; background: var(--sa-accent); border-radius:3px 0 0 3px; }
.sidebar-link i { font-size: 18px; width: 22px; }
.sidebar-link .badge { margin-left: auto; font-size: 10px; }
.sidebar-footer { padding: 16px; border-top: 1px solid rgba(255,255,255,.08); }
.user-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: rgba(255,255,255,.07); border-radius: 10px; }
.user-avatar { width: 36px; height: 36px; background: var(--sa-primary); border-radius: 50%; display: grid; place-items: center; color: #fff; font-weight: 700; font-size: 15px; flex-shrink: 0; }
.user-info strong { display: block; color: #fff; font-size: 13px; }
.user-info span   { color: rgba(255,255,255,.45); font-size: 11px; }

/* MAIN */
#main-wrapper { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
#topbar { height: var(--topbar-h); background: #fff; border-bottom: 1px solid var(--sa-border); display: flex; align-items: center; padding: 0 28px; gap: 16px; position: sticky; top: 0; z-index: 900; }
.topbar-title { font-size: 18px; font-weight: 600; flex: 1; }
.topbar-title span { color: var(--sa-muted); font-weight: 400; font-size: 14px; margin-left: 8px; }
#page-content { flex: 1; padding: 28px; }

/* CARDS */
.card { background: var(--sa-card); border: 1px solid var(--sa-border); border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
.card-header { background: transparent; border-bottom: 1px solid var(--sa-border); padding: 18px 22px; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 8px; }
.card-body { padding: 22px; }

/* STAT CARDS */
.stat-card { background: #fff; border: 1px solid var(--sa-border); border-radius: 14px; padding: 22px; display: flex; align-items: flex-start; gap: 16px; transition: box-shadow .2s; }
.stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); }
.stat-icon { width: 52px; height: 52px; border-radius: 12px; display: grid; place-items: center; font-size: 22px; flex-shrink: 0; }
.stat-icon.purple { background: var(--sa-primary-l); color: var(--sa-primary); }
.stat-icon.green  { background: #dcfce7; color: #16a34a; }
.stat-icon.amber  { background: #fef3c7; color: #d97706; }
.stat-icon.red    { background: #fee2e2; color: #dc2626; }
.stat-icon.blue   { background: #dbeafe; color: #2563eb; }
.stat-info .label { font-size: 13px; color: var(--sa-muted); margin-bottom: 4px; }
.stat-info .value { font-size: 28px; font-weight: 700; line-height: 1; }
.stat-info .sub   { font-size: 12px; color: var(--sa-muted); margin-top: 4px; }

/* BUTTONS */
.btn-primary { background: var(--sa-primary); border-color: var(--sa-primary); }
.btn-primary:hover { background: var(--sa-primary-d); border-color: var(--sa-primary-d); }
.btn-outline-primary { color: var(--sa-primary); border-color: var(--sa-primary); }
.btn-outline-primary:hover { background: var(--sa-primary); color: #fff; }

/* TABLES */
.table thead th { background: #f8f7ff; color: var(--sa-muted); font-size: 12px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; border-bottom: 2px solid var(--sa-border); padding: 12px 16px; }
.table td { vertical-align: middle; padding: 12px 16px; font-size: 14px; }

/* FORMS */
.form-control, .form-select { border-color: var(--sa-border); font-size: 14px; border-radius: 8px; padding: 10px 14px; }
.form-control:focus, .form-select:focus { border-color: var(--sa-primary); box-shadow: 0 0 0 3px rgba(124,58,237,.12); }
.form-label { font-size: 13px; font-weight: 600; margin-bottom: 6px; }

/* STATUS BADGES */
.badge-status { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-trial     { background: #e0f2fe; color: #0369a1; }
.status-active    { background: #dcfce7; color: #166534; }
.status-suspended { background: #fef3c7; color: #92400e; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.status-pending   { background: #fef3c7; color: #92400e; }
.status-paid      { background: #dcfce7; color: #166534; }
.status-overdue   { background: #fee2e2; color: #991b1b; }

/* PAGE HEADER */
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.page-header h2 { font-size: 22px; font-weight: 700; }
.page-header p  { color: var(--sa-muted); font-size: 14px; margin: 0; }

/* FLASH */
.flash-toast { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; box-shadow: 0 4px 20px rgba(0,0,0,.12); animation: slideIn .3s ease; }
@keyframes slideIn { from { transform: translateX(60px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.flash-toast.success { background: #dcfce7; border-left: 4px solid #16a34a; color: #166534; }
.flash-toast.error   { background: #fee2e2; border-left: 4px solid #dc2626; color: #991b1b; }
.flash-toast.info    { background: #dbeafe; border-left: 4px solid #2563eb; color: #1e40af; }
.flash-toast.warning { background: #fef3c7; border-left: 4px solid var(--sa-accent); color: #92400e; }

.divider { height: 1px; background: var(--sa-border); margin: 20px 0; }

@media (max-width: 992px) { #sidebar { transform: translateX(-100%); } #sidebar.open { transform: translateX(0); } #main-wrapper { margin-left: 0; } }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside id="sidebar">
    <div class="sidebar-brand">
        <img src="/londonlab/superadmin/assets/logos/LondonLab_Logo---modified.png" alt="Logo" height="60" >
        <div class="brand-text">
            <strong><?= PLATFORM_NAME ?></strong>
            <span>SUPER ADMIN PANEL</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="<?= SUPERADMIN_URL ?>/index.php" class="sidebar-link <?= $currentPage==='index'?'active':'' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>

        <div class="nav-label">Labs</div>
        <a href="<?= SUPERADMIN_URL ?>/modules/labs/index.php" class="sidebar-link <?= strpos($currentPage,'lab')!==false?'active':'' ?>">
            <i class="bi bi-building"></i> All Labs
        </a>
        <a href="<?= SUPERADMIN_URL ?>/modules/labs/create.php" class="sidebar-link">
            <i class="bi bi-plus-circle"></i> Add New Lab
        </a>

        <div class="nav-label">Finance</div>
        <a href="<?= SUPERADMIN_URL ?>/modules/billing/index.php" class="sidebar-link <?= strpos($currentPage,'billing')!==false || strpos($currentPage,'invoice')!==false ?'active':'' ?>">
            <i class="bi bi-receipt"></i> Billing / Invoices
            <?php if ($pendingInvoices > 0): ?>
            <span class="badge bg-warning text-dark"><?= $pendingInvoices ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= SUPERADMIN_URL ?>/modules/subscriptions/index.php" class="sidebar-link <?= strpos($currentPage,'subscription')!==false?'active':'' ?>">
            <i class="bi bi-calendar-check"></i> Subscriptions
            <?php if ($trialExpiring > 0): ?>
            <span class="badge bg-danger"><?= $trialExpiring ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= SUPERADMIN_URL ?>/modules/reports/index.php" class="sidebar-link <?= strpos($currentPage,'report')!==false?'active':'' ?>">
            <i class="bi bi-bar-chart-fill"></i> Revenue Reports
        </a>

        <div class="nav-label">System</div>
        <a href="<?= SUPERADMIN_URL ?>/modules/plans/index.php" class="sidebar-link <?= strpos($currentPage,'plan')!==false?'active':'' ?>">
            <i class="bi bi-boxes"></i> Manage Plans
        </a>
        <a href="<?= SUPERADMIN_URL ?>/profile.php" class="sidebar-link <?= $currentPage==='profile'?'active':'' ?>">
            <i class="bi bi-person-gear"></i> My Profile
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?= strtoupper(substr($saUser['name'],0,1)) ?></div>
            <div class="user-info" style="flex:1;min-width:0;">
                <strong><?= saClean($saUser['name']) ?></strong>
                <span><?= ucfirst($saUser['role']) ?></span>
            </div>
            <a href="<?= SUPERADMIN_URL ?>/logout.php" title="Logout" style="color:rgba(255,255,255,.4);">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </div>
</aside>

<!-- MAIN -->
<div id="main-wrapper">
    <header id="topbar">
        <button class="btn btn-sm d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="topbar-title">
            <?= $pageTitle ?? 'Dashboard' ?>
            <span><?= PLATFORM_NAME ?> Control Panel</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($trialExpiring > 0): ?>
            <a href="<?= SUPERADMIN_URL ?>/modules/subscriptions/index.php" class="badge bg-danger text-decoration-none px-3 py-2">
                <?= $trialExpiring ?> trial(s) expiring soon
            </a>
            <?php endif; ?>
            <div class="text-muted small"><?= date('D, d M Y') ?></div>
        </div>
    </header>

    <div id="page-content">
        <?php if ($saFlash): ?>
        <div class="flash-toast <?= $saFlash['type'] ?>" id="saFlashToast">
            <i class="bi bi-<?= $saFlash['type']==='success'?'check-circle-fill':'x-circle-fill' ?>"></i>
            <?= $saFlash['message'] ?>
        </div>
        <script>setTimeout(()=>{ const t=document.getElementById('saFlashToast'); if(t){t.style.opacity='0';t.style.transform='translateX(60px)';t.style.transition='all .3s';setTimeout(()=>t.remove(),300);}},4000);</script>
        <?php endif; ?>
