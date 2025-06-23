<?php
// student/view_history.php
// Allows students to view their quiz history and detailed results for specific attempts.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$user_id = getUserId(); // Get the ID of the currently logged-in student
$attempts = []; // Array to hold fetched quiz attempts for overview
$current_attempt = null; // Object to hold detailed data for a single attempt view
$proctoring_logs = []; // Array to hold fetched proctoring logs for a specific attempt

// Sanitize the input attempt_id from the GET request
$view_attempt_id = sanitize_input($_GET['attempt_id'] ?? null);

try {
    if ($view_attempt_id) {
        // Fetch detailed results and proctoring logs for a specific attempt for the current student
        $stmt = $pdo->prepare("
            SELECT
                qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                q.title as quiz_title, q.description as quiz_description
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.quiz_id
            WHERE qa.attempt_id = :attempt_id AND qa.user_id = :user_id
        ");
        $stmt->execute(['attempt_id' => $view_attempt_id, 'user_id' => $user_id]);
        $current_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current_attempt) {
            // Fetch answers for this attempt
            // GROUP_CONCAT is used to aggregate correct options for multiple choice questions
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
            $message = display_message("Attempt not found or you do not have permission to view it.", "error");
        }

    } else {
        // Fetch overview of all quiz attempts for the current student
        $stmt = $pdo->prepare("
            SELECT
                qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                q.title as quiz_title
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.quiz_id
            WHERE qa.user_id = :user_id
            ORDER BY qa.start_time DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Student View History Error: " . $e->getMessage());
    $message = display_message("An error occurred while fetching your quiz history. Please try again later.", "error");
}

?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Your Quiz History</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if ($view_attempt_id && isset($current_attempt)): ?>
        <!-- Detailed View of a Single Quiz Attempt -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Details for "<?php echo htmlspecialchars($current_attempt['quiz_title']); ?>"</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700 mb-6">
                <p><strong>Attempt ID:</strong> <?php echo htmlspecialchars($current_attempt['attempt_id']); ?></p>
                <!-- Format start_time to "7:35 PM, June 20, 2025" -->
                <p><strong>Started:</strong> <?php echo date('g:i A, F j, Y', strtotime($current_attempt['start_time'])); ?></p>
                <!-- Format end_time or display "Cancelled" -->
                <p><strong>Completed:</strong> <?php echo $current_attempt['end_time'] ? date('g:i A, F j, Y', strtotime($current_attempt['end_time'])) : 'Cancelled'; ?></p>
                <!-- Show 'Completed' if is_completed is true, otherwise 'Cancelled' -->
                <p><strong>Status:</strong> <?php echo $current_attempt['is_completed'] ? 'Completed' : 'Cancelled'; ?></p>
                <p><strong>Your Score:</strong> <?php echo htmlspecialchars($current_attempt['score'] ?? 'N/A'); ?></p>
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
                                        list($opt_text, $is_correct_val) = explode('||', $opt_str);
                                        if ((bool)$is_correct_val) {
                                            $correct_options_parsed[] = $opt_text;
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
                <p class="text-gray-600">No answers recorded for this attempt yet.</p>
            <?php endif; ?>

            <h3 class="text-xl font-semibold text-gray-700 mb-3 mt-6">Proctoring Activity During This Attempt</h3>
            <?php if (!empty($proctoring_logs)): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-200">
                        <?php foreach ($proctoring_logs as $log): ?>
                        <tr>
                            <!-- Format log_time to "7:35 PM, June 20, 2025" -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('g:i A, F j, Y', strtotime($log['log_time'])); ?></td>
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
                <p class="text-sm text-gray-500 mt-4"><em>Proctoring logs are for administrator review and indicate activities during the exam.</em></p>
            <?php else: ?>
                <p class="text-gray-600">No proctoring logs recorded for this attempt.</p>
            <?php endif; ?>

            <div class="mt-8">
                <a href="view_history.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                    &larr; Back to All Attempts
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- Overview of All Quiz Attempts for the Student -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Your Recent Attempts</h2>
            <?php if (empty($attempts)): ?>
                <p class="text-gray-600">You have not completed any quizzes yet.</p>
                <div class="mt-4 text-center">
                    <a href="dashboard.php" class="inline-block bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-300">
                        Explore Available Quizzes
                    </a>
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($attempt['score'] ?? 'N/A'); ?></td>
                            <!-- Format start_time to "7:35 PM, June 20, 2025" -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('g:i A, F j, Y', strtotime($attempt['start_time'])); ?></td>
                            <!-- Format end_time or display "Cancelled" -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $attempt['end_time'] ? date('g:i A, F j, Y', strtotime($attempt['end_time'])) : 'Cancelled'; ?></td>
                            <!-- Show 'Completed' if is_completed is true, otherwise 'Cancelled' -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $attempt['is_completed'] ? 'Completed' : 'Cancelled'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="view_history.php?attempt_id=<?php echo htmlspecialchars($attempt['attempt_id']); ?>"
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
