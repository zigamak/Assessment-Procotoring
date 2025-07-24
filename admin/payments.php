<?php
// admin/payments.php
// Page to view and filter all payment records for administrators.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Contains format_datetime() and display_message()

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$payments = []; // Array to hold fetched payment records

// Pagination settings
$records_per_page = 10; // Number of payments to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Filters
$filter_user_id = sanitize_input($_GET['user_id'] ?? null);
$filter_quiz_id = sanitize_input($_GET['quiz_id'] ?? null);
$filter_status = sanitize_input($_GET['status'] ?? null);
$filter_start_date = sanitize_input($_GET['start_date'] ?? null);
$filter_end_date = sanitize_input($_GET['end_date'] ?? null);

// Fetch all users for the filter dropdown
$all_users_for_filters = [];
try {
    $stmt_users = $pdo->query("SELECT user_id, username FROM users ORDER BY username ASC");
    $all_users_for_filters = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Payments Page User Fetch Error: " . $e->getMessage());
    $message = display_message("Could not fetch user list for filters.", "error");
}

// Fetch all quizzes for the filter dropdown
$all_quizzes_for_filters = [];
try {
    $stmt_quizzes = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title ASC");
    $all_quizzes_for_filters = $stmt_quizzes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Payments Page Quiz Fetch Error: " . $e->getMessage());
    $message = display_message("Could not fetch assessment list for filters.", "error");
}

$total_payments = 0;
try {
    $sql_count = "
        SELECT COUNT(*)
        FROM payments p
        JOIN users u ON p.user_id = u.user_id
        JOIN quizzes q ON p.quiz_id = q.quiz_id
        WHERE 1=1
    ";

    $sql_data = "
        SELECT
            p.payment_id,
            p.amount,
            p.status,
            p.transaction_reference,
            p.payment_date,
            u.username AS student_username,
            q.title AS quiz_title
        FROM payments p
        JOIN users u ON p.user_id = u.user_id
        JOIN quizzes q ON p.quiz_id = q.quiz_id
        WHERE 1=1
    ";

    $params = [];
    $where_clauses = [];

    if ($filter_user_id) {
        $where_clauses[] = "p.user_id = :user_id";
        $params['user_id'] = $filter_user_id;
    }
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
        $sql_count .= " AND " . implode(" AND ", $where_clauses);
        $sql_data .= " AND " . implode(" AND ", $where_clauses);
    }

    // Get total count
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_payments = $stmt_count->fetchColumn();
    $total_pages = ceil($total_payments / $records_per_page);

    // Fetch data with pagination
    $sql_data .= " ORDER BY p.payment_date DESC LIMIT :limit OFFSET :offset";
    $params['limit'] = $records_per_page;
    $params['offset'] = $offset;

    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Payments History Fetch Error: " . $e->getMessage());
    $message = display_message("Could not retrieve Payments. Please try again later.", "error");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* Custom styles for the sidebar and Select2 overrides */

        /* Sidebar transition and initial hidden state */
        .sidebar-filter {
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
            z-index: 1000; /* Ensure it's above other content */
        }
        .sidebar-filter.open {
            transform: translateX(0);
        }

        /* Overlay transition */
        .overlay {
            transition: opacity 0.3s ease-out;
            z-index: 999; /* Below sidebar, above content */
        }

        /* Select2 custom styling to match Tailwind forms */
        .select2-container--default .select2-selection--single {
            border: 1px solid #d1d5db; /* Tailwind's border-gray-300 */
            border-radius: 0.375rem; /* Tailwind's rounded-md */
            height: 2.5rem; /* Equivalent to input h-10 or py-2 */
            display: flex; /* For proper alignment of text and arrow */
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1f2937; /* Default text-gray-900 */
            padding-left: 0.75rem; /* px-3 */
            line-height: 1.5; /* Match default input line-height */
            flex-grow: 1; /* Allow text to take available space */
            white-space: normal; /* Allow text wrapping */
            overflow-wrap: break-word; /* Ensure long words break */
            display: block; /* Important for white-space to work reliably */
        }

        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #6b7280; /* Tailwind's text-gray-500 */
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px; /* Adjust arrow width */
            padding-right: 0.5rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__clear {
            position: absolute; /* Position clear button */
            right: 2rem; /* Adjust as needed to not overlap arrow */
            top: 50%;
            transform: translateY(-50%);
            padding: 0 0.5rem;
            font-size: 1.25rem; /* text-xl */
            color: #9ca3af; /* text-gray-400 */
        }

        /* Focus styles for Select2 */
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #3b82f6; /* Assuming accent is a blue */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5); /* focus:ring-accent */
        }

        /* Dropdown specific styles */
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: #3b82f6; /* bg-accent */
            color: white;
        }

        .select2-container--default .select2-results__option--selected {
            background-color: #e0f2fe; /* bg-blue-50 */
            color: #1f2937;
        }

        /* Ensure Select2 dropdown width is dynamic */
        .select2-container {
            width: 100% !important; /* Important for fitting into grid/flex parents */
        }
        .select2-container--open .select2-dropdown {
            min-width: 250px; /* Ensure dropdown is not too narrow */
            width: auto !important; /* Allow it to expand to content */
        }

        /* Responsive table scrolling */
        .overflow-x-auto table {
            min-width: 768px; /* Ensures table doesn't shrink too much on small screens */
        }

        /* Customize scrollbar for aesthetics (optional) */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Custom tooltip styles */
        .tooltip-container {
            position: relative;
            display: inline-block;
        }

        .tooltip-content {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%; /* Position the tooltip above the text */
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            white-space: nowrap; /* Prevent text wrapping */
            font-size: 0.875rem; /* text-sm */
        }

        .tooltip-container:hover .tooltip-content {
            visibility: visible;
            opacity: 1;
        }

        .tooltip-content::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        /* Popover styles */
        .popover {
            position: absolute;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 0.375rem;
            padding: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10;
            max-width: 300px;
            word-wrap: break-word;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .popover-close {
            float: right;
            cursor: pointer;
            font-weight: bold;
            color: #999;
            margin-left: 10px;
        }
        .popover-arrow {
            position: absolute;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid #ccc;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
        }
        .popover-arrow::after {
            content: '';
            position: absolute;
            width: 0;
            height: 0;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 7px solid #fff;
            bottom: 1px;
            left: -7px;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <?php // require_once '../includes/header_admin.php'; // Included at the top ?>

    <div class="container mx-auto p-4 py-8 max-w-7xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Manage Payments</h1>

        <?php echo $message; // Display any feedback messages ?>

        <div class="mb-6 flex justify-end">
            <button id="openFilterSidebar" class="bg-blue-600 text-white px-6 py-3 rounded-lg shadow-md hover:bg-blue-700 transition duration-300 ease-in-out flex items-center space-x-2">
                <i class="fas fa-filter text-lg"></i>
                <span class="text-lg font-semibold">Filter Payments</span>
            </button>
        </div>

        <div class="bg-white p-4 rounded-lg shadow-md overflow-hidden">
            <?php if (empty($payments)): ?>
                <p class="text-center text-gray-600 py-8 text-lg">No payment records found matching your criteria.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Ref.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                                    <?php echo htmlspecialchars($payment['student_username']); ?>
                                </td>
                                <td class="px-4 py-2 whitespace-normal text-gray-900 max-w-xs break-words">
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
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars(ucfirst($payment['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-blue-600 hover:underline cursor-pointer transaction-ref-cell"
                                    data-full-ref="<?php echo htmlspecialchars($payment['transaction_reference']); ?>">
                                    <div class="tooltip-container">
                                        <?php
                                            $ref = htmlspecialchars($payment['transaction_reference'] ?: 'N/A');
                                            echo (strlen($ref) > 15) ? substr($ref, 0, 12) . '...' : $ref;
                                        ?>
                                        <?php if (strlen($ref) > 15): ?>
                                            <span class="tooltip-content"><?php echo $ref; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                                    <?php echo format_datetime($payment['payment_date'], 'j F Y, h:i A'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="mt-6 flex justify-center" aria-label="Pagination">
                        <ul class="flex items-center space-x-2">
                            <?php if ($current_page > 1): ?>
                                <li>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-3 py-2 leading-tight <?php echo ($i === $current_page) ? 'text-blue-600 bg-blue-50 border border-blue-300' : 'text-gray-500 bg-white border border-gray-300'; ?> rounded-lg hover:bg-gray-100 hover:text-gray-700">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 hover:text-gray-700">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <div id="filterSidebar" class="sidebar-filter fixed top-0 right-0 h-full w-full md:w-96 bg-white shadow-xl flex flex-col pt-4 md:pt-0">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-2xl font-semibold text-gray-800">Filter Options</h2>
            <button id="closeFilterSidebar" class="text-gray-500 hover:text-gray-700 text-2xl p-2 rounded-full hover:bg-gray-100 transition duration-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="payments.php" method="GET" class="flex-grow p-6 overflow-y-auto space-y-6">
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">Student:</label>
                <select name="user_id" id="user_id"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 select2-enabled"
                        data-placeholder="All Students" data-allow-clear="true">
                    <option value=""></option>
                    <?php foreach ($all_users_for_filters as $user_filter) : ?>
                        <option value="<?php echo htmlspecialchars($user_filter['user_id']); ?>" <?php echo ((string)$filter_user_id === (string)$user_filter['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user_filter['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="quiz_id" class="block text-sm font-medium text-gray-700 mb-2">Assessment:</label>
                <select name="quiz_id" id="quiz_id"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 select2-enabled"
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
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status:</label>
                <select name="status" id="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Statuses</option>
                    <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="failed" <?php echo ($filter_status === 'failed') ? 'selected' : ''; ?>>Failed</option>
                    <option value="abandoned" <?php echo ($filter_status === 'abandoned') ? 'selected' : ''; ?>>Abandoned</option>
                </select>
            </div>

            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date:</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"
                       class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 p-2.5">
            </div>

            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date:</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"
                       class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 p-2.5">
            </div>

            <div class="flex-grow"></div>

            <div class="sticky bottom-0 bg-white p-6 -mx-6 -mb-6 border-t border-gray-200 flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 mt-auto">
                <button type="submit" class="w-full sm:w-auto bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 ease-in-out flex items-center justify-center space-x-2 shadow-md">
                    <i class="fas fa-search"></i>
                    <span>Apply Filters</span>
                </button>
                <a href="payments.php" class="w-full sm:w-auto bg-gray-300 text-gray-800 px-6 py-3 rounded-lg hover:bg-gray-400 transition duration-300 ease-in-out flex items-center justify-center space-x-2 shadow-md">
                    <i class="fas fa-undo"></i>
                    <span>Reset Filters</span>
                </a>
            </div>
        </form>
    </div>

    <div id="sidebarOverlay" class="overlay fixed inset-0 bg-black bg-opacity-40 hidden opacity-0"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('filterSidebar');
            const openSidebarBtn = document.getElementById('openFilterSidebar');
            const closeSidebarBtn = document.getElementById('closeFilterSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            // Function to open the sidebar
            function openSidebar() {
                sidebar.classList.add('open');
                sidebarOverlay.classList.remove('hidden');
                setTimeout(() => sidebarOverlay.classList.remove('opacity-0'), 10); // Fade in overlay
                document.body.classList.add('overflow-hidden'); // Prevent scrolling body
                
                // Trigger change on Select2 elements to force redraw and proper width calculation
                $('.select2-enabled').each(function() {
                    $(this).trigger('change');
                });
            }

            // Function to close the sidebar
            function closeSidebar() {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.add('opacity-0'); // Fade out overlay
                setTimeout(() => sidebarOverlay.classList.add('hidden'), 300); // Hide after transition
                document.body.classList.remove('overflow-hidden'); // Allow body scrolling
            }

            // Event Listeners
            if (openSidebarBtn) {
                openSidebarBtn.addEventListener('click', openSidebar);
            }
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', closeSidebar);
            }
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar); // Close sidebar when clicking overlay
            }

            // Select2 Initialization
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                $('.select2-enabled').each(function() {
                    const placeholder = $(this).data('placeholder') || 'Select an option';
                    const allowClear = $(this).data('allow-clear') === true; // Check for strict true

                    $(this).select2({
                        placeholder: placeholder,
                        allowClear: allowClear,
                        width: '100%', // Ensure Select2 takes full width of its parent
                        dropdownParent: $(this).parent() // Crucial for correct dropdown positioning within modal/sidebar
                    });
                });
            } else {
                console.warn("jQuery or Select2 plugin not loaded. Select2 functionality will not work.");
            }

            // --- Popover functionality for transaction reference ---
            let currentPopover = null;

            function showPopover(element, reference) {
                // Remove any existing popover
                if (currentPopover) {
                    currentPopover.remove();
                    currentPopover = null;
                }

                const popover = document.createElement('div');
                popover.classList.add('popover');
                popover.innerHTML = `
                    <span class="popover-close">&times;</span>
                    <strong>Transaction Reference:</strong><br>${reference}
                    <div class="popover-arrow"></div>
                `;
                document.body.appendChild(popover);

                // Position the popover
                const rect = element.getBoundingClientRect();
                popover.style.left = `${rect.left + window.scrollX + (rect.width / 2) - (popover.offsetWidth / 2)}px`;
                popover.style.top = `${rect.top + window.scrollY - popover.offsetHeight - 10}px`; // 10px above the element

                // Adjust if popover goes off screen to the left
                if (parseFloat(popover.style.left) < 0) {
                    popover.style.left = '10px';
                }
                // Adjust if popover goes off screen to the right
                if (parseFloat(popover.style.left) + popover.offsetWidth > window.innerWidth) {
                    popover.style.left = `${window.innerWidth - popover.offsetWidth - 10}px`;
                }

                currentPopover = popover;

                // Close popover on click outside
                document.addEventListener('click', function handler(event) {
                    if (currentPopover && !currentPopover.contains(event.target) && !element.contains(event.target)) {
                        currentPopover.remove();
                        currentPopover = null;
                        document.removeEventListener('click', handler);
                    }
                });

                // Close popover using the close button
                popover.querySelector('.popover-close').addEventListener('click', () => {
                    popover.remove();
                    currentPopover = null;
                });
            }

            document.querySelectorAll('.transaction-ref-cell').forEach(cell => {
                // Add click listener for popover
                cell.addEventListener('click', function() {
                    const fullRef = this.dataset.fullRef;
                    if (fullRef && fullRef !== 'N/A') {
                        showPopover(this, fullRef);
                    }
                });
            });
            // --- End Popover functionality ---
        });
    </script>

    <?php // require_once '../includes/footer_admin.php'; // Included at the bottom of body ?>
</body>
</html>
<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>