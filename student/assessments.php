<?php
// student/assessments.php
// Allows students to view their quiz history and detailed results for specific attempts.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = '';
$user_id = getUserId();

// Redirect if user_id is not available
if (!$user_id) {
    redirect('login.php');
    exit();
}

// Fetch user's grade
$logged_in_user_grade = '';
if (isset($_SESSION['user_grade'])) {
    $logged_in_user_grade = $_SESSION['user_grade'];
} else {
    try {
        $stmt_user_grade = $pdo->prepare("SELECT grade FROM users WHERE user_id = :user_id");
        $stmt_user_grade->execute(['user_id' => $user_id]);
        $user_data = $stmt_user_grade->fetch(PDO::FETCH_ASSOC);
        if ($user_data && !empty($user_data['grade'])) {
            $logged_in_user_grade = $user_data['grade'];
            $_SESSION['user_grade'] = $logged_in_user_grade;
        }
    } catch (PDOException $e) {
        error_log("Error fetching user grade: " . $e->getMessage());
        $message .= display_message("Could not fetch your grade information.", "error");
    }
}

$attempts = [];
$current_attempt = null;

// Filter variables
$filter_quiz_id = sanitize_input($_GET['quiz_id'] ?? null);
$filter_start_date = sanitize_input($_GET['start_date'] ?? null);
$filter_end_date = sanitize_input($_GET['end_date'] ?? null);
$filter_min_percentage = filter_input(INPUT_GET, 'min_percentage', FILTER_VALIDATE_FLOAT);
if ($filter_min_percentage !== false && $filter_min_percentage < 0) $filter_min_percentage = 0;
if ($filter_min_percentage !== false && $filter_min_percentage > 100) $filter_min_percentage = 100;

// Search variable
$search_query = sanitize_input($_GET['search'] ?? null);

// Sanitize attempt_id
$view_attempt_id = filter_input(INPUT_GET, 'attempt_id', FILTER_VALIDATE_INT);

/**
 * Calculates the percentage score.
 * @param float $score The raw score obtained.
 * @param float $max_score The maximum possible score for the quiz.
 * @return float The percentage score, rounded to 2 decimal places.
 */
function calculate_percentage($score, $max_score) {
    if ($max_score <= 0) {
        return 0;
    }
    return round(($score / $max_score) * 100, 2);
}

// Fetch quizzes for filter dropdown
$all_quizzes_for_filters = [];
try {
    $sql_quizzes_filter = "SELECT quiz_id, title FROM quizzes";
    $quiz_filter_params = [];
    $quiz_filter_where_clauses = [];
    if (!empty($logged_in_user_grade)) {
        $quiz_filter_where_clauses[] = "(grade IS NULL OR grade = :user_grade)";
        $quiz_filter_params['user_grade'] = $logged_in_user_grade;
    }
    if (!empty($quiz_filter_where_clauses)) {
        $sql_quizzes_filter .= " WHERE " . implode(" AND ", $quiz_filter_where_clauses);
    }
    $sql_quizzes_filter .= " ORDER BY title ASC";
    $stmt_quizzes = $pdo->prepare($sql_quizzes_filter);
    $stmt_quizzes->execute($quiz_filter_params);
    $all_quizzes_for_filters = $stmt_quizzes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("View History Quiz Fetch Error (for filters): " . $e->getMessage());
    $message = display_message("Could not fetch assessment list for filters.", "error");
}

// Main logic
if ($view_attempt_id) {
    try {
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
            $current_attempt['percentage_score'] = $current_attempt['is_completed'] ? calculate_percentage($current_attempt['score'], $current_attempt['max_possible_score']) : 'N/A';
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
                $current_attempt['answers'] = [];
            }
        } else {
            $message = display_message("Attempt not found or you do not have permission to view it.", "error");
            $view_attempt_id = null;
        }
    } catch (PDOException $e) {
        error_log("Student View History (Main Detailed) Error: " . $e->getMessage());
        $message = display_message("An error occurred while fetching the detailed quiz attempt.", "error");
        $view_attempt_id = null;
    }
}

if (!$view_attempt_id) {
    try {
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
        if ($search_query) {
            $where_clauses[] = "q.title LIKE :search_query";
            $params['search_query'] = '%' . $search_query . '%';
        }
        if (!empty($where_clauses)) {
            $sql .= " AND " . implode(" AND ", $where_clauses);
        }
        $sql .= " ORDER BY qa.start_time DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attempts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($attempts_raw as $attempt) {
            $attempt['percentage_score'] = $attempt['is_completed'] ? calculate_percentage($attempt['score'], $attempt['max_possible_score']) : 'N/A';
            if ($filter_min_percentage !== false) {
                if ($attempt['is_completed'] && $attempt['percentage_score'] >= $filter_min_percentage) {
                    $attempts[] = $attempt;
                } else if (!$attempt['is_completed'] && $filter_min_percentage == 0) {
                    $attempts[] = $attempt;
                }
            } else {
                $attempts[] = $attempt;
            }
        }
    } catch (PDOException $e) {
        error_log("Student View History (Overview) Error: " . $e->getMessage());
        $message = display_message("An error occurred while fetching your quiz history overview.", "error");
        $attempts = [];
    }
}
?>

<div class="container mx-auto p-4 py-8 max-w-7xl">
    <h1 class="text-3xl font-bold text-theme-color mb-6 text-center">Your Assessments</h1>

    <?php echo $message; ?>

    <?php if ($view_attempt_id && isset($current_attempt) && $current_attempt): ?>
        <div class="flex flex-col md:flex-row justify-center items-start gap-8 mb-8">
            <div class="flex-shrink-0 w-full md:w-80 p-6 rounded-3xl shadow-xl text-center text-white"
                 style="background: linear-gradient(180deg, #6742F1 0%, #392B7D 100%);">
                <h2 class="text-xl font-bold mb-6 opacity-80">Your Result</h2>
                <div class="relative w-40 h-40 mx-auto mb-6 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(180deg, #4E23D7 0%, rgba(37, 24, 137, 0.8) 100%);">
                    <span id="animatedScore" class="text-6xl font-bold">0</span>
                    <span class="text-lg opacity-70 absolute bottom-8">%</span>
                </div>
                <h3 id="resultStatus" class="text-3xl font-bold mb-2"></h3>
                <p id="resultFeedback" class="text-base opacity-80 px-4"></p>
            </div>

            <div class="flex-grow bg-white p-6 rounded-3xl shadow-xl w-full">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Assessment Details: "<?php echo htmlspecialchars($current_attempt['quiz_title']); ?>"</h2>
                <div class="space-y-3 text-gray-700 mb-6">
                    <p><strong>Attempt ID:</strong> <?php echo htmlspecialchars($current_attempt['attempt_id']); ?></p>
                    <p><strong>Started:</strong> <?php echo date('g:i A, F j, Y', strtotime($current_attempt['start_time'])); ?></p>
                    <p><strong>Completed:</strong> <?php echo $current_attempt['end_time'] ? date('g:i A, F j, Y', strtotime($current_attempt['end_time'])) : 'N/A'; ?></p>
                    <p><strong>Status:</strong> <?php echo $current_attempt['is_completed'] ? 'Completed' : 'Cancelled'; ?></p>
                    <p><strong>Overall Score:</strong> <?php echo htmlspecialchars(sprintf('%.2f', $current_attempt['score'] ?? 0)); ?> / <?php echo htmlspecialchars($current_attempt['max_possible_score'] ?? 0); ?></p>
                </div>
                <div class="mt-8 text-center">
                    <button id="toggleAnswersButton" class="inline-block w-full bg-purple-600 text-white px-6 py-3 rounded-full hover:bg-purple-700 transition duration-300">
                        <i class="fas fa-eye mr-2"></i> View Questions & Answers
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <div id="questionsAnswersSection" class="hidden">
                <h3 class="text-xl font-semibold text-gray-700 mb-3">Questions and Your Answers</h3>
                <?php if (!empty($current_attempt['answers'])): ?>
                    <?php foreach ($current_attempt['answers'] as $answer): ?>
                        <div class="border p-4 rounded-md bg-gray-50 mb-4">
                            <p class="font-semibold text-gray-800">Q: <?php echo htmlspecialchars($answer['question_text']); ?> (Points: <?php echo htmlspecialchars($answer['question_score']); ?>)</p>
                            <p class="text-sm text-gray-600">Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $answer['question_type']))); ?></p>
                            <?php if ($answer['question_type'] === 'multiple_choice'): ?>
                                <p><strong>Your Selected Option:</strong> <?php echo htmlspecialchars($answer['selected_option_text'] ?? 'No answer'); ?></p>
                                <?php
                                    $correct_options_parsed = [];
                                    if (!empty($answer['correct_options_data'])) {
                                        $options_raw = explode(';;', $answer['correct_options_data']);
                                        foreach ($options_raw as $opt_str) {
                                            if (strpos($opt_str, '||') !== false) {
                                                list($opt_text, $is_correct_val) = explode('||', $opt_str, 2);
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
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const scoreElement = document.getElementById('animatedScore');
                const resultStatusElement = document.getElementById('resultStatus');
                const resultFeedbackElement = document.getElementById('resultFeedback');
                const finalScore = <?php echo json_encode($current_attempt['percentage_score'] !== 'N/A' ? (float)$current_attempt['percentage_score'] : 0); ?>;
                const duration = 1500;
                const start = 0;
                let startTime = null;

                // Feedback message arrays for different score ranges
                const feedbackMessages = {
                    exceptional: [
                        "Phenomenal performance! You're in the elite top tier!",
                        "Absolutely stellar! You've mastered this challenge!",
                        "Incredible work! You're a quiz superstar!",
                        "Unbelievable! You've set a new standard of excellence!",
                        "Spectacular job! You're among the best of the best!"
                    ],
                    great: [
                        "Great job! You’re smashing it!",
                        "Outstanding! You nailed it.",
                        "Top marks! Keep it up.",
                        "Brilliant work! You're on fire.",
                        "You’ve done really well—keep going!"
                    ],
                    good: [
                        "Good effort! A bit more polish and you'll ace it.",
                        "Nice work, but you can push further.",
                        "Almost there! Keep at it.",
                        "Solid try! Now aim higher.",
                        "You’ve got potential—just a little more effort!"
                    ],
                    improving: [
                        "You're getting there! A bit more practice will boost your score!",
                        "Nice try! Focus on the details to climb higher.",
                        "Keep going! You're building a strong foundation.",
                        "Good start! A little more study and you'll shine.",
                        "Progress in motion! Stay focused to improve!"
                    ],
                    needsWork: [
                        "Don't give up! Every attempt makes you stronger.",
                        "Keep practicing! You're learning with every step.",
                        "A challenge today is a victory tomorrow—keep at it!",
                        "You're on the path to success—more practice will get you there!",
                        "Every effort counts! Review and try again to soar!"
                    ]
                };

                function getFeedback(score) {
                    let messages, status, percentile;
                    if (score > 95) {
                        messages = feedbackMessages.exceptional;
                        status = 'Exceptional';
                        percentile = 96;
                    } else if (score >= 90) {
                        messages = feedbackMessages.great;
                        status = 'Great Job';
                        percentile = 90;
                    } else if (score >= 70) {
                        messages = feedbackMessages.good;
                        status = 'Good Effort';
                        percentile = 75;
                    } else if (score >= 50) {
                        messages = feedbackMessages.improving;
                        status = 'Needs Improvement';
                        percentile = null; // No percentile for <70%
                    } else {
                        messages = feedbackMessages.needsWork;
                        status = 'Needs Improvement';
                        percentile = null; // No percentile for <70%
                    }
                    const message = messages[Math.floor(Math.random() * messages.length)];
                    // 50% chance to include percentile message for scores >= 70%
                    const showPercentile = score >= 70 && Math.random() < 0.5;
                    const finalMessage = showPercentile && percentile ? `${message} You scored higher than ${percentile}% of participants.` : message;
                    return { status, message: finalMessage };
                }

                function animateScore(currentTime) {
                    if (!startTime) startTime = currentTime;
                    const progress = Math.min((currentTime - startTime) / duration, 1);
                    const currentScore = Math.floor(progress * (finalScore - start) + start);
                    scoreElement.textContent = currentScore;
                    document.querySelector('#animatedScore + span').textContent = '%';

                    if (progress < 1) {
                        requestAnimationFrame(animateScore);
                    } else {
                        if (finalScore !== 'N/A' && finalScore > 0) {
                            const { status, message } = getFeedback(finalScore);
                            resultStatusElement.textContent = status;
                            resultFeedbackElement.textContent = message;
                        } else {
                            scoreElement.textContent = 'N/A';
                            document.querySelector('#animatedScore + span').textContent = '';
                            resultStatusElement.textContent = 'Not Completed';
                            resultFeedbackElement.textContent = 'This assessment was not completed.';
                        }
                    }
                }

                if (finalScore !== 'N/A') {
                    requestAnimationFrame(animateScore);
                } else {
                    scoreElement.textContent = 'N/A';
                    document.querySelector('#animatedScore + span').textContent = '';
                    resultStatusElement.textContent = 'Not Completed';
                    resultFeedbackElement.textContent = 'This assessment was not completed.';
                }

                const toggleButton = document.getElementById('toggleAnswersButton');
                const questionsAnswersSection = document.getElementById('questionsAnswersSection');
                toggleButton.addEventListener('click', function() {
                    questionsAnswersSection.classList.toggle('hidden');
                    toggleButton.innerHTML = questionsAnswersSection.classList.contains('hidden')
                        ? '<i class="fas fa-eye mr-2"></i> View Questions & Answers'
                        : '<i class="fas fa-eye-slash mr-2"></i> Hide Questions & Answers';
                });
            });
        </script>

    <?php else: ?>
        <div class="flex flex-col sm:flex-row justify-end items-stretch sm:items-center gap-2 mb-4">
            <div class="relative flex-grow">
                <input type="text" name="search" id="search_input"
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       class="form-input w-3/4 rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50 pl-10 py-3 text-lg"
                       placeholder="Search assessments...">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <i class="fas fa-search"></i>
                </span>
            </div>
            <button id="openFilterModal" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-300 flex items-center justify-center sm:w-auto w-full">
                <i class="fas fa-filter mr-2"></i> Filter
            </button>
            <a href="assessments.php" class="bg-gray-400 text-white px-4 py-2 rounded-md hover:bg-gray-500 transition duration-300 ease-in-out flex items-center justify-center sm:w-auto w-full">
                <i class="fas fa-undo mr-2"></i> Reset
            </a>
        </div>

        <div id="filterModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-lg mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Filter Quiz Attempts</h2>
                    <button id="closeFilterModal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form id="filterForm" action="assessments.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="search" id="modal_search_input" value="<?php echo htmlspecialchars($search_query); ?>">
                    <div>
                        <label for="quiz_id" class="block text-sm font-medium text-gray-700 mb-1">Assessment:</label>
                        <select name="quiz_id" id="quiz_id"
                                class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50 select2-enabled"
                                data-placeholder="All Assessments" data-allow-clear="true">
                            <option value=""></option>
                            <?php foreach ($all_quizzes_for_filters as $quiz_filter): ?>
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
                        <button type="button" id="resetFiltersModal" class="bg-gray-400 text-white px-6 py-2 rounded-md hover:bg-gray-500 transition duration-300 ease-in-out flex items-center justify-center">
                            <i class="fas fa-undo mr-2"></i> Reset Filters
                        </button>
                    </div>
                </form>
            </div>
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
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Title</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score (%)</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-2 whitespace-nowrap text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const openFilterModalBtn = document.getElementById('openFilterModal');
                const closeFilterModalBtn = document.getElementById('closeFilterModal');
                const filterModal = document.getElementById('filterModal');
                const searchInput = document.getElementById('search_input');
                const modalSearchInput = document.getElementById('modal_search_input');
                const filterForm = document.getElementById('filterForm');
                const resetFiltersModalBtn = document.getElementById('resetFiltersModal');

                openFilterModalBtn.addEventListener('click', function() {
                    modalSearchInput.value = searchInput.value;
                    filterModal.classList.remove('hidden');
                });

                closeFilterModalBtn.addEventListener('click', function() {
                    filterModal.classList.add('hidden');
                });

                filterModal.addEventListener('click', function(event) {
                    if (event.target === filterModal) {
                        filterModal.classList.add('hidden');
                    }
                });

                searchInput.addEventListener('input', function() {
                    modalSearchInput.value = searchInput.value;
                });

                searchInput.addEventListener('keypress', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        const currentParams = new URLSearchParams(window.location.search);
                        currentParams.set('search', searchInput.value);
                        currentParams.delete('attempt_id');
                        window.location.search = currentParams.toString();
                    }
                });

                resetFiltersModalBtn.addEventListener('click', function() {
                    document.getElementById('quiz_id').value = '';
                    document.getElementById('min_percentage').value = '';
                    document.getElementById('start_date').value = '';
                    document.getElementById('end_date').value = '';
                    filterForm.submit();
                });
            });
        </script>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer_student.php';
?>