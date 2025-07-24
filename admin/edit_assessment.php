<?php
// admin/edit_assessment.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

// Ensure only admins can access
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../auth/login.php');
    exit;
}

// Initialize $assessment variable to avoid errors if not set
$assessment = null;
$quiz_id = 0;

// Define all possible grades for checkboxes
$allPossibleGrades = [
    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
    'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'
];

// Handle form submission first if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_assessment') {
    // Sanitize and validate POST data
    $quiz_id = sanitize_input($_POST['quiz_id'] ?? 0); // Get quiz_id from hidden input
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $max_attempts = sanitize_input($_POST['max_attempts'] ?? 0);
    $duration_minutes = !empty($_POST['duration_minutes']) ? sanitize_input($_POST['duration_minutes']) : NULL;
    $open_datetime = !empty($_POST['open_datetime']) ? sanitize_input($_POST['open_datetime']) : NULL;

    // --- MODIFICATION START: Handle multiple grades from checkboxes ---
    $selected_grades_array = $_POST['grades'] ?? []; // Get array of selected grades
    // Sanitize each grade in the array and then implode to a comma-separated string
    $grade = !empty($selected_grades_array) ? implode(',', array_map('sanitize_input', $selected_grades_array)) : NULL;
    // --- MODIFICATION END ---

    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $assessment_fee = ($is_paid && !empty($_POST['assessment_fee'])) ? sanitize_input($_POST['assessment_fee']) : NULL;

    // Validation
    if ($quiz_id <= 0) {
        $_SESSION['form_message'] = "Invalid assessment ID for update.";
        $_SESSION['form_message_type'] = 'error';
    } elseif ($is_paid && ($assessment_fee === NULL || !is_numeric($assessment_fee) || $assessment_fee < 0)) {
        $_SESSION['form_message'] = "Assessment fee is required and must be a valid positive number if the assessment is paid.";
        $_SESSION['form_message_type'] = 'error';
    } elseif (empty($title)) {
        $_SESSION['form_message'] = "Assessment title is required.";
        $_SESSION['form_message_type'] = 'error';
    } elseif (empty($selected_grades_array)) { // New validation for grades
        $_SESSION['form_message'] = "At least one grade level must be selected.";
        $_SESSION['form_message_type'] = 'error';
    }
    else {
        try {
            $stmt = $pdo->prepare("
                UPDATE quizzes
                SET title = :title, description = :description, max_attempts = :max_attempts,
                    duration_minutes = :duration_minutes, open_datetime = :open_datetime,
                    grade = :grade, is_paid = :is_paid, assessment_fee = :assessment_fee,
                    updated_at = NOW()
                WHERE quiz_id = :quiz_id
            ");
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'max_attempts' => $max_attempts,
                'duration_minutes' => $duration_minutes,
                'open_datetime' => $open_datetime,
                'grade' => $grade, // This will now be a comma-separated string
                'is_paid' => $is_paid,
                'assessment_fee' => $assessment_fee,
                'quiz_id' => $quiz_id
            ]);
            error_log("Edit Assessment: Successfully updated quiz_id {$quiz_id} by user_id " . getUserId());
            $_SESSION['form_message'] = "Assessment updated successfully!";
            $_SESSION['form_message_type'] = 'success';
            redirect('assessments.php'); // Redirect after successful update
            exit;
        } catch (PDOException $e) {
            error_log("Edit Assessment Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
            $_SESSION['form_message'] = "Database error while updating assessment: " . htmlspecialchars($e->getMessage());
            $_SESSION['form_message_type'] = 'error';
        }
    }
    // If there was an error, the script will continue to display the form with the error message
    // and re-fetch the assessment details to populate the form correctly.
}

// Fetch assessment details (for initial load or if POST request failed validation)
if (isset($_GET['quiz_id'])) {
    $quiz_id = (int)$_GET['quiz_id'];
} elseif (isset($_POST['quiz_id'])) { // In case of a POST failure, use the posted quiz_id to re-populate
    $quiz_id = (int)$_POST['quiz_id'];
}

if ($quiz_id <= 0) {
    $_SESSION['form_message'] = "Invalid assessment ID.";
    $_SESSION['form_message_type'] = 'error';
    redirect('assessments.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT quiz_id, title, description, max_attempts, duration_minutes, grade,
                is_paid, assessment_fee, open_datetime
        FROM quizzes
        WHERE quiz_id = :quiz_id
    ");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assessment) {
        $_SESSION['form_message'] = "Assessment not found.";
        $_SESSION['form_message_type'] = 'error';
        redirect('assessments.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Fetch Assessment Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "Database error while fetching assessment: " . htmlspecialchars($e->getMessage());
    $_SESSION['form_message_type'] = 'error';
    redirect('assessments.php');
    exit;
}

// --- MODIFICATION START: Prepare current grades for checkbox pre-selection ---
// If the form was submitted and failed validation, use the posted grades
// Otherwise, use the grades from the database
$current_grades_for_display = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grades'])) {
    $current_grades_for_display = $_POST['grades'];
} elseif (!empty($assessment['grade'])) {
    $current_grades_for_display = explode(',', $assessment['grade']);
}
// --- MODIFICATION END ---


require_once '../includes/header_admin.php'; // Only include once at the top
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assessment - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .bg-navy-900 { background-color: #0a1930; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
        .border-custom-dark { border-color: #171248; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <main class="flex-1 p-4 lg:p-8 mt-16 w-full max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Assessment</h1>

        <div id="form-notification" class="px-4 py-3 rounded-md hidden z-50 mb-6" role="alert">
            <strong class="font-bold"></strong>
            <span class="block sm:inline" id="notification-message-content"></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                    <path d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </span>
        </div>

        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-h-[80vh] overflow-y-auto">
            <form action="edit_assessment.php" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="edit_assessment">
                <input type="hidden" id="edit_quiz_id" name="quiz_id" value="<?php echo htmlspecialchars($assessment['quiz_id']); ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="edit_title" class="block text-gray-700 text-sm font-bold mb-2">Assessment Title:</label>
                        <input type="text" id="edit_title" name="title" value="<?php echo htmlspecialchars($assessment['title']); ?>" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Applicable Grade Level(s):</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                            <?php foreach ($allPossibleGrades as $gradeOption): ?>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="grades[]" value="<?php echo htmlspecialchars($gradeOption); ?>"
                                           class="form-checkbox h-5 w-5 text-navy-900"
                                           <?php echo in_array($gradeOption, $current_grades_for_display) ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($gradeOption); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Select one or more grades this assessment applies to.</p>
                    </div>
                    <div>
                        <label for="edit_max_attempts" class="block text-gray-700 text-sm font-bold mb-2">Max Attempts (0 for unlimited):</label>
                        <input type="number" id="edit_max_attempts" name="max_attempts" value="<?php echo htmlspecialchars($assessment['max_attempts']); ?>" min="0" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                    </div>
                    
                    <div>
                        <label for="edit_duration_minutes" class="block text-gray-700 text-sm font-bold mb-2">Duration (minutes, leave blank for no limit):</label>
                        <input type="number" id="edit_duration_minutes" name="duration_minutes" value="<?php echo htmlspecialchars($assessment['duration_minutes'] ?? ''); ?>" min="1"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                    </div>
                    
                    <div>
                        <label for="edit_open_datetime" class="block text-gray-700 text-sm font-bold mb-2">Opening Date/Time:</label>
                        <input type="datetime-local" id="edit_open_datetime" name="open_datetime" value="<?php echo $assessment['open_datetime'] ? date('Y-m-d\TH:i', strtotime($assessment['open_datetime'])) : ''; ?>"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                    </div>
                    
                    <div>
                        <div class="flex items-center mb-2">
                            <input type="checkbox" id="edit_is_paid" name="is_paid" value="1" <?php echo $assessment['is_paid'] ? 'checked' : ''; ?>
                                    class="form-checkbox h-5 w-5 text-navy-900">
                            <label for="edit_is_paid" class="ml-2 text-gray-700">Requires Payment</label>
                        </div>
                        <div id="edit_assessment_fee_group" class="<?php echo $assessment['is_paid'] ? '' : 'hidden'; ?>">
                            <label for="edit_assessment_fee" class="block text-gray-700 text-sm font-bold mb-2">Assessment Fee (NGN):</label>
                            <input type="number" id="edit_assessment_fee" name="assessment_fee" value="<?php echo htmlspecialchars($assessment['assessment_fee'] ?? ''); ?>" step="0.01" min="0"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900" <?php echo $assessment['is_paid'] ? 'required' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="edit_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                        <textarea id="edit_description" name="description" rows="3"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900"><?php echo htmlspecialchars($assessment['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="flex justify-between space-x-4 mt-6 pt-4 border-t">
                    <a href="assessments.php"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Cancel
                    </a>
                    <button type="submit"
                                class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php require_once '../includes/footer_admin.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        function displayNotification(message, type) {
            const notificationContainer = document.getElementById('form-notification');
            const messageContentElement = document.getElementById('notification-message-content');
            const strongTag = notificationContainer.querySelector('strong');

            notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
            strongTag.textContent = '';

            if (message) {
                messageContentElement.textContent = message;
                if (type === 'success') {
                    notificationContainer.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
                    strongTag.textContent = 'Success!';
                } else {
                    notificationContainer.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
                    strongTag.textContent = 'Error!';
                }
                notificationContainer.classList.remove('hidden');
                setTimeout(() => {
                    hideNotification();
                }, 5000);
            }
        }

        function hideNotification() {
            const notificationElement = document.getElementById('form-notification');
            notificationElement.classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Toggle payment requirement fields
            const editIsPaidCheckbox = document.getElementById('edit_is_paid');
            const editAssessmentFeeGroup = document.getElementById('edit_assessment_fee_group');
            const editAssessmentFeeInput = document.getElementById('edit_assessment_fee');
            function toggleEditAssessmentFeeVisibility() {
                if (editIsPaidCheckbox.checked) {
                    editAssessmentFeeGroup.classList.remove('hidden');
                    editAssessmentFeeInput.setAttribute('required', 'required');
                } else {
                    editAssessmentFeeGroup.classList.add('hidden');
                    editAssessmentFeeInput.removeAttribute('required');
                    editAssessmentFeeInput.value = '';
                }
            }
            editIsPaidCheckbox.addEventListener('change', toggleEditAssessmentFeeVisibility);
            toggleEditAssessmentFeeVisibility();

            // Initialize datetime picker
            flatpickr("#edit_open_datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today", // Assuming an assessment cannot be set to open in the past relative to today's date.
                time_24hr: true
            });

            // Display session messages
            <?php if (isset($_SESSION['form_message'])): ?>
                displayNotification("<?php echo htmlspecialchars($_SESSION['form_message']); ?>", 
                                     "<?php echo htmlspecialchars($_SESSION['form_message_type']); ?>");
                <?php
                unset($_SESSION['form_message']);
                unset($_SESSION['form_message_type']);
                ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>