<?php
// admin/manage_users.php
// Page to add, edit, and delete Admin and Student accounts.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$users = [];   // Array to hold fetched users

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
                        $message = display_message("Database error while adding user.", "error");
                    }
                }
                break;

            case 'edit':
                $user_id = sanitize_input($_POST['user_id'] ?? 0);
                $username = sanitize_input($_POST['username'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $role = sanitize_input($_POST['role'] ?? '');
                $new_password = sanitize_input($_POST['new_password'] ?? ''); // Optional

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
                        $message = display_message("Database error while updating user.", "error");
                    }
                }
                break;

            case 'delete':
                $user_id = sanitize_input($_POST['user_id'] ?? 0);
                if (empty($user_id)) {
                    $message = display_message("User ID is required to delete.", "error");
                } else {
                    // Prevent admin from deleting themselves
                    if (getUserId() == $user_id) {
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
                            $message = display_message("Database error while deleting user.", "error");
                        }
                    }
                }
                break;
        }
    }
}

// Fetch all users for display
try {
    $stmt = $pdo->query("SELECT user_id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Users Error: " . $e->getMessage());
    $message = display_message("Could not fetch users. Please try again later.", "error");
}
?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Manage Users</h1>

    <?php echo $message; // Display any feedback messages ?>

    <!-- Add New User Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add New User</h2>
        <form action="manage_users.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="add">
            <div>
                <label for="add_username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" id="add_username" name="username" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="add_email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="add_email" name="email" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="add_password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="add_password" name="password" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="add_role" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
                <select id="add_role" name="role" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="student">Student</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Add User
                </button>
            </div>
        </form>
    </div>

    <!-- User List Table -->
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Existing Users</h2>
        <?php if (empty($users)): ?>
            <p class="text-gray-600">No users found.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['user_id']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['created_at']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                    class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                            <form action="manage_users.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Edit User Modal (Hidden by default) -->
<div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Edit User</h2>
        <form action="manage_users.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_user_id" name="user_id">
            <div>
                <label for="edit_username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" id="edit_username" name="username" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="edit_email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="edit_email" name="email" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="edit_role" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
                <select id="edit_role" name="role" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                    <option value="admin">Admin</option>
                    <option value="student">Student</option>
                </select>
            </div>
            <div>
                <label for="edit_new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password (leave blank to keep current):</label>
                <input type="password" id="edit_new_password" name="new_password"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeEditUserModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Cancel
                </button>
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
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
        document.getElementById('edit_new_password').value = ''; // Clear password field
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }
</script>

<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>
