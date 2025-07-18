<?php
// auth/auto_login.php
// Handles one-time login links for welcome emails.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // For redirect()

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize_input($_GET['token']);

    try {
        // Find the user with the given token that hasn't expired
        $stmt = $pdo->prepare("SELECT user_id, username, role, auto_login_token_expiry FROM users WHERE auto_login_token = :token AND auto_login_token_expiry > NOW()");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if the token has expired (double-check, though SQL should handle most)
            if (strtotime($user['auto_login_token_expiry']) > time()) {
                // Log the user in
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Invalidate the one-time token immediately after use
                $update_stmt = $pdo->prepare("UPDATE users SET auto_login_token = NULL, auto_login_token_expiry = NULL WHERE user_id = :user_id");
                $update_stmt->execute(['user_id' => $user['user_id']]);

                // Redirect to their dashboard
                redirect('../student/dashboard.php'); // Or based on role: redirect(getUserDashboardLink($user['role']));

            } else {
                $_SESSION['form_message'] = "Your auto-login link has expired. Please log in manually.";
                $_SESSION['form_message_type'] = 'error';
                redirect('login.php');
            }
        } else {
            $_SESSION['form_message'] = "Invalid or expired auto-login link. Please log in manually.";
            $_SESSION['form_message_type'] = 'error';
            redirect('login.php');
        }
    } catch (PDOException $e) {
        error_log("Auto-login Error: " . $e->getMessage());
        $_SESSION['form_message'] = "An unexpected error occurred. Please try again.";
        $_SESSION['form_message_type'] = 'error';
        redirect('login.php');
    }
} else {
    $_SESSION['form_message'] = "Invalid auto-login request.";
    $_SESSION['form_message_type'] = 'error';
    redirect('login.php');
}
exit;
?>