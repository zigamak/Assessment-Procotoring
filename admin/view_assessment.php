<?php
// admin/view_assessment.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

// Ensure only admins can access
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../auth/login.php');
    exit;
}

// Check if quiz_id is provided
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
if ($quiz_id <= 0) {
    $_SESSION['form_message'] = "Invalid assessment ID.";
    $_SESSION['form_message_type'] = 'error';
    redirect('assessments.php');
    exit;
}

// Define all possible grades for checkboxes
$allPossibleGrades = [
    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
    'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'
];

// Fetch assessment details
try {
    $stmt = $pdo->prepare("
        SELECT quiz_id, title, description, max_attempts, duration_minutes, grade,
               is_paid, assessment_fee, open_datetime, created_at, updated_at
        FROM quizzes
        WHERE quiz_id = :quiz_id
    ");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assessment) {
        $_SESSION['form_message'] = "Assessment not found.";
        $_SESSION['form_message_type'] = 'error';
        redirect('assessments.php');
        exit;
    }
    
    // Prepare grades for display
    $current_grades_for_display = [];
    if (!empty($assessment['grade'])) {
        $current_grades_for_display = explode(',', $assessment['grade']);
    }
} catch (PDOException $e) {
    error_log("Fetch Assessment Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "Database error while fetching assessment: " . htmlspecialchars($e->getMessage());
    $_SESSION['form_message_type'] = 'error';
    redirect('assessments.php');
    exit;
}

require_once '../includes/header_admin.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assessment - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Custom scrollbar for better aesthetics */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
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
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php require_once '../includes/header_admin.php'; ?>

    <main class="flex-1 p-4 lg:p-8 mt-16 w-full max-w-5xl mx-auto">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-8">
            <h1 class="text-3xl lg:text-4xl font-extrabold text-gray-900 mb-4 sm:mb-0">Assessment Details</h1>
            <button onclick="openEditModal()" class="flex items-center px-6 py-3 bg-indigo-700 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-800 transition duration-300 ease-in-out transform hover:scale-105">
                <i class="fas fa-edit mr-3"></i> Edit Assessment
            </button>
        </div>

        <div id="form-notification" class="fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg hidden z-50 transition-all duration-300 ease-out" role="alert">
            <strong class="font-bold text-lg"></strong>
            <span class="block sm:inline text-base" id="notification-message-content"></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                <svg class="h-6 w-6 fill-current" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>

        <div class="bg-white p-8 rounded-xl shadow-2xl w-full custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <div class="detail-item">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Title:</label>
                    <p class="text-gray-900 text-lg font-semibold"><?php echo htmlspecialchars($assessment['title']); ?></p>
                </div>
                <div class="detail-item">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Max Attempts:</label>
                    <p class="text-gray-900 text-lg"><?php echo $assessment['max_attempts'] == 0 ? 'Unlimited' : htmlspecialchars($assessment['max_attempts']); ?></p>
                </div>
                <div class="detail-item">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Duration:</label>
                    <p class="text-gray-900 text-lg"><?php echo $assessment['duration_minutes'] ? htmlspecialchars($assessment['duration_minutes']) . ' minutes' : 'No limit'; ?></p>
                </div>
                <div class="detail-item">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Opening Date/Time:</label>
                    <p class="text-gray-900 text-lg"><?php echo $assessment['open_datetime'] ? date('M j, Y g:i A', strtotime($assessment['open_datetime'])) : 'Immediate'; ?></p>
                </div>
                <div class="detail-item">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Payment Required:</label>
                    <p class="text-gray-900 text-lg"><?php echo $assessment['is_paid'] ? 'Yes' : 'No'; ?></p>
                </div>
                <div class="detail-item <?php echo $assessment['is_paid'] ? '' : 'hidden'; ?>">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Assessment Fee (NGN):</label>
                    <p class="text-gray-900 text-lg font-semibold"><?php echo $assessment['assessment_fee'] ? 'NGN ' . number_format($assessment['assessment_fee'], 2) : 'N/A'; ?></p>
                </div>
                <div class="detail-item">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Created At:</label>
                    <p class="text-gray-900 text-lg"><?php echo date('M j, Y g:i A', strtotime($assessment['created_at'])); ?></p>
                </div>
                <div class="detail-item">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Updated At:</label>
                    <p class="text-gray-900 text-lg"><?php echo $assessment['updated_at'] ? date('M j, Y g:i A', strtotime($assessment['updated_at'])) : 'N/A'; ?></p>
                </div>
                <div class="detail-item md:col-span-2">
                    <label class="block text-gray-600 text-sm font-medium mb-1">Description:</label>
                    <p class="text-gray-900 text-lg leading-relaxed"><?php echo nl2br(htmlspecialchars($assessment['description'] ?? 'No description provided.')); ?></p>
                </div>
                <div class="detail-item md:col-span-2">
                    <label class="block text-gray-600 text-sm font-medium mb-2">Applicable Grade Level(s):</label>
                    <div class="flex flex-wrap gap-2">
                        <?php if (!empty($current_grades_for_display)): ?>
                            <?php foreach ($current_grades_for_display as $grade): ?>
                                <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm font-medium">
                                    <?php echo htmlspecialchars($grade); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-gray-500 text-lg">No specific grades assigned.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex justify-end space-x-4 mt-10 border-t pt-6">
                <a href="<?php echo BASE_URL; ?>admin/questions.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                   class="flex items-center px-6 py-3 bg-teal-600 text-white font-semibold rounded-lg shadow-md hover:bg-teal-700 transition duration-300 ease-in-out transform hover:scale-105">
                    <i class="fas fa-question-circle mr-3"></i> Manage Questions
                </a>
                <a href="assessments.php"
                   class="flex items-center px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg shadow-md hover:bg-gray-300 transition duration-300 ease-in-out transform hover:scale-105">
                    <i class="fas fa-times-circle mr-3"></i> Close
                </a>
            </div>
        </div>
    </main>

    <div id="editModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center p-4 z-50 hidden transition-opacity duration-300 ease-out opacity-0">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform scale-95 transition-transform duration-300 ease-out">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h2 class="text-3xl font-bold text-gray-800">Edit Assessment</h2>
                <button type="button" onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700 transition duration-200">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form id="editAssessmentForm" action="edit_assessment.php" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="edit_assessment">
                <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($assessment['quiz_id']); ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="edit_title" class="block text-gray-700 text-sm font-semibold mb-2">Assessment Title:</label>
                        <input type="text" id="edit_title" name="title" value="<?php echo htmlspecialchars($assessment['title']); ?>" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Applicable Grade Level(s):</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <?php foreach ($allPossibleGrades as $gradeOption): ?>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="grades[]" value="<?php echo htmlspecialchars($gradeOption); ?>"
                                           class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500"
                                           <?php echo in_array($gradeOption, $current_grades_for_display) ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-gray-800 font-medium"><?php echo htmlspecialchars($gradeOption); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Select one or more grades this assessment applies to.</p>
                    </div>

                    <div>
                        <label for="edit_max_attempts" class="block text-gray-700 text-sm font-semibold mb-2">Max Attempts (0 for unlimited):</label>
                        <input type="number" id="edit_max_attempts" name="max_attempts" value="<?php echo htmlspecialchars($assessment['max_attempts']); ?>" min="0" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="edit_duration_minutes" class="block text-gray-700 text-sm font-semibold mb-2">Duration (minutes, leave blank for no limit):</label>
                        <input type="number" id="edit_duration_minutes" name="duration_minutes" value="<?php echo htmlspecialchars($assessment['duration_minutes'] ?? ''); ?>" min="1"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="edit_open_datetime" class="block text-gray-700 text-sm font-semibold mb-2">Opening Date/Time:</label>
                        <input type="datetime-local" id="edit_open_datetime" name="open_datetime" value="<?php echo $assessment['open_datetime'] ? date('Y-m-d\TH:i', strtotime($assessment['open_datetime'])) : ''; ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent">
                    </div>
                    
                    <div>
                        <div class="flex items-center mb-2">
                            <input type="checkbox" id="edit_is_paid" name="is_paid" value="1" <?php echo $assessment['is_paid'] ? 'checked' : ''; ?>
                                    class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500">
                            <label for="edit_is_paid" class="ml-2 text-gray-800 font-medium">Requires Payment</label>
                        </div>
                        <div id="edit_assessment_fee_group" class="<?php echo $assessment['is_paid'] ? '' : 'hidden'; ?>">
                            <label for="edit_assessment_fee" class="block text-gray-700 text-sm font-semibold mb-2">Assessment Fee (NGN):</label>
                            <input type="number" id="edit_assessment_fee" name="assessment_fee" value="<?php echo htmlspecialchars($assessment['assessment_fee'] ?? ''); ?>" step="0.01" min="0"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent" <?php echo $assessment['is_paid'] ? 'required' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="edit_description" class="block text-gray-700 text-sm font-semibold mb-2">Description:</label>
                        <textarea id="edit_description" name="description" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent placeholder-gray-400"
                                placeholder="Enter assessment description..."><?php echo htmlspecialchars($assessment['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeEditModal()"
                            class="px-6 py-3 bg-gray-300 text-gray-800 font-semibold rounded-lg shadow-md hover:bg-gray-400 transition duration-300 ease-in-out">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-indigo-700 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-800 transition duration-300 ease-in-out transform hover:scale-105">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php require_once '../includes/footer_admin.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        function displayNotification(message, type) {
            const notificationContainer = document.getElementById('form-notification');
            const messageContentElement = document.getElementById('notification-message-content');
            const strongTag = notificationContainer.querySelector('strong');

            // Reset classes
            notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
            notificationContainer.classList.remove('border-red-500', 'border-green-500'); // For stronger border
            strongTag.textContent = '';

            if (message) {
                messageContentElement.textContent = message;
                if (type === 'success') {
                    notificationContainer.classList.add('bg-green-100', 'border-green-500', 'text-green-800');
                    strongTag.textContent = 'Success!';
                } else {
                    notificationContainer.classList.add('bg-red-100', 'border-red-500', 'text-red-800');
                    strongTag.textContent = 'Error!';
                }
                notificationContainer.classList.remove('hidden');
                // Animate in
                setTimeout(() => {
                    notificationContainer.style.opacity = '1';
                    notificationContainer.style.transform = 'translateY(0)';
                }, 10);
                
                setTimeout(() => {
                    hideNotification();
                }, 5000); // Notification disappears after 5 seconds
            }
        }

        function hideNotification() {
            const notificationElement = document.getElementById('form-notification');
            // Animate out
            notificationElement.style.opacity = '0';
            notificationElement.style.transform = 'translateY(-20px)';
            notificationElement.addEventListener('transitionend', function handler() {
                notificationElement.classList.add('hidden');
                notificationElement.removeEventListener('transitionend', handler);
            });
        }

        function openEditModal() {
            const editModal = document.getElementById('editModal');
            editModal.classList.remove('hidden');
            // Animate in
            setTimeout(() => {
                editModal.style.opacity = '1';
                editModal.querySelector(':first-child').style.transform = 'scale(1)';
            }, 10);
            
            // Initialize datetime picker
            flatpickr("#edit_open_datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today",
                time_24hr: true
            });

            // Toggle payment requirement fields
            const editIsPaidCheckbox = document.getElementById('edit_is_paid');
            const editAssessmentFeeGroup = document.getElementById('edit_assessment_fee_group');
            const editAssessmentFeeInput = document.getElementById('edit_assessment_fee');
            
            function toggleEditAssessmentFeeVisibility() {
                if (editIsPaidCheckbox.checked) {
                    editAssessmentFeeGroup.classList.remove('hidden');
                    editAssessmentFeeInput.setAttribute('required', 'required');
                } else {
                    editAssessmentFeeGroup.classList.add('hidden');
                    editAssessmentFeeInput.removeAttribute('required');
                    editAssessmentFeeInput.value = ''; // Clear fee if payment not required
                }
            }
            
            editIsPaidCheckbox.addEventListener('change', toggleEditAssessmentFeeVisibility);
            toggleEditAssessmentFeeVisibility(); // Call on open to set initial state
        }

        function closeEditModal() {
            const editModal = document.getElementById('editModal');
            // Animate out
            editModal.style.opacity = '0';
            editModal.querySelector(':first-child').style.transform = 'scale(0.95)';
            editModal.addEventListener('transitionend', function handler() {
                editModal.classList.add('hidden');
                editModal.removeEventListener('transitionend', handler);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Display session messages
            <?php if (isset($_SESSION['form_message'])): ?>
                displayNotification("<?php echo htmlspecialchars($_SESSION['form_message']); ?>", 
                                    "<?php echo htmlspecialchars($_SESSION['form_message_type']); ?>");
                <?php
                unset($_SESSION['form_message']);
                unset($_SESSION['form_message_type']);
                ?>
            <?php endif; ?>

            // Close modal when clicking outside
            document.getElementById('editModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEditModal();
                }
            });

            // Handle form submission via Fetch API
            document.getElementById('editAssessmentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('edit_assessment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        return response.text(); // or response.json() if your PHP returns JSON
                    }
                    throw new Error('Network response was not ok');
                })
                .then(data => {
                    // For a simple reload, you can do this.
                    // If your edit_assessment.php sets session messages,
                    // they will be picked up on reload.
                    window.location.reload(); 
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayNotification('An error occurred while saving the assessment.', 'error');
                });
            });
        });
    </script>
</body>
</html>