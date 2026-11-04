<?php
session_start();
require_once __DIR__ . '/config.php';

// If an administrator is already logged in, provide a direct shortcut
$isAdmin = isset($_SESSION["user_id"], $_SESSION["role"]) && $_SESSION["role"] === "admin";

// Live stats preview from database
$totalBusinesses = 0;
$totalServices = 0;
$totalCategories = 0;

try {
    if (isset($conn) && !$conn->connect_error) {
        $resB = $conn->query("SELECT COUNT(*) AS c FROM businesses WHERE status = 'approved'");
        if ($resB) { $totalBusinesses = (int)($resB->fetch_assoc()['c'] ?? 0); $resB->free(); }

        $resS = $conn->query("SELECT COUNT(*) AS c FROM services WHERE availability = 'available'");
        if ($resS) { $totalServices = (int)($resS->fetch_assoc()['c'] ?? 0); $resS->free(); }

        $resC = $conn->query("SELECT COUNT(*) AS c FROM categories");
        if ($resC) { $totalCategories = (int)($resC->fetch_assoc()['c'] ?? 0); $resC->free(); }
    }
} catch (Throwable $e) {}

$apkFile = __DIR__ . '/downloads/uniserve-v1.0.apk';
$apkSizeMb = file_exists($apkFile) ? round(filesize($apkFile) / (1024 * 1024), 1) : 14.4;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniServe — Unified Business & Service Management Ecosystem</title>
    <link rel="stylesheet" href="assets/css/landing.css">
</head>

<body class="landing-page">
    <div class="landing-ambient" aria-hidden="true"></div>

    <div class="landing-container">

        <!-- NAVIGATION -->
        <header class="landing-nav">
            <a class="nav-brand" href="index.php">
                <div class="nav-brand-icon">U</div>
                <div class="nav-brand-title">
                    <strong>UniServe</strong>
                    <small>ECOSYSTEM PORTAL</small>
                </div>
            </a>

            <div class="nav-actions">
                <div class="status-badge">
                    <span class="status-pulse"></span>
                    <span>Cloud API & Database Active</span>
                </div>

                <?php if ($isAdmin): ?>
                    <a class="btn-nav-admin" href="admin_dashboard.php">
                        <span>Admin Dashboard →</span>
                    </a>
                <?php else: ?>
                    <a class="btn-nav-admin" href="login.php">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Admin Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- HERO SECTION -->
        <section class="landing-hero">
            <div class="hero-pill-badge">
                <span>NEW RELEASE &bull; ANDROID APK v1.0.0 NOW AVAILABLE</span>
            </div>

            <h1>
                One Platform for Services.<br>
                <span class="gradient-text">Built for Mobile & Cloud.</span>
            </h1>

            <p class="hero-subtitle">
                UniServe connects Customers and Merchants through our native Android application, backed by a real-time PostgreSQL database and executive web administrative governance.
            </p>

            <div class="hero-cta-group">
                <!-- Direct APK Download Button -->
                <a class="btn-download-apk" href="download.php">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-1.0001s.4482-1.0001.9993-1.0001c.552 0 .9997.4486.9997 1.0001s-.4477 1.0001-.9997 1.0001m-11.046 0c-.5511 0-.9993-.4486-.9993-1.0001s.4482-1.0001.9993-1.0001c.5519 0 .9997.4486.9997 1.0001s-.4478 1.0001-.9997 1.0001m11.4045-6.02l1.9973-3.4592a.416.416 0 0 0-.1521-.5676.416.416 0 0 0-.5676.1521l-2.0223 3.503C15.5902 8.414 13.8533 8.12 12 8.12s-3.5902.294-5.1368.8307L4.8409 5.4477a.416.416 0 0 0-.5676-.1521.4157.4157 0 0 0-.1521.5676l1.9973 3.4592C2.6889 11.1867.3432 14.6589 0 18.761h24c-.3432-4.1021-2.6889-7.5743-6.1185-9.4396"/>
                    </svg>
                    <span>Download Android APK</span>
                </a>

                <!-- Admin Console Link -->
                <a class="btn-admin-portal" href="login.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Admin Web Portal</span>
                </a>
            </div>

            <div class="apk-meta-bar">
                <span>Package: <strong>UniServe-v1.0.apk</strong></span>
                <span>&bull;</span>
                <span>Size: <strong><?= $apkSizeMb ?> MB</strong></span>
                <span>&bull;</span>
                <span>Android: <strong>v8.0+ (API 33+)</strong></span>
                <span>&bull;</span>
                <span>Arch: <strong>Universal ARM/x86</strong></span>
            </div>
        </section>

        <!-- WHAT CAN YOU ACCESS (ROLE ACCESS BREAKDOWN) -->
        <section class="access-guide-section" id="access-guide">
            <div class="section-header">
                <span class="section-label">PLATFORM PERMISSIONS & ROLES</span>
                <h2>What Can You Access?</h2>
                <p>
                    UniServe employs strict role-based architecture. Customers and Merchants operate on the high-performance Android App, while Platform Governance is hosted on the Web Console.
                </p>
            </div>

            <div class="access-roles-grid">

                <!-- 1. CUSTOMER -->
                <div class="role-card role-customer">
                    <div class="role-header">
                        <div class="role-icon-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="role-title-wrap">
                            <h3>Customer</h3>
                            <span class="role-platform-tag">Android App Exclusive</span>
                        </div>
                    </div>

                    <p class="role-desc">
                        Designed for individuals seeking services, booking appointments, and tracking real-time delivery from trusted merchants.
                    </p>

                    <ul class="role-features">
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Browse verified local services & storefronts</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Filter catalog by category, price, & availability</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Submit custom requests with preferred schedules</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Real-time status tracking (Pending → Completed)</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Mobile account profile & order history logs</span>
                        </li>
                    </ul>

                    <a class="role-action-btn" href="download.php">
                        <span>Get Customer App (APK)</span>
                        <span>↓</span>
                    </a>
                </div>

                <!-- 2. MERCHANT -->
                <div class="role-card role-merchant">
                    <div class="role-header">
                        <div class="role-icon-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                        </div>
                        <div class="role-title-wrap">
                            <h3>Merchant / Business</h3>
                            <span class="role-platform-tag">Android App Exclusive</span>
                        </div>
                    </div>

                    <p class="role-desc">
                        Empowers store owners and service providers to manage digital storefronts, accept bookings, and communicate order progress.
                    </p>

                    <ul class="role-features">
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Onboard and manage business storefront profiles</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>List services, set rates, and toggle availability</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Review incoming customer booking notifications</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Accept, reject, or mark orders In Progress</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Instant merchant dashboard metrics & active logs</span>
                        </li>
                    </ul>

                    <a class="role-action-btn" href="download.php">
                        <span>Get Merchant App (APK)</span>
                        <span>↓</span>
                    </a>
                </div>

                <!-- 3. ADMINISTRATOR -->
                <div class="role-card role-admin">
                    <div class="role-header">
                        <div class="role-icon-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                        </div>
                        <div class="role-title-wrap">
                            <h3>Administrator</h3>
                            <span class="role-platform-tag">Web Console Exclusive</span>
                        </div>
                    </div>

                    <p class="role-desc">
                        Strict executive portal for platform operators managing business moderation, user security, category taxonomies, and audit ledgers.
                    </p>

                    <ul class="role-features">
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Storefront verification (Approve or Reject merchants)</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>System category & marketplace taxonomy management</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Platform user auditing & role access administration</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Full financial transaction & order request oversight</span>
                        </li>
                        <li>
                            <span class="feature-check">✓</span>
                            <span>Supabase PostgreSQL analytics & health telemetry</span>
                        </li>
                    </ul>

                    <a class="role-action-btn" href="login.php">
                        <span>Enter Admin Console</span>
                        <span>→</span>
                    </a>
                </div>

            </div>
        </section>

        <!-- APK INSTALLATION GUIDE -->
        <section class="install-guide-card">
            <div class="install-header">
                <div>
                    <span class="section-label">MOBILE SETUP GUIDE</span>
                    <h3>How to Install the UniServe Android APK</h3>
                </div>
                <a class="btn-download-apk" href="download.php" style="padding: 10px 20px; font-size: 13px;">
                    <span>Download APK Now</span>
                </a>
            </div>

            <div class="install-steps-grid">
                <div class="step-card">
                    <div class="step-num">1</div>
                    <strong>Download the APK</strong>
                    <p>Tap the <strong>Download Android APK</strong> button above to save <code>UniServe-v1.0.apk</code> directly to your device.</p>
                </div>

                <div class="step-card">
                    <div class="step-num">2</div>
                    <strong>Allow Installation</strong>
                    <p>When prompted by your browser, enable <em>"Install unknown apps"</em> in your Android settings for your browser or file manager.</p>
                </div>

                <div class="step-card">
                    <div class="step-num">3</div>
                    <strong>Open and Launch</strong>
                    <p>Open the downloaded package, tap <strong>Install</strong>, and launch UniServe. Choose <strong>Customer</strong> or <strong>Merchant</strong> mode.</p>
                </div>

                <div class="step-card">
                    <div class="step-num">4</div>
                    <strong>Sign In or Register</strong>
                    <p>Create your new account in seconds or sign in with your credentials to start browsing or fulfilling orders.</p>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="landing-footer">
            <p>
                &copy; <?= date("Y") ?> UniServe Platform. All rights reserved. &bull;
                <a href="download.php">Download APK</a> &bull;
                <a href="login.php">Admin Sign In</a> &bull;
                <a href="ping.php" target="_blank">System Health</a>
            </p>
            <p style="margin-top: 8px; font-size: 11px; color: var(--text-muted);">
                Powered by Render.com Docker Web Service &bull; Supabase PostgreSQL Database &bull; Native Android Engine
            </p>
        </footer>

    </div>

<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>
