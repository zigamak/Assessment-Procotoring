<?php
// quiz/process_quiz.php
// Handles the submission, grading, and storage (for logged-in users) of quizzes.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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
$quiz_id = sanitize_input($_POST['quiz_id'] ?? null);
$attempt_id = sanitize_input($_POST['attempt_id'] ?? null); // Only for logged-in quizzes
$is_public_quiz = (isset($_POST['is_public_quiz']) && $_POST['is_public_quiz'] == 1);
$submitted_answers = $_POST['answers'] ?? []; // Array of question_id => answer

if (!$quiz_id) {
    $message = display_message("Quiz ID not provided.", "error");
    // Display error and stop processing
    // (For public quiz, display immediately. For logged-in, might redirect to dashboard)
    if (!$is_public_quiz && !empty($user_id)) {
        redirect('../student/dashboard.php?message=quiz_id_missing');
    }
}

$quiz_title = "Unknown Quiz";
$total_score_possible = 0;
$student_score = 0;
$graded_answers = []; // To store results for display

try {
    // Fetch quiz details
    $stmt = $pdo->prepare("SELECT quiz_id, title, description FROM quizzes WHERE quiz_id = :quiz_id");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $quiz_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($quiz_details) {
        $quiz_title = htmlspecialchars($quiz_details['title']);
    }

    // Fetch all questions and their correct answers for this quiz
    // IMPORTANT: Added `q.question_text` to the SELECT statement to resolve the undefined array key warning.
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
            $options_raw = explode(';;', $q_data['options_data']);
            $correct_option_ids = [];
            foreach ($options_raw as $opt_str) {
                list($opt_id, $is_correct, $opt_text) = explode('||', $opt_str);
                if ($is_correct == 1) {
                    $correct_option_ids[] = (int)$opt_id;
                }
            }
            $correct_value = $correct_option_ids;
        } elseif ($question_type === 'true_false' || $question_type === 'short_answer') {
            // For true/false and short answer, the correct answer might be stored in a specific option
            // or implicitly handled. For simplicity, we'll assume here that short_answer/true_false
            // correct answers would ideally be a separate field in `questions` table or derived.
            // For now, let's just mark them as needing manual grading and not auto-correct.
            // If you had fixed correct answers for these, they'd be fetched here.
            $correct_value = null; // Manual grading for now
        }
        // Essay questions always require manual grading

        $correct_answers_map[$question_id] = [
            'type' => $question_type,
            'correct_value' => $correct_value,
            'score' => $score,
            'question_text' => $q_data['question_text'] // Store question text for results display
        ];
    }

    // Grade the submitted answers
    foreach ($submitted_answers as $question_id => $answer_value) {
        $question_id = sanitize_input($question_id);
        $answer_value = sanitize_input($answer_value); // For text answers

        $is_correct = false; // Default to false
        $awarded_score = 0;

        if (isset($correct_answers_map[$question_id])) {
            $q_info = $correct_answers_map[$question_id];
            $question_type = $q_info['type'];
            $question_score = $q_info['score'];

            if ($question_type === 'multiple_choice') {
                $submitted_option_id = (int)$answer_value;
                if (in_array($submitted_option_id, $q_info['correct_value'])) {
                    $is_correct = true;
                    $awarded_score = $question_score;
                }
            } elseif ($question_type === 'true_false' || $question_type === 'short_answer') {
                // For now, these are considered for manual grading unless explicit correct answers are added to schema/logic
                // If you had predefined true/false answers or short answer exact match:
                // if ($answer_value === $q_info['correct_value']) { $is_correct = true; $awarded_score = $question_score; }
                $is_correct = null; // Indicates needs manual review/grading
                $awarded_score = 0; // Default to 0 until manually graded
            } elseif ($question_type === 'essay') {
                $is_correct = null; // Indicates needs manual review/grading
                $awarded_score = 0; // Default to 0 until manually graded
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
                'correct_value' => $q_info['correct_value'] // For multiple choice, to show correct option text
            ];
        }
    }

    // --- Database Operations based on Quiz Type ---
    if (!$is_public_quiz && $user_id && $attempt_id) { // This is a logged-in quiz
        // Update the existing quiz attempt record
        $stmt_update_attempt = $pdo->prepare("UPDATE quiz_attempts SET end_time = NOW(), score = :score, is_completed = 1 WHERE attempt_id = :attempt_id AND user_id = :user_id");
        $stmt_update_attempt->execute(['score' => $student_score, 'attempt_id' => $attempt_id, 'user_id' => $user_id]);

        // Insert student's answers into the 'answers' table
        $stmt_insert_answer = $pdo->prepare("INSERT INTO answers (attempt_id, question_id, selected_option_id, answer_text, is_correct, submitted_at) VALUES (:attempt_id, :question_id, :selected_option_id, :answer_text, :is_correct, NOW())");
        foreach ($graded_answers as $g_answer) {
            $selected_option_id = null;
            $answer_text_val = null;

            if ($g_answer['question_type'] === 'multiple_choice') {
                $selected_option_id = (int)$g_answer['submitted_answer'];
            } else {
                $answer_text_val = $g_answer['submitted_answer'];
            }

            $stmt_insert_answer->execute([
                'attempt_id' => $attempt_id,
                'question_id' => $g_answer['question_id'],
                'selected_option_id' => $selected_option_id,
                'answer_text' => $answer_text_val,
                'is_correct' => $g_answer['is_correct'] // Will be null for essay/short_answer
            ]);
        }

        // Redirect logged-in users to their history page to see the results
        redirect('../student/assessments.php?attempt_id=' . $attempt_id . '&message=quiz_submitted');

    } else {
        // This is a public quiz, display results directly on this page
        // No database storage for this attempt since user is anonymous
    }

} catch (PDOException $e) {
    error_log("Process Quiz Error: " . $e->getMessage());
    $message = display_message("An error occurred while processing your quiz. Please try again later.", "error");
    // If it was a logged-in quiz and failed, attempt to redirect back to dashboard
    if (!$is_public_quiz && !empty($user_id)) {
        redirect('../student/dashboard.php?message=quiz_submission_failed');
    }
}

// For public quizzes, or if an error occurred for logged-in users before redirect,
// display the results directly on this page.
// The header_public.php is already included by public_quiz.php if quiz_id was passed directly.
// If an error occurred and redirected here from a logged-in quiz, header_public is fine too.
require_once '../includes/header_public.php'; // Ensure header is included for display
?>

<div class="container mx-auto p-4 py-8">
    <div class="max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">Quiz Results for "<?php echo $quiz_title; ?>"</h1>

        <?php echo $message; // Display any feedback messages ?>

        <?php if ($is_public_quiz || empty($user_id)): // For public quizzes or if logged-in user context lost ?>
            <div class="text-center mb-6">
                <p class="text-2xl font-semibold text-gray-800">Your Score: <span class="text-theme-color"><?php echo htmlspecialchars($student_score); ?></span> / <?php echo htmlspecialchars($total_score_possible); ?></p>
                <p class="text-md text-gray-600">This attempt was not saved as you were not logged in.</p>
            </div>
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Answers:</h2>
        <?php else: // For logged-in users who might see this due to an error before redirect ?>
            <div class="text-center mb-6">
                <p class="text-2xl font-semibold text-gray-800">Your Score: <span class="text-theme-color"><?php echo htmlspecialchars($student_score); ?></span> / <?php echo htmlspecialchars($total_score_possible); ?></p>
                <p class="text-md text-gray-600">Your quiz results have been saved.</p>
            </div>
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Answers:</h2>
        <?php endif; ?>

        <?php if (!empty($graded_answers)): ?>
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
                                        // Attempt to find the text for the selected option ID from the original question's options
                                        foreach ($correct_answers_data as $q_data_original) { // Use a different variable name
                                            if ($q_data_original['question_id'] == $answer['question_id'] && $q_data_original['options_data']) {
                                                $options_raw = explode(';;', $q_data_original['options_data']);
                                                foreach ($options_raw as $opt_str) {
                                                    list($opt_id, $is_correct_val, $opt_text) = explode('||', $opt_str);
                                                    if ((int)$opt_id == (int)$answer['submitted_answer']) {
                                                        $selected_option_text = htmlspecialchars($opt_text);
                                                        break 2; // Break out of both loops
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
                                    if (!empty($answer['correct_value'])) { // For MCQs, correct_value holds option IDs
                                        foreach ($correct_answers_data as $q_data_original) { // Use a different variable name
                                            if ($q_data_original['question_id'] == $answer['question_id'] && $q_data_original['options_data']) {
                                                $options_raw = explode(';;', $q_data_original['options_data']);
                                                foreach ($options_raw as $opt_str) {
                                                    list($opt_id, $is_correct_val, $opt_text) = explode('||', $opt_str);
                                                    if ($is_correct_val == 1) {
                                                        $correct_options_display[] = htmlspecialchars($opt_text);
                                                    }
                                                }
                                                break; // Break after finding options for the current question
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

<?php
// Include the public footer (works for both public and logged-in results display)
require_once '../includes/footer_public.php';
?>
