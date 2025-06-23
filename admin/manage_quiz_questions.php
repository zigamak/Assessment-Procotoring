<?php
// admin/manage_quiz_questions.php
// Page to manage questions (add, edit, delete, import from CSV) for a specific quiz.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$quiz_id = sanitize_input($_GET['quiz_id'] ?? null);
$quiz_title = '';
$questions = []; // Array to hold fetched questions

// Redirect if no quiz ID is provided
if (!$quiz_id) {
    redirect(BASE_URL . 'admin/manage_quizzes.php?message=no_quiz_selected');
}

// Fetch quiz title for display
try {
    $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE quiz_id = :quiz_id");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $quiz_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($quiz_data) {
        $quiz_title = htmlspecialchars($quiz_data['title']);
    } else {
        redirect(BASE_URL . 'admin/manage_quizzes.php?message=quiz_not_found');
    }
} catch (PDOException $e) {
    error_log("Fetch Quiz Title Error: " . $e->getMessage());
    $message = display_message("Could not fetch quiz details. Please try again later.", "error");
}


// Handle form submissions for adding, editing, deleting questions, or CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'add_question':
                $question_text = sanitize_input($_POST['question_text'] ?? '');
                $question_type = sanitize_input($_POST['question_type'] ?? '');
                $score = sanitize_input($_POST['score'] ?? 1);
                $image_url = !empty($_POST['image_url']) ? sanitize_input($_POST['image_url']) : NULL; // Allow NULL if empty
                $options_texts = $_POST['option_text'] ?? [];
                $correct_options_indices = $_POST['is_correct'] ?? []; // Array of indices for correct options

                if (empty($question_text) || empty($question_type)) {
                    $message = display_message("Question text and type are required.", "error");
                } else {
                    try {
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, question_type, score, image_url) VALUES (:quiz_id, :question_text, :question_type, :score, :image_url)");
                        $stmt->execute([
                            'quiz_id' => $quiz_id,
                            'question_text' => $question_text,
                            'question_type' => $question_type,
                            'score' => $score,
                            'image_url' => $image_url
                        ]);
                        $question_id = $pdo->lastInsertId();

                        if ($question_type === 'multiple_choice') {
                            if (empty($options_texts)) {
                                throw new Exception("Multiple choice questions require at least one option.");
                            }
                            foreach ($options_texts as $index => $option_text) {
                                if (!empty(trim($option_text))) { // Ensure option text is not just whitespace
                                    // Check if the current option's index is in the array of correct options
                                    $is_correct = in_array(strval($index), $correct_options_indices) ? 1 : 0;
                                    $stmt = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (:question_id, :option_text, :is_correct)");
                                    $stmt->execute(['question_id' => $question_id, 'option_text' => sanitize_input($option_text), 'is_correct' => $is_correct]);
                                }
                            }
                        }

                        $pdo->commit();
                        $message = display_message("Question added successfully!", "success");
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        error_log("Add Question Error: " . $e->getMessage());
                        $message = display_message("Failed to add question: " . $e->getMessage(), "error");
                    }
                }
                break;

            case 'edit_question':
                $question_id_to_edit = sanitize_input($_POST['question_id'] ?? 0);
                $question_text = sanitize_input($_POST['question_text'] ?? '');
                $question_type = sanitize_input($_POST['question_type'] ?? '');
                $score = sanitize_input($_POST['score'] ?? 1);
                $image_url = !empty($_POST['image_url']) ? sanitize_input($_POST['image_url']) : NULL;
                $options_texts = $_POST['option_text'] ?? [];
                $correct_options_indices = $_POST['is_correct'] ?? [];
                $option_ids = $_POST['option_id'] ?? [];


                if (empty($question_id_to_edit) || empty($question_text) || empty($question_type)) {
                    $message = display_message("Question ID, text, and type are required to edit.", "error");
                } else {
                    try {
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare("UPDATE questions SET question_text = :question_text, question_type = :question_type, score = :score, image_url = :image_url WHERE question_id = :question_id AND quiz_id = :quiz_id");
                        $stmt->execute([
                            'question_text' => $question_text,
                            'question_type' => $question_type,
                            'score' => $score,
                            'image_url' => $image_url,
                            'question_id' => $question_id_to_edit,
                            'quiz_id' => $quiz_id
                        ]);

                        // Handle options for multiple choice questions
                        if ($question_type === 'multiple_choice') {
                            // Delete existing options first
                            $stmt = $pdo->prepare("DELETE FROM options WHERE question_id = :question_id");
                            $stmt->execute(['question_id' => $question_id_to_edit]);

                            if (empty($options_texts)) {
                                throw new Exception("Multiple choice questions require at least one option.");
                            }

                            foreach ($options_texts as $index => $option_text) {
                                if (!empty(trim($option_text))) {
                                    $is_correct = in_array(strval($index), $correct_options_indices) ? 1 : 0;
                                    $stmt = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (:question_id, :option_text, :is_correct)");
                                    $stmt->execute(['question_id' => $question_id_to_edit, 'option_text' => sanitize_input($option_text), 'is_correct' => $is_correct]);
                                }
                            }
                        } else {
                            // If question type changes from multiple_choice, delete its options
                            $stmt = $pdo->prepare("DELETE FROM options WHERE question_id = :question_id");
                            $stmt->execute(['question_id' => $question_id_to_edit]);
                        }

                        $pdo->commit();
                        $message = display_message("Question updated successfully!", "success");
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        error_log("Edit Question Error: " . $e->getMessage());
                        $message = display_message("Failed to update question: " . $e->getMessage(), "error");
                    }
                }
                break;


            case 'delete_question':
                $question_id_to_delete = sanitize_input($_POST['question_id'] ?? 0);
                if (empty($question_id_to_delete)) {
                    $message = display_message("Question ID is required to delete.", "error");
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM questions WHERE question_id = :question_id AND quiz_id = :quiz_id");
                        if ($stmt->execute(['question_id' => $question_id_to_delete, 'quiz_id' => $quiz_id])) {
                            $message = display_message("Question deleted successfully!", "success");
                        } else {
                            $message = display_message("Failed to delete question.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Delete Question Error: " . $e->getMessage());
                        $message = display_message("Database error while deleting question.", "error");
                    }
                }
                break;

            case 'import_csv':
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
                    $file_tmp_path = $_FILES['csv_file']['tmp_name'];
                    $file_extension = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);

                    if (strtolower($file_extension) !== 'csv') {
                        $message = display_message("Invalid file type. Please upload a CSV file.", "error");
                    } else {
                        $row = 0;
                        $imported_count = 0;
                        $failed_count = 0;
                        $error_details = [];

                        if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
                            fgetcsv($handle); // Skip header row

                            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                                $row++;
                                // Expected CSV columns:
                                // 0: QuestionText, 1: QuestionType, 2: Score, 3: ImageURL (optional)
                                // For Multiple Choice: 4: CorrectOptionIndex (0-based), 5: Option1, 6: Option2, ...

                                $question_text = sanitize_input($data[0] ?? '');
                                $question_type = sanitize_input(strtolower($data[1] ?? ''));
                                $score = sanitize_input(intval($data[2] ?? 1));
                                $image_url = !empty($data[3]) ? sanitize_input($data[3]) : NULL;

                                if (empty($question_text) || empty($question_type)) {
                                    $failed_count++;
                                    $error_details[] = "Row {$row}: Missing QuestionText or QuestionType.";
                                    continue;
                                }

                                try {
                                    $pdo->beginTransaction();

                                    $stmt_q = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, question_type, score, image_url) VALUES (:quiz_id, :question_text, :question_type, :score, :image_url)");
                                    $stmt_q->execute([
                                        'quiz_id' => $quiz_id,
                                        'question_text' => $question_text,
                                        'question_type' => $question_type,
                                        'score' => $score,
                                        'image_url' => $image_url
                                    ]);
                                    $question_id = $pdo->lastInsertId();

                                    if ($question_type === 'multiple_choice') {
                                        $correct_option_index = intval($data[4] ?? -1); // 0-based index of correct option
                                        $options_start_index = 5; // Options start from column 5

                                        if ($correct_option_index < 0) {
                                            throw new Exception("Missing or invalid correct option index for multiple choice question.");
                                        }

                                        $option_count = 0;
                                        for ($i = $options_start_index; $i < count($data); $i++) {
                                            $option_text = sanitize_input($data[$i] ?? '');
                                            if (!empty(trim($option_text))) {
                                                $is_correct = ($option_count === $correct_option_index) ? 1 : 0;
                                                $stmt_o = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (:question_id, :option_text, :is_correct)");
                                                $stmt_o->execute(['question_id' => $question_id, 'option_text' => $option_text, 'is_correct' => $is_correct]);
                                                $option_count++;
                                            }
                                        }
                                        if ($option_count === 0) {
                                            throw new Exception("No options provided for multiple choice question.");
                                        }
                                    }

                                    $pdo->commit();
                                    $imported_count++;

                                } catch (Exception $e) {
                                    $pdo->rollBack();
                                    $failed_count++;
                                    $error_details[] = "Row {$row}: " . $e->getMessage();
                                }
                            }
                            fclose($handle);

                            if ($imported_count > 0) {
                                $message .= display_message("Successfully imported {$imported_count} questions.", "success");
                            }
                            if ($failed_count > 0) {
                                $message .= display_message("Failed to import {$failed_count} questions. Details: <pre>" . htmlspecialchars(implode("\n", $error_details)) . "</pre>", "error");
                            }
                        } else {
                            $message = display_message("Failed to open uploaded CSV file.", "error");
                        }
                    }
                } else {
                    $message = display_message("Error uploading file: " . $_FILES['csv_file']['error'], "error");
                }
                break;
        }
    }
}

// Fetch all questions for the current quiz for display
try {
    $stmt = $pdo->prepare("
        SELECT q.question_id, q.question_text, q.question_type, q.score, q.image_url,
                GROUP_CONCAT(CONCAT(o.option_id, '||', o.option_text, '||', o.is_correct) SEPARATOR ';;') as options_data
        FROM questions q
        LEFT JOIN options o ON q.question_id = o.question_id
        WHERE q.quiz_id = :quiz_id
        GROUP BY q.question_id
        ORDER BY q.question_id ASC
    ");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $raw_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format questions with their options
    foreach ($raw_questions as $raw_q) {
        $question = [
            'question_id' => $raw_q['question_id'],
            'question_text' => $raw_q['question_text'],
            'question_type' => $raw_q['question_type'],
            'score' => $raw_q['score'],
            'image_url' => $raw_q['image_url'],
            'options' => []
        ];
        if ($raw_q['question_type'] === 'multiple_choice' && $raw_q['options_data']) {
            $options_raw = explode(';;', $raw_q['options_data']);
            foreach ($options_raw as $opt_str) {
                // Ensure there are enough parts before list() to prevent errors on malformed data
                $parts = explode('||', $opt_str);
                if (count($parts) >= 3) {
                    list($opt_id, $opt_text, $is_correct) = $parts;
                    $question['options'][] = [
                        'option_id' => $opt_id,
                        'option_text' => $opt_text,
                        'is_correct' => (bool)$is_correct
                    ];
                }
            }
        }
        $questions[] = $question;
    }
} catch (PDOException $e) {
    error_log("Fetch Questions for Quiz Error: " . $e->getMessage());
    $message = display_message("Could not fetch questions for this quiz. Please try again later.", "error");
}
?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Manage Questions for "<?php echo $quiz_title; ?>"</h1>

    <?php echo $message; // Display any feedback messages ?>

    <div class="mb-8">
        <a href="<?php echo BASE_URL; ?>admin/manage_quizzes.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
            &larr; Back to Manage Quizzes
        </a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8 overflow-x-auto">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Existing Questions</h2>
        <?php if (empty($questions)): ?>
            <p class="text-gray-600">No questions found for this quiz.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question Text</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($questions as $question): ?>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 max-w-xs overflow-hidden text-ellipsis"><?php echo htmlspecialchars($question['question_text']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $question['question_type']))); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($question['score']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-left text-sm font-medium">
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($question)); ?>)" class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</button>
                            <form action="manage_quiz_questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this question?');">
                                <input type="hidden" name="action" value="delete_question">
                                <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($question['question_id']); ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="md:col-span-2 bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add New Question</h2>
            <form id="addQuestionForm" action="manage_quiz_questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" class="space-y-3">
                <input type="hidden" name="action" value="add_question">
                <div>
                    <label for="add_question_text" class="block text-gray-700 text-sm font-bold mb-1">Question Text:</label>
                    <textarea id="add_question_text" name="question_text" rows="2" required
                              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="add_question_type" class="block text-gray-700 text-sm font-bold mb-1">Question Type:</label>
                        <select id="add_question_type" name="question_type" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500"
                                onchange="toggleQuestionOptions('add')">
                            <option value="">Select Type</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>
                    <div>
                        <label for="add_score" class="block text-gray-700 text-sm font-bold mb-1">Score:</label>
                        <input type="number" id="add_score" name="score" value="1" min="1" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                    </div>
                </div>
                <div>
                    <label for="add_image_url" class="block text-gray-700 text-sm font-bold mb-1">Image URL (Optional):</label>
                    <input type="text" id="add_image_url" name="image_url"
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                </div>

                <div id="addMultipleChoiceOptions" class="hidden border p-3 rounded-md bg-gray-50">
                    <h4 class="font-semibold mb-2">Options (for Multiple Choice):</h4>
                    <div id="addOptionsContainer">
                        <div class="flex items-center space-x-2 mb-2">
                            <input type="checkbox" name="is_correct[]" value="0" class="h-4 w-4 text-green-600">
                            <input type="text" name="option_text[]" placeholder="Option 1" required
                                   class="shadow appearance-none border rounded w-full py-1 px-2 text-gray-700">
                        </div>
                        <div class="flex items-center space-x-2 mb-2">
                            <input type="checkbox" name="is_correct[]" value="1" class="h-4 w-4 text-green-600">
                            <input type="text" name="option_text[]" placeholder="Option 2" required
                                   class="shadow appearance-none border rounded w-full py-1 px-2 text-gray-700">
                        </div>
                    </div>
                    <button type="button" onclick="addOptionField('add')"
                            class="mt-2 bg-blue-500 hover:bg-blue-600 text-white text-sm py-1 px-3 rounded">
                        Add Option
                    </button>
                </div>

                <div class="flex justify-end mt-4">
                    <button type="submit"
                            class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        Add Question
                    </button>
                </div>
            </form>
        </div>

        <div class="md:col-span-1 bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Import Questions from CSV</h2>
            <form action="manage_quiz_questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="import_csv">
                <div>
                    <label for="csv_file" class="block text-gray-700 text-sm font-bold mb-2">Upload CSV File:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required
                           class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none">
                    <p class="mt-1 text-sm text-gray-500">Only CSV files are allowed.</p>
                </div>
                <button type="submit"
                        class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Import CSV
                </button>
            </form>

            <div class="mt-6 p-4 bg-gray-100 rounded-md border border-gray-200 text-sm">
                <h3 class="font-semibold text-gray-800 mb-2">CSV Format Information:</h3>
                <p class="text-gray-700">
                    To ensure successful import, please use the correct CSV format.
                </p>
                <a href="<?php echo BASE_URL; ?>admin/sample_questions.csv" download class="inline-block mt-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                    Download Sample CSV
                </a>
                <p class="mt-3 text-red-600 text-sm">
                    *Note: For 'true_false', 'short_answer', and 'essay' question types, any data after the 'ImageURL' column in the CSV will be ignored by the importer for simplicity. These question types will require manual grading.*
                </p>
            </div>
        </div>
    </div>
</div>

<div id="editQuestionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-semibold text-gray-800">Edit Question</h3>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700 text-2xl font-bold">&times;</button>
        </div>
        <form id="editQuestionForm" action="manage_quiz_questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" class="space-y-3">
            <input type="hidden" name="action" value="edit_question">
            <input type="hidden" name="question_id" id="edit_question_id">
            <div>
                <label for="edit_question_text" class="block text-gray-700 text-sm font-bold mb-1">Question Text:</label>
                <textarea id="edit_question_text" name="question_text" rows="2" required
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500"></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="edit_question_type" class="block text-gray-700 text-sm font-bold mb-1">Question Type:</label>
                    <select id="edit_question_type" name="question_type" required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500"
                            onchange="toggleQuestionOptions('edit')">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="short_answer">Short Answer</option>
                        <option value="essay">Essay</option>
                    </select>
                </div>
                <div>
                    <label for="edit_score" class="block text-gray-700 text-sm font-bold mb-1">Score:</label>
                    <input type="number" id="edit_score" name="score" value="1" min="1" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                </div>
            </div>
            <div>
                <label for="edit_image_url" class="block text-gray-700 text-sm font-bold mb-1">Image URL (Optional):</label>
                <input type="text" id="edit_image_url" name="image_url"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>

            <div id="editMultipleChoiceOptions" class="hidden border p-3 rounded-md bg-gray-50">
                <h4 class="font-semibold mb-2">Options (for Multiple Choice):</h4>
                <div id="editOptionsContainer">
                    </div>
                <button type="button" onclick="addOptionField('edit')"
                        class="mt-2 bg-blue-500 hover:bg-blue-600 text-white text-sm py-1 px-3 rounded">
                    Add Option
                </button>
            </div>

            <div class="flex justify-end mt-4">
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Update Question
                </button>
            </div>
        </form>
    </div>
</div>


<script>
    // JavaScript for adding/removing options for Multiple Choice questions
    function toggleQuestionOptions(mode) {
        const questionType = document.getElementById(`${mode}_question_type`).value;
        const mcOptions = document.getElementById(`${mode}MultipleChoiceOptions`);
        if (questionType === 'multiple_choice') {
            mcOptions.classList.remove('hidden');
        } else {
            mcOptions.classList.add('hidden');
        }
    }

    function addOptionField(mode, optionText = '', isCorrect = false, optionId = null) {
        const optionsContainer = document.getElementById(`${mode}OptionsContainer`);
        const newOptionIndex = optionsContainer.children.length; // Use current number of children as new index
        const checked = isCorrect ? 'checked' : '';

        const newOptionHtml = `
            <div class="flex items-center space-x-2 mb-2">
                <input type="checkbox" name="is_correct[]" value="${newOptionIndex}" class="h-4 w-4 text-green-600" ${checked}>
                <input type="text" name="option_text[]" placeholder="Option ${newOptionIndex + 1}"
                       class="shadow appearance-none border rounded w-full py-1 px-2 text-gray-700" value="${optionText}" required>
                ${optionId ? `<input type="hidden" name="option_id[]" value="${optionId}">` : ''}
                <button type="button" onclick="removeOptionField(this, '${mode}')" class="text-red-500 hover:text-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1zm1 3a1 1 0 100 2h4a1 1 0 100-2H8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        `;
        optionsContainer.insertAdjacentHTML('beforeend', newOptionHtml);
    }

    function removeOptionField(button, mode) {
        button.closest('.flex').remove();
        // Re-index correct_options values after removal to maintain sequential indices
        const checkboxes = document.querySelectorAll(`#${mode}OptionsContainer input[type="checkbox"]`);
        checkboxes.forEach((checkbox, index) => {
            checkbox.value = index;
        });
    }

    function openEditModal(question) {
        const modal = document.getElementById('editQuestionModal');
        document.getElementById('edit_question_id').value = question.question_id;
        document.getElementById('edit_question_text').value = question.question_text;
        document.getElementById('edit_question_type').value = question.question_type;
        document.getElementById('edit_score').value = question.score;
        document.getElementById('edit_image_url').value = question.image_url || '';

        // Clear existing options
        const editOptionsContainer = document.getElementById('editOptionsContainer');
        editOptionsContainer.innerHTML = '';

        if (question.question_type === 'multiple_choice') {
            question.options.forEach((option, index) => {
                addOptionField('edit', option.option_text, option.is_correct, option.option_id);
            });
            toggleQuestionOptions('edit');
        } else {
            toggleQuestionOptions('edit'); // Hide options if not multiple choice
        }

        modal.classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editQuestionModal').classList.add('hidden');
    }

    // Initialize option toggling on page load
    window.onload = () => {
        toggleQuestionOptions('add');
        toggleQuestionOptions('edit'); // Ensure edit modal options are hidden on initial load
    };

</script>

<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>