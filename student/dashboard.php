<?php
// student/dashboard.php
// The main dashboard page for students.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Contains format_datetime() and display_message()

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$all_assessments_for_display = []; // Will hold all assessments with their status
$all_previous_attempts = []; // All attempts, to be sliced for display
$attempts_summary_per_quiz = []; // Summary of attempts per quiz for dashboard cards
$user_id = getUserId(); // Get the logged-in student's user_id

// Fetch the logged-in user's name
$logged_in_username = htmlspecialchars($_SESSION['username'] ?? 'Student'); // Default to 'Student' if not set

// --- START: Verification Check Integration ---
$verification_completed = false;
$current_passport_image = '';
$current_city = '';
$current_state = '';
$current_country = '';

try {
    $stmt = $pdo->prepare("SELECT passport_image_path, city, state, country FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user_verification_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_verification_data) {
        $current_passport_image = $user_verification_data['passport_image_path'] ?? '';
        $current_city = $user_verification_data['city'] ?? '';
        $current_state = $user_verification_data['state'] ?? '';
        $current_country = $user_verification_data['country'] ?? '';

        if (!empty($current_passport_image) && !empty($current_city) && !empty($current_state) && !empty($current_country)) {
            $verification_completed = true;
        }
    }
} catch (PDOException $e) {
    error_log("Student Dashboard Verification Check Error: " . $e->getMessage());
    $message = display_message("Could not check verification status. Please try again later.", "error");
}
// --- END: Verification Check Integration ---

// --- START: Payment Check Integration ---
$paid_assessments = []; // To store quizzes for which the student has paid

try {
    // Fetch all quizzes and their fees
    $stmt = $pdo->prepare("SELECT quiz_id, assessment_fee FROM quizzes");
    $stmt->execute();
    $all_quiz_fees = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // quiz_id => assessment_fee

    // Fetch all completed payments for the current user
    $stmt_payments = $pdo->prepare("SELECT quiz_id FROM payments WHERE user_id = :user_id AND status = 'completed'");
    $stmt_payments->execute(['user_id' => $user_id]);
    $user_payments_raw = $stmt_payments->fetchAll(PDO::FETCH_COLUMN);
    $user_paid_quiz_ids = array_flip($user_payments_raw); // For quick lookup

    // Determine which assessments are 'paid' (have a fee and payment is completed)
    foreach ($all_quiz_fees as $quiz_id => $fee) {
        if ($fee > 0 && isset($user_paid_quiz_ids[$quiz_id])) {
            $paid_assessments[$quiz_id] = true; // Mark as paid
        } elseif ($fee == 0 || $fee === null) { // Quizzes with no fee (or NULL fee) are considered 'paid'
            $paid_assessments[$quiz_id] = true;
        } else {
            $paid_assessments[$quiz_id] = false; // Fee exists but not paid
        }
    }
} catch (PDOException $e) {
    error_log("Student Dashboard Payment Check Error: " . $e->getMessage());
    $message = display_message("Could not check payment status. Please try again later.", "error");
}
// --- END: Payment Check Integration ---

// Store max possible scores for all quizzes to use in percentage calculation
$max_scores_per_quiz = [];

try {
    // Fetch ALL assessments
    $stmt = $pdo->prepare("
        SELECT quiz_id, title, description, max_attempts, duration_minutes, assessment_fee
        FROM quizzes
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $all_assessments_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_assessments_raw as $assessment) {
        $assessment_id = $assessment['quiz_id'];

        // Get count of attempts for this assessment by this student
        $stmt_attempts_count = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = :user_id AND quiz_id = :quiz_id");
        $stmt_attempts_count->execute(['user_id' => $user_id, 'quiz_id' => $assessment_id]);
        $attempts_taken = $stmt_attempts_count->fetchColumn();

        $assessment['attempts_taken'] = $attempts_taken;
        $assessment['is_paid'] = $paid_assessments[$assessment_id] ?? false;

        $all_assessments_for_display[] = $assessment;
    }

    // Fetch ALL previous assessment attempts by the current student
    // Also fetch max_score for each quiz for percentage calculation
    $stmt = $pdo->prepare("
        SELECT
            qa.attempt_id,
            qa.score,
            qa.start_time,
            qa.end_time,
            qa.is_completed,
            q.title as quiz_title,
            q.quiz_id,
            (SELECT SUM(score) FROM questions WHERE quiz_id = q.quiz_id) as max_possible_score
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.quiz_id
        WHERE qa.user_id = :user_id
        ORDER BY qa.start_time DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $all_previous_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Populate max_scores_per_quiz for later use
    foreach ($all_previous_attempts as $attempt) {
        $max_scores_per_quiz[$attempt['quiz_id']] = $attempt['max_possible_score'];
    }

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
                'max_score' => $attempt['max_possible_score'], // Already fetched
                'passed_attempts' => 0,
                'failed_attempts' => 0,
            ];
        }
        $attempts_summary_per_quiz[$quiz_id]['total_attempts']++;

        if ($attempt['is_completed']) {
            $attempts_summary_per_quiz[$quiz_id]['completed_attempts']++;
            $attempts_summary_per_quiz[$quiz_id]['total_score_sum'] += $attempt['score'];

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
   <h1 class="text-3xl font-bold text-accent mb-6">Welcome, <?php echo ucfirst($logged_in_username); ?>!</h1>

    <?php echo $message; // Display any feedback messages ?>

    <?php if (!$verification_completed): ?>
    <div id="verificationModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold text-red-600 mb-4">Verification Required!</h2>
            <p class="text-gray-700 mb-6">
                To access assessments and other features, you must complete your profile verification by uploading a passport/ID image and providing your **City, State, and Country**.
            </p>
            <div class="flex justify-end space-x-4">
                <a href="<?php echo BASE_URL; ?>student/profile.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Go to Profile
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Assessments Available</h2>
                <p class="text-3xl font-bold text-accent mt-2"><?php echo htmlspecialchars(count($all_assessments_for_display)); ?></p>
            </div>
            <div class="text-gray-500">
                <i class="fas fa-file-alt fa-3x"></i> </div>
        </div>

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
                <i class="fas fa-check-circle fa-3x"></i> </div>
        </div>

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
                <i class="fas fa-trophy fa-3x text-gray-500"></i> </div>
        </div>
    </div>

    <h2 class="text-2xl font-bold text-accent mb-4">All Assessments</h2>
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto mb-8">
        <?php if (empty($all_assessments_for_display)): ?>
            <p class="text-gray-600">No assessments found.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status/Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($all_assessments_for_display as $assessment): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <a href="<?php echo BASE_URL; ?>student/take_quiz.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>" class="text-accent hover:text-blue-700 font-medium">
                                <?php echo htmlspecialchars($assessment['title']); ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 max-w-xs overflow-hidden text-ellipsis">
                            <?php
                            $description = htmlspecialchars($assessment['description']);
                            $short_description_limit = 15; // Number of words to show
                            $words = explode(' ', $description);

                            if (count($words) > $short_description_limit) {
                                $short_description = implode(' ', array_slice($words, 0, $short_description_limit)) . '...';
                                echo '<span id="short-desc-' . $assessment['quiz_id'] . '">' . $short_description . '</span>';
                                echo '<span id="full-desc-' . $assessment['quiz_id'] . '" style="display: none;">' . $description . '</span>';
                                echo '<button onclick="toggleDescription(' . $assessment['quiz_id'] . ')" class="text-accent hover:text-blue-700 ml-2 text-xs font-semibold bg-gray-100 px-2 py-1 rounded">Read More</button>';
                            } else {
                                echo $description;
                            }
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php
                                $attempts_left = ($assessment['max_attempts'] == 0) ? 'Unlimited' : ($assessment['max_attempts'] - $assessment['attempts_taken']);
                                $attempts_display = ($assessment['max_attempts'] == 0) ? 'Unlimited' : htmlspecialchars($assessment['attempts_taken']) . ' of ' . htmlspecialchars($assessment['max_attempts']);
                                echo $attempts_display;
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['duration_minutes'] ?: 'No Limit'); ?> mins</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php
                                echo $assessment['assessment_fee'] > 0 ? '₦' . number_format($assessment['assessment_fee'], 2) : 'Free';
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <?php
                            $can_start_assessment = true;
                            $action_link = '';
                            $action_text = '';
                            $action_class = '';
                            $action_title = '';

                            if (!$verification_completed) {
                                $can_start_assessment = false;
                                $action_text = 'Verification Required';
                                $action_class = 'text-gray-400 cursor-not-allowed';
                                $action_title = 'Please complete your profile verification to start this assessment.';
                            } elseif ($assessment['assessment_fee'] > 0 && !$assessment['is_paid']) {
                                // This is the "Pay Now" case
                                $can_start_assessment = false; // Cannot start yet, payment needed
                                $action_text = 'Pay Now (₦' . number_format($assessment['assessment_fee'], 2) . ')';
                                $action_link = BASE_URL . 'student/make_payment.php?quiz_id=' . htmlspecialchars($assessment['quiz_id']) . '&amount=' . htmlspecialchars($assessment['assessment_fee']);
                                $action_class = 'text-orange-600 hover:text-orange-800 font-bold'; // Added font-bold for better visibility
                                $action_title = 'Payment required to start this assessment.';
                            } elseif ($assessment['max_attempts'] !== 0 && $assessment['attempts_taken'] >= $assessment['max_attempts']) {
                                $can_start_assessment = false;
                                $action_text = 'Attempts Exhausted';
                                $action_class = 'text-red-600 cursor-not-allowed'; // Added cursor-not-allowed
                                $action_title = 'You have used all your attempts for this assessment.';
                            } else {
                                $action_text = 'Start Assessment';
                                $action_link = BASE_URL . 'student/take_quiz.php?quiz_id=' . htmlspecialchars($assessment['quiz_id']);
                                $action_class = 'text-green-600 hover:text-green-900 font-bold'; // Added font-bold
                            }

                            // Render the link or span based on whether an action is possible
                            if (!empty($action_link) && ($can_start_assessment || ($assessment['assessment_fee'] > 0 && !$assessment['is_paid']))) {
                                // Allow "Start Assessment" and "Pay Now" to be actual links
                                echo '<a href="' . $action_link . '" class="' . $action_class . '" title="' . $action_title . '">' . $action_text . '</a>';
                            } else {
                                // For "Verification Required" and "Attempts Exhausted", display as text
                                echo '<span class="' . $action_class . '" title="' . $action_title . '">' . $action_text . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <h2 class="text-2xl font-bold text-accent mb-4">Your Recent Assessment Results</h2>
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
                    <?php
                    $displayed_attempts_count = 0;
                    foreach ($all_previous_attempts as $attempt):
                        if ($displayed_attempts_count >= 5) break; // Limit to 5 attempts
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php
                            if ($attempt['is_completed'] && isset($max_scores_per_quiz[$attempt['quiz_id']]) && $max_scores_per_quiz[$attempt['quiz_id']] > 0) {
                                $percentage_score = ($attempt['score'] / $max_scores_per_quiz[$attempt['quiz_id']]) * 100;
                                echo htmlspecialchars(round($percentage_score, 2)) . '%';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo format_datetime($attempt['start_time']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $attempt['end_time'] ? format_datetime($attempt['end_time']) : 'N/A'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php
                                echo $attempt['is_completed'] ? 'Completed' : 'Cancelled';
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="<?php echo BASE_URL; ?>student/assessments.php?attempt_id=<?php echo htmlspecialchars($attempt['attempt_id']); ?>"
                               class="text-blue-600 hover:text-blue-900">View Details</a>
                        </td>
                    </tr>
                    <?php
                    $displayed_attempts_count++;
                    endforeach;
                    ?>
                </tbody>
            </table>
            <?php if (count($all_previous_attempts) > 5): ?>
            <div class="mt-4 text-center">
                <a href="<?php echo BASE_URL; ?>student/assessments.php" class="inline-block bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 transition duration-300">
                    View All History &rarr;
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <h2 class="text-2xl font-bold text-accent mb-4">Overall Assessment Statistics</h2>
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
                        <th scope="col" scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
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
                            <?php
                            if ($summary['completed_attempts'] > 0 && $summary['max_score'] > 0) {
                                $avg_percentage = (($summary['total_score_sum'] / $summary['completed_attempts']) / $summary['max_score']) * 100;
                                echo htmlspecialchars(round($avg_percentage, 2)) . '%';
                            } else {
                                echo 'N/A';
                            }
                            ?>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const verificationModal = document.getElementById('verificationModal');

        // Intercept clicks on 'Start Assessment' or 'Pay Now' if conditions are not met
        document.querySelectorAll('a[href*="take_quiz.php"], a[href*="make_payment.php"]').forEach(link => {
            link.addEventListener('click', function(event) {
                const isVerificationRequiredText = event.target.textContent.includes('Verification Required');
                const isAttemptsExhaustedText = event.target.textContent.includes('Attempts Exhausted');

                if (isVerificationRequiredText || isAttemptsExhaustedText) {
                    event.preventDefault();
                    const title = event.target.getAttribute('title');
                    if (title) {
                        alert(title); // Using alert as per previous pattern, consider custom modal for better UX
                    } else {
                        alert('This action is not available at this time.');
                    }
                } else if (verificationModal && verificationModal.style.display !== 'none') {
                    // If verification modal is visible and it's a 'Start Assessment' link, prevent default
                    if (event.target.href.includes('take_quiz.php')) {
                        event.preventDefault();
                        alert('Please complete your profile verification before starting any assessment.'); // Using alert
                        verificationModal.style.display = 'flex'; // Re-show modal
                    }
                    // For "Pay Now" links, no `preventDefault()` is called here, so it will proceed normally.
                }
            });
        });
    });

    // Function to toggle description visibility
    function toggleDescription(quizId) {
        const shortDesc = document.getElementById('short-desc-' + quizId);
        const fullDesc = document.getElementById('full-desc-' + quizId);
        // The button is the third sibling after shortDesc and fullDesc (which is hidden)
        const button = document.querySelector(`#short-desc-${quizId} ~ button`);

        if (fullDesc.style.display === 'none') {
            shortDesc.style.display = 'none';
            fullDesc.style.display = 'inline';
            button.textContent = 'Show Less';
        } else {
            shortDesc.style.display = 'inline';
            fullDesc.style.display = 'none';
            button.textContent = 'Read More';
        }
    }
</script>