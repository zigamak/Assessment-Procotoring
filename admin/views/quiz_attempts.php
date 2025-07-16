<?php
// admin/views/quiz_attempts.php
// This view displays all attempts for a specific quiz, with filtering options.

if (!isset($results) || !isset($current_quiz) || !isset($all_users) || !isset($summary_metrics)) {
    echo display_message("Error: Required data not found for quiz attempts.", "error");
    exit;
}
?>

<div class="bg-white shadow-lg rounded-lg p-6 mb-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
        Attempts for Assessment: <span class="text-accent"><?php echo htmlspecialchars($current_quiz['title']); ?></span>
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-blue-50 p-4 rounded-lg shadow-sm text-center">
            <p class="text-sm font-semibold text-gray-600">Total Attempts</p>
            <p class="text-2xl font-bold text-blue-700"><?php echo $summary_metrics['total_attempts']; ?></p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg shadow-sm text-center">
            <p class="text-sm font-semibold text-gray-600">Completed Attempts</p>
            <p class="text-2xl font-bold text-green-700"><?php echo $summary_metrics['completed_attempts']; ?></p>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg shadow-sm text-center">
            <p class="text-sm font-semibold text-gray-600">Average Score</p>
            <p class="text-2xl font-bold text-yellow-700"><?php echo $summary_metrics['average_score']; ?>%</p>
        </div>
        <div class="bg-red-50 p-4 rounded-lg shadow-sm text-center">
            <p class="text-sm font-semibold text-gray-600">Proctoring Violations</p>
            <p class="text-2xl font-bold text-red-700"><?php echo $summary_metrics['attempts_with_proctoring_violations']; ?></p>
        </div>
    </div>

    <div class="bg-gray-100 p-6 rounded-lg shadow-inner mb-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Filter Attempts</h3>
        <form action="results.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <input type="hidden" name="view_quiz" value="<?php echo htmlspecialchars($current_quiz['quiz_id']); ?>">

            <div>
                <label for="filter_user_id_detail" class="block text-sm font-medium text-gray-700 mb-1">Filter by Student:</label>
                <select name="user_id_detail" id="filter_user_id_detail"
                        class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50 select2-enabled"
                        data-placeholder="All Students" data-allow-clear="true">
                    <option value=""></option> <?php foreach ($all_users as $user) : ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo ((string)$filter_user_id_detail === (string)$user['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_completion_status" class="block text-sm font-medium text-gray-700 mb-1">Completion Status:</label>
                <select name="completion_status" id="filter_completion_status" class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
                    <option value="">All Statuses</option>
                    <option value="1" <?php echo ((string)$filter_completion_status === '1') ? 'selected' : ''; ?>>Completed</option>
                    <option value="0" <?php echo ((string)$filter_completion_status === '0') ? 'selected' : ''; ?>>In Progress</option>
                </select>
            </div>

            <div>
                <label for="filter_score_min" class="block text-sm font-medium text-gray-700 mb-1">Min Score:</label>
                <input type="number" name="score_min" id="filter_score_min" value="<?php echo htmlspecialchars($filter_score_min); ?>"
                       class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
            </div>

            <div>
                <label for="filter_score_max" class="block text-sm font-medium text-gray-700 mb-1">Max Score:</label>
                <input type="number" name="score_max" id="filter_score_max" value="<?php echo htmlspecialchars($filter_score_max); ?>"
                       class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
            </div>

            <div>
                <label for="filter_start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                <input type="date" name="start_date" id="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"
                       class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
            </div>

            <div>
                <label for="filter_end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                <input type="date" name="end_date" id="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"
                       class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50">
            </div>

            <div class="md:col-span-2 lg:col-span-3 flex justify-end space-x-2 mt-4">
                <button type="submit" class="bg-accent text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-300 ease-in-out flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="results.php?view_quiz=<?php echo htmlspecialchars($current_quiz['quiz_id']); ?>" class="bg-gray-400 text-white px-6 py-2 rounded-md hover:bg-gray-500 transition duration-300 ease-in-out flex items-center">
                    <i class="fas fa-undo mr-2"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>

    <?php if (!empty($results)) : ?>
        <div class="overflow-x-auto bg-white rounded-lg shadow-md mt-8">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempt ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Violations</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($results as $attempt) : ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($attempt['attempt_id']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($attempt['username']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($attempt['score']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    <?php
                                        if ($attempt['is_completed']) {
                                            echo 'bg-green-100 text-green-800';
                                        } else {
                                            echo 'bg-yellow-100 text-yellow-800';
                                        }
                                    ?>">
                                    <?php echo $attempt['is_completed'] ? 'Completed' : 'In Progress'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_datetime($attempt['start_time']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $attempt['end_time'] ? format_datetime($attempt['end_time']) : 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="<?php echo ($attempt['log_violations'] > 0) ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo htmlspecialchars($attempt['log_violations']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="results.php?view_attempt=<?php echo $attempt['attempt_id']; ?>" class="text-accent hover:text-blue-700 inline-flex items-center">
                                    View Details <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <p class="text-center text-gray-600 py-8 mt-8">No attempts found for this assessment with the selected filters.</p>
    <?php endif; ?>
</div>