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
            case 'add_assessment':
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

            case 'edit_assessment':
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

            case 'delete_assessment':
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
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Manage Assessments</h1>
        <button id="toggleAddAssessmentForm"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center space-x-2 transition duration-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>Add New Assessment</span>
        </button>
    </div>

    <?php echo $message; // Display any feedback messages ?>

    <div id="addAssessmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Add New Assessment</h2>
            <form action="assessments.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_assessment">
                <div>
                    <label for="add_title" class="block text-gray-700 text-sm font-bold mb-2">Assessment Title:</label>
                    <input type="text" id="add_title" name="title" required
                           placeholder="Enter assessment title"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500">
                </div>
                <div>
                    <label for="add_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                    <textarea id="add_description" name="description" rows="3"
                              placeholder="Describe the assessment"
                              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500"></textarea>
                </div>
                <div>
                    <label for="add_max_attempts" class="block text-gray-700 text-sm font-bold mb-2">Max Attempts (0 for unlimited):</label>
                    <input type="number" id="add_max_attempts" name="max_attempts" value="1" min="0" required
                           placeholder="e.g., 1"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500">
                </div>
                <div>
                    <label for="add_duration_minutes" class="block text-gray-700 text-sm font-bold mb-2">Duration (minutes, leave blank for no limit):</label>
                    <input type="number" id="add_duration_minutes" name="duration_minutes" min="1"
                           placeholder="e.g., 60"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500">
                </div>
                <div>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" id="add_is_paid" name="is_paid" value="1"
                               class="form-checkbox h-5 w-5 text-indigo-600">
                        <label for="add_is_paid" class="ml-2 text-gray-700">Requires Payment</label>
                    </div>
                    <div id="add_assessment_fee_group" class="hidden">
                        <label for="add_assessment_fee" class="block text-gray-700 text-sm font-bold mb-2">Assessment Fee (NGN):</label>
                        <input type="number" id="add_assessment_fee" name="assessment_fee" step="0.01" min="0"
                               placeholder="e.g., 1500.00"
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500">
                    </div>
                </div>
                <div class="flex justify-between space-x-4 mt-6">
                    <button type="button" onclick="document.getElementById('addAssessmentModal').classList.add('hidden');"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Add Assessment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-semibold text-gray-800">Existing Assessments</h2>
            <div>
                <input type="text" id="assessmentSearch" placeholder="Search by title..."
                       class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500 w-64">
            </div>
        </div>
        <?php if (empty($assessments)): ?>
            <p class="text-gray-600">No assessments found.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Attempts</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Duration</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Fee</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="assessmentTableBody">
                    <?php foreach ($assessments as $assessment): ?>
                    <tr class="assessment-row">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['title']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['max_attempts']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['duration_minutes'] ?: 'No Limit'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $assessment['is_paid'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo $assessment['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $assessment['is_paid'] && $assessment['assessment_fee'] !== null ? '₦' . number_format($assessment['assessment_fee'], 2) : 'N/A'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex space-x-3">
                            <button onclick="openEditAssessmentModal(<?php echo htmlspecialchars(json_encode($assessment)); ?>)"
                                    class="text-indigo-600 hover:text-indigo-900 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                                Edit
                            </button>
                            <a href="<?php echo BASE_URL; ?>admin/questions.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                               class="text-blue-600 hover:text-blue-900 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                                </svg>
                                Manage
                            </a>
                            <form action="assessments.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this assessment and all its questions?');">
                                <input type="hidden" name="action" value="delete_assessment">
                                <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($assessment['quiz_id']); ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Delete
                                </button>
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
                       placeholder="Enter assessment title"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500">
            </div>
            <div>
                <label for="edit_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                <textarea id="edit_description" name="description" rows="3"
                          placeholder="Describe the assessment"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500"></textarea>
            </div>
            <div>
                <label for="edit_max_attempts" class="block text-gray-700 text-sm font-bold mb-2">Max Attempts (0 for unlimited):</label>
                <input type="number" id="edit_max_attempts" name="max_attempts" min="0" required
                       placeholder="e.g., 1"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500">
            </div>
            <div>
                <label for="edit_duration_minutes" class="block text-gray-700 text-sm font-bold mb-2">Duration (minutes, leave blank for no limit):</label>
                <input type="number" id="edit_duration_minutes" name="duration_minutes" min="1"
                       placeholder="e.g., 60"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500">
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
                           placeholder="e.g., 1500.00"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-indigo-500">
                </div>
            </div>
            <div class="flex justify-between space-x-4 mt-6">
                <button type="button" onclick="closeEditAssessmentModal()"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    Cancel
                </button>
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                    Update
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // JavaScript for Assessment Management Modals
    function openEditAssessmentModal(assessment) {
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
            editAssessmentFeeInput.setAttribute('required', 'required');
        } else {
            editAssessmentFeeGroup.classList.add('hidden');
            editAssessmentFeeInput.removeAttribute('required');
        }

        document.getElementById('editAssessmentModal').classList.remove('hidden');
    }

    function closeEditAssessmentModal() {
        document.getElementById('editAssessmentModal').classList.add('hidden');
    }

    // Toggle for Add New Assessment Modal
    document.getElementById('toggleAddAssessmentForm').addEventListener('click', function() {
        document.getElementById('addAssessmentModal').classList.remove('hidden');
    });

    // JavaScript for conditional display of Assessment Fee
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
                addAssessmentFeeInput.value = '';
            }
        }

        addIsPaidCheckbox.addEventListener('change', toggleAddAssessmentFeeVisibility);
        toggleAddAssessmentFeeVisibility();

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

        // Search functionality
        const searchInput = document.getElementById('assessmentSearch');
        const tableBody = document.getElementById('assessmentTableBody');
        const rows = tableBody.getElementsByClassName('assessment-row');

        searchInput.addEventListener('input', function() {
            const searchTerm = searchInput.value.toLowerCase();
            Array.from(rows).forEach(row => {
                const title = row.cells[0].textContent.toLowerCase();
                row.style.display = title.includes(searchTerm) ? '' : 'none';
            });
        });
    });
</script>

<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>