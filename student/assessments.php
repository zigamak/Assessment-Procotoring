<?php
// student/assessments.php
// Allows students to view their quiz history and detailed results for specific attempts.

require_once '../includes/session.php';
require_once '../includes/db.php'; // Make sure this connects successfully and sets $pdo
require_once '../includes/functions.php'; // Make sure this contains getUserId() and sanitize_input(), display_message()

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$user_id = getUserId(); // Get the ID of the currently logged-in student

// Redirect if user_id is not available (not logged in or session issue)
if (!$user_id) {
    // This scenario should ideally be handled by header_student.php, but good to have a fallback
    redirect('login.php'); // Or appropriate redirect for not logged in users
    exit();
}

$attempts = []; // Array to hold fetched quiz attempts for overview
$current_attempt = null; // Object to hold detailed data for a single attempt view

// --- Filter Variables ---
$filter_quiz_id = sanitize_input($_GET['quiz_id'] ?? null);
$filter_start_date = sanitize_input($_GET['start_date'] ?? null);
$filter_end_date = sanitize_input($_GET['end_date'] ?? null);
$filter_min_percentage = filter_input(INPUT_GET, 'min_percentage', FILTER_VALIDATE_FLOAT);
// Ensure min_percentage is within a valid range
if ($filter_min_percentage !== false && $filter_min_percentage < 0) $filter_min_percentage = 0;
if ($filter_min_percentage !== false && $filter_min_percentage > 100) $filter_min_percentage = 100;


// Sanitize the input attempt_id from the GET request
$view_attempt_id = filter_input(INPUT_GET, 'attempt_id', FILTER_VALIDATE_INT);

/**
 * Calculates the percentage score.
 * @param float $score The raw score obtained.
 * @param float $max_score The maximum possible score for the quiz.
 * @return float The percentage score, rounded to 2 decimal places.
 */
function calculate_percentage($score, $max_score) {
    if ($max_score <= 0) {
        return 0; // Avoid division by zero
    }
    return round(($score / $max_score) * 100, 2);
}

// Fetch all quizzes for the filter dropdown
$all_quizzes_for_filters = [];
try {
    $stmt_quizzes = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title ASC");
    $all_quizzes_for_filters = $stmt_quizzes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("View History Quiz Fetch Error: " . $e->getMessage());
    $message = display_message("Could not fetch assessment list for filters.", "error");
}


// Main logic to fetch either overview or detailed attempt
if ($view_attempt_id) {
    // Detailed View Logic
    try {
        // Fetch detailed results for a specific attempt for the current student
        $stmt = $pdo->prepare("
            SELECT
                qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                q.title as quiz_title, q.description as quiz_description,
                (SELECT SUM(score) FROM questions WHERE quiz_id = q.quiz_id) as max_possible_score
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.quiz_id
            WHERE qa.attempt_id = :attempt_id AND qa.user_id = :user_id
        ");
        $stmt->execute(['attempt_id' => $view_attempt_id, 'user_id' => $user_id]);
        $current_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current_attempt) {
            // Calculate percentage for the detailed view
            if ($current_attempt['is_completed']) {
                $current_attempt['percentage_score'] = calculate_percentage($current_attempt['score'], $current_attempt['max_possible_score']);
            } else {
                $current_attempt['percentage_score'] = 'N/A';
            }

            // Fetch answers for this attempt
            try {
                $stmt_answers = $pdo->prepare("
                    SELECT
                        a.answer_id, a.answer_text, a.is_correct,
                        qs.question_id, qs.question_text, qs.question_type, qs.score as question_score,
                        o.option_text as selected_option_text,
                        GROUP_CONCAT(DISTINCT CONCAT(opt.option_text, '||', opt.is_correct) SEPARATOR ';;') as correct_options_data
                    FROM answers a
                    JOIN questions qs ON a.question_id = qs.question_id
                    LEFT JOIN options o ON a.selected_option_id = o.option_id
                    LEFT JOIN options opt ON qs.question_id = opt.question_id AND opt.is_correct = TRUE
                    WHERE a.attempt_id = :attempt_id
                    GROUP BY a.answer_id, a.answer_text, a.is_correct, qs.question_id, qs.question_text, qs.question_type, qs.score, o.option_text
                    ORDER BY qs.question_id ASC
                ");
                $stmt_answers->execute(['attempt_id' => $view_attempt_id]);
                $current_attempt['answers'] = $stmt_answers->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Student View History (Answers) Error: " . $e->getMessage());
                $message .= display_message("An error occurred while fetching answers for this attempt.", "warning");
                $current_attempt['answers'] = []; // Ensure it's an empty array if fetching fails
            }

        } else {
            $message = display_message("Attempt not found or you do not have permission to view it.", "error");
            // If attempt isn't found/permission denied, set $view_attempt_id to null
            // so that the overview is shown instead of a blank detailed view.
            $view_attempt_id = null;
        }

    } catch (PDOException $e) {
        error_log("Student View History (Main Detailed) Error: " . $e->getMessage());
        $message = display_message("An error occurred while fetching the detailed quiz attempt. Please try again later.", "error");
        $view_attempt_id = null; // Revert to overview if detailed attempt query failed
    }
}

// If not viewing a specific attempt (or if the specific attempt failed/not found), show overview
if (!$view_attempt_id) {
    try {
        // Fetch overview of all quiz attempts for the current student with filters
        $sql = "
            SELECT
                qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                q.title as quiz_title,
                (SELECT SUM(score) FROM questions WHERE quiz_id = q.quiz_id) as max_possible_score
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.quiz_id
            WHERE qa.user_id = :user_id
        ";

        $params = ['user_id' => $user_id];
        $where_clauses = [];

        if ($filter_quiz_id) {
            $where_clauses[] = "q.quiz_id = :quiz_id";
            $params['quiz_id'] = $filter_quiz_id;
        }
        if ($filter_start_date) {
            $where_clauses[] = "DATE(qa.start_time) >= :start_date";
            $params['start_date'] = $filter_start_date;
        }
        if ($filter_end_date) {
            $where_clauses[] = "DATE(qa.start_time) <= :end_date";
            $params['end_date'] = $filter_end_date;
        }

        if (!empty($where_clauses)) {
            $sql .= " AND " . implode(" AND ", $where_clauses);
        }

        $sql .= " ORDER BY qa.start_time DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attempts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process attempts to add percentage and then apply percentage filter
        foreach ($attempts_raw as $attempt) {
            if ($attempt['is_completed']) {
                $calculated_percentage = calculate_percentage($attempt['score'], $attempt['max_possible_score']);
                $attempt['percentage_score'] = $calculated_percentage;
            } else {
                $attempt['percentage_score'] = 'N/A';
            }

            // Apply percentage filter AFTER calculation
            if ($filter_min_percentage !== false) {
                if ($attempt['is_completed'] && $attempt['percentage_score'] >= $filter_min_percentage) {
                    $attempts[] = $attempt;
                } else if (!$attempt['is_completed'] && $filter_min_percentage == 0) { // If filtering for 0%, show incomplete
                    $attempts[] = $attempt;
                }
            } else {
                // If no percentage filter is set, include all
                $attempts[] = $attempt;
            }
        }

    } catch (PDOException $e) {
        error_log("Student View History (Overview) Error: " . $e->getMessage());
        $message = display_message("An error occurred while fetching your quiz history overview. Please try again later.", "error");
        $attempts = []; // Ensure it's an empty array if fetching fails
    }
}
?>

<div class="container mx-auto p-4 py-8 max-w-7xl">
    <h1 class="text-3xl font-bold text-theme-color mb-6 text-center">Your Assessments</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if ($view_attempt_id && isset($current_attempt) && $current_attempt): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Details for "<?php echo htmlspecialchars($current_attempt['quiz_title']); ?>"</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700 mb-6">
                <p><strong>Attempt ID:</strong> <?php echo htmlspecialchars($current_attempt['attempt_id']); ?></p>
                <p><strong>Started:</strong> <?php echo date('g:i A, F j, Y', strtotime($current_attempt['start_time'])); ?></p>
                <p><strong>Completed:</strong> <?php echo $current_attempt['end_time'] ? date('g:i A, F j, Y', strtotime($current_attempt['end_time'])) : 'N/A'; ?></p>
                <p><strong>Status:</strong> <?php echo $current_attempt['is_completed'] ? 'Completed' : 'Cancelled'; ?></p>
                <p><strong>Your Score:</strong> <?php echo htmlspecialchars($current_attempt['score'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($current_attempt['max_possible_score'] ?? 'N/A'); ?></p>
                <p><strong>Percentage Score:</strong> <span class="font-bold <?php echo ($current_attempt['percentage_score'] !== 'N/A' && $current_attempt['percentage_score'] >= 70) ? 'text-green-600' : 'text-red-600'; ?>"><?php echo htmlspecialchars($current_attempt['percentage_score']); ?>%</span></p>
            </div>

            <h3 class="text-xl font-semibold text-gray-700 mb-3">Questions and Your Answers</h3>
            <?php if (!empty($current_attempt['answers'])): ?>
                <?php foreach ($current_attempt['answers'] as $answer): ?>
                    <div class="border p-4 rounded-md bg-gray-50 mb-4">
                        <p class="font-semibold text-gray-800">Q: <?php echo htmlspecialchars($answer['question_text']); ?> (Points: <?php echo htmlspecialchars($answer['question_score']); ?>)</p>
                        <p class="text-sm text-gray-600">Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $answer['question_type']))); ?></p>
                        <?php if ($answer['question_type'] === 'multiple_choice'): ?>
                            <p><strong>Your Selected Option:</strong> <?php echo htmlspecialchars($answer['selected_option_text'] ?? 'No answer'); ?></p>
                            <?php
                                // Parse the correct options data
                                $correct_options_parsed = [];
                                if (!empty($answer['correct_options_data'])) {
                                    $options_raw = explode(';;', $answer['correct_options_data']);
                                    foreach ($options_raw as $opt_str) {
                                        // Ensure opt_str is not empty and contains '||' to prevent errors
                                        if (strpos($opt_str, '||') !== false) {
                                            list($opt_text, $is_correct_val) = explode('||', $opt_str, 2); // Limit split to 2
                                            if ((bool)$is_correct_val) {
                                                $correct_options_parsed[] = $opt_text;
                                            }
                                        }
                                    }
                                }
                            ?>
                            <p><strong>Correct Answer(s):</strong> <?php echo !empty($correct_options_parsed) ? htmlspecialchars(implode(', ', $correct_options_parsed)) : 'N/A'; ?></p>
                            <p><strong>Result:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></span></p>
                        <?php elseif ($answer['question_type'] === 'true_false' || $answer['question_type'] === 'short_answer'): ?>
                            <p><strong>Your Answer:</strong> <?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?></p>
                            <p><strong>Result:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></span></p>
                        <?php elseif ($answer['question_type'] === 'essay'): ?>
                            <p><strong>Your Answer:</strong></p>
                            <div class="bg-gray-100 p-3 rounded-md border text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?></div>
                            <p class="text-sm text-gray-600 mt-2"><em>This question requires manual grading by an administrator.</em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-600">No answers recorded for this attempt yet, or there was an issue retrieving them.</p>
            <?php endif; ?>

            <div class="mt-8">
                <a href="assessments.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                    &larr; Back to All Attempts
                </a>
            </div>
        </div>

    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Filter Quiz Attempts</h2>
            <form action="assessments.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="quiz_id" class="block text-sm font-medium text-gray-700 mb-1">Assessment:</label>
                    <select name="quiz_id" id="quiz_id"
                            class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50 select2-enabled"
                            data-placeholder="All Assessments" data-allow-clear="true">
                        <option value=""></option>
                        <?php foreach ($all_quizzes_for_filters as $quiz_filter) : ?>
                            <option value="<?php echo htmlspecialchars($quiz_filter['quiz_id']); ?>" <?php echo ((string)$filter_quiz_id === (string)$quiz_filter['quiz_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($quiz_filter['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="min_percentage" class="block text-sm font-medium text-gray-700 mb-1">Min. Percentage (%):</label>
                    <input type="number" name="min_percentage" id="min_percentage" min="0" max="100" step="any"
                           value="<?php echo htmlspecialchars($filter_min_percentage); ?>"
                           class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50"
                           placeholder="e.g., 70">
                </div>

                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"
                           class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
                </div>

                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"
                           class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
                </div>

                <div class="col-span-full flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-2 mt-4">
                    <button type="submit" class="bg-accent text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-300 ease-in-out flex items-center justify-center">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="assessments.php" class="bg-gray-400 text-white px-6 py-2 rounded-md hover:bg-gray-500 transition duration-300 ease-in-out flex items-center justify-center">
                        <i class="fas fa-undo mr-2"></i> Reset Filters
                    </a>
                </div>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Your Assessment Attempts</h2>
            <?php if (empty($attempts)): ?>
                <p class="text-center text-gray-600 py-8">No assessment attempts found matching your criteria.</p>
                <div class="mt-4 text-center">
                    <a href="dashboard.php" class="inline-block bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-300">
                        Explore Available Assessments
                    </a>
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm"> <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Title</th> <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score (%)</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-900"><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                                <?php echo htmlspecialchars($attempt['score'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($attempt['max_possible_score'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-900">
                                <span class="<?php echo ($attempt['percentage_score'] !== 'N/A' && $attempt['percentage_score'] >= 70) ? 'text-green-600 font-bold' : 'text-red-600 font-bold'; ?>">
                                    <?php echo htmlspecialchars($attempt['percentage_score']); ?>%
                                </span>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-900"><?php echo date('g:i A, F j, Y', strtotime($attempt['start_time'])); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-900"><?php echo $attempt['is_completed'] ? 'Completed' : 'Cancelled'; ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-right font-medium">
                                <a href="assessments.php?attempt_id=<?php echo htmlspecialchars($attempt['attempt_id']); ?>"
                                   class="text-blue-600 hover:text-blue-900">View Details</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>