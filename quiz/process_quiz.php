<?php
// quiz/process_quiz.php
// Handles the submission, grading, storage, and email notification for quizzes.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/send_email.php'; // Include sendEmail function

$message = '';
$quiz_id = null;
$attempt_id = null;
$is_public_quiz = false;
$user_id = getUserId(); // Will be null if not logged in

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php'); // Redirect if accessed directly without POST data
}

// Get POST data
$quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
$attempt_id = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT); // Only for logged-in quizzes
$is_public_quiz = (isset($_POST['is_public_quiz']) && $_POST['is_public_quiz'] == 1);
$submitted_answers = $_POST['answers'] ?? []; // Array of question_id => answer

if (!$quiz_id) {
    $message = display_message("Quiz ID not provided.", "error");
    if (!$is_public_quiz && !empty($user_id)) {
        $_SESSION['message'] = $message;
        redirect('../student/dashboard.php');
    }
}

$quiz = null;
$total_score_possible = 0;
$student_score = 0;
$graded_answers = []; // To store results for display

try {
    // Fetch quiz details
    $stmt = $pdo->prepare("SELECT quiz_id, title, description FROM quizzes WHERE quiz_id = :quiz_id");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        throw new Exception("Quiz not found.");
    }

    // Fetch all questions and their correct answers for this quiz
    $stmt_questions = $pdo->prepare("
        SELECT q.question_id, q.question_type, q.score, q.question_text,
               GROUP_CONCAT(CONCAT(o.option_id, '||', o.is_correct, '||', o.option_text) SEPARATOR ';;') as options_data
        FROM questions q
        LEFT JOIN options o ON q.question_id = o.question_id
        WHERE q.quiz_id = :quiz_id
        GROUP BY q.question_id
    ");
    $stmt_questions->execute(['quiz_id' => $quiz_id]);
    $correct_answers_data = $stmt_questions->fetchAll(PDO::FETCH_ASSOC);

    $correct_answers_map = []; // Map question_id to correct answer(s) and type
    foreach ($correct_answers_data as $q_data) {
        $question_id = $q_data['question_id'];
        $question_type = $q_data['question_type'];
        $score = $q_data['score'];
        $total_score_possible += $score;

        $correct_value = null;
        if ($question_type === 'multiple_choice') {
            $options_raw = $q_data['options_data'] ? explode(';;', $q_data['options_data']) : [];
            $correct_option_ids = [];
            foreach ($options_raw as $opt_str) {
                if (strpos($opt_str, '||') === false) continue;
                list($opt_id, $is_correct, $opt_text) = explode('||', $opt_str, 3);
                if ($is_correct == 1) {
                    $correct_option_ids[] = (int)$opt_id;
                }
            }
            $correct_value = $correct_option_ids;
        } elseif ($question_type === 'true_false') {
            $options_raw = $q_data['options_data'] ? explode(';;', $q_data['options_data']) : [];
            foreach ($options_raw as $opt_str) {
                if (strpos($opt_str, '||') === false) continue;
                list($opt_id, $is_correct, $opt_text) = explode('||', $opt_str, 3);
                if ($is_correct == 1) {
                    $correct_value = strtolower($opt_text) === 'true' ? 'true' : 'false';
                    break;
                }
            }
        } elseif ($question_type === 'short_answer') {
            $options_raw = $q_data['options_data'] ? explode(';;', $q_data['options_data']) : [];
            foreach ($options_raw as $opt_str) {
                if (strpos($opt_str, '||') === false) continue;
                list($opt_id, $is_correct, $opt_text) = explode('||', $opt_str, 3);
                if ($is_correct == 1) {
                    $correct_value = $opt_text;
                    break;
                }
            }
        }
        // Essay questions require manual grading

        $correct_answers_map[$question_id] = [
            'type' => $question_type,
            'correct_value' => $correct_value,
            'score' => $score,
            'question_text' => $q_data['question_text']
        ];
    }

    // Grade the submitted answers
    foreach ($submitted_answers as $question_id => $answer_value) {
        $question_id = filter_var($question_id, FILTER_VALIDATE_INT);
        if (!$question_id) continue;
        $answer_value = sanitize_input($answer_value);

        $is_correct = false;
        $awarded_score = 0;

        if (isset($correct_answers_map[$question_id])) {
            $q_info = $correct_answers_map[$question_id];
            $question_type = $q_info['type'];
            $question_score = $q_info['score'];

            if ($question_type === 'multiple_choice') {
                $submitted_option_id = (int)$answer_value;
                if ($submitted_option_id && in_array($submitted_option_id, $q_info['correct_value'] ?? [])) {
                    $is_correct = true;
                    $awarded_score = $question_score;
                }
            } elseif ($question_type === 'true_false') {
                if ($answer_value === $q_info['correct_value']) {
                    $is_correct = true;
                    $awarded_score = $question_score;
                }
            } elseif ($question_type === 'short_answer') {
                if ($q_info['correct_value'] && strtolower(trim($answer_value)) === strtolower(trim($q_info['correct_value']))) {
                    $is_correct = true;
                    $awarded_score = $question_score;
                }
            } elseif ($question_type === 'essay') {
                $is_correct = null; // Requires manual grading
                $awarded_score = 0;
            }

            $student_score += $awarded_score;

            // Prepare graded answer for display/storage
            $graded_answers[] = [
                'question_id' => $question_id,
                'question_text' => $q_info['question_text'],
                'question_type' => $question_type,
                'submitted_answer' => $answer_value,
                'is_correct' => $is_correct,
                'awarded_score' => $awarded_score,
                'max_score' => $question_score,
                'correct_value' => $q_info['correct_value']
            ];
        }
    }

    // --- Database Operations and Email for Logged-in Users ---
    if (!$is_public_quiz && $user_id && $attempt_id) {
        // Verify attempt
        $stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE attempt_id = :attempt_id AND user_id = :user_id AND quiz_id = :quiz_id AND is_completed = 0");
        $stmt->execute(['attempt_id' => $attempt_id, 'user_id' => $user_id, 'quiz_id' => $quiz_id]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            throw new Exception("Invalid or already completed attempt.");
        }

        // Update quiz attempt
        $stmt_update_attempt = $pdo->prepare("UPDATE quiz_attempts SET end_time = NOW(), score = :score, is_completed = 1 WHERE attempt_id = :attempt_id AND user_id = :user_id");
        $stmt_update_attempt->execute(['score' => $student_score, 'attempt_id' => $attempt_id, 'user_id' => $user_id]);

        // Insert answers
        $stmt_insert_answer = $pdo->prepare("
            INSERT INTO answers (attempt_id, question_id, selected_option_id, answer_text, is_correct, submitted_at)
            VALUES (:attempt_id, :question_id, :selected_option_id, :answer_text, :is_correct, NOW())
        ");
        foreach ($graded_answers as $g_answer) {
            $selected_option_id = ($g_answer['question_type'] === 'multiple_choice') ? (int)$g_answer['submitted_answer'] : null;
            $answer_text_val = ($g_answer['question_type'] !== 'multiple_choice') ? $g_answer['submitted_answer'] : null;

            $stmt_insert_answer->execute([
                'attempt_id' => $attempt_id,
                'question_id' => $g_answer['question_id'],
                'selected_option_id' => $selected_option_id,
                'answer_text' => $answer_text_val,
                'is_correct' => $g_answer['is_correct'] === null ? null : ($g_answer['is_correct'] ? 1 : 0)
            ]);
        }

        // Fetch user details for email
        $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Calculate percentage
            $percentage = $total_score_possible > 0 ? round(($student_score / $total_score_possible) * 100, 2) : 0;
            $max_score = $total_score_possible; // Define max_score for template

            // Load email template
            ob_start();
            include '../includes/email_templates/assessment_results.php';
            $email_body = ob_get_clean();

            // Send email
            $subject = "Your Assessment Results for {$quiz['title']}";
            $alt_body = "You completed the assessment '{$quiz['title']}'.\nScore: {$student_score} / {$total_score_possible}\nPercentage: {$percentage}%\nStatus: " . ($percentage >= 70 ? 'Great' : 'Needs Improvement');
            if (!sendEmail($user['email'], $subject, $email_body, $alt_body)) {
                error_log("Failed to send assessment result email to {$user['email']} for quiz_id: {$quiz_id}, attempt_id: {$attempt_id}");
                $message = display_message("Assessment submitted, but failed to send result email.", "warning");
            } else {
                $message = display_message("Assessment submitted successfully! Your score: {$student_score} / {$total_score_possible} ({$percentage}%)", "success");
            }
        } else {
            error_log("Could not fetch user details for email. User ID: {$user_id}");
            $message = display_message("Assessment submitted, but failed to retrieve user details for email notification.", "warning");
        }

        // Redirect to assessments page
        $_SESSION['message'] = $message;
        redirect('../student/assessments.php?attempt_id=' . $attempt_id);
    }

} catch (Exception $e) {
    error_log("Process Quiz Error: " . $e->getMessage());
    $message = display_message("An error occurred while processing your quiz: " . htmlspecialchars($e->getMessage()), "error");
    if (!$is_public_quiz && $user_id) {
        $_SESSION['message'] = $message;
        redirect('../student/dashboard.php');
    }
}


?>

<div class="container mx-auto p-4 py-8">
    <div class="max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">Quiz Results for "<?php echo htmlspecialchars($quiz['title'] ?? 'Unknown Quiz'); ?>"</h1>

        <?php echo $message; ?>

        <div class="text-center mb-6">
            <p class="text-2xl font-semibold text-gray-800">Your Score: <span class="text-theme-color"><?php echo htmlspecialchars($student_score); ?></span> / <?php echo htmlspecialchars($total_score_possible); ?></p>
            <?php if ($is_public_quiz || empty($user_id)): ?>
                <p class="text-md text-gray-600">This attempt was not saved as you were not logged in.</p>
            <?php else: ?>
                <p class="text-md text-gray-600">Your quiz results have been saved.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($graded_answers)): ?>
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Answers:</h2>
            <div class="space-y-6">
                <?php foreach ($graded_answers as $index => $answer): ?>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <p class="font-semibold text-gray-900 mb-2">Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?></p>
                        <p class="text-sm text-gray-700">Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $answer['question_type']))); ?> | Max Score: <?php echo htmlspecialchars($answer['max_score']); ?></p>

                        <?php if ($answer['question_type'] === 'multiple_choice'): ?>
                            <p class="text-gray-700 mt-2"><strong>Your Answer:</strong>
                                <?php
                                    $selected_option_text = 'No answer';
                                    if (!empty($answer['submitted_answer'])) {
                                        foreach ($correct_answers_data as $q_data) {
                                            if ($q_data['question_id'] == $answer['question_id'] && $q_data['options_data']) {
                                                $options_raw = explode(';;', $q_data['options_data']);
                                                foreach ($options_raw as $opt_str) {
                                                    if (strpos($opt_str, '||') === false) continue;
                                                    list($opt_id, $is_correct, $opt_text) = explode('||', $opt_str, 3);
                                                    if ((int)$opt_id == (int)$answer['submitted_answer']) {
                                                        $selected_option_text = htmlspecialchars($opt_text);
                                                        break 2;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    echo $selected_option_text;
                                ?>
                            </p>
                            <p class="text-gray-700"><strong>Correct Answer(s):</strong>
                                <?php
                                    $correct_options_display = [];
                                    if (!empty($answer['correct_value'])) {
                                        foreach ($correct_answers_data as $q_data) {
                                            if ($q_data['question_id'] == $answer['question_id'] && $q_data['options_data']) {
                                                $options_raw = explode(';;', $q_data['options_data']);
                                                foreach ($options_raw as $opt_str) {
                                                    if (strpos($opt_str, '||') === false) continue;
                                                    list($opt_id, $is_correct, $opt_text) = explode('||', $opt_str, 3);
                                                    if ($is_correct == 1) {
                                                        $correct_options_display[] = htmlspecialchars($opt_text);
                                                    }
                                                }
                                                break;
                                            }
                                        }
                                    }
                                    echo !empty($correct_options_display) ? implode(', ', $correct_options_display) : 'N/A';
                                ?>
                            </p>
                            <p class="text-sm mt-1"><strong>Result:</strong>
                                <?php if ($answer['is_correct'] === true): ?>
                                    <span class="text-green-600 font-semibold">Correct</span> (Awarded: <?php echo htmlspecialchars($answer['awarded_score']); ?> points)
                                <?php elseif ($answer['is_correct'] === false): ?>
                                    <span class="text-red-600 font-semibold">Incorrect</span> (Awarded: <?php echo htmlspecialchars($answer['awarded_score']); ?> points)
                                <?php else: ?>
                                    <span class="text-gray-500 font-semibold">Awaiting Grading</span> (Awarded: <?php echo htmlspecialchars($answer['awarded_score']); ?> points)
                                <?php endif; ?>
                            </p>
                        <?php elseif ($answer['question_type'] === 'short_answer' || $answer['question_type'] === 'essay'): ?>
                            <p class="text-gray-700 mt-2"><strong>Your Answer:</strong></p>
                            <div class="bg-white p-3 rounded-md border text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($answer['submitted_answer'] ?? 'No answer'); ?></div>
                            <p class="text-sm mt-1"><strong>Status:</strong> <span class="text-gray-500 font-semibold">Awaiting Manual Grading</span></p>
                            <p class="text-sm text-gray-500"><em>This type of question requires an administrator to review and grade.</em></p>
                        <?php elseif ($answer['question_type'] === 'true_false'): ?>
                            <p class="text-gray-700 mt-2"><strong>Your Answer:</strong> <?php echo htmlspecialchars(ucfirst($answer['submitted_answer'] ?? 'No answer')); ?></p>
                            <p class="text-sm mt-1"><strong>Result:</strong>
                                <?php if ($answer['is_correct'] === true): ?>
                                    <span class="text-green-600 font-semibold">Correct</span> (Awarded: <?php echo htmlspecialchars($answer['awarded_score']); ?> points)
                                <?php elseif ($answer['is_correct'] === false): ?>
                                    <span class="text-red-600 font-semibold">Incorrect</span> (Awarded: <?php echo htmlspecialchars($answer['awarded_score']); ?> points)
                                <?php else: ?>
                                    <span class="text-gray-500 font-semibold">Awaiting Grading</span> (Awarded: <?php echo htmlspecialchars($answer['awarded_score']); ?> points)
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-600 text-center">No answers were submitted for this quiz.</p>
        <?php endif; ?>

        <div class="text-center mt-8">
            <?php if ($is_public_quiz): ?>
                <a href="../index.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                    Back to Home
                </a>
            <?php else: ?>
                <a href="../student/dashboard.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                    Back to Dashboard
                </a>
                <a href="../student/assessments.php?attempt_id=<?php echo htmlspecialchars($attempt_id); ?>" class="inline-block ml-4 bg-gray-600 text-white px-6 py-3 rounded-md hover:bg-gray-700 transition duration-300">
                    View Full Attempt History
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

