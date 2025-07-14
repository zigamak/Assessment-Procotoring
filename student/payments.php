<?php
// student/payment_history.php
// Displays the payment history for the logged-in student with filtering options.

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
    error_log("Payment History Quiz Fetch Error: " . $e->getMessage());
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
            q.title AS quiz_title
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
    error_log("Payment History Fetch Error: " . $e->getMessage());
    $message = display_message("Could not retrieve payment history. Please try again later.", "error");
}

?>

<div class="container mx-auto p-4 py-8 max-w-7xl">
    <h1 class="text-3xl font-bold text-accent mb-6 text-center">Your Payment History</h1>

    <?php echo $message; // Display any feedback messages ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Filter Payments</h2>
        <form action="payment_history.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="quiz_id" class="block text-sm font-medium text-gray-700 mb-1">Assessment:</label>
                <select name="quiz_id" id="quiz_id"
                        class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50 select2-enabled"
                        data-placeholder="All Assessments" data-allow-clear="true">
                    <option value=""></option>
                    <?php foreach ($all_quizzes_for_filters as $quiz_filter) : ?>
                        <option value="<?php echo htmlspecialchars($quiz_filter['quiz_id']); ?>" <?php echo ((string)$filter_quiz_id === (string)$quiz_filter['quiz_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($quiz_filter['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status:</label>
                <select name="status" id="status" class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
                    <option value="">All Statuses</option>
                    <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="failed" <?php echo ($filter_status === 'failed') ? 'selected' : ''; ?>>Failed</option>
                    <option value="abandoned" <?php echo ($filter_status === 'abandoned') ? 'selected' : ''; ?>>Abandoned</option>
                </select>
            </div>

            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"
                       class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
            </div>

            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"
                       class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
            </div>

            <div class="col-span-full flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-2 mt-4">
                <button type="submit" class="bg-accent text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-300 ease-in-out flex items-center justify-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="payment_history.php" class="bg-gray-400 text-white px-6 py-2 rounded-md hover:bg-gray-500 transition duration-300 ease-in-out flex items-center justify-center">
                    <i class="fas fa-undo mr-2"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-md overflow-x-auto"> <?php if (empty($payments)): ?>
            <p class="text-center text-gray-600 py-8">No payment records found matching your criteria.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200 text-sm"> <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th> <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment</th>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Ref.</th> <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap font-medium text-gray-900"> <?php echo htmlspecialchars($payment['payment_id']); ?>
                        </td>
                        <td class="px-4 py-2 whitespace-normal text-gray-900 max-w-xs">
                            <?php echo htmlspecialchars($payment['quiz_title']); ?>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                            ₦<?php echo number_format(htmlspecialchars($payment['amount']), 2); ?>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
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
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars(ucfirst($payment['status'])); ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                            <?php echo htmlspecialchars($payment['transaction_reference'] ?: 'N/A'); ?>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                            <?php echo format_datetime($payment['payment_date'], 'j F Y, h:i A'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>