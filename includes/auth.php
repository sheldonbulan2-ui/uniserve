<?php
/**
 * Authentication and Session Helpers
 */

if (!function_exists('require_auth')) {
    function require_auth($allowedRoles = []) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
            header('Location: login.php');
            exit();
        }

        if (!empty($allowedRoles) && !in_array($_SESSION['role'], (array)$allowedRoles, true)) {
            header('Location: index.php');
            exit();
        }
    }
}
