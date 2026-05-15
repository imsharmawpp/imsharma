<?php
/**
 * Admin Panel Shared Header & Sidebar
 * Include at the top of every admin page (after auth check).
 */

if (!isset($pageTitle)) $pageTitle = 'Admin Panel';
$currentAdmin = getCurrentAdmin();
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> - VastuKundali Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../frontend/css/style.css">
    <link rel="stylesheet" href="../../frontend/css/animations.css">
    <style>
        body { background: #FAF8F1; }
        .admin-content { padding: 24px; }
        .table-actions { display: flex; gap: 8px; }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="logo">
            <span class="logo-icon">🏛️</span>
            <span class="logo-text">Vastu<span class="gold">Admin</span></span>
        </div>
        <nav class="admin-nav">
            <a href="dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="reports.php" class="<?= $currentPage === 'reports' ? 'active' : '' ?>"><i class="fas fa-file-alt"></i> Reports</a>
            <a href="orders.php" class="<?= $currentPage === 'orders' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="products.php" class="<?= $currentPage === 'products' || $currentPage === 'product_edit' ? 'active' : '' ?>"><i class="fas fa-box"></i> Products</a>
            <a href="users.php" class="<?= $currentPage === 'users' ? 'active' : '' ?>"><i class="fas fa-users"></i> Users</a>
            <a href="leads.php" class="<?= $currentPage === 'leads' ? 'active' : '' ?>"><i class="fas fa-user-plus"></i> Leads</a>
            <a href="coupons.php" class="<?= $currentPage === 'coupons' ? 'active' : '' ?>"><i class="fas fa-tag"></i> Coupons</a>
            <a href="settings.php" class="<?= $currentPage === 'settings' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Settings</a>
            <a href="logout.php" style="margin-top: auto; color: #EF4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <div class="user-menu">
                <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #D4AF37, #B8941F); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #0A0E27; font-weight: 700;">
                    <?= strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)) ?>
                </div>
                <div>
                    <strong style="font-size: 14px;"><?= htmlspecialchars($currentAdmin['name'] ?? 'Admin') ?></strong>
                    <div style="font-size: 12px; color: #4B5563;">Administrator</div>
                </div>
            </div>
        </div>
        <div class="admin-content">
