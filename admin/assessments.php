<?php
// admin/assessments.php
// Page to create, edit, and delete assessments (formerly quizzes), now with payment options.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$assessments = []; // Array to hold fetched assessments

// Handle form submissions for adding, editing, and deleting assessments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'add_assessment': // Changed from add_quiz
                $title = sanitize_input($_POST['title'] ?? '');
                $description = sanitize_input($_POST['description'] ?? '');
                $max_attempts = sanitize_input($_POST['max_attempts'] ?? 0);
                // Convert empty string duration to NULL for DB
                $duration_minutes = !empty($_POST['duration_minutes']) ? sanitize_input($_POST['duration_minutes']) : NULL;
                $created_by = getUserId(); // Current logged-in admin's ID

                // New fields for payment
                $is_paid = isset($_POST['is_paid']) ? 1 : 0;
                // If not paid, ensure fee is NULL or 0.00
                $assessment_fee = ($is_paid && !empty($_POST['assessment_fee'])) ? sanitize_input($_POST['assessment_fee']) : NULL;
                if ($is_paid && ($assessment_fee === NULL || !is_numeric($assessment_fee) || $assessment_fee < 0)) {
                    $message = display_message("Assessment fee is required and must be a valid positive number if the assessment is paid.", "error");
                    break; // Exit switch case
                }

                if (empty($title)) {
                    $message = display_message("Assessment title is required.", "error");
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, max_attempts, duration_minutes, is_paid, assessment_fee, created_by) VALUES (:title, :description, :max_attempts, :duration_minutes, :is_paid, :assessment_fee, :created_by)");
                        if ($stmt->execute([
                            'title' => $title,
                            'description' => $description,
                            'max_attempts' => $max_attempts,
                            'duration_minutes' => $duration_minutes,
                            'is_paid' => $is_paid,
                            'assessment_fee' => $assessment_fee,
                            'created_by' => $created_by
                        ])) {
                            $message = display_message("Assessment added successfully!", "success");
                        } else {
                            $message = display_message("Failed to add assessment.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Add Assessment Error: " . $e->getMessage());
                        $message = display_message("Database error while adding assessment.", "error");
                    }
                }
                break;

            case 'edit_assessment': // Changed from edit_quiz
                $quiz_id = sanitize_input($_POST['quiz_id'] ?? 0); // Still quiz_id in DB
                $title = sanitize_input($_POST['title'] ?? '');
                $description = sanitize_input($_POST['description'] ?? '');
                $max_attempts = sanitize_input($_POST['max_attempts'] ?? 0);
                // Convert empty string duration to NULL for DB
                $duration_minutes = !empty($_POST['duration_minutes']) ? sanitize_input($_POST['duration_minutes']) : NULL;

                // New fields for payment
                $is_paid = isset($_POST['is_paid']) ? 1 : 0;
                // If not paid, ensure fee is NULL or 0.00
                $assessment_fee = ($is_paid && !empty($_POST['assessment_fee'])) ? sanitize_input($_POST['assessment_fee']) : NULL;
                if ($is_paid && ($assessment_fee === NULL || !is_numeric($assessment_fee) || $assessment_fee < 0)) {
                    $message = display_message("Assessment fee is required and must be a valid positive number if the assessment is paid.", "error");
                    break; // Exit switch case
                }

                if (empty($quiz_id) || empty($title)) {
                    $message = display_message("Assessment ID and title are required to edit.", "error");
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE quizzes SET title = :title, description = :description, max_attempts = :max_attempts, duration_minutes = :duration_minutes, is_paid = :is_paid, assessment_fee = :assessment_fee WHERE quiz_id = :quiz_id");
                        if ($stmt->execute([
                            'title' => $title,
                            'description' => $description,
                            'max_attempts' => $max_attempts,
                            'duration_minutes' => $duration_minutes,
                            'is_paid' => $is_paid,
                            'assessment_fee' => $assessment_fee,
                            'quiz_id' => $quiz_id
                        ])) {
                            $message = display_message("Assessment updated successfully!", "success");
                        } else {
                            $message = display_message("Failed to update assessment.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Edit Assessment Error: " . $e->getMessage());
                        $message = display_message("Database error while updating assessment.", "error");
                    }
                }
                break;

            case 'delete_assessment': // Changed from delete_quiz
                $quiz_id = sanitize_input($_POST['quiz_id'] ?? 0); // Still quiz_id in DB
                if (empty($quiz_id)) {
                    $message = display_message("Assessment ID is required to delete.", "error");
                } else {
                    try {
                        // Deleting an assessment will cascade delete related questions and options due to foreign key constraints
                        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = :quiz_id");
                        if ($stmt->execute(['quiz_id' => $quiz_id])) {
                            $message = display_message("Assessment and all its questions deleted successfully!", "success");
                        } else {
                            $message = display_message("Failed to delete assessment.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Delete Assessment Error: " . $e->getMessage());
                        $message = display_message("Database error while deleting assessment.", "error");
                    }
                }
                break;
        }
    }
}

// Fetch all assessments for display, including new payment fields
try {
    $stmt = $pdo->query("SELECT quiz_id, title, description, max_attempts, duration_minutes, is_paid, assessment_fee, created_at FROM quizzes ORDER BY created_at DESC");
    $assessments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Assessments Error: " . $e->getMessage());
    $message = display_message("Could not fetch assessments. Please try again later.", "error");
}
?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Manage Assessments</h1>

    <?php echo $message; // Display any feedback messages ?>

    <div class="mb-6">
        <button id="toggleAddAssessmentForm"
                class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
            Add New Assessment
        </button>
    </div>

    <div id="addAssessmentForm" class="bg-white p-6 rounded-lg shadow-md mb-8 hidden">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add New Assessment</h2>
        <form action="assessments.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="add_assessment">
            <div>
                <label for="add_title" class="block text-gray-700 text-sm font-bold mb-2">Assessment Title:</label>
                <input type="text" id="add_title" name="title" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="add_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                <textarea id="add_description" name="description" rows="3"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500"></textarea>
            </div>
            <div>
                <label for="add_max_attempts" class="block text-gray-700 text-sm font-bold mb-2">Max Attempts (0 for unlimited):</label>
                <input type="number" id="add_max_attempts" name="max_attempts" value="1" min="0" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="add_duration_minutes" class="block text-gray-700 text-sm font-bold mb-2">Duration (minutes, leave blank for no limit):</label>
                <input type="number" id="add_duration_minutes" name="duration_minutes" min="1"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div class="md:col-span-2">
                <div class="flex items-center mb-2">
                    <input type="checkbox" id="add_is_paid" name="is_paid" value="1"
                           class="form-checkbox h-5 w-5 text-indigo-600">
                    <label for="add_is_paid" class="ml-2 text-gray-700">Requires Payment</label>
                </div>
                <div id="add_assessment_fee_group" class="hidden">
                    <label for="add_assessment_fee" class="block text-gray-700 text-sm font-bold mb-2">Assessment Fee (NGN):</label>
                    <input type="number" id="add_assessment_fee" name="assessment_fee" step="0.01" min="0"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500"
                           placeholder="e.g., 1500.00">
                </div>
            </div>
            <div class="md:col-span-2">
                <button type="submit"
                         class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Create Assessment
                </button>
                <button type="button" onclick="document.getElementById('addAssessmentForm').classList.add('hidden');"
                         class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ml-2">
                    Cancel
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Existing Assessments</h2>
        <?php if (empty($assessments)): ?>
            <p class="text-gray-600">No assessments found.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($assessments as $assessment): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['quiz_id']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['title']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['max_attempts']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['duration_minutes'] ?: 'No Limit'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $assessment['is_paid'] ? 'Yes' : 'No'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $assessment['is_paid'] && $assessment['assessment_fee'] !== null ? '₦' . number_format($assessment['assessment_fee'], 2) : 'N/A'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button onclick="openEditAssessmentModal(<?php echo htmlspecialchars(json_encode($assessment)); ?>)"
                                    class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                            <a href="<?php echo BASE_URL; ?>admin/questions.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                               class="text-blue-600 hover:text-blue-900 mr-3">Manage Questions</a>
                            <form action="assessments.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this assessment and all its questions?');">
                                <input type="hidden" name="action" value="delete_assessment">
                                <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($assessment['quiz_id']); ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div id="editAssessmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Edit Assessment</h2>
        <form action="assessments.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_assessment">
            <input type="hidden" id="edit_quiz_id" name="quiz_id">
            <div>
                <label for="edit_title" class="block text-gray-700 text-sm font-bold mb-2">Assessment Title:</label>
                <input type="text" id="edit_title" name="title" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="edit_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                <textarea id="edit_description" name="description" rows="3"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500"></textarea>
            </div>
            <div>
                <label for="edit_max_attempts" class="block text-gray-700 text-sm font-bold mb-2">Max Attempts (0 for unlimited):</label>
                <input type="number" id="edit_max_attempts" name="max_attempts" min="0" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="edit_duration_minutes" class="block text-gray-700 text-sm font-bold mb-2">Duration (minutes, leave blank for no limit):</label>
                <input type="number" id="edit_duration_minutes" name="duration_minutes" min="1"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <div class="flex items-center mb-2">
                    <input type="checkbox" id="edit_is_paid" name="is_paid" value="1"
                           class="form-checkbox h-5 w-5 text-indigo-600">
                    <label for="edit_is_paid" class="ml-2 text-gray-700">Requires Payment</label>
                </div>
                <div id="edit_assessment_fee_group" class="hidden">
                    <label for="edit_assessment_fee" class="block text-gray-700 text-sm font-bold mb-2">Assessment Fee (NGN):</label>
                    <input type="number" id="edit_assessment_fee" name="assessment_fee" step="0.01" min="0"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500"
                           placeholder="e.g., 1500.00">
                </div>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeEditAssessmentModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Cancel
                </button>
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // JavaScript for Assessment Management Modals
    function openEditAssessmentModal(assessment) { // Changed function name
        document.getElementById('edit_quiz_id').value = assessment.quiz_id;
        document.getElementById('edit_title').value = assessment.title;
        document.getElementById('edit_description').value = assessment.description;
        document.getElementById('edit_max_attempts').value = assessment.max_attempts;
        // Ensure that if duration_minutes is null, it displays as empty string
        document.getElementById('edit_duration_minutes').value = assessment.duration_minutes !== null ? assessment.duration_minutes : '';

        // Populate payment fields for edit modal
        const editIsPaidCheckbox = document.getElementById('edit_is_paid');
        const editAssessmentFeeInput = document.getElementById('edit_assessment_fee');
        const editAssessmentFeeGroup = document.getElementById('edit_assessment_fee_group');

        editIsPaidCheckbox.checked = assessment.is_paid == 1;
        // If assessment_fee is stored as NULL, display as empty string
        editAssessmentFeeInput.value = assessment.assessment_fee !== null ? parseFloat(assessment.assessment_fee).toFixed(2) : '';

        // Trigger visibility for fee input based on is_paid status
        if (assessment.is_paid == 1) {
            editAssessmentFeeGroup.classList.remove('hidden');
            editAssessmentFeeInput.setAttribute('required', 'required'); // Make it required when visible
        } else {
            editAssessmentFeeGroup.classList.add('hidden');
            editAssessmentFeeInput.removeAttribute('required'); // Not required when hidden
        }

        document.getElementById('editAssessmentModal').classList.remove('hidden'); // Changed modal ID
    }

    function closeEditAssessmentModal() { // Changed function name
        document.getElementById('editAssessmentModal').classList.add('hidden'); // Changed modal ID
    }

    // Toggle for Add New Assessment Form
    document.getElementById('toggleAddAssessmentForm').addEventListener('click', function() { // Changed ID
        const addAssessmentForm = document.getElementById('addAssessmentForm'); // Changed ID
        addAssessmentForm.classList.toggle('hidden');
    });

    // --- JavaScript for conditional display of Assessment Fee ---

    // For Add Assessment Form
    document.addEventListener('DOMContentLoaded', function() {
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
                addAssessmentFeeInput.value = ''; // Clear value when hidden
            }
        }

        addIsPaidCheckbox.addEventListener('change', toggleAddAssessmentFeeVisibility);
        // Initial call in case the checkbox is checked by default (though not in this code)
        toggleAddAssessmentFeeVisibility();


        // For Edit Assessment Modal (attach event listener when modal is shown or on page load)
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
                editAssessmentFeeInput.value = ''; // Clear value when hidden
            }
        }

        // Listener for the edit modal's checkbox
        editIsPaidCheckbox.addEventListener('change', toggleEditAssessmentFeeVisibility);

        // We also need to ensure the edit modal's fee field visibility is set correctly
        // when openEditAssessmentModal() is called. This is already handled inside openEditAssessmentModal.
        // The DOMContentLoaded listener ensures the function exists, but the actual toggle
        // happens when `openEditAssessmentModal` is called for the edit button.
    });
</script>

<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>