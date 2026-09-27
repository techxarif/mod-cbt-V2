<?php
// includes/header.php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/bootstrap.php';
}

$page_title = $page_title ?? (defined('APP_NAME') ? APP_NAME : 'MODUS CBT');
$base       = app_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/responsive.css">
</head>
<body>
<!-- Mobile Backdrop Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Mobile Top Bar with Hamburger -->
<header class="mobile-topbar">
    <a href="<?= $base ?>dashboard/index.php" class="mobile-brand">
        <div class="mobile-brand-logo">M</div>
        <div class="mobile-brand-name"><?= e(APP_NAME) ?></div>
    </a>
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
        ☰
    </button>
</header>

<div class="admin-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="admin-main">
        <div class="admin-content">