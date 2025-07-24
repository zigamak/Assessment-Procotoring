<?php
// student/dashboard.php
// The main dashboard page for students.

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Contains format_datetime() and display_message()

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

// Fallback display_message function if not defined in functions.php
if (!function_exists('display_message')) {
    function display_message($text, $type = 'info') {
        $classes = [
            'success' => 'bg-green-100 border-green-400 text-green-700',
            'error' => 'bg-red-100 border-red-400 text-red-700',
            'info' => 'bg-blue-100 border-blue-400 text-blue-700',
        ];
        $class = $classes[$type] ?? $classes['info'];
        return "<div class='mb-4 px-4 py-3 rounded-md $class' role='alert'><strong class='font-bold'>" . ucfirst($type) . "!</strong> <span class='block sm:inline'>" . htmlspecialchars($text) . "</span></div>";
    }
}

// Debug: Check if functions.php was loaded correctly
if (!file_exists('../includes/functions.php')) {
    error_log("Functions file not found at ../includes/functions.php");
    $message = display_message("Server configuration error: Functions file missing.", "error");
} elseif (!function_exists('format_datetime')) {
    error_log("format_datetime function not defined in functions.php");
    $message = display_message("Server configuration error: Required functions missing.", "error");
} else {
    $message = ''; // Initialize message variable for feedback
}

$all_assessments_for_display = []; // Will hold all assessments with their status
$all_previous_attempts = []; // All attempts, to be sliced for display
$attempts_summary_per_quiz = []; // Summary of attempts per quiz for dashboard cards
$user_id = getUserId(); // Get the logged-in student's user_id
$user_grade = null; // To store the user's grade

// Fetch the logged-in user's name and grade
try {
    $stmt = $pdo->prepare("SELECT username, grade FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $logged_in_username = htmlspecialchars($user_data['username'] ?? 'Student'); // Default to 'Student' if not set
    $user_grade = $user_data['grade'] ?? null; // Get user's grade (e.g., "Grade 4" or NULL)
} catch (PDOException $e) {
    error_log("Fetch User Data Error: " . $e->getMessage());
    $message = display_message("Could not fetch user data. Please try again later.", "error");
}

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
    $sql = "
        SELECT quiz_id, title, max_attempts, duration_minutes, open_datetime, grade, assessment_fee
        FROM quizzes
        WHERE grade IS NULL OR grade = '' OR FIND_IN_SET(:user_grade, grade) > 0
        ORDER BY created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_grade' => $user_grade]);
    $all_assessments_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_assessments_raw as &$assessment) {
        // Convert comma-separated grades to an array for display
        $grade_display = 'N/A';
        if (!empty($assessment['grade'])) {
            $grades_array = explode(',', $assessment['grade']);
            
            // Sort grades numerically from lowest to highest
            usort($grades_array, function($a, $b) {
                // Extract numbers from "Grade X" strings
                $num_a = (int)filter_var($a, FILTER_SANITIZE_NUMBER_INT);
                $num_b = (int)filter_var($b, FILTER_SANITIZE_NUMBER_INT);
                return $num_a - $num_b;
            });
            
            $grade_display = implode(', ', $grades_array);
        }
        $assessment['grade_display'] = $grade_display;

        // Check if assessment duration has expired
        $assessment['is_expired'] = false;
        if ($assessment['open_datetime'] && $assessment['duration_minutes']) {
            $open_time = strtotime($assessment['open_datetime']);
            $end_time = $open_time + ($assessment['duration_minutes'] * 60);
            if (time() > $end_time) {
                $assessment['is_expired'] = true;
            }
        }

        // Add the 'is_paid' status to each assessment for direct access in the loop
        $assessment['is_paid'] = $paid_assessments[$assessment['quiz_id']] ?? false;

        // Fetch the number of attempts already made by the user for this specific quiz
        $stmt_attempts_taken = $pdo->prepare("SELECT COUNT(*) AS attempts_count FROM quiz_attempts WHERE user_id = :user_id AND quiz_id = :quiz_id");
        $stmt_attempts_taken->execute([
            'user_id' => $user_id,
            'quiz_id' => $assessment['quiz_id']
        ]);
        $attempts_data = $stmt_attempts_taken->fetch(PDO::FETCH_ASSOC);
        $assessment['attempts_taken'] = $attempts_data['attempts_count'] ?? 0;

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
            q.title AS quiz_title,
            q.quiz_id,
            (
                SELECT SUM(score)
                FROM questions
                WHERE quiz_id = q.quiz_id
            ) AS max_possible_score
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
                'max_score' => $attempt['max_possible_score'],
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/heroicons@2.1.1/dist/heroicons.js"></script>
    <style>
        /* Custom styles for truncating text and showing full text on hover */
        .truncate-ellipsis {
            max-width: 150px; /* Adjust width as needed */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            position: relative;
        }
        /* Tooltip styles */
        .tooltip-container {
            position: relative;
            display: inline-block;
        }
        .tooltip-content {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 10px;
            position: absolute;
            z-index: 10;
            bottom: 125%; /* Position above the text */
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            white-space: nowrap;
            font-size: 0.875rem;
        }
        .tooltip-container:hover .tooltip-content {
            visibility: visible;
            opacity: 1;
        }
        .tooltip-content::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        /* Popover styles */
        .popover {
            position: absolute;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 0.375rem;
            padding: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10;
            max-width: 300px;
            word-wrap: break-word;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .popover-close {
            float: right;
            cursor: pointer;
            font-weight: bold;
            color: #999;
            margin-left: 10px;
        }
        .popover-arrow {
            position: absolute;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid #ccc;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
        }
        .popover-arrow::after {
            content: '';
            position: absolute;
            width: 0;
            height: 0;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 7px solid #fff;
            bottom: 1px;
            left: -7px;
        }
        .table-compact th, .table-compact td {
            padding: 8px; /* Reduced padding */
            font-size: 0.875rem; /* Smaller font size */
        }
        .action-btn {
            display: inline-block;
            padding: 6px 16px; /* Longer but less wide */
            font-size: 0.75rem; /* Smaller font size */
            text-align: center;
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4 py-8 max-w-6xl"> <!-- Reduced max-width -->
        <h1 class="text-3xl font-extrabold text-indigo-600 mb-8 flex items-center">
            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Welcome, <?php echo ucfirst($logged_in_username); ?>!
        </h1>

        <?php echo $message; // Display any feedback messages ?>

        <?php if (!$verification_completed): ?>
        <div id="verificationModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 transition-opacity duration-300">
            <div class="bg-white p-6 rounded-2xl shadow-2xl max-w-sm w-full mx-4 transform transition-all duration-300 scale-100">
                <h2 class="text-xl font-bold text-red-600 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Verification Required!
                </h2>
                <p class="text-gray-700 mb-4 text-sm">
                    To access assessments, please complete your profile verification by uploading a passport/ID image and providing your City, State, and Country.
                </p>
                <div class="flex justify-end">
                    <a href="<?php echo BASE_URL; ?>student/profile.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-1 px-4 rounded-full transition duration-300 transform hover:scale-105 text-sm">
                        Go to Profile
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8"> <!-- Reduced gap -->
            <div class="bg-gradient-to-r from-indigo-50 to-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-700 mb-1">Assessments Available</h2>
                        <p class="text-2xl font-bold text-indigo-600"><?php echo htmlspecialchars(count($all_assessments_for_display)); ?></p>
                        <p class="text-xs text-gray-500 mt-1">Ready to take</p>
                    </div>
                    <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="bg-gradient-to-r from-green-50 to-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-700 mb-1">Total Completed</h2>
                        <p class="text-2xl font-bold text-green-600"><?php
                            $total_completed = 0;
                            foreach ($attempts_summary_per_quiz as $summary) {
                                $total_completed += $summary['completed_attempts'];
                            }
                            echo htmlspecialchars($total_completed);
                        ?></p>
                        <p class="text-xs text-gray-500 mt-1">Assessments finished</p>
                    </div>
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>
            <div class="bg-gradient-to-r from-blue-50 to-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-700 mb-1">Assessments Passed</h2>
                        <p class="text-2xl font-bold text-blue-600"><?php
                            $total_passed = 0;
                            foreach ($attempts_summary_per_quiz as $summary) {
                                $total_passed += $summary['passed_attempts'];
                            }
                            echo htmlspecialchars($total_passed);
                        ?></p>
                        <p class="text-xs text-gray-500 mt-1">Successful attempts</p>
                    </div>
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <h2 class="text-xl font-bold text-indigo-600 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            All Assessments
        </h2>
        <div class="bg-white p-6 rounded-xl shadow-md overflow-x-auto mb-8 max-h-[60vh] overflow-y-auto"> <!-- Reduced padding and max-height -->
            <?php if (empty($all_assessments_for_display)): ?>
                <p class="text-gray-600 text-sm">No assessments available</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 table-compact">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($all_assessments_for_display as $assessment): ?>
                        <tr class="hover:bg-gray-50 transition duration-200">
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                <a href="<?php echo BASE_URL; ?>student/take_quiz.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>" 
                                   class="text-indigo-600 hover:text-indigo-800 font-medium truncate-ellipsis tooltip-container" 
                                   data-full-text="<?php echo htmlspecialchars($assessment['title']); ?>">
                                    <?php 
                                        $title = htmlspecialchars($assessment['title']);
                                        echo strlen($title) > 25 ? substr($title, 0, 22) . '...' : $title;
                                    ?>
                                    <?php if (strlen($title) > 25): ?>
                                        <span class="tooltip-content"><?php echo $title; ?></span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 truncate-ellipsis tooltip-container" 
                                data-full-text="<?php echo htmlspecialchars($assessment['grade_display']); ?>">
                                <?php 
                                    $grade_display = htmlspecialchars($assessment['grade_display']);
                                    echo strlen($grade_display) > 15 ? substr($grade_display, 0, 12) . '...' : $grade_display;
                                ?>
                                <?php if (strlen($grade_display) > 15): ?>
                                    <span class="tooltip-content"><?php echo $grade_display; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['duration_minutes'] ?: 'No Limit'); ?> mins</td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 truncate-ellipsis tooltip-container" 
                                data-full-text="<?php
                                    echo $assessment['open_datetime']
                                        ? date('j F, Y g:i a', strtotime($assessment['open_datetime']))
                                        : 'Immediate';
                                ?>">
                                <?php
                                    $date_display = $assessment['open_datetime']
                                        ? date('j F, Y g:i a', strtotime($assessment['open_datetime']))
                                        : 'Immediate';
                                    echo htmlspecialchars(strlen($date_display) > 15 ? substr($date_display, 0, 12) . '...' : $date_display);
                                ?>
                                <?php if (strlen($date_display) > 15): ?>
                                    <span class="tooltip-content"><?php echo htmlspecialchars($date_display); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                                <?php
                                $can_start_assessment = true;
                                $action_link = '';
                                $action_text = '';
                                $action_class = '';
                                $action_title = '';

                                // Check if assessment is available based on open_datetime (5-minute window)
                                $is_assessment_available = true;
                                if ($assessment['open_datetime']) {
                                    $open_time = strtotime($assessment['open_datetime']);
                                    $current_time = time();
                                    $five_minutes_before = $open_time - (5 * 60); // 5 minutes before open_datetime
                                    if ($current_time < $five_minutes_before) {
                                        $is_assessment_available = false;
                                    }
                                }

                                // Check if assessment duration has expired
                                $is_expired = $assessment['is_expired'];

                                if ($is_expired) {
                                    $can_start_assessment = false;
                                    $action_text = 'Expired';
                                    $action_class = 'text-red-600 cursor-not-allowed action-btn';
                                    $action_title = 'This assessment is no longer available as its duration has expired.';
                                } elseif (!$is_assessment_available) {
                                    $can_start_assessment = false;
                                    $action_text = 'Not Available';
                                    $action_class = 'text-gray-400 cursor-not-allowed action-btn';
                                    $action_title = 'This assessment is not yet available. It can be started 5 minutes before the scheduled time.';
                                } elseif (!$verification_completed) {
                                    $can_start_assessment = false;
                                    $action_text = 'Verify Profile';
                                    $action_class = 'text-gray-400 cursor-not-allowed action-btn';
                                    $action_title = 'Please complete your profile verification to start this assessment.';
                                } elseif ($assessment['assessment_fee'] > 0 && !$assessment['is_paid']) {
                                    $can_start_assessment = false;
                                    $action_text = 'Pay Assessment';
                                    $action_link = BASE_URL . 'student/make_payment.php?quiz_id=' . htmlspecialchars($assessment['quiz_id']) . '&amount=' . htmlspecialchars($assessment['assessment_fee']);
                                    $action_class = 'text-orange-600 hover:text-orange-800 font-semibold bg-orange-100 action-btn transition duration-200';
                                    $action_title = 'Payment required to start this assessment.';
                                } elseif ($assessment['max_attempts'] !== 0 && $assessment['attempts_taken'] >= $assessment['max_attempts']) {
                                    $can_start_assessment = false;
                                    $action_text = 'Attempts Done';
                                    $action_class = 'text-red-600 cursor-not-allowed action-btn';
                                    $action_title = 'You have used all your attempts for this assessment.';
                                } else {
                                    $action_text = 'Start';
                                    $action_link = BASE_URL . 'student/take_quiz.php?quiz_id=' . htmlspecialchars($assessment['quiz_id']);
                                    $action_class = 'text-green-600 hover:text-green-900 font-semibold bg-green-100 action-btn transition duration-200';
                                    $action_title = 'Start this assessment now.';
                                }

                                if (!empty($action_link) && ($can_start_assessment || ($assessment['assessment_fee'] > 0 && !$assessment['is_paid']))) {
                                    echo '<a href="' . $action_link . '" class="' . $action_class . '" title="' . $action_title . '">' . $action_text . '</a>';
                                } else {
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

        <h2 class="text-xl font-bold text-indigo-600 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Your Recent Assessment Results
        </h2>
        <div class="bg-white p-6 rounded-xl shadow-md overflow-x-auto mb-8"> <!-- Reduced padding -->
            <?php if (empty($all_previous_attempts)): ?>
                <p class="text-gray-600 text-sm">No assessments completed yet.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 table-compact">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Title</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $displayed_attempts_count = 0;
                        foreach ($all_previous_attempts as $attempt):
                            if ($displayed_attempts_count >= 5) break; // Limit to 5 attempts
                        ?>
                        <tr class="hover:bg-gray-50 transition duration-200">
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 truncate-ellipsis tooltip-container" 
                                data-full-text="<?php echo htmlspecialchars($attempt['quiz_title']); ?>">
                                <?php 
                                    $quiz_title = htmlspecialchars($attempt['quiz_title']);
                                    echo strlen($quiz_title) > 25 ? substr($quiz_title, 0, 22) . '...' : $quiz_title;
                                ?>
                                <?php if (strlen($quiz_title) > 25): ?>
                                    <span class="tooltip-content"><?php echo $quiz_title; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                <?php
                                if ($attempt['is_completed'] && isset($max_scores_per_quiz[$attempt['quiz_id']]) && $max_scores_per_quiz[$attempt['quiz_id']] > 0) {
                                    $percentage_score = ($attempt['score'] / $max_scores_per_quiz[$attempt['quiz_id']]) * 100;
                                    echo htmlspecialchars(round($percentage_score, 2)) . '%';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 truncate-ellipsis tooltip-container" 
                                data-full-text="<?php echo format_datetime($attempt['start_time']); ?>">
                                <?php 
                                    $start_time = format_datetime($attempt['start_time']);
                                    echo htmlspecialchars(strlen($start_time) > 15 ? substr($start_time, 0, 12) . '...' : $start_time);
                                ?>
                                <?php if (strlen($start_time) > 15): ?>
                                    <span class="tooltip-content"><?php echo htmlspecialchars($start_time); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 truncate-ellipsis tooltip-container" 
                                data-full-text="<?php echo $attempt['end_time'] ? format_datetime($attempt['end_time']) : 'N/A'; ?>">
                                <?php 
                                    $end_time = $attempt['end_time'] ? format_datetime($attempt['end_time']) : 'N/A';
                                    echo htmlspecialchars(strlen($end_time) > 15 ? substr($end_time, 0, 12) . '...' : $end_time);
                                ?>
                                <?php if (strlen($end_time) > 15): ?>
                                    <span class="tooltip-content"><?php echo htmlspecialchars($end_time); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo $attempt['is_completed'] ? 'Completed' : 'Cancelled'; ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-medium">
                                <a href="<?php echo BASE_URL; ?>student/assessments.php?attempt_id=<?php echo htmlspecialchars($attempt['attempt_id']); ?>"
                                   class="text-indigo-600 hover:text-indigo-800 font-semibold bg-indigo-100 action-btn transition duration-200">View</a>
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
                    <a href="<?php echo BASE_URL; ?>student/assessments.php" class="inline-block bg-indigo-100 text-indigo-600 px-4 py-1 rounded-full hover:bg-indigo-200 font-semibold transition duration-200 text-sm">
                        View All History →
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <h2 class="text-xl font-bold text-indigo-600 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Overall Assessment Statistics
        </h2>
        <div class="bg-white p-6 rounded-xl shadow-md overflow-x-auto"> <!-- Reduced padding -->
            <?php if (empty($attempts_summary_per_quiz)): ?>
                <p class="text-gray-600 text-sm">No assessment statistics available yet.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 table-compact">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Title</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passed</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Score</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($attempts_summary_per_quiz as $quiz_id => $summary): ?>
                        <tr class="hover:bg-gray-50 transition duration-200">
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 truncate-ellipsis tooltip-container" 
                                data-full-text="<?php echo htmlspecialchars($summary['title']); ?>">
                                <?php 
                                    $summary_title = htmlspecialchars($summary['title']);
                                    echo strlen($summary_title) > 25 ? substr($summary_title, 0, 22) . '...' : $summary_title;
                                ?>
                                <?php if (strlen($summary_title) > 25): ?>
                                    <span class="tooltip-content"><?php echo $summary_title; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['total_attempts']); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['completed_attempts']); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['passed_attempts']); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($summary['failed_attempts']); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
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

            // Intercept clicks on 'Start' or 'Pay for Assessment' if conditions are not met
            document.querySelectorAll('a[href*="take_quiz.php"], a[href*="make_payment.php"]').forEach(link => {
                link.addEventListener('click', function(event) {
                    // Check if the link's text corresponds to a disabled action
                    const actionText = event.target.textContent.trim();
                    const titleAttribute = event.target.getAttribute('title');

                    if (actionText === 'Verify Profile' || 
                        actionText === 'Attempts Done' || 
                        actionText === 'Not Available' || 
                        actionText === 'Expired') {
                        
                        event.preventDefault();
                        if (titleAttribute) {
                            alert(titleAttribute);
                        } else {
                            alert('This action is not available at this time.');
                        }
                    } else if (verificationModal && verificationModal.classList.contains('flex')) {
                        // This block handles cases where the modal is already visible
                        // and the user tries to click a valid "Start" or "Pay for Assessment" link
                        if ((event.target.href.includes('take_quiz.php') || event.target.href.includes('make_payment.php')) && !<?php echo json_encode($verification_completed); ?>) {
                            event.preventDefault();
                            alert('Please complete your profile verification before proceeding with assessments or payments.');
                            // The modal is already visible, so no need to change its classes here
                        }
                    }
                });
            });

            // Popover functionality for truncated text
            let currentPopover = null;

            function showPopover(element, text) {
                // Remove any existing popover
                if (currentPopover) {
                    currentPopover.remove();
                    currentPopover = null;
                }

                const popover = document.createElement('div');
                popover.classList.add('popover');
                popover.innerHTML = `
                    <span class="popover-close">×</span>
                    <strong>Full Text:</strong><br>${text}
                    <div class="popover-arrow"></div>
                `;
                document.body.appendChild(popover);

                // Position the popover
                const rect = element.getBoundingClientRect();
                popover.style.left = `${rect.left + window.scrollX + (rect.width / 2) - (popover.offsetWidth / 2)}px`;
                popover.style.top = `${rect.top + window.scrollY - popover.offsetHeight - 10}px`; // 10px above the element

                // Adjust if popover goes off screen to the left
                if (parseFloat(popover.style.left) < 0) {
                    popover.style.left = '10px';
                }
                // Adjust if popover goes off screen to the right
                if (parseFloat(popover.style.left) + popover.offsetWidth > window.innerWidth) {
                    popover.style.left = `${window.innerWidth - popover.offsetWidth - 10}px`;
                }

                currentPopover = popover;

                // Close popover on click outside
                document.addEventListener('click', function handler(event) {
                    if (currentPopover && !currentPopover.contains(event.target) && !element.contains(event.target)) {
                        currentPopover.remove();
                        currentPopover = null;
                        document.removeEventListener('click', handler);
                    }
                });

                // Close popover using the close button
                popover.querySelector('.popover-close').addEventListener('click', () => {
                    popover.remove();
                    currentPopover = null;
                });
            }

            // Add click listeners for truncated text cells
            document.querySelectorAll('.truncate-ellipsis.tooltip-container').forEach(cell => {
                cell.addEventListener('click', function() {
                    const fullText = this.dataset.fullText;
                    if (fullText) {
                        showPopover(this, fullText);
                    }
                });
            });
        });
    </script>
</body>
</html>