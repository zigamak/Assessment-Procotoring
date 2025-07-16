<?php
// admin/views/attempt_details.php
// View for detailed attempt information
?>
<div class="mb-6 text-left">
    <a href="results.php?view_quiz=<?php echo htmlspecialchars($current_attempt['quiz_id']); ?>" class="inline-block bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition duration-300 text-sm font-semibold shadow-sm">
        ← Back to Assessment Attempts
    </a>
</div>
<div class="bg-white p-6 rounded-xl shadow-lg mb-8 border border-gray-200">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
        Attempt Details for <span class="text-theme-color"><?php echo htmlspecialchars($current_attempt['quiz_title']); ?></span> by <span class="text-theme-color"><?php echo htmlspecialchars($current_attempt['username']); ?></span>
    </h2>
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
            <span class="text-indigo-800 text-2xl font-bold"><?php echo htmlspecialchars($current_attempt['score'] ?? 'N/A'); ?></span>
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

    <h3 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-3">Questions and Answers Review</h3>
    <?php if (!empty($current_attempt['answers'])): ?>
        <?php foreach ($current_attempt['answers'] as $answer): ?>
            <div class="border border-gray-200 p-5 rounded-lg bg-gray-50 mb-6 shadow-sm hover:shadow-md transition-shadow duration-200">
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
                            $stmt_tf_correct = $pdo->prepare("SELECT option_text FROM options WHERE question_id = :qid AND is_correct = 1 LIMIT 1");
                            $stmt_tf_correct->execute(['qid' => $answer['question_id']]);
                            $correct_answer_text = $stmt_tf_correct->fetchColumn() ?: 'N/A';
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

    <h3 class="text-2xl font-bold text-gray-800 mb-4 mt-8 border-b pb-3">Proctoring Images</h3>
    <?php if (!empty($proctoring_images)): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-8">
            <?php foreach ($proctoring_images as $image): ?>
                <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>"
                         alt="Proctoring Image"
                         class="w-full h-48 object-cover">
                    <p class="text-gray-600 text-sm p-2">
                        <strong>Capture Time:</strong> <?php echo date('h:i A, j F, Y', strtotime($image['capture_time'])); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-red-600 font-semibold italic">No proctoring images available for this attempt.<?php echo $current_attempt['is_completed'] ? ' <span class="text-red-800">(Potential Violation)</span>' : ''; ?></p>
    <?php endif; ?>

    <h3 class="text-2xl font-bold text-gray-800 mb-4 mt-8 border-b pb-3">Proctoring Logs</h3>
    <?php if (!empty($proctoring_logs)): ?>
        <div class="overflow-x-auto shadow-md rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Time</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Event Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/2">Details</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($proctoring_logs as $log): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('h:i A, j F, Y', strtotime($log['log_time'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 <?php echo ($log['event_type'] === 'critical_error') ? 'font-bold text-red-600' : ''; ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['event_type']))); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 break-words max-w-md">
                                <?php
                                    $log_data = $log['log_data'] ?? 'N/A';
                                    $decoded_data = json_decode($log_data, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data)) {
                                        echo '<pre class="bg-gray-100 p-2 rounded text-xs overflow-auto max-h-24 custom-scrollbar">' . htmlspecialchars(json_encode($decoded_data, JSON_PRETTY_PRINT)) . '</pre>';
                                    } else {
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