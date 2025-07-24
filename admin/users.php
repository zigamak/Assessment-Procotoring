<?php
// admin/users.php
// Page to add, edit, and delete Admin and Student accounts, and send payment links.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/send_email.php';
require_once '../includes/header_admin.php';

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . '/');
}

// Define BASE_DIR and upload paths
if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/..'));
}
$upload_dir = BASE_DIR . '/Uploads/passports/';
$admin_upload_dir = BASE_DIR . '/Uploads/admin_profiles/';
$upload_url_path = BASE_URL . 'Uploads/passports/';
$admin_profile_upload_dir = BASE_URL . 'Uploads/admin_profiles/';
$student_passport_upload_dir = BASE_URL . 'Uploads/passports/';

// Placeholder image URL
$placeholder_image_url = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSQY2wp4iDIst2_iF51miozA4fRKg58TnnxCw&s';

// Ensure the upload directories exist
foreach ([$upload_dir, $admin_upload_dir] as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Failed to create upload directory: " . $dir);
            $message = display_message("Upload directory does not exist and could not be created. Please contact support.", "error");
        }
    }
}

$message = ''; // Initialize message variable for feedback
$users = [];   // Array to hold fetched users

// Ensure $pdo is available from db.php
if (!isset($pdo)) {
    error_log("Database connection (PDO) not available in users.php.");
    $message = display_message("Database connection error. Please try again later.", "error");
}

// Handle form submissions for adding, editing, deleting users, and sending payment links
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'add':
                $username = sanitize_input($_POST['username'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $password = sanitize_input($_POST['password'] ?? '');
                $role = sanitize_input($_POST['role'] ?? '');

                if (empty($username) || empty($email) || empty($password) || empty($role)) {
                    $message = display_message("All fields are required to add a user.", "error");
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = display_message("Invalid email format.", "error");
                } elseif ($role !== 'admin' && $role !== 'student') {
                    $message = display_message("Invalid role specified.", "error");
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
                        $stmt->execute(['username' => $username, 'email' => $email]);
                        if ($stmt->fetchColumn() > 0) {
                            $message = display_message("Username or email already exists.", "error");
                        } else {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (:username, :password_hash, :email, :role)");
                            if ($stmt->execute(['username' => $username, 'password_hash' => $password_hash, 'email' => $email, 'role' => $role])) {
                                $message = display_message("User added successfully!", "success");
                            } else {
                                $message = display_message("Failed to add user.", "error");
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Add User Error: " . $e->getMessage());
                        $message = display_message("Database error while adding user: " . htmlspecialchars($e->getMessage()), "error");
                    }
                }
                break;

            case 'edit':
                $user_id = sanitize_input($_POST['user_id'] ?? 0);
                $username = sanitize_input($_POST['username'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $role = sanitize_input($_POST['role'] ?? '');
                $new_password = sanitize_input($_POST['new_password'] ?? '');
                $first_name = sanitize_input($_POST['first_name'] ?? '');
                $last_name = sanitize_input($_POST['last_name'] ?? '');
                $city = sanitize_input($_POST['city'] ?? '');
                $state = sanitize_input($_POST['state'] ?? '');
                $country = sanitize_input($_POST['country'] ?? '');
                $date_of_birth = sanitize_input($_POST['date_of_birth'] ?? '');
                $grade = sanitize_input($_POST['grade'] ?? '');
                $address = sanitize_input($_POST['address'] ?? '');
                $gender = sanitize_input($_POST['gender'] ?? '');
                $school_name = sanitize_input($_POST['school_name'] ?? '');

                if (empty($user_id) || empty($username) || empty($email) || empty($role)) {
                    $message = display_message("All required fields are missing.", "error");
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = display_message("Invalid email format.", "error");
                } elseif ($role !== 'admin' && $role !== 'student') {
                    $message = display_message("Invalid role specified.", "error");
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email) AND user_id != :user_id");
                        $stmt->execute(['username' => $username, 'email' => $email, 'user_id' => $user_id]);
                        if ($stmt->fetchColumn() > 0) {
                            $message = display_message("Username or email already exists for another user.", "error");
                        } else {
                            $sql = "UPDATE users SET 
                                    username = :username, 
                                    email = :email, 
                                    role = :role,
                                    first_name = :first_name,
                                    last_name = :last_name,
                                    city = :city,
                                    state = :state,
                                    country = :country,
                                    date_of_birth = :date_of_birth,
                                    grade = :grade,
                                    address = :address,
                                    gender = :gender,
                                    school_name = :school_name";
                            $params = [
                                'username' => $username,
                                'email' => $email,
                                'role' => $role,
                                'first_name' => $first_name,
                                'last_name' => $last_name,
                                'city' => $city,
                                'state' => $state,
                                'country' => $country,
                                'date_of_birth' => $date_of_birth,
                                'grade' => $grade,
                                'address' => $address,
                                'gender' => $gender,
                                'school_name' => $school_name,
                                'user_id' => $user_id
                            ];

                            if (!empty($new_password)) {
                                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                                $sql .= ", password_hash = :password_hash";
                                $params['password_hash'] = $password_hash;
                            }
                            $sql .= " WHERE user_id = :user_id";

                            $stmt = $pdo->prepare($sql);
                            if ($stmt->execute($params)) {
                                $message = display_message("User updated successfully!", "success");
                            } else {
                                $message = display_message("Failed to update user.", "error");
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Edit User Error: " . $e->getMessage());
                        $message = display_message("Database error while updating user: " . htmlspecialchars($e->getMessage()), "error");
                    }
                }
                break;

            case 'delete':
                $user_id = sanitize_input($_POST['user_id'] ?? 0);
                if (empty($user_id)) {
                    $message = display_message("User ID is required to delete.", "error");
                } else {
                    if (function_exists('getUserId') && getUserId() == $user_id) {
                        $message = display_message("You cannot delete your own account.", "error");
                    } else {
                        try {
                            $pdo->beginTransaction();
                            $stmt = $pdo->prepare("DELETE FROM proctoring_logs WHERE user_id = :user_id");
                            $stmt->execute(['user_id' => $user_id]);
                            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
                            if ($stmt->execute(['user_id' => $user_id])) {
                                $pdo->commit();
                                $message = display_message("User and associated data deleted successfully!", "success");
                            } else {
                                $pdo->rollBack();
                                $message = display_message("Failed to delete user.", "error");
                            }
                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            error_log("Delete User Error: " . $e->getMessage());
                            $message = display_message("Database error while deleting user: " . htmlspecialchars($e->getMessage()), "error");
                        }
                    }
                }
                break;

            case 'send_payment_link':
                $user_id = sanitize_input($_POST['user_id'] ?? 0);
                $quiz_id = sanitize_input($_POST['quiz_id'] ?? 0);

                if (empty($user_id) || empty($quiz_id)) {
                    $message = display_message("User ID and Assessment ID are required.", "error");
                } else {
                    try {
                        // Fetch user and quiz details
                        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = :user_id");
                        $stmt->execute(['user_id' => $user_id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        $stmt = $pdo->prepare("SELECT title, assessment_fee FROM quizzes WHERE quiz_id = :quiz_id");
                        $stmt->execute(['quiz_id' => $quiz_id]);
                        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$user || !$quiz) {
                            $message = display_message("User or Assessment not found.", "error");
                        } else {
                            // Generate auto-login token
                            $auto_login_token = bin2hex(random_bytes(32));
                            $auto_login_token_expiry = date('Y-m-d H:i:s', strtotime('+2 weeks'));

                            // Update user with new auto-login token
                            $stmt = $pdo->prepare("UPDATE users SET auto_login_token = :token, auto_login_token_expiry = :expiry WHERE user_id = :user_id");
                            $stmt->execute([
                                'token' => $auto_login_token,
                                'expiry' => $auto_login_token_expiry,
                                'user_id' => $user_id
                            ]);

                            // Generate payment link
                            $payment_link = BASE_URL . "auth/payment.php?quiz_id=" . urlencode($quiz_id) . "&amount=" . urlencode($quiz['assessment_fee']) . "&token=" . urlencode($auto_login_token);

                            // Send email
                            ob_start();
                            require '../includes/email_templates/payment_link_email.php';
                            $email_body = ob_get_clean();
                            $email_body = str_replace('{{username}}', htmlspecialchars($user['username']), $email_body);
                            $email_body = str_replace('{{quiz_title}}', htmlspecialchars($quiz['title']), $email_body);
                            $email_body = str_replace('{{payment_link}}', $payment_link, $email_body);
                            $email_body = str_replace('{{amount}}', number_format($quiz['assessment_fee'], 2), $email_body);

                            $subject = "Payment Link for " . htmlspecialchars($quiz['title']) . " - Mackenny Assessment";

                            if (sendEmail($user['email'], $subject, $email_body)) {
                                $message = display_message("Payment link sent successfully to " . htmlspecialchars($user['email']) . "!", "success");
                            } else {
                                $message = display_message("Failed to send payment link email.", "error");
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Send Payment Link Error: " . $e->getMessage());
                        $message = display_message("Database error while sending payment link: " . htmlspecialchars($e->getMessage()), "error");
                    }
                }
                break;
        }
    }
}

// Fetch users with optional search and role filter
$search = sanitize_input($_GET['search'] ?? '');
$role_filter = sanitize_input($_GET['role'] ?? '');
$sql = "SELECT user_id, username, email, role, created_at, first_name, last_name, city, state, country, date_of_birth, grade, address, gender, school_name, passport_image_path FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (username LIKE :search_username OR email LIKE :search_email)";
    $params['search_username'] = '%' . $search . '%';
    $params['search_email'] = '%' . $search . '%';
}

if (!empty($role_filter) && in_array($role_filter, ['admin', 'student'])) {
    $sql .= " AND role = :role";
    $params['role'] = $role_filter;
} elseif (!empty($role_filter)) {
    error_log("Invalid role filter: $role_filter");
    $message = display_message("Invalid role filter provided.", "error");
}

$sql .= " ORDER BY created_at DESC";

try {
    error_log("Executing SQL: " . $sql . " with params: " . json_encode($params));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Users Error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params));
    $message = display_message("Could not fetch users. Error: " . htmlspecialchars($e->getMessage()), "error");
}

// Fetch all available quizzes for the payment link modal
$quizzes = [];
try {
    $stmt = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title");
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Quizzes Error: " . $e->getMessage());
    $message = display_message("Could not fetch assessments. Error: " . htmlspecialchars($e->getMessage()), "error");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 96%;
        }
        .modal {
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        .modal.hidden {
            opacity: 0;
            transform: scale(0.95);
        }
        .modal:not(.hidden) {
            opacity: 1;
            transform: scale(1);
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select, textarea {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        button {
            border-radius: 0.5rem;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            transition: background-color 0.3s ease-in-out, transform 0.1s ease-in-out;
        }
        button:hover {
            transform: translateY(-1px);
        }
        button.bg-indigo-600 {
            background-color: #4f46e5;
        }
        button.bg-indigo-600:hover {
            background-color: #4338ca;
        }
        button.bg-gray-500 {
            background-color: #6b7280;
        }
        button.bg-gray-500:hover {
            background-color: #4b5563;
        }
        button.bg-blue-500 {
            background-color: #3b82f6;
        }
        button.bg-blue-500:hover {
            background-color: #2563eb;
        }
        button.bg-red-500 {
            background-color: #ef4444;
        }
        button.bg-red-500:hover {
            background-color: #dc2626;
        }
        button.bg-green-500 {
            background-color: #10b981;
        }
        button.bg-green-500:hover {
            background-color: #059669;
        }
        button.bg-yellow-500 {
            background-color: #f59e0b;
        }
        button.bg-yellow-500:hover {
            background-color: #d97706;
        }
        table {
            border-collapse: separate;
            border-spacing: 0;
        }
        thead th {
            background-color: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            font-size: 0.875rem;
            color: #4b5563;
        }
        tbody td {
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            color: #374151;
        }
        tbody tr:nth-child(odd) {
            background-color: #f9fafb;
        }
        tbody tr:hover {
            background-color: #f3f4f6;
        }
        .user-link {
            cursor: pointer;
            color: #4f46e5;
            text-decoration: underline;
        }
        .user-link:hover {
            color: #4338ca;
        }
        #userDetailsModal .modal-content-wrapper {
            max-width: 90%;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .edit-field:read-only {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }
        .edit-field:not(:read-only) {
            background-color: #ffffff;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manage Users</h1>

    <?php echo $message; ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 space-y-4 sm:space-y-0">
            <h2 class="text-xl font-semibold text-gray-800">User List</h2>
            <button onclick="openAddUserModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300 w-full sm:w-auto">
                Add New User
            </button>
        </div>

        <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4 mb-4 items-end">
            <input type="text" id="search" placeholder="Search by username or email..."
                   class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                   value="<?php echo htmlspecialchars($search); ?>">
            <select id="role_filter"
                    class="w-full sm:w-auto py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Roles</option>
                <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="student" <?php echo ($role_filter === 'student') ? 'selected' : ''; ?>>Student</option>
            </select>
            <button id="searchButton" class="bg-gray-700 hover:bg-gray-800 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300 w-full sm:w-auto">
                Search
            </button>
        </div>

        <?php if (empty($users)): ?>
            <p class="text-gray-600 text-center py-4">No users found.</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Username</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Email</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Role</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Created At</th>
                            <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="py-3 px-4 text-sm text-gray-700">
                                    <span class="user-link" onclick="openUserDetailsModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm text_RAtext-gray-700"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700"><?php echo htmlspecialchars($user['role']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                                    <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                            class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-3 rounded-lg shadow-md transition duration-300 text-xs w-full sm:w-auto">
                                        Edit
                                    </button>
                                    <button onclick="openSendPaymentLinkModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                            class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-1 px-3 rounded-lg shadow-md transition duration-300 text-xs w-full sm:w-auto">
                                        Send Payment Link
                                    </button>
                                    <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user? This will also delete all associated proctoring logs.');" class="w-full sm:w-auto">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                        <button type="submit"
                                                class="bg-red-500 hover:bg-red-600 text-white font-semibold py-1 px-3 rounded-lg shadow-md transition duration-300 text-xs w-full">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50 modal">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md transform transition-all">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Add New User</h2>
        <form action="users.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add">
            <div>
                <label for="add_username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" id="add_username" name="username" required
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="add_email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="add_email" name="email" required
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="relative">
                <label for="add_password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="add_password" name="password" required
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button type="button" onclick="togglePassword('add_password', 'add_password_toggle')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none">
                    <i id="add_password_toggle" class="fas fa-eye"></i>
                </button>
            </div>
            <div>
                <label for="add_role" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
                <select id="add_role" name="role" required
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="student">Student</option>
                </select>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeAddUserModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">
                    Cancel
                </button>
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">
                    Add User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50 modal">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md transform transition-all">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Edit User</h2>
        <form action="users.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_user_id" name="user_id">
            <div>
                <label for="edit_username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" id="edit_username" name="username" required
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="edit_email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="edit_email" name="email" required
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="relative">
                <label for="edit_new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password (leave blank to keep current):</label>
                <input type="password" id="edit_new_password" name="new_password"
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button type="button" onclick="togglePassword('edit_new_password', 'edit_password_toggle')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none">
                    <i id="edit_password_toggle" class="fas fa-eye"></i>
                </button>
            </div>
            <div>
                <label for="edit_role" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
                <select id="edit_role" name="role" required
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="admin">Admin</option>
                    <option value="student">Student</option>
                </select>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeEditUserModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">
                    Cancel
                </button>
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Send Payment Link Modal -->
<div id="sendPaymentLinkModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50 modal">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md transform transition-all">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Send Payment Link</h2>
        <form action="users.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="send_payment_link">
            <input type="hidden" id="payment_user_id" name="user_id">
            <div>
                <label for="quiz_id" class="block text-gray-700 text-sm font-bold mb-2">Select Assessment:</label>
                <select id="quiz_id" name="quiz_id" required
                        class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select Assessment</option>
                    <?php foreach ($quizzes as $quiz): ?>
                        <option value="<?php echo htmlspecialchars($quiz['quiz_id']); ?>">
                            <?php echo htmlspecialchars($quiz['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeSendPaymentLinkModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">
                    Cancel
                </button>
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">
                    Send Payment Link
                </button>
            </div>
        </form>
    </div>
</div>

<!-- User Details Modal -->
<div id="userDetailsModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden z-50 modal p-4">
    <div class="bg-white rounded-xl shadow-2xl transform transition-all modal-content-wrapper w-full max-w-2xl mx-auto overflow-hidden">
        <div class="relative p-6 text-white text-center" style="background-color: #1a202c;">
            <h2 class="text-3xl font-bold mb-1">User Profile</h2>
            <p class="text-gray-300">Detailed information about the user</p>
            <button type="button" onclick="closeUserDetailsModal()" class="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-8 space-y-6 text-gray-800">
            <div id="detail_passport_image_container" class="mb-6 text-center">
                <img id="detail_passport_image" src="<?php echo $placeholder_image_url; ?>" alt="Passport/ID Image" class="w-32 h-32 object-cover rounded-full shadow-lg border-4 border-gray-300 mx-auto transform transition-transform duration-300 hover:scale-105">
            </div>
            
            <form id="editUserForm" action="users.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="detail_user_id" name="user_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Username:</label>
                        <input type="text" id="detail_username" name="username" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Email:</label>
                        <input type="email" id="detail_email" name="email" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Role:</label>
                        <select id="detail_role" name="role" 
                                class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" disabled>
                            <option value="admin">Admin</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">First Name:</label>
                        <input type="text" id="detail_first_name" name="first_name" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Last Name:</label>
                        <input type="text" id="detail_last_name" name="last_name" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Date of Birth:</label>
                        <input type="date" id="detail_date_of_birth" name="date_of_birth" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Gender:</label>
                        <select id="detail_gender" name="gender" 
                                class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" disabled>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Grade:</label>
                        <select id="detail_grade" name="grade" 
                                class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" disabled>
                            <option value="">Select Grade</option>
                            <option value="Grade 1">Grade 1</option>
                            <option value="Grade 2">Grade 2</option>
                            <option value="Grade 3">Grade 3</option>
                            <option value="Grade 4">Grade 4</option>
                            <option value="Grade 5">Grade 5</option>
                            <option value="Grade 6">Grade 6</option>
                            <option value="Grade 7">Grade 7</option>
                            <option value="Grade 8">Grade 8</option>
                            <option value="Grade 9">Grade 9</option>
                            <option value="Grade 10">Grade 10</option>
                            <option value="Grade 11">Grade 11</option>
                            <option value="Grade 12">Grade 12</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-1">School Name:</label>
                        <input type="text" id="detail_school_name" name="school_name" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">City:</label>
                        <input type="text" id="detail_city" name="city" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">State:</label>
                        <input type="text" id="detail_state" name="state" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Country:</label>
                        <input type="text" id="detail_country" name="country" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-1">Address:</label>
                        <textarea id="detail_address" name="address" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 edit-field" readonly></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-1">Account Created:</label>
                        <input type="text" id="detail_created_at" 
                               class="w-full py-2 px-3 border rounded-lg shadow-sm bg-gray-100" readonly>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-1">Paid Assessments:</label>
                        <div id="paid_assessments" class="w-full py-2 px-3 border rounded-lg shadow-sm bg-gray-100"></div>
                    </div>
                </div>
                
                <div class="hidden" id="editControls">
                    <div class="relative mt-4">
                        <label class="block text-gray-700 text-sm font-bold mb-1">New Password (leave blank to keep current):</label>
                        <input type="password" id="detail_new_password" name="new_password"
                               class="w-full py-2 px-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="button" onclick="togglePassword('detail_new_password', 'detail_password_toggle')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none">
                            <i id="detail_password_toggle" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="bg-gray-50 px-8 py-5 flex justify-between">
            <button type="button" id="editToggleBtn" onclick="toggleEditMode()"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-6 rounded-lg shadow-sm transition duration-300 ease-in-out transform hover:-translate-y-0.5">
                Edit User
            </button>
            <div class="hidden space-x-4" id="formActionButtons">
                <button type="button" onclick="toggleEditMode(false)"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow-sm transition duration-300 ease-in-out">
                    Cancel
                </button>
                <button type="button" onclick="submitUserForm()"
                        class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-6 rounded-lg shadow-sm transition duration-300 ease-in-out transform hover:-translate-y-0.5">
                    Save Changes
                </button>
            </div>
            <button type="button" onclick="closeUserDetailsModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow-sm transition duration-300 ease-in-out">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    const ADMIN_PROFILE_UPLOAD_DIR = '<?php echo $admin_profile_upload_dir; ?>';
    const STUDENT_PASSPORT_UPLOAD_DIR = '<?php echo $student_passport_upload_dir; ?>';
    const PLACEHOLDER_IMAGE = '<?php echo $placeholder_image_url; ?>';

    let isEditMode = false;
    let originalUserData = {};

    function openEditUserModal(user) {
        document.getElementById('edit_user_id').value = user.user_id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email(None);
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_new_password').value = '';
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }

    function openAddUserModal() {
        document.getElementById('addUserModal').classList.remove('hidden');
        document.getElementById('add_username').value = '';
        document.getElementById('add_email').value = '';
        document.getElementById('add_password').value = '';
        document.getElementById('add_role').value = '';
    }

    function closeAddUserModal() {
        document.getElementById('addUserModal').classList.add('hidden');
    }

    function openSendPaymentLinkModal(user) {
        document.getElementById('payment_user_id').value = user.user_id;
        document.getElementById('quiz_id').value = '';
        document.getElementById('sendPaymentLinkModal').classList.remove('hidden');
    }

    function closeSendPaymentLinkModal() {
        document.getElementById('sendPaymentLinkModal').classList.add('hidden');
    }

    function togglePassword(inputId, toggleId) {
        const input = document.getElementById(inputId);
        const toggle = document.getElementById(toggleId);
        if (input.type === 'password') {
            input.type = 'text';
            toggle.classList.remove('fa-eye');
            toggle.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            toggle.classList.remove('fa-eye-slash');
            toggle.classList.add('fa-eye');
        }
    }

    function applySearchAndFilter() {
        const search = document.getElementById('search').value;
        const role = document.getElementById('role_filter').value;
        window.location.href = `users.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}`;
    }

    function openUserDetailsModal(user) {
        // Store original data for cancel functionality
        originalUserData = JSON.parse(JSON.stringify(user));
        
        // Set form values
        document.getElementById('detail_user_id').value = user.user_id;
        document.getElementById('detail_username').value = user.username || '';
        document.getElementById('detail_email').value = user.email || '';
        document.getElementById('detail_role').value = user.role || 'student';
        document.getElementById('detail_first_name').value = user.first_name || '';
        document.getElementById('detail_last_name').value = user.last_name || '';
        document.getElementById('detail_city').value = user.city || '';
        document.getElementById('detail_state').value = user.state || '';
        document.getElementById('detail_country').value = user.country || '';
        document.getElementById('detail_date_of_birth').value = user.date_of_birth || '';
        document.getElementById('detail_grade').value = user.grade || '';
        document.getElementById('detail_address').value = user.address || '';
        document.getElementById('detail_gender').value = user.gender || '';
        document.getElementById('detail_school_name').value = user.school_name || '';
        document.getElementById('detail_created_at').value = user.created_at || '';

        // Handle passport image
        const passportImage = document.getElementById('detail_passport_image');
        const passportImageContainer = document.getElementById('detail_passport_image_container');
        let imagePath = '';

        if (user.passport_image_path) {
            if (user.role === 'admin') {
                imagePath = ADMIN_PROFILE_UPLOAD_DIR + user.passport_image_path;
            } else if (user.role === 'student') {
                imagePath = STUDENT_PASSPORT_UPLOAD_DIR + user.passport_image_path;
            }
            passportImage.src = imagePath;
            passportImage.alt = `Profile image for ${user.username}`;
            passportImage.onerror = function() {
                passportImage.src = PLACEHOLDER_IMAGE;
                passportImage.alt = 'Placeholder image';
            };
        } else {
            passportImage.src = PLACEHOLDER_IMAGE;
            passportImage.alt = 'Placeholder image';
        }
        passportImageContainer.classList.remove('hidden');

        // Fetch and display paid assessments
        fetchPaidAssessments(user.user_id);

        // Reset edit mode
        toggleEditMode(false);
        
        // Show modal
        document.getElementById('userDetailsModal').classList.remove('hidden');
    }

    function fetchPaidAssessments(userId) {
        fetch('get_paid_assessments.php?user_id=' + encodeURIComponent(userId))
            .then(response => response.json())
            .then(data => {
                const paidAssessmentsDiv = document.getElementById('paid_assessments');
                if (data.error || data.length === 0) {
                    paidAssessmentsDiv.innerHTML = '<p class="text-gray-600">No paid assessments found.</p>';
                } else {
                    let html = '<ul class="list-disc list-inside">';
                    data.forEach(assessment => {
                        html += `<li>${assessment.title} - Paid on ${assessment.payment_date}</li>`;
                    });
                    html += '</ul>';
                    paidAssessmentsDiv.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error fetching paid assessments:', error);
                document.getElementById('paid_assessments').innerHTML = '<p class="text-red-600">Error loading paid assessments.</p>';
            });
    }

    function toggleEditMode(enable) {
        isEditMode = enable !== undefined ? enable : !isEditMode;
        
        const editFields = document.querySelectorAll('.edit-field');
        editFields.forEach(field => {
            if (field.tagName === 'SELECT') {
                field.disabled = !isEditMode;
            } else {
                field.readOnly = !isEditMode;
            }
            
            if (isEditMode) {
                field.classList.remove('bg-gray-100');
                field.classList.add('bg-white');
            } else {
                field.classList.remove('bg-white');
                field.classList.add('bg-gray-100');
            }
        });
        
        document.getElementById('editControls').classList.toggle('hidden', !isEditMode);
        document.getElementById('editToggleBtn').classList.toggle('hidden', isEditMode);
        document.getElementById('formActionButtons').classList.toggle('hidden', !isEditMode);
    }

    function submitUserForm() {
        const form =
 document.getElementById('editUserForm');
        const formData = new FormData(form);
        
        fetch('users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                return response.text();
            }
            throw new Error('Network response was not ok');
        })
        .then(data => {
            // Reload the page to see changes
            window.location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the user data.');
        });
    }

    function resetFormToOriginal() {
        const user = originalUserData;
        document.getElementById('detail_username').value = user.username || '';
        document.getElementById('detail_email').value = user.email || '';
        document.getElementById('detail_role').value = user.role || 'student';
        document.getElementById('detail_first_name').value = user.first_name || '';
        document.getElementById('detail_last_name').value = user.last_name || '';
        document.getElementById('detail_city').value = user.city || '';
        document.getElementById('detail_state').value = user.state || '';
        document.getElementById('detail_country').value = user.country || '';
        document.getElementById('detail_date_of_birth').value = user.date_of_birth || '';
        document.getElementById('detail_grade').value = user.grade || '';
        document.getElementById('detail_address').value = user.address || '';
        document.getElementById('detail_gender').value = user.gender || '';
        document.getElementById('detail_school_name').value = user.school_name || '';
    }

    function closeUserDetailsModal() {
        document.getElementById('userDetailsModal').classList.add('hidden');
    }

    document.getElementById('searchButton').addEventListener('click', applySearchAndFilter);
    document.getElementById('search').addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            applySearchAndFilter();
        }
    });

    window.addEventListener('click', function(event) {
        const addUserModal = document.getElementById('addUserModal');
        const editUserModal = document.getElementById('editUserModal');
        const userDetailsModal = document.getElementById('userDetailsModal');
        const sendPaymentLinkModal = document.getElementById('sendPaymentLinkModal');
        if (event.target === addUserModal) {
            closeAddUserModal();
        }
        if (event.target === editUserModal) {
            closeEditUserModal();
        }
        if (event.target === userDetailsModal) {
            closeUserDetailsModal();
        }
        if (event.target === sendPaymentLinkModal) {
            closeSendPaymentLinkModal();
        }
    });

    document.querySelector('#addUserModal > div').addEventListener('click', function(event) {
        event.stopPropagation();
    });
    document.querySelector('#editUserModal > div').addEventListener('click', function(event) {
        event.stopPropagation();
    });
    document.querySelector('#userDetailsModal .modal-content-wrapper').addEventListener('click', function(event) {
        event.stopPropagation();
    });
    document.querySelector('#sendPaymentLinkModal > div').addEventListener('click', function(event) {
        event.stopPropagation();
    });
</script>

<?php
require_once '../includes/footer_admin.php';
?>