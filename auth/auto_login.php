<?php
// auth/auto_login.php
// Handles multi-use login links that expire, using auto_login_tokens table.

// Enable error reporting for debugging - REMOVE IN PRODUCTION
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // For sanitize_input(), redirect(), BASE_URL

// Set timezone to Africa/Lagos (WAT, UTC+1) for consistency
date_default_timezone_set('Africa/Lagos');

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize_input($_GET['token']);

    try {
        // Find the token in auto_login_tokens
        $stmt = $pdo->prepare("
            SELECT alt.user_id, alt.expires_at, alt.used, u.username, u.role
            FROM auto_login_tokens alt
            JOIN users u ON alt.user_id = u.user_id
            WHERE alt.token = :token
        ");
        $stmt->execute(['token' => $token]);
        $token_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token_record) {
            $current_time = new DateTime();
            $expiry_time = new DateTime($token_record['expires_at']);

            if ($current_time < $expiry_time && !$token_record['used']) {
                // Token is valid and not used - Log the user in
                $_SESSION['user_id'] = $token_record['user_id'];
                $_SESSION['username'] = $token_record['username'];
                $_SESSION['role'] = $token_record['role'];
                $_SESSION['loggedin'] = true;

                // Optionally mark token as used (uncomment if single-use tokens are desired)
                /*
                $update_stmt = $pdo->prepare("UPDATE auto_login_tokens SET used = 1 WHERE token = :token");
                $update_stmt->execute(['token' => $token]);
                */

                // Update last login timestamp
                $update_last_login_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
                $update_last_login_stmt->execute(['user_id' => $token_record['user_id']]);

                // Redirect based on role
                if ($token_record['role'] === 'admin') {
                    redirect(BASE_URL . 'admin/dashboard.php');
                } else {
                    redirect(BASE_URL . 'student/dashboard.php');
                }
                exit;
            } else {
                // Token is expired or used
                $_SESSION['form_message'] = $token_record['used'] ? "This auto-login link has already been used." : "Your auto-login link has expired. Please request a new one.";
                $_SESSION['form_message_type'] = 'error';

                // Optionally clear expired/used token
                $update_stmt = $pdo->prepare("DELETE FROM auto_login_tokens WHERE token = :token");
                $update_stmt->execute(['token' => $token]);
                redirect(BASE_URL . 'auth/login.php');
                exit;
            }
        } else {
            // No token found
            $_SESSION['form_message'] = "Invalid auto-login link. Please log in manually.";
            $_SESSION['form_message_type'] = 'error';
            redirect(BASE_URL . 'auth/login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Auto-login Database Error: " . $e->getMessage());
        $_SESSION['form_message'] = "An unexpected error occurred during auto-login. Please try again.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'auth/login.php');
        exit;
    }
} else {
    $_SESSION['form_message'] = "Invalid auto-login request: No token provided.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/login.php');
    exit;
}
?>