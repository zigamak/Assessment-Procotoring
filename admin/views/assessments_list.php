<?php
// admin/views/assessments_list.php
// This view displays a list of all assessments with summary statistics and filters.

if (!isset($assessments) || !isset($all_assessments_for_filters) || !isset($all_users)) {
    // Redirect or show an error if data is not properly passed
    echo display_message("Error: Required data not found.", "error");
    exit;
}
?>

<div class="bg-white shadow-lg rounded-lg p-6 mb-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Overview of All Assessments</h2>

    <div class="flex flex-col lg:flex-row lg:space-x-6">
        <div class="bg-gray-100 p-6 rounded-lg shadow-inner mb-6 lg:mb-0 lg:w-1/2">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Filter by Assessment</h3>
            <form action="view_results.php" method="GET" class="space-y-4">
                <div>
                    <label for="filter_assessment_id_main" class="block text-sm font-medium text-gray-700 mb-1">Select Assessment:</label>
                    <select name="assessment_id_main" id="filter_assessment_id_main"
                            class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50 select2-enabled"
                            data-placeholder="All Assessments" data-allow-clear="true">
                        <option value=""></option> <?php foreach ($all_assessments_for_filters as $assessment_filter) : ?>
                            <option value="<?php echo $assessment_filter['quiz_id']; ?>" <?php echo (string)$filter_assessment_id_main === (string)$assessment_filter['quiz_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($assessment_filter['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="submit" class="bg-accent text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-300 ease-in-out flex items-center">
                        <i class="fas fa-filter mr-2"></i> Apply Filter
                    </button>
                    <a href="view_results.php" class="bg-gray-400 text-white px-6 py-2 rounded-md hover:bg-gray-500 transition duration-300 ease-in-out flex items-center">
                        <i class="fas fa-undo mr-2"></i> Reset Filter
                    </a>
                </div>
            </form>
        </div>

        <div class="bg-gray-100 p-6 rounded-lg shadow-inner lg:w-1/2">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">View Attempts by Student</h3>
            <form action="view_results.php" method="GET" class="space-y-4">
                <div>
                    <label for="view_user" class="block text-sm font-medium text-gray-700 mb-1">Select Student:</label>
                    <select name="view_user" id="view_user"
                            class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50 select2-enabled"
                            data-placeholder="-- Select a Student --" data-allow-clear="true" required>
                        <option value=""></option> <?php foreach ($all_users as $user_filter) : ?>
                            <option value="<?php echo $user_filter['user_id']; ?>">
                                <?php echo htmlspecialchars($user_filter['username']); ?> (<?php echo htmlspecialchars($user_filter['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-300 ease-in-out flex items-center">
                        <i class="fas fa-user-circle mr-2"></i> View Student's Attempts
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($assessments)) : ?>
        <div class="overflow-x-auto bg-white rounded-lg shadow-md mt-8">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Attempts</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed Attempts</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Score (%)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Violation Count</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($assessments as $assessment) : ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($assessment['title']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($assessment['total_attempts']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($assessment['completed_attempts_count']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($assessment['avg_score_percentage']); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="<?php echo ($assessment['violation_count'] > 0) ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo htmlspecialchars($assessment['violation_count']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view_results.php?view_quiz=<?php echo $assessment['quiz_id']; ?>" class="text-accent hover:text-blue-700 inline-flex items-center">
                                    View Attempts <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <p class="text-center text-gray-600 py-8 mt-8">No assessments found.</p>
    <?php endif; ?>
</div>