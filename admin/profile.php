<?php
// admin/profile.php
// Enhanced Admin Profile page with improved design and name fields

require_once '../includes/header_admin.php'; // Includes session, db, functions, and enforces admin role
require_once '../includes/db.php'; // Ensure pdo is available
require_once '../includes/functions.php'; // Ensure functions are available

$message = ''; // Initialize message variable for feedback
$user_id = $_SESSION['user_id'] ?? null;
$user_details = null;

// Define allowed image types and upload directory
$allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

// --- START: Absolute Path for Upload Directory ---
// Define BASE_DIR if not already defined (e.g., in functions.php or config)
if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/..'));
}
$upload_dir = BASE_DIR . '/uploads/admin_profiles/'; // Dedicated directory for admin profile images

// Ensure the upload directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) { // 0755 permissions, recursive creation
        error_log("Failed to create admin profile upload directory: " . $upload_dir);
        $message = display_message("Profile image upload directory does not exist and could not be created. Please contact support.", "error");
    }
}
// --- END: Absolute Path for Upload Directory ---

// Function to fetch user details (updated to include first_name and last_name)
function fetchUserDetails($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, role, passport_image_path, city, state, country, first_name, last_name FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admin profile: " . $e->getMessage());
        return null;
    }
}

// Fetch current user data initially
if ($user_id) {
    $user_details = fetchUserDetails($pdo, $user_id);
    if (!$user_details) {
        $message = display_message("User data not found. Please log in again.", "error");
        // Redirect to logout or login page if user data cannot be fetched
        redirect(BASE_URL . 'auth/logout.php');
    }
} else {
    $message = display_message("User not identified. Please log in again.", "error");
    redirect(BASE_URL . 'auth/logout.php');
}

// Handle POST requests for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_details) {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'update_personal_info':
                $first_name = sanitize_input($_POST['first_name'] ?? '');
                $last_name = sanitize_input($_POST['last_name'] ?? '');
                $new_city = sanitize_input($_POST['city'] ?? '');
                $new_state = sanitize_input($_POST['state'] ?? '');
                $new_country = sanitize_input($_POST['country'] ?? '');

                try {
                    $stmt_update_info = $pdo->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, city = :city, state = :state, country = :country WHERE user_id = :user_id");
                    if ($stmt_update_info->execute([
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'city' => $new_city,
                        'state' => $new_state,
                        'country' => $new_country,
                        'user_id' => $user_id
                    ])) {
                        $message = display_message("Personal information updated successfully!", "success");
                        // Re-fetch user details to update the displayed values
                        $user_details = fetchUserDetails($pdo, $user_id);
                    } else {
                        $message = display_message("Failed to update personal information.", "error");
                    }
                } catch (PDOException $e) {
                    error_log("Update Personal Info Error: " . $e->getMessage());
                    $message = display_message("Database error while updating personal information.", "error");
                }
                break;

            case 'update_email':
                $new_email = sanitize_input($_POST['email'] ?? '');

                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $message = display_message("Invalid email format.", "error");
                } elseif (empty($new_email)) {
                    $message = display_message("Email cannot be empty.", "error");
                } else {
                    try {
                        // Check if the new email already exists for another user (excluding current user)
                        $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id");
                        $stmt_check_email->execute(['email' => $new_email, 'user_id' => $user_id]);
                        if ($stmt_check_email->fetchColumn() > 0) {
                            $message = display_message("This email is already registered by another user.", "error");
                        } else {
                            $stmt_update_email = $pdo->prepare("UPDATE users SET email = :email WHERE user_id = :user_id");
                            if ($stmt_update_email->execute(['email' => $new_email, 'user_id' => $user_id])) {
                                $_SESSION['email'] = $new_email; // Update session with new email
                                $message = display_message("Email updated successfully!", "success");
                                // Re-fetch user details to update the displayed values
                                $user_details = fetchUserDetails($pdo, $user_id);
                            } else {
                                $message = display_message("Failed to update email.", "error");
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Update Email Error: " . $e->getMessage());
                        $message = display_message("Database error while updating email.", "error");
                    }
                }
                break;

            case 'change_password':
                $current_password = sanitize_input($_POST['current_password'] ?? '');
                $new_password = sanitize_input($_POST['new_password'] ?? '');
                $confirm_new_password = sanitize_input($_POST['confirm_new_password'] ?? '');

                if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
                    $message = display_message("All password fields are required.", "error");
                } elseif ($new_password !== $confirm_new_password) {
                    $message = display_message("New password and confirmation do not match.", "error");
                } elseif (strlen($new_password) < 8) {
                    $message = display_message("New password must be at least 8 characters long.", "error");
                } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
                    $message = display_message("Password must contain at least one uppercase letter, one lowercase letter, and one number.", "error");
                } else {
                    try {
                        // Verify current password
                        if ($user_details && password_verify($current_password, $user_details['password_hash'])) {
                            // Hash the new password
                            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                            $stmt_update_password = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id");
                            if ($stmt_update_password->execute(['password_hash' => $new_password_hash, 'user_id' => $user_id])) {
                                $message = display_message("Password changed successfully!", "success");
                            } else {
                                $message = display_message("Failed to change password.", "error");
                            }
                        } else {
                            $message = display_message("Incorrect current password.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Change Password Error: " . $e->getMessage());
                        $message = display_message("Database error while changing password.", "error");
                    }
                }
                break;

            case 'upload_profile_image':
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['profile_image']['name'];
                    $file_tmp = $_FILES['profile_image']['tmp_name'];
                    $file_size = $_FILES['profile_image']['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (!in_array($file_ext, $allowed_types)) {
                        $message = display_message("Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.", "error");
                    } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                        $message = display_message("File size exceeds 5MB limit.", "error");
                    } else {
                        // Generate a unique file name to prevent overwrites
                        $new_file_name = uniqid('admin_profile_') . '.' . $file_ext;
                        // Use the absolute path for destination
                        $destination = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp, $destination)) {
                            try {
                                // Delete old image if it exists
                                if (!empty($user_details['passport_image_path'])) {
                                    $old_image = $upload_dir . $user_details['passport_image_path'];
                                    if (file_exists($old_image)) {
                                        unlink($old_image);
                                    }
                                }

                                // Update database with the new image path
                                $stmt_update_image = $pdo->prepare("UPDATE users SET passport_image_path = :path WHERE user_id = :user_id");
                                if ($stmt_update_image->execute(['path' => $new_file_name, 'user_id' => $user_id])) {
                                    $message = display_message("Profile image uploaded successfully!", "success");
                                    // Re-fetch user details to update the displayed values
                                    $user_details = fetchUserDetails($pdo, $user_id);
                                } else {
                                    $message = display_message("Failed to update image path in database.", "error");
                                    unlink($destination); // Delete uploaded file if DB update fails
                                }
                            } catch (PDOException $e) {
                                error_log("Upload Image DB Error: " . $e->getMessage());
                                $message = display_message("Database error while uploading image.", "error");
                                unlink($destination); // Delete uploaded file on DB error
                            }
                        } else {
                            $message = display_message("Failed to upload image. Please check server permissions.", "error");
                            error_log("Failed to move uploaded file: " . $file_tmp . " to " . $destination);
                        }
                    }
                } else {
                    // Check for specific upload errors
                    if (isset($_FILES['profile_image']['error']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $phpFileUploadErrors = array(
                            UPLOAD_ERR_OK => 'There is no error, the file uploaded with success',
                            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
                        );
                        $message = display_message("File upload error: " . ($phpFileUploadErrors[$_FILES['profile_image']['error']] ?? 'Unknown error'), "error");
                    } else {
                        $message = display_message("Please select an image to upload.", "error");
                    }
                }
                break;
        }
    }
}

// Ensure $user_details is available for display after any updates
if (!$user_details) {
    $user_details = fetchUserDetails($pdo, $user_id); // Re-fetch if it became null due to an error
}

// Helper function to get full name
function getFullName($user_details) {
    $first_name = trim($user_details['first_name'] ?? '');
    $last_name = trim($user_details['last_name'] ?? '');
    
    if (!empty($first_name) && !empty($last_name)) {
        return $first_name . ' ' . $last_name;
    } elseif (!empty($first_name)) {
        return $first_name;
    } elseif (!empty($last_name)) {
        return $last_name;
    } else {
        return $user_details['username'] ?? 'Unknown User';
    }
}

?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-8">
    <div class="container mx-auto px-4">
        <?php if (isset($message)) echo $message; // Display any feedback messages ?>
        
        <div class="max-w-4xl mx-auto">
            <!-- Profile Header -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-12 text-white relative">
                    <div class="absolute top-0 left-0 w-full h-full bg-black opacity-10"></div>
                    <div class="relative z-10 flex flex-col md:flex-row items-center space-y-6 md:space-y-0 md:space-x-8">
                        <?php
                        // Construct the full URL for the image
                        $image_path_from_db = $user_details['passport_image_path'] ?? '';
                        $display_image_url = BASE_URL . 'uploads/admin_profiles/' . $image_path_from_db;
                        
                        // Check if the file actually exists on the server
                        $absolute_image_path = $upload_dir . $image_path_from_db;
                        
                        if (empty($image_path_from_db) || !file_exists($absolute_image_path)) {
                            $display_image_url = 'https://ui-avatars.com/api/?name=' . urlencode(getFullName($user_details)) . '&size=200&background=3b82f6&color=ffffff&bold=true';
                        }
                        ?>
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars($display_image_url); ?>" alt="Profile Picture" 
                                 class="w-32 h-32 md:w-40 md:h-40 rounded-full object-cover border-4 border-white shadow-lg">
                            <div class="absolute -bottom-2 -right-2 bg-green-500 w-8 h-8 rounded-full border-4 border-white"></div>
                        </div>
                        <div class="text-center md:text-left">
                            <h1 class="text-3xl md:text-4xl font-bold mb-2"><?php echo htmlspecialchars(getFullName($user_details)); ?></h1>
                            <p class="text-xl opacity-90 mb-1">@<?php echo htmlspecialchars($user_details['username']); ?></p>
                            <p class="text-lg opacity-75 capitalize mb-3"><?php echo htmlspecialchars($user_details['role']); ?> Administrator</p>
                            <div class="flex flex-wrap justify-center md:justify-start gap-2 text-sm opacity-90">
                                <?php if (!empty($user_details['city'])): ?>
                                    <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                        📍 <?php echo htmlspecialchars($user_details['city']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($user_details['country'])): ?>
                                    <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                        🌍 <?php echo htmlspecialchars($user_details['country']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Management Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Personal Information Card -->
                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800">Personal Information</h2>
                    </div>
                    
                    <form action="profile.php" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_personal_info">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user_details['first_name'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user_details['last_name'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>
                        </div>
                        
                        <div>
                            <label for="city" class="block text-sm font-semibold text-gray-700 mb-2">City</label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($user_details['city'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="state" class="block text-sm font-semibold text-gray-700 mb-2">State/Region</label>
                                <input type="text" id="state" name="state" 
                                       value="<?php echo htmlspecialchars($user_details['state'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>
                            <div>
                                <label for="country" class="block text-sm font-semibold text-gray-700 mb-2">Country</label>
                                <input type="text" id="country" name="country" 
                                       value="<?php echo htmlspecialchars($user_details['country'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-blue-700 hover:to-indigo-700 transition duration-300 transform hover:scale-105">
                            Update Personal Information
                        </button>
                    </form>
                </div>

                <!-- Profile Picture Card -->
                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800">Profile Picture</h2>
                    </div>
                    
                    <div class="text-center mb-6">
                        <img src="<?php echo htmlspecialchars($display_image_url); ?>" alt="Current Profile Picture" 
                             class="w-32 h-32 rounded-full object-cover mx-auto border-4 border-gray-200 shadow-lg">
                    </div>
                    
                    <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="upload_profile_image">
                        
                        <div>
                            <label for="profile_image" class="block text-sm font-semibold text-gray-700 mb-2">Select New Image</label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-green-500 transition duration-200">
                                <input type="file" id="profile_image" name="profile_image" accept="image/*" required
                                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                                <p class="text-xs text-gray-500 mt-2">JPG, JPEG, PNG, GIF up to 5MB</p>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-green-700 hover:to-emerald-700 transition duration-300 transform hover:scale-105">
                            Upload New Picture
                        </button>
                    </form>
                </div>

                <!-- Email Update Card -->
                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800">Email Address</h2>
                    </div>
                    
                    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-600">Current Email:</p>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user_details['email']); ?></p>
                    </div>
                    
                    <form action="profile.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_email">
                        
                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">New Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition duration-200">
                        </div>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-purple-700 hover:to-pink-700 transition duration-300 transform hover:scale-105">
                            Update Email Address
                        </button>
                    </form>
                </div>

                <!-- Password Change Card -->
                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800">Security</h2>
                    </div>
                    
                    <form action="profile.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200">
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                            <input type="password" id="new_password" name="new_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200">
                            <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters with uppercase, lowercase, and number</p>
                        </div>
                        
                        <div>
                            <label for="confirm_new_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200">
                        </div>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-pink-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-red-700 hover:to-pink-700 transition duration-300 transform hover:scale-105">
                            Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add some interactive features
document.addEventListener('DOMContentLoaded', function() {
    // Profile image preview
    const profileImageInput = document.getElementById('profile_image');
    const currentProfileImage = document.querySelector('img[alt="Current Profile Picture"]');
    
    if (profileImageInput && currentProfileImage) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    currentProfileImage.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Password strength indicator
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_new_password');
    
    if (newPasswordInput) {
        const strengthIndicator = document.createElement('div');
        strengthIndicator.className = 'mt-2 text-xs';
        newPasswordInput.parentNode.appendChild(strengthIndicator);
        
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            updateStrengthIndicator(strengthIndicator, strength);
        });
    }
    
    if (confirmPasswordInput && newPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = newPasswordInput.value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('border-red-500');
                this.classList.remove('border-green-500');
            } else {
                this.setCustomValidity('');
                this.classList.remove('border-red-500');
                if (confirmPassword) {
                    this.classList.add('border-green-500');
                }
            }
        });
    }
    
    // Form validation feedback
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing...';
                submitButton.disabled = true;
            }
        });
    });
    
    // Auto-hide success messages
    const successMessages = document.querySelectorAll('.alert-success, .bg-green-100');
    successMessages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            setTimeout(() => {
                message.remove();
            }, 500);
        }, 5000);
    });
});

function calculatePasswordStrength(password) {
    let strength = 0;
    const checks = [
        { regex: /.{8,}/, label: 'At least 8 characters' },
        { regex: /[a-z]/, label: 'Lowercase letter' },
        { regex: /[A-Z]/, label: 'Uppercase letter' },
        { regex: /[0-9]/, label: 'Number' },
        { regex: /[^A-Za-z0-9]/, label: 'Special character' }
    ];
    
    const passed = checks.filter(check => check.regex.test(password));
    return {
        score: passed.length,
        total: checks.length,
        checks: checks.map(check => ({
            ...check,
            passed: check.regex.test(password)
        }))
    };
}

function updateStrengthIndicator(indicator, strength) {
    const colors = ['text-red-500', 'text-red-400', 'text-yellow-500', 'text-green-400', 'text-green-500'];
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    
    indicator.innerHTML = `
        <div class="flex items-center space-x-2">
            <div class="flex space-x-1">
                ${Array.from({length: 5}, (_, i) => 
                    `<div class="w-2 h-2 rounded-full ${i < strength.score ? colors[strength.score - 1] : 'bg-gray-300'}"></div>`
                ).join('')}
            </div>
            <span class="${colors[strength.score - 1] || 'text-gray-400'}">${labels[strength.score - 1] || 'Enter password'}</span>
        </div>
        <div class="mt-1 text-xs text-gray-500">
            ${strength.checks.map(check => 
                `<span class="${check.passed ? 'text-green-500' : 'text-gray-400'}">✓ ${check.label}</span>`
            ).join(' • ')}
        </div>
    `;
}
</script>

<?php
require_once '../includes/footer_admin.php'; // Includes the footer template
?>