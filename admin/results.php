<?php
// admin/results.php
// Controller for viewing assessment results, proctoring logs, and images.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin-specific header
require_once '../includes/header_admin.php';

$message = ''; // Initialize message variable for feedback
$results = []; // Array to hold fetched assessment results
$proctoring_logs = []; // Array to hold fetched proctoring logs
$proctoring_images = []; // Array to hold fetched proctoring images
$current_attempt = null; // Store details of the currently viewed attempt
$assessments = []; // Array to hold all assessments
$current_quiz = null; // Store details of the currently viewed quiz
$current_user = null; // Store details of the currently viewed user
$summary_metrics = []; // Summary metrics for quiz attempts or user attempts

// Filters for the main assessment list
$filter_assessment_id_main = sanitize_input($_GET['assessment_id_main'] ?? null);

// Filters for detailed attempt list (used for both quiz attempts and user attempts)
$filter_user_id_detail = sanitize_input($_GET['user_id_detail'] ?? null);
$filter_assessment_id_detail = sanitize_input($_GET['assessment_id_detail'] ?? null); // New filter for user_attempts view
$filter_completion_status = sanitize_input($_GET['completion_status'] ?? null);
$filter_score_min = sanitize_input($_GET['score_min'] ?? null);
$filter_score_max = sanitize_input($_GET['score_max'] ?? null);
$filter_start_date = sanitize_input($_GET['start_date'] ?? null);
$filter_end_date = sanitize_input($_GET['end_date'] ?? null);

// Sorting
$sort_by = sanitize_input($_GET['sort_by'] ?? 'score');
$sort_order = sanitize_input(strtoupper($_GET['sort_order'] ?? 'DESC'));

// Validate sort parameters
$allowed_sort_columns = ['attempt_id', 'username', 'quiz_title', 'score', 'start_time', 'end_time', 'is_completed'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'score';
}
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

$view_attempt_id = sanitize_input($_GET['view_attempt'] ?? null);
$view_quiz_id = sanitize_input($_GET['view_quiz'] ?? null);
$view_user_id = sanitize_input($_GET['view_user'] ?? null); // New: For viewing attempts by user

try {
    // Fetch users and assessments for filter dropdowns
    $all_users = $pdo->query("SELECT user_id, username, email, role FROM users WHERE role = 'student' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    $all_assessments_for_filters = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($view_attempt_id) {
        // Detailed view of a single attempt
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
            // Fetch answers
            $stmt_answers = $pdo->prepare("
                SELECT
                    a.answer_id, a.answer_text, a.is_correct, a.selected_option_id,
                    qs.question_id, qs.question_text, qs.question_type, qs.score as question_score,
                    GROUP_CONCAT(DISTINCT CONCAT(opt.option_id, '||', opt.option_text, '||', opt.is_correct) SEPARATOR ';;') as all_options_data
                FROM answers a
                JOIN questions qs ON a.question_id = qs.question_id
                LEFT JOIN options opt ON qs.question_id = opt.question_id
                WHERE a.attempt_id = :attempt_id
                GROUP BY a.answer_id, qs.question_id
                ORDER BY qs.question_id ASC
            ");
            $stmt_answers->execute(['attempt_id' => $view_attempt_id]);
            $current_attempt['answers'] = $stmt_answers->fetchAll(PDO::FETCH_ASSOC);

            // Fetch proctoring logs
            $stmt_logs = $pdo->prepare("
                SELECT log_id, event_type, log_data, timestamp as log_time
                FROM proctoring_logs
                WHERE attempt_id = :attempt_id
                ORDER BY timestamp ASC
            ");
            $stmt_logs->execute(['attempt_id' => $view_attempt_id]);
            $proctoring_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

            // Fetch proctoring images
            $stmt_images = $pdo->prepare("
                SELECT image_id, image_path, capture_time
                FROM proctoring_images
                WHERE attempt_id = :attempt_id
                ORDER BY capture_time ASC
            ");
            $stmt_images->execute(['attempt_id' => $view_attempt_id]);
            $proctoring_images = $stmt_images->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $message = display_message("Attempt not found.", "error");
        }
    } elseif ($view_quiz_id) {
        // View all attempts for a specific quiz
        $stmt_quiz = $pdo->prepare("SELECT quiz_id, title FROM quizzes WHERE quiz_id = :quiz_id");
        $stmt_quiz->execute(['quiz_id' => $view_quiz_id]);
        $current_quiz = $stmt_quiz->fetch(PDO::FETCH_ASSOC);

        if (!$current_quiz) {
            $message = display_message("Assessment not found.", "error");
            $view_quiz_id = null;
        } else {
            $sql = "
                SELECT
                    qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                    u.username, q.title as quiz_title, q.quiz_id, u.user_id,
                    (SELECT COUNT(*) FROM proctoring_logs pl WHERE pl.attempt_id = qa.attempt_id AND pl.event_type = 'critical_error') as log_violations,
                    (SELECT COUNT(*) FROM proctoring_images pi WHERE pi.attempt_id = qa.attempt_id) as image_count
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

            $sql .= " ORDER BY {$sort_by} {$sort_order}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate summary metrics
            $total_attempts = count($results);
            $completed_attempts = 0;
            $passed_attempts = 0;
            $failed_attempts = 0;
            $total_score_sum = 0;
            $attempts_with_proctoring_violations = 0;
            $passing_percentage = 70; // You might want to make this configurable

            $stmt_max_quiz_score = $pdo->prepare("SELECT SUM(score) FROM questions WHERE quiz_id = :quiz_id");
            $stmt_max_quiz_score->execute(['quiz_id' => $view_quiz_id]);
            $max_possible_quiz_score = $stmt_max_quiz_score->fetchColumn() ?: 1;

            foreach ($results as $result) {
                if ($result['is_completed']) {
                    $completed_attempts++;
                    $total_score_sum += $result['score'];
                    $percentage_score = ($max_possible_quiz_score > 0) ? ($result['score'] / $max_possible_quiz_score) * 100 : 0;
                    if ($percentage_score >= $passing_percentage) {
                        $passed_attempts++;
                    } else {
                        $failed_attempts++;
                    }
                }
                $has_violation = ($result['log_violations'] > 0) || ($result['is_completed'] && $result['image_count'] == 0);
                if ($has_violation) {
                    $attempts_with_proctoring_violations++;
                }
            }
            $average_score = ($completed_attempts > 0) ? round($total_score_sum / $completed_attempts, 2) : 0;
            $average_score_percentage = ($max_possible_quiz_score > 0) ? round(($average_score / $max_possible_quiz_score) * 100, 2) : 0;
            $completion_rate = ($total_attempts > 0) ? round(($completed_attempts / $total_attempts) * 100, 2) : 0;

            $summary_metrics = [
                'total_attempts' => $total_attempts,
                'completed_attempts' => $completed_attempts,
                'passed_attempts' => $passed_attempts,
                'failed_attempts' => $failed_attempts,
                'average_score' => $average_score_percentage,
                'completion_rate' => $completion_rate,
                'attempts_with_proctoring_violations' => $attempts_with_proctoring_violations,
                'passing_percentage_threshold' => $passing_percentage,
                'max_possible_quiz_score' => $max_possible_quiz_score
            ];
        }
    } elseif ($view_user_id) { // New: Handle viewing attempts by user
        $stmt_user = $pdo->prepare("SELECT user_id, username, email FROM users WHERE user_id = :user_id AND role = 'student'");
        $stmt_user->execute(['user_id' => $view_user_id]);
        $current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if (!$current_user) {
            $message = display_message("User not found or is not a student.", "error");
            $view_user_id = null;
        } else {
            $sql = "
                SELECT
                    qa.attempt_id, qa.score, qa.start_time, qa.end_time, qa.is_completed,
                    u.username, q.title as quiz_title, q.quiz_id, u.user_id,
                    (SELECT COUNT(*) FROM proctoring_logs pl WHERE pl.attempt_id = qa.attempt_id AND pl.event_type = 'critical_error') as log_violations,
                    (SELECT COUNT(*) FROM proctoring_images pi WHERE pi.attempt_id = qa.attempt_id) as image_count
                FROM quiz_attempts qa
                JOIN users u ON qa.user_id = u.user_id
                JOIN quizzes q ON qa.quiz_id = q.quiz_id
                WHERE u.user_id = :user_id
            ";
            $params = ['user_id' => $view_user_id];
            $where_clauses = [];

            if ($filter_assessment_id_detail) {
                $where_clauses[] = "q.quiz_id = :quiz_id";
                $params['quiz_id'] = $filter_assessment_id_detail;
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

            $sql .= " ORDER BY {$sort_by} {$sort_order}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate summary metrics for the user's attempts
            $total_attempts = count($results);
            $completed_attempts = 0;
            $total_score_sum = 0;
            $attempts_with_proctoring_violations = 0;

            // To calculate average score percentage correctly, we need the max possible score for each quiz.
            // This is more complex for a user's attempts across multiple quizzes.
            // For simplicity, let's calculate average actual score for now, and note the limitation.
            // Or, we can modify summary metrics to show average percentage IF all attempts are for the same quiz.

            $user_passing_attempts = 0;
            $user_failing_attempts = 0;

            foreach ($results as $result) {
                if ($result['is_completed']) {
                    $completed_attempts++;
                    $total_score_sum += $result['score'];

                    // Fetch max possible score for this specific quiz attempt
                    $stmt_quiz_max_score = $pdo->prepare("SELECT SUM(score) FROM questions WHERE quiz_id = :quiz_id");
                    $stmt_quiz_max_score->execute(['quiz_id' => $result['quiz_id']]);
                    $max_quiz_score_for_attempt = $stmt_quiz_max_score->fetchColumn() ?: 1;

                    $percentage_score = ($max_quiz_score_for_attempt > 0) ? ($result['score'] / $max_quiz_score_for_attempt) * 100 : 0;
                    if ($percentage_score >= 70) { // Assuming 70% as passing
                        $user_passing_attempts++;
                    } else {
                        $user_failing_attempts++;
                    }
                }
                $has_violation = ($result['log_violations'] > 0) || ($result['is_completed'] && $result['image_count'] == 0);
                if ($has_violation) {
                    $attempts_with_proctoring_violations++;
                }
            }

            $average_score_user = ($completed_attempts > 0) ? round($total_score_sum / $completed_attempts, 2) : 0;
            $completion_rate_user = ($total_attempts > 0) ? round(($completed_attempts / $total_attempts) * 100, 2) : 0;

            $summary_metrics = [
                'total_attempts' => $total_attempts,
                'completed_attempts' => $completed_attempts,
                'passed_attempts' => $user_passing_attempts,
                'failed_attempts' => $user_failing_attempts,
                'average_actual_score' => $average_score_user, // Display actual score for user's aggregate
                'completion_rate' => $completion_rate_user,
                'attempts_with_proctoring_violations' => $attempts_with_proctoring_violations,
                'passing_percentage_threshold' => 70 // Consistent passing threshold
            ];
        }
    } else {
        // Default view: List all assessments
        $sql = "
            SELECT
                q.quiz_id, q.title, COUNT(qa.attempt_id) as total_attempts,
                AVG(CASE WHEN qa.is_completed = 1 THEN qa.score END) as avg_score,
                SUM(CASE WHEN qa.is_completed = 1 THEN 1 ELSE 0 END) as completed_attempts_count,
                SUM(CASE WHEN pl.event_type = 'critical_error' OR (qa.is_completed = 1 AND NOT EXISTS (SELECT 1 FROM proctoring_images pi WHERE pi.attempt_id = qa.attempt_id)) THEN 1 ELSE 0 END) as violation_count
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

        foreach ($assessments as &$assessment) {
            $stmt_max_score_overall = $pdo->prepare("SELECT SUM(score) FROM questions WHERE quiz_id = :quiz_id");
            $stmt_max_score_overall->execute(['quiz_id' => $assessment['quiz_id']]);
            $assessment['max_possible_score'] = $stmt_max_score_overall->fetchColumn() ?: 1;
            $assessment['avg_score_percentage'] = ($assessment['avg_score'] !== null && $assessment['max_possible_score'] > 0) ? round(($assessment['avg_score'] / $assessment['max_possible_score']) * 100, 2) : 0;
        }
        unset($assessment);
    }
} catch (PDOException $e) {
    error_log("View Results Error: " . $e->getMessage());
    $message = display_message("Could not fetch results. Please try again later.", "error");
}
?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-4xl font-extrabold text-theme-color mb-8 text-center">Assessment Results Dashboard</h1>
    <?php echo $message; ?>

    <?php
    if ($view_attempt_id && isset($current_attempt)) {
        require_once 'views/attempt_details.php';
    } elseif ($view_quiz_id && isset($current_quiz)) {
        require_once 'views/quiz_attempts.php';
    } elseif ($view_user_id && isset($current_user)) { // New: Include user_attempts view
        require_once 'views/user_attempts.php';
    } else {
        require_once 'views/assessments_list.php';
    }
    ?>
</div>

<?php
// Include the admin-specific footer
require_once '../includes/footer_admin.php';
?>