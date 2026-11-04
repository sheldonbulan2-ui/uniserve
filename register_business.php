<?php
session_start();
require_once "config.php";

/* ==========================================
   ACCESS CONTROL
========================================== */

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "business_owner"
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$owner_name = $_SESSION["full_name"] ?? "Business Owner";
$initial = strtoupper(substr($owner_name, 0, 1));
$today = date("F j, Y");

/* ==========================================
   INITIALIZE VARIABLES
========================================== */

$errors = [];
$success = "";

$business_name = "";
$description = "";
$address = "";
$contact_number = "";
$category_id = "";

function escape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* ==========================================
   PROCESS BUSINESS REGISTRATION
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $business_name = trim($_POST["business_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");
    $category_id = trim((string) ($_POST["category_id"] ?? ""));

    /* Required fields */

    if ($business_name === "" || $address === "" || $contact_number === "") {
        $errors[] = "Business name, address, and contact number are required.";
    }

    /* Character limits */

    if (strlen($business_name) > 150) {
        $errors[] = "Business name must not exceed 150 characters.";
    }

    if (strlen($description) > 2000) {
        $errors[] = "Business description must not exceed 2000 characters.";
    }

    if (strlen($address) > 500) {
        $errors[] = "Business address must not exceed 500 characters.";
    }

    if (strlen($contact_number) > 30) {
        $errors[] = "Contact number must not exceed 30 characters.";
    }

    /* Validate selected category */

    $category_value = null;

    if ($category_id !== "") {
        if (!ctype_digit($category_id) || (int) $category_id < 1) {
            $errors[] = "Please select a valid category.";
        } else {
            $category_value = (int) $category_id;

            $category_stmt = $conn->prepare(
                "SELECT category_id
                 FROM categories
                 WHERE category_id = ?"
            );

            if (!$category_stmt) {
                $errors[] = "Unable to validate the category. Please try again.";
            } else {
                $category_stmt->bind_param("i", $category_value);

                if (!$category_stmt->execute()) {
                    $errors[] = "Unable to validate the category. Please try again.";
                } else {
                    $category_result = $category_stmt->get_result();

                    if ($category_result->num_rows === 0) {
                        $errors[] = "The selected category does not exist.";
                    }
                }

                $category_stmt->close();
            }
        }
    }

    /* Save registration */

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO businesses (
                owner_id,
                category_id,
                business_name,
                description,
                address,
                contact_number,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending')"
        );

        if (!$stmt) {
            $errors[] = "Unable to prepare the registration. Please try again.";
        } else {
            $stmt->bind_param(
                "iissss",
                $user_id,
                $category_value,
                $business_name,
                $description,
                $address,
                $contact_number
            );

            if ($stmt->execute()) {
                $success = "Business registration submitted successfully. Please wait for admin approval.";

                $business_name = "";
                $description = "";
                $address = "";
                $contact_number = "";
                $category_id = "";
            } else {
                $errors[] = "Unable to submit your business registration. Please try again.";
            }

            $stmt->close();
        }
    }
}

/* ==========================================
   LOAD BUSINESS CATEGORIES
========================================== */

$categories = $conn->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name ASC"
);

if (!$categories) {
    $errors[] = "Unable to load business categories. Please refresh the page.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register Business | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/register_business.css">
</head>

<body class="admin-dashboard business-dashboard register-business-page">

<div class="layout admin-layout">

    <!-- SIDEBAR -->
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

            <a class="active" href="register_business.php">
                <span class="nav-icon">＋</span>
                Register Business
            </a>

            <a href="my_businesses.php">
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
    <!-- MAIN CONTENT -->
    <main class="main-content admin-main">

        <!-- TOPBAR -->
        <header class="admin-topbar">
            <div class="topbar-heading">
                <h1>Register Business</h1>
                <p>Register a new business on UniServe</p>
            </div>

           <div class="admin-info">

                <span class="admin-avatar">
                    <?= escape($initial) ?>
                </span>

                <div>
                    <strong><?= escape($owner_name) ?></strong>
                    <p>Business Owner</p>
                </div>

            </div>
        </header>

        <section class="admin-content register-business-content">

            <!-- WELCOME SECTION -->
            <section class="welcome-section register-business-welcome">
                <div class="welcome-copy">
                    <span class="welcome-label">BUSINESS REGISTRATION</span>
                    <h2>Register Your Business</h2>
                    <p>
                        Provide your business information for administrator review.
                    </p>
                </div>

            </section>

            <!-- REGISTRATION LAYOUT -->
            <div class="register-business-layout">

                <!-- REGISTRATION FORM -->
                <section class="register-form-card">

                    <div class="register-form-heading">
                        <div class="register-heading-icon">＋</div>

                        <div>
                            <h2>Business Information</h2>
                            <p>
                                Fill in the details below to submit your registration.
                            </p>
                        </div>
                    </div>

                    <!-- ERROR MESSAGES -->
                    <?php if (!empty($errors)): ?>
                        <div class="register-alert register-alert-error" role="alert">
                            <strong>Please check the following:</strong>

                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- SUCCESS MESSAGE -->
                    <?php if ($success !== ""): ?>
                        <div class="register-alert register-alert-success" role="status">
                            <?= escape($success) ?>
                        </div>
                    <?php endif; ?>

                    <form
                        method="POST"
                        action="register_business.php"
                        class="register-business-form"
                    >

                        <div class="register-form-grid">

                            <div class="register-field register-field-full">
                                <label for="business_name">
                                    Business Name <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    id="business_name"
                                    name="business_name"
                                    maxlength="150"
                                    value="<?= escape($business_name) ?>"
                                    placeholder="Enter your business name"
                                    required
                                >
                            </div>

                            <div class="register-field register-field-full">
                                <label for="category_id">Business Category</label>

                                <select id="category_id" name="category_id">
                                    <option value="">Select a category (optional)</option>

                                    <?php if ($categories): ?>
                                        <?php while ($category = $categories->fetch_assoc()): ?>
                                            <option
                                                value="<?= (int) $category["category_id"] ?>"
                                                <?= (string) $category_id === (string) $category["category_id"] ? "selected" : "" ?>
                                            >
                                                <?= escape($category["category_name"]) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="register-field register-field-full">
                                <label for="description">Business Description</label>

                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="2000"
                                    rows="4"
                                    placeholder="Describe your business and the products or services you offer."
                                ><?= escape($description) ?></textarea>

                                <small>Maximum of 2,000 characters.</small>
                            </div>

                            <div class="register-field register-field-full">
                                <label for="address">
                                    Business Address <span>*</span>
                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    maxlength="500"
                                    rows="3"
                                    placeholder="Enter the complete business address"
                                    required
                                ><?= escape($address) ?></textarea>
                            </div>

                            <div class="register-field register-field-full">
                                <label for="contact_number">
                                    Contact Number <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    id="contact_number"
                                    name="contact_number"
                                    maxlength="30"
                                    value="<?= escape($contact_number) ?>"
                                    placeholder="Enter your contact number"
                                    required
                                >
                            </div>

                        </div>

                        <div class="register-form-note">
                            <span>*</span> Required fields
                        </div>

                        <div class="register-form-actions">
                            <a
                                href="my_businesses.php"
                                class="register-btn register-btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="register-btn register-btn-primary"
                            >
                                Submit Registration
                                <span aria-hidden="true">→</span>
                            </button>
                        </div>

                    </form>
                </section>

                <!-- INFORMATION CARD -->
                <aside class="register-info-card">

                    <div class="register-info-icon">i</div>

                    <h3>Before You Submit</h3>

                    <p>
                        Make sure your business information is accurate and complete.
                    </p>

                    <div class="register-info-item">
                        <span class="register-info-number">01</span>

                        <div>
                            <strong>Complete the form</strong>
                            <p>
                                Enter your business name, address, and contact number.
                            </p>
                        </div>
                    </div>

                    <div class="register-info-item">
                        <span class="register-info-number">02</span>

                        <div>
                            <strong>Admin review</strong>
                            <p>
                                Your submission will be marked as pending for review.
                            </p>
                        </div>
                    </div>

                    <div class="register-info-item">
                        <span class="register-info-number">03</span>

                        <div>
                            <strong>Wait for approval</strong>
                            <p>
                                Your business status will depend on the administrator's decision.
                            </p>
                        </div>
                    </div>

                    <div class="register-pending-note">
                        <span class="register-pending-dot"></span>
                        <span>New registrations start with Pending status.</span>
                    </div>

                </aside>

            </div>

            <!-- FOOTER -->
            <footer class="admin-footer">
                <span>UniServe</span>
                <span>
                    © <?= date("Y") ?> Unified Business and Service Management Application
                </span>
            </footer>

        </section>
    </main>

</div>

</body>
</html>
