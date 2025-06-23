<?php
// auth/forgot_password.php
// Handles forgot password requests: email input, token generation, and sending reset link.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// PHPMailer Library (download and place in 'includes' or use Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Include PHPMailer autoloader if not using Composer, or ensure Composer autoloader is included
// If using Composer:
// require_once '../vendor/autoload.php';
// If manually downloading and placing in 'includes/phpmailer/':
require '../includes/phpmailer/src/Exception.php';
require '../includes/phpmailer/src/PHPMailer.php';
require '../includes/phpmailer/src/SMTP.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = display_message("Please enter a valid email address.", "error");
    } else {
        try {
            // Check if the email exists in the database
            $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $user_id = $user['user_id'];
                $username = $user['username'];

                // Generate a unique token
                $token = bin2hex(random_bytes(32)); // 64-character hex string
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

                // Store the token in the database
                // First, delete any old tokens for this user
                $stmt_delete = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
                $stmt_delete->execute(['user_id' => $user_id]);

                // Insert the new token
                $stmt_insert = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
                $stmt_insert->execute([
                    'user_id' => $user_id,
                    'token' => $token,
                    'expires_at' => $expires
                ]);

                // Construct the reset link
                $reset_link = BASE_URL . 'auth/reset_password.php?token=' . $token;

                // Send the email using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    // Server settings (GET THESE FROM YOUR SMTP PROVIDER)
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;           // Specify main and backup SMTP servers
                    $mail->SMTPAuth   = true;                // Enable SMTP authentication
                    $mail->Username   = SMTP_USERNAME;       // SMTP username
                    $mail->Password   = SMTP_PASSWORD;       // SMTP password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable TLS encryption, `PHPMailer::ENCRYPTION_SMTPS` for port 465, `PHPMailer::ENCRYPTION_STARTTLS` for port 587
                    $mail->Port       = SMTP_PORT;           // TCP port to connect to

                    // Recipients
                    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                    $mail->addAddress($email, $username);     // Add a recipient

                    // Content
                    $mail->isHTML(true);                      // Set email format to HTML
                    $mail->Subject = 'Password Reset Request';
                    $mail->Body    = '
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Password Reset Request</title>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { width: 80%; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
                                .header { background-color: #1e4b31; color: #ffffff; padding: 10px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                                .content { padding: 20px; }
                                .button { display: inline-block; padding: 10px 20px; margin-top: 20px; background-color: #28a745; color: #ffffff; text-decoration: none; border-radius: 5px; }
                                .footer { margin-top: 30px; font-size: 0.9em; color: #777; text-align: center; }
                            </style>
                        </head>
                        <body>
                            <div class="container">
                                <div class="header">
                                    <h2>Password Reset Request</h2>
                                </div>
                                <div class="content">
                                    <p>Dear ' . htmlspecialchars($username) . ',</p>
                                    <p>You have requested to reset your password for your account.</p>
                                    <p>Please click the following link to reset your password:</p>
                                    <p><a href="' . htmlspecialchars($reset_link) . '" class="button">Reset Your Password</a></p>
                                    <p>This link will expire in 1 hour.</p>
                                    <p>If you did not request a password reset, please ignore this email.</p>
                                    <p>Thank you,</p>
                                    <p>The ' . SITE_NAME . ' Team</p>
                                </div>
                                <div class="footer">
                                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                                </div>
                            </div>
                        </body>
                        </html>';

                    $mail->send();
                    $message = display_message("A password reset link has been sent to your email address. Please check your inbox (and spam folder).", "success");
                } catch (Exception $e) {
                    error_log("PHPMailer Error: " . $mail->ErrorInfo);
                    $message = display_message("Could not send the password reset email. Mailer Error: {$mail->ErrorInfo}", "error");
                }

            } else {
                $message = display_message("No account found with that email address.", "error");
            }
        } catch (PDOException $e) {
            error_log("Forgot Password Database Error: " . $e->getMessage());
            $message = display_message("An unexpected error occurred. Please try again later.", "error");
        }
    }
}

require_once '../includes/header_public.php';
?>

<div class="container mx-auto p-4 py-8">
    <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">Forgot Your Password?</h1>

        <?php echo $message; ?>

        <p class="text-gray-700 mb-4 text-center">Enter your email address and we'll send you a link to reset your password.</p>

        <form action="forgot_password.php" method="POST" class="space-y-4">
            <div>
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address:</label>
                <input type="email" id="email" name="email" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Send Reset Link
                </button>
                <a href="login.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    Back to Login
                </a>
            </div>
        </form>
    </div>
</div>

<?php
require_once '../includes/footer_public.php';
?>