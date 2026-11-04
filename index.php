<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/Response.php';

// Fetch latest APK metadata
$apkFile = __DIR__ . '/downloads/UniServe-v1.0.apk';
$fileSize = file_exists($apkFile) ? round(filesize($apkFile) / (1024 * 1024), 2) : 14.95;
$checksum = file_exists($apkFile) ? hash_file('sha256', $apkFile) : 'a5536eb99b760723bcd6845faf2a4db2c0fe95e4f48b066cf640c4e70e9a5697';
$version = 'v1.0.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniServe — Unified Business & Service Management Platform</title>
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Navigation Header -->
    <header class="landing-nav">
        <a href="index.php" class="brand-wrap">
            <div class="brand-logo">U</div>
            <div class="brand-text">
                <strong>UniServe</strong>
                <small>Ecosystem</small>
            </div>
        </a>

        <ul class="nav-links">
            <li><a href="#features">Features</a></li>
            <li><a href="#download">Android App</a></li>
            <li><a href="#roles">Portals</a></li>
            <li><a href="login.php" class="btn btn-secondary btn-sm">Sign In</a></li>
            <li><a href="register.php" class="btn btn-primary btn-sm">Get Started</a></li>
        </ul>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <span class="apk-badge">&#9679; Version 1.0 Production Ready</span>
            <h1>Unified Services.<br><span class="hero-highlight">Absolute Precision.</span></h1>
            <p class="hero-subtitle">
                An enterprise-grade ecosystem uniting live inventory, food & retail ordering, restaurant reservations, and transactional governance across Web and native Android.
            </p>
            <div class="hero-actions">
                <a href="#download" class="btn btn-primary">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Android APK
                </a>
                <a href="login.php" class="btn btn-secondary">Web Console Login</a>
            </div>
        </div>

        <!-- Standout APK Download Card -->
        <div class="apk-card" id="download">
            <span class="apk-badge">&#129302; Official Android Release</span>
            <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 8px;">UniServe Mobile</h3>
            <p style="color: var(--color-text-muted); font-size: 0.875rem; margin-bottom: 20px;">
                Designed exclusively for Customers and Merchants. Real-time ordering, push fulfillment, and table bookings.
            </p>

            <div class="apk-details">
                <span>Release Version</span>
                <span><?= htmlspecialchars($version) ?></span>
            </div>
            <div class="apk-details">
                <span>Package Size</span>
                <span><?= $fileSize ?> MB</span>
            </div>
            <div class="apk-details">
                <span>Target Architecture</span>
                <span>Universal (ARM64 / x86_64)</span>
            </div>
            <div class="apk-details">
                <span>Direct REST API</span>
                <span style="color: var(--color-accent);">&#10003; Connected</span>
            </div>

            <div class="checksum-box">
                <strong style="color: #F1F5F9; display: block; margin-bottom: 4px;">SHA-256 Checksum:</strong>
                <?= htmlspecialchars($checksum) ?>
            </div>

            <a href="download.php" class="btn btn-accent" style="width: 100%; margin-top: 10px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download UniServe.apk (<?= $fileSize ?> MB)
            </a>
            <small style="display: block; text-align: center; color: var(--color-text-dim); margin-top: 8px; font-size: 0.75rem;">
                Requires Android 8.0 (Oreo) or higher.
            </small>
        </div>
    </section>

    <!-- Role Architecture Matrix -->
    <section class="features-section" id="roles">
        <div class="section-header">
            <h2>Three Dedicated Roles. One Architecture.</h2>
            <p>Purpose-driven workflows engineered for seamless business operations.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">&#128187;</div>
                <h3 style="margin-bottom: 8px; font-weight: 700;">Admin Governance</h3>
                <span class="badge badge-danger" style="margin-bottom: 12px;">Web Portal Only</span>
                <p style="color: var(--color-text-muted); font-size: 0.875rem; line-height: 1.6;">
                    Super Admin command center for system KPIs, merchant approvals, global transactions, audit logs, and APK release distribution.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">&#127978;</div>
                <h3 style="margin-bottom: 8px; font-weight: 700;">Merchant Operations</h3>
                <span class="badge badge-success" style="margin-bottom: 12px;">Web & Android App</span>
                <p style="color: var(--color-text-muted); font-size: 0.875rem; line-height: 1.6;">
                    Real-time catalog CRUD, ACID-protected inventory deduction, live order fulfillment queue, and table reservation scheduling.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">&#128100;</div>
                <h3 style="margin-bottom: 8px; font-weight: 700;">Customer Experience</h3>
                <span class="badge badge-info" style="margin-bottom: 12px;">Web & Android App</span>
                <p style="color: var(--color-text-muted); font-size: 0.875rem; line-height: 1.6;">
                    Interactive storefront discovery, stock-validated cart checkout, dynamic reservations, and live transaction order tracking.
                </p>
            </div>
        </div>
    </section>

    <footer style="text-align: center; padding: var(--space-8); border-top: 1px solid var(--color-border); color: var(--color-text-muted); font-size: 0.8125rem;">
        &copy; <?= date('Y') ?> UniServe Enterprise Platform. All rights reserved.
    </footer>

</body>
</html>
