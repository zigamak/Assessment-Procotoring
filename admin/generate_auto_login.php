<?php
// admin/generate_auto_login.php
// Allows admins to generate or send auto-login links for users.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Contains sanitize_input, redirect, isLoggedIn, getUserRole, BASE_URL, format_datetime
require_once '../includes/send_email.php'; // Contains sendEmail

// Ensure only admins can access this page
if (!isLoggedIn() || getUserRole() !== 'admin') {
    error_log("Generate Auto-Login: Unauthorized access attempt by user_id=" . (getUserId() ?? 'none'));
    redirect(BASE_URL . 'auth/login.php');
    exit;
}

// --- Pagination and Search Logic ---
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_sql = '';
$search_params = [];

if (!empty($search_query)) {
    $search_sql = " WHERE u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.username LIKE :search";
    $search_params[':search'] = '%' . $search_query . '%';
}

// Fetch total number of users for pagination
$total_users = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(user_id) FROM users u" . $search_sql);
    $stmt->execute($search_params);
    $total_users = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Generate Auto-Login: Total Users Fetch Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "Could not fetch total users for pagination.";
    $_SESSION['form_message_type'] = 'error';
}

$total_pages = ceil($total_users / $records_per_page);

// Fetch all users and their *most recent active* auto-login token
$users = [];
try {
    // This query now uses a subquery to select the most recent active token for each user
    // if one exists (expires_at > NOW() AND used = 0), otherwise it will be NULL.
    $sql = "
        SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.username,
            alt.token AS auto_login_token,
            alt.expires_at AS auto_login_token_expiry,
            alt.used AS auto_login_token_used
        FROM users u
        LEFT JOIN (
            SELECT
                user_id,
                token,
                expires_at,
                used,
                created_at,
                ROW_NUMBER() OVER(PARTITION BY user_id ORDER BY created_at DESC) as rn
            FROM auto_login_tokens
            WHERE expires_at > NOW() AND used = 0
        ) AS alt ON u.user_id = alt.user_id AND alt.rn = 1
        " . $search_sql . "
        ORDER BY u.last_name, u.first_name
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($search_params as $key => &$val) {
        $stmt->bindParam($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Generate Auto-Login: Users Fetch Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "Could not fetch users. Please try again later.";
    $_SESSION['form_message_type'] = 'error';
}

// Handle generate, send or delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = sanitize_input($_POST['user_id']);
    $action = sanitize_input($_POST['action']);
    $token_to_delete = isset($_POST['token']) ? sanitize_input($_POST['token']) : null; // For delete_token action

    try {
        // Fetch user details
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($action === 'generate' || $action === 'send') {
                $auto_login_token = bin2hex(random_bytes(32));
                $expiry_date = new DateTime();
                $expiry_date->modify('+2 weeks');
                $auto_login_token_expiry = $expiry_date->format('Y-m-d H:i:s');

                // Invalidate any existing active tokens for this user before creating a new one
                $stmt_invalidate = $pdo->prepare("
                    UPDATE auto_login_tokens
                    SET used = 1
                    WHERE user_id = :user_id AND expires_at > NOW() AND used = 0
                ");
                $stmt_invalidate->execute(['user_id' => $user_id]);

                // Insert new token into auto_login_tokens table
                $stmt_insert = $pdo->prepare("
                    INSERT INTO auto_login_tokens (user_id, token, expires_at, used)
                    VALUES (:user_id, :token, :expiry, 0)
                ");
                $stmt_insert->execute([
                    'user_id' => $user_id,
                    'token' => $auto_login_token,
                    'expiry' => $auto_login_token_expiry
                ]);

                $auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($auto_login_token);

                if ($action === 'send') {
                    // Send auto-login email
                    ob_start();
                    require '../includes/email_templates/auto_login_email.php'; // Reusing this template
                    $email_body = ob_get_clean();

                    $email_body = str_replace('{{subject}}', "Access Your Mackenny Assessment Account", $email_body);
                    $email_body = str_replace('{{username}}', htmlspecialchars($user['username']), $email_body);
                    $email_body = str_replace('{{email}}', htmlspecialchars($user['email']), $email_body);
                    $email_body = str_replace('{{auto_login_link}}', htmlspecialchars($auto_login_link), $email_body);
                    $email_body = str_replace('{{quiz_title}}', 'Your Assessments', $email_body);
                    $email_body = str_replace('{{description}}', 'Use the link below to access your Mackenny Assessment account. This link is valid until ' . format_datetime($auto_login_token_expiry) . '.', $email_body);
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
                        $_SESSION['form_message'] = "Auto-login link sent to {$user['email']}. It is valid until " . format_datetime($auto_login_token_expiry) . ".";
                        $_SESSION['form_message_type'] = 'success';
                    } else {
                        error_log("Generate Auto-Login: Failed to send email to {$user['email']} for user_id {$user_id}");
                        $_SESSION['form_message'] = "Failed to send auto-login link to {$user['email']}. Please try again.";
                        $_SESSION['form_message_type'] = 'error';
                    }
                } else { // 'generate' action
                    error_log("Generate Auto-Login: Token generated for user_id {$user_id}");
                    $_SESSION['form_message'] = "Auto-login token generated for {$user['username']}. Link: " . $auto_login_link . " (Valid until " . format_datetime($auto_login_token_expiry) . ")";
                    $_SESSION['form_message_type'] = 'success';
                }
            } elseif ($action === 'invalidate_current_token') {
                if ($token_to_delete) {
                    $stmt_delete = $pdo->prepare("
                        UPDATE auto_login_tokens
                        SET used = 1
                        WHERE user_id = :user_id AND token = :token AND used = 0 AND expires_at > NOW()
                    ");
                    $stmt_delete->execute(['user_id' => $user_id, 'token' => $token_to_delete]);
                    if ($stmt_delete->rowCount() > 0) {
                        error_log("Generate Auto-Login: Active token '{$token_to_delete}' invalidated for user_id {$user_id}");
                        $_SESSION['form_message'] = "The current active auto-login token for {$user['username']} has been invalidated.";
                        $_SESSION['form_message_type'] = 'success';
                    } else {
                        error_log("Generate Auto-Login: No active token '{$token_to_delete}' found for user_id {$user_id} to invalidate.");
                        $_SESSION['form_message'] = "Could not find an active token to invalidate for {$user['username']}. It might already be expired or used.";
                        $_SESSION['form_message_type'] = 'warning';
                    }
                } else {
                    $_SESSION['form_message'] = "Token to invalidate was not provided.";
                    $_SESSION['form_message_type'] = 'error';
                }
            } elseif ($action === 'invalidate_all_tokens') {
                 $stmt_invalidate_all = $pdo->prepare("
                    UPDATE auto_login_tokens
                    SET used = 1
                    WHERE user_id = :user_id
                 ");
                 $stmt_invalidate_all->execute(['user_id' => $user_id]);
                 error_log("Generate Auto-Login: All tokens invalidated for user_id {$user_id}. Rows affected: " . $stmt_invalidate_all->rowCount());
                 $_SESSION['form_message'] = "All auto-login tokens for {$user['username']} have been invalidated.";
                 $_SESSION['form_message_type'] = 'success';
            }
        } else {
            error_log("Generate Auto-Login: User not found for user_id {$user_id}");
            $_SESSION['form_message'] = "User not found.";
            $_SESSION['form_message_type'] = 'error';
        }
    } catch (PDOException $e) {
        error_log("Generate Auto-Login: DB Error for user_id {$user_id} (Action: {$action}): SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
        $_SESSION['form_message'] = "An unexpected error occurred. Please try again later.";
        $_SESSION['form_message_type'] = 'error';
    }

    // Redirect to refresh the user list and show the message
    // Preserve search and page parameters
    $redirect_url = BASE_URL . 'admin/generate_auto_login.php';
    $params = [];
    if (!empty($search_query)) $params['search'] = $search_query;
    if ($current_page > 1) $params['page'] = $current_page;
    if (!empty($params)) {
        $redirect_url .= '?' . http_build_query($params);
    }
    redirect($redirect_url);
    exit;
}

$page_title = "Manage Auto-Login Links";
$current_page_nav = "generate_auto_login"; // For active state in navigation
?>

<?php include '../includes/header_admin.php'; ?>

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

        <div class="mb-6">
            <form action="generate_auto_login.php" method="GET" class="flex items-center space-x-2">
                <input type="text" name="search" placeholder="Search by name, email, or username"
                       class="flex-1 p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    Search
                </button>
                <?php if (!empty($search_query)): ?>
                    <a href="generate_auto_login.php" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($users)): ?>
            <p class="text-center text-gray-600 py-8">No users found<?php echo !empty($search_query) ? " matching your search." : "."; ?></p>
        <?php else: ?>
            <div class="overflow-x-auto shadow-md rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Token Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <?php
                                $token_status = 'None';
                                $expiry_display = 'N/A';
                                $is_token_active = false;
                                $auto_login_link = '';

                                if ($user['auto_login_token'] && $user['auto_login_token_expiry'] && !$user['auto_login_token_used']) {
                                    $expiry_time = new DateTime($user['auto_login_token_expiry']);
                                    $current_time = new DateTime();
                                    if ($current_time < $expiry_time) {
                                        $token_status = 'Active';
                                        $expiry_display = format_datetime($user['auto_login_token_expiry']);
                                        $is_token_active = true;
                                        $auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($user['auto_login_token']);
                                    } else {
                                        $token_status = 'Expired';
                                        $expiry_display = format_datetime($user['auto_login_token_expiry']) . ' (Expired)';
                                    }
                                } elseif ($user['auto_login_token_used']) {
                                    $token_status = 'Used';
                                    $expiry_display = format_datetime($user['auto_login_token_expiry']) . ' (Used)';
                                }
                            ?>
                            <tr class="hover:bg-gray-50 transition duration-200">
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900">
                                    <?php echo $token_status; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-900">
                                    <?php echo $expiry_display; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <div class="relative inline-block text-left">
                                        <div>
                                            <button type="button" class="inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-gray-600 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-gray-500" id="options-menu-<?php echo $user['user_id']; ?>" aria-haspopup="true" aria-expanded="true" onclick="toggleDropdown(<?php echo $user['user_id']; ?>)">
                                                Actions
                                                <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>

                                        <div class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 hidden" id="dropdown-menu-<?php echo $user['user_id']; ?>" role="menu" aria-orientation="vertical" aria-labelledby="options-menu-<?php echo $user['user_id']; ?>">
                                            <div class="py-1" role="none">
                                                <form id="generate-form-<?php echo $user['user_id']; ?>" action="generate_auto_login.php" method="POST" class="block" onsubmit="return showConfirmModal('Generate new auto-login token for <?php echo htmlspecialchars($user['username']); ?>? This will invalidate any existing active token.', this);">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                    <input type="hidden" name="action" value="generate">
                                                    <button type="submit" class="text-gray-700 block px-4 py-2 text-sm w-full text-left hover:bg-gray-100" role="menuitem">
                                                        Generate New Token
                                                    </button>
                                                </form>
                                                <button type="button" onclick="copyAutoLoginLink(<?php echo htmlspecialchars($user['user_id']); ?>, '<?php echo $is_token_active ? $auto_login_link : ''; ?>')" class="text-gray-700 block px-4 py-2 text-sm w-full text-left hover:bg-gray-100 <?php echo !$is_token_active ? 'opacity-50 cursor-not-allowed' : ''; ?>" role="menuitem" <?php echo !$is_token_active ? 'disabled' : ''; ?>>
                                                    Copy Link
                                                </button>
                                                <form id="send-form-<?php echo $user['user_id']; ?>" action="generate_auto_login.php" method="POST" class="block" onsubmit="return showConfirmModal('Send auto-login link to <?php echo htmlspecialchars($user['email']); ?>? A new token will be generated and any existing active token will be invalidated.', this);">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                    <input type="hidden" name="action" value="send">
                                                    <button type="submit" class="text-gray-700 block px-4 py-2 text-sm w-full text-left hover:bg-gray-100" role="menuitem">
                                                        Send Email
                                                    </button>
                                                </form>
                                                <?php if ($is_token_active): // Only show invalidate current if there's an active token ?>
                                                <form id="invalidate-current-form-<?php echo $user['user_id']; ?>" action="generate_auto_login.php" method="POST" class="block" onsubmit="return showConfirmModal('Are you sure you want to invalidate the current active auto-login token for <?php echo htmlspecialchars($user['username']); ?>?', this);">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                    <input type="hidden" name="action" value="invalidate_current_token">
                                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($user['auto_login_token']); ?>">
                                                    <button type="submit" class="text-yellow-600 block px-4 py-2 text-sm w-full text-left hover:bg-yellow-100" role="menuitem">
                                                        Invalidate Current Token
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <form id="invalidate-all-form-<?php echo $user['user_id']; ?>" action="generate_auto_login.php" method="POST" class="block" onsubmit="return showConfirmModal('Are you sure you want to invalidate ALL auto-login tokens for <?php echo htmlspecialchars($user['username']); ?>? This includes all active, expired, and previously used tokens.', this);">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                    <input type="hidden" name="action" value="invalidate_all_tokens">
                                                    <button type="submit" class="text-red-600 block px-4 py-2 text-sm w-full text-left hover:bg-red-100" role="menuitem">
                                                        Invalidate All Tokens
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $current_page ? 'bg-indigo-50 border-indigo-500 text-indigo-600' : 'text-gray-700 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    <?php endif; ?>
                </nav>
                <p class="text-sm text-gray-700">
                    Showing <span class="font-medium"><?php echo min($offset + 1, $total_users); ?></span> to <span class="font-medium"><?php echo min($offset + $records_per_page, $total_users); ?></span> of <span class="font-medium"><?php echo $total_users; ?></span> results
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<div id="confirmation-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-[100] hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm mx-auto transform transition-all sm:w-full">
        <div class="text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Confirm Action</h3>
            <div class="mt-2">
                <p class="text-sm text-gray-500" id="modal-message"></p>
            </div>
        </div>
        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
            <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-2 sm:text-sm" onclick="confirmAction()">
                Confirm
            </button>
            <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:col-start-1 sm:text-sm" onclick="cancelAction()">
                Cancel
            </button>
        </div>
    </div>
</div>

<?php include '../includes/footer_admin.php'; ?>

<script>
    // Global variable to store the form that needs to be submitted after confirmation
    let formToSubmit = null;

    function displayNotification(message, type) {
        const notificationContainer = document.getElementById('form-notification');
        const messageContentElement = document.getElementById('notification-message-content');
        const strongTag = notificationContainer.querySelector('strong');

        notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
        strongTag.textContent = '';

        if (message) {
            notificationContainer.classList.add('border-l-4', 'shadow-lg'); // Added for consistent styling
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

    // Function to copy the auto-login link to clipboard
    function copyAutoLoginLink(userId, link) {
        // Close the dropdown after action
        closeAllDropdowns();

        if (!link || link.includes("null") || link.includes("undefined") || link.endsWith("token=")) {
            displayNotification("No active auto-login token exists for this user. Please generate a new one first.", "warning");
            return;
        }

        const tempInput = document.createElement('textarea');
        tempInput.value = link;
        document.body.appendChild(tempInput);
        tempInput.select();
        try {
            document.execCommand('copy');
            displayNotification('Auto-login link copied to clipboard!', 'success');
        } catch (err) {
            console.error('Failed to copy link: ', err);
            displayNotification('Failed to copy link. Please copy it manually from the "Generate" message if available.', 'error');
        } finally {
            document.body.removeChild(tempInput);
        }
    }

    // Dropdown functionality
    function toggleDropdown(userId) {
        const dropdownMenu = document.getElementById(`dropdown-menu-${userId}`);
        const allDropdowns = document.querySelectorAll('[id^="dropdown-menu-"]');

        // Close all other dropdowns
        allDropdowns.forEach(dropdown => {
            if (dropdown.id !== `dropdown-menu-${userId}`) {
                dropdown.classList.add('hidden');
            }
        });

        dropdownMenu.classList.toggle('hidden');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const dropdownButtons = document.querySelectorAll('[id^="options-menu-"]');
        let clickedOnDropdown = false;
        dropdownButtons.forEach(button => {
            if (button.contains(event.target)) {
                clickedOnDropdown = true;
            }
            // Also check if the click is inside any dropdown menu itself
            const dropdownMenu = button.parentNode.querySelector('[id^="dropdown-menu-"]');
            if (dropdownMenu && dropdownMenu.contains(event.target)) {
                clickedOnDropdown = true;
            }
        });

        if (!clickedOnDropdown) {
            closeAllDropdowns();
        }
    });

    function closeAllDropdowns() {
        const allDropdowns = document.querySelectorAll('[id^="dropdown-menu-"]');
        allDropdowns.forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
    }

    // Custom Confirmation Modal Functions
    function showConfirmModal(message, form) {
        const modal = document.getElementById('confirmation-modal');
        const modalMessage = document.getElementById('modal-message');
        modalMessage.textContent = message;
        formToSubmit = form; // Store the form reference
        modal.classList.remove('hidden');
        return false; // Prevent default form submission
    }

    function confirmAction() {
        const modal = document.getElementById('confirmation-modal');
        modal.classList.add('hidden');
        if (formToSubmit) {
            formToSubmit.submit(); // Submit the stored form
        }
        formToSubmit = null; // Clear the stored form
    }

    function cancelAction() {
        const modal = document.getElementById('confirmation-modal');
        modal.classList.add('hidden');
        formToSubmit = null; // Clear the stored form
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['form_message'])): ?>
            displayNotification("<?php echo htmlspecialchars($_SESSION['form_message']); ?>", "<?php echo htmlspecialchars($_SESSION['form_message_type']); ?>");
            <?php
            unset($_SESSION['form_message']);
            unset($_SESSION['form_message_type']);
            ?>
        <?php endif; ?>
    });
</script>