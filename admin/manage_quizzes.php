<?php
// admin/manage_quizzes.php
// Page to create, edit, and delete quizzes.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$quizzes = []; // Array to hold fetched quizzes

// Handle form submissions for adding, editing, and deleting quizzes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'add_quiz':
                $title = sanitize_input($_POST['title'] ?? '');
                $description = sanitize_input($_POST['description'] ?? '');
                $is_public = isset($_POST['is_public']) ? 1 : 0;
                $max_attempts = sanitize_input($_POST['max_attempts'] ?? 0);
                // Convert empty string duration to NULL for DB
                $duration_minutes = !empty($_POST['duration_minutes']) ? sanitize_input($_POST['duration_minutes']) : NULL;
                $created_by = getUserId(); // Current logged-in admin's ID

                if (empty($title)) {
                    $message = display_message("Quiz title is required.", "error");
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, is_public, max_attempts, duration_minutes, created_by) VALUES (:title, :description, :is_public, :max_attempts, :duration_minutes, :created_by)");
                        if ($stmt->execute([
                            'title' => $title,
                            'description' => $description,
                            'is_public' => $is_public,
                            'max_attempts' => $max_attempts,
                            'duration_minutes' => $duration_minutes,
                            'created_by' => $created_by
                        ])) {
                            $message = display_message("Quiz added successfully!", "success");
                        } else {
                            $message = display_message("Failed to add quiz.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Add Quiz Error: " . $e->getMessage());
                        $message = display_message("Database error while adding quiz.", "error");
                    }
                }
                break;

            case 'edit_quiz':
                $quiz_id = sanitize_input($_POST['quiz_id'] ?? 0);
                $title = sanitize_input($_POST['title'] ?? '');
                $description = sanitize_input($_POST['description'] ?? '');
                $is_public = isset($_POST['is_public']) ? 1 : 0;
                $max_attempts = sanitize_input($_POST['max_attempts'] ?? 0);
                // Convert empty string duration to NULL for DB
                $duration_minutes = !empty($_POST['duration_minutes']) ? sanitize_input($_POST['duration_minutes']) : NULL;

                if (empty($quiz_id) || empty($title)) {
                    $message = display_message("Quiz ID and title are required to edit.", "error");
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE quizzes SET title = :title, description = :description, is_public = :is_public, max_attempts = :max_attempts, duration_minutes = :duration_minutes WHERE quiz_id = :quiz_id");
                        if ($stmt->execute([
                            'title' => $title,
                            'description' => $description,
                            'is_public' => $is_public,
                            'max_attempts' => $max_attempts,
                            'duration_minutes' => $duration_minutes,
                            'quiz_id' => $quiz_id
                        ])) {
                            $message = display_message("Quiz updated successfully!", "success");
                        } else {
                            $message = display_message("Failed to update quiz.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Edit Quiz Error: " . $e->getMessage());
                        $message = display_message("Database error while updating quiz.", "error");
                    }
                }
                break;

            case 'delete_quiz':
                $quiz_id = sanitize_input($_POST['quiz_id'] ?? 0);
                if (empty($quiz_id)) {
                    $message = display_message("Quiz ID is required to delete.", "error");
                } else {
                    try {
                        // Deleting a quiz will cascade delete related questions and options due to foreign key constraints
                        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = :quiz_id");
                        if ($stmt->execute(['quiz_id' => $quiz_id])) {
                            $message = display_message("Quiz and all its questions deleted successfully!", "success");
                        } else {
                            $message = display_message("Failed to delete quiz.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Delete Quiz Error: " . $e->getMessage());
                        $message = display_message("Database error while deleting quiz.", "error");
                    }
                }
                break;
        }
    }
}

// Fetch all quizzes for display
try {
    $stmt = $pdo->query("SELECT quiz_id, title, description, is_public, max_attempts, duration_minutes, created_at FROM quizzes ORDER BY created_at DESC");
    $quizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Quizzes Error: " . $e->getMessage());
    $message = display_message("Could not fetch quizzes. Please try again later.", "error");
}
?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Manage Quizzes</h1>

    <?php echo $message; // Display any feedback messages ?>

    <div class="mb-6">
        <button id="toggleAddQuizForm"
                class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
            Add New Quiz
        </button>
    </div>

    <div id="addQuizForm" class="bg-white p-6 rounded-lg shadow-md mb-8 hidden">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add New Quiz</h2>
        <form action="manage_quizzes.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="add_quiz">
            <div>
                <label for="add_title" class="block text-gray-700 text-sm font-bold mb-2">Quiz Title:</label>
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
            <div class="md:col-span-2 flex items-center">
                <input type="checkbox" id="add_is_public" name="is_public" value="1"
                       class="form-checkbox h-5 w-5 text-green-600">
                <label for="add_is_public" class="ml-2 text-gray-700">Public Quiz (No Login Required)</label>
            </div>
            <div class="md:col-span-2">
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Create Quiz
                </button>
                <button type="button" onclick="document.getElementById('addQuizForm').classList.add('hidden');"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 ml-2">
                    Cancel
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Existing Quizzes</h2>
        <?php if (empty($quizzes)): ?>
            <p class="text-gray-600">No quizzes found.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Public</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($quizzes as $quiz): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($quiz['quiz_id']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($quiz['title']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $quiz['is_public'] ? 'Yes' : 'No'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($quiz['max_attempts']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($quiz['duration_minutes'] ?: 'No Limit'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button onclick="openEditQuizModal(<?php echo htmlspecialchars(json_encode($quiz)); ?>)"
                                    class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                            <a href="<?php echo BASE_URL; ?>admin/manage_quiz_questions.php?quiz_id=<?php echo htmlspecialchars($quiz['quiz_id']); ?>"
                               class="text-blue-600 hover:text-blue-900 mr-3">Manage Questions</a>
                            <form action="manage_quizzes.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this quiz and all its questions?');">
                                <input type="hidden" name="action" value="delete_quiz">
                                <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($quiz['quiz_id']); ?>">
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

<div id="editQuizModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Edit Quiz</h2>
        <form action="manage_quizzes.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_quiz">
            <input type="hidden" id="edit_quiz_id" name="quiz_id">
            <div>
                <label for="edit_title" class="block text-gray-700 text-sm font-bold mb-2">Quiz Title:</label>
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
            <div class="flex items-center">
                <input type="checkbox" id="edit_is_public" name="is_public" value="1"
                       class="form-checkbox h-5 w-5 text-green-600">
                <label for="edit_is_public" class="ml-2 text-gray-700">Public Quiz (No Login Required)</label>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeEditQuizModal()"
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
    // JavaScript for Quiz Management Modals
    function openEditQuizModal(quiz) {
        document.getElementById('edit_quiz_id').value = quiz.quiz_id;
        document.getElementById('edit_title').value = quiz.title;
        document.getElementById('edit_description').value = quiz.description;
        document.getElementById('edit_max_attempts').value = quiz.max_attempts;
        // Ensure that if duration_minutes is null, it displays as empty string
        document.getElementById('edit_duration_minutes').value = quiz.duration_minutes !== null ? quiz.duration_minutes : '';
        document.getElementById('edit_is_public').checked = quiz.is_public == 1;
        document.getElementById('editQuizModal').classList.remove('hidden');
    }

    function closeEditQuizModal() {
        document.getElementById('editQuizModal').classList.add('hidden');
    }

    // Toggle for Add New Quiz Form
    document.getElementById('toggleAddQuizForm').addEventListener('click', function() {
        const addQuizForm = document.getElementById('addQuizForm');
        addQuizForm.classList.toggle('hidden');
    });
</script>

<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>