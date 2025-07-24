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

// Fetch all users
$users = [];
try {
    $stmt = $pdo->query("
        SELECT user_id, first_name, last_name, email, username, auto_login_token, auto_login_token_expiry
        FROM users
        ORDER BY last_name, first_name
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Generate Auto-Login: Users Fetch Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "Could not fetch users. Please try again later.";
    $_SESSION['form_message_type'] = 'error';
}

// Handle generate or send actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = sanitize_input($_POST['user_id']);
    $action = sanitize_input($_POST['action']);

    try {
        // Fetch user details
        $stmt = $pdo->prepare("SELECT username, email, auto_login_token, auto_login_token_expiry FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate new token if needed or if existing one is expired
            $current_time = new DateTime();
            $token_needs_generation = true;

            if ($user['auto_login_token'] && $user['auto_login_token_expiry']) {
                // Only create DateTime object if expiry is not null
                $expiry_time = new DateTime($user['auto_login_token_expiry']);
                if ($current_time < $expiry_time) {
                    $token_needs_generation = false; // Token is still valid
                }
            }
            
            $auto_login_token = $user['auto_login_token'];
            $auto_login_token_expiry = $user['auto_login_token_expiry'];

            if ($token_needs_generation) {
                $auto_login_token = bin2hex(random_bytes(32));
                $expiry_date = new DateTime();
                $expiry_date->modify('+2 weeks');
                $auto_login_token_expiry = $expiry_date->format('Y-m-d H:i:s');

                $stmt_update = $pdo->prepare("
                    UPDATE users
                    SET auto_login_token = :token, auto_login_token_expiry = :expiry
                    WHERE user_id = :user_id
                ");
                $stmt_update->execute([
                    'token' => $auto_login_token,
                    'expiry' => $auto_login_token_expiry,
                    'user_id' => $user_id
                ]);
            }

            $auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($auto_login_token);

            if ($action === 'send') {
                // Send auto-login email
                ob_start();
                require '../includes/email_templates/assessment_reminder_email.php';
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
            } else {
                error_log("Generate Auto-Login: Token generated for user_id {$user_id}");
                $_SESSION['form_message'] = "Auto-login token generated for {$user['username']}. Link: " . $auto_login_link . " (Valid until " . format_datetime($auto_login_token_expiry) . ")";
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

    // Redirect to refresh the user list and show the message
    redirect(BASE_URL . 'admin/generate_auto_login.php');
    exit;
}

$page_title = "Generate Auto-Login Links";
$current_page = "generate_auto_login"; // For active state in navigation
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

        <?php if (empty($users)): ?>
            <p class="text-center text-gray-600 py-8">No users found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
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
                                if ($user['auto_login_token'] && $user['auto_login_token_expiry']) {
                                    $expiry_time = new DateTime($user['auto_login_token_expiry']);
                                    $current_time = new DateTime();
                                    if ($current_time < $expiry_time) {
                                        $token_status = 'Active';
                                        $expiry_display = format_datetime($user['auto_login_token_expiry']);
                                    } else {
                                        $token_status = 'Expired';
                                        $expiry_display = format_datetime($user['auto_login_token_expiry']) . ' (Expired)';
                                    }
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
                                                <form action="generate_auto_login.php" method="POST" class="block" onsubmit="return confirm('Generate new auto-login token for <?php echo htmlspecialchars($user['username']); ?>? This will invalidate any existing token.');">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                    <input type="hidden" name="action" value="generate">
                                                    <button type="submit" class="text-gray-700 block px-4 py-2 text-sm w-full text-left hover:bg-gray-100" role="menuitem">
                                                        Generate New Token
                                                    </button>
                                                </form>
                                                <button type="button" onclick="copyAutoLoginLink(<?php echo htmlspecialchars($user['user_id']); ?>, '<?php echo BASE_URL . "auth/auto_login.php?token=" . urlencode($user['auto_login_token']); ?>')" class="text-gray-700 block px-4 py-2 text-sm w-full text-left hover:bg-gray-100 <?php echo empty($user['auto_login_token']) || $token_status === 'Expired' ? 'opacity-50 cursor-not-allowed' : ''; ?>" role="menuitem" <?php echo empty($user['auto_login_token']) || $token_status === 'Expired' ? 'disabled' : ''; ?>>
                                                    Copy Link
                                                </button>
                                                <form action="generate_auto_login.php" method="POST" class="block" onsubmit="return confirm('Send auto-login link to <?php echo htmlspecialchars($user['email']); ?>? A new token will be generated if the current one is expired or non-existent.');">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                    <input type="hidden" name="action" value="send">
                                                    <button type="submit" class="text-gray-700 block px-4 py-2 text-sm w-full text-left hover:bg-gray-100" role="menuitem">
                                                        Send Email
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
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer_admin.php'; ?>

<script>
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
            displayNotification("No auto-login token exists for this user or it's invalid. Please generate a new one first.", "warning");
            return;
        }

        navigator.clipboard.writeText(link)
            .then(() => {
                displayNotification('Auto-login link copied to clipboard!', 'success');
            })
            .catch(err => {
                console.error('Failed to copy link: ', err);
                displayNotification('Failed to copy link. Please copy it manually from the "Generate" message if available.', 'error');
            });
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