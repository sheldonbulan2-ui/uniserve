<?php
session_start();

if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    if ($_SESSION["role"] === "admin") {
        header("Location: admin_dashboard.php");
        exit();
    } else {
        // Customer or Business Owner accounts belong on the mobile app
        session_unset();
        session_destroy();
        header("Location: login.php?msg=mobile_only");
        exit();
    }
}

header("Location: login.php");
exit();
