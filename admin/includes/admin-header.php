<?php
require_once __DIR__ . '/../../functions.php';
initSession();
if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
    requireLogin();
}
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? 'Dashboard'); ?> - Bharat SEO CRM</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="brand">
                    <span class="brand-icon">&#9733;</span>
                    <div>
                        <h2>Bharat SEO</h2>
                        <small>Lead Finder & CRM</small>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#8962;</span> Dashboard
                </a>
                <a href="lead-search.php" class="nav-item <?php echo $currentPage === 'lead-search' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#128269;</span> Lead Search
                </a>
                <a href="leads.php" class="nav-item <?php echo $currentPage === 'leads' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#128101;</span> All Leads
                </a>
                <a href="bulk-audit.php" class="nav-item <?php echo $currentPage === 'bulk-audit' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#9889;</span> Bulk Audit
                </a>
                <a href="outreach.php" class="nav-item <?php echo $currentPage === 'outreach' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#128172;</span> Outreach
                </a>
                <a href="import-csv.php" class="nav-item <?php echo $currentPage === 'import-csv' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#128228;</span> Import CSV
                </a>
                <a href="export.php" class="nav-item <?php echo $currentPage === 'export' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#128229;</span> Export
                </a>
                <a href="settings.php" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#9881;</span> Settings
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item logout-btn">
                    <span class="nav-icon">&#10148;</span> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <button class="menu-toggle" id="menuToggle">&#9776;</button>
                <h1 class="page-title"><?php echo e($pageTitle ?? 'Dashboard'); ?></h1>
                <div class="top-bar-right">
                    <span class="admin-user">&#128100; <?php echo e($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                </div>
            </header>
            <div class="content-wrapper">
