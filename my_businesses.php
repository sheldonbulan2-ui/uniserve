<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "business_owner"
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$sql = "SELECT
            b.business_id,
            b.business_name,
            b.description,
            b.address,
            b.contact_number,
            b.status,
            b.created_at,
            c.category_name
        FROM businesses b
        LEFT JOIN categories c
            ON b.category_id = c.category_id
        WHERE b.owner_id = ?
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to retrieve your businesses. Please try again later.");
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    exit("Unable to retrieve your businesses. Please try again later.");
}

$result = $stmt->get_result();
$full_name = $_SESSION["full_name"] ?? "Business Owner";
$initial = strtoupper(substr($full_name, 0, 1));

$business_count = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Businesses | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/my_businesses.css">
</head>

<body class="my-businesses-page">
<aside class="sidebar admin-sidebar">

        <div class="brand">
            <div class="brand-icon">U</div>

            <div>
                <h1>UniServe</h1>
                <p>Business &amp; Service Management</p>
            </div>
        </div>

        <h3>Business Management</h3>

        <nav aria-label="Business navigation">

            <a href="business_dashboard.php">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a href="register_business.php">
                <span class="nav-icon">＋</span>
                Register Business
            </a>

            <a class="active"href="my_businesses.php">
                <span class="nav-icon">▤</span>
                My Businesses
            </a>

            <a href="my_services.php">
                <span class="nav-icon">◇</span>
                My Services
            </a>

            <a href="business_requests.php">
                <span class="nav-icon">☷</span>
                Business Requests
            </a>

        </nav>

        <div class="sidebar-bottom">

            <h3>Account</h3>

            <nav aria-label="Account navigation">

                <a href="profile.php">
                    <span class="nav-icon">♙</span>
                    My Profile
                </a>

                <a class="logout-link" href="logout.php">
                    <span class="nav-icon">↪</span>
                    Logout
                </a>

            </nav>

        </div>

    </aside>

<div class="main-content">

    <header class="topbar">
        <div class="topbar-title">
            <span class="page-eyebrow">BUSINESS OWNER PORTAL</span>
            <h2>My Businesses</h2>
            <p>Manage your registrations and monitor their approval status.</p>
        </div>

        <div class="user-profile">
            <div class="user-avatar">
                <?= escape_html($initial) ?>
            </div>

            <div class="user-details">
                <strong><?= escape_html($full_name) ?></strong>
                <span>Business Owner</span>
            </div>
        </div>
    </header>

    <main class="content">

        <section class="page-banner">
            <div class="banner-content">
                <span class="banner-label">YOUR BUSINESS SPACE</span>
                <h1>My Businesses</h1>
                <p>
                    Keep track of your registered businesses, review their details,
                    and check their current approval status.
                </p>

            </div>

            <div class="banner-decoration" aria-hidden="true">
                <div class="decoration-circle circle-one"></div>
                <div class="decoration-circle circle-two"></div>
                <div class="decoration-icon">▣</div>
            </div>
        </section>

        <section class="section-heading">
            <div>
                <h2>Registered Businesses</h2>
                <p>Review your business information and registration status.</p>
            </div>

            <a href="register_business.php" class="button primary-button">
                <span class="button-icon">+</span>
                Register Business
            </a>
        </section>

        <div class="page-actions">
            <a href="business_dashboard.php" class="button secondary-button">
                <span class="button-icon">←</span>
                Back to Dashboard
            </a>
        </div>

        <?php if ($business_count > 0): ?>

            <div class="business-list">

                <?php while ($business = $result->fetch_assoc()): ?>

                    <?php
                    $status = strtolower((string) ($business["status"] ?? "pending"));

                    $allowed_statuses = ["pending", "approved", "rejected"];

                    if (!in_array($status, $allowed_statuses, true)) {
                        $status = "pending";
                    }

                    $status_messages = [
                        "pending" => [
                            "title" => "Awaiting Approval",
                            "message" => "Your business registration is waiting for administrator approval."
                        ],
                        "approved" => [
                            "title" => "Business Approved",
                            "message" => "Your business has been approved. You can manage its services from your dashboard."
                        ],
                        "rejected" => [
                            "title" => "Registration Rejected",
                            "message" => "Your business registration was rejected. Please contact the administrator for more information."
                        ]
                    ];
                    ?>

                    <article class="business-card">

                        <div class="business-card-header">
                            <div class="business-title-group">
                                <div class="business-icon">▣</div>

                                <div class="business-title">
                                    <h3>
                                        <?= escape_html($business["business_name"]) ?>
                                    </h3>

                                    <span class="business-category">
                                        <?= escape_html(
                                            $business["category_name"] ?: "Uncategorized"
                                        ) ?>
                                    </span>
                                </div>
                            </div>

                            <span class="status-badge status-<?= escape_html($status) ?>">
                                <span class="status-dot"></span>
                                <?= escape_html(ucfirst($status)) ?>
                            </span>
                        </div>

                        <div class="business-divider"></div>

                        <div class="business-details">

                            <div class="detail-item detail-description">
                                <span class="detail-label">DESCRIPTION</span>
                                <p>
                                    <?= nl2br(escape_html(
                                        $business["description"] ?: "No description provided."
                                    )) ?>
                                </p>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">BUSINESS ADDRESS</span>
                                <p>
                                    <?= escape_html(
                                        $business["address"] ?: "Not provided"
                                    ) ?>
                                </p>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">CONTACT NUMBER</span>
                                <p>
                                    <?= escape_html(
                                        $business["contact_number"] ?: "Not provided"
                                    ) ?>
                                </p>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">DATE REGISTERED</span>
                                <p>
                                    <?php
                                    if (!empty($business["created_at"])) {
                                        $timestamp = strtotime($business["created_at"]);

                                        echo $timestamp
                                            ? escape_html(date("F j, Y", $timestamp))
                                            : escape_html($business["created_at"]);
                                    } else {
                                        echo "Not available";
                                    }
                                    ?>
                                </p>
                            </div>

                        </div>

                        <div class="business-notice notice-<?= escape_html($status) ?>">
                            <span class="notice-icon">
                                <?php
                                if ($status === "approved") {
                                    echo "✓";
                                } elseif ($status === "rejected") {
                                    echo "!";
                                } else {
                                    echo "i";
                                }
                                ?>
                            </span>

                            <div>
                                <strong>
                                    <?= escape_html($status_messages[$status]["title"]) ?>
                                </strong>

                                <p>
                                    <?= escape_html($status_messages[$status]["message"]) ?>
                                </p>
                            </div>
                        </div>

                    </article>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <section class="empty-state">
                <div class="empty-icon">▣</div>

                <h3>No Businesses Registered Yet</h3>

                <p>
                    You haven't registered a business. Add your first business
                    to start managing your services through UniServe.
                </p>

                <a href="register_business.php" class="button primary-button">
                    <span class="button-icon">+</span>
                    Register Your First Business
                </a>
            </section>

        <?php endif; ?>

    </main>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>