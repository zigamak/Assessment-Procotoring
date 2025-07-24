<?php
// auth/register.php
// Handles new user registration in three steps.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/send_email.php';

$message = '';
$current_step = isset($_SESSION['registration_step']) ? (int)$_SESSION['registration_step'] : 1;

// Initialize session data for registration if not set
if (!isset($_SESSION['reg_data'])) {
    $_SESSION['reg_data'] = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)($_POST['step'] ?? 1);

    if ($step === 1) {
        $_SESSION['reg_data']['first_name'] = sanitize_input($_POST['first_name'] ?? '');
        $_SESSION['reg_data']['last_name'] = sanitize_input($_POST['last_name'] ?? '');
        $_SESSION['reg_data']['username'] = sanitize_input($_POST['username'] ?? '');
        $_SESSION['reg_data']['email'] = sanitize_input($_POST['email'] ?? '');

        // Validate Step 1
        if (empty($_SESSION['reg_data']['first_name']) || empty($_SESSION['reg_data']['last_name']) || empty($_SESSION['reg_data']['email'])) {
            $message = "First Name, Last Name, and Email are required.";
        } elseif (!filter_var($_SESSION['reg_data']['email'], FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            // Check if email already exists
            $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt_check_email->execute(['email' => $_SESSION['reg_data']['email']]);
            if ($stmt_check_email->fetchColumn() > 0) {
                $message = "An account with this email address already exists.";
            } else {
                // Validate username if provided
                $username_to_use = $_SESSION['reg_data']['username'];
                if (!empty($username_to_use)) {
                    $stmt_check_user_username = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                    $stmt_check_user_username->execute(['username' => $username_to_use]);
                    if ($stmt_check_user_username->fetchColumn() > 0) {
                        $message = "The chosen username is already taken. Please choose another or leave it blank to auto-generate one.";
                    }
                }
            }
        }
        if (empty($message)) {
            $_SESSION['registration_step'] = 2;
            header('Location: register.php');
            exit;
        }
    } elseif ($step === 2) {
        $_SESSION['reg_data']['city'] = sanitize_input($_POST['city'] ?? '');
        $_SESSION['reg_data']['state'] = sanitize_input($_POST['state'] ?? '');
        $_SESSION['reg_data']['country'] = sanitize_input($_POST['country'] ?? '');
        $_SESSION['reg_data']['school_name'] = sanitize_input($_POST['school_name'] ?? '');

        // Validate Step 2
        if (empty($_SESSION['reg_data']['city']) || empty($_SESSION['reg_data']['state']) || empty($_SESSION['reg_data']['country']) || empty($_SESSION['reg_data']['school_name'])) {
            $message = "City, State, Country, and School Name are required.";
        } else {
            $_SESSION['registration_step'] = 3;
            header('Location: register.php');
            exit;
        }
    } elseif ($step === 3) {
        $_SESSION['reg_data']['date_of_birth'] = sanitize_input($_POST['date_of_birth'] ?? '');
        $_SESSION['reg_data']['grade'] = sanitize_input($_POST['grade'] ?? '');
        $_SESSION['reg_data']['address'] = sanitize_input($_POST['address'] ?? '');
        $_SESSION['reg_data']['gender'] = sanitize_input($_POST['gender'] ?? '');

        // Handle passport image upload
        $passport_image = '';
        if (isset($_FILES['passport_image']) && $_FILES['passport_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/passports/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['passport_image']['name']);
            $upload_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['passport_image']['tmp_name'], $upload_path)) {
                $passport_image = $file_name;
            } else {
                $message = "Failed to upload passport image.";
            }
        }

        // Validate Step 3
        if (empty($_SESSION['reg_data']['date_of_birth']) || empty($_SESSION['reg_data']['grade']) || empty($_SESSION['reg_data']['address']) || empty($_SESSION['reg_data']['gender']) || empty($passport_image)) {
            $message = "Date of Birth, Grade, Address, Gender, and Passport Image are required.";
        } else {
            try {
                // Auto-generate username if not provided
                $username_to_use = $_SESSION['reg_data']['username'];
                if (empty($username_to_use)) {
                    $base_username = strtolower(str_replace(' ', '', $_SESSION['reg_data']['first_name'])) . '.' . strtolower(str_replace(' ', '', $_SESSION['reg_data']['last_name']));
                    $generated_username = $base_username;
                    $counter = 1;
                    while (true) {
                        $stmt_check_gen_username = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                        $stmt_check_gen_username->execute(['username' => $generated_username]);
                        if ($stmt_check_gen_username->fetchColumn() == 0) {
                            $username_to_use = $generated_username;
                            break;
                        }
                        $generated_username = $base_username . $counter++;
                    }
                }

                $role = 'student';
                // Insert user without password_hash, reset_token, auto_login_token fields
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        first_name, last_name, username, email, city, state, country,
                        date_of_birth, grade, address, gender, passport_image_path,
                        role, school_name
                    ) VALUES (
                        :first_name, :last_name, :username, :email, :city, :state, :country,
                        :date_of_birth, :grade, :address, :gender, :passport_image_path,
                        :role, :school_name
                    )
                ");
                $result = $stmt->execute([
                    'first_name' => $_SESSION['reg_data']['first_name'],
                    'last_name' => $_SESSION['reg_data']['last_name'],
                    'username' => $username_to_use,
                    'email' => $_SESSION['reg_data']['email'],
                    'city' => $_SESSION['reg_data']['city'],
                    'state' => $_SESSION['reg_data']['state'],
                    'country' => $_SESSION['reg_data']['country'],
                    'date_of_birth' => $_SESSION['reg_data']['date_of_birth'],
                    'grade' => $_SESSION['reg_data']['grade'],
                    'address' => $_SESSION['reg_data']['address'],
                    'gender' => $_SESSION['reg_data']['gender'],
                    'passport_image_path' => $passport_image,
                    'role' => $role,
                    'school_name' => $_SESSION['reg_data']['school_name']
                ]);

                if ($result) {
                    ob_start();
                    require '../includes/email_templates/registration_confirmation_email.php';
                    $email_body = ob_get_clean();
                    $email_body = str_replace('{{username}}', htmlspecialchars($username_to_use), $email_body);
                    $subject = "Registration Confirmation - Mackenny Assessment";

                    if (sendEmail($_SESSION['reg_data']['email'], $subject, $email_body)) {
                        $_SESSION['form_message'] = "Registration successful! Your application will be reviewed, and we'll get back to you.";
                        $_SESSION['form_message_type'] = 'success';
                    } else {
                        error_log("Failed to send confirmation email to " . $_SESSION['reg_data']['email']);
                        $_SESSION['form_message'] = "Registration successful, but we could not send the confirmation email.";
                        $_SESSION['form_message_type'] = 'warning';
                    }

                    // Clear session data
                    unset($_SESSION['reg_data']);
                    unset($_SESSION['registration_step']);
                    redirect('registration_confirmed.php');
                } else {
                    $message = "Registration failed. Please try again.";
                }
            } catch (PDOException $e) {
                error_log("Registration Error: " . $e->getMessage());
                $message = "An unexpected error occurred during registration. Please try again later.";
            }
        }
    }

    if (!empty($message)) {
        $_SESSION['form_message'] = $message;
        $_SESSION['form_message_type'] = 'error';
        $_SESSION['registration_step'] = $step;
        header('Location: register.php');
        exit;
    }
}

// Load current data from session if available
$current_first_name = htmlspecialchars($_SESSION['reg_data']['first_name'] ?? '');
$current_last_name = htmlspecialchars($_SESSION['reg_data']['last_name'] ?? '');
$current_username = htmlspecialchars($_SESSION['reg_data']['username'] ?? '');
$current_email = htmlspecialchars($_SESSION['reg_data']['email'] ?? '');
$current_city = htmlspecialchars($_SESSION['reg_data']['city'] ?? '');
$current_state = htmlspecialchars($_SESSION['reg_data']['state'] ?? '');
$current_country = htmlspecialchars($_SESSION['reg_data']['country'] ?? '');
$current_school_name = htmlspecialchars($_SESSION['reg_data']['school_name'] ?? '');
$current_date_of_birth = htmlspecialchars($_SESSION['reg_data']['date_of_birth'] ?? '');
$current_grade = htmlspecialchars($_SESSION['reg_data']['grade'] ?? '');
$current_address = htmlspecialchars($_SESSION['reg_data']['address'] ?? '');
$current_gender = htmlspecialchars($_SESSION['reg_data']['gender'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    .bg-navy-900 { background-color: #0a1930; }
    .bg-navy-800 { background-color: #1a2b4a; }
    .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
    .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
    .text-theme-color { color: #1e4b31; }
    .step-circle { 
        @apply w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold;
    }
    .step-active { @apply bg-navy-900; }
    .step-inactive { @apply bg-gray-300; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-50 min-h-screen flex items-center justify-center">

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden">
        <div class="bg-navy-900 text-white p-12 flex flex-col justify-center">
            <h1 class="text-4xl font-bold mb-4">Join Mackenny Assessment</h1>
            <p class="text-lg mb-6">
                Complete the three-step registration process to submit your application.
                Your details will be reviewed, and you will be notified upon approval.
            </p>
            <p class="text-sm italic">
                Start your journey towards academic excellence today!
            </p>
        </div>
        
        <div class="p-12 relative">
            <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Registration - Step <?= $current_step ?>/3</h2>
            
            <div class="flex justify-center mb-6">
                <div class="flex items-center space-x-4">
                    <div class="step-circle <?= $current_step >= 1 ? 'step-active' : 'step-inactive' ?>">1</div>
                    <div class="w-12 h-1 bg-gray-300 <?= $current_step >= 2 ? 'bg-navy-900' : '' ?>"></div>
                    <div class="step-circle <?= $current_step >= 2 ? 'step-active' : 'step-inactive' ?>">2</div>
                    <div class="w-12 h-1 bg-gray-300 <?= $current_step >= 3 ? 'bg-navy-900' : '' ?>"></div>
                    <div class="step-circle <?= $current_step >= 3 ? 'step-active' : 'step-inactive' ?>">3</div>
                </div>
            </div>

            <div id="form-notification" class="absolute top-0 left-0 w-full px-4 py-3 rounded-md hidden" role="alert">
                <strong class="font-bold"></strong>
                <span class="block sm:inline" id="notification-message-content"></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                    <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                        <path d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </span>
            </div>

            <form action="register.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="step" value="<?= $current_step ?>">
                
                <?php if ($current_step === 1): ?>
                    <div>
                        <label for="first_name" class="block text-gray-700 text-sm font-semibold mb-2">First Name</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            required
                            maxlength="50"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your first name"
                            value="<?= $current_first_name ?>"
                        >
                    </div>
                    <div>
                        <label for="last_name" class="block text-gray-700 text-sm font-semibold mb-2">Last Name</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            required
                            maxlength="50"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your last name"
                            value="<?= $current_last_name ?>"
                        >
                    </div>
                    <div>
                        <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username (Optional)</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            maxlength="50"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Choose a username (or leave blank to auto-generate)"
                            value="<?= $current_username ?>"
                        >
                    </div>
                    <div>
                        <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            maxlength="100"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your email address"
                            value="<?= $current_email ?>"
                        >
                    </div>
                <?php elseif ($current_step === 2): ?>
                    <div>
                        <label for="city" class="block text-gray-700 text-sm font-semibold mb-2">City</label>
                        <input 
                            type="text" 
                            id="city" 
                            name="city" 
                            required
                            maxlength="100"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your city"
                            value="<?= $current_city ?>"
                        >
                    </div>
                    <div>
                        <label for="state" class="block text-gray-700 text-sm font-semibold mb-2">State</label>
                        <input 
                            type="text" 
                            id="state" 
                            name="state" 
                            required
                            maxlength="100"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your state"
                            value="<?= $current_state ?>"
                        >
                    </div>
                    <div>
                        <label for="country" class="block text-gray-700 text-sm font-semibold mb-2">Country</label>
                        <input 
                            type="text" 
                            id="country" 
                            name="country" 
                            required
                            maxlength="100"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your country"
                            value="<?= $current_country ?>"
                        >
                    </div>
                    <div>
                        <label for="school_name" class="block text-gray-700 text-sm font-semibold mb-2">School Name</label>
                        <input 
                            type="text" 
                            id="school_name" 
                            name="school_name" 
                            required
                            maxlength="255"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your school name"
                            value="<?= $current_school_name ?>"
                        >
                    </div>
                <?php elseif ($current_step === 3): ?>
                    <div>
                        <label for="date_of_birth" class="block text-gray-700 text-sm font-semibold mb-2">Date of Birth</label>
                        <input 
                            type="date" 
                            id="date_of_birth" 
                            name="date_of_birth" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            value="<?= $current_date_of_birth ?>"
                        >
                    </div>
                    <div>
                        <label for="grade" class="block text-gray-700 text-sm font-semibold mb-2">Grade</label>
                        <select 
                            id="grade" 
                            name="grade" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                        >
                            <option value="" disabled <?php echo empty($current_grade) ? 'selected' : ''; ?>>Select Grade</option>
                            <?php
                            $grades = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
                            foreach ($grades as $grade_option) {
                                echo "<option value='$grade_option' " . ($current_grade === $grade_option ? 'selected' : '') . ">$grade_option</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="address" class="block text-gray-700 text-sm font-semibold mb-2">Address</label>
                        <input 
                            type="text" 
                            id="address" 
                            name="address" 
                            required
                            maxlength="255"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your address"
                            value="<?= $current_address ?>"
                        >
                    </div>
                    <div>
                        <label for="gender" class="block text-gray-700 text-sm font-semibold mb-2">Gender</label>
                        <select 
                            id="gender" 
                            name="gender" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                        >
                            <option value="" disabled <?php echo empty($current_gender) ? 'selected' : ''; ?>>Select Gender</option>
                            <option value="male" <?php echo $current_gender === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $current_gender === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $current_gender === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="passport_image" class="block text-gray-700 text-sm font-semibold mb-2">Passport Image</label>
                        <input 
                            type="file" 
                            id="passport_image" 
                            name="passport_image" 
                            accept="image/*"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                        >
                    </div>
                <?php endif; ?>

                <div class="flex items-center justify-between">
                    <?php if ($current_step > 1): ?>
                        <button 
                            type="button"
                            onclick="window.location.href='register.php?step=<?= $current_step - 1 ?>'"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200"
                        >
                            Previous
                        </button>
                    <?php endif; ?>
                    <button 
                        type="submit"
                        class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200"
                    >
                        <?= $current_step === 3 ? 'Submit' : 'Next' ?>
                    </button>
                    <a href="login.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium hover:underline">
                        Already have an account? Login here.
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function displayNotification(message, type) {
        const notificationContainer = document.getElementById('form-notification');
        const messageContentElement = document.getElementById('notification-message-content');
        const strongTag = notificationContainer.querySelector('strong');

        notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
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
            setTimeout(() => {
                notificationContainer.style.transition = 'transform 0.3s ease-out';
                notificationContainer.style.transform = 'translateY(0)';
            }, 10);
        } else {
            hideNotification();
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

    <?php if (isset($_SESSION['form_message'])): ?>
        displayNotification("<?= htmlspecialchars($_SESSION['form_message']) ?>", "<?= htmlspecialchars($_SESSION['form_message_type']) ?>");
        <?php
        unset($_SESSION['form_message']);
        unset($_SESSION['form_message_type']);
        ?>
    <?php endif; ?>
</script>

</body>
</html>