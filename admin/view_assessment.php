<?php
// admin/view_assessment.php
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

// Check if quiz_id is provided
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
if ($quiz_id <= 0) {
    $_SESSION['form_message'] = "Invalid assessment ID.";
    $_SESSION['form_message_type'] = 'error';
    redirect('assessments.php');
    exit;
}

// Fetch assessment details
try {
    $stmt = $pdo->prepare("
        SELECT quiz_id, title, description, max_attempts, duration_minutes, grade,
               is_paid, assessment_fee, open_datetime, created_at, updated_at
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

require_once '../includes/header_admin.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assessment - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Assessment Details</h1>

        <!-- Notification -->
        <div id="form-notification" class="fixed top-4 right-4 px-4 py-3 rounded-md hidden z-50" role="alert">
            <strong class="font-bold"></strong>
            <span class="block sm:inline" id="notification-message-content"></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                    <path d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </span>
        </div>

        <!-- Assessment Details -->
        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md max-h-[80vh] overflow-y-auto">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Title:</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($assessment['title']); ?></p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Description:</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($assessment['description'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Grade Level:</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($assessment['grade'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Max Attempts:</label>
                    <p class="text-gray-900"><?php echo $assessment['max_attempts'] == 0 ? 'Unlimited' : htmlspecialchars($assessment['max_attempts']); ?></p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Duration:</label>
                    <p class="text-gray-900"><?php echo $assessment['duration_minutes'] ? htmlspecialchars($assessment['duration_minutes']) . ' minutes' : 'No limit'; ?></p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Opening Date/Time:</label>
                    <p class="text-gray-900"><?php echo $assessment['open_datetime'] ? date('M j, Y g:i A', strtotime($assessment['open_datetime'])) : 'Immediate'; ?></p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Payment Required:</label>
                    <p class="text-gray-900"><?php echo $assessment['is_paid'] ? 'Yes' : 'No'; ?></p>
                </div>
                <div class="<?php echo $assessment['is_paid'] ? '' : 'hidden'; ?>">
                    <label class="block text-gray-700 text-sm font-bold mb-1">Assessment Fee (NGN):</label>
                    <p class="text-gray-900"><?php echo $assessment['assessment_fee'] ? 'NGN ' . htmlspecialchars($assessment['assessment_fee']) : 'N/A'; ?></p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Created At:</label>
                    <p class="text-gray-900"><?php echo date('M j, Y g:i A', strtotime($assessment['created_at'])); ?></p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1">Updated At:</label>
                    <p class="text-gray-900"><?php echo $assessment['updated_at'] ? date('M j, Y g:i A', strtotime($assessment['updated_at'])) : 'N/A'; ?></p>
                </div>
            </div>
            <div class="flex justify-between space-x-4 mt-6">
                <a href="<?php echo BASE_URL; ?>admin/questions.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                   class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                    </svg>
                    Manage Questions
                </a>
                <a href="assessments.php"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    Close
                </a>
            </div>
        </div>
    </main>

    <?php require_once '../includes/footer_admin.php'; ?>

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
                notificationContainer.style.transform = 'translateY(-100%)';
                setTimeout(() => {
                    notificationContainer.style.transition = 'transform 0.3s ease-out';
                    notificationContainer.style.transform = 'translateY(0)';
                }, 10);
                setTimeout(() => {
                    hideNotification();
                }, 5000);
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

        document.addEventListener('DOMContentLoaded', function() {
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