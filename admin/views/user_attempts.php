<?php
// admin/views/user_attempts.php
// This view displays all quiz attempts for a specific user, along with filters and summary.

if (!isset($current_user) || !isset($results)) {
    // Redirect or show an error if data is not properly passed
    echo display_message("Error: User data not found.", "error");
    exit;
}

$user_id = $current_user['user_id'];
$username = htmlspecialchars($current_user['username']);
$user_email = htmlspecialchars($current_user['email']);
?>

<div class="bg-white shadow-lg rounded-lg p-6 mb-8">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Attempts for User: <span class="text-theme-color"><?php echo $username; ?></span> (<?php echo $user_email; ?>)</h2>
        <a href="results.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition duration-300 ease-in-out flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Assessments
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-blue-100 p-4 rounded-lg shadow-sm text-center">
            <p class="text-sm font-semibold text-blue-700">Total Attempts</p>
            <p class="text-2xl font-bold text-blue-900"><?php echo $summary_metrics['total_attempts']; ?></p>
        </div>
        <div class="bg-green-100 p-4 rounded-lg shadow-sm text-center">
            <p class="text-sm font-semibold text-green-700">Completed</p>
            <p class="text-2xl font-bold text-green-900"><?php echo $summary_metrics['completed_attempts']; ?></p>
        </div>
        <div class="bg-purple-100 p-4 rounded-lg shadow-sm text-center">
            <p class="text-sm font-semibold text-purple-700">Average Actual Score</p>
            <p class="text-2xl font-bold text-purple-900"><?php echo $summary_metrics['average_actual_score']; ?></p>
        </div>
        <div class="bg-red-100 p-4 rounded-lg shadow-sm text-center">
            <p class="text-sm font-semibold text-red-700">Proctoring Violations</p>
            <p class="text-2xl font-bold text-red-900"><?php echo $summary_metrics['attempts_with_proctoring_violations']; ?></p>
        </div>
    </div>

    <div class="bg-gray-100 p-6 rounded-lg shadow-inner mb-6">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Filter Attempts</h3>
        <form action="results.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <input type="hidden" name="view_user" value="<?php echo $user_id; ?>">

            <div>
                <label for="filter_assessment_id_detail" class="block text-sm font-medium text-gray-700 mb-1">Assessment:</label>
                <select name="assessment_id_detail" id="filter_assessment_id_detail" class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring focus:ring-theme-color focus:ring-opacity-50">
                    <option value="">All Assessments</option>
                    <?php foreach ($all_assessments_for_filters as $assessment_filter) : ?>
                        <option value="<?php echo $assessment_filter['quiz_id']; ?>" <?php echo (string)$filter_assessment_id_detail === (string)$assessment_filter['quiz_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($assessment_filter['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_completion_status" class="block text-sm font-medium text-gray-700 mb-1">Completion Status:</label>
                <select name="completion_status" id="filter_completion_status" class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring focus:ring-theme-color focus:ring-opacity-50">
                    <option value="">All Statuses</option>
                    <option value="1" <?php echo (string)$filter_completion_status === '1' ? 'selected' : ''; ?>>Completed</option>
                    <option value="0" <?php echo (string)$filter_completion_status === '0' ? 'selected' : ''; ?>>Incomplete</option>
                </select>
            </div>

            <div>
                <label for="filter_score_min" class="block text-sm font-medium text-gray-700 mb-1">Min Score:</label>
                <input type="number" name="score_min" id="filter_score_min" value="<?php echo htmlspecialchars($filter_score_min); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring focus:ring-theme-color focus:ring-opacity-50">
            </div>

            <div>
                <label for="filter_score_max" class="block text-sm font-medium text-gray-700 mb-1">Max Score:</label>
                <input type="number" name="score_max" id="filter_score_max" value="<?php echo htmlspecialchars($filter_score_max); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring focus:ring-theme-color focus:ring-opacity-50">
            </div>

            <div>
                <label for="filter_start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                <input type="date" name="start_date" id="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring focus:ring-theme-color focus:ring-opacity-50">
            </div>

            <div>
                <label for="filter_end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                <input type="date" name="end_date" id="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring focus:ring-theme-color focus:ring-opacity-50">
            </div>

            <div class="md:col-span-2 lg:col-span-3 flex justify-end space-x-2">
                <button type="submit" class="bg-theme-color text-white px-6 py-2 rounded-md hover:bg-theme-dark transition duration-300 ease-in-out flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="results.php?view_user=<?php echo $user_id; ?>" class="bg-gray-400 text-white px-6 py-2 rounded-md hover:bg-gray-500 transition duration-300 ease-in-out flex items-center">
                    <i class="fas fa-undo mr-2"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>

    <?php if (!empty($results)) : ?>
        <div class="overflow-x-auto bg-white rounded-lg shadow-md">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="results.php?view_user=<?php echo $user_id; ?>&sort_by=quiz_title&sort_order=<?php echo ($sort_by === 'quiz_title' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_filter([
                                'assessment_id_detail' => $filter_assessment_id_detail,
                                'completion_status' => $filter_completion_status,
                                'score_min' => $filter_score_min,
                                'score_max' => $filter_score_max,
                                'start_date' => $filter_start_date,
                                'end_date' => $filter_end_date
                            ])); ?>" class="flex items-center">
                                Assessment
                                <?php if ($sort_by === 'quiz_title') : ?>
                                    <i class="ml-1 fas <?php echo $sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="results.php?view_user=<?php echo $user_id; ?>&sort_by=score&sort_order=<?php echo ($sort_by === 'score' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_filter([
                                'assessment_id_detail' => $filter_assessment_id_detail,
                                'completion_status' => $filter_completion_status,
                                'score_min' => $filter_score_min,
                                'score_max' => $filter_score_max,
                                'start_date' => $filter_start_date,
                                'end_date' => $filter_end_date
                            ])); ?>" class="flex items-center">
                                Score
                                <?php if ($sort_by === 'score') : ?>
                                    <i class="ml-1 fas <?php echo $sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="results.php?view_user=<?php echo $user_id; ?>&sort_by=start_time&sort_order=<?php echo ($sort_by === 'start_time' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_filter([
                                'assessment_id_detail' => $filter_assessment_id_detail,
                                'completion_status' => $filter_completion_status,
                                'score_min' => $filter_score_min,
                                'score_max' => $filter_score_max,
                                'start_date' => $filter_start_date,
                                'end_date' => $filter_end_date
                            ])); ?>" class="flex items-center">
                                Start Time
                                <?php if ($sort_by === 'start_time') : ?>
                                    <i class="ml-1 fas <?php echo $sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="results.php?view_user=<?php echo $user_id; ?>&sort_by=end_time&sort_order=<?php echo ($sort_by === 'end_time' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_filter([
                                'assessment_id_detail' => $filter_assessment_id_detail,
                                'completion_status' => $filter_completion_status,
                                'score_min' => $filter_score_min,
                                'score_max' => $filter_score_max,
                                'start_date' => $filter_start_date,
                                'end_date' => $filter_end_date
                            ])); ?>" class="flex items-center">
                                End Time
                                <?php if ($sort_by === 'end_time') : ?>
                                    <i class="ml-1 fas <?php echo $sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="results.php?view_user=<?php echo $user_id; ?>&sort_by=is_completed&sort_order=<?php echo ($sort_by === 'is_completed' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_filter([
                                'assessment_id_detail' => $filter_assessment_id_detail,
                                'completion_status' => $filter_completion_status,
                                'score_min' => $filter_score_min,
                                'score_max' => $filter_score_max,
                                'start_date' => $filter_start_date,
                                'end_date' => $filter_end_date
                            ])); ?>" class="flex items-center">
                                Status
                                <?php if ($sort_by === 'is_completed') : ?>
                                    <i class="ml-1 fas <?php echo $sort_order === 'ASC' ? 'fa-sort-up' : 'fa-sort-down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Violations</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Images</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($results as $attempt) : ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($attempt['quiz_title']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($attempt['score']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_datetime($attempt['start_time']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $attempt['end_time'] ? format_datetime($attempt['end_time']) : 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php
                                $status_class = $attempt['is_completed'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                $status_text = $attempt['is_completed'] ? 'Completed' : 'In Progress';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="<?php echo ($attempt['log_violations'] > 0) ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo htmlspecialchars($attempt['log_violations']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($attempt['image_count']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="results.php?view_attempt=<?php echo $attempt['attempt_id']; ?>" class="text-theme-color hover:text-theme-dark inline-flex items-center">
                                    View Details <i class="fas fa-eye ml-1"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <p class="text-center text-gray-600 py-8">No attempts found for this user matching the criteria.</p>
    <?php endif; ?>
</div>

<script>
    // Optional: JavaScript to maintain scroll position or handle AJAX loading for filters if desired
</script>