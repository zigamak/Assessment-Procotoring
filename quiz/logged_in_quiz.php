<?php
// quiz/logged_in_quiz.php
// Allows logged-in students to take a specific quiz.
// Implements a single-question-per-view navigation using JavaScript/AJAX.
// Includes client-side timer and proctoring hooks.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement and ensures user is logged in.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$quiz = null;
$questions = [];
$current_attempt_id = null;
$quiz_started_successfully = false; // Flag to indicate if the quiz attempt was successfully initiated
$user_id = getUserId(); // Get the logged-in student's user_id

// Ensure quiz_id is provided in the URL or display a list of quizzes
if (!isset($_GET['quiz_id']) || !is_numeric($_GET['quiz_id'])) {
    $message = display_message("No quiz selected. Please select a quiz from the list below.", "info");
    // If no quiz ID, display a list of available quizzes for the student
    try {
        // Fetch quizzes available to the student (both non-public and public ones not exhausted)
        $stmt = $pdo->prepare("
            SELECT quiz_id, title, description, max_attempts, duration_minutes
            FROM quizzes
            WHERE is_public = 0 OR is_public = 1 -- Allow logged-in users to see both types of quizzes
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $available_quizzes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $available_quizzes_filtered = [];
        foreach ($available_quizzes_raw as $q) {
            $quiz_id_check = $q['quiz_id'];

            // Get count of attempts for this quiz by this student
            $stmt_attempts_count = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = :user_id AND quiz_id = :quiz_id");
            $stmt_attempts_count->execute(['user_id' => $user_id, 'quiz_id' => $quiz_id_check]);
            $attempts_taken = $stmt_attempts_count->fetchColumn();

            // Only add if attempts are not exhausted (0 for unlimited)
            if ($q['max_attempts'] === 0 || $attempts_taken < $q['max_attempts']) {
                $q['attempts_taken'] = $attempts_taken;
                $available_quizzes_filtered[] = $q;
            }
        }
        $available_quizzes_for_list = $available_quizzes_filtered; // Use a distinct variable name
        $quiz = null; // Ensure quiz variable is null to trigger list display
    } catch (PDOException $e) {
        error_log("Logged-in Quiz List Error: " . $e->getMessage());
        $message = display_message("Could not fetch available quizzes. Please try again later.", "error");
    }

} else {
    $quiz_id = sanitize_input($_GET['quiz_id']);

    try {
        // Fetch quiz details. Allow logged-in users to take both public and non-public quizzes.
        $stmt = $pdo->prepare("SELECT quiz_id, title, description, is_public, max_attempts, duration_minutes FROM quizzes WHERE quiz_id = :quiz_id");
        $stmt->execute(['quiz_id' => $quiz_id]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) {
            $message = display_message("Quiz not found or is not accessible.", "error");
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
                    $quiz_started_successfully = true; // Set flag to true
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Logged-in Quiz Load Error: " . $e->getMessage());
        $message = display_message("An error occurred while preparing the quiz. Please try again later.", "error");
        $quiz = null;
    }
}
?>

<div class="container mx-auto p-4 py-8">
    <?php echo $message; // Display any feedback messages ?>

    <?php if ($quiz && $quiz_started_successfully): // Display the quiz if a valid quiz ID is provided and found ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h1 class="text-3xl font-bold text-theme-color mb-4"><?php echo htmlspecialchars($quiz['title']); ?></h1>
            <p class="text-gray-700 mb-4"><?php echo htmlspecialchars($quiz['description']); ?></p>

            <div class="flex justify-between items-center mb-6">
                <div class="text-lg font-semibold text-gray-800">
                    <?php if ($quiz['duration_minutes']): ?>
                        Time Remaining: <span id="quiz-timer" class="text-red-600"></span>
                    <?php else: ?>
                        No Time Limit
                    <?php endif; ?>
                </div>
                <div class="text-lg font-semibold text-gray-800">
                    Question <span id="current-question-number">1</span> of <span id="total-questions"></span>
                </div>
            </div>

            <form id="loggedInQuizForm" action="process_quiz.php" method="POST" class="space-y-8">
                <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($quiz['quiz_id']); ?>">
                <input type="hidden" name="attempt_id" value="<?php echo htmlspecialchars($current_attempt_id); ?>">
                <input type="hidden" name="is_public_quiz" value="<?php echo htmlspecialchars($quiz['is_public']); ?>"> <input type="hidden" id="current-question-index" value="0">
                <input type="hidden" id="questions-data" value="<?php echo htmlspecialchars(json_encode($questions), ENT_QUOTES, 'UTF-8'); ?>">

                <div id="question-container" class="bg-gray-50 p-6 rounded-lg shadow-sm border border-gray-200">
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
    <?php elseif (!isset($_GET['quiz_id']) && !empty($available_quizzes_for_list)): // Display list of available quizzes ?>
        <h1 class="text-3xl font-bold text-theme-color mb-6">Available Quizzes for You</h1>
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz Title</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts Left</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($available_quizzes_for_list as $q): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($q['title']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900 max-w-xs overflow-hidden text-ellipsis"><?php echo htmlspecialchars($q['description']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php
                                if ($q['max_attempts'] == 0) {
                                    echo "Unlimited";
                                } else {
                                    echo ($q['max_attempts'] - $q['attempts_taken']) . " of " . htmlspecialchars($q['max_attempts']);
                                }
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($q['duration_minutes'] ?: 'No Limit'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="logged_in_quiz.php?quiz_id=<?php echo htmlspecialchars($q['quiz_id']); ?>"
                               class="text-green-600 hover:text-green-900">Start Quiz</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Quiz Not Available</h2>
            <p class="text-gray-600">
                <?php echo $message ?: "The quiz you are trying to access is not available or you are not eligible to take it."; ?>
            </p>
            <a href="../student/dashboard.php" class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                Back to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
    // --- JavaScript for Quiz Logic (shared with public_quiz.php) ---
    const quizData = JSON.parse(document.getElementById('questions-data')?.value || '[]');
    let currentQuestionIndex = parseInt(document.getElementById('current-question-index')?.value || '0');
    const questionContainer = document.getElementById('question-container');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    const currentQuestionNumberSpan = document.getElementById('current-question-number');
    const totalQuestionsSpan = document.getElementById('total-questions');
    const quizForm = document.getElementById('loggedInQuizForm');

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
    function startTimer() {
        if (quizDurationMinutes > 0) {
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
                    showCustomMessageBox('Time is up!', 'Your quiz will be submitted automatically.', () => {
                        submitQuizAutomatically();
                    });
                }
            }, 1000);
        }
    }

    // --- Custom Message Box (instead of alert) ---
    function showCustomMessageBox(title, message, callback = null) {
        const modal = document.createElement('div');
        modal.id = 'customMessageBox';
        modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-sm text-center">
                <h3 class="text-xl font-bold mb-4 text-gray-900">${title}</h3>
                <p class="text-gray-700 mb-6">${message}</p>
                <button id="msgBoxOkBtn" class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    OK
                </button>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('msgBoxOkBtn').onclick = () => {
            modal.remove();
            if (callback) {
                callback();
            }
        };
    }

    function submitQuizAutomatically() {
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
        quizForm.submit();
    }


    // --- Form Submission Handler ---
    quizForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        clearInterval(timerInterval); // Stop timer on manual submission
        submitQuizAutomatically();
    });

    // --- Proctoring Features (specific to logged-in quizzes) ---
    let videoElement;
    let canvasElement;
    let context;
    let proctoringInterval;
    const WEBCAM_SNAPSHOT_INTERVAL = 10000; // 10 seconds

    // Variables from PHP for JavaScript use
    const attemptId = <?php echo json_encode($current_attempt_id); ?>; // Passed from PHP
    const userId = <?php echo json_encode($user_id); ?>; // Passed from PHP

    async function startWebcamProctoring() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            console.warn('Webcam not supported by this browser.');
            logProctoringEvent('webcam_not_supported', 'Browser does not support webcam access.');
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            videoElement = document.createElement('video');
            videoElement.srcObject = stream;
            videoElement.play();

            // Set up canvas for snapshot capture
            canvasElement = document.createElement('canvas');
            videoElement.onloadedmetadata = () => {
                canvasElement.width = videoElement.videoWidth;
                canvasElement.height = videoElement.videoHeight;
                context = canvasElement.getContext('2d');
            };

            proctoringInterval = setInterval(() => {
                if (context && videoElement.readyState === videoElement.HAVE_ENOUGH_DATA) {
                    context.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
                    const imageDataURL = canvasElement.toDataURL('image/jpeg', 0.7);
                    sendProctoringData('webcam_snapshot', imageDataURL);
                }
            }, WEBCAM_SNAPSHOT_INTERVAL);

            logProctoringEvent('webcam_started', 'Webcam proctoring initiated.');

        } catch (err) {
            console.error('Error accessing webcam: ', err);
            logProctoringEvent('webcam_access_denied', 'Webcam access denied or error: ' + err.name);
            showCustomMessageBox('Webcam Access Required', 'Webcam access is required for this assessment. Please allow camera access and refresh the page.', () => {
                   // Optionally redirect or disable quiz if access not granted
            });
        }
    }

    function setupTabSwitchDetection() {
        document.addEventListener("visibilitychange", () => {
            if (document.hidden) {
                logProctoringEvent('tab_switched_away', 'User switched away from the quiz tab.');
            } else {
                logProctoringEvent('tab_switched_back', 'User returned to the quiz tab.');
            }
        });
        logProctoringEvent('tab_monitoring_started', 'Tab switching monitoring initiated.');
    }

    async function sendProctoringData(eventType, data = null) {
        const payload = {
            attempt_id: attemptId,
            user_id: userId,
            event_type: eventType,
            log_data: data
        };

        try {
            const response = await fetch('../student/proctoring_data.php', {
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

    function logProctoringEvent(eventType, message = '') {
        sendProctoringData(eventType, message);
    }

    // --- Initialization on page load ---
    window.onload = function() {
        if (quizData.length > 0) {
            renderQuestion(currentQuestionIndex);
            if (quizDurationMinutes > 0) {
                startTimer();
            }
            // Start proctoring features only if a quiz is successfully started and it's a logged-in quiz
            <?php if ($quiz && $quiz_started_successfully): ?>
                startWebcamProctoring();
                setupTabSwitchDetection();
            <?php endif; ?>
        } else {
            questionContainer.innerHTML = '<p class="text-center text-gray-600">No questions loaded for this quiz.</p>';
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'none';
        }
    };

    // Clean up intervals when leaving the page
    window.onbeforeunload = function() {
        clearInterval(timerInterval);
        clearInterval(proctoringInterval); // Clear proctoring interval too
        // Log that the user left the quiz if not submitted
        // Note: Actual form submission sets `quizForm.submitted` if you add such a flag
        // For simplicity, we assume if current_attempt_id exists and form not submitted, log.
        if (attemptId && !quizForm.hasAttribute('data-submitted')) {
            logProctoringEvent('page_leave', 'User navigated away from the quiz page during attempt.');
        }
    };
</script>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>