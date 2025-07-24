<?php
// student/payments.php
// Displays the Payments for the logged-in student with filtering options in a centered popup.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Contains format_datetime() and display_message()

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$payments = []; // Array to hold fetched payment records
$user_id = getUserId(); // Get the logged-in student's user_id

// Filters
$filter_quiz_id = sanitize_input($_GET['quiz_id'] ?? null);
$filter_status = sanitize_input($_GET['status'] ?? null);
$filter_start_date = sanitize_input($_GET['start_date'] ?? null);
$filter_end_date = sanitize_input($_GET['end_date'] ?? null);

// Fetch all quizzes for the filter dropdown
$all_quizzes_for_filters = [];
try {
    $stmt_quizzes = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title ASC");
    $all_quizzes_for_filters = $stmt_quizzes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Payments Quiz Fetch Error: " . $e->getMessage());
    $message = display_message("Could not fetch assessment list for filters.", "error");
}

try {
    $sql = "
        SELECT
            p.payment_id,
            p.amount,
            p.status,
            p.transaction_reference,
            p.payment_date,
            q.title AS quiz_title,
            q.description AS quiz_description
        FROM payments p
        JOIN quizzes q ON p.quiz_id = q.quiz_id
        WHERE p.user_id = :user_id
    ";

    $params = ['user_id' => $user_id];
    $where_clauses = [];

    if ($filter_quiz_id) {
        $where_clauses[] = "p.quiz_id = :quiz_id";
        $params['quiz_id'] = $filter_quiz_id;
    }
    if ($filter_status) {
        $where_clauses[] = "p.status = :status";
        $params['status'] = $filter_status;
    }
    if ($filter_start_date) {
        $where_clauses[] = "DATE(p.payment_date) >= :start_date";
        $params['start_date'] = $filter_start_date;
    }
    if ($filter_end_date) {
        $where_clauses[] = "DATE(p.payment_date) <= :end_date";
        $params['end_date'] = $filter_end_date;
    }

    if (!empty($where_clauses)) {
        $sql .= " AND " . implode(" AND ", $where_clauses);
    }

    $sql .= " ORDER BY p.payment_date DESC"; // Order by most recent payment first

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Payments Fetch Error: " . $e->getMessage());
    $message = display_message("Could not retrieve Payments. Please try again later.", "error");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Payments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/heroicons@2.1.1/dist/heroicons.js"></script>
    <style>
        /* Tooltip styles */
        .tooltip-container {
            position: relative;
            display: inline-block;
        }

        .tooltip {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 8px;
            position: absolute;
            z-index: 60; /* Higher than modals */
            bottom: 125%; /* Position above the text */
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            white-space: nowrap; /* Prevent wrapping */
        }

        .tooltip-container:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <header class="bg-white shadow-md p-4 flex items-center justify-between fixed w-full z-10 top-0">
            <div class="flex items-center">
                <h1 class="text-2xl font-bold text-indigo-600 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Your Payments
                </h1>
            </div>
            <div>
                <a href="../logout.php" class="text-indigo-600 hover:text-indigo-800 font-semibold">Logout</a>
            </div>
        </header>

        <main class="flex-1 p-4 lg:p-8 mt-16 w-full max-w-5xl mx-auto">
            <?php echo $message; // Display any feedback messages ?>

            <div class="mb-8 flex justify-end">
                <button onclick="toggleFilterModal()" class="bg-indigo-600 text-white px-6 py-2 rounded-full hover:bg-indigo-700 transition duration-300 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1m-17 4h14m-5 4h5m-9 4h9"></path>
                    </svg>
                    Filter Payments
                </button>
            </div>

            <div id="filterModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden transition-opacity duration-300">
                <div class="bg-white p-6 rounded-2xl shadow-2xl max-w-xl w-full transform transition-all duration-300 scale-100">
                    <h2 class="text-xl font-bold text-indigo-600 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1m-17 4h14m-5 4h5m-9 4h9"></path>
                        </svg>
                        Filter Payments
                    </h2>
                    <form action="payments.php" method="GET" class="grid grid-cols-1 gap-4">
                        <div>
                            <label for="quiz_id" class="block text-sm font-medium text-gray-700 mb-1">Assessment</label>
                            <select name="quiz_id" id="quiz_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-600 focus:ring focus:ring-indigo-600 focus:ring-opacity-50">
                                <option value="">All Assessments</option>
                                <?php foreach ($all_quizzes_for_filters as $quiz_filter) : ?>
                                    <option value="<?php echo htmlspecialchars($quiz_filter['quiz_id']); ?>" <?php echo ((string)$filter_quiz_id === (string)$quiz_filter['quiz_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($quiz_filter['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-600 focus:ring focus:ring-indigo-600 focus:ring-opacity-50">
                                <option value="">All Statuses</option>
                                <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="failed" <?php echo ($filter_status === 'failed') ? 'selected' : ''; ?>>Failed</option>
                                <option value="abandoned" <?php echo ($filter_status === 'abandoned') ? 'selected' : ''; ?>>Abandoned</option>
                            </select>
                        </div>
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-600 focus:ring focus:ring-indigo-600 focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-600 focus:ring focus:ring-indigo-600 focus:ring-opacity-50">
                        </div>
                        <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-4 mt-4">
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-full hover:bg-indigo-700 transition duration-300 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1m-17 4h14m-5 4h5m-9 4h9"></path>
                                </svg>
                                Apply Filters
                            </button>
                            <a href="payments.php" class="bg-gray-600 text-white px-6 py-2 rounded-full hover:bg-gray-700 transition duration-300 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Reset Filters
                            </a>
                            <button type="button" onclick="toggleFilterModal()" class="bg-gray-600 text-white px-6 py-2 rounded-full hover:bg-gray-700 transition duration-300 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-lg overflow-x-auto">
                <?php if (empty($payments)): ?>
                    <p class="text-center text-gray-600 py-8">No payment records found matching your criteria.</p>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Ref.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-gray-50 transition duration-200 cursor-pointer" onclick="showPaymentDetails(<?php echo htmlspecialchars(json_encode($payment)); ?>)">
                                <td class="px-4 py-4 whitespace-normal text-gray-900 max-w-xs"><?php echo htmlspecialchars($payment['quiz_title']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900">₦<?php echo number_format(htmlspecialchars($payment['amount']), 2); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php
                                        $status_class = '';
                                        switch ($payment['status']) {
                                            case 'completed': $status_class = 'bg-green-100 text-green-800'; break;
                                            case 'pending': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                                            case 'failed': $status_class = 'bg-red-100 text-red-800'; break;
                                            case 'abandoned': $status_class = 'bg-gray-100 text-gray-800'; break;
                                            default: $status_class = 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars(ucfirst($payment['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900 max-w-[120px] truncate tooltip-container">
                                    <div class="truncate"><?php echo htmlspecialchars($payment['transaction_reference'] ?: 'N/A'); ?></div>
                                    <?php if ($payment['transaction_reference'] && strlen($payment['transaction_reference']) > 15) : // Example: show tooltip if longer than 15 chars ?>
                                    <span class="tooltip"><?php echo htmlspecialchars($payment['transaction_reference']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900 max-w-[150px] truncate tooltip-container">
                                    <?php $formatted_date_time = format_datetime($payment['payment_date'], 'j F Y, h:i A'); ?>
                                    <div class="truncate"><?php echo htmlspecialchars($formatted_date_time); ?></div>
                                    <span class="tooltip"><?php echo htmlspecialchars($formatted_date_time); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="paymentDetailModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden transition-opacity duration-300">
        <div class="bg-white p-6 rounded-2xl shadow-2xl max-w-lg w-full transform transition-all duration-300 scale-100">
            <h2 class="text-xl font-bold text-indigo-600 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Payment Details
            </h2>
            <div id="paymentDetailsContent" class="text-gray-700">
                </div>
            <div class="mt-6 text-right">
                <button type="button" onclick="togglePaymentDetailModal()" class="bg-indigo-600 text-white px-6 py-2 rounded-full hover:bg-indigo-700 transition duration-300">
                    Close
                </button>
            </div>
        </div>
    </div>

    <?php
    // Include the student specific footer
    require_once '../includes/footer_student.php';
    ?>

    <script>
        function toggleFilterModal() {
            const modal = document.getElementById('filterModal');
            modal.classList.toggle('hidden');
            modal.classList.toggle('flex');
        }

        function togglePaymentDetailModal() {
            const modal = document.getElementById('paymentDetailModal');
            modal.classList.toggle('hidden');
            modal.classList.toggle('flex'); // Add/remove flex to re-center
        }

        function showPaymentDetails(payment) {
            const detailContent = document.getElementById('paymentDetailsContent');
            const formattedDate = new Date(payment.payment_date).toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

            let statusClass = '';
            switch (payment.status) {
                case 'completed': statusClass = 'bg-green-100 text-green-800'; break;
                case 'pending': statusClass = 'bg-yellow-100 text-yellow-800'; break;
                case 'failed': statusClass = 'bg-red-100 text-red-800'; break;
                case 'abandoned': statusClass = 'bg-gray-100 text-gray-800'; break;
                default: statusClass = 'bg-gray-100 text-gray-800'; break;
            }

            detailContent.innerHTML = `
                <div class="grid grid-cols-2 gap-y-3 gap-x-4">
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-sm font-medium text-gray-500">Payment ID:</p>
                        <p class="text-base font-semibold text-gray-900">${payment.payment_id}</p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-sm font-medium text-gray-500">Assessment:</p>
                        <p class="text-base font-semibold text-gray-900">${payment.quiz_title}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm font-medium text-gray-500">Assessment Description:</p>
                        <p class="text-base text-gray-900">${payment.quiz_description || 'N/A'}</p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-sm font-medium text-gray-500">Amount:</p>
                        <p class="text-base font-semibold text-gray-900">₦${parseFloat(payment.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-sm font-medium text-gray-500">Status:</p>
                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                            ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                        </span>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-sm font-medium text-gray-500">Transaction Reference:</p>
                        <p class="text-base text-gray-900">${payment.transaction_reference || 'N/A'}</p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-sm font-medium text-gray-500">Payment Date:</p>
                        <p class="text-base text-gray-900">${formattedDate}</p>
                    </div>
                </div>
            `;
            togglePaymentDetailModal();
        }
    </script>
</body>
</html>