<?php
session_start();

if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UniServe</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <main class="container">

        <div class="brand">
            <div class="brand-icon">U</div>
            <div>
                <p class="brand-name">UniServe</p>
                <p class="brand-subtitle">
                    Unified Business and Service Management
                </p>
            </div>
        </div>

        <div class="icon-circle" aria-hidden="true">
            &#128274;
        </div>

        <h1>Forgot Password?</h1>

        <p class="description">
            Having trouble accessing your account? Follow the instructions
            below to get assistance with recovering your password.
        </p>

        <div class="notice">
            <span class="notice-icon" aria-hidden="true">&#9432;</span>
            <div>
                <strong>Administrator Assistance Required</strong>
                For security, your password will not be changed through
                this page. Please contact the UniServe system administrator.
                The administrator must verify your identity before assisting
                with password recovery.
            </div>
        </div>

        <a class="button" href="login.php">
            &larr; Back to Login
        </a>

        <p class="footer">
            UniServe &copy; <?= date("Y") ?><br>
            Unified Business and Service Management System
        </p>

    </main>
</body>
</html>