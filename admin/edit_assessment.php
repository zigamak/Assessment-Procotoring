<?php
// admin/edit_assessment.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../auth/login.php');
    exit;
}

$quiz_id = $_GET['quiz_id'] ?? 0;
$assessment = [];

// Fetch assessment data
try {
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = :quiz_id");
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $grade = sanitize_input($_POST['grade'] ?? NULL);
    $open_datetime = !empty($_POST['open_datetime']) ? sanitize_input($_POST['open_datetime']) : NULL;
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $assessment_fee = ($is_paid && !empty($_POST['assessment_fee'])) ? sanitize_input($_POST['assessment_fee']) : NULL;

    // Validate assessment fee
    if ($is_paid && ($assessment_fee === NULL || !is_numeric($assessment_fee) || $assessment_fee < 0)) {
        $_SESSION['form_message'] = "Assessment fee must be a valid positive number if the assessment is paid.";
        $_SESSION['form_message_type'] = 'error';
    } elseif (empty($title)) {
        $_SESSION['form_message'] = "Assessment title is required.";
        $_SESSION['form_message_type'] = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE quizzes 
                SET title = :title, description = :description, grade = :grade,
                    open_datetime = :open_datetime, is_paid = :is_paid, assessment_fee = :assessment_fee 
                WHERE quiz_id = :quiz_id
            ");
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'grade' => $grade,
                'open_datetime' => $open_datetime,
                'is_paid' => $is_paid,
                'assessment_fee' => $assessment_fee,
                'quiz_id' => $quiz_id
            ]);
            $_SESSION['form_message'] = "Assessment updated successfully!";
            $_SESSION['form_message_type'] = 'success';
            redirect('view_assessment.php?quiz_id=' . $quiz_id);
        } catch (PDOException $e) {
            error_log("Edit Assessment Error: " . $e->getMessage());
            $_SESSION['form_message'] = "Database error while updating assessment.";
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
    <title>Edit Assessment - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                <h1 class="text-3xl font-bold text-gray-800">Edit Assessment</h1>
                <div class="flex space-x-4">
                    <a href="<?php echo BASE_URL; ?>admin/questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>"
                       class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                        </svg>
                        Manage Questions
                    </a>
                    <a href="view_assessment.php?quiz_id=<?= htmlspecialchars($quiz_id) ?>" 
                       class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg transition duration-300">
                        Back to View
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

            <div class="bg-white p-6 rounded-lg shadow-md max-h-[80vh] overflow-y-auto">
                <form action="edit_assessment.php?quiz_id=<?= htmlspecialchars($quiz_id) ?>" method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="edit_title" class="block text-gray-700 text-sm font-bold mb-2">Assessment Title:</label>
                            <input type="text" id="edit_title" name="title" value="<?= htmlspecialchars($assessment['title']) ?>" required
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                        </div>
                        
                        <div>
                            <label for="edit_grade" class="block text-gray-700 text-sm font-bold mb-2">Grade Level:</label>
                            <select id="edit_grade" name="grade" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                                <option value="">Select Grade</option>
                                <?php 
                                $grades = range(1, 12);
                                foreach ($grades as $grade): ?>
                                    <option value="<?= $grade ?>" <?= $assessment['grade'] == $grade ? 'selected' : '' ?>><?= $grade ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="edit_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                        <textarea id="edit_description" name="description" rows="5"
                                  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900"><?= htmlspecialchars($assessment['description']) ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="edit_open_datetime" class="block text-gray-700 text-sm font-bold mb-2">Opening Date/Time:</label>
                            <input type="datetime-local" id="edit_open_datetime" name="open_datetime" value="<?= $assessment['open_datetime'] ? date('Y-m-d\TH:i', strtotime($assessment['open_datetime'])) : '' ?>"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                        </div>
                        
                        <div>
                            <div class="flex items-center mb-2">
                                <input type="checkbox" id="edit_is_paid" name="is_paid" value="1" <?= $assessment['is_paid'] ? 'checked' : '' ?>
                                       class="form-checkbox h-5 w-5 text-navy-900">
                                <label for="edit_is_paid" class="ml-2 text-gray-700">Requires Payment</label>
                            </div>
                            <div id="edit_assessment_fee_group" class="<?= $assessment['is_paid'] ? '' : 'hidden' ?>">
                                <label for="edit_assessment_fee" class="block text-gray-700 text-sm font-bold mb-2">Assessment Fee (NGN):</label>
                                <input type="number" id="edit_assessment_fee" name="assessment_fee" step="0.01" min="0" value="<?= $assessment['assessment_fee'] ?? '' ?>"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-6">
                        <a href="view_assessment.php?quiz_id=<?= htmlspecialchars($quiz_id) ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg transition duration-300">
                            Cancel
                        </a>
                        <button type="submit" class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <?php require_once '../includes/footer_admin.php'; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize datetime picker
            flatpickr("#edit_open_datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today",
                time_24hr: true
            });

            // Toggle payment requirement fields
            const editIsPaidCheckbox = document.getElementById('edit_is_paid');
            const editAssessmentFeeGroup = document.getElementById('edit_assessment_fee_group');
            const editAssessmentFeeInput = document.getElementById('edit_assessment_fee');
            
            editIsPaidCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    editAssessmentFeeGroup.classList.remove('hidden');
                    editAssessmentFeeInput.setAttribute('required', 'required');
                } else {
                    editAssessmentFeeGroup.classList.add('hidden');
                    editAssessmentFeeInput.removeAttribute('required');
                    editAssessmentFeeInput.value = '';
                }
            });
        });
    </script>
</body>
</html>