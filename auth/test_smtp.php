<?php
// auth/test_smtp.php
// A simple page to test the SMTP email sending functionality.

require_once '../includes/db.php'; // Assuming $pdo is initialized here
require_once '../includes/send_email.php'; // Includes the sendEmail function
require_once '../includes/functions.php'; // For display_message and sanitize_input

// Include PHPMailer's SMTP class for debugging constants
use PHPMailer\PHPMailer\SMTP;

$message = ''; // Initialize message variable for feedback
$debug_output = ''; // Initialize variable to store debug output

// Custom error handler to capture PHPMailer debug output
function capture_debug_output($str, $level) {
    global $debug_output;
    $debug_output .= $str;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = sanitize_input($_POST['to_email'] ?? '');
    $subject = sanitize_input($_POST['subject'] ?? 'Test Email from Mackenny Assessment');
    $body = sanitize_input($_POST['body'] ?? 'This is a test email sent from the Mackenny Assessment application.');

    if (empty($to_email)) {
        $message = display_message("Please enter a recipient email address.", "error");
    } else {
        // Temporarily set PHPMailer's debug output to capture_debug_output function
        // This requires modifying the sendEmail function in includes/send_email.php
        // to accept a debug output handler.
        // For now, we'll enable it directly within sendEmail and output to HTML.

        // To enable verbose debugging, you would typically modify send_email.php like this:
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        // $mail->Debugoutput = 'html'; // Or a custom function like capture_debug_output

        // For this test page, we'll make a small modification to sendEmail
        // or assume sendEmail already prints to output if debug is enabled.
        // If sendEmail is not modified to pass debug output, you will still need
        // to check your PHP error logs for the detailed Mailer Error.

        // Attempt to send the email
        // Note: The sendEmail function in includes/send_email.php would need to be
        // modified to accept a PHPMailer instance or a callback for debug output
        // if you want to capture it here. For simplicity and to avoid modifying
        // sendEmail's signature, we'll rely on its internal error logging for now
        // and guide the user to enable SMTPDebug directly in send_email.php if needed.
        if (sendEmail($to_email, $subject, $body)) {
            $message = display_message("Test email successfully sent to " . htmlspecialchars($to_email) . "!", "success");
        } else {
            // The sendEmail function already logs the specific PHPMailer error.
            // Here, we just give a generic failure message to the user.
            $message = display_message("Failed to send test email. Please check your SMTP settings and server logs for details.", "error");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test SMTP - Mackenny Assessment</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8; /* Light blue-gray background */
        }
        .bg-navy-900 { background-color: #0a1930; }
        .bg-navy-800 { background-color: #1a2b4a; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
        /* Custom styles for the message box, mimicking PHP's display_message */
        .message-box {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .message-box.error {
            background-color: #fee2e2; /* Red-100 */
            color: #ef4444; /* Red-500 */
            border: 1px solid #fca5a5; /* Red-300 */
        }
        .message-box.success {
            background-color: #dcfce7; /* Green-100 */
            color: #22c55e; /* Green-600 */
            border: 1px solid #86efac; /* Green-300 */
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="container mx-auto max-w-lg bg-white rounded-xl shadow-2xl p-8 md:p-12">
        <h1 class="text-3xl font-bold text-center mb-8 text-gray-800">Test SMTP Email Sending</h1>

        <?php echo $message; // Display any feedback messages ?>

        <form action="test_smtp.php" method="POST" class="space-y-6">
            <div>
                <label for="to_email" class="block text-gray-700 text-sm font-semibold mb-2">Recipient Email:</label>
                <input type="email" id="to_email" name="to_email" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                       placeholder="e.g., test@example.com">
            </div>
            <div>
                <label for="subject" class="block text-gray-700 text-sm font-semibold mb-2">Subject (Optional):</label>
                <input type="text" id="subject" name="subject"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                       value="Test Email from Mackenny Assessment">
            </div>
            <div>
                <label for="body" class="block text-gray-700 text-sm font-semibold mb-2">Message Body (Optional):</label>
                <textarea id="body" name="body" rows="5"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                          placeholder="This is a test email sent from the Mackenny Assessment application.">This is a test email sent from the Mackenny Assessment application.</textarea>
            </div>
            <div class="flex justify-center">
                <button type="submit"
                        class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200">
                    Send Test Email
                </button>
            </div>
        </form>

        <?php if (!empty($debug_output)): ?>
            <div class="mt-8 p-4 bg-gray-100 rounded-lg border border-gray-300 text-sm text-gray-700 overflow-x-auto">
                <h3 class="font-bold mb-2">SMTP Debug Output:</h3>
                <pre class="whitespace-pre-wrap break-words"><?php echo htmlspecialchars($debug_output); ?></pre>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
