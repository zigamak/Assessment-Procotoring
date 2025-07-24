<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/send_email.php';

// Retrieve user data from session
$username = $_SESSION['new_user_username'] ?? 'User';
$user_email = $_SESSION['new_user_email'] ?? '';

// Clear session variables after use
unset($_SESSION['new_user_username']);
unset($_SESSION['new_user_email']);

// Prepare and send email
$message = '';
if (!empty($user_email)) {
    ob_start();
    require_once '../includes/email_templates/registration_confirmation_email.php';
    $email_body = ob_get_clean();
    $email_body = str_replace('{{username}}', htmlspecialchars($username), $email_body);
    $subject = "Registration Confirmation - Mackenny Assessment";

    if (sendEmail($user_email, $subject, $email_body)) {
        error_log("Confirmation email successfully sent to $user_email (username: $username)");
        $message = "Registration successful! A confirmation email has been sent to $user_email.";
    } else {
        error_log("Failed to send confirmation email to $user_email (username: $username)");
        $message = "Registration successful, but we were unable to send a confirmation email. Please contact support.";
    }
} else {
    error_log("Registration confirmed but no email available to send confirmation to.");
    $message = "Registration successful, but no email address was provided for confirmation.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    .bg-navy-900 { background-color: #0a1930; }
    .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-50 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8 text-center">
        <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-2xl p-12">
            <div class="header">
                <img src="https://mackennytutors.com/wp-content/uploads/2025/05/Mackenny.png" alt="Mackenny Assessment Logo" style="max-width: 20%; height: auto;">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">Registration Confirmed</h1>
            </div>
            <p class="text-lg text-gray-600 mb-4">
                <?php echo htmlspecialchars($message); ?>
            </p>
            <p class="text-lg text-gray-600 mb-4">
                Your application has been submitted and will be reviewed. Please check your inbox (and spam/junk folder) for further details.
            </p>
      
        </div>
    </div>
</body>
</html>