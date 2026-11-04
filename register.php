<?php
session_start();
require_once "config.php";

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$full_name = "";
$email = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        $full_name === "" ||
        $email === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $error = "Please complete all fields.";
    } elseif (strlen($full_name) > 150) {
        $error = "Full name must not exceed 150 characters.";
    } elseif (strlen($email) > 255) {
        $error = "Email address must not exceed 255 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (strlen($password) > 72) {
        $error = "Password must not exceed 72 bytes.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ?"
        );

        if (!$check) {
            $error = "Unable to process registration. Please try again.";
        } else {
            $check->bind_param("s", $email);

            if (!$check->execute()) {
                $error = "Unable to process registration. Please try again.";
                $check->close();
            } else {
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = "This email address is already registered.";
                    $check->close();
                } else {
                    $check->close();

                    $role = "customer";
                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        "INSERT INTO users
                        (full_name, email, password, role)
                        VALUES (?, ?, ?, ?)"
                    );

                    if (!$stmt) {
                        $error = "Registration failed. Please try again.";
                    } else {
                        $stmt->bind_param(
                            "ssss",
                            $full_name,
                            $email,
                            $hashed_password,
                            $role
                        );

                        if ($stmt->execute()) {
                            $stmt->close();
                            $conn->close();

                            header("Location: login.php?registered=1");
                            exit();
                        } else {
                            if ($stmt->errno === 1062) {
                                $error = "This email address is already registered.";
                            } else {
                                $error = "Registration failed. Please try again.";
                            }

                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}

function escape_html($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UniServe</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container">
        <h1>Create an Account</h1>
        <p class="subtitle">Join UniServe as a customer.</p>

        <?php if ($error !== ""): ?>
            <div class="error">
                <?= escape_html($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                maxlength="150"
                value="<?= escape_html($full_name) ?>"
                autocomplete="name"
                required
            >

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="255"
                value="<?= escape_html($email) ?>"
                autocomplete="email"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <button type="submit">Create Account</button>
        </form>

        <div class="links">
            Already have an account?
            <a href="login.php">Log in here</a>
            <br>
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>