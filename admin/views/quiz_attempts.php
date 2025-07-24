<?php
// admin/views/quiz_attempts.php
// This view displays all attempts for a specific quiz, with filtering options.

if (!isset($results) || !isset($current_quiz) || !isset($all_users) || !isset($summary_metrics)) {
    echo display_message("Error: Required data not found for quiz attempts.", "error");
    exit;
}

// Function to format datetime for display with truncation and full on hover
function format_and_truncate_datetime($datetime_str) {
    if (!$datetime_str) {
        return 'N/A';
    }
    $timestamp = strtotime($datetime_str);
    $full_format = date('H:i:s, M d, Y', $timestamp); // Time first, then date
    $truncated_format = date('H:i, M d', $timestamp) . '...'; // Truncated
    return "<span title='" . htmlspecialchars($full_format) . "'>" . htmlspecialchars($truncated_format) . "</span>";
}

?>

<div class="flex flex-col gap-6">

    <div class="flex-1 bg-white shadow-lg rounded-lg p-6 mb-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
            Results for Assessment: <span class="text-accent"><?php echo htmlspecialchars($current_quiz['title']); ?></span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
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

        <div class="text-right mb-4">
            <button id="openFilterButton" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700 transition duration-300 ease-in-out flex items-center justify-center ml-auto">
                <i class="fas fa-filter mr-2"></i> Open Filters
            </button>
        </div>

        <?php if (!empty($results)) : ?>
            <div class="overflow-x-auto bg-white rounded-lg shadow-md mt-8">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($results as $attempt) : ?>
                            <tr class="hover:bg-gray-50">
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
                                    <?php echo format_and_truncate_datetime($attempt['start_time']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo format_and_truncate_datetime($attempt['end_time']); ?>
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
</div>

<div id="filterPopup" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex justify-end transition-opacity duration-300 ease-in-out opacity-0">
    <div id="filterSidebar" class="w-full sm:w-80 bg-gray-100 shadow-lg p-6 transform translate-x-full transition-transform duration-300 ease-in-out relative">
        <button id="closeFilterButton" class="absolute top-4 right-4 text-gray-600 hover:text-gray-900 text-2xl">
            <i class="fas fa-times"></i>
        </button>
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Filter Attempts</h3>
        <form action="results.php" method="GET" class="space-y-4">
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

            <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-2 mt-4">
                <button type="submit" class="bg-accent text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-300 ease-in-out flex items-center justify-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="results.php?view_quiz=<?php echo htmlspecialchars($current_quiz['quiz_id']); ?>" class="bg-gray-400 text-white px-6 py-2 rounded-md hover:bg-gray-500 transition duration-300 ease-in-out flex items-center justify-center">
                    <i class="fas fa-undo mr-2"></i> Reset Filters
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    const openFilterButton = document.getElementById('openFilterButton');
    const closeFilterButton = document.getElementById('closeFilterButton');
    const filterPopup = document.getElementById('filterPopup');
    const filterSidebar = document.getElementById('filterSidebar');

    function openFilter() {
        filterPopup.classList.remove('hidden');
        // Trigger opacity transition
        setTimeout(() => {
            filterPopup.classList.remove('opacity-0');
            filterSidebar.classList.remove('translate-x-full');
        }, 10); // Small delay to allow 'hidden' removal to register
    }

    function closeFilter() {
        filterPopup.classList.add('opacity-0');
        filterSidebar.classList.add('translate-x-full');
        // Hide the popup after the transition completes
        filterSidebar.addEventListener('transitionend', function handler() {
            filterPopup.classList.add('hidden');
            filterSidebar.removeEventListener('transitionend', handler);
        });
    }

    openFilterButton.addEventListener('click', openFilter);
    closeFilterButton.addEventListener('click', closeFilter);

    // Close sidebar if clicking outside the sidebar content but within the popup overlay
    filterPopup.addEventListener('click', function(event) {
        if (!filterSidebar.contains(event.target) && event.target !== openFilterButton) {
            closeFilter();
        }
    });
</script>