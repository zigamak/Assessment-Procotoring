<?php
// quiz/public_quiz.php
// Allows anyone to take a public quiz (no login required).
// Implements a single-question-per-view navigation using JavaScript/AJAX.

require_once '../includes/session.php'; // Included for general functions, though not for session login here
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the public header
require_once '../includes/header_public.php';

$message = ''; // Initialize message variable for feedback
$quiz = null;
$questions = [];

// Ensure quiz_id is provided in the URL
if (!isset($_GET['quiz_id']) || !is_numeric($_GET['quiz_id'])) {
    // If no specific quiz_id is provided, list all public quizzes
    try {
        $stmt = $pdo->query("SELECT quiz_id, title, description FROM quizzes WHERE is_public = 1 ORDER BY created_at DESC");
        $public_quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($public_quizzes)) {
            $message = display_message("No public quizzes are currently available. Please check back later!", "info");
        }
    } catch (PDOException $e) {
        error_log("Public Quizzes List Error: " . $e->getMessage());
        $message = display_message("Could not fetch public quizzes. Please try again later.", "error");
    }
} else {
    $quiz_id = sanitize_input($_GET['quiz_id']);

    try {
        // Fetch quiz details
        $stmt = $pdo->prepare("SELECT quiz_id, title, description, is_public, duration_minutes FROM quizzes WHERE quiz_id = :quiz_id AND is_public = 1");
        $stmt->execute(['quiz_id' => $quiz_id]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) {
            $message = display_message("Public quiz not found or is not accessible.", "error");
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
                $message = display_message("This public quiz has no questions yet. Please contact the administrator.", "info");
                $quiz = null; // Don't allow taking an empty quiz
            }
        }
    } catch (PDOException $e) {
        error_log("Public Quiz Load Error: " . $e->getMessage());
        $message = display_message("An error occurred while loading the quiz. Please try again later.", "error");
        $quiz = null;
    }
}
?>

<div class="container mx-auto p-4 py-8">
    <?php echo $message; // Display any feedback messages ?>

    <?php if (!isset($_GET['quiz_id'])): // Display list of public quizzes if no quiz ID is specified ?>
        <h1 class="text-3xl font-bold text-theme-color mb-6">Available Public Quizzes</h1>
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <?php if (empty($public_quizzes)): ?>
                <p class="text-gray-600">No public quizzes found.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($public_quizzes as $pq): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($pq['title']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 max-w-xs overflow-hidden text-ellipsis"><?php echo htmlspecialchars($pq['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="public_quiz.php?quiz_id=<?php echo htmlspecialchars($pq['quiz_id']); ?>"
                                   class="text-green-600 hover:text-green-900">Start Quiz</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php elseif ($quiz): // Display the quiz if a valid quiz ID is provided and found ?>
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

            <form id="publicQuizForm" action="process_quiz.php" method="POST" class="space-y-8">
                <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($quiz['quiz_id']); ?>">
                <input type="hidden" name="is_public_quiz" value="1">
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
    <?php endif; ?>
</div>

<script>
    const quizData = JSON.parse(document.getElementById('questions-data')?.value || '[]');
    let currentQuestionIndex = parseInt(document.getElementById('current-question-index')?.value || '0');
    const questionContainer = document.getElementById('question-container');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    const currentQuestionNumberSpan = document.getElementById('current-question-number');
    const totalQuestionsSpan = document.getElementById('total-questions');
    const quizForm = document.getElementById('publicQuizForm');

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
                    // Using a custom message box instead of alert()
                    showCustomMessageBox('Time is up!', 'Your quiz will be submitted automatically.', () => {
                        submitQuizAutomatically();
                    });
                }
            }, 1000);
        }
    }

    // --- Custom Message Box (instead of alert) ---
    function showCustomMessageBox(title, message, callback = null) {
        // Create modal container
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
        // Prevent default submission to manually add answers before resubmitting
        event.preventDefault();
        clearInterval(timerInterval); // Stop timer on manual submission

        // Add all collected answers to the form before submitting
        submitQuizAutomatically(); // Re-use the logic for adding answers
    });


    // --- Initialization on page load ---
    window.onload = function() {
        if (quizData.length > 0) {
            renderQuestion(currentQuestionIndex);
            if (quizDurationMinutes > 0) {
                startTimer();
            }
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
    };
</script>

<?php
// Include the public footer
require_once '../includes/footer_public.php';
?>
