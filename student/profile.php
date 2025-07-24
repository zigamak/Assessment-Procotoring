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
$upload_dir = BASE_DIR . '/uploads/passports/';
$upload_url_path = BASE_URL . 'uploads/passports/'; // URL path for displaying images

// Placeholder image URL
$placeholder_image_url = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSQY2wp4iDIst2_iF51miozA4fRKg58TnnxCw&s';

// Ensure the upload directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) { // 0755 permissions, recursive creation
        error_log("Failed to create upload directory: " . $upload_dir);
        $message = display_message("Upload directory does not exist and could not be created. Please contact support.", "error");
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
$current_first_name = '';
$current_last_name = '';
$current_date_of_birth = '';
$current_grade = '';
$current_address = '';
$current_gender = '';
$verification_completed = false;
$initial_load = true; // Flag to determine if it's the initial page load

try {
    $stmt = $pdo->prepare("SELECT username, email, passport_image_path, city, state, country, first_name, last_name, date_of_birth, grade, address, gender FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $current_username = htmlspecialchars($user_data['username']);
        $current_email = htmlspecialchars($user_data['email']);
        $current_passport_image = htmlspecialchars($user_data['passport_image_path'] ?? '');
        $current_city = htmlspecialchars($user_data['city'] ?? '');
        $current_state = htmlspecialchars($user_data['state'] ?? '');
        $current_country = htmlspecialchars($user_data['country'] ?? '');
        $current_first_name = htmlspecialchars($user_data['first_name'] ?? '');
        $current_last_name = htmlspecialchars($user_data['last_name'] ?? '');
        $current_date_of_birth = htmlspecialchars($user_data['date_of_birth'] ?? '');
        $current_grade = htmlspecialchars($user_data['grade'] ?? '');
        $current_address = htmlspecialchars($user_data['address'] ?? '');
        $current_gender = htmlspecialchars($user_data['gender'] ?? '');

        // Check verification status
        if (!empty($current_passport_image) && !empty($current_city) && !empty($current_state) && !empty($current_country) && !empty($current_first_name) && !empty($current_last_name) && !empty($current_date_of_birth) && !empty($current_grade) && !empty($current_address) && !empty($current_gender)) {
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
                $first_name = sanitize_input($_POST['first_name_modal'] ?? '');
                $last_name = sanitize_input($_POST['last_name_modal'] ?? '');
                $date_of_birth = sanitize_input($_POST['date_of_birth_modal'] ?? '');
                $grade = sanitize_input($_POST['grade_modal'] ?? '');
                $address = sanitize_input($_POST['address_modal'] ?? '');
                $gender = sanitize_input($_POST['gender_modal'] ?? '');

                $has_error = false;

                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $message = display_message("Invalid email format.", "error");
                    $has_error = true;
                } elseif (empty($new_email)) {
                    $message = display_message("Email cannot be empty.", "error");
                    $has_error = true;
                } elseif (empty($city) || empty($state) || empty($country) || empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($grade) || empty($address) || empty($gender)) {
                    $message = display_message("All fields (email, city, state, country, first name, last name, date of birth, grade, address, gender) are required for verification.", "error");
                    $has_error = true;
                } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_of_birth)) {
                    $message = display_message("Invalid date of birth format. Use YYYY-MM-DD.", "error");
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
                            $stmt_update = $pdo->prepare("UPDATE users SET email = :email, passport_image_path = :path, city = :city, state = :state, country = :country, first_name = :first_name, last_name = :last_name, date_of_birth = :date_of_birth, grade = :grade, address = :address, gender = :gender WHERE user_id = :user_id");
                            if ($stmt_update->execute([
                                'email' => $new_email,
                                'path' => $new_file_name,
                                'city' => $city,
                                'state' => $state,
                                'country' => $country,
                                'first_name' => $first_name,
                                'last_name' => $last_name,
                                'date_of_birth' => $date_of_birth,
                                'grade' => $grade,
                                'address' => $address,
                                'gender' => $gender,
                                'user_id' => $user_id
                            ])) {
                                $_SESSION['email'] = $new_email; // Update session
                                $current_email = htmlspecialchars($new_email);
                                $current_passport_image = htmlspecialchars($new_file_name);
                                $current_city = htmlspecialchars($city);
                                $current_state = htmlspecialchars($state);
                                $current_country = htmlspecialchars($country);
                                $current_first_name = htmlspecialchars($first_name);
                                $current_last_name = htmlspecialchars($last_name);
                                $current_date_of_birth = htmlspecialchars($date_of_birth);
                                $current_grade = htmlspecialchars($grade);
                                $current_address = htmlspecialchars($address);
                                $current_gender = htmlspecialchars($gender);

                                // Re-check verification status
                                if (!empty($current_passport_image) && !empty($current_city) && !empty($current_state) && !empty($current_country) && !empty($current_first_name) && !empty($current_last_name) && !empty($current_date_of_birth) && !empty($current_grade) && !empty($current_address) && !empty($current_gender)) {
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
        <p class="text-base">Please complete your profile verification by providing your <strong>email, city, state, country, first name, last name, date of birth, grade, address, gender</strong> and uploading your <strong>passport/ID image</strong>. Verification is mandatory to access assessments.</p>
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
                        <p><strong class="font-semibold text-gray-900">First Name:</strong> <span class="ml-2"><?php echo empty($current_first_name) ? '<span class="text-red-500">Not Provided</span>' : $current_first_name; ?></span></p>
                        <p><strong class="font-semibold text-gray-900">Last Name:</strong> <span class="ml-2"><?php echo empty($current_last_name) ? '<span class="text-red-500">Not Provided</span>' : $current_last_name; ?></span></p>
                    </div>
                </div>

                <p><strong class="font-semibold text-gray-900">City:</strong> <span class="ml-2"><?php echo empty($current_city) ? '<span class="text-red-500">Not Provided</span>' : $current_city; ?></span></p>
                <p><strong class="font-semibold text-gray-900">State/Region:</strong> <span class="ml-2"><?php echo empty($current_state) ? '<span class="text-red-500">Not Provided</span>' : $current_state; ?></span></p>
                <p><strong class="font-semibold text-gray-900">Country:</strong> <span class="ml-2"><?php echo empty($current_country) ? '<span class="text-red-500">Not Provided</span>' : $current_country; ?></span></p>
                <p><strong class="font-semibold text-gray-900">Date of Birth:</strong> <span class="ml-2"><?php echo empty($current_date_of_birth) ? '<span class="text-red-500">Not Provided</span>' : $current_date_of_birth; ?></span></p>
                <p><strong class="font-semibold text-gray-900">Grade:</strong> <span class="ml-2"><?php echo empty($current_grade) ? '<span class="text-red-500">Not Provided</span>' : $current_grade; ?></span></p>
                <p><strong class="font-semibold text-gray-900">Address:</strong> <span class="ml-2"><?php echo empty($current_address) ? '<span class="text-red-500">Not Provided</span>' : $current_address; ?></span></p>
                <p><strong class="font-semibold text-gray-900">Gender:</strong> <span class="ml-2"><?php echo empty($current_gender) ? '<span class="text-red-500">Not Provided</span>' : $current_gender; ?></span></p>

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
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-4xl mx-4 transform transition-all duration-300 scale-95 opacity-0 max-h-[80vh] overflow-y-auto" id="editProfileModalContent">
        <div class="flex justify-between items-center mb-6 border-b pb-3 sticky top-0 bg-white z-10">
            <h2 class="text-2xl font-bold text-gray-800">Edit Profile Details</h2>
            <button onclick="closeModal('editProfileModal')" class="text-gray-500 hover:text-gray-700 text-3xl font-light">×</button>
        </div>
        <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="action" value="update_profile_and_image">

            <div>
                <label for="first_name_modal" class="block text-gray-800 text-sm font-semibold mb-2">First Name:</label>
                <input type="text" id="first_name_modal" name="first_name_modal" value="<?php echo $current_first_name; ?>" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        placeholder="e.g., John">
            </div>

            <div>
                <label for="last_name_modal" class="block text-gray-800 text-sm font-semibold mb-2">Last Name:</label>
                <input type="text" id="last_name_modal" name="last_name_modal" value="<?php echo $current_last_name; ?>" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        placeholder="e.g., Doe">
            </div>

            <div>
                <label for="email_modal" class="block text-gray-800 text-sm font-semibold mb-2">Email:</label>
                <input type="email" id="email_modal" name="email_modal" value="<?php echo $current_email; ?>" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        placeholder="your.email@example.com">
            </div>

            <div>
                <label for="date_of_birth_modal" class="block text-gray-800 text-sm font-semibold mb-2">Date of Birth (YYYY-MM-DD):</label>
                <input type="text" id="date_of_birth_modal" name="date_of_birth_modal" value="<?php echo $current_date_of_birth; ?>" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-200"
                        placeholder="e.g., 2000-01-01">
            </div>

            <div>
                <label for="grade_modal" class="block text-gray-800 text-sm font-semibold mb-2">Grade:</label>
                <select id="grade_modal" name="grade_modal" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-200">
                    <option value="" <?php echo empty($current_grade) ? 'selected' : ''; ?>>Select Grade</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="Grade <?php echo $i; ?>" <?php echo $current_grade == 'Grade ' . $i ? 'selected' : ''; ?>>Grade <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div>
                <label for="address_modal" class="block text-gray-800 text-sm font-semibold mb-2">Address:</label>
                <input type="text" id="address_modal" name="address_modal" value="<?php echo $current_address; ?>" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-200"
                        placeholder="e.g., 123 Main St">
            </div>

            <div>
                <label for="gender_modal" class="block text-gray-800 text-sm font-semibold mb-2">Gender:</label>
                <select id="gender_modal" name="gender_modal" required
                        class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-200">
                    <option value="" <?php echo empty($current_gender) ? 'selected' : ''; ?>>Select Gender</option>
                    <option value="Male" <?php echo $current_gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $current_gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select>
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
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md mx-4 transform transition-all duration-300 scale-95 opacity-0
" id="changePasswordModalContent">
        <div class="flex justify-between items-center mb-6 border-b pb-3 sticky top-0 bg-white z-10">
            <h2 class="text-2xl font-bold text-gray-800">Change Password</h2>
            <button onclick="closeModal('changePasswordModal')" class="text-gray-500 hover:text-gray-700 text-3xl font-light">×</button>
        </div>
        <form action="profile.php" method="POST" class="space-y-6">
            <input type="hidden" name="action" value="change_password">

            <div>
                <label for="current_password" class="block text-gray-800 text-sm font-semibold mb-2">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required
                       class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
            </div>

            <div>
                <label for="new_password" class="block text-gray-800 text-sm font-semibold mb-2">New Password:</label>
                <input type="password" id="new_password" name="new_password" required
                       class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
            </div>

            <div>
                <label for="confirm_new_password" class="block text-gray-800 text-sm font-semibold mb-2">Confirm New Password:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" required
                       class="shadow-sm border border-gray-300 rounded-lg w-full py-2.5 px-4 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeModal('changePasswordModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-lg transition duration-300">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-5 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 transition duration-300 ease-in-out transform hover:scale-105">
                    Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // JavaScript for modal functionality
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        const modalContent = document.getElementById(modalId + 'Content'); // Assuming content div has ID like 'modalIdContent'

        if (modal && modalContent) {
            modal.classList.remove('hidden');
            // Trigger reflow to ensure transition plays
            void modalContent.offsetWidth;
            modalContent.classList.remove('opacity-0', 'scale-95');
            modalContent.classList.add('opacity-100', 'scale-100');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        const modalContent = document.getElementById(modalId + 'Content');

        if (modal && modalContent) {
            modalContent.classList.remove('opacity-100', 'scale-100');
            modalContent.classList.add('opacity-0', 'scale-95');

            // Hide the modal completely after the transition
            modalContent.addEventListener('transitionend', function handler() {
                modal.classList.add('hidden');
                modalContent.removeEventListener('transitionend', handler);
            });
        }
    }

    // Automatically open verification modal if needed on initial load
    document.addEventListener('DOMContentLoaded', function() {
        const showVerificationModal = <?php echo json_encode($show_verification_modal_on_load); ?>;
        if (showVerificationModal) {
            openModal('editProfileModal');
        }
    });
</script>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>
