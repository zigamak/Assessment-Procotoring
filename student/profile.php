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
$upload_url_path = BASE_URL . 'uploads/verification/'; // URL path for displaying images

// Placeholder image URL
$placeholder_image_url = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSQY2wp4iDIst2_iF51miozA4fRKg58TnnxCw&s';

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

// Fetch current user data
$current_username = '';
$current_email = '';
$current_passport_image = '';
$current_city = '';
$current_state = '';
$current_country = '';
$verification_completed = false;
$initial_load = true; // Flag to determine if it's the initial page load

try {
    $stmt = $pdo->prepare("SELECT username, email, passport_image_path, city, state, country FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $current_username = htmlspecialchars($user_data['username']);
        $current_email = htmlspecialchars($user_data['email']);
        $current_passport_image = htmlspecialchars($user_data['passport_image_path'] ?? '');
        $current_city = htmlspecialchars($user_data['city'] ?? '');
        $current_state = htmlspecialchars($user_data['state'] ?? '');
        $current_country = htmlspecialchars($user_data['country'] ?? '');

        // Check verification status
        if (!empty($current_passport_image) && !empty($current_city) && !empty($current_state) && !empty($current_country)) {
            $verification_completed = true;
        }
    } else {
        $message = display_message("User data not found. Please log in again.", "error");
        redirect(BASE_URL . 'auth/logout.php');
    }
} catch (PDOException $e) {
    error_log("Profile Data Fetch Error: " . $e->getMessage());
    $message = display_message("An error occurred while fetching profile data. Please try again later.", "error");
}


// Handle POST requests for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $initial_load = false; // It's a POST request, not an initial load
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'update_profile_and_image': // Combined action for email, personal details, and image
                $new_email = sanitize_input($_POST['email_modal'] ?? '');
                $city = sanitize_input($_POST['city_modal'] ?? '');
                $state = sanitize_input($_POST['state_modal'] ?? '');
                $country = sanitize_input($_POST['country_modal'] ?? '');

                $has_error = false;

                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $message = display_message("Invalid email format.", "error");
                    $has_error = true;
                } elseif (empty($new_email)) {
                    $message = display_message("Email cannot be empty.", "error");
                    $has_error = true;
                } elseif (empty($city) || empty($state) || empty($country)) {
                    $message = display_message("City, State, and Country are required for verification.", "error");
                    $has_error = true;
                }

                if (!$has_error) {
                    $image_uploaded = false;
                    $new_file_name = $current_passport_image; // Keep existing if no new image uploaded

                    if (isset($_FILES['passport_image_modal']) && $_FILES['passport_image_modal']['error'] === UPLOAD_ERR_OK) {
                        $file_name = $_FILES['passport_image_modal']['name'];
                        $file_tmp = $_FILES['passport_image_modal']['tmp_name'];
                        $file_size = $_FILES['passport_image_modal']['size'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                        if (!in_array($file_ext, $allowed_types)) {
                            $message = display_message("Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.", "error");
                            $has_error = true;
                        } elseif ($file_size > 2 * 1024 * 1024) { // 2MB limit
                            $message = display_message("File size exceeds 2MB limit.", "error");
                            $has_error = true;
                        } else {
                            $new_file_name = uniqid('passport_') . '.' . $file_ext;
                            $destination = $upload_dir . $new_file_name;

                            if (!is_dir(dirname($destination))) {
                                 $message = display_message("Upload directory not found. Please contact support.", "error");
                                 error_log("Upload directory missing for verification: " . dirname($destination));
                                 $has_error = true;
                            } elseif (move_uploaded_file($file_tmp, $destination)) {
                                $image_uploaded = true;
                                // Delete old image if a new one is uploaded and an old one exists
                                if (!empty($current_passport_image) && file_exists($upload_dir . $current_passport_image)) {
                                    unlink($upload_dir . $current_passport_image);
                                }
                            } else {
                                $message = display_message("Failed to upload image. Please check server permissions.", "error");
                                error_log("Failed to move uploaded file: " . $file_tmp . " to " . $destination);
                                $has_error = true;
                            }
                        }
                    } else if (isset($_FILES['passport_image_modal']['error']) && $_FILES['passport_image_modal']['error'] !== UPLOAD_ERR_NO_FILE) {
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
                        $message = display_message("File upload error: " . ($phpFileUploadErrors[$_FILES['passport_image_modal']['error']] ?? 'Unknown error'), "error");
                        $has_error = true;
                    }
                }

                if (!$has_error) {
                    try {
                        // Check if the new email already exists for another user
                        if ($new_email !== $current_email) {
                            $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id");
                            $stmt_check_email->execute(['email' => $new_email, 'user_id' => $user_id]);
                            if ($stmt_check_email->fetchColumn() > 0) {
                                $message = display_message("This email is already registered by another user.", "error");
                                $has_error = true;
                            }
                        }

                        if (!$has_error) {
                            $stmt_update = $pdo->prepare("UPDATE users SET email = :email, passport_image_path = :path, city = :city, state = :state, country = :country WHERE user_id = :user_id");
                            if ($stmt_update->execute([
                                'email' => $new_email,
                                'path' => $new_file_name,
                                'city' => $city,
                                'state' => $state,
                                'country' => $country,
                                'user_id' => $user_id
                            ])) {
                                $_SESSION['email'] = $new_email; // Update session
                                $current_email = htmlspecialchars($new_email);
                                $current_passport_image = htmlspecialchars($new_file_name);
                                $current_city = htmlspecialchars($city);
                                $current_state = htmlspecialchars($state);
                                $current_country = htmlspecialchars($country);

                                // Re-check verification status
                                if (!empty($current_passport_image) && !empty($current_city) && !empty($current_state) && !empty($current_country)) {
                                    $verification_completed = true;
                                } else {
                                    $verification_completed = false;
                                }
                                $message = display_message("Profile updated successfully!", "success");
                            } else {
                                $message = display_message("Failed to update profile. Please try again.", "error");
                                // If DB update failed, and a new image was moved, try to unlink it
                                if ($image_uploaded && file_exists($destination)) {
                                    unlink($destination);
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Update Profile DB Error: " . $e->getMessage());
                        $message = display_message("Database error while updating profile.", "error");
                        if ($image_uploaded && file_exists($destination)) {
                            unlink($destination);
                        }
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
                } elseif (strlen($new_password) < 6) {
                    $message = display_message("New password must be at least 6 characters long.", "error");
                } else {
                    try {
                        $stmt_get_password = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
                        $stmt_get_password->execute(['user_id' => $user_id]);
                        $user_password_data = $stmt_get_password->fetch(PDO::FETCH_ASSOC);

                        if ($user_password_data && password_verify($current_password, $user_password_data['password_hash'])) {
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
        }
    }
}

// Check if verification is needed on initial page load
$show_verification_modal_on_load = $initial_load && !$verification_completed;

?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Your Profile</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if (!$verification_completed): ?>
    <div class="bg-red-50 border-l-4 border-red-400 text-red-700 p-4 mb-6 rounded-md shadow-sm" role="alert">
        <p class="font-bold text-lg">Verification Required!</p>
        <p class="text-base">Please complete your profile verification by providing your **email, city, state, country** and uploading your **passport/ID image**. Verification is mandatory to access assessments.</p>
        <p class="mt-2 text-sm">Click "Edit Profile" below to provide these details.</p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-8">
        <div class="bg-white p-8 rounded-xl shadow-lg border border-gray-200 relative">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-3">Personal Information</h2>
            <div class="space-y-4 text-gray-700">
                <div class="flex items-center space-x-4 mb-4">
                    <img src="<?php echo !empty($current_passport_image) ? $upload_url_path . $current_passport_image : $placeholder_image_url; ?>"
                         alt="Passport/ID Image"
                         class="w-24 h-24 object-cover rounded-full shadow-md border-2 border-gray-300">
                    <div>
                        <p><strong class="font-semibold text-gray-900">Username:</strong> <span class="ml-2"><?php echo $current_username; ?></span></p>
                        <p><strong class="font-semibold text-gray-900">Email:</strong> <span class="ml-2"><?php echo $current_email; ?></span></p>
                    </div>
                </div>

                <p><strong class="font-semibold text-gray-900">City:</strong> <span class="ml-2"><?php echo empty($current_city) ? '<span class="text-red-500">Not Provided</span>' : $current_city; ?></span></p>
                <p><strong class="font-semibold text-gray-900">State/Region:</strong> <span class="ml-2"><?php echo empty($current_state) ? '<span class="text-red-500">Not Provided</span>' : $current_state; ?></span></p>
                <p><strong class="font-semibold text-gray-900">Country:</strong> <span class="ml-2"><?php echo empty($current_country) ? '<span class="text-red-500">Not Provided</span>' : $current_country; ?></span></p>

                <div class="pt-4 border-t mt-4">
                    <p class="text-gray-700"><strong class="font-semibold text-gray-900">Verification Status:</strong></p>
                    <?php if ($verification_completed): ?>
                        <div class="flex items-center mt-2">
                            <span class="text-green-600 font-bold text-lg flex items-center">
                                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Completed
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center mt-2">
                            <span class="text-red-600 font-bold text-lg flex items-center">
                                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Pending
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="absolute top-4 right-4 flex space-x-2">
                <button onclick="openModal('editProfileModal')" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-full text-sm shadow-md transition duration-300">
                    Edit Profile
                </button>
                <button onclick="openModal('changePasswordModal')" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-full text-sm shadow-md transition duration-300">
                    Change Password
                </button>
            </div>
        </div>
    </div>
</div>

<div id="editProfileModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-2xl mx-4 transform transition-all duration-300 scale-95 opacity-0" id="editProfileModalContent">
        <div class="flex justify-between items-center mb-6 border-b pb-3">
            <h2 class="text-2xl font-bold text-gray-800">Edit Profile Details</h2>
            <button onclick="closeModal('editProfileModal')" class="text-gray-500 hover:text-gray-700 text-3xl font-light">&times;</button>
        </div>
        <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="action" value="update_profile_and_image">

            <div>
                <label for="email_modal" class="block text-gray-800 text-sm font-semibold mb-2">Email:</label>
                <input type="email" id="email_modal" name="email_modal" value="<?php echo $current_email; ?>" required
                       class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                       placeholder="your.email@example.com">
            </div>

            <div>
                <label for="city_modal" class="block text-gray-800 text-sm font-semibold mb-2">City:</label>
                <input type="text" id="city_modal" name="city_modal" value="<?php echo $current_city; ?>" required
                       class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-200"
                       placeholder="e.g., Lagos">
            </div>

            <div>
                <label for="state_modal" class="block text-gray-800 text-sm font-semibold mb-2">State/Region:</label>
                <input type="text" id="state_modal" name="state_modal" value="<?php echo $current_state; ?>" required
                       class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-200"
                       placeholder="e.g., Lagos State">
            </div>

            <div>
                <label for="country_modal" class="block text-gray-800 text-sm font-semibold mb-2">Country:</label>
                <input type="text" id="country_modal" name="country_modal" value="<?php echo $current_country; ?>" required
                       class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-200"
                       placeholder="e.g., Nigeria">
            </div>

            <div>
                <label for="passport_image_modal" class="block text-gray-800 text-sm font-semibold mb-2">Select Passport/ID Image: <span class="text-red-500">*</span></label>
                <input type="file" id="passport_image_modal" name="passport_image_modal" accept="image/*" required
                       class="block w-full text-sm text-gray-800
                              file:mr-4 file:py-2.5 file:px-4
                              file:rounded-full file:border-0
                              file:text-sm file:font-semibold
                              file:bg-green-100 file:text-green-700
                              hover:file:bg-green-200 cursor-pointer">
                <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, JPEG, PNG, GIF. Maximum size: 2MB.</p>
                <?php if (!empty($current_passport_image)): ?>
                    <p class="text-sm text-gray-600 mt-2">Current Image (will be replaced if new one is uploaded):</p>
                    <img src="<?php echo $upload_url_path . $current_passport_image; ?>" alt="Current Passport/ID" class="mt-2 max-w-[100px] h-auto rounded-lg shadow-sm">
                <?php endif; ?>
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeModal('editProfileModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-lg transition duration-300">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-5 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 transition duration-300 ease-in-out transform hover:scale-105">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<div id="changePasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0" id="changePasswordModalContent">
        <div class="flex justify-between items-center mb-6 border-b pb-3">
            <h2 class="text-2xl font-bold text-gray-800">Change Password</h2>
            <button onclick="closeModal('changePasswordModal')" class="text-gray-500 hover:text-gray-700 text-3xl font-light">&times;</button>
        </div>
        <form action="profile.php" method="POST" class="space-y-5">
            <input type="hidden" name="action" value="change_password">
            <div>
                <label for="current_password_modal" class="block text-gray-800 text-sm font-semibold mb-2">Current Password:</label>
                <input type="password" id="current_password_modal" name="current_password" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200">
            </div>
            <div>
                <label for="new_password_modal" class="block text-gray-800 text-sm font-semibold mb-2">New Password:</label>
                <input type="password" id="new_password_modal" name="new_password" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200">
            </div>
            <div>
                <label for="confirm_new_password_modal" class="block text-gray-800 text-sm font-semibold mb-2">Confirm New Password:</label>
                <input type="password" id="confirm_new_password_modal" name="confirm_new_password" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200">
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeModal('changePasswordModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-lg transition duration-300">Cancel</button>
                <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-5 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 transition duration-300 ease-in-out transform hover:scale-105">
                    Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<div id="verificationNeededModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0" id="verificationNeededModalContent">
        <div class="flex justify-between items-center mb-6 border-b pb-3">
            <h2 class="text-2xl font-bold text-red-700">Action Required: Profile Verification!</h2>
            <button onclick="closeModal('verificationNeededModal')" class="text-gray-500 hover:text-gray-700 text-3xl font-light">&times;</button>
        </div>
        <p class="text-gray-700 mb-4">To access assessments and fully utilize your account, please complete your profile verification.</p>
        <p class="text-gray-700 mb-6">This includes providing your **email, city, state, country** and uploading your **passport/ID image**.</p>
        <div class="flex justify-end">
            <button onclick="closeModal('verificationNeededModal'); openModal('editProfileModal');" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-lg focus:outline-none focus:ring-2 focus://ring-blue-500 focus:ring-opacity-50 transition duration-300">
                Go to Verification
            </button>
        </div>
    </div>
</div>


<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        const modalContent = document.getElementById(modalId + 'Content');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 50); // Small delay for transition
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        const modalContent = document.getElementById(modalId + 'Content');
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
            // Reset form fields when modal closes (optional, but good for user experience)
            if (modalId === 'editProfileModal') {
                document.getElementById('editProfileModal').querySelector('form').reset();
            } else if (modalId === 'changePasswordModal') {
                document.getElementById('changePasswordModal').querySelector('form').reset();
            }
        }, 300); // Match CSS transition duration
    }

    // Show verification modal on page load if incomplete
    window.onload = function() {
        <?php if ($show_verification_modal_on_load): ?>
            openModal('verificationNeededModal');
        <?php endif; ?>
    };
</script>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>