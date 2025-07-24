<?php
// includes/send_registration_email.php
// This script is called in the background to send registration confirmation emails.

// Ensure this script is not directly accessible via web
// This check helps prevent direct browser access to background scripts
if (php_sapi_name() !== 'cli' && !isset($_GET['background_task_trigger'])) {
    die('Access Denied');
}

require_once __DIR__ . '/db.php'; // Adjust path as necessary
require_once __DIR__ . '/functions.php'; // Adjust path as necessary
require_once __DIR__ . '/send_email.php'; // Contains the sendEmail() function

// Retrieve the email address passed as a command-line argument
// Expecting email as the first argument (index 1 because index 0 is script name)
$user_email_from_args = $_SERVER['argv'][1] ?? '';

if (empty($user_email_from_args)) {
    error_log("send_registration_email.php: Missing email argument. Cannot send email.");
    exit;
}

error_log("send_registration_email.php: Script started for email: " . $user_email_from_args);

// Fetch all user details from the database using the email
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $user_email_from_args]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        error_log("send_registration_email.php: User with email '{$user_email_from_args}' not found in DB. Cannot send email.");
        exit;
    }

    error_log("send_registration_email.php: User data fetched successfully for " . $user_data['email']);

    // Define all variables expected by the email template from the fetched user data
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $username = $user_data['username'];
    $email = $user_data['email']; // Use the email fetched from DB for sending
    $city = $user_data['city'];
    $state = $user_data['state'];
    $country = $user_data['country'];
    $school_name = $user_data['school_name'];
    $date_of_birth = $user_data['date_of_birth'];
    $grade = $user_data['grade'];
    $address = $user_data['address'];
    $gender = $user_data['gender'];

} catch (PDOException $e) {
    error_log("send_registration_email.php: Database error fetching user data for email '{$user_email_from_args}': " . $e->getMessage());
    exit;
}

// Start output buffering to capture the email template's content
ob_start();
// The template will now have access to all the variables defined above
require __DIR__ . '/email_templates/registration_confirmation_email.php';
$email_body = ob_get_clean();

if (empty($email_body)) {
    error_log("send_registration_email.php: Email body is empty after including template. Check template file and its includes (header.php, footer.php) for errors.");
    exit;
} else {
    error_log("send_registration_email.php: Email body generated, length: " . strlen($email_body) . " bytes.");
}

$subject = "Registration Confirmation - Mackenny Assessment";

// Call the sendEmail function from send_email.php
if (sendEmail($email, $subject, $email_body)) {
    error_log("Confirmation email successfully sent to {$email} (username: {$username})");
} else {
    error_log("Failed to send confirmation email to {$email} (username: {$username}) - sendEmail function returned false.");
}

?>