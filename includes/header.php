<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$flash = get_flash();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' | Billing & Inventory' : 'Billing & Inventory Management System' ?></title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Main Application Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (isset($extra_css)): ?>
        <link rel="stylesheet" href="<?= e($extra_css) ?>">
    <?php endif; ?>
</head>
<body>
<div class="app-container">
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Navigation Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button id="sidebarToggle" class="btn-icon" title="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="breadcrumbs">
                    <span class="breadcrumb-item"><i class="fa-solid fa-layer-group"></i> Management</span>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-active"><?= isset($page_title) ? e($page_title) : 'Dashboard' ?></span>
                </div>
            </div>
            <div class="topbar-right">
                <a href="invoice_create.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-receipt"></i> + New Invoice
                </a>
                <div class="user-pill">
                    <div class="avatar"><i class="fa-solid fa-user-shield"></i></div>
                    <span class="username">Admin</span>
                </div>
            </div>
        </header>

        <!-- Flash Message Alerts -->
        <?php if ($flash): ?>
            <div class="flash-alert alert-<?= e($flash['type']) ?>">
                <div class="alert-icon">
                    <?php if ($flash['type'] === 'success'): ?>
                        <i class="fa-solid fa-circle-check"></i>
                    <?php elseif ($flash['type'] === 'danger'): ?>
                        <i class="fa-solid fa-circle-exclamation"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-circle-info"></i>
                    <?php endif; ?>
                </div>
                <div class="alert-content">
                    <?= e($flash['message']) ?>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>

        <div class="content-wrapper">
