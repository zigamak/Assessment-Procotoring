<?php
// student/profile.php
// Allows students to view and edit their profile information, including changing email and password,
// and uploading a passport/image for verification.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$user_id = getUserId();

// Define allowed image types and upload directory
$allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

// --- START: Absolute Path for Upload Directory (Recommended Fix from previous discussion) ---
if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/..'));
}
$upload_dir = BASE_DIR . '/uploads/verification/';

// Ensure the upload directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) { // 0755 permissions, recursive creation
        error_log("Failed to create upload directory: " . $upload_dir);
        $message = display_message("Upload directory does not exist and could not be created. Please contact support.", "error");
        // Optionally, prevent further execution if directory cannot be created
        // die("Fatal Error: Upload directory not found and cannot be created.");
    }
}
// --- END: Absolute Path for Upload Directory ---


// Fetch current user data - ADD NEW COLUMNS HERE
$current_username = '';
$current_email = '';
$current_passport_image = '';
$current_city = ''; // New
$current_state = ''; // New
$current_country = ''; // New
$verification_completed = false;


try {
    $stmt = $pdo->prepare("SELECT username, email, passport_image_path, city, state, country FROM users WHERE user_id = :user_id"); // ADDED city, state, country
    $stmt->execute(['user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $current_username = htmlspecialchars($user_data['username']);
        $current_email = htmlspecialchars($user_data['email']);
        $current_passport_image = htmlspecialchars($user_data['passport_image_path'] ?? '');
        $current_city = htmlspecialchars($user_data['city'] ?? ''); // New
        $current_state = htmlspecialchars($user_data['state'] ?? ''); // New
        $current_country = htmlspecialchars($user_data['country'] ?? ''); // New

        // Check verification status (now includes image and new fields)
        if (!empty($current_passport_image) && !empty($current_city) && !empty($current_state) && !empty($current_country)) {
            $verification_completed = true;
        }
    } else {
        // This should ideally not happen if role enforcement is working
        $message = display_message("User data not found. Please log in again.", "error");
        // Redirect to logout or login page
        redirect(BASE_URL . 'auth/logout.php');
    }
} catch (PDOException $e) {
    error_log("Profile Data Fetch Error: " . $e->getMessage());
    $message = display_message("An error occurred while fetching profile data. Please try again later.", "error");
}


// Handle POST requests for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'update_email':
                // ... (existing email update logic)
                $new_email = sanitize_input($_POST['email'] ?? '');

                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $message = display_message("Invalid email format.", "error");
                } elseif (empty($new_email)) {
                    $message = display_message("Email cannot be empty.", "error");
                } else {
                    try {
                        // Check if the new email already exists for another user
                        $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id");
                        $stmt_check_email->execute(['email' => $new_email, 'user_id' => $user_id]);
                        if ($stmt_check_email->fetchColumn() > 0) {
                            $message = display_message("This email is already registered by another user.", "error");
                        } else {
                            $stmt_update_email = $pdo->prepare("UPDATE users SET email = :email WHERE user_id = :user_id");
                            if ($stmt_update_email->execute(['email' => $new_email, 'user_id' => $user_id])) {
                                $_SESSION['email'] = $new_email; // Update session with new email
                                $current_email = htmlspecialchars($new_email); // Update display
                                $message = display_message("Email updated successfully!", "success");
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
                // ... (existing password change logic)
                $current_password = sanitize_input($_POST['current_password'] ?? '');
                $new_password = sanitize_input($_POST['new_password'] ?? '');
                $confirm_new_password = sanitize_input($_POST['confirm_new_password'] ?? '');

                if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
                    $message = display_message("All password fields are required.", "error");
                } elseif ($new_password !== $confirm_new_password) {
                    $message = display_message("New password and confirmation do not match.", "error");
                } elseif (strlen($new_password) < 6) { // Example: minimum 6 characters
                    $message = display_message("New password must be at least 6 characters long.", "error");
                } else {
                    try {
                        // Verify current password
                        $stmt_get_password = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
                        $stmt_get_password->execute(['user_id' => $user_id]);
                        $user_password_data = $stmt_get_password->fetch(PDO::FETCH_ASSOC);

                        if ($user_password_data && password_verify($current_password, $user_password_data['password_hash'])) {
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

            case 'upload_passport_image':
                // NEW: Get city, state, country from POST data
                $city = sanitize_input($_POST['city'] ?? '');
                $state = sanitize_input($_POST['state'] ?? '');
                $country = sanitize_input($_POST['country'] ?? '');

                // Add validation for new fields
                if (empty($city) || empty($state) || empty($country)) {
                    $message = display_message("City, State, and Country are required for verification.", "error");
                } elseif (isset($_FILES['passport_image']) && $_FILES['passport_image']['error'] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['passport_image']['name'];
                    $file_tmp = $_FILES['passport_image']['tmp_name'];
                    $file_size = $_FILES['passport_image']['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (!in_array($file_ext, $allowed_types)) {
                        $message = display_message("Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.", "error");
                    } elseif ($file_size > 2 * 1024 * 1024) { // 2MB limit
                        $message = display_message("File size exceeds 2MB limit.", "error");
                    } else {
                        // Generate a unique file name to prevent overwrites
                        $new_file_name = uniqid('passport_') . '.' . $file_ext;
                        // Use the absolute path for destination
                        $destination = $upload_dir . $new_file_name;

                        // Check if the directory exists before moving (extra safeguard)
                        if (!is_dir(dirname($destination))) {
                             $message = display_message("Upload directory not found. Please contact support.", "error");
                             error_log("Upload directory missing for verification: " . dirname($destination));
                        } elseif (move_uploaded_file($file_tmp, $destination)) {
                            try {
                                // Update database with the new image path AND new verification fields
                                $stmt_update_image = $pdo->prepare("UPDATE users SET passport_image_path = :path, city = :city, state = :state, country = :country WHERE user_id = :user_id");
                                if ($stmt_update_image->execute([
                                    'path' => $new_file_name,
                                    'city' => $city,      // NEW
                                    'state' => $state,    // NEW
                                    'country' => $country, // NEW
                                    'user_id' => $user_id
                                ])) {
                                    $current_passport_image = htmlspecialchars($new_file_name);
                                    $current_city = htmlspecialchars($city);     // Update display
                                    $current_state = htmlspecialchars($state);   // Update display
                                    $current_country = htmlspecialchars($country); // Update display
                                    $verification_completed = true;
                                    $message = display_message("Passport/Image and details uploaded successfully!", "success");
                                } else {
                                    $message = display_message("Failed to update image path and details in database.", "error");
                                    unlink($destination); // Delete uploaded file if DB update fails
                                }
                            } catch (PDOException $e) {
                                error_log("Upload Image & Details DB Error: " . $e->getMessage());
                                $message = display_message("Database error while uploading image and details.", "error");
                                unlink($destination); // Delete uploaded file on DB error
                            }
                        } else {
                            $message = display_message("Failed to upload image. Please check server permissions.", "error");
                            error_log("Failed to move uploaded file: " . $file_tmp . " to " . $destination);
                        }
                    }
                } else {
                    // Check for specific upload errors
                    if (isset($_FILES['passport_image']['error']) && $_FILES['passport_image']['error'] !== UPLOAD_ERR_NO_FILE) {
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
                        $message = display_message("File upload error: " . ($phpFileUploadErrors[$_FILES['passport_image']['error']] ?? 'Unknown error'), "error");
                    } else {
                         $message = display_message("Please select an image to upload.", "error");
                    }
                }
                break;
        }
    }
}

?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Your Profile</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if (!$verification_completed): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
        <p class="font-bold">Important:</p>
        <p>Please upload your passport/ID image and provide **City, State, and Country** to complete <a href="#upload-verification-form" class="font-bold underline">verification</a> before you can take any quizzes.</p>
    </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Profile Information</h2>
        <div class="space-y-4">
            <p class="text-gray-700"><strong class="font-semibold">Username:</strong> <?php echo $current_username; ?></p>
            <p class="text-gray-700"><strong class="font-semibold">Email:</strong> <?php echo $current_email; ?></p>
            <p class="text-gray-700"><strong class="font-semibold">City:</strong> <?php echo empty($current_city) ? 'N/A' : $current_city; ?></p>
            <p class="text-gray-700"><strong class="font-semibold">State:</strong> <?php echo empty($current_state) ? 'N/A' : $current_state; ?></p>
            <p class="text-gray-700"><strong class="font-semibold">Country:</strong> <?php echo empty($current_country) ? 'N/A' : $current_country; ?></p>


            <p class="text-gray-700">
                <strong class="font-semibold">Verification Status:</strong>
                <?php if ($verification_completed): ?>
                    <span class="text-green-600 font-semibold">Completed</span>
                    <br>
                    <?php
                    // Ensure BASE_URL is defined in your config or includes/functions.php
                    // Example: define('BASE_URL', 'http://localhost/assessment/');
                    // Note: Use 'uploads/verification/' for HTML src, not the server-side absolute $upload_dir
                    ?>
                    <img src="<?php echo BASE_URL . 'uploads/verification/' . $current_passport_image; ?>" alt="Passport/ID Image" class="mt-2 w-48 h-auto rounded shadow-md">
                <?php else: ?>
                    <span class="text-red-600 font-semibold">Pending</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Update Email Address</h2>
        <form action="profile.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_email">
            <div>
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">New Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $current_email; ?>" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Update Email
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Change Password</h2>
        <form action="profile.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="change_password">
            <div>
                <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password:</label>
                <input type="password" id="new_password" name="new_password" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="confirm_new_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Change Password
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 id="upload-verification-form" class="text-2xl font-semibold text-gray-800 mb-4">Upload Passport/ID Image & Verification Details</h2>
        <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="upload_passport_image">

            <div>
                <label for="city" class="block text-gray-700 text-sm font-bold mb-2">City:</label>
                <input type="text" id="city" name="city" value="<?php echo $current_city; ?>" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>

            <div>
                <label for="state" class="block text-gray-700 text-sm font-bold mb-2">State/Region:</label>
                <input type="text" id="state" name="state" value="<?php echo $current_state; ?>" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>

            <div>
                <label for="country" class="block text-gray-700 text-sm font-bold mb-2">Country:</label>
                <input type="text" id="country" name="country" value="<?php echo $current_country; ?>" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>

            <div>
                <label for="passport_image" class="block text-gray-700 text-sm font-bold mb-2">Select Passport/ID Image:</label>
                <input type="file" id="passport_image" name="passport_image" accept="image/*" required
                       class="block w-full text-sm text-gray-700
                             file:mr-4 file:py-2 file:px-4
                             file:rounded-full file:border-0
                             file:text-sm file:font-semibold
                             file:bg-blue-50 file:text-blue-700
                             hover:file:bg-blue-100">
                <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, JPEG, PNG, GIF. Max size: 2MB.</p>
            </div>
            <div>
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Submit Verification Details
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>