<?php
// admin/views/attempt_details.php
// View for detailed attempt information

// Assume $current_attempt, $pdo, $proctoring_images, $proctoring_logs are already defined and fetched in the controller.
// Make sure you fetch user details for the profile image and full name in your controller if not already done.

// Define a default profile image if the actual user image path is not available or empty
$default_profile_image = BASE_URL . 'assets/images/default_profile.png'; // Ensure this path is valid

$user_profile_image_for_display = $default_profile_image;
$user_full_name_for_display = htmlspecialchars($current_attempt['username']); // Default to username from attempt data

// Removed: define('PROCTORING_IMAGES_WEB_SUBDIR', 'uploads/proctoring_images/');
// This constant is removed because it appears $image['image_path'] from the database
// already contains the 'uploads/proctoring_images/' segment, causing duplication.

// Fetch user's passport image path and full name if $current_attempt has user_id and $pdo is available
if (isset($current_attempt['user_id']) && isset($pdo)) {
    try {
        $stmt_user_info = $pdo->prepare("SELECT passport_image_path, first_name, last_name, username, email FROM users WHERE user_id = :user_id");
        $stmt_user_info->execute(['user_id' => $current_attempt['user_id']]);
        $user_data_result = $stmt_user_info->fetch(PDO::FETCH_ASSOC);

        if ($user_data_result) {
            // Update profile image using passport_image_path from the users table
            if (!empty($user_data_result['passport_image_path'])) {
                $user_profile_image_for_display = BASE_URL . 'uploads/passports/' . $user_data_result['passport_image_path'];
            }
            // Update full name, preferring first_name and last_name if available
            $first_name = htmlspecialchars($user_data_result['first_name'] ?? '');
            $last_name = htmlspecialchars($user_data_result['last_name'] ?? '');
            if (!empty($first_name) || !empty($last_name)) {
                $user_full_name_for_display = trim($first_name . ' ' . $last_name);
            } else {
                // Fallback to username if first_name and last_name are not set
                $user_full_name_for_display = htmlspecialchars($user_data_result['username'] ?? 'Student');
            }
            // Also ensure email is available if not directly in $current_attempt
            $current_attempt['email'] = $user_data_result['email'] ?? ($current_attempt['email'] ?? 'N/A');
        }
    } catch (PDOException $e) {
        error_log("Error fetching user info for attempt details: " . $e->getMessage());
        // Fallback to default image and username
    }
}

// --- Score and Percentage Calculation ---
// Get the achieved score from the current attempt data
$score = $current_attempt['score'] ?? 0;

// Initialize total_possible_score to a safe default
$total_possible_score = 1;

// Fetch the total possible score for the quiz from the questions table
if (isset($pdo) && isset($current_attempt['quiz_id'])) {
    try {
        $stmt_total_score = $pdo->prepare("SELECT SUM(score) FROM questions WHERE quiz_id = :quiz_id");
        $stmt_total_score->execute(['quiz_id' => $current_attempt['quiz_id']]);
        $fetched_total_score = $stmt_total_score->fetchColumn();

        // If a valid sum is returned, use it; otherwise, keep the default of 1
        if ($fetched_total_score !== false && $fetched_total_score !== null) {
            $total_possible_score = (int)$fetched_total_score;
        }
    } catch (PDOException $e) {
        error_log("Error fetching total possible score for quiz " . $current_attempt['quiz_id'] . ": " . $e->getMessage());
        // total_possible_score remains 1 to prevent division by zero
    }
}

// Calculate percentage score, ensuring total_possible_score is valid
$percentage_score = 0;
if ($total_possible_score > 0) {
    $percentage_score = round(($score / $total_possible_score) * 100, 2);
}

// Map PHP upload errors to human-readable messages (if not already defined)
if (!isset($phpFileUploadErrors)) {
    $phpFileUploadErrors = array(
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    );
}
?>

<div class="mb-6 text-left">
    <a href="results.php?view_quiz=<?php echo htmlspecialchars($current_attempt['quiz_id']); ?>" class="inline-block bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition duration-300 text-sm font-semibold shadow-sm">
        &larr; Back to Assessment Attempts
    </a>
</div>

<div class="bg-white p-6 rounded-xl shadow-lg mb-8 border border-gray-200">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
        Attempt Details for <span class="text-blue-600"><?php echo htmlspecialchars($current_attempt['quiz_title']); ?></span> by <span class="text-blue-600"><?php echo htmlspecialchars($user_full_name_for_display); ?></span>
    </h2>

    <div class="flex flex-col md:flex-row items-center justify-center gap-6 mb-8 pb-6 border-b border-gray-200">
        <div class="relative w-32 h-32 rounded-full overflow-hidden border-4 border-blue-300 shadow-md">
            <img src="<?php echo htmlspecialchars($user_profile_image_for_display); ?>" alt="<?php echo htmlspecialchars($user_full_name_for_display); ?>'s Profile" class="w-full h-full object-cover">
        </div>
        <div class="text-center md:text-left">
            <p class="text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars($user_full_name_for_display); ?></p>
            <p class="text-lg text-gray-600"><?php echo htmlspecialchars($current_attempt['email'] ?? 'N/A'); ?></p>
            <p class="text-sm text-gray-500">Username: <span class="font-mono"><?php echo htmlspecialchars($current_attempt['username']); ?></span></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 text-gray-700 mb-8 border-b pb-6">
        <div class="bg-blue-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
            <p class="font-semibold text-lg">Attempt ID:</p>
            <span class="text-blue-800 text-lg font-mono"><?php echo htmlspecialchars($current_attempt['attempt_id']); ?></span>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
            <p class="font-semibold text-lg">Started:</p>
            <span class="text-yellow-800 text-lg"><?php echo date('h:i A, j F, Y', strtotime($current_attempt['start_time'])); ?></span>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
            <p class="font-semibold text-lg">Completed:</p>
            <span class="text-purple-800 text-lg"><?php echo $current_attempt['end_time'] ? date('h:i A, j F, Y', strtotime($current_attempt['end_time'])) : '<span class="text-red-500">In Progress</span>'; ?></span>
        </div>
        <div class="bg-green-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
            <p class="font-semibold text-lg">Status:</p>
            <span class="text-green-800 text-lg font-bold"><?php echo $current_attempt['is_completed'] ? 'Completed' : 'In Progress'; ?></span>
        </div>
        <div class="bg-indigo-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
            <p class="font-semibold text-lg">Score:</p>
            <span class="text-indigo-800 text-2xl font-bold"><?php echo htmlspecialchars($score); ?> / <?php echo htmlspecialchars($total_possible_score); ?></span>
        </div>
        <div class="bg-orange-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
            <p class="font-semibold text-lg">Percentage:</p>
            <span class="text-orange-800 text-2xl font-bold"><?php echo htmlspecialchars($percentage_score); ?>%</span>
        </div>
        <div class="bg-pink-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
            <p class="font-semibold text-lg">User Email:</p>
            <span class="text-pink-800 text-lg"><?php echo htmlspecialchars($current_attempt['email'] ?? 'N/A'); ?></span>
        </div>
        <div class="bg-teal-50 p-4 rounded-lg flex items-center justify-between shadow-sm">
            <p class="font-semibold text-lg">Assessment Duration:</p>
            <span class="text-teal-800 text-lg"><?php echo htmlspecialchars($current_attempt['duration_minutes'] ? $current_attempt['duration_minutes'] . ' minutes' : 'No Limit'); ?></span>
        </div>
    </div>

    <div class="mb-8">
        <div class="flex justify-between items-center mb-4 border-b pb-3 cursor-pointer" onclick="toggleSection('questionsAnswers')">
            <h3 class="text-2xl font-bold text-gray-800">Questions and Answers Review</h3>
            <button type="button" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                <svg id="icon-questionsAnswers" class="w-6 h-6 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
        </div>
        <div id="questionsAnswersContent" class="space-y-6 hidden">
            <?php if (!empty($current_attempt['answers'])): ?>
                <?php foreach ($current_attempt['answers'] as $answer): ?>
                    <div class="border border-gray-200 p-5 rounded-lg bg-gray-50 shadow-sm hover:shadow-md transition-shadow duration-200">
                        <p class="font-bold text-lg text-gray-900 mb-2">Q: <?php echo htmlspecialchars($answer['question_text']); ?> <span class="text-gray-600">(Score: <?php echo htmlspecialchars($answer['question_score']); ?> points)</span></p>
                        <p class="text-sm text-gray-500 mb-3">Type: <span class="font-semibold"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $answer['question_type']))); ?></span></p>
                        <?php if ($answer['question_type'] === 'multiple_choice'): ?>
                            <?php
                                $all_options_parsed = [];
                                $selected_option_text = 'No answer selected';
                                $correct_option_texts = [];

                                if (!empty($answer['all_options_data'])) {
                                    $options_raw = explode(';;', $answer['all_options_data']);
                                    foreach ($options_raw as $opt_str) {
                                        @list($opt_id, $opt_text, $is_correct_val) = explode('||', $opt_str, 3);
                                        $all_options_parsed[] = ['id' => (int)$opt_id, 'text' => $opt_text, 'is_correct' => (bool)$is_correct_val];
                                        if ((int)$opt_id === (int)$answer['selected_option_id']) {
                                            $selected_option_text = $opt_text;
                                        }
                                        if ((bool)$is_correct_val) {
                                            $correct_option_texts[] = $opt_text;
                                        }
                                    }
                                }
                            ?>
                            <div class="mb-2">
                                <p class="font-semibold text-gray-700">Available Options:</p>
                                <ul class="list-disc ml-6 text-sm text-gray-700">
                                    <?php foreach($all_options_parsed as $option): ?>
                                        <li class="<?php
                                            if ((int)$option['id'] === (int)$answer['selected_option_id']) {
                                                echo $answer['is_correct'] ? 'text-green-700 font-bold' : 'text-red-700 font-bold';
                                            } elseif ($option['is_correct']) {
                                                echo 'text-green-600';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($option['text']); ?>
                                            <?php if ((int)$option['id'] === (int)$answer['selected_option_id']) echo ' <span class="text-blue-500">(Selected)</span>'; ?>
                                            <?php if ($option['is_correct']) echo ' <span class="text-green-500">(Correct Answer)</span>'; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <p class="mt-3"><strong>Your Answer:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'; ?>"><?php echo htmlspecialchars($selected_option_text); ?></span></p>
                            <p><strong>Correct Answer:</strong> <span class="text-green-600 font-semibold"><?php echo htmlspecialchars(implode(', ', $correct_option_texts)); ?></span></p>
                            <p><strong>Result:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600 font-bold' : 'text-red-600 font-bold'; ?>"><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></span></p>
                        <?php elseif ($answer['question_type'] === 'true_false' || $answer['question_type'] === 'short_answer'): ?>
                            <p><strong>Student's Answer:</strong> <span class="font-semibold"><?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?></span></p>
                            <?php
                                $correct_answer_text = 'N/A';
                                if ($answer['question_type'] === 'true_false') {
                                    // Ensure $pdo is available in this scope for the query
                                    if (isset($pdo)) {
                                        try {
                                            $stmt_tf_correct = $pdo->prepare("SELECT option_text FROM options WHERE question_id = :qid AND is_correct = 1 LIMIT 1");
                                            $stmt_tf_correct->execute(['qid' => $answer['question_id']]);
                                            $correct_answer_text = $stmt_tf_correct->fetchColumn() ?: 'N/A';
                                        } catch (PDOException $e) {
                                            error_log("Error fetching true/false correct answer: " . $e->getMessage());
                                            $correct_answer_text = 'Database Error';
                                        }
                                    } else {
                                        $correct_answer_text = 'DB Not Available'; // Fallback if $pdo is not set
                                    }
                                }
                            ?>
                            <p><strong>Correct Answer:</strong> <span class="text-green-600 font-semibold"><?php echo ($answer['question_type'] === 'true_false') ? htmlspecialchars($correct_answer_text) : 'Manual Review Needed'; ?></span></p>
                            <p><strong>Result:</strong> <span class="<?php echo $answer['is_correct'] ? 'text-green-600 font-bold' : 'text-red-600 font-bold'; ?>"><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?></span></p>
                        <?php elseif ($answer['question_type'] === 'essay'): ?>
                            <p><strong>Student's Answer:</strong></p>
                            <div class="bg-gray-100 p-3 rounded-md border border-gray-300 text-gray-700 whitespace-pre-wrap text-sm max-h-40 overflow-y-auto custom-scrollbar">
                                <?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer'); ?>
                            </div>
                            <p class="text-sm text-gray-500 mt-2 italic">Requires manual grading.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-600 italic">No answers recorded for this attempt yet or attempt is incomplete.</p>
            <?php endif; ?>
        </div>
    </div>

    <hr class="my-8">

    <div class="mb-8">
        <div class="flex justify-between items-center mb-4 border-b pb-3 cursor-pointer" onclick="toggleSection('proctoringImages')">
            <h3 class="text-2xl font-bold text-gray-800">Proctoring Images</h3>
            <button type="button" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                <svg id="icon-proctoringImages" class="w-6 h-6 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
        </div>
        <div id="proctoringImagesContent" class="space-y-4 hidden">
            <?php if (!empty($proctoring_images)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($proctoring_images as $image):
                        $image_full_path = '';

                        // Construct the full web path for the image
                        // Assuming $image['image_path'] from DB is already like 'uploads/proctoring_images/2/2/192/proctor_687a4e32bd940_1752845874.png'
                        // So we just append it directly to BASE_URL
                        $image_full_path = BASE_URL . $image['image_path'];

                        // Sanitize the path for HTML output
                        $image_display_src = htmlspecialchars($image_full_path);

                        // Construct the file system path for file_exists check
                        // Based on the error, it seems $_SERVER['DOCUMENT_ROOT'] already points to
                        // the 'assessment' directory (e.g., /home/.../public_html/assessment/)
                        // So we just append the image_path directly to DOCUMENT_ROOT.
                        $file_system_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $image['image_path'];

                        // Check if the file exists on the server's file system
                        $file_exists = file_exists($file_system_path) && is_file($file_system_path);

                        // Set a fallback image if the file doesn't exist
                        $fallback_image_src = BASE_URL . 'assets/images/image_not_found.png'; // Create a default "image not found" placeholder
                    ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-200 flex flex-col">
                            <?php if ($file_exists): ?>
                                <img src="<?php echo $image_display_src; ?>"
                                    alt="Proctoring Image"
                                    class="w-full h-48 sm:h-56 object-cover border-b border-gray-100">
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($fallback_image_src); ?>"
                                    alt="Image not found"
                                    class="w-full h-48 sm:h-56 object-contain border-b border-gray-100 bg-gray-100 p-4 text-gray-500 text-center flex items-center justify-center">
                                <p class="text-red-500 text-xs p-2 text-center">Image file not found on server!</p>
                                <p class="text-gray-500 text-xs p-1 text-center break-all">Expected Web Path: <?php echo htmlspecialchars($image_full_path); ?></p>
                                <p class="text-gray-500 text-xs p-1 text-center break-all">Attempted FS Path: <?php echo htmlspecialchars($file_system_path); ?></p>
                            <?php endif; ?>
                            <p class="text-gray-600 text-xs p-3">
                                <strong>Capture Time:</strong> <span class="font-medium"><?php echo date('h:i A, j F, Y', strtotime($image['capture_time'])); ?></span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-red-600 font-semibold italic">No proctoring images available for this attempt.<?php echo $current_attempt['is_completed'] ? ' <span class="text-red-800">(Potential Violation)</span>' : ''; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <hr class="my-8">

    <div class="mb-8">
        <div class="flex justify-between items-center mb-4 border-b pb-3 cursor-pointer" onclick="toggleSection('proctoringLogs')">
            <h3 class="text-2xl font-bold text-gray-800">Proctoring Logs</h3>
            <button type="button" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                <svg id="icon-proctoringLogs" class="w-6 h-6 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
        </div>
        <div id="proctoringLogsContent" class="hidden">
            <?php if (!empty($proctoring_logs)): ?>
                <div class="overflow-x-auto shadow-md rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">Event Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-3/5">Details</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($proctoring_logs as $log): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('h:i A, j F, Y', strtotime($log['log_time'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 <?php echo ($log['event_type'] === 'critical_error' || strpos($log['event_type'], 'violation') !== false) ? 'font-bold text-red-600' : ''; ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['event_type']))); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 break-words">
                                        <?php
                                            $log_data = $log['log_data'] ?? 'N/A';
                                            $decoded_data = json_decode($log_data, true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data)) {
                                                // Display JSON in a pre-formatted, scrollable block
                                                echo '<pre class="bg-gray-100 p-2 rounded text-xs overflow-auto max-h-24 custom-scrollbar">' . htmlspecialchars(json_encode($decoded_data, JSON_PRETTY_PRINT)) . '</pre>';
                                            } else {
                                                // Display raw data if not valid JSON
                                                echo htmlspecialchars($log_data);
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600 italic">No proctoring logs available for this attempt.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Custom Scrollbar for Pre-formatted Log Data and Essay Answers */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px; /* For horizontal scrollbar if needed */
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>

<script>
    function toggleSection(sectionId) {
        const content = document.getElementById(sectionId + 'Content');
        const icon = document.getElementById('icon-' + sectionId);
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.remove('rotate-0'); // Remove initial state if set via CSS
            icon.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180');
            icon.classList.add('rotate-0'); // Return to initial state
        }
    }
</script>
