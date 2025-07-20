<?php
// admin/generate_auto_login.php
// Allows admins to generate, send, copy, or delete auto-login links for users, with search and grade filtering.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Contains sanitize_input, redirect, BASE_URL
require_once '../includes/send_email.php'; // Contains sendEmail

// Pagination settings
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch distinct grades for filter dropdown
$grades = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT grade FROM quizzes WHERE grade IS NOT NULL ORDER BY grade");
    $grades = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Generate Auto-Login: Grades Fetch Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    // Only set session message if it's the main page load, not during AJAX
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        $_SESSION['form_message'] = "Could not fetch grades. Please try again later.";
        $_SESSION['form_message_type'] = 'error';
    }
}

// Handle search and grade filter for initial load or non-AJAX POST actions
$search = sanitize_input($_REQUEST['search'] ?? ''); // Use $_REQUEST to get from GET or POST
$selected_grade = sanitize_input($_REQUEST['grade'] ?? '');
$users = [];
$total_users = 0;

// Handle AJAX request for user data
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && isset($_GET['fetch_users'])) {
    
    $search = sanitize_input($_GET['search'] ?? '');
    $selected_grade = sanitize_input($_GET['grade'] ?? '');
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    try {
        $query = "
            SELECT u.user_id, u.first_name, u.last_name, u.email, u.username, u.auto_login_token
            FROM users u
        ";
        $params = [];
        $join_conditions = [];
        $where_conditions = ["1=1"];

        if ($search) {
            $where_conditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.username LIKE :search)";
            $params['search'] = "%$search%";
        }

        if ($selected_grade) {
            // Only join payments and quizzes if a grade filter is applied
            $join_conditions[] = "LEFT JOIN payments p ON u.user_id = p.user_id AND p.status = 'completed'";
            $join_conditions[] = "LEFT JOIN quizzes q ON p.quiz_id = q.quiz_id";
            $where_conditions[] = "q.grade = :grade";
            $params['grade'] = $selected_grade;
        }
        
        // Build the full query
        $query .= " " . implode(" ", $join_conditions);
        $query .= " WHERE " . implode(" AND ", $where_conditions);
        $query .= " GROUP BY u.user_id, u.first_name, u.last_name, u.email, u.username, u.auto_login_token"; // Use GROUP BY with DISTINCT in SELECT

        // Count total users for pagination
        $count_query = "SELECT COUNT(DISTINCT u.user_id) FROM users u " . implode(" ", $join_conditions) . " WHERE " . implode(" AND ", $where_conditions);
        
        $stmt_count = $pdo->prepare($count_query);
        $stmt_count->execute($params);
        $total_users = $stmt_count->fetchColumn();

        $query .= " ORDER BY u.last_name, u.first_name LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare response for AJAX
        ob_start(); // Start output buffering
        if (empty($users)) {
            echo '<p class="text-center text-gray-600 py-8">No users found matching the criteria.</p>';
        } else {
            // Render table rows
            ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Token Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 transition duration-200">
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900">
                                    <?php echo $user['auto_login_token'] ? 'Active' : 'None'; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap relative">
                                    <button onclick="toggleMenu('menu-<?php echo $user['user_id']; ?>')" class="text-gray-600 hover:bg-custom-dark hover:text-white rounded-full p-2 focus:outline-none transition duration-200">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                        </svg>
                                    </button>
                                    <div id="menu-<?php echo $user['user_id']; ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                        <form action="generate_auto_login.php" method="POST" class="border-b border-gray-200">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                            <input type="hidden" name="action" value="generate">
                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                            <input type="hidden" name="grade" value="<?php echo htmlspecialchars($selected_grade); ?>">
                                            <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                                            <button type="submit" onclick="return confirm('Generate auto-login token for <?php echo htmlspecialchars($user['username']); ?>?');" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-navy-900 hover:text-white">Generate</button>
                                        </form>
                                        <form action="generate_auto_login.php" method="POST" class="border-b border-gray-200">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                            <input type="hidden" name="action" value="send">
                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                            <input type="hidden" name="grade" value="<?php echo htmlspecialchars($selected_grade); ?>">
                                            <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                                            <button type="submit" onclick="return confirm('Send auto-login link to <?php echo htmlspecialchars($user['email']); ?>?');" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-600 hover:text-white">Send</button>
                                        </form>
                                        <?php if ($user['auto_login_token']): ?>
                                            <button onclick="copyToken('<?php echo htmlspecialchars(BASE_URL . 'auth/auto_login.php?token=' . urlencode($user['auto_login_token'])); ?>', '<?php echo htmlspecialchars($user['username']); ?>')" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-green-600 hover:text-white">Copy</button>
                                            <form action="generate_auto_login.php" method="POST">
                                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                                <input type="hidden" name="grade" value="<?php echo htmlspecialchars($selected_grade); ?>">
                                                <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                                                <button type="submit" onclick="return confirm('Delete auto-login token for <?php echo htmlspecialchars($user['username']); ?>?');" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-600 hover:text-white">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <button disabled class="block w-full text-left px-4 py-2 text-sm text-gray-400 cursor-not-allowed">Copy</button>
                                            <button disabled class="block w-full text-left px-4 py-2 text-sm text-gray-400 cursor-not-allowed">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
        $table_html = ob_get_clean(); // Get the buffered output

        // Render pagination links
        $total_pages = ceil($total_users / $limit);
        ob_start();
        if ($total_pages > 1) {
            ?>
            <nav class="flex justify-center items-center space-x-2 mt-6" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="javascript:void(0);" onclick="fetchUsers(<?php echo $page - 1; ?>)" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Previous
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="javascript:void(0);" onclick="fetchUsers(<?php echo $i; ?>)" class="relative inline-flex items-center px-4 py-2 text-sm font-medium <?php echo ($i === $page) ? 'bg-navy-900 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="javascript:void(0);" onclick="fetchUsers(<?php echo $page + 1; ?>)" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Next
                    </a>
                <?php endif; ?>
            </nav>
            <?php
        }
        $pagination_html = ob_get_clean();

        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode(['table_html' => $table_html, 'pagination_html' => $pagination_html]);
        exit;

    } catch (PDOException $e) {
        error_log("Generate Auto-Login: AJAX Users Fetch Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => "Could not fetch users: " . $e->getMessage()]);
        exit;
    }
}


// Handle non-AJAX POST actions (generate, send, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = sanitize_input($_POST['user_id']);
    $action = sanitize_input($_POST['action']);
    
    // Retain current search, grade, and page for redirection
    $current_search = sanitize_input($_POST['search'] ?? '');
    $current_grade = sanitize_input($_POST['grade'] ?? '');
    $current_page = sanitize_input($_POST['page'] ?? 1);

    try {
        // Fetch user details
        $stmt = $pdo->prepare("SELECT username, email, auto_login_token FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($action === 'generate' || $action === 'send') {
                // Generate new token if needed
                $auto_login_token = $user['auto_login_token'] ?: bin2hex(random_bytes(32));
                $stmt_update = $pdo->prepare("
                    UPDATE users 
                    SET auto_login_token = :token
                    WHERE user_id = :user_id
                ");
                $stmt_update->execute([
                    'token' => $auto_login_token,
                    'user_id' => $user_id
                ]);

                if ($action === 'send') {
                    // Send auto-login email
                    $auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($auto_login_token);
                    ob_start();
                    require '../includes/email_templates/assessment_reminder_email.php';
                    $email_body = ob_get_clean();
                    
                    $email_body = str_replace('{{subject}}', "Access Your Mackenny Assessment Account", $email_body);
                    $email_body = str_replace('{{username}}', htmlspecialchars($user['username']), $email_body);
                    $email_body = str_replace('{{email}}', htmlspecialchars($user['email']), $email_body);
                    $email_body = str_replace('{{auto_login_link}}', htmlspecialchars($auto_login_link), $email_body);
                    $email_body = str_replace('{{quiz_title}}', 'Your Assessments', $email_body);
                    $email_body = str_replace('{{description}}', 'Access your Mackenny Assessment account to view and take your assessments.', $email_body);
                    $email_body = str_replace('{{open_datetime}}', 'N/A', $email_body);
                    $email_body = str_replace('{{duration_minutes}}', 'N/A', $email_body);
                    $email_body = str_replace('{{grade}}', 'N/A', $email_body);
                    $email_body = str_replace('{{amount}}', 'N/A', $email_body);
                    $email_body = str_replace('{{transaction_reference}}', 'N/A', $email_body);
                    $email_body = str_replace('{{payment_date}}', 'N/A', $email_body);
                    $email_body = str_replace('{{message}}', "Use the link below to access your Mackenny Assessment account.", $email_body);
                    
                    $subject = "Access Your Mackenny Assessment Account";

                    if (sendEmail($user['email'], $subject, $email_body)) {
                        error_log("Generate Auto-Login: Email sent to {$user['email']} for user_id {$user_id}");
                        $_SESSION['form_message'] = "Auto-login link sent to {$user['email']}.";
                        $_SESSION['form_message_type'] = 'success';
                    } else {
                        error_log("Generate Auto-Login: Failed to send email to {$user['email']} for user_id {$user_id}");
                        $_SESSION['form_message'] = "Failed to send auto-login link to {$user['email']}. Please try again.";
                        $_SESSION['form_message_type'] = 'error';
                    }
                } else {
                    error_log("Generate Auto-Login: Token generated for user_id {$user_id}");
                    $_SESSION['form_message'] = "Auto-login token generated for {$user['username']}.";
                    $_SESSION['form_message_type'] = 'success';
                }
            } elseif ($action === 'delete') {
                // Delete auto-login token
                $stmt_delete = $pdo->prepare("
                    UPDATE users 
                    SET auto_login_token = NULL
                    WHERE user_id = :user_id
                ");
                $stmt_delete->execute(['user_id' => $user_id]);
                error_log("Generate Auto-Login: Token deleted for user_id {$user_id}");
                $_SESSION['form_message'] = "Auto-login token deleted for {$user['username']}.";
                $_SESSION['form_message_type'] = 'success';
            }
        } else {
            error_log("Generate Auto-Login: User not found for user_id {$user_id}");
            $_SESSION['form_message'] = "User not found.";
            $_SESSION['form_message_type'] = 'error';
        }
    } catch (PDOException $e) {
        error_log("Generate Auto-Login: DB Error for user_id {$user_id}: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
        $_SESSION['form_message'] = "An unexpected error occurred. Please try again later.";
        $_SESSION['form_message_type'] = 'error';
    }
    
    // Redirect to maintain search and grade filters
    $redirect_url = BASE_URL . 'admin/generate_auto_login.php?page=' . $current_page;
    if ($current_search) {
        $redirect_url .= '&search=' . urlencode($current_search);
    }
    if ($current_grade) {
        $redirect_url .= '&grade=' . urlencode($current_grade);
    }
    redirect($redirect_url);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Auto-Login Links - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-navy-900 { background-color: #0a1930; }
        .bg-navy-800 { background-color: #1a2b4a; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
        .border-custom-dark { border-color: #171248; }
        .text-custom-dark { color: #171248; }
        .hover\:bg-custom-dark:hover { background-color: #171248; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-50 min-h-screen flex flex-col">
    <?php require_once '../includes/header_admin.php'; ?>

    <main class="flex-1 p-4 lg:p-8 mt-16 w-full max-w-6xl mx-auto">
        <div class="bg-white rounded-xl shadow-2xl p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Manage Auto-Login Links</h2>

            <div id="form-notification" class="absolute top-4 right-4 px-4 py-3 rounded-md hidden z-50" role="alert">
                <strong class="font-bold"></strong>
                <span class="block sm:inline" id="notification-message-content"></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                    <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                        <path d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </span>
            </div>

            <div class="mb-6 flex flex-col sm:flex-row sm:space-x-4 items-end">
                <div class="flex-1 mb-4 sm:mb-0">
                    <label for="search" class="block text-sm font-medium text-gray-700">Search User</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, or username" class="w-full rounded-md border-2 border-custom-dark shadow-sm focus:border-indigo-600 focus:ring focus:ring-indigo-600 focus:ring-opacity-50 p-2 text-custom-dark">
                </div>
                <div class="flex-1 mb-4 sm:mb-0">
                    <label for="grade" class="block text-sm font-medium text-gray-700">Filter by Grade</label>
                    <select id="grade" name="grade" class="w-full rounded-md border-2 border-custom-dark shadow-sm focus:border-indigo-600 focus:ring focus:ring-indigo-600 focus:ring-opacity-50 p-2 text-custom-dark">
                        <option value="" <?php echo ($selected_grade === '') ? 'selected' : ''; ?>>All Grades</option>
                        <?php foreach ($grades as $grade): ?>
                            <option value="<?php echo htmlspecialchars($grade); ?>" <?php echo ($selected_grade === $grade) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grade); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                </div>

            <div id="userTableContainer">
                <p class="text-center text-gray-600 py-8">Loading users...</p>
            </div>
            
            <div id="paginationContainer">
                </div>
        </div>
    </main>

    <?php require_once '../includes/footer_admin.php'; ?>
    <script>
        // Debounce function to limit how often a function is called
        function debounce(func, delay) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        }

        function displayNotification(message, type) {
            const notificationContainer = document.getElementById('form-notification');
            const messageContentElement = document.getElementById('notification-message-content');
            const strongTag = notificationContainer.querySelector('strong');

            notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
            notificationContainer.classList.add('px-4', 'py-3', 'rounded-md', 'border', 'flex', 'items-center', 'space-x-2'); // Ensure base classes are present
            strongTag.textContent = '';

            if (message) {
                messageContentElement.textContent = message;
                if (type === 'error') {
                    notificationContainer.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
                    strongTag.textContent = 'Error!';
                } else if (type === 'success') {
                    notificationContainer.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
                    strongTag.textContent = 'Success!';
                } else if (type === 'warning') {
                    notificationContainer.classList.add('bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
                    strongTag.textContent = 'Warning!';
                }
                notificationContainer.classList.remove('hidden');
                notificationContainer.style.transform = 'translateY(-100%)';
                setTimeout(() => {
                    notificationContainer.style.transition = 'transform 0.3s ease-out';
                    notificationContainer.style.transform = 'translateY(0)';
                }, 10);
                setTimeout(() => {
                    hideNotification();
                }, 5000);
            }
        }

        function hideNotification() {
            const notificationElement = document.getElementById('form-notification');
            notificationElement.style.transition = 'transform 0.3s ease-in';
            notificationElement.style.transform = 'translateY(-100%)';
            notificationElement.addEventListener('transitionend', function handler() {
                notificationElement.classList.add('hidden');
                notificationElement.removeEventListener('transitionend', handler);
            });
        }

        async function copyToken(url, username) {
            try {
                await navigator.clipboard.writeText(url);
                displayNotification(`Auto-login URL copied for ${username}.`, 'success');
            } catch (err) {
                displayNotification('Failed to copy URL. Please try again.', 'error');
                console.error('Copy failed:', err);
            }
        }

        function toggleMenu(menuId) {
            const menu = document.getElementById(menuId);
            const isHidden = menu.classList.contains('hidden');
            // Close all other menus
            document.querySelectorAll('[id^="menu-"]').forEach(m => m.classList.add('hidden'));
            // Toggle the clicked menu
            if (isHidden) {
                menu.classList.remove('hidden');
            } else {
                menu.classList.add('hidden');
            }
            // Close menu when clicking outside
            document.addEventListener('click', function closeMenu(event) {
                // Check if the click was outside the menu AND not on the toggle button itself
                if (!menu.contains(event.target) && !event.target.closest('button[onclick^="toggleMenu"]')) {
                    menu.classList.add('hidden');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }

        // AJAX function to fetch users
        const fetchUsers = debounce(async (page = 1) => {
            const searchInput = document.getElementById('search').value;
            const gradeSelect = document.getElementById('grade').value;
            const userTableContainer = document.getElementById('userTableContainer');
            const paginationContainer = document.getElementById('paginationContainer');

            // Show loading state
            userTableContainer.innerHTML = '<p class="text-center text-gray-600 py-8">Loading users...</p>';
            paginationContainer.innerHTML = ''; // Clear pagination while loading

            const params = new URLSearchParams({
                fetch_users: 'true', // Indicate this is an AJAX fetch
                search: searchInput,
                grade: gradeSelect,
                page: page
            });

            try {
                const response = await fetch(`generate_auto_login.php?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Identify as AJAX request
                    }
                });
                const data = await response.json();

                if (response.ok) {
                    userTableContainer.innerHTML = data.table_html;
                    paginationContainer.innerHTML = data.pagination_html;
                    // Re-bind click handlers for toggleMenu if dynamic content is loaded (though direct onclick is usually fine)
                    // document.querySelectorAll('button[onclick^="toggleMenu"]').forEach(button => {
                    //     button.onclick = () => toggleMenu(button.nextElementSibling.id);
                    // });
                } else {
                    displayNotification(data.error || 'Failed to fetch users.', 'error');
                    userTableContainer.innerHTML = '<p class="text-center text-red-600 py-8">Error loading users. ' + (data.error || '') + '</p>';
                }
            } catch (error) {
                console.error('Fetch error:', error);
                displayNotification('An error occurred while fetching data.', 'error');
                userTableContainer.innerHTML = '<p class="text-center text-red-600 py-8">An unexpected error occurred while loading users.</p>';
            }
        }, 300); // Debounce by 300ms

        document.addEventListener('DOMContentLoaded', function() {
            // Display any initial PHP-set messages
            <?php if (isset($_SESSION['form_message'])): ?>
                displayNotification("<?php echo htmlspecialchars($_SESSION['form_message']); ?>", "<?php echo htmlspecialchars($_SESSION['form_message_type']); ?>");
                <?php
                unset($_SESSION['form_message']);
                unset($_SESSION['form_message_type']);
                ?>
            <?php endif; ?>

            // Attach event listeners for search and grade filter
            document.getElementById('search').addEventListener('input', () => fetchUsers(1)); // Reset to page 1 on search
            document.getElementById('grade').addEventListener('change', () => fetchUsers(1)); // Reset to page 1 on grade change

            // Initial fetch of users when the page loads
            fetchUsers(<?php echo $page; ?>); // Load the initial page, could be 1 or from GET param
        });
    </script>
</body>
</html>