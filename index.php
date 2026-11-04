<?php
session_start();

if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    switch ($_SESSION["role"]) {
        case "admin":
            header("Location: admin_dashboard.php");
            exit();

        case "business_owner":
            header("Location: business_dashboard.php");
            exit();

        case "customer":
            header("Location: customer_dashboard.php");
            exit();

        default:
            session_unset();
            session_destroy();
            header("Location: login.php");
            exit();
    }
}

header("Location: login.php");
exit();
?>