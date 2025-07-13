<?php
// student/make_payment.php
// Handles initiating the Paystack payment process for assessments.

require_once '../includes/session.php';
require_once '../includes/db.php'; // Contains Paystack keys now
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$quiz_id = $_GET['quiz_id'] ?? null;
$amount_from_url = $_GET['amount'] ?? null;
$user_id = getUserId(); // Get the logged-in student's user_id
$user_email = getUserEmail(); // Assuming you have a function to get user email

$quiz_title = '';
$assessment_fee = 0;
$payment_already_made = false; // Flag to indicate if payment was already made for this quiz
$transaction_reference = ''; // Initialize here, will be generated conditionally

// Redirect if quiz_id or amount is missing or invalid
if (!$quiz_id || !is_numeric($quiz_id) || !$amount_from_url || !is_numeric($amount_from_url)) {
    $message = display_message("Invalid assessment details provided. Please return to the dashboard.", "error");
    // Consider adding a redirect to dashboard after a delay here.
} else {
    try {
        // Fetch quiz details to verify the amount and get the title
        $stmt = $pdo->prepare("SELECT title, assessment_fee FROM quizzes WHERE quiz_id = :quiz_id");
        $stmt->execute(['quiz_id' => $quiz_id]);
        $quiz_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz_data) {
            $message = display_message("Assessment not found. Please try again.", "error");
            $quiz_id = null; // Invalidate quiz_id to prevent further processing
        } else {
            $quiz_title = htmlspecialchars($quiz_data['title']);
            $assessment_fee = (float)$quiz_data['assessment_fee']; // Amount in base currency (e.g., NGN)

            // Basic validation: Check if the amount from URL matches the database amount
            // Use a small epsilon for float comparison to account for precision issues
            if (abs($assessment_fee - (float)$amount_from_url) > 0.01) {
                $message = display_message("Mismatch in assessment fee. Please try again from the dashboard.", "error");
                $quiz_id = null; // Invalidate quiz_id to prevent payment initiation
            } else {
                // Check if payment has already been completed for this quiz by this user
                $stmt_check_payment = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = :user_id AND quiz_id = :quiz_id AND status = 'completed'");
                $stmt_check_payment->execute(['user_id' => $user_id, 'quiz_id' => $quiz_id]);
                if ($stmt_check_payment->fetchColumn() > 0) {
                    $message = display_message("You have already paid for this assessment. You can now access it from your dashboard.", "info");
                    $payment_already_made = true;
                }
            }
        }

    } catch (PDOException $e) {
        error_log("Make Payment Data Fetch Error: " . $e->getMessage());
        $message = display_message("Could not retrieve assessment details. Please try again later.", "error");
        $quiz_id = null; // Invalidate quiz_id
    }
}

// Ensure the user's email is available for Paystack
if (empty($user_email) && $user_id) { // Only try to fetch if user_id exists and email is empty
    try {
        $stmt_email = $pdo->prepare("SELECT email FROM users WHERE user_id = :user_id");
        $stmt_email->execute(['user_id' => $user_id]);
        $fetched_email = $stmt_email->fetchColumn();
        if ($fetched_email) {
            $user_email = $fetched_email;
        } else {
            $message = display_message("Your email address is missing. Please update your profile.", "error");
            $quiz_id = null; // Prevent payment initiation
        }
    } catch (PDOException $e) {
        error_log("Fetch User Email Error: " . $e->getMessage());
        $message = display_message("Could not retrieve user email. Please try again later.", "error");
        $quiz_id = null; // Prevent payment initiation
    }
}

// Only proceed to generate reference and record pending if conditions are met
if ($quiz_id && !$payment_already_made && $assessment_fee > 0 && !empty($user_email)) {
    // Generate a NEW unique transaction reference for this attempt
    $transaction_reference = 'QZPAY_' . time() . '_' . $user_id . '_' . $quiz_id . '_' . bin2hex(random_bytes(4));

    try {
        // Check if there's an existing PENDING payment for this user and quiz
        $stmt_check_pending = $pdo->prepare("SELECT transaction_reference FROM payments WHERE user_id = :user_id AND quiz_id = :quiz_id AND status = 'pending' LIMIT 1");
        $stmt_check_pending->execute(['user_id' => $user_id, 'quiz_id' => $quiz_id]);
        $existing_pending_ref = $stmt_check_pending->fetchColumn();

        if ($existing_pending_ref) {
            // Update the existing pending record with the new transaction reference
            $stmt_update_pending = $pdo->prepare("
                UPDATE payments
                SET transaction_reference = :new_transaction_reference, amount = :amount, payment_date = CURRENT_TIMESTAMP
                WHERE user_id = :user_id AND quiz_id = :quiz_id AND status = 'pending'
            ");
            $stmt_update_pending->execute([
                'new_transaction_reference' => $transaction_reference,
                'amount' => $assessment_fee,
                'user_id' => $user_id,
                'quiz_id' => $quiz_id
            ]);
        } else {
            // Insert a new pending payment record
            $stmt_insert_pending = $pdo->prepare("
                INSERT INTO payments (user_id, quiz_id, amount, status, transaction_reference)
                VALUES (:user_id, :quiz_id, :amount, 'pending', :transaction_reference)
            ");
            $stmt_insert_pending->execute([
                'user_id' => $user_id,
                'quiz_id' => $quiz_id,
                'amount' => $assessment_fee,
                'transaction_reference' => $transaction_reference
            ]);
        }

    } catch (PDOException $e) {
        error_log("Pending Payment Record Error: " . $e->getMessage());
        $message = display_message("Could not prepare payment record. Please try again.", "error");
        $quiz_id = null; // Prevent payment initiation if we can't record pending state
    }
}

?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Make Payment</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if ($quiz_id && !$payment_already_made && $assessment_fee > 0 && !empty($transaction_reference)): // Added !empty($transaction_reference) ?>
        <div class="bg-white p-6 rounded-lg shadow-md max-w-md mx-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Payment for: <?php echo $quiz_title; ?></h2>
            <p class="text-gray-700 text-lg mb-4">
                Amount Due: <span class="font-bold text-green-600">₦<?php echo number_format($assessment_fee, 2); ?></span>
            </p>
            <p class="text-gray-600 mb-6">
                Click the button below to complete your payment securely via Paystack.
            </p>

            <button id="paystack-button"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md transition duration-300 ease-in-out transform hover:scale-105 shadow-lg">
                Pay Now with Paystack (₦<?php echo number_format($assessment_fee, 2); ?>)
            </button>

            <div class="mt-4 text-center">
                <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="text-gray-600 hover:text-gray-800 text-sm">
                    Cancel and Go Back to Dashboard
                </a>
            </div>
        </div>

    <?php elseif ($payment_already_made): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md max-w-md mx-auto" role="alert">
            <p class="font-bold">Payment Complete!</p>
            <p>You have already paid for "<?php echo $quiz_title; ?>". You can now take the assessment.</p>
            <div class="mt-4 text-center">
                <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Go to Dashboard
                </a>
                <?php if ($quiz_id): // Ensure quiz_id is still valid before offering to start quiz ?>
                <a href="<?php echo BASE_URL; ?>student/take_quiz.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>"
                   class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300 ml-2">
                    Start Assessment
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($assessment_fee == 0 && $quiz_id): // For free assessments linked here somehow ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg shadow-md max-w-md mx-auto" role="alert">
            <p class="font-bold">No Payment Required!</p>
            <p>This assessment ("<?php echo $quiz_title; ?>") is free. You can proceed to take it directly.</p>
            <div class="mt-4 text-center">
                <a href="<?php echo BASE_URL; ?>student/take_quiz.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>"
                   class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Start Assessment
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>

<?php if ($quiz_id && !$payment_already_made && $assessment_fee > 0 && !empty(PAYSTACK_PUBLIC_KEY) && !empty($transaction_reference)): ?>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const paystackButton = document.getElementById('paystack-button');
        if (paystackButton) {
            paystackButton.addEventListener('click', function(e) {
                e.preventDefault();

                let handler = PaystackPop.setup({
                    key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>', // Replace with your public key
                    email: '<?php echo htmlspecialchars($user_email); ?>', // Customer's email address
                    amount: <?php echo $assessment_fee * 100; ?>, // Amount in kobo (or cents), required by Paystack
                    ref: '<?php echo $transaction_reference; ?>', // Unique transaction reference (generated above)
                    currency: "NGN", // Currency
                    callback: function(response) {
                        // This callback is fired after the transaction is completed.
                        // It does NOT mean the transaction was successful; it just means it's complete.
                        // You MUST verify on your server!

                        // Redirect to a server-side script for verification
                        window.location.href = '<?php echo BASE_URL; ?>student/paystack_callback.php?reference=' + response.reference + '&quiz_id=<?php echo htmlspecialchars($quiz_id); ?>';
                    },
                    onClose: function() {
                        // Called when the user closes the modal
                        alert('Payment window closed. You can try again or cancel.');
                    }
                });

                handler.openIframe(); // Open the Paystack modal
            });
        }
    });
</script>
<?php endif; ?>