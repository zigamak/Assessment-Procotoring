<?php
// admin/add_assessment.php
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_assessment') {
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $max_attempts = sanitize_input($_POST['max_attempts'] ?? 0);
    $duration_minutes = !empty($_POST['duration_minutes']) ? sanitize_input($_POST['duration_minutes']) : NULL;
    $open_datetime = !empty($_POST['open_datetime']) ? sanitize_input($_POST['open_datetime']) : NULL;
    $grade = sanitize_input($_POST['grade'] ?? NULL);
    $created_by = getUserId();
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $assessment_fee = ($is_paid && !empty($_POST['assessment_fee'])) ? sanitize_input($_POST['assessment_fee']) : NULL;

    if ($is_paid && ($assessment_fee === NULL || !is_numeric($assessment_fee) || $assessment_fee < 0)) {
        $_SESSION['form_message'] = "Assessment fee is required and must be a valid positive number if the assessment is paid.";
        $_SESSION['form_message_type'] = 'error';
    } elseif (empty($title)) {
        $_SESSION['form_message'] = "Assessment title is required.";
        $_SESSION['form_message_type'] = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO quizzes (title, description, max_attempts, duration_minutes, 
                                     open_datetime, grade, is_paid, assessment_fee, created_by, created_at, updated_at)
                VALUES (:title, :description, :max_attempts, :duration_minutes, 
                               :open_datetime, :grade, :is_paid, :assessment_fee, :created_by, NOW(), NOW())
            ");
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'max_attempts' => $max_attempts,
                'duration_minutes' => $duration_minutes,
                'open_datetime' => $open_datetime,
                'grade' => $grade,
                'is_paid' => $is_paid,
                'assessment_fee' => $assessment_fee,
                'created_by' => $created_by
            ]);
            error_log("Add Assessment: Successfully added quiz_id {$pdo->lastInsertId()} by user_id {$created_by}");
            $_SESSION['form_message'] = "Assessment added successfully!";
            $_SESSION['form_message_type'] = 'success';
            redirect('assessments.php');
            exit;
        } catch (PDOException $e) {
            error_log("Add Assessment Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
            $_SESSION['form_message'] = "Database error while adding assessment: " . htmlspecialchars($e->getMessage());
            $_SESSION['form_message_type'] = 'error';
        }
    }
}

require_once '../includes/header_admin.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Assessment - Mackenny Assessment</title>
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
    <?php require_once '../includes/header_admin.php'; ?>

    <main class="flex-1 p-4 lg:p-8 mt-16 w-full max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Add New Assessment</h1>

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
            <form action="add_assessment.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_assessment">
                
                <div>
                    <label for="add_title" class="block text-gray-700 text-sm font-bold mb-2">Assessment Title:</label>
                    <input type="text" id="add_title" name="title" required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                </div>
                
                <div>
                    <label for="add_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                    <textarea id="add_description" name="description" rows="3"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900"></textarea>
                </div>
                
                <div>
                    <label for="add_grade" class="block text-gray-700 text-sm font-bold mb-2">Grade Level:</label>
                    <select id="add_grade" name="grade" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                        <option value="">Select Grade</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="Grade <?php echo $i; ?>">Grade <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label for="add_max_attempts" class="block text-gray-700 text-sm font-bold mb-2">Max Attempts (0 for unlimited):</label>
                    <input type="number" id="add_max_attempts" name="max_attempts" value="1" min="0" required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                </div>
                
                <div>
                    <label for="add_duration_minutes" class="block text-gray-700 text-sm font-bold mb-2">Duration (minutes, leave blank for no limit):</label>
                    <input type="number" id="add_duration_minutes" name="duration_minutes" min="1"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                </div>
                
                <div>
                    <label for="add_open_datetime" class="block text-gray-700 text-sm font-bold mb-2">Opening Date/Time:</label>
                    <input type="datetime-local" id="add_open_datetime" name="open_datetime"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                </div>
                
                <div>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" id="add_is_paid" name="is_paid" value="1"
                                    class="form-checkbox h-5 w-5 text-navy-900">
                        <label for="add_is_paid" class="ml-2 text-gray-700">Requires Payment</label>
                    </div>
                    <div id="add_assessment_fee_group" class="hidden">
                        <label for="add_assessment_fee" class="block text-gray-700 text-sm font-bold mb-2">Assessment Fee (NGN):</label>
                        <input type="number" id="add_assessment_fee" name="assessment_fee" step="0.01" min="0"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                    </div>
                </div>
                
                <div class="flex justify-between space-x-4 mt-6">
                    <a href="assessments.php"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Cancel
                    </a>
                    <button type="submit"
                                    class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Add Assessment
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
                // Removed the translateY for slide-in effect as it's no longer fixed
                // notificationContainer.style.transform = 'translateY(-100%)'; 
                // setTimeout(() => {
                //     notificationContainer.style.transition = 'transform 0.3s ease-out';
                //     notificationContainer.style.transform = 'translateY(0)';
                // }, 10);
                setTimeout(() => {
                    hideNotification();
                }, 5000);
            }
        }

        function hideNotification() {
            const notificationElement = document.getElementById('form-notification');
            // Removed the translateY for slide-out effect as it's no longer fixed
            // notificationElement.style.transition = 'transform 0.3s ease-in';
            // notificationElement.style.transform = 'translateY(-100%)';
            // notificationElement.addEventListener('transitionend', function handler() {
            //     notificationElement.classList.add('hidden');
            //     notificationElement.removeEventListener('transitionend', handler);
            // });
            notificationElement.classList.add('hidden'); // Just hide it directly
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Toggle payment requirement fields
            const addIsPaidCheckbox = document.getElementById('add_is_paid');
            const addAssessmentFeeGroup = document.getElementById('add_assessment_fee_group');
            const addAssessmentFeeInput = document.getElementById('add_assessment_fee');
            function toggleAddAssessmentFeeVisibility() {
                if (addIsPaidCheckbox.checked) {
                    addAssessmentFeeGroup.classList.remove('hidden');
                    addAssessmentFeeInput.setAttribute('required', 'required');
                } else {
                    addAssessmentFeeGroup.classList.add('hidden');
                    addAssessmentFeeInput.removeAttribute('required');
                    addAssessmentFeeInput.value = '';
                }
            }
            addIsPaidCheckbox.addEventListener('change', toggleAddAssessmentFeeVisibility);
            toggleAddAssessmentFeeVisibility();

            // Initialize datetime picker
            flatpickr("#add_open_datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today",
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