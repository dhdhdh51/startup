<?php
/**
 * Bharat SEO CRM - Admin Header
 * 
 * HTML5 responsive header with sidebar navigation.
 */

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <title><?php echo isset($pageTitle) ? sanitize($pageTitle) . ' - ' : ''; ?><?php echo SITE_NAME; ?> CRM</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h2><?php echo SITE_NAME; ?></h2>
                <p class="brand-tagline"><?php echo SITE_TAGLINE; ?></p>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a>
                    </li>
                    <li class="<?php echo $currentPage === 'lead-search' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>admin/lead-search.php">Lead Search</a>
                    </li>
                    <li class="<?php echo $currentPage === 'leads' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>admin/leads.php">Leads</a>
                    </li>
                    <li class="<?php echo $currentPage === 'bulk-audit' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>admin/bulk-audit.php">Bulk Audit</a>
                    </li>
                    <li class="<?php echo $currentPage === 'import-csv' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>admin/import-csv.php">Import CSV</a>
                    </li>
                    <li class="<?php echo $currentPage === 'export' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>admin/export.php">Export</a>
                    </li>
                    <li class="<?php echo $currentPage === 'outreach' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>admin/outreach.php">Outreach</a>
                    </li>
                    <li class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>admin/settings.php">Settings</a>
                    </li>
                </ul>
            </nav>
            <div class="sidebar-user">
                <span class="user-name"><?php echo sanitize($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                <a href="<?php echo BASE_URL; ?>admin/logout.php" class="logout-link">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <button class="sidebar-toggle" id="sidebarToggle">&#9776;</button>
                <h1><?php echo isset($pageTitle) ? sanitize($pageTitle) : 'Dashboard'; ?></h1>
            </header>
            <div class="content-body">
