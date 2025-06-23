<?php
// student/dashboard.php
// The main dashboard page for students.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$available_assessments = [];
$all_previous_attempts = []; // Renamed from $recent_attempts
$attempts_summary_per_quiz = []; // New variable for total attempts per quiz
$user_id = getUserId(); // Get the logged-in student's user_id

try {
    // Fetch assessments available to the student (not public, or assessments assigned/relevant)
    $stmt = $pdo->prepare("
        SELECT quiz_id, title, description, max_attempts, duration_minutes
        FROM quizzes
        WHERE is_public = 0 OR is_public = 1
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $all_assessments_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter assessments to show only those a student hasn't exceeded attempts on
    $available_assessments_filtered = [];
    foreach ($all_assessments_raw as $assessment) {
        $assessment_id = $assessment['quiz_id'];

        // Get count of attempts for this assessment by this student
        $stmt_attempts_count = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = :user_id AND quiz_id = :quiz_id");
        $stmt_attempts_count->execute(['user_id' => $user_id, 'quiz_id' => $assessment_id]);
        $attempts_taken = $stmt_attempts_count->fetchColumn();

        if ($assessment['max_attempts'] === 0 || $attempts_taken < $assessment['max_attempts']) {
            $assessment['attempts_taken'] = $attempts_taken;
            $available_assessments_filtered[] = $assessment;
        }
    }
    $available_assessments = $available_assessments_filtered;

    // Fetch ALL previous assessment attempts by the current student
    $stmt = $pdo->prepare("
        SELECT qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed, q.title as quiz_title, q.quiz_id
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.quiz_id
        WHERE qa.user_id = :user_id
        ORDER BY qa.start_time DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $all_previous_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total attempts per assessment for the summary card
    foreach ($all_previous_attempts as $attempt) {
        $quiz_id = $attempt['quiz_id'];
        $quiz_title = $attempt['quiz_title'];

        if (!isset($attempts_summary_per_quiz[$quiz_id])) {
            $attempts_summary_per_quiz[$quiz_id] = [
                'title' => $quiz_title,
                'total_attempts' => 0,
                'completed_attempts' => 0,
                'total_score_sum' => 0,
                'max_score' => 0, // Will be fetched
                'passed_attempts' => 0,
                'failed_attempts' => 0,
            ];
        }
        $attempts_summary_per_quiz[$quiz_id]['total_attempts']++;

        if ($attempt['is_completed']) {
            $attempts_summary_per_quiz[$quiz_id]['completed_attempts']++;
            $attempts_summary_per_quiz[$quiz_id]['total_score_sum'] += $attempt['score'];

            // Fetch max possible score for this specific quiz (if not already fetched)
            if ($attempts_summary_per_quiz[$quiz_id]['max_score'] === 0) {
                $stmt_max_score = $pdo->prepare("SELECT SUM(score) FROM questions WHERE quiz_id = :quiz_id");
                $stmt_max_score->execute(['quiz_id' => $quiz_id]);
                $attempts_summary_per_quiz[$quiz_id]['max_score'] = $stmt_max_score->fetchColumn();
            }

            // Determine pass/fail for completed attempts
            $max_possible_score = $attempts_summary_per_quiz[$quiz_id]['max_score'];
            $passing_percentage = 70; // Assuming a passing percentage

            if ($max_possible_score > 0) {
                $percentage_score = ($attempt['score'] / $max_possible_score) * 100;
                if ($percentage_score >= $passing_percentage) {
                    $attempts_summary_per_quiz[$quiz_id]['passed_attempts']++;
                } else {
                    $attempts_summary_per_quiz[$quiz_id]['failed_attempts']++;
                }
            } else {
                // If quiz has no questions or max score is 0, consider it failed for dashboard metric
                $attempts_summary_per_quiz[$quiz_id]['failed_attempts']++;
            }
        }
    }

} catch (PDOException $e) {
    error_log("Student Dashboard Data Fetch Error: " . $e->getMessage());
    $message = display_message("Could not load dashboard data. Please try again later.", "error");
}

?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Student Dashboard</h1>

    <?php echo $message; // Display any feedback messages ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Dashboard Card 1: Available Assessments -->
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Assessments Available</h2>
                <p class="text-3xl font-bold text-theme-color mt-2"><?php echo htmlspecialchars(count($available_assessments)); ?></p>
            </div>
            <div class="text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
            </div>
        </div>

        <!-- Dashboard Card 2: Total Completed Assessments -->
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Total Completed Assessments</h2>
                <p class="text-3xl font-bold text-green-600 mt-2"><?php
                    $total_completed = 0;
                    foreach ($attempts_summary_per_quiz as $summary) {
                        $total_completed += $summary['completed_attempts'];
                    }
                    echo htmlspecialchars($total_completed);
                ?></p>
            </div>
            <div class="text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        
        <!-- Dashboard Card 3: Assessments Passed -->
        <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
            <h2 class="text-xl font-semibold text-gray-800">Assessments Passed</h2>
            <p class="text-3xl font-bold text-blue-600 mt-2"><?php
                $total_passed = 0;
                foreach ($attempts_summary_per_quiz as $summary) {
                    $total_passed += $summary['passed_attempts'];
                }
                echo htmlspecialchars($total_passed);
            ?></p>
            <div class="mt-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
        </div>
    </div>

    <h2 class="text-2xl font-bold text-theme-color mb-4">Assessments Available to You</h2>
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto mb-8">
        <?php if (empty($available_assessments)): ?>
            <p class="text-gray-600">No assessments are currently available to you.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts Left</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($available_assessments as $assessment): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['title']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900 max-w-xs overflow-hidden text-ellipsis"><?php echo htmlspecialchars($assessment['description']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php
                                if ($assessment['max_attempts'] == 0) {
                                    echo "Unlimited";
                                } else {
                                    echo ($assessment['max_attempts'] - $assessment['attempts_taken']) . " of " . htmlspecialchars($assessment['max_attempts']);
                                }
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['duration_minutes'] ?: 'No Limit'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="<?php echo BASE_URL; ?>student/take_quiz.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                               class="text-green-600 hover:text-green-900">Start Assessment</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <h2 class="text-2xl font-bold text-theme-color mb-4">Your Previous Assessment Results</h2>
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <?php if (empty($all_previous_attempts)): ?>
            <p class="text-gray-600">You have not completed any assessments yet.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($all_previous_attempts as $attempt): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($attempt['score'] ?? 'N/A'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo date('h:i A, j F, Y', strtotime($attempt['start_time'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $attempt['end_time'] ? date('h:i A, j F, Y', strtotime($attempt['end_time'])) : 'N/A'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php
                                // Logic for status: If completed is 1, then "Completed". Otherwise "Cancelled".
                                echo $attempt['is_completed'] ? 'Completed' : 'Cancelled';
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="<?php echo BASE_URL; ?>student/view_history.php?attempt_id=<?php echo htmlspecialchars($attempt['attempt_id']); ?>"
                               class="text-blue-600 hover:text-blue-900">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-4 text-center">
                <a href="<?php echo BASE_URL; ?>student/view_history.php" class="inline-block bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 transition duration-300">
                    View All History &rarr;
                </a>
            </div>
        <?php endif; ?>
    </div>

    <h2 class="text-2xl font-bold text-theme-color mb-4">Overall Assessment Statistics</h2>
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <?php if (empty($attempts_summary_per_quiz)): ?>
            <p class="text-gray-600">No assessment statistics available yet.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Attempts</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed Attempts</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passed</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Score</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($attempts_summary_per_quiz as $quiz_id => $summary): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['title']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['total_attempts']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['completed_attempts']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['passed_attempts']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['failed_attempts']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $summary['completed_attempts'] > 0 ? round(($summary['total_score_sum'] / $summary['completed_attempts']), 2) : 'N/A'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>
