<?php
// admin/view_results.php
// Page for administrators to view assessment results and proctoring logs.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$results = []; // Array to hold fetched assessment results
$proctoring_logs = []; // Array to hold fetched proctoring logs for a specific attempt
$current_attempt = null; // Store details of the currently viewed attempt

// Filters
$filter_user_id = sanitize_input($_GET['user_id'] ?? null);
$filter_assessment_id = sanitize_input($_GET['quiz_id'] ?? null); // Renamed for consistency

// Sorting
$sort_by = sanitize_input($_GET['sort_by'] ?? 'start_time'); // Default sort by start time
$sort_order = sanitize_input(strtoupper($_GET['sort_order'] ?? 'DESC')); // Default sort order DESC

// Validate sort parameters to prevent SQL injection
$allowed_sort_columns = ['attempt_id', 'username', 'quiz_title', 'score', 'start_time', 'end_time', 'is_completed'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'start_time'; // Fallback to default
}
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC'; // Fallback to default
}


$view_attempt_id = sanitize_input($_GET['view_attempt'] ?? null);

try {
    if ($view_attempt_id) {
        // Fetch detailed results and proctoring logs for a specific attempt
        $stmt = $pdo->prepare("
            SELECT
                qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                u.username, u.email, q.title as quiz_title, q.duration_minutes
            FROM quiz_attempts qa
            JOIN users u ON qa.user_id = u.user_id
            JOIN quizzes q ON qa.quiz_id = q.quiz_id
            WHERE qa.attempt_id = :attempt_id
        ");
        $stmt->execute(['attempt_id' => $view_attempt_id]);
        $current_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current_attempt) {
            // Fetch answers for this attempt
            $stmt_answers = $pdo->prepare("
                SELECT
                    a.answer_id, a.answer_text, a.is_correct, a.selected_option_id,
                    qs.question_id, qs.question_text, qs.question_type, qs.score as question_score,
                    GROUP_CONCAT(DISTINCT CONCAT(opt.option_id, '||', opt.option_text, '||', opt.is_correct) SEPARATOR ';;') as all_options_data
                FROM answers a
                JOIN questions qs ON a.question_id = qs.question_id
                LEFT JOIN options opt ON qs.question_id = opt.question_id
                WHERE a.attempt_id = :attempt_id
                GROUP BY a.answer_id, qs.question_id, qs.question_text, qs.question_type, qs.score, a.answer_text, a.is_correct, a.selected_option_id
                ORDER BY qs.question_id ASC
            ");
            $stmt_answers->execute(['attempt_id' => $view_attempt_id]);
            $current_attempt['answers'] = $stmt_answers->fetchAll(PDO::FETCH_ASSOC);

            // Fetch proctoring logs for this attempt
            $stmt_logs = $pdo->prepare("
                SELECT log_time, event_type, log_data
                FROM proctoring_logs
                WHERE attempt_id = :attempt_id
                ORDER BY log_time ASC
            ");
            $stmt_logs->execute(['attempt_id' => $view_attempt_id]);
            $proctoring_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $message = display_message("Attempt not found.", "error");
        }

    } else {
        // Fetch overview of all assessment attempts (or filtered attempts)
        $sql = "
            SELECT
                qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                u.username, q.title as quiz_title, q.quiz_id, u.user_id
            FROM quiz_attempts qa
            JOIN users u ON qa.user_id = u.user_id
            JOIN quizzes q ON qa.quiz_id = q.quiz_id
        ";
        $params = [];
        $where_clauses = [];

        if ($filter_user_id) {
            $where_clauses[] = "u.user_id = :user_id";
            $params['user_id'] = $filter_user_id;
        }
        if ($filter_assessment_id) {
            $where_clauses[] = "q.quiz_id = :quiz_id";
            $params['quiz_id'] = $filter_assessment_id;
        }

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $sql .= " ORDER BY {$sort_by} {$sort_order}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Calculate Summary Metrics ---
        $total_attempts = count($results);
        $completed_attempts = 0;
        $passed_attempts = 0;
        $failed_attempts = 0;
        $total_score_sum = 0;
        $attempts_with_proctoring_violations = 0; // Initialize violation count

        // Define a passing score percentage (e.g., 70% of max possible score for the quiz)
        // This assumes 'score' in quiz_attempts is out of a max, and 'question_score' is also defined.
        // A more robust solution would dynamically get max_score for each quiz.
        $passing_percentage = 70; // 70% to pass

        foreach ($results as $result) {
            if ($result['is_completed']) {
                $completed_attempts++;
                $total_score_sum += $result['score']; // Assuming 'score' is a valid number

                // To determine pass/fail, we need the max possible score for the quiz
                // This would ideally come from the 'quizzes' table or calculated from questions.
                // For simplicity here, let's assume maximum score is 100 for percentage calculation.
                // A more accurate approach would be to fetch sum of question scores for each quiz.
                // Let's retrieve max score for each quiz on the fly for accurate pass/fail.
                $stmt_max_score = $pdo->prepare("SELECT SUM(score) FROM questions WHERE quiz_id = :quiz_id");
                $stmt_max_score->execute(['quiz_id' => $result['quiz_id']]);
                $max_possible_score = $stmt_max_score->fetchColumn();

                if ($max_possible_score > 0) {
                    $percentage_score = ($result['score'] / $max_possible_score) * 100;
                    if ($percentage_score >= $passing_percentage) {
                        $passed_attempts++;
                    } else {
                        $failed_attempts++;
                    }
                } else {
                    // If no questions or max score is 0, consider it N/A or default to failed
                    $failed_attempts++;
                }

                // Check for proctoring violations for completed attempts
                $stmt_violation_check = $pdo->prepare("SELECT COUNT(*) FROM proctoring_logs WHERE attempt_id = :attempt_id AND event_type = 'critical_error'");
                $stmt_violation_check->execute(['attempt_id' => $result['attempt_id']]);
                if ($stmt_violation_check->fetchColumn() > 0) {
                    $attempts_with_proctoring_violations++;
                }
            }
        }
        $average_score = ($completed_attempts > 0) ? round($total_score_sum / $completed_attempts, 2) : 0;
        $completion_rate = ($total_attempts > 0) ? round(($completed_attempts / $total_attempts) * 100, 2) : 0;

        $summary_metrics = [
            'total_attempts' => $total_attempts,
            'completed_attempts' => $completed_attempts,
            'passed_attempts' => $passed_attempts,
            'failed_attempts' => $failed_attempts,
            'average_score' => $average_score,
            'completion_rate' => $completion_rate,
            'attempts_with_proctoring_violations' => $attempts_with_proctoring_violations,
            'passing_percentage_threshold' => $passing_percentage
        ];
    }

    // Fetch lists of users (only students) and assessments for filter dropdowns
    $all_users = $pdo->query("SELECT user_id, username, role FROM users WHERE role = 'student' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    $all_assessments = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("View Results Error: " . $e->getMessage());
    $message = display_message("Could not fetch results. Please try again later.", "error");
}

?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">View Assessment Results</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if ($view_attempt_id && isset($current_attempt)): ?>
        <!-- Detailed View of a Single Assessment Attempt -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Attempt Details for "<?php echo htmlspecialchars($current_attempt['quiz_title']); ?>" by <?php echo htmlspecialchars($current_attempt['username']); ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700 mb-6">
                <p><strong>Attempt ID:</strong> <?php echo htmlspecialchars($current_attempt['attempt_id']); ?></p>
                <p><strong>Started:</strong> <?php echo date('h:i A, j F, Y', strtotime($current_attempt['start_time'])); ?></p>
                <p><strong>Completed:</strong> <?php echo $current_attempt['end_time'] ? date('h:i A, j F, Y', strtotime($current_attempt['end_time'])) : 'N/A'; ?></p>
                <p><strong>Status:</strong> <?php echo $current_attempt['is_completed'] ? 'Completed' : 'In Progress'; ?></p>
                <p><strong>Score:</strong> <?php echo htmlspecialchars($current_attempt['score'] ?? 'N/A'); ?></p>
                <p><strong>User Email:</strong> <?php echo htmlspecialchars($current_attempt['email'] ?? 'N/A'); ?></p>
                <p><strong>Assessment Duration:</strong> <?php echo htmlspecialchars($current_attempt['duration_minutes'] ? $current_attempt['duration_minutes'] . ' minutes' : 'No Limit'); ?></p>
            </div>

            <h3 class="text-xl font-semibold text-gray-700 mb-3">Questions and Answers</h3>
            <?php if (!empty($current_attempt['answers'])): ?>
                <?php foreach ($current_attempt['answers'] as $answer): ?>
                    <div class="border p-4 rounded-md bg-gray-50 mb-4">
                        <p class="font-semibold text-gray-800">Q: <?php echo htmlspecialchars($answer['question_text']); ?> (Score: <?php echo htmlspecialchars($answer['question_score']); ?> points)</p>
                        <p class="text-sm text-gray-600">Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $answer['question_type']))); ?></p>
                        <?php if ($answer['question_type'] === 'multiple_choice'): ?>
                            <?php
                                $all_options_parsed = [];
                                $selected_option_text = 'No answer';
                                $correct_option_texts = [];

                                if (!empty($answer['all_options_data'])) {
                                    $options_raw = explode(';;', $answer['all_options_data']);
                                    foreach ($options_raw as $opt_str) {
                                        list($opt_id, $opt_text, $is_correct_val) = explode('||', $opt_str);
                                        $all_options_parsed[] = ['id' => (int)$opt_id, 'text' => $opt_text, 'is_correct' => (bool)$is_correct_val];
                                        if ((int)$opt_id === (int)$answer['selected_option_id']) {
                                            $selected_option_text = $opt_text;
                                        }
                                        if ((bool)$is_correct_val) {
                                            $correct_option_texts[] = $opt_text;
                                        }
                                    }
                                }
                            ?>
                            <p class="mt-2"><strong>All Options:</strong></p>
                            <ul class="list-disc ml-5 text-sm text-gray-700">
                                <?php foreach($all_options_parsed as $option): ?>
                                    <li class="<?php
                                        if ((int)$option['id'] === (int)$answer['selected_option_id']) {
                                            echo $answer['is_correct'] ? 'text-green-700 font-bold' : 'text-red-700 font-bold';
                                        } elseif ($option['is_correct']) {
                                            echo 'text-green-600'; // Correct option not selected
                                        }
                                    ?>">
                                        <?php echo htmlspecialchars($option['text']); ?>
                                        <?php if ((int)$option['id'] === (int)$answer['selected_option_id']) echo ' (Selected)'; ?>
                                        <?php if ($option['is_correct']) echo ' (Correct Answer)'; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="mt-2"><strong>Your Answer:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600' : 'text-red-600'; ?>"><?php echo htmlspecialchars($selected_option_text); ?></span></p>
                            <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars(implode(', ', $correct_option_texts)); ?></p>
                            <p><strong>Result:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></span></p>
                        <?php elseif ($answer['question_type'] === 'true_false' || $answer['question_type'] === 'short_answer'): ?>
                            <p><strong>Student's Answer:</strong> <?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?></p>
                            <?php
                                // Fetch the actual correct answer for true/false or short answer if applicable
                                $correct_answer_text = 'N/A';
                                if ($answer['question_type'] === 'true_false') {
                                    // For true/false, correct answer is usually stored in the question itself or implied
                                    // Assuming a 'true'/'false' option text is marked as correct in the options table for this type
                                    $stmt_tf_correct = $pdo->prepare("SELECT option_text FROM options WHERE question_id = :qid AND is_correct = 1 LIMIT 1");
                                    $stmt_tf_correct->execute(['qid' => $answer['question_id']]);
                                    $correct_answer_text = $stmt_tf_correct->fetchColumn() ?: 'N/A';
                                }
                                // For short answer, correct answer often requires manual review, or it's a specific string comparison
                                // This example doesn't have a direct 'correct_answer' field for short_answer in questions table
                            ?>
                            <p><strong>Correct Answer:</strong> <?php echo ($answer['question_type'] === 'true_false') ? htmlspecialchars($correct_answer_text) : 'Manual Review'; ?></p>
                            <p><strong>Result:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></span></p>
                        <?php elseif ($answer['question_type'] === 'essay'): ?>
                            <p><strong>Student's Answer:</strong></p>
                            <div class="bg-gray-100 p-3 rounded-md border text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?></div>
                            <p class="text-sm text-gray-600 mt-2"><em>Requires manual grading.</em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-600">No answers recorded for this attempt yet.</p>
            <?php endif; ?>

            <h3 class="text-xl font-semibold text-gray-700 mb-3 mt-6">Proctoring Logs</h3>
            <?php if (!empty($proctoring_logs)): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($proctoring_logs as $log): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('h:i A, j F, Y', strtotime($log['log_time'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['event_type']))); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 break-words max-w-lg">
                                <?php
                                    // Attempt to decode JSON data if it's a JSON string
                                    $log_data = $log['log_data'];
                                    $decoded_data = json_decode($log_data, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data)) {
                                        echo '<pre class="bg-gray-100 p-2 rounded text-xs overflow-auto max-h-24">' . htmlspecialchars(json_encode($decoded_data, JSON_PRETTY_PRINT)) . '</pre>';
                                    } else {
                                        echo htmlspecialchars($log_data);
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-600">No proctoring logs available for this attempt.</p>
            <?php endif; ?>

            <div class="mt-8">
                <a href="view_results.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                    &larr; Back to All Results
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- Overview of All Assessment Attempts (or filtered) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Summary Card: Total Attempts -->
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <h3 class="text-xl font-semibold text-gray-800">Total Attempts</h3>
                <p class="text-4xl font-bold text-theme-color mt-3"><?php echo htmlspecialchars($summary_metrics['total_attempts']); ?></p>
            </div>
            <!-- Summary Card: Completed Attempts -->
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <h3 class="text-xl font-semibold text-gray-800">Completed</h3>
                <p class="text-4xl font-bold text-green-600 mt-3"><?php echo htmlspecialchars($summary_metrics['completed_attempts']); ?></p>
            </div>
            <!-- Summary Card: Passed Attempts -->
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <h3 class="text-xl font-semibold text-gray-800">Passed (>= <?php echo $summary_metrics['passing_percentage_threshold']; ?>%)</h3>
                <p class="text-4xl font-bold text-blue-600 mt-3"><?php echo htmlspecialchars($summary_metrics['passed_attempts']); ?></p>
            </div>
            <!-- Summary Card: Failed Attempts -->
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <h3 class="text-xl font-semibold text-gray-800">Failed (< <?php echo $summary_metrics['passing_percentage_threshold']; ?>%)</h3>
                <p class="text-4xl font-bold text-red-600 mt-3"><?php echo htmlspecialchars($summary_metrics['failed_attempts']); ?></p>
            </div>
             <!-- Summary Card: Average Score -->
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <h3 class="text-xl font-semibold text-gray-800">Average Score</h3>
                <p class="text-4xl font-bold text-purple-600 mt-3"><?php echo htmlspecialchars($summary_metrics['average_score']); ?>%</p>
            </div>
            <!-- Summary Card: Completion Rate -->
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <h3 class="text-xl font-semibold text-gray-800">Completion Rate</h3>
                <p class="text-4xl font-bold text-indigo-600 mt-3"><?php echo htmlspecialchars($summary_metrics['completion_rate']); ?>%</p>
            </div>
            <!-- Summary Card: Proctoring Violations -->
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <h3 class="text-xl font-semibold text-gray-800">Proctoring Violations</h3>
                <p class="text-4xl font-bold text-orange-600 mt-3"><?php echo htmlspecialchars($summary_metrics['attempts_with_proctoring_violations']); ?></p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Filter Results</h2>
            <form action="view_results.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="filter_user" class="block text-gray-700 text-sm font-bold mb-2">Filter by Student:</label>
                    <select id="filter_user" name="user_id"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                        <option value="">All Students</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['user_id']); ?>"
                                <?php echo ($filter_user_id == $user['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars(ucfirst($user['role'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_assessment" class="block text-gray-700 text-sm font-bold mb-2">Filter by Assessment:</label>
                    <select id="filter_assessment" name="quiz_id"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                        <option value="">All Assessments</option>
                        <?php foreach ($all_assessments as $assessment): ?>
                            <option value="<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                                <?php echo ($filter_assessment_id == $assessment['quiz_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($assessment['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit"
                            class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300 w-full">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">All Assessment Attempts</h2>
            <?php if (empty($results)): ?>
                <p class="text-gray-600">No assessment attempts found matching the criteria.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php
                            $headers = [
                                'attempt_id' => 'Attempt ID',
                                'username' => 'Student',
                                'quiz_title' => 'Assessment Title',
                                'score' => 'Score',
                                'start_time' => 'Start Time',
                                'end_time' => 'End Time',
                                'is_completed' => 'Status'
                            ];
                            foreach ($headers as $col_name => $display_name):
                                $new_sort_order = ($sort_by === $col_name && $sort_order === 'ASC') ? 'DESC' : 'ASC';
                                $arrow = ($sort_by === $col_name) ? ($sort_order === 'ASC' ? ' &uarr;' : ' &darr;') : '';
                            ?>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <a href="?user_id=<?php echo htmlspecialchars($filter_user_id); ?>&quiz_id=<?php echo htmlspecialchars($filter_assessment_id); ?>&sort_by=<?php echo htmlspecialchars($col_name); ?>&sort_order=<?php echo htmlspecialchars($new_sort_order); ?>" class="hover:text-gray-700">
                                        <?php echo $display_name . $arrow; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($results as $result): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($result['attempt_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($result['username']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($result['quiz_title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($result['score'] ?? 'N/A'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('h:i A, j F, Y', strtotime($result['start_time'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $result['end_time'] ? date('h:i A, j F, Y', strtotime($result['end_time'])) : 'In Progress'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $result['is_completed'] ? 'Completed' : 'In Progress'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="view_results.php?view_attempt=<?php echo htmlspecialchars($result['attempt_id']); ?>"
                                   class="text-blue-600 hover:text-blue-900 mr-3">View Details</a>
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
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>
