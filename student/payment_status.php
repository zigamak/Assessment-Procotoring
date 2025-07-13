<?php
// student/payment_status.php
// Displays the result of a payment transaction.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

// Retrieve payment status details from session
$payment_status = $_SESSION['payment_status'] ?? null;

// Clear the session variable immediately after retrieving it to prevent re-display on refresh
unset($_SESSION['payment_status']);

// Default values in case session data is missing
$type = $payment_status['type'] ?? 'error';
$message = $payment_status['message'] ?? 'An unknown error occurred. Please check your dashboard.';
$quiz_title = $payment_status['quiz_title'] ?? 'Unknown Assessment';
$amount_paid = $payment_status['amount_paid'] ?? 'N/A';
$transaction_reference = $payment_status['transaction_reference'] ?? 'N/A';
$quiz_id = $payment_status['quiz_id'] ?? null;

// Determine colors and icons based on payment status type
$bg_color = '';
$text_color = '';
$border_color = '';
$icon_svg = '';
$main_heading = '';

if ($type === 'success') {
    $bg_color = 'bg-green-50';
    $text_color = 'text-green-800';
    $border_color = 'border-green-500';
    $main_heading = 'Payment Successful!';
    $icon_svg = '<svg class="w-16 h-16 text-green-500 mx-auto mb-4 animate-bounce-in" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
} elseif ($type === 'info') {
    $bg_color = 'bg-blue-50';
    $text_color = 'text-blue-800';
    $border_color = 'border-blue-500';
    $main_heading = 'Information';
    $icon_svg = '<svg class="w-16 h-16 text-blue-500 mx-auto mb-4 animate-bounce-in" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
} else { // 'error' or default
    $bg_color = 'bg-red-50';
    $text_color = 'text-red-800';
    $border_color = 'border-red-500';
    $main_heading = 'Payment Failed!';
    $icon_svg = '<svg class="w-16 h-16 text-red-500 mx-auto mb-4 animate-shake" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
}

?>

<style>
    @keyframes bounceIn {
        0% { opacity: 0; transform: scale(0.3); }
        50% { opacity: 1; transform: scale(1.05); }
        70% { transform: scale(0.9); }
        100% { transform: scale(1); }
    }
    .animate-bounce-in {
        animation: bounceIn 0.8s ease-out;
    }

    @keyframes shake {
        0% { transform: translateX(0); }
        20% { transform: translateX(-5px); }
        40% { transform: translateX(5px); }
        60% { transform: translateX(-5px); }
        80% { transform: translateX(5px); }
        100% { transform: translateX(0); }
    }
    .animate-shake {
        animation: shake 0.5s ease-in-out;
    }
</style>

<div class="container mx-auto p-4 py-8 flex items-center justify-center min-h-[calc(100vh-120px)]">
    <div class="max-w-md w-full p-8 rounded-lg shadow-xl text-center <?php echo $bg_color; ?> border-t-4 <?php echo $border_color; ?>">
        <?php echo $icon_svg; // Display the appropriate icon ?>
        <h1 class="text-3xl font-extrabold mb-4 <?php echo $text_color; ?>">
            <?php echo $main_heading; ?>
        </h1>
        <p class="text-lg mb-6 <?php echo $text_color; ?>"><?php echo htmlspecialchars($message); ?></p>

        <div class="bg-white p-6 rounded-lg shadow-inner mb-6 text-left border border-gray-200">
            <p class="text-gray-700 mb-2"><strong class="font-semibold">Assessment:</strong> <?php echo $quiz_title; ?></p>
            <?php if ($amount_paid !== 'N/A' && $amount_paid !== '₦0.00'): // Don't show amount if N/A or zero ?>
                <p class="text-gray-700 mb-2"><strong class="font-semibold">Amount:</strong> <?php echo $amount_paid; ?></p>
            <?php endif; ?>
            <p class="text-gray-700"><strong class="font-semibold">Transaction Ref:</strong> <?php echo htmlspecialchars($transaction_reference); ?></p>
        </div>

        <div class="flex flex-col space-y-3">
            <a href="<?php echo BASE_URL; ?>student/dashboard.php"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 shadow-md">
                Go to Dashboard
            </a>
            <?php if ($type === 'success' && $quiz_id): ?>
                <a href="<?php echo BASE_URL; ?>student/take_quiz.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>"
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 shadow-md">
                    Start Assessment Now
                </a>
            <?php elseif ($type === 'error' && $quiz_id): // Offer to try again only if quiz_id is available and it was an error ?>
                <a href="<?php echo BASE_URL; ?>student/make_payment.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>&amount=<?php echo htmlspecialchars(str_replace('₦', '', $amount_paid)); ?>"
                   class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 shadow-md">
                    Try Payment Again
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>