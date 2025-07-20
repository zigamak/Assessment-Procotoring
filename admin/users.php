<?php
// admin/users.php
// Page to add, edit, and delete Admin and Student accounts.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
// This file is assumed to handle session start, role checks, and potentially
// define functions like getUserId().
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$users = [];   // Array to hold fetched users

// Ensure $pdo is available from db.php
if (!isset($pdo)) {
    // Handle error if PDO connection is not established
    error_log("Database connection (PDO) not available in users.php.");
    $message = display_message("Database connection error. Please try again later.", "error");
    // You might want to exit here or redirect if the DB connection is critical.
    // exit();
}

// Handle form submissions for adding, editing, and deleting users
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
                        // Check if username or email already exists
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

                if (empty($user_id) || empty($username) || empty($email) || empty($role)) {
                    $message = display_message("All fields are required to edit a user.", "error");
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = display_message("Invalid email format.", "error");
                } elseif ($role !== 'admin' && $role !== 'student') {
                    $message = display_message("Invalid role specified.", "error");
                } else {
                    try {
                        // Check for duplicate username/email excluding the current user
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email) AND user_id != :user_id");
                        $stmt->execute(['username' => $username, 'email' => $email, 'user_id' => $user_id]);
                        if ($stmt->fetchColumn() > 0) {
                            $message = display_message("Username or email already exists for another user.", "error");
                        } else {
                            $sql = "UPDATE users SET username = :username, email = :email, role = :role";
                            $params = [
                                'username' => $username,
                                'email' => $email,
                                'role' => $role,
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
                    // Prevent admin from deleting themselves
                    // Assuming getUserId() is available and returns the current logged-in user's ID
                    if (function_exists('getUserId') && getUserId() == $user_id) {
                        $message = display_message("You cannot delete your own account.", "error");
                    } else {
                        try {
                            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
                            if ($stmt->execute(['user_id' => $user_id])) {
                                $message = display_message("User deleted successfully!", "success");
                            } else {
                                $message = display_message("Failed to delete user.", "error");
                            }
                        } catch (PDOException $e) {
                            error_log("Delete User Error: " . $e->getMessage());
                            $message = display_message("Database error while deleting user: " . htmlspecialchars($e->getMessage()), "error");
                        }
                    }
                }
                break;
        }
    }
}

// Fetch users with optional search and role filter
$search = sanitize_input($_GET['search'] ?? '');
$role_filter = sanitize_input($_GET['role'] ?? '');
$sql = "SELECT user_id, username, email, role, created_at FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    // Fix for SQLSTATE[HY093]: Invalid parameter number - use unique named parameters
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <!-- Include Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include Font Awesome for icons (e.g., eye icon for password toggle) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Assuming you have a main.css or similar in your css folder for custom styles -->
    <!-- <link rel="stylesheet" href="../css/main.css"> -->
    <!-- <link rel="stylesheet" href="../css/admin.css"> -->
    <style>
        /* Custom styles for better aesthetics and responsiveness */
        body {
            font-family: 'Inter', sans-serif; /* Use Inter font */
        }
        .container {
            max-width: 96%; /* Fluid width for responsiveness */
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
        /* Ensure inputs and selects have consistent styling */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            padding: 0.75rem 1rem; /* Adjust padding */
            border-radius: 0.5rem; /* More rounded corners */
            border: 1px solid #d1d5db; /* Light gray border */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* Subtle shadow */
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #6366f1; /* Indigo focus ring */
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); /* Soft focus ring */
        }
        /* Button styling for consistency */
        button {
            border-radius: 0.5rem; /* Rounded corners for buttons */
            padding: 0.6rem 1.2rem; /* Adjusted padding for buttons */
            font-weight: 600; /* Semi-bold text */
            transition: background-color 0.3s ease-in-out, transform 0.1s ease-in-out;
        }
        button:hover {
            transform: translateY(-1px); /* Slight lift on hover */
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
        /* Table styling */
        table {
            border-collapse: separate; /* Allows border-radius on table */
            border-spacing: 0;
        }
        thead th {
            background-color: #f3f4f6; /* Light gray header */
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            font-size: 0.875rem; /* sm text */
            color: #4b5563; /* Gray-600 */
        }
        tbody td {
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem; /* sm text */
            color: #374151; /* Gray-700 */
        }
        tbody tr:nth-child(odd) {
            background-color: #f9fafb; /* Slightly darker for odd rows */
        }
        tbody tr:hover {
            background-color: #f3f4f6; /* Hover effect for rows */
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manage Users</h1>

    <?php echo $message; // Display feedback messages ?>

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
                                <td class="py-3 px-4 text-sm text-gray-700"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700"><?php echo htmlspecialchars($user['role']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                                    <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                            class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1 px-3 rounded-lg shadow-md transition duration-300 text-xs w-full sm:w-auto">
                                        Edit
                                    </button>
                                    <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" class="w-full sm:w-auto">
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

<script>
    // JavaScript for handling the Edit User Modal
    function openEditUserModal(user) {
        document.getElementById('edit_user_id').value = user.user_id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_new_password').value = ''; // Clear password field on open
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }

    // JavaScript for handling the Add New User Modal
    function openAddUserModal() {
        document.getElementById('addUserModal').classList.remove('hidden');
        // Clear form fields when opening the add modal
        document.getElementById('add_username').value = '';
        document.getElementById('add_email').value = '';
        document.getElementById('add_password').value = '';
        document.getElementById('add_role').value = '';
    }

    function closeAddUserModal() {
        document.getElementById('addUserModal').classList.add('hidden');
    }

    // Toggle password visibility
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

    // Function to trigger search and filter
    function applySearchAndFilter() {
        const search = document.getElementById('search').value;
        const role = document.getElementById('role_filter').value;
        window.location.href = `users.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}`;
    }

    // Event listener for the new Search button
    document.getElementById('searchButton').addEventListener('click', applySearchAndFilter);

    // Optional: Allow pressing Enter in the search field to trigger search
    document.getElementById('search').addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault(); // Prevent default form submission if any
            applySearchAndFilter();
        }
    });

    // Handle modal closing when clicking outside the modal content
    window.addEventListener('click', function(event) {
        const addUserModal = document.getElementById('addUserModal');
        const editUserModal = document.getElementById('editUserModal');

        if (event.target === addUserModal) {
            closeAddUserModal();
        }
        if (event.target === editUserModal) {
            closeEditUserModal();
        }
    });

    // Prevent modal from closing when clicking inside the modal content (optional, but good practice)
    document.querySelector('#addUserModal > div').addEventListener('click', function(event) {
        event.stopPropagation();
    });
    document.querySelector('#editUserModal > div').addEventListener('click', function(event) {
        event.stopPropagation();
    });

</script>

<?php
// Include the admin specific footer
// This file is assumed to contain closing HTML tags like </body> and </html>
require_once '../includes/footer_admin.php';
?>
