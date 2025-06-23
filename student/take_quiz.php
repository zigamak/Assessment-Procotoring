<?php
// student/take_quiz.php
// Allows students to take a specific quiz. Integrates client-side timer and proctoring features.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$quiz = null;
$questions = [];
$current_attempt_id = null;
$quiz_data_loaded = false; // Flag to check if quiz questions are loaded
$user_id = getUserId(); // Get the logged-in student's user_id

// Ensure quiz_id is provided in the URL
if (!isset($_GET['quiz_id']) || !is_numeric($_GET['quiz_id'])) {
    $message = display_message("No quiz selected or invalid quiz ID.", "error");
    // Optionally redirect to dashboard or available quizzes list
    // redirect(BASE_URL . 'student/dashboard.php');
} else {
    $quiz_id = sanitize_input($_GET['quiz_id']);

    try {
        // Fetch quiz details. Ensure it's not a public quiz if we are in logged_in_quiz.php,
        // although an admin can also assign public quizzes to logged in users if desired.
        // For strict separation, we check is_public = 0 here for logged-in quizzes.
        $stmt = $pdo->prepare("SELECT quiz_id, title, description, is_public, max_attempts, duration_minutes FROM quizzes WHERE quiz_id = :quiz_id AND is_public = 0");
        $stmt->execute(['quiz_id' => $quiz_id]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) {
            $message = display_message("Quiz not found or is not accessible for logged-in users.", "error");
        } else {
            // Check student eligibility for the quiz (max attempts)
            $stmt_attempts_count = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = :user_id AND quiz_id = :quiz_id");
            $stmt_attempts_count->execute(['user_id' => $user_id, 'quiz_id' => $quiz_id]);
            $attempts_taken = $stmt_attempts_count->fetchColumn();

            if ($quiz['max_attempts'] !== 0 && $attempts_taken >= $quiz['max_attempts']) {
                $message = display_message("You have exceeded the maximum attempts for this quiz.", "error");
                $quiz = null; // Prevent quiz from being displayed
            } else {
                // Fetch questions for the quiz, randomize them
                $stmt = $pdo->prepare("
                    SELECT q.question_id, q.question_text, q.question_type, q.score, q.image_url,
                           GROUP_CONCAT(CONCAT(o.option_id, '||', o.option_text) SEPARATOR ';;') as options_data
                    FROM questions q
                    LEFT JOIN options o ON q.question_id = o.question_id
                    WHERE q.quiz_id = :quiz_id
                    GROUP BY q.question_id
                    ORDER BY RAND() -- Randomize questions for each attempt
                ");
                $stmt->execute(['quiz_id' => $quiz_id]);
                $raw_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Format questions with their options
                foreach ($raw_questions as $raw_q) {
                    $question = [
                        'question_id' => $raw_q['question_id'],
                        'question_text' => $raw_q['question_text'],
                        'question_type' => $raw_q['question_type'],
                        'score' => $raw_q['score'],
                        'image_url' => $raw_q['image_url'],
                        'options' => []
                    ];
                    if ($raw_q['question_type'] === 'multiple_choice' && $raw_q['options_data']) {
                        $options_raw = explode(';;', $raw_q['options_data']);
                        shuffle($options_raw); // Randomize options as well
                        foreach ($options_raw as $opt_str) {
                            list($opt_id, $opt_text) = explode('||', $opt_str);
                            $question['options'][] = [
                                'option_id' => (int)$opt_id,
                                'option_text' => $opt_text
                            ];
                        }
                    }
                    $questions[] = $question;
                }

                if (empty($questions)) {
                    $message = display_message("This quiz has no questions yet. Please contact your administrator.", "info");
                    $quiz = null; // Don't allow taking an empty quiz
                } else {
                    // Start a new quiz attempt in the database
                    // This creates the initial record. The end_time and score will be updated on submission.
                    $stmt_start_attempt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, start_time, is_completed) VALUES (:user_id, :quiz_id, NOW(), 0)");
                    $stmt_start_attempt->execute(['user_id' => $user_id, 'quiz_id' => $quiz_id]);
                    $current_attempt_id = $pdo->lastInsertId();
                    $quiz_data_loaded = true; // Quiz data is ready to be displayed and proctoring can start
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Take Quiz Error: " . $e->getMessage());
        $message = display_message("An error occurred while preparing the quiz. Please try again later.", "error");
        $quiz = null;
    }
}
?>

<!-- Include TensorFlow.js and BlazeFace Model -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface"></script>

<!-- Include Proctoring CSS -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/proctoring.css">

<div class="container mx-auto p-4 py-8">
    <?php echo $message; // Display any feedback messages ?>

    <?php if ($quiz && $quiz_data_loaded): ?>
        <!-- Proctoring Section (Always visible at the top) -->
        <?php include '../public/html/proctoring_widget.html'; ?>

        <!-- Quiz Section (Initially hidden, enabled by proctoring.js) -->
        <div id="quizContent" class="hidden">
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h1 class="text-3xl font-bold text-theme-color mb-4"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                <p class="text-gray-700 mb-4"><?php echo htmlspecialchars($quiz['description']); ?></p>

                <div class="flex justify-between items-center mb-6">
                    <div class="text-lg font-semibold text-gray-800">
                        Time Remaining: <span id="quiz-timer" class="text-red-600">
                            <?php // Display initial time if available, otherwise "No Limit"
                                if ($quiz['duration_minutes']) {
                                    echo str_pad($quiz['duration_minutes'], 2, '0', STR_PAD_LEFT) . ':00';
                                } else {
                                    echo 'No Limit';
                                }
                            ?>
                        </span>
                    </div>
                    <div class="text-lg font-semibold text-gray-800">
                        Question <span id="current-question-number">1</span> of <span id="total-questions"></span>
                    </div>
                </div>

                <form id="quizForm" action="<?php echo BASE_URL; ?>quiz/process_quiz.php" method="POST" class="space-y-8">
                    <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($quiz['quiz_id']); ?>">
                    <input type="hidden" name="attempt_id" value="<?php echo htmlspecialchars($current_attempt_id); ?>">
                    <input type="hidden" name="is_public_quiz" value="0">
                    <input type="hidden" id="current-question-index" value="0">
                    <input type="hidden" id="questions-data" value="<?php echo htmlspecialchars(json_encode($questions), ENT_QUOTES, 'UTF-8'); ?>">

                    <div id="question-container" class="bg-gray-50 p-6 rounded-lg shadow-sm border border-gray-200">
                        <!-- Question will be loaded here by JavaScript -->
                        <p class="text-center text-gray-600">Loading question...</p>
                    </div>

                    <div class="flex justify-between items-center mt-8">
                        <button type="button" id="prev-btn"
                                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
                            Previous
                        </button>
                        <button type="button" id="next-btn"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                        <button type="submit" id="submit-btn" style="display: none;"
                                class="bg-green-700 hover:bg-green-800 text-white font-bold py-3 px-8 rounded-md text-xl focus:outline-none focus:shadow-outline transition duration-300">
                            Submit Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Quiz Not Available</h2>
            <p class="text-gray-600">
                <?php echo $message ?: "The quiz you are trying to access is not available or you are not eligible."; ?>
            </p>
            <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                Back to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Include Proctoring JavaScript -->
<script src="<?php echo BASE_URL; ?>public/js/proctoring.js"></script>
<script>// --- Quiz-specific JavaScript (controls rendering and timer) ---
const quizData = JSON.parse(document.getElementById('questions-data')?.value || '[]');
let currentQuestionIndex = parseInt(document.getElementById('current-question-index')?.value || '0');
const questionContainer = document.getElementById('question-container');
const prevBtn = document.getElementById('prev-btn');
const nextBtn = document.getElementById('next-btn');
const submitBtn = document.getElementById('submit-btn');
const currentQuestionNumberSpan = document.getElementById('current-question-number');
const totalQuestionsSpan = document.getElementById('total-questions');
const quizForm = document.getElementById('quizForm');
const quizContentDiv = document.getElementById('quizContent'); // The main quiz content div

let quizDurationMinutes = <?php echo json_encode($quiz['duration_minutes'] ?? 0); ?>;
let timeRemainingSeconds = quizDurationMinutes * 60;
let timerInterval;
const quizTimerDisplay = document.getElementById('quiz-timer');

// Store student's answers (keyed by question_id)
const studentAnswers = {};

function renderQuestion(index) {
    if (index < 0 || index >= quizData.length) {
        console.error('Invalid question index:', index);
        return;
    }

    const question = quizData[index];
    let html = `
        <p class="text-lg font-semibold text-gray-900 mb-3">
            Question ${index + 1}: ${question.question_text}
            <span class="text-sm font-normal text-gray-600">(Type: ${question.question_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())} - ${question.score} points)</span>
        </p>
    `;

    if (question.image_url) {
        html += `
            <div class="mb-4 text-center">
                <img src="${question.image_url}" alt="Question Image" class="max-w-full h-auto rounded-md shadow-md mx-auto" onerror="this.onerror=null;this.src='https://placehold.co/400x200/cccccc/333333?text=Image+Not+Found';">
            </div>
        `;
    }

    html += `<div class="mt-4 space-y-2">`;

    const answerName = `answers[${question.question_id}]`;
    const currentAnswer = studentAnswers[question.question_id];

    if (question.question_type === 'multiple_choice') {
        question.options.forEach(option => {
            const isChecked = currentAnswer === String(option.option_id) ? 'checked' : '';
            html += `
                <label class="flex items-center bg-white p-3 rounded-md border hover:bg-gray-100 cursor-pointer">
                    <input type="radio" name="${answerName}" value="${option.option_id}" ${isChecked} class="form-radio h-5 w-5 text-green-600">
                    <span class="ml-3 text-gray-800">${option.option_text}</span>
                </label>
            `;
        });
    } else if (question.question_type === 'true_false') {
        const trueChecked = currentAnswer === 'true' ? 'checked' : '';
        const falseChecked = currentAnswer === 'false' ? 'checked' : '';
        html += `
            <label class="flex items-center bg-white p-3 rounded-md border hover:bg-gray-100 cursor-pointer">
                <input type="radio" name="${answerName}" value="true" ${trueChecked} class="form-radio h-5 w-5 text-green-600">
                <span class="ml-3 text-gray-800">True</span>
            </label>
            <label class="flex items-center bg-white p-3 rounded-md border hover:bg-gray-100 cursor-pointer">
                <input type="radio" name="${answerName}" value="false" ${falseChecked} class="form-radio h-5 w-5 text-green-600">
                <span class="ml-3 text-gray-800">False</span>
            </label>
        `;
    } else if (question.question_type === 'short_answer') {
        const answerValue = currentAnswer || '';
        html += `
            <textarea name="${answerName}" rows="2" placeholder="Type your short answer here..."
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">${answerValue}</textarea>
        `;
    } else if (question.question_type === 'essay') {
        const answerValue = currentAnswer || '';
        html += `
            <textarea name="${answerName}" rows="6" placeholder="Type your essay answer here..."
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">${answerValue}</textarea>
        `;
    }
    html += `</div>`;
    questionContainer.innerHTML = html;
    updateNavigationButtons();
    updateQuestionNumber();

    // Attach event listener to save answer when input changes
    const currentQuestionInputs = questionContainer.querySelectorAll('input, textarea');
    currentQuestionInputs.forEach(input => {
        input.addEventListener('change', (event) => {
            if (input.type === 'radio') {
                studentAnswers[question.question_id] = event.target.value;
            } else {
                studentAnswers[question.question_id] = event.target.value;
            }
        });
    });
}

function updateNavigationButtons() {
    prevBtn.disabled = currentQuestionIndex === 0;
    nextBtn.disabled = currentQuestionIndex === quizData.length - 1;

    if (currentQuestionIndex === quizData.length - 1) {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-block';
    } else {
        nextBtn.style.display = 'inline-block';
        submitBtn.style.display = 'none';
    }
}

function updateQuestionNumber() {
    currentQuestionNumberSpan.textContent = currentQuestionIndex + 1;
    totalQuestionsSpan.textContent = quizData.length;
}

// --- Navigation Handlers ---
prevBtn?.addEventListener('click', () => {
    if (currentQuestionIndex > 0) {
        currentQuestionIndex--;
        renderQuestion(currentQuestionIndex);
    }
});

nextBtn?.addEventListener('click', () => {
    if (currentQuestionIndex < quizData.length - 1) {
        currentQuestionIndex++;
        renderQuestion(currentQuestionIndex);
    }
});

// --- Quiz Timer Logic ---
function startQuizTimer() {
    if (quizDurationMinutes > 0 && !timerInterval) { // Only start if duration > 0 and not already running
        timerInterval = setInterval(() => {
            timeRemainingSeconds--;
            if (quizTimerDisplay) {
                const minutes = Math.floor(timeRemainingSeconds / 60);
                const seconds = timeRemainingSeconds % 60;
                quizTimerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }

            if (timeRemainingSeconds <= 0) {
                clearInterval(timerInterval);
                // Time's up, automatically submit the quiz
                proctoringShowCustomMessageBox('Time is up!', 'Your quiz will be submitted automatically.', () => {
                    submitQuizForm();
                });
            }
        }, 1000);
    }
}


// --- Form Submission Logic ---
function submitQuizForm() {
    // Prevent multiple submissions if already in progress
    if (quizForm.hasAttribute('data-submitted') && quizForm.getAttribute('data-submitted') === 'true') {
        console.warn('Quiz form already submitted or submission in progress. Ignoring.');
        return;
    }

    // Create hidden inputs for all collected answers and append to form
    for (const question_id in studentAnswers) {
        if (studentAnswers.hasOwnProperty(question_id)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `answers[${question_id}]`;
            input.value = studentAnswers[question_id];
            quizForm.appendChild(input);
        }
    }
    // Add a flag to indicate the form was submitted
    quizForm.setAttribute('data-submitted', 'true');
    quizForm.submit();
}

quizForm?.addEventListener('submit', (event) => {
    event.preventDefault(); // Prevent default submission
    clearInterval(timerInterval); // Stop timer on manual submission
    submitQuizForm(); // Manually submit after adding hidden fields
});


// --- Integration with Proctoring (Callbacks) ---
const attemptId = <?php echo json_encode($current_attempt_id); ?>;
const userId = <?php echo json_encode($user_id); ?>;

function onProctoringConditionsMet() {
    if (quizContentDiv.classList.contains('hidden')) { // Only show once
        quizContentDiv.classList.remove('hidden');
        quizContentDiv.classList.add('active'); // Add active class if you want transitions
        // Start quiz timer only when proctoring starts successfully
        if (quizDurationMinutes > 0 && !timerInterval) {
            startQuizTimer();
        }
        // Ensure initial question is rendered when quiz is first enabled
        if (quizData.length > 0 && questionContainer.innerHTML.includes('Loading question...')) {
            renderQuestion(currentQuestionIndex);
        }
    }
    // Enable quiz form inputs
    quizForm.querySelectorAll('input, textarea, button').forEach(el => {
        el.disabled = false;
        // Specifically re-evaluate nav buttons
        if (el.id === 'prev-btn' || el.id === 'next-btn' || el.id === 'submit-btn') {
            updateNavigationButtons(); // Re-enable specific buttons based on index
        }
    });
    quizForm.style.opacity = '1';
    quizForm.style.pointerEvents = 'auto';

    // Apply fullscreen styles to body
    document.body.classList.add('assessment-fullscreen');

    // If timer was paused (e.g., by a critical error which is now resolved, though unlikely)
    // or if it was initially set up but paused due to conditions not being met.
    // Ensure timer resumes if conditions are met AND it hasn't finished.
    if (timeRemainingSeconds > 0 && !timerInterval && quizDurationMinutes > 0) {
        startQuizTimer();
    }
}

function onProctoringConditionsViolated() {
    // Disable quiz form inputs
    quizForm.querySelectorAll('input, textarea, button').forEach(el => {
        el.disabled = true;
    });
    quizForm.style.opacity = '0.5';
    quizForm.style.pointerEvents = 'none';
    // Removed: clearInterval(timerInterval); // This line was removed as requested.

    // Remove fullscreen styles from body
    document.body.classList.remove('assessment-fullscreen');
}

function onProctoringCriticalError(message) {
    // Stop everything, quiz is terminated
    clearInterval(timerInterval); // Timer stops on critical error/termination
    quizForm.querySelectorAll('input, textarea, button').forEach(el => {
        el.disabled = true;
    });
    quizForm.style.opacity = '0.3';
    quizForm.style.pointerEvents = 'none';

    // Remove fullscreen styles from body
    document.body.classList.remove('assessment-fullscreen');

    // This is called when the assessment is terminated, including user canceling fullscreen.
    // As per the request, submit the quiz in this scenario.
    submitQuizForm(); // Submit the quiz immediately upon critical error/termination.

    // Display a message on the page if not already handled by modal
    const quizNotAvailableDiv = document.querySelector('.container .bg-white.p-6.rounded-lg.shadow-md.text-center');
    if (quizNotAvailableDiv) {
        quizNotAvailableDiv.innerHTML = `
                <h2 class="text-2xl font-bold text-red-600 mb-4">Assessment Terminated</h2>
                <p class="text-gray-700 mb-4">Reason: ${message}</p>
                <p class="text-gray-600">Your answers have been submitted. Please contact your administrator for further instructions.</p>
                <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                    Back to Dashboard
                </a>
            `;
        quizNotAvailableDiv.classList.remove('hidden'); // Ensure it's visible
        quizContentDiv.classList.add('hidden'); // Hide the quiz content
    }
}

async function sendProctoringLog(eventType, data = null) {
    // Convert the data to a string if it's an object/array, for simpler storage in 'log_data' TEXT field
    const logDataString = typeof data === 'object' ? JSON.stringify(data) : String(data);

    const payload = {
        attempt_id: attemptId,
        user_id: userId,
        event_type: eventType,
        log_data: logDataString
    };

    try {
        const response = await fetch('<?php echo BASE_URL; ?>student/proctoring_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Failed to send proctoring data:', response.status, errorText);
        }
    } catch (error) {
        console.error('Network error sending proctoring data:', error);
    }
}

// Custom message box for quiz timer (re-used from proctoring.js)
function proctoringShowCustomMessageBox(title, message, callback = null) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active'; // Re-use modal-overlay styling
    modal.innerHTML = `
            <div class="modal-content">
                <h3 class="modal-title">${title}</h3>
                <p class="modal-message">${message}</p>
                <button class="modal-button primary-button" id="quizMsgBoxOkBtn">OK</button>
            </div>
        `;
    document.body.appendChild(modal);

    document.getElementById('quizMsgBoxOkBtn').onclick = () => {
        modal.remove();
        if (callback) {
            callback();
        }
    };
}

// --- Main Page Initialization ---
window.addEventListener('DOMContentLoaded', () => {
    // Initialize proctoring system
    if (typeof initProctoring !== 'undefined') {
        initProctoring({
            onConditionsMet: onProctoringConditionsMet,
            onConditionsViolated: onProctoringConditionsViolated,
            onCriticalError: onProctoringCriticalError,
            sendProctoringLog: sendProctoringLog
        });
    } else {
        console.error("proctoring.js not loaded or initProctoring function not found.");
        onProctoringCriticalError("Proctoring system failed to initialize. Cannot start assessment.");
    }

    // Initially render first question (or placeholder) if quiz data is loaded
    if (quizData.length > 0) {
        renderQuestion(currentQuestionIndex);
        totalQuestionsSpan.textContent = quizData.length;
    } else {
        questionContainer.innerHTML = '<p class="text-center text-gray-600">No questions loaded for this quiz.</p>';
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'none';
    }

    // Initially disable quiz content until proctoring is ready
    onProctoringConditionsViolated();
});

// Cleanup when leaving the page (beforeunload)
window.onbeforeunload = function() {
    // Only clear timer if it's active AND the form hasn't been submitted (to avoid stopping timer
    // if submission is already in progress due to critical error or timeout)
    if (timerInterval && !quizForm.hasAttribute('data-submitted')) {
        clearInterval(timerInterval);
    }

    // Proctoring.js should handle its own camera/interval cleanup.
    // Log that the user left the quiz if not submitted
    if (attemptId && !quizForm.hasAttribute('data-submitted')) {
        sendProctoringLog('page_leave', 'User navigated away from the quiz page during attempt.');
    }
};
</script>
<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>
