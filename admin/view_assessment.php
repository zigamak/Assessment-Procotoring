<?php
// admin/view_assessment.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure only admins can access
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../auth/login.php');
    exit;
}

$quiz_id = $_GET['quiz_id'] ?? 0;
$assessment = [];

// Fetch assessment data
try {
    $stmt = $pdo->prepare("
        SELECT quiz_id, title, description, grade, open_datetime, is_paid 
        FROM quizzes 
        WHERE quiz_id = :quiz_id
    ");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assessment) {
        $_SESSION['form_message'] = "Assessment not found.";
        $_SESSION['form_message_type'] = 'error';
        redirect('assessments.php');
    }
} catch (PDOException $e) {
    error_log("Fetch Assessment Error: " . $e->getMessage());
    $_SESSION['form_message'] = "Could not fetch assessment details.";
    $_SESSION['form_message_type'] = 'error';
    redirect('assessments.php');
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
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <?php require_once '../includes/header_admin.php'; ?>

        <main class="flex-1 p-4 lg:p-8 mt-16 w-full max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Assessment Details</h1>
                <div class="flex space-x-4">
                    <a href="edit_assessment.php?quiz_id=<?= htmlspecialchars($quiz_id) ?>" 
                       class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                        Edit Assessment
                    </a>
                    <a href="assessments.php" 
                       class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg transition duration-300">
                        Back to Assessments
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['form_message'])): ?>
                <div id="form-notification" class="mb-4 px-4 py-3 rounded-md <?php echo $_SESSION['form_message_type'] === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?>" role="alert">
                    <strong class="font-bold"><?php echo $_SESSION['form_message_type'] === 'success' ? 'Success!' : 'Error!'; ?></strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['form_message']); ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="this.parentElement.remove()">
                        <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                            <path d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                </div>
                <?php unset($_SESSION['form_message'], $_SESSION['form_message_type']); ?>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Assessment Information</h2>
                <div class="space-y-4 text-gray-700">
                    <p><strong class="font-semibold text-gray-900">Title:</strong> <span class="ml-2"><?php echo htmlspecialchars($assessment['title']); ?></span></p>
                    <p><strong class="font-semibold text-gray-900">Grade:</strong> <span class="ml-2"><?php echo htmlspecialchars($assessment['grade'] ?? 'N/A'); ?></span></p>
                    <p><strong class="font-semibold text-gray-900">Opening Date/Time:</strong> <span class="ml-2"><?php echo $assessment['open_datetime'] ? date('M j, Y g:i A', strtotime($assessment['open_datetime'])) : 'Immediate'; ?></span></p>
                    <p><strong class="font-semibold text-gray-900">Status:</strong> 
                        <span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full <?php echo $assessment['is_paid'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $assessment['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                        </span>
                    </p>
                    <p><strong class="font-semibold text-gray-900">Description:</strong> <span class="ml-2"><?php echo htmlspecialchars($assessment['description'] ?? 'No description provided'); ?></span></p>
                </div>
                <div class="mt-6 pt-4 border-t">
                    <a href="<?php echo BASE_URL; ?>admin/questions.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                       class="text-navy-900 hover:text-navy-700 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                        </svg>
                        Manage Questions
                    </a>
                </div>
            </div>
        </main>

        <?php require_once '../includes/footer_admin.php'; ?>
    </div>
</body>
</html>