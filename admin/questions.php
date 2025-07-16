<?php
// admin/questions.php
// Page to manage questions for a specific assessment, including CSV upload.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$questions = []; // Array to hold fetched questions
$quiz_id = null;
$quiz_title = 'N/A';
$question_types = ['multiple_choice', 'true_false']; // Define allowed question types

// Get the quiz_id from the URL
if (isset($_GET['quiz_id'])) {
    $quiz_id = sanitize_input($_GET['quiz_id']);
    // Fetch quiz details to display its title
    try {
        $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE quiz_id = :quiz_id");
        $stmt->execute(['quiz_id' => $quiz_id]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($quiz) {
            $quiz_title = htmlspecialchars($quiz['title']);
        } else {
            $message = display_message("Assessment not found.", "error");
            $quiz_id = null; // Invalidate quiz_id if not found
        }
    } catch (PDOException $e) {
        error_log("Fetch Quiz Title Error: " . $e->getMessage());
        $message = display_message("Database error while fetching assessment details.", "error");
    }
} else {
    $message = display_message("No assessment ID provided. Please select an assessment to manage its questions.", "info");
}

// Handle form submissions for adding, editing, deleting questions, and CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz_id !== null) {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'add_question':
                $question_text = sanitize_input($_POST['question_text'] ?? '');
                $question_type = sanitize_input($_POST['question_type'] ?? '');
                $score = sanitize_input($_POST['score'] ?? 0);
                $correct_option_index = sanitize_input($_POST['correct_option_index'] ?? -1);
                $options_text = $_POST['options'] ?? []; // Array of option texts

                // Basic validation
                if (empty($question_text) || empty($question_type) || !in_array($question_type, $question_types)) {
                    $message = display_message("Question text and a valid type are required.", "error");
                    break;
                }
                if ($score <= 0) {
                    $message = display_message("Score must be a positive number.", "error");
                    break;
                }
                if (($question_type === 'multiple_choice' || $question_type === 'true_false') && (empty($options_text) || $correct_option_index === -1)) {
                     $message = display_message("Options and a correct option must be provided for multiple choice/true false questions.", "error");
                     break;
                }

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, question_type, score) VALUES (:quiz_id, :question_text, :question_type, :score)");
                    $stmt->execute([
                        'quiz_id' => $quiz_id,
                        'question_text' => $question_text,
                        'question_type' => $question_type,
                        'score' => $score
                    ]);
                    $question_id = $pdo->lastInsertId();

                    if ($question_id) {
                        // Insert options
                        foreach ($options_text as $index => $option_content) {
                            $is_correct = ($index == $correct_option_index) ? 1 : 0;
                            $stmt_option = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (:question_id, :option_text, :is_correct)");
                            $stmt_option->execute([
                                'question_id' => $question_id,
                                'option_text' => sanitize_input($option_content),
                                'is_correct' => $is_correct
                            ]);
                        }
                        $pdo->commit();
                        $message = display_message("Question added successfully!", "success");
                    } else {
                        $pdo->rollBack();
                        $message = display_message("Failed to add question.", "error");
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Add Question Error: " . $e->getMessage());
                    $message = display_message("Database error while adding question.", "error");
                }
                break;

            case 'edit_question':
                $question_id = sanitize_input($_POST['question_id'] ?? 0);
                $question_text = sanitize_input($_POST['question_text'] ?? '');
                $question_type = sanitize_input($_POST['question_type'] ?? '');
                $score = sanitize_input($_POST['score'] ?? 0);
                $correct_option_index = sanitize_input($_POST['correct_option_index'] ?? -1);
                $options_data = $_POST['options'] ?? []; // Array of {id, text} for options

                // Basic validation
                if (empty($question_id) || empty($question_text) || empty($question_type) || !in_array($question_type, $question_types)) {
                    $message = display_message("Question ID, text, and valid type are required to edit.", "error");
                    break;
                }
                if ($score <= 0) {
                    $message = display_message("Score must be a positive number.", "error");
                    break;
                }
                if (($question_type === 'multiple_choice' || $question_type === 'true_false') && (empty($options_data) || $correct_option_index === -1)) {
                    $message = display_message("Options and a correct option must be provided for multiple choice/true false questions.", "error");
                    break;
                }

                try {
                    $pdo->beginTransaction();

                    // Update question
                    $stmt = $pdo->prepare("UPDATE questions SET question_text = :question_text, question_type = :question_type, score = :score WHERE question_id = :question_id AND quiz_id = :quiz_id");
                    $stmt->execute([
                        'question_text' => $question_text,
                        'question_type' => $question_type,
                        'score' => $score,
                        'question_id' => $question_id,
                        'quiz_id' => $quiz_id
                    ]);

                    // Update/Add/Delete options
                    $existing_option_ids = [];
                    foreach ($options_data as $index => $option_item) {
                        $option_id = $option_item['id'] ?? null;
                        $option_text = sanitize_input($option_item['text'] ?? '');
                        $is_correct = ($index == $correct_option_index) ? 1 : 0;

                        if (!empty($option_id)) {
                            // Update existing option
                            $stmt_update_option = $pdo->prepare("UPDATE options SET option_text = :option_text, is_correct = :is_correct WHERE option_id = :option_id AND question_id = :question_id");
                            $stmt_update_option->execute([
                                'option_text' => $option_text,
                                'is_correct' => $is_correct,
                                'option_id' => $option_id,
                                'question_id' => $question_id
                            ]);
                            $existing_option_ids[] = $option_id;
                        } else {
                            // Add new option
                            $stmt_add_option = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (:question_id, :option_text, :is_correct)");
                            $stmt_add_option->execute([
                                'question_id' => $question_id,
                                'option_text' => $option_text,
                                'is_correct' => $is_correct
                            ]);
                            $existing_option_ids[] = $pdo->lastInsertId();
                        }
                    }

                    // Delete options that were removed from the form
                    if (!empty($existing_option_ids)) {
                        $placeholders = implode(',', array_fill(0, count($existing_option_ids), '?'));
                        $stmt_delete_old_options = $pdo->prepare("DELETE FROM options WHERE question_id = ? AND option_id NOT IN ($placeholders)");
                        $params = array_merge([$question_id], $existing_option_ids);
                        $stmt_delete_old_options->execute($params);
                    } else if (isset($_POST['options']) && empty($_POST['options'])) {
                         $stmt_delete_all_options = $pdo->prepare("DELETE FROM options WHERE question_id = ?");
                         $stmt_delete_all_options->execute([$question_id]);
                    }

                    $pdo->commit();
                    $message = display_message("Question updated successfully!", "success");
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Edit Question Error: " . $e->getMessage());
                    $message = display_message("Database error while updating question.", "error");
                }
                break;

            case 'delete_question':
                $question_id = sanitize_input($_POST['question_id'] ?? 0);
                if (empty($question_id)) {
                    $message = display_message("Question ID is required to delete.", "error");
                    break;
                }
                try {
                    $stmt = $pdo->prepare("DELETE FROM questions WHERE question_id = :question_id AND quiz_id = :quiz_id");
                    if ($stmt->execute(['question_id' => $question_id, 'quiz_id' => $qiuiz_id])) {
                        $message = display_message("Question deleted successfully!", "success");
                    } else {
                        $message = display_message("Failed to delete question.", "error");
                    }
                } catch (PDOException $e) {
                    error_log("Delete Question Error: " . $e->getMessage());
                    $message = display_message("Database error while deleting question.", "error");
                }
                break;

            case 'delete_all_questions':
                try {
                    // Delete all questions for the quiz, which will cascade delete options
                    $stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = :quiz_id");
                    if ($stmt->execute(['quiz_id' => $quiz_id])) {
                        $message = display_message("All questions deleted successfully!", "success");
                    } else {
                        $message = display_message("Failed to delete questions.", "error");
                    }
                } catch (PDOException $e) {
                    error_log("Delete All Questions Error: " . $e->getMessage());
                    $message = display_message("Database error while deleting all questions.", "error");
                }
                break;

            case 'upload_csv':
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp_path = $_FILES['csv_file']['tmp_name'];
                    $file_mime_type = mime_content_type($file_tmp_path);
                    $allowed_mime_types = ['text/csv', 'application/csv', 'text/plain'];

                    if (!in_array($file_mime_type, $allowed_mime_types)) {
                        $message = display_message("Invalid file type. Please upload a CSV file.", "error");
                        break;
                    }

                    if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
                        $row_count = 0;
                        $questions_added = 0;
                        $errors = [];

                        fgetcsv($handle); // Skip header row

                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            $row_count++;
                            if (count($data) < 3) {
                                $errors[] = "Row {$row_count}: Insufficient columns. Expected at least Question Text, Type, Score. Skipping row.";
                                continue;
                            }

                            $csv_question_text = trim(sanitize_input($data[0] ?? ''));
                            $csv_question_type = strtolower(trim(sanitize_input($data[1] ?? '')));
                            $csv_score = (int)($data[2] ?? 0);

                            if (empty($csv_question_text)) {
                                $errors[] = "Row {$row_count}: Question Text cannot be empty. Skipping row.";
                                continue;
                            }
                            if (empty($csv_question_type) || !in_array($csv_question_type, $question_types)) {
                                $errors[] = "Row {$row_count}: Invalid or missing Question Type ('{$csv_question_type}'). Allowed types are: " . implode(', ', $question_types) . ". Skipping row.";
                                continue;
                            }
                            if ($csv_score <= 0) {
                                $errors[] = "Row {$row_count}: Score must be a positive number. Skipping row.";
                                continue;
                            }

                            try {
                                $pdo->beginTransaction();

                                $stmt_q = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, question_type, score) VALUES (:quiz_id, :question_text, :question_type, :score)");
                                $stmt_q->execute([
                                    'quiz_id' => $quiz_id,
                                    'question_text' => $csv_question_text,
                                    'question_type' => $csv_question_type,
                                    'score' => $csv_score
                                ]);
                                $new_question_id = $pdo->lastInsertId();

                                if ($new_question_id) {
                                    if ($csv_question_type === 'multiple_choice' || $csv_question_type === 'true_false') {
                                        $correct_option_found = false;
                                        $options_count_for_row = 0;
                                        for ($i = 3; $i < count($data); $i += 2) {
                                            $option_text_raw = $data[$i] ?? '';
                                            $option_text = sanitize_input($option_text_raw);
                                            $is_correct = (int)($data[$i+1] ?? 0);

                                            if (empty($option_text) && !empty(trim($option_text_raw))) {
                                                $errors[] = "Row {$row_count}: Problem with option text (empty after sanitization) at column " . ($i + 1) . ". Skipping options for this question.";
                                                $pdo->rollBack();
                                                continue 2;
                                            }

                                            if (!empty($option_text) || $is_correct == 1) {
                                                $stmt_o = $pdo->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (:question_id, :option_text, :is_correct)");
                                                $stmt_o->execute([
                                                    'question_id' => $new_question_id,
                                                    'option_text' => $option_text,
                                                    'is_correct' => $is_correct
                                                ]);
                                                if ($is_correct == 1) {
                                                    $correct_option_found = true;
                                                }
                                                $options_count_for_row++;
                                            }
                                        }

                                        if (!$correct_option_found) {
                                            $pdo->rollBack();
                                            $errors[] = "Row {$row_count}: No correct option specified for multiple choice/true false question. Question not added.";
                                            continue;
                                        }
                                        if ($options_count_for_row < 2 && ($csv_question_type === 'multiple_choice' || $csv_question_type === 'true_false')) {
                                            $pdo->rollBack();
                                            $errors[] = "Row {$row_count}: Not enough options provided for '{$csv_question_type}' question type (at least 2 required). Question not added.";
                                            continue;
                                        }
                                    }
                                    $pdo->commit();
                                    $questions_added++;
                                } else {
                                    $pdo->rollBack();
                                    $errors[] = "Row {$row_count}: Failed to insert question into database.";
                                }
                            } catch (PDOException $e) {
                                $pdo->rollBack();
                                error_log("CSV Upload Error (Row {$row_count}): " . $e->getMessage());
                                $errors[] = "Row {$row_count}: Database error while adding question - " . $e->getMessage();
                            }
                        }
                        fclose($handle);

                        if ($questions_added > 0) {
                            $message = display_message("Successfully added {$questions_added} questions from CSV.", "success");
                        }
                        if (!empty($errors)) {
                            $message .= display_message("Some issues were found during CSV upload:<br>" . implode("<br>", $errors), "warning");
                        }
                        if ($questions_added == 0 && empty($errors)) {
                            $message = display_message("No valid questions found in the CSV file after processing.", "info");
                        }
                    } else {
                        $message = display_message("Failed to open the uploaded CSV file. Check file permissions or try again.", "error");
                    }
                } else {
                    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['csv_file']['error'] === UPLOAD_ERR_FORM_SIZE) {
                        $message = display_message("Uploaded file exceeds maximum size. Please upload a smaller file.", "error");
                    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $message = display_message("An unknown file upload error occurred (Error Code: " . $_FILES['csv_file']['error'] . ").", "error");
                    } else {
                        $message = display_message("No file was selected for upload.", "error");
                    }
                }
                break;
        }
    }
}

// Fetch questions for the current quiz_id for display
if ($quiz_id !== null) {
    try {
        $stmt = $pdo->prepare("
            SELECT q.question_id, q.question_text, q.question_type, q.score,
                   GROUP_CONCAT(o.option_id, '||', o.option_text, '||', o.is_correct ORDER BY o.option_id ASC SEPARATOR '###') AS options_data
            FROM questions q
            LEFT JOIN options o ON q.question_id = o.question_id
            WHERE q.quiz_id = :quiz_id
            GROUP BY q.question_id
            ORDER BY q.created_at ASC
        ");
        $stmt->execute(['quiz_id' => $quiz_id]);
        $raw_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_questions as $row) {
            $question = [
                'question_id' => $row['question_id'],
                'question_text' => $row['question_text'],
                'question_type' => $row['question_type'],
                'score' => $row['score'],
                'options' => [],
                'correct_option_index' => -1,
            ];

            if ($row['options_data']) {
                $options_raw = explode('###', $row['options_data']);
                foreach ($options_raw as $opt_str) {
                    list($option_id, $option_text, $is_correct) = explode('||', $opt_str);
                    $option = [
                        'option_id' => $option_id,
                        'option_text' => $option_text,
                        'is_correct' => $is_correct,
                    ];
                    $question['options'][] = $option;
                    if ($is_correct == 1) {
                        $question['correct_option_index'] = count($question['options']) - 1;
                    }
                }
            }
            $questions[] = $question;
        }
    } catch (PDOException $e) {
        error_log("Fetch Questions Error: " . $e->getMessage());
        $message = display_message("Could not fetch questions. Please try again later.", "error");
    }
}
?>

<div class="container mx-auto p-4 py-8">
    <?php if ($quiz_id !== null): ?>
        <div class="mb-4">
            <a href="<?php echo BASE_URL; ?>admin/assessments.php" class="inline-block bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">Go Back</a>
        </div>
    <?php endif; ?>
    <h1 class="text-3xl font-bold text-theme-color mb-4">Manage Questions for "<?php echo $quiz_title; ?>"</h1>

    <?php echo $message; ?>

    <?php if ($quiz_id === null): ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4" role="alert">
            <p class="font-bold">No Assessment Selected</p>
            <p>Please go to <a href="<?php echo BASE_URL; ?>admin/assessments.php" class="underline hover:text-blue-900">Manage Assessments</a> to select an assessment or create a new one to add questions.</p>
        </div>
    <?php else: ?>
        <div class="mb-6 flex gap-4">
         <button id="openAddQuestionsModal"
        class="text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300"
        style="background-color: #1a202c; hover:background-color: #2d3748;">
    + Add Questions
</button>
            <button id="openDeleteAllModal"
                    class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                Delete All Questions
            </button>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Existing Questions</h2>
            <?php if (empty($questions)): ?>
                <p class="text-gray-600">No questions found for this assessment. Start by adding one above!</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question Text</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correct Option</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($questions as $question): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900 max-w-md overflow-hidden text-ellipsis">
                                <?php echo htmlspecialchars($question['question_text']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo str_replace('_', ' ', htmlspecialchars(ucwords($question['question_type']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($question['score']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php
                                    if ($question['question_type'] === 'multiple_choice' && isset($question['options'][$question['correct_option_index']])) {
                                        echo htmlspecialchars($question['options'][$question['correct_option_index']]['option_text']);
                                    } elseif ($question['question_type'] === 'true_false') {
                                        foreach ($question['options'] as $opt) {
                                            if ($opt['is_correct'] == 1) {
                                                echo htmlspecialchars($opt['option_text']);
                                                break;
                                            }
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="openEditQuestionModal(<?php echo htmlspecialchars(json_encode($question)); ?>)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                <form action="questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this question and its options?');">
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
    <?php endif; ?>
</div>

<!-- Add Questions Modal -->
<div id="addQuestionsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-lg">
       <h2 class="text-2xl font-semibold mb-6" style="color: #1a202c;">Add Questions</h2>
        <div class="flex border-b border-gray-200 mb-4">
            <button id="manualTab" class="flex-1 py-2 px-4 text-center font-medium text-gray-700 bg-gray-100 rounded-tl-lg focus:outline-none active:bg-white active:border-b-2 active:border-green-500">Add Manually</button>
            <button id="csvTab" class="flex-1 py-2 px-4 text-center font-medium text-gray-700 bg-gray-100 rounded-tr-lg focus:outline-none">Upload CSV</button>
        </div>

        <!-- Manual Add Form -->
        <div id="manualAddForm" class="space-y-4">
            <form action="questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_question">
                <div>
                    <label for="add_question_text" class="block text-gray-700 text-sm font-bold mb-2">Question Text:</label>
                    <textarea id="add_question_text" name="question_text" rows="3" required
                              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500"></textarea>
                </div>
                <div>
                    <label for="add_score" class="block text-gray-700 text-sm font-bold mb-2">Score for this question:</label>
                    <input type="number" id="add_score" name="score" min="1" value="1" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                </div>
                <div>
                    <label for="add_question_type" class="block text-gray-700 text-sm font-bold mb-2">Question Type:</label>
                    <select id="add_question_type" name="question_type" required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                        <option value="">Select Type</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                    </select>
                </div>

                <div id="add_options_container" class="space-y-2 hidden">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Options:</label>
                    <div id="add_options_list"></div>
                    <button type="button" onclick="addOptionField('add')" class="text-blue-600 hover:text-blue-800 text-sm font-semibold mt-2">Add Option</button>
                </div>

                <div class="flex justify-end space-x-4 mt-6">
                    <button type="submit"
                            class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        Add Question
                    </button>
                    <button type="button" onclick="closeAddQuestionsModal()"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- CSV Upload Form -->
        <div id="csvUploadForm" class="space-y-4 hidden">
            <form action="questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload_csv">
                <div>
                    <label for="csv_file" class="block text-gray-700 text-sm font-bold mb-2">Select CSV File:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-purple-500">
                    <p class="text-sm text-gray-600 mt-2">
                        <strong>Expected CSV Format (with optional header row):</strong><br>
                        `Question Text,Question Type,Score,Option1,IsCorrect1,Option2,IsCorrect2,...`
                        <br><br>
                        <strong>Example (Multiple Choice):</strong><br>
                        `What is the capital of France?,multiple_choice,10,Paris,1,London,0,Berlin,0`
                        <br><br>
                        <strong>Example (True/False):</strong><br>
                        `The Earth is flat?,true_false,5,True,0,False,1`
                        <br><br>
                        `IsCorrect` should be `1` for the correct option and `0` for incorrect options.
                        Make sure to include at least one correct option for multiple choice/true false questions.
                    </p>
                    <p class="mt-4 text-sm">
                        <a href="<?php echo BASE_URL; ?>admin/sample_questions.csv" download="sample_questions.csv"
                           class="text-blue-600 hover:text-blue-800 font-semibold flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Download Sample CSV
                        </a>
                    </p>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeAddQuestionsModal()"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-purple-700 hover:bg-purple-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        Upload CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete All Questions Modal -->
<div id="deleteAllModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Confirm Delete All Questions</h2>
        <p class="text-gray-600 mb-6">Are you sure you want to delete ALL questions for this assessment? This action cannot be undone.</p>
        <form action="questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" class="flex justify-end space-x-4">
            <input type="hidden" name="action" value="delete_all_questions">
            <button type="button" onclick="closeDeleteAllModal()"
                    class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                Cancel
            </button>
            <button type="submit"
                    class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                Delete All
            </button>
        </form>
    </div>
</div>

<!-- Edit Question Modal -->
<div id="editQuestionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-lg">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Edit Question</h2>
        <form action="questions.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_question">
            <input type="hidden" id="edit_question_id" name="question_id">
            <div>
                <label for="edit_question_text" class="block text-gray-700 text-sm font-bold mb-2">Question Text:</label>
                <textarea id="edit_question_text" name="question_text" rows="3" required
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500"></textarea>
            </div>
            <div>
                <label for="edit_score" class="block text-gray-700 text-sm font-bold mb-2">Score for this question:</label>
                <input type="number" id="edit_score" name="score" min="1" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="edit_question_type" class="block text-gray-700 text-sm font-bold mb-2">Question Type:</label>
                <select id="edit_question_type" name="question_type" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                    <option value="">Select Type</option>
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True/False</option>
                </select>
            </div>

            <div id="edit_options_container" class="space-y-2 hidden">
                <label class="block text-gray-700 text-sm font-bold mb-2">Options:</label>
                <div id="edit_options_list"></div>
                <button type="button" onclick="addOptionField('edit')" class="text-blue-600 hover:text-blue-800 text-sm font-semibold mt-2">Add Option</button>
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeEditQuestionModal()"
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
    // Global counter for adding new options
    let addOptionCounter = 0;
    let editOptionCounter = 0;

    function addOptionField(formType, optionText = '', isCorrect = false, optionId = '') {
        const listId = `${formType}_options_list`;
        const optionsList = document.getElementById(listId);
        const currentCounter = (formType === 'add') ? addOptionCounter++ : editOptionCounter++;

        const optionDiv = document.createElement('div');
        optionDiv.className = 'flex items-center space-x-2 option-row';
        optionDiv.setAttribute('data-option-index', currentCounter);

        let optionInputHtml = '';
        if (optionId) {
            optionInputHtml += `<input type="hidden" name="options[${currentCounter}][id]" value="${optionId}">`;
        }
        optionInputHtml += `
            <input type="radio" name="${formType}_correct_option_index" value="${currentCounter}" ${isCorrect ? 'checked' : ''}
                   class="form-radio h-4 w-4 text-indigo-600 correct-option-radio" required>
            <input type="text" name="options[${currentCounter}][text]" placeholder="Option text" value="${optionText}" required
                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline flex-grow">
            <button type="button" onclick="removeOptionField(this, '${formType}')"
                    class="text-red-600 hover:text-red-800 text-sm font-semibold py-1 px-2 rounded">Remove</button>
        `;

        optionDiv.innerHTML = optionInputHtml;
        optionsList.appendChild(optionDiv);

        const hiddenCorrectIndexInput = document.querySelector(`#${formType}QuestionForm input[name="correct_option_index"]`) ||
                                      document.querySelector(`#editQuestionModal input[name="correct_option_index"]`);
        if (hiddenCorrectIndexInput && isCorrect) {
            hiddenCorrectIndexInput.value = currentCounter;
        }
    }

    function removeOptionField(button, formType) {
        const optionRow = button.closest('.option-row');
        const optionsList = document.getElementById(`${formType}_options_list`);
        const removedIndex = parseInt(optionRow.getAttribute('data-option-index'));

        optionRow.remove();
        reindexOptions(formType);

        const hiddenCorrectIndexInput = document.querySelector(`#${formType}QuestionForm input[name="correct_option_index"]`) ||
                                       document.querySelector(`#editQuestionModal input[name="correct_option_index"]`);
        if (hiddenCorrectIndexInput && parseInt(hiddenCorrectIndexInput.value) === removedIndex) {
            hiddenCorrectIndexInput.value = -1;
            const remainingRadios = optionsList.querySelectorAll(`.correct-option-radio`);
            if (remainingRadios.length > 0) {
                remainingRadios[0].checked = true;
                hiddenCorrectIndexInput.value = remainingRadios[0].value;
            }
        }
    }

    function reindexOptions(formType) {
        const optionsList = document.getElementById(`${formType}_options_list`);
        const optionRows = optionsList.querySelectorAll('.option-row');
        let newCounter = 0;

        optionRows.forEach((row, index) => {
            row.setAttribute('data-option-index', index);
            const radio = row.querySelector('.correct-option-radio');
            if (radio) {
                radio.value = index;
            }
            const textInput = row.querySelector(`input[name$="][text]"]`);
            if (textInput) {
                textInput.name = `options[${index}][text]`;
            }
            const hiddenIdInput = row.querySelector(`input[name$="][id]"]`);
            if (hiddenIdInput) {
                hiddenIdInput.name = `options[${index}][id]`;
            }
            newCounter++;
        });

        const hiddenCorrectIndexInput = document.querySelector(`#${formType}QuestionForm input[name="correct_option_index"]`) ||
                                       document.querySelector(`#editQuestionModal input[name="correct_option_index"]`);
        if (hiddenCorrectIndexInput) {
            const recheckedRadio = optionsList.querySelector(`.correct-option-radio:checked`);
            hiddenCorrectIndexInput.value = recheckedRadio ? recheckedRadio.value : -1;
        }

        window[`${formType}OptionCounter`] = newCounter;
    }

    function toggleOptionsVisibility(formType) {
        const questionTypeSelect = document.getElementById(`${formType}_question_type`);
        const optionsContainer = document.getElementById(`${formType}_options_container`);
        const optionsList = document.getElementById(`${formType}_options_list`);

        if (questionTypeSelect.value === 'multiple_choice' || questionTypeSelect.value === 'true_false') {
            optionsContainer.classList.remove('hidden');
        } else {
            optionsContainer.classList.add('hidden');
            optionsList.innerHTML = '';
            if (formType === 'add') addOptionCounter = 0;
            if (formType === 'edit') editOptionCounter = 0;
        }
    }

    document.getElementById('add_question_type').addEventListener('change', function() {
        toggleOptionsVisibility('add');
        const optionsList = document.getElementById('add_options_list');
        optionsList.innerHTML = '';
        addOptionCounter = 0;

        if (this.value === 'true_false') {
            addOptionField('add', 'True', false);
            addOptionField('add', 'False', false);
        } else if (this.value === 'multiple_choice') {
            addOptionField('add');
            addOptionField('add');
        }
    });

    function openEditQuestionModal(question) {
        document.getElementById('edit_question_id').value = question.question_id;
        document.getElementById('edit_question_text').value = question.question_text;
        document.getElementById('edit_score').value = question.score;
        document.getElementById('edit_question_type').value = question.question_type;

        const editOptionsList = document.getElementById('edit_options_list');
        editOptionsList.innerHTML = '';
        editOptionCounter = 0;

        if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
            document.getElementById('edit_options_container').classList.remove('hidden');
            if (question.options && question.options.length > 0) {
                question.options.forEach(function(option, index) {
                    const is_correct_for_this_option = (question.correct_option_index === index);
                    addOptionField('edit', option.option_text, is_correct_for_this_option, option.option_id);
                });
            } else {
                if (question.question_type === 'true_false') {
                    addOptionField('edit', 'True', false);
                    addOptionField('edit', 'False', false);
                } else {
                    addOptionField('edit');
                    addOptionField('edit');
                }
            }
        } else {
            document.getElementById('edit_options_container').classList.add('hidden');
        }

        const hiddenEditCorrectIndexInput = document.querySelector('#editQuestionModal input[name="correct_option_index"]');
        if (hiddenEditCorrectIndexInput) {
            hiddenEditCorrectIndexInput.value = question.correct_option_index;
        }

        document.getElementById('editQuestionModal').classList.remove('hidden');
    }

    function closeEditQuestionModal() {
        document.getElementById('editQuestionModal').classList.add('hidden');
    }

    function openAddQuestionsModal() {
        const modal = document.getElementById('addQuestionsModal');
        const manualTab = document.getElementById('manualTab');
        const csvTab = document.getElementById('csvTab');
        const manualForm = document.getElementById('manualAddForm');
        const csvForm = document.getElementById('csvUploadForm');

        modal.classList.remove('hidden');
        manualTab.classList.add('bg-white', 'border-b-2', 'border-green-500');
        csvTab.classList.remove('bg-white', 'border-b-2', 'border-green-500');
        manualForm.classList.remove('hidden');
        csvForm.classList.add('hidden');

        // Reset manual form
        document.getElementById('manualAddForm').querySelector('form').reset();
        document.getElementById('add_options_list').innerHTML = '';
        addOptionCounter = 0;
        document.getElementById('add_options_container').classList.add('hidden');

        // Reset CSV form
        document.getElementById('csv_file').value = '';
    }

    function closeAddQuestionsModal() {
        document.getElementById('addQuestionsModal').classList.add('hidden');
    }

    function openDeleteAllModal() {
        document.getElementById('deleteAllModal').classList.remove('hidden');
    }

    function closeDeleteAllModal() {
        document.getElementById('deleteAllModal').classList.add('hidden');
    }

    document.getElementById('openAddQuestionsModal').addEventListener('click', openAddQuestionsModal);

    document.getElementById('openDeleteAllModal').addEventListener('click', openDeleteAllModal);

    document.getElementById('manualTab').addEventListener('click', function() {
        this.classList.add('bg-white', 'border-b-2', 'border-green-500');
        document.getElementById('csvTab').classList.remove('bg-white', 'border-b-2', 'border-green-500');
        document.getElementById('manualAddForm').classList.remove('hidden');
        document.getElementById('csvUploadForm').classList.add('hidden');
    });

    document.getElementById('csvTab').addEventListener('click', function() {
        this.classList.add('bg-white', 'border-b-2', 'border-green-500');
        document.getElementById('manualTab').classList.remove('bg-white', 'border-b-2', 'border-green-500');
        document.getElementById('csvUploadForm').classList.remove('hidden');
        document.getElementById('manualAddForm').classList.add('hidden');
    });

    document.getElementById('edit_question_type').addEventListener('change', function() {
        toggleOptionsVisibility('edit');
        const optionsList = document.getElementById('edit_options_list');
        optionsList.innerHTML = '';
        editOptionCounter = 0;

        if (this.value === 'true_false') {
            addOptionField('edit', 'True', false);
            addOptionField('edit', 'False', false);
        } else if (this.value === 'multiple_choice') {
            addOptionField('edit');
            addOptionField('edit');
        }
    });

    window.addEventListener('click', function(event) {
        const addModal = document.getElementById('addQuestionsModal');
        const editModal = document.getElementById('editQuestionModal');
        const deleteModal = document.getElementById('deleteAllModal');

        if (event.target === addModal) {
            closeAddQuestionsModal();
        }
        if (event.target === editModal) {
            closeEditQuestionModal();
        }
        if (event.target === deleteModal) {
            closeDeleteAllModal();
        }
    });

    window.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAddQuestionsModal();
            closeEditQuestionModal();
            closeDeleteAllModal();
        }
    });

    document.getElementById('add_options_list').addEventListener('change', function(event) {
        if (event.target.classList.contains('correct-option-radio')) {
            document.querySelector('#manualAddForm input[name="correct_option_index"]').value = event.target.value;
        }
    });

    document.getElementById('edit_options_list').addEventListener('change', function(event) {
        if (event.target.classList.contains('correct-option-radio')) {
            document.querySelector('#editQuestionModal input[name="correct_option_index"]').value = event.target.value;
        }
    });
</script>

<?php
require_once '../includes/footer_admin.php';
?>