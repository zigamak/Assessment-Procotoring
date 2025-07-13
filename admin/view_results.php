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
$assessments = []; // Array to hold all assessments for the main view

// Filters for the main assessment list
$filter_assessment_id_main = sanitize_input($_GET['assessment_id_main'] ?? null);

// Filters for detailed attempt list
$filter_user_id_detail = sanitize_input($_GET['user_id_detail'] ?? null);
$filter_completion_status = sanitize_input($_GET['completion_status'] ?? null);
$filter_score_min = sanitize_input($_GET['score_min'] ?? null);
$filter_score_max = sanitize_input($_GET['score_max'] ?? null);

// Sorting
$sort_by = sanitize_input($_GET['sort_by'] ?? 'score'); // Default sort by score
$sort_order = sanitize_input(strtoupper($_GET['sort_order'] ?? 'DESC')); // Default sort order DESC

// Validate sort parameters to prevent SQL injection
$allowed_sort_columns = ['attempt_id', 'username', 'quiz_title', 'score', 'start_time', 'end_time', 'is_completed'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'score'; // Fallback to default
}
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC'; // Fallback to default
}

$view_attempt_id = sanitize_input($_GET['view_attempt'] ?? null);
$view_quiz_id = sanitize_input($_GET['view_quiz'] ?? null); // New: To view results for a specific quiz

try {
    // Fetch lists of users (only students) and assessments for filter dropdowns
    $all_users = $pdo->query("SELECT user_id, username, email, role FROM users WHERE role = 'student' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    $all_assessments_for_filters = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($view_attempt_id) {
        // --- Detailed View of a Single Assessment Attempt ---
        $stmt = $pdo->prepare("
            SELECT
                qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                u.username, u.email, q.title as quiz_title, q.duration_minutes, q.quiz_id
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
    } elseif ($view_quiz_id) {
        // --- View All Attempts for a Specific Quiz ---
        $stmt_quiz = $pdo->prepare("SELECT quiz_id, title FROM quizzes WHERE quiz_id = :quiz_id");
        $stmt_quiz->execute(['quiz_id' => $view_quiz_id]);
        $current_quiz = $stmt_quiz->fetch(PDO::FETCH_ASSOC);

        if (!$current_quiz) {
            $message = display_message("Assessment not found.", "error");
            $view_quiz_id = null; // Reset to show all assessments
        } else {
            $sql = "
                SELECT
                    qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                    u.username, q.title as quiz_title, q.quiz_id, u.user_id
                FROM quiz_attempts qa
                JOIN users u ON qa.user_id = u.user_id
                JOIN quizzes q ON qa.quiz_id = q.quiz_id
                WHERE qa.quiz_id = :quiz_id
            ";
            $params = ['quiz_id' => $view_quiz_id];
            $where_clauses = [];

            if ($filter_user_id_detail) {
                $where_clauses[] = "u.user_id = :user_id";
                $params['user_id'] = $filter_user_id_detail;
            }
            if ($filter_completion_status !== null && $filter_completion_status !== '') {
                $where_clauses[] = "qa.is_completed = :is_completed";
                $params['is_completed'] = (int)$filter_completion_status;
            }
            if ($filter_score_min !== null && $filter_score_min !== '') {
                $where_clauses[] = "qa.score >= :score_min";
                $params['score_min'] = (float)$filter_score_min;
            }
            if ($filter_score_max !== null && $filter_score_max !== '') {
                $where_clauses[] = "qa.score <= :score_max";
                $params['score_max'] = (float)$filter_score_max;
            }

            if (!empty($where_clauses)) {
                $sql .= " AND " . implode(" AND ", $where_clauses);
            }

            // Apply sorting, with score DESC as default if no sort parameters are set
            if (!isset($_GET['sort_by']) && !isset($_GET['sort_order'])) {
                 $sql .= " ORDER BY qa.score DESC"; // Default sort by score descending
            } else {
                $sql .= " ORDER BY {$sort_by} {$sort_order}";
            }


            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Calculate Summary Metrics for the specific quiz ---
            $total_attempts = count($results);
            $completed_attempts = 0;
            $passed_attempts = 0;
            $failed_attempts = 0;
            $total_score_sum = 0;
            $attempts_with_proctoring_violations = 0;

            $passing_percentage = 70; // 70% to pass

            // Fetch max possible score for the current quiz
            $stmt_max_quiz_score = $pdo->prepare("SELECT SUM(score) FROM questions WHERE quiz_id = :quiz_id");
            $stmt_max_quiz_score->execute(['quiz_id' => $view_quiz_id]);
            $max_possible_quiz_score = $stmt_max_quiz_score->fetchColumn();

            foreach ($results as $result) {
                if ($result['is_completed']) {
                    $completed_attempts++;
                    $total_score_sum += $result['score'];

                    if ($max_possible_quiz_score > 0) {
                        $percentage_score = ($result['score'] / $max_possible_quiz_score) * 100;
                        if ($percentage_score >= $passing_percentage) {
                            $passed_attempts++;
                        } else {
                            $failed_attempts++;
                        }
                    } else {
                        $failed_attempts++; // If max score is 0, or no questions, consider it failed
                    }

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
                'passing_percentage_threshold' => $passing_percentage,
                'max_possible_quiz_score' => $max_possible_quiz_score
            ];
        }
    } else {
        // --- Default View: List All Assessments ---
        $sql = "
            SELECT
                q.quiz_id, q.title, COUNT(qa.attempt_id) as total_attempts,
                AVG(CASE WHEN qa.is_completed = 1 THEN qa.score END) as avg_score,
                SUM(CASE WHEN qa.is_completed = 1 THEN 1 ELSE 0 END) as completed_attempts_count,
                SUM(CASE WHEN pl.event_type = 'critical_error' THEN 1 ELSE 0 END) as violation_count
            FROM quizzes q
            LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
            LEFT JOIN proctoring_logs pl ON qa.attempt_id = pl.attempt_id
        ";
        $params = [];
        $where_clauses = [];

        if ($filter_assessment_id_main) {
            $where_clauses[] = "q.quiz_id = :quiz_id";
            $params['quiz_id'] = $filter_assessment_id_main;
        }

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $sql .= " GROUP BY q.quiz_id, q.title ORDER BY q.title ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch max scores for each quiz for accurate calculations
        foreach ($assessments as &$assessment) {
            $stmt_max_score_overall = $pdo->prepare("SELECT SUM(score) FROM questions WHERE quiz_id = :quiz_id");
            $stmt_max_score_overall->execute(['quiz_id' => $assessment['quiz_id']]);
            $assessment['max_possible_score'] = $stmt_max_score_overall->fetchColumn() ?: 1; // Avoid division by zero

            // Calculate average score as a percentage
            if ($assessment['avg_score'] !== null && $assessment['max_possible_score'] > 0) {
                $assessment['avg_score_percentage'] = round(($assessment['avg_score'] / $assessment['max_possible_score']) * 100, 2);
            } else {
                $assessment['avg_score_percentage'] = 0;
            }
        }
        unset($assessment); // Break the reference
    }
} catch (PDOException $e) {
    error_log("View Results Error: " . $e->getMessage());
    $message = display_message("Could not fetch results. Please try again later.", "error");
}

?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-4xl font-extrabold text-theme-color mb-8 text-center">Assessment Results Dashboard</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if ($view_attempt_id && isset($current_attempt)): ?>
        <div class="mb-6 text-left">
            <a href="view_results.php?view_quiz=<?php echo htmlspecialchars($current_attempt['quiz_id']); ?>" class="inline-block bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition duration-300 text-sm font-semibold shadow-sm">
                &larr; Back to Assessment Attempts
            </a>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-lg mb-8 border border-gray-200">
            <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
                Attempt Details for <span class="text-theme-color"><?php echo htmlspecialchars($current_attempt['quiz_title']); ?></span> by <span class="text-theme-color"><?php echo htmlspecialchars($current_attempt['username']); ?></span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 text-gray-700 mb-8 border-b pb-6">
                <div class="bg-blue-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
                    <p class="font-semibold text-lg">Attempt ID:</p>
                    <span class="text-blue-800 text-lg font-mono"><?php echo htmlspecialchars($current_attempt['attempt_id']); ?></span>
                </div>
                <div class="bg-yellow-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
                    <p class="font-semibold text-lg">Started:</p>
                    <span class="text-yellow-800 text-lg"><?php echo date('h:i A, j F, Y', strtotime($current_attempt['start_time'])); ?></span>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
                    <p class="font-semibold text-lg">Completed:</p>
                    <span class="text-purple-800 text-lg"><?php echo $current_attempt['end_time'] ? date('h:i A, j F, Y', strtotime($current_attempt['end_time'])) : '<span class="text-red-500">In Progress</span>'; ?></span>
                </div>
                <div class="bg-green-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
                    <p class="font-semibold text-lg">Status:</p>
                    <span class="text-green-800 text-lg font-bold"><?php echo $current_attempt['is_completed'] ? 'Completed' : 'In Progress'; ?></span>
                </div>
                <div class="bg-indigo-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
                    <p class="font-semibold text-lg">Score:</p>
                    <span class="text-indigo-800 text-2xl font-bold"><?php echo htmlspecialchars($current_attempt['score'] ?? 'N/A'); ?></span>
                </div>
                <div class="bg-pink-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
                    <p class="font-semibold text-lg">User Email:</p>
                    <span class="text-pink-800 text-lg"><?php echo htmlspecialchars($current_attempt['email'] ?? 'N/A'); ?></span>
                </div>
                 <div class="bg-teal-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
                    <p class="font-semibold text-lg">Assessment Duration:</p>
                    <span class="text-teal-800 text-lg"><?php echo htmlspecialchars($current_attempt['duration_minutes'] ? $current_attempt['duration_minutes'] . ' minutes' : 'No Limit'); ?></span>
                </div>
            </div>

            <h3 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-3">Questions and Answers Review</h3>
            <?php if (!empty($current_attempt['answers'])): ?>
                <?php foreach ($current_attempt['answers'] as $answer): ?>
                    <div class="border border-gray-200 p-5 rounded-lg bg-gray-50 mb-6 shadow-sm hover:shadow-md transition-shadow duration-200">
                        <p class="font-bold text-lg text-gray-900 mb-2">Q: <?php echo htmlspecialchars($answer['question_text']); ?> <span class="text-gray-600">(Score: <?php echo htmlspecialchars($answer['question_score']); ?> points)</span></p>
                        <p class="text-sm text-gray-500 mb-3">Type: <span class="font-semibold"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $answer['question_type']))); ?></span></p>
                        <?php if ($answer['question_type'] === 'multiple_choice'): ?>
                            <?php
                                $all_options_parsed = [];
                                $selected_option_text = 'No answer selected';
                                $correct_option_texts = [];

                                if (!empty($answer['all_options_data'])) {
                                    $options_raw = explode(';;', $answer['all_options_data']);
                                    foreach ($options_raw as $opt_str) {
                                        @list($opt_id, $opt_text, $is_correct_val) = explode('||', $opt_str, 3); // Limit to 3 to handle '||' in option_text
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
                            <div class="mb-2">
                                <p class="font-semibold text-gray-700">Available Options:</p>
                                <ul class="list-disc ml-6 text-sm text-gray-700">
                                    <?php foreach($all_options_parsed as $option): ?>
                                        <li class="<?php
                                            if ((int)$option['id'] === (int)$answer['selected_option_id']) {
                                                echo $answer['is_correct'] ? 'text-green-700 font-bold' : 'text-red-700 font-bold';
                                            } elseif ($option['is_correct']) {
                                                echo 'text-green-600'; // Correct option not selected
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($option['text']); ?>
                                            <?php if ((int)$option['id'] === (int)$answer['selected_option_id']) echo ' <span class="text-blue-500">(Selected)</span>'; ?>
                                            <?php if ($option['is_correct']) echo ' <span class="text-green-500">(Correct Answer)</span>'; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <p class="mt-3"><strong>Your Answer:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'; ?>"><?php echo htmlspecialchars($selected_option_text); ?></span></p>
                            <p><strong>Correct Answer:</strong> <span class="text-green-600 font-semibold"><?php echo htmlspecialchars(implode(', ', $correct_option_texts)); ?></span></p>
                            <p><strong>Result:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600 font-bold' : 'text-red-600 font-bold'; ?>"><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></span></p>
                        <?php elseif ($answer['question_type'] === 'true_false' || $answer['question_type'] === 'short_answer'): ?>
                            <p><strong>Student's Answer:</strong> <span class="font-semibold"><?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?></span></p>
                            <?php
                                $correct_answer_text = 'N/A';
                                if ($answer['question_type'] === 'true_false') {
                                    $stmt_tf_correct = $pdo->prepare("SELECT option_text FROM options WHERE question_id = :qid AND is_correct = 1 LIMIT 1");
                                    $stmt_tf_correct->execute(['qid' => $answer['question_id']]);
                                    $correct_answer_text = $stmt_tf_correct->fetchColumn() ?: 'N/A';
                                }
                            ?>
                            <p><strong>Correct Answer:</strong> <span class="text-green-600 font-semibold"><?php echo ($answer['question_type'] === 'true_false') ? htmlspecialchars($correct_answer_text) : 'Manual Review Needed'; ?></span></p>
                            <p><strong>Result:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600 font-bold' : 'text-red-600 font-bold'; ?>"><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></span></p>
                        <?php elseif ($answer['question_type'] === 'essay'): ?>
                            <p><strong>Student's Answer:</strong></p>
                            <div class="bg-gray-100 p-3 rounded-md border border-gray-300 text-gray-700 whitespace-pre-wrap text-sm max-h-40 overflow-y-auto custom-scrollbar">
                                <?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?>
                            </div>
                            <p class="text-sm text-gray-500 mt-2 italic">Requires manual grading.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-600 italic">No answers recorded for this attempt yet or attempt is incomplete.</p>
            <?php endif; ?>

            <h3 class="text-2xl font-bold text-gray-800 mb-4 mt-8 border-b pb-3">Proctoring Logs</h3>
            <?php if (!empty($proctoring_logs)): ?>
                <div class="overflow-x-auto shadow-md rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Event Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/2">Details</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($proctoring_logs as $log): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('h:i A, j F, Y', strtotime($log['log_time'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 <?php echo ($log['event_type'] === 'critical_error') ? 'font-bold text-red-600' : ''; ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['event_type']))); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 break-words max-w-md">
                                        <?php
                                            $log_data = $log['log_data'];
                                            $decoded_data = json_decode($log_data, true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data)) {
                                                echo '<pre class="bg-gray-100 p-2 rounded text-xs overflow-auto max-h-24 custom-scrollbar">' . htmlspecialchars(json_encode($decoded_data, JSON_PRETTY_PRINT)) . '</pre>';
                                            } else {
                                                echo htmlspecialchars($log_data);
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600 italic">No proctoring logs available for this attempt.</p>
            <?php endif; ?>
        </div>

    <?php elseif ($view_quiz_id && isset($current_quiz)): ?>
        <div class="mb-6 text-left">
            <a href="view_results.php" class="inline-block bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition duration-300 text-sm font-semibold shadow-sm">
                &larr; Back to All Assessments
            </a>
        </div>
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
            Results for Assessment: <span class="text-theme-color"><?php echo htmlspecialchars($current_quiz['title']); ?></span>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center border border-blue-100">
                <h3 class="text-xl font-semibold text-gray-800">Total Attempts</h3>
                <p class="text-4xl font-bold text-blue-600 mt-3"><?php echo htmlspecialchars($summary_metrics['total_attempts']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center border border-green-100">
                <h3 class="text-xl font-semibold text-gray-800">Completed</h3>
                <p class="text-4xl font-bold text-green-600 mt-3"><?php echo htmlspecialchars($summary_metrics['completed_attempts']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center border border-purple-100">
                <h3 class="text-xl font-semibold text-gray-800">Passed (>= <?php echo $summary_metrics['passing_percentage_threshold']; ?>%)</h3>
                <p class="text-4xl font-bold text-purple-600 mt-3"><?php echo htmlspecialchars($summary_metrics['passed_attempts']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center border border-red-100">
                <h3 class="text-xl font-semibold text-gray-800">Failed (< <?php echo $summary_metrics['passing_percentage_threshold']; ?>%)</h3>
                <p class="text-4xl font-bold text-red-600 mt-3"><?php echo htmlspecialchars($summary_metrics['failed_attempts']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center border border-teal-100">
                <h3 class="text-xl font-semibold text-gray-800">Average Score</h3>
                <p class="text-4xl font-bold text-teal-600 mt-3"><?php echo htmlspecialchars($summary_metrics['average_score']); ?>%</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center border border-orange-100">
                <h3 class="text-xl font-semibold text-gray-800">Proctoring Violations</h3>
                <p class="text-4xl font-bold text-orange-600 mt-3"><?php echo htmlspecialchars($summary_metrics['attempts_with_proctoring_violations']); ?></p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg mb-8 border border-gray-200">
            <h3 class="text-2xl font-semibold text-gray-800 mb-5 border-b pb-3">Filter & Sort Attempts</h3>
            <form action="view_results.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <input type="hidden" name="view_quiz" value="<?php echo htmlspecialchars($view_quiz_id); ?>">
                <div>
                    <label for="filter_user_detail" class="block text-gray-700 text-sm font-semibold mb-2">Filter by Student:</label>
                    <select id="filter_user_detail" name="user_id_detail"
                            class="form-select block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50">
                        <option value="">All Students</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['user_id']); ?>"
                                <?php echo ($filter_user_id_detail == $user['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_completion_status" class="block text-gray-700 text-sm font-semibold mb-2">Completion Status:</label>
                    <select id="filter_completion_status" name="completion_status"
                            class="form-select block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50">
                        <option value="">All Statuses</option>
                        <option value="1" <?php echo ($filter_completion_status === '1') ? 'selected' : ''; ?>>Completed</option>
                        <option value="0" <?php echo ($filter_completion_status === '0') ? 'selected' : ''; ?>>In Progress</option>
                    </select>
                </div>
                <div>
                    <label for="filter_score_min" class="block text-gray-700 text-sm font-semibold mb-2">Min Score:</label>
                    <input type="number" id="filter_score_min" name="score_min" value="<?php echo htmlspecialchars($filter_score_min); ?>"
                           class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50" placeholder="e.g., 50">
                </div>
                <div>
                    <label for="filter_score_max" class="block text-gray-700 text-sm font-semibold mb-2">Max Score:</label>
                    <input type="number" id="filter_score_max" name="score_max" value="<?php echo htmlspecialchars($filter_score_max); ?>"
                           class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50" placeholder="e.g., 100">
                </div>
                <div class="col-span-1 md:col-span-2 lg:col-span-4 flex justify-end mt-4">
                    <button type="submit"
                            class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-6 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75 transition duration-300 shadow-md">
                        Apply Filters & Sort
                    </button>
                    <a href="view_results.php?view_quiz=<?php echo htmlspecialchars($view_quiz_id); ?>" class="ml-4 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg transition duration-300 shadow-md">Reset</a>
                </div>
            </form>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
            <h3 class="text-2xl font-semibold text-gray-800 mb-4">Attempt List</h3>
            <?php if (empty($results)): ?>
                <p class="text-gray-600 italic text-center py-4">No assessment attempts found for this quiz matching the criteria.</p>
            <?php else: ?>
                <div class="overflow-x-auto"> <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <?php
                                $headers_detail = [
                                    'attempt_id' => 'Attempt ID',
                                    'username' => 'Student',
                                    'score' => 'Score',
                                    'start_time' => 'Start Time',
                                    'end_time' => 'End Time',
                                    'is_completed' => 'Status'
                                ];
                                foreach ($headers_detail as $col_name => $display_name):
                                    $new_sort_order = ($sort_by === $col_name && $sort_order === 'ASC') ? 'DESC' : 'ASC';
                                    $arrow = ($sort_by === $col_name) ? ($sort_order === 'ASC' ? ' &uarr;' : ' &darr;') : '';
                                    $current_filters = http_build_query([
                                        'view_quiz' => $view_quiz_id,
                                        'user_id_detail' => $filter_user_id_detail,
                                        'completion_status' => $filter_completion_status,
                                        'score_min' => $filter_score_min,
                                        'score_max' => $filter_score_max
                                    ]);
                                ?>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="?<?php echo htmlspecialchars($current_filters); ?>&sort_by=<?php echo htmlspecialchars($col_name); ?>&sort_order=<?php echo htmlspecialchars($new_sort_order); ?>" class="hover:text-gray-700 flex items-center">
                                            <?php echo $display_name . $arrow; ?>
                                        </a>
                                    </th>
                                <?php endforeach; ?>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($result['attempt_id']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($result['username']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php
                                            $display_score = 'N/A';
                                            if ($result['is_completed']) {
                                                $display_score = htmlspecialchars($result['score']);
                                                if ($summary_metrics['max_possible_quiz_score'] > 0) {
                                                    $percentage_score = round(($result['score'] / $summary_metrics['max_possible_quiz_score']) * 100, 2);
                                                    $display_score .= " (" . $percentage_score . "%)";
                                                }
                                            }
                                            echo $display_score;
                                        ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo date('M j, Y H:i', strtotime($result['start_time'])); ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo $result['end_time'] ? date('M j, Y H:i', strtotime($result['end_time'])) : '<span class="text-gray-500 italic">In Progress</span>'; ?></td>
                                    <td class="px-4 py-4 text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $result['is_completed'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo $result['is_completed'] ? 'Completed' : 'In Progress'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm font-medium">
                                        <a href="view_results.php?view_attempt=<?php echo htmlspecialchars($result['attempt_id']); ?>"
                                           class="text-blue-600 hover:text-blue-800 transition duration-150 ease-in-out">Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="bg-white p-6 rounded-xl shadow-lg mb-8 border border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800 mb-5 border-b pb-3">Filter Assessments</h2>
            <form action="view_results.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label for="filter_assessment_main" class="block text-gray-700 text-sm font-semibold mb-2">Select Assessment:</label>
                    <select id="filter_assessment_main" name="assessment_id_main"
                            class="form-select block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-500 focus:ring-opacity-50">
                        <option value="">All Assessments</option>
                        <?php foreach ($all_assessments_for_filters as $assessment_filter): ?>
                            <option value="<?php echo htmlspecialchars($assessment_filter['quiz_id']); ?>"
                                <?php echo ($filter_assessment_id_main == $assessment_filter['quiz_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($assessment_filter['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end mt-4">
                    <button type="submit"
                            class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-6 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75 transition duration-300 shadow-md">
                        Apply Filter
                    </button>
                    <a href="view_results.php" class="ml-4 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg transition duration-300 shadow-md">Reset</a>
                </div>
            </form>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Overview of All Assessments</h2>
            <?php if (empty($assessments)): ?>
                <p class="text-gray-600 italic text-center py-4">No assessments found.</p>
            <?php else: ?>
                <div class="overflow-x-auto"> <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Title</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Attempts</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Score</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Violations</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($assessments as $assessment): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($assessment['title']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($assessment['total_attempts']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($assessment['completed_attempts_count']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($assessment['avg_score_percentage']) . '%'; ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 <?php echo ($assessment['violation_count'] > 0) ? 'font-bold text-red-600' : ''; ?>">
                                        <?php echo htmlspecialchars($assessment['violation_count']); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="view_results.php?view_quiz=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                                           class="text-indigo-600 hover:text-indigo-900 transition duration-150 ease-in-out">View Attempts</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>