<?php
/**
 * UniServe Enterprise Admin Layout Header
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// Strict administrative access control
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php?msg=unauthorized');
    exit();
}

if (!defined('UNISERVE_ADMIN_LAYOUT')) {
    define('UNISERVE_ADMIN_LAYOUT', true);
}

$pageTitle = $pageTitle ?? 'Admin Console';
$pageSubtitle = $pageSubtitle ?? 'UniServe Ecosystem Governance';
$activeNav = $activeNav ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — UniServe Admin</title>
    <link rel="stylesheet" href="assets/css/admin_theme.css">
</head>

<body class="admin-body">
<div class="admin-layout">

    <!-- SIDEBAR NAVIGATION -->
    <?php require_once __DIR__ . '/admin_sidebar.php'; ?>

    <!-- MAIN APP CANVAS -->
    <main class="admin-main">
        
        <!-- TOPBAR -->
        <header class="admin-topbar">
            <div class="admin-topbar-title">
                <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div style="display: flex; align-items: center; gap: 16px;">
                <a href="profile.php" class="btn-secondary" style="padding: 7px 14px; font-size: 12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span>Account Settings</span>
                </a>
            </div>
        </header>

        <!-- CONTENT BODY -->
        <div class="admin-content-body">
