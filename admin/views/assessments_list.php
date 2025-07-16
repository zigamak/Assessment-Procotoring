<?php
// admin/views/assessments_list.php
// This view displays a list of all assessments with summary statistics and filters.

if (!isset($assessments) || !isset($all_assessments_for_filters) || !isset($all_users)) {
    // Redirect or show an error if data is not properly passed
    echo display_message("Error: Required data not found.", "error");
    exit;
}
?>

<div class="container mx-auto p-4 py-8">
    <div class="bg-white shadow-lg rounded-lg p-6 mb-8">
        <h2 class="text-4xl font-extrabold text-accent mb-8 text-center leading-tight">
            Assessment Overview
        </h2>

        <div class="flex flex-col md:flex-row justify-center items-center space-y-4 md:space-y-0 md:space-x-6 mb-10">
            <button id="showFilterAssessmentPopoverBtn" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-8 py-3 rounded-full shadow-lg hover:shadow-xl hover:from-blue-600 hover:to-blue-700 transition duration-300 ease-in-out flex items-center justify-center text-lg font-semibold">
                <i class="fas fa-filter mr-3"></i> Filter by Assessment
            </button>
            <button id="showViewStudentPopoverBtn" class="bg-gradient-to-r from-purple-500 to-purple-600 text-white px-8 py-3 rounded-full shadow-lg hover:shadow-xl hover:from-purple-600 hover:to-purple-700 transition duration-300 ease-in-out flex items-center justify-center text-lg font-semibold">
                <i class="fas fa-user-circle mr-3"></i> View Attempts by Student
            </button>
        </div>

        <?php if (!empty($assessments)) : ?>
            <div class="overflow-x-auto bg-white rounded-xl shadow-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Assessment Title</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Attempts</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Completed</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Avg. Score (%)</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Violations</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php foreach ($assessments as $assessment) : ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($assessment['title']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($assessment['total_attempts']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($assessment['completed_attempts_count']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <span class="font-semibold <?php echo ($assessment['avg_score_percentage'] >= 75) ? 'text-green-600' : (($assessment['avg_score_percentage'] >= 50) ? 'text-orange-500' : 'text-red-600'); ?>">
                                        <?php echo htmlspecialchars(number_format($assessment['avg_score_percentage'], 1)); ?>%
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <span class="<?php echo ($assessment['violation_count'] > 0) ? 'text-red-600 font-bold' : 'text-green-600'; ?>">
                                        <?php echo htmlspecialchars($assessment['violation_count']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="results.php?view_quiz=<?php echo $assessment['quiz_id']; ?>" class="text-accent hover:text-blue-700 inline-flex items-center text-sm font-medium">
                                        View Details <i class="fas fa-external-link-alt ml-2 text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="bg-gray-100 rounded-lg p-6 text-center shadow-inner mt-8">
                <p class="text-lg text-gray-700 mb-2">No assessments have been created yet.</p>
                <p class="text-md text-gray-500">Start by adding a new assessment to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="filterAssessmentPopover" class="fixed inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center hidden z-[1000]">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-lg w-full mx-4 relative transform scale-95 opacity-0 transition-all duration-300 ease-out" id="filterAssessmentPopoverContent">
        <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-3xl transition-colors duration-200 popover-close-btn" data-popover-id="filterAssessmentPopover">
            <i class="fas fa-times-circle"></i>
        </button>
        <h3 class="text-3xl font-bold text-gray-800 mb-6 text-center">Filter by Assessment</h3>
        <form action="results.php" method="GET" class="space-y-6">
            <div>
                <label for="filter_assessment_id_main_popover" class="block text-sm font-medium text-gray-700 mb-2">Select Assessment:</label>
                <select name="assessment_id_main" id="filter_assessment_id_main_popover"
                        class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50"
                        data-placeholder="All Assessments" data-allow-clear="true">
                    <option value=""></option>
                    <?php foreach ($all_assessments_for_filters as $assessment_filter) : ?>
                        <option value="<?php echo $assessment_filter['quiz_id']; ?>" <?php echo (isset($filter_assessment_id_main) && (string)$filter_assessment_id_main === (string)$assessment_filter['quiz_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($assessment_filter['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end space-x-3 mt-8">
                <button type="submit" class="bg-accent text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 ease-in-out flex items-center font-semibold shadow-md">
                    <i class="fas fa-filter mr-2"></i> Apply Filter
                </button>
                <a href="results.php" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition duration-300 ease-in-out flex items-center font-semibold shadow-md">
                    <i class="fas fa-undo mr-2"></i> Reset Filter
                </a>
            </div>
        </form>
    </div>
</div>

<div id="viewStudentPopover" class="fixed inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center hidden z-[1000]">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-lg w-full mx-4 relative transform scale-95 opacity-0 transition-all duration-300 ease-out" id="viewStudentPopoverContent">
        <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-3xl transition-colors duration-200 popover-close-btn" data-popover-id="viewStudentPopover">
            <i class="fas fa-times-circle"></i>
        </button>
        <h3 class="text-3xl font-bold text-gray-800 mb-6 text-center">View Attempts by Student</h3>
        <form action="results.php" method="GET" class="space-y-6">
            <div>
                <label for="view_user_popover" class="block text-sm font-medium text-gray-700 mb-2">Select Student:</label>
                <select name="view_user" id="view_user_popover"
                        class="form-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50"
                        data-placeholder="-- Select a Student --" data-allow-clear="true" required>
                    <option value=""></option>
                    <?php foreach ($all_users as $user_filter) : ?>
                        <option value="<?php echo $user_filter['user_id']; ?>">
                            <?php echo htmlspecialchars($user_filter['username']); ?> (<?php echo htmlspecialchars($user_filter['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end mt-8">
                <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition duration-300 ease-in-out flex items-center font-semibold shadow-md">
                    <i class="fas fa-user-circle mr-2"></i> View Student's Attempts
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Ensure these functions are globally available by defining them directly,
    // not just inside $(document).ready()
    function showPopover(popoverId) {
        const popover = document.getElementById(popoverId);
        const popoverContent = document.getElementById(popoverId + 'Content');
        if (popover && popoverContent) {
            popover.classList.remove('hidden');
            // Animate in
            setTimeout(() => {
                popoverContent.classList.remove('scale-95', 'opacity-0');
                popoverContent.classList.add('scale-100', 'opacity-100');
            }, 10); // Small delay to ensure hidden class is removed first

            // Re-initialize Select2 for any select element within this popover
            // Ensure jQuery and Select2 are loaded
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
                if (popoverId === 'filterAssessmentPopover') {
                    $('#filter_assessment_id_main_popover').select2({
                        placeholder: $('#filter_assessment_id_main_popover').data('placeholder') || "-- Select an option --",
                        allowClear: $('#filter_assessment_id_main_popover').data('allow-clear') || false,
                        dropdownParent: $('#filterAssessmentPopover'), // Important for z-index issues
                        width: 'resolve'
                    });
                } else if (popoverId === 'viewStudentPopover') {
                    $('#view_user_popover').select2({
                        placeholder: $('#view_user_popover').data('placeholder') || "-- Select a Student --",
                        allowClear: $('#view_user_popover').data('allow-clear') || false,
                        dropdownParent: $('#viewStudentPopover'), // Important for z-index issues
                        width: 'resolve'
                    });
                }
            } else {
                console.warn("jQuery or Select2 not loaded. Select2 functionality will be unavailable.");
            }
        }
    }

    function hidePopover(popoverId) {
        const popover = document.getElementById(popoverId);
        const popoverContent = document.getElementById(popoverId + 'Content');
        if (popover && popoverContent) {
            // Animate out
            popoverContent.classList.remove('scale-100', 'opacity-100');
            popoverContent.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                popover.classList.add('hidden');
                // Destroy Select2 to prevent multiple initializations or memory leaks
                if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
                    if (popoverId === 'filterAssessmentPopover') {
                        if ($('#filter_assessment_id_main_popover').data('select2')) {
                            $('#filter_assessment_id_main_popover').select2('destroy');
                        }
                    } else if (popoverId === 'viewStudentPopover') {
                        if ($('#view_user_popover').data('select2')) {
                            $('#view_user_popover').select2('destroy');
                        }
                    }
                }
            }, 300); // Duration of the transition (300ms)
        }
    }

    $(document).ready(function() {
        // Get references to the trigger buttons
        const showFilterAssessmentPopoverBtn = document.getElementById('showFilterAssessmentPopoverBtn');
        const showViewStudentPopoverBtn = document.getElementById('showViewStudentPopoverBtn');

        // Add event listeners to the trigger buttons
        if (showFilterAssessmentPopoverBtn) {
            showFilterAssessmentPopoverBtn.addEventListener('click', function() {
                showPopover('filterAssessmentPopover');
            });
        }

        if (showViewStudentPopoverBtn) {
            showViewStudentPopoverBtn.addEventListener('click', function() {
                showPopover('viewStudentPopover');
            });
        }

        // --- NEW/MODIFIED: Event listener for close buttons ---
        // Use event delegation for all popover-close-btn to ensure they work even if popovers are dynamically added
        document.addEventListener('click', function(event) {
            // Check if the clicked element or its parent is a .popover-close-btn
            const closeButton = event.target.closest('.popover-close-btn');
            if (closeButton) {
                const popoverId = closeButton.dataset.popoverId;
                if (popoverId) {
                    hidePopover(popoverId);
                }
            }

            // Existing logic for closing popovers when clicking outside their content
            const filterPopover = document.getElementById('filterAssessmentPopover');
            const studentPopover = document.getElementById('viewStudentPopover');

            if (filterPopover && !filterPopover.classList.contains('hidden')) {
                const filterContent = document.getElementById('filterAssessmentPopoverContent');
                if (filterContent && !filterContent.contains(event.target) && !showFilterAssessmentPopoverBtn.contains(event.target)) {
                    hidePopover('filterAssessmentPopover');
                }
            }

            if (studentPopover && !studentPopover.classList.contains('hidden')) {
                const studentContent = document.getElementById('viewStudentPopoverContent');
                if (studentContent && !studentContent.contains(event.target) && !showViewStudentPopoverBtn.contains(event.target)) {
                    hidePopover('viewStudentPopover');
                }
            }
        });

        // Close popovers with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const filterPopover = document.getElementById('filterAssessmentPopover');
                const studentPopover = document.getElementById('viewStudentPopover');
                if (filterPopover && !filterPopover.classList.contains('hidden')) {
                    hidePopover('filterAssessmentPopover');
                }
                if (studentPopover && !studentPopover.classList.contains('hidden')) {
                    hidePopover('viewStudentPopover');
                }
            }
        });
    });
</script>