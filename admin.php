<?php
require_once 'config.php';

$full_name = 'UniServe Administrator';
$email = 'admin@uniserve.com';
$password = password_hash('Admin123!', PASSWORD_DEFAULT);
$role = 'admin';

$message = '';
$message_type = 'success';

$stmt = $conn->prepare(
    "SELECT user_id FROM users WHERE email = ?"
);

if (!$stmt) {
    die("Database query error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $message = "Admin account already exists.";
    $message_type = 'info';
    $stmt->close();
} else {
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO users (full_name, email, password, role)
         VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        die("Database query error: " . htmlspecialchars($conn->error));
    }

    $stmt->bind_param(
        "ssss",
        $full_name,
        $email,
        $password,
        $role
    );

    if ($stmt->execute()) {
        $message = "Admin account created successfully!";
        $message_type = 'success';
    } else {
        $message = "Error creating admin account.";
        $message_type = 'error';
    }

    $stmt->close();
}

$conn->close();

function escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin | UniServe</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-icon">U</div>
        <div>
            <h1>UniServe</h1>
            <p>Unified Business and Service Management</p>
        </div>
    </div>

    <div class="admin-label">Administrator Setup</div>
</header>

<main class="container">
    <div class="breadcrumb">
        UniServe / <strong>Admin Account Setup</strong>
    </div>

    <div class="page-heading">
        <h2>Admin Account Setup</h2>
        <p>Check the result of the administrator account creation process.</p>
    </div>

    <section class="card">
        <div class="status-icon">
            <?php
            if ($message_type === 'success') {
                echo '&#10003;';
            } elseif ($message_type === 'info') {
                echo '&#8505;';
            } else {
                echo '&#10007;';
            }
            ?>
        </div>

        <div class="message">
            <?php echo escape($message); ?>
        </div>

        <?php if ($message_type === 'success' || $message_type === 'info'): ?>

            <p class="description">
                <?php
                echo $message_type === 'success'
                    ? 'The administrator account setup process has completed.'
                    : 'You can use the existing administrator account if its password is still known.';
                ?>
            </p>

            <div class="account-details">
                <h3>Administrator Account Details</h3>

                <div class="detail-row">
                    <span class="detail-label">Full Name</span>
                    <span class="detail-value">
                        <?php echo escape($full_name); ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Email Address</span>
                    <span class="detail-value">
                        <?php echo escape($email); ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Role</span>
                    <span class="detail-value">Administrator</span>
                </div>
            </div>

            <div class="warning">
                <strong>Security reminder:</strong>
                This is a setup script. After confirming that the account
                has been created, delete this file from your web server
                or restrict access to it.
            </div>

        <?php else: ?>

            <p class="description">
                The account could not be created. Check your database connection
                and the users table configuration, then try again.
            </p>

        <?php endif; ?>

        <a class="back-link" href="login.php">Go to Login</a>
    </section>

    <div class="footer">
        &copy; <?php echo date('Y'); ?> UniServe. All rights reserved.
    </div>
</main>

</body>
</html>