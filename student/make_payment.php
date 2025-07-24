<?php
// student/make_payment.php
// Handles initiating the Paystack payment process for assessments by redirecting to a central payment handler.

require_once '../includes/session.php';
require_once '../includes/db.php'; // Contains Paystack keys and BASE_URL
require_once '../includes/functions.php'; // For display_message, sanitize_input, etc.

// Ensure only logged-in students can access this page
// Assuming enforceRole is defined and works as expected
enforceRole('student', BASE_URL . 'auth/login.php');

// Include the student specific header.
require_once '../includes/header_student.php';

// Initialize message variable for feedback
$message = '';
$redirect_to_payment_page = false; // Flag to control redirection

$quiz_id = sanitize_input($_GET['quiz_id'] ?? null);
$amount_from_url = sanitize_input($_GET['amount'] ?? null);
$user_id = getUserId(); // Get the logged-in student's user_id from session.php

// Fetch user details once to get email, first_name, last_name
$user_details = fetchUserDetails($pdo, $user_id); // fetchUserDetails is in functions.php

$user_email = $user_details['email'] ?? '';
$user_first_name = $user_details['first_name'] ?? '';
$user_last_name = $user_details['last_name'] ?? '';

// Validate essential parameters and user details
if (empty($quiz_id) || empty($amount_from_url) || empty($user_id) || empty($user_email)) {
    $message = display_message("Invalid assessment details or missing user information. Please return to the dashboard.", "error");
} else {
    try {
        // Fetch quiz details to verify the amount and get the title
        $stmt = $pdo->prepare("SELECT title, assessment_fee FROM quizzes WHERE quiz_id = :quiz_id");
        $stmt->execute(['quiz_id' => $quiz_id]);
        $quiz_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz_data) {
            $message = display_message("Assessment not found. Please try again.", "error");
        } else {
            $quiz_title = htmlspecialchars($quiz_data['title']);
            $assessment_fee = (float)$quiz_data['assessment_fee'];

            // Basic validation: Check if the amount from URL matches the database amount
            // Use a small epsilon for float comparison to account for precision issues
            if (abs($assessment_fee - (float)$amount_from_url) > 0.01) {
                $message = display_message("Mismatch in assessment fee. Please try again from the dashboard.", "error");
            } else {
                // Check if payment has already been completed for this quiz by this user
                $stmt_check_payment = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = :user_id AND quiz_id = :quiz_id AND status = 'completed'");
                $stmt_check_payment->execute(['user_id' => $user_id, 'quiz_id' => $quiz_id]);
                if ($stmt_check_payment->fetchColumn() > 0) {
                    $message = display_message("You have already paid for this assessment. You can now access it from your dashboard.", "info");
                    // Optionally, redirect to the dashboard or take_quiz page directly if payment is already made
                    // redirect(BASE_URL . 'student/dashboard.php');
                    // exit;
                } else if ($assessment_fee == 0) {
                     // If the assessment is free, directly redirect to take quiz or mark as paid
                    $message = display_message("This assessment ('" . $quiz_title . "') is free. You can proceed directly.", "info");
                    // Optionally, you might want to record a 'free' or 'completed' payment status here if needed for tracking
                    // For example: insert into payments (user_id, quiz_id, amount, status, transaction_reference) values (...) 'free', 'N/A'
                    // redirect(BASE_URL . 'student/take_quiz.php?quiz_id=' . $quiz_id);
                    // exit;
                } else {
                    // All checks passed, set flag to redirect to auth/payment.php
                    $redirect_to_payment_page = true;
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Make Payment Data Fetch Error: " . $e->getMessage());
        $message = display_message("Could not retrieve assessment details. Please try again later.", "error");
    }
}

// If all checks pass, redirect to auth/payment.php
if ($redirect_to_payment_page) {
    $payment_url = BASE_URL . 'auth/payment.php?' . http_build_query([
        'quiz_id' => $quiz_id,
        'amount' => $assessment_fee, // Use the verified amount from DB
        'user_id' => $user_id,
        'email' => $user_email,
        'first_name' => $user_first_name,
        'last_name' => $user_last_name
    ]);
    redirect($payment_url);
    exit;
}

// If not redirected, display feedback message
?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Make Payment</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if (!$redirect_to_payment_page): ?>
        <div class="bg-white p-6 rounded-lg shadow-md max-w-md mx-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Payment Status</h2>
            <p class="text-gray-600 mb-6">
                <?php if (strpos($message, "already paid") !== false): ?>
                    You have already paid for this assessment.
                <?php elseif (strpos($message, "is free") !== false): ?>
                    This assessment is free.
                <?php else: ?>
                    There was an issue processing your request or payment is not required.
                <?php endif; ?>
            </p>
            <div class="mt-4 text-center">
                <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Go to Dashboard
                </a>
                <?php if (strpos($message, "already paid") !== false || strpos($message, "is free") !== false): ?>
                    <?php if ($quiz_id): ?>
                        <a href="<?php echo BASE_URL; ?>student/take_quiz.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>"
                           class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition duration-300 ml-2">
                            Start Assessment
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>