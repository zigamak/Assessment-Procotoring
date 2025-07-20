<?php
// admin/assessments.php
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

// Fetch all assessments
try {
    $stmt = $pdo->query("
        SELECT quiz_id, title, description, max_attempts, duration_minutes, grade,
               is_paid, assessment_fee, open_datetime, created_at, updated_at
        FROM quizzes 
        ORDER BY created_at DESC
    ");
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Assessments Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "Could not fetch assessments. Please try again later.";
    $_SESSION['form_message_type'] = 'error';
}

require_once '../includes/header_admin.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assessments - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-navy-900 { background-color: #0a1930; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
        .border-custom-dark { border-color: #171248; }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            z-index: 50;
            margin-top: 0.25rem;
            min-width: 12rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            background-color: white;
        }
        .dropdown.show .dropdown-content {
            display: block;
        }
        .dropdown-item:hover {
            background-color: #171248;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <?php require_once '../includes/header_admin.php'; ?>

    <main class="flex-1 p-4 lg:p-8 mt-16 w-full max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Manage Assessments</h1>
            <a href="add_assessment.php"
               class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center space-x-2 transition duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Add New Assessment</span>
            </a>
        </div>

        <!-- Notification -->
        <div id="form-notification" class="fixed top-4 right-4 px-4 py-3 rounded-md hidden z-50" role="alert">
            <strong class="font-bold"></strong>
            <span class="block sm:inline" id="notification-message-content"></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                    <path d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </span>
        </div>

        <!-- Assessments Table -->
        <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold text-gray-800">Existing Assessments</h2>
                <div>
                    <input type="text" id="assessmentSearch" placeholder="Search by title or grade..."
                           class="shadow appearance-none border-2 border-custom-dark rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-navy-900 w-64">
                </div>
            </div>
            
            <?php if (empty($assessments)): ?>
                <p class="text-gray-600">No assessments found.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Grade</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Opening Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="assessmentTableBody">
                        <?php foreach ($assessments as $assessment): ?>
                            <tr class="assessment-row">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['title']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($assessment['grade'] ? $assessment['grade'] : 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $assessment['open_datetime'] ? date('M j, Y g:i A', strtotime($assessment['open_datetime'])) : 'Immediate'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $assessment['is_paid'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $assessment['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center space-x-2 justify-end">
                                        <a href="view_assessment.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                                           class="text-blue-600 hover:text-blue-800 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View
                                        </a>
                                        <div class="relative dropdown">
                                            <button type="button" class="inline-flex justify-center w-full rounded-md px-2 py-1 text-gray-700 hover:bg-gray-100 focus:outline-none"
                                                    onclick="toggleDropdown(this)">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path>
                                                </svg>
                                            </button>
                                            <div class="dropdown-content origin-top-right right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                                <div class="py-1" role="menu" aria-orientation="vertical">
                                                    <a href="edit_assessment.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                                                       class="dropdown-item block px-4 py-2 text-sm text-gray-700 hover:bg-navy-900 hover:text-white w-full text-left flex items-center"
                                                       role="menuitem">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                        </svg>
                                                        Edit Assessment
                                                    </a>
                                                    <?php if ($assessment['is_paid'] && $assessment['assessment_fee'] !== null && $assessment['assessment_fee'] > 0): ?>
                                                        <button onclick="showPaymentLink('<?php echo htmlspecialchars(BASE_URL . '/auth/payment.php?quiz_id=' . $assessment['quiz_id'] . '&amount=' . $assessment['assessment_fee']); ?>')"
                                                                class="dropdown-item block px-4 py-2 text-sm text-gray-700 hover:bg-navy-900 hover:text-white w-full text-left flex items-center"
                                                                role="menuitem">
                                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                            </svg>
                                                            Payment Link
                                                        </button>
                                                    <?php endif; ?>
                                                    <form action="assessments.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this assessment? This cannot be undone if there are no associated payments.');">
                                                        <input type="hidden" name="action" value="delete_assessment">
                                                        <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($assessment['quiz_id']); ?>">
                                                        <button type="submit"
                                                                class="dropdown-item block px-4 py-2 text-sm text-red-600 hover:bg-red-600 hover:text-white w-full text-left flex items-center"
                                                                role="menuitem">
                                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="<?php echo BASE_URL; ?>admin/questions.php?quiz_id=<?php echo htmlspecialchars($assessment['quiz_id']); ?>"
                                           class="text-navy-900 hover:text-navy-700 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                                            </svg>
                                            Questions
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Payment Link Modal -->
        <div id="paymentLinkModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
            <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Payment Link</h2>
                <p class="text-gray-600 mb-4">Copy the link below to share with users for registration and payment.</p>
                <div class="bg-gray-100 p-3 rounded-md">
                    <input id="paymentLinkInput" type="text" readonly
                           class="w-full bg-transparent text-gray-700 focus:outline-none">
                </div>
                <div class="flex justify-between space-x-4 mt-6">
                    <button type="button" onclick="copyPaymentLink()"
                            class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Copy to Clipboard
                    </button>
                    <button type="button" onclick="document.getElementById('paymentLinkModal').classList.add('hidden');"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </main>

    <?php require_once '../includes/footer_admin.php'; ?>

    <script>
        let currentOpenDropdown = null;

        function toggleDropdown(button) {
            const dropdown = button.closest('.dropdown');
            if (currentOpenDropdown && currentOpenDropdown !== dropdown) {
                currentOpenDropdown.classList.remove('show');
            }
            dropdown.classList.toggle('show');
            currentOpenDropdown = dropdown.classList.contains('show') ? dropdown : null;
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown') && currentOpenDropdown) {
                currentOpenDropdown.classList.remove('show');
                currentOpenDropdown = null;
            }
            if (e.target.id === 'paymentLinkModal') {
                e.target.classList.add('hidden');
            }
        });

        function showPaymentLink(link) {
            document.getElementById('paymentLinkInput').value = link;
            document.getElementById('paymentLinkModal').classList.remove('hidden');
        }

        async function copyPaymentLink() {
            try {
                const input = document.getElementById('paymentLinkInput');
                await navigator.clipboard.writeText(input.value);
                displayNotification('Payment link copied to clipboard!', 'success');
            } catch (err) {
                displayNotification('Failed to copy payment link.', 'error');
                console.error('Copy failed:', err);
            }
        }

        function displayNotification(message, type) {
            const notificationContainer = document.getElementById('form-notification');
            const messageContentElement = document.getElementById('notification-message-content');
            const strongTag = notificationContainer.querySelector('strong');

            notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
            strongTag.textContent = '';

            if (message) {
                messageContentElement.textContent = message;
                if (type === 'success') {
                    notificationContainer.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
                    strongTag.textContent = 'Success!';
                } else {
                    notificationContainer.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
                    strongTag.textContent = 'Error!';
                }
                notificationContainer.classList.remove('hidden');
                notificationContainer.style.transform = 'translateY(-100%)';
                setTimeout(() => {
                    notificationContainer.style.transition = 'transform 0.3s ease-out';
                    notificationContainer.style.transform = 'translateY(0)';
                }, 10);
                setTimeout(() => {
                    hideNotification();
                }, 5000);
            }
        }

        function hideNotification() {
            const notificationElement = document.getElementById('form-notification');
            notificationElement.style.transition = 'transform 0.3s ease-in';
            notificationElement.style.transform = 'translateY(-100%)';
            notificationElement.addEventListener('transitionend', function handler() {
                notificationElement.classList.add('hidden');
                notificationElement.removeEventListener('transitionend', handler);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('assessmentSearch');
            const tableBody = document.getElementById('assessmentTableBody');
            const rows = tableBody.getElementsByClassName('assessment-row');
            searchInput.addEventListener('input', function() {
                const searchTerm = searchInput.value.toLowerCase();
                Array.from(rows).forEach(row => {
                    const title = row.cells[0].textContent.toLowerCase();
                    const grade = row.cells[1].textContent.toLowerCase();
                    row.style.display = (title.includes(searchTerm) || grade.includes(searchTerm)) ? '' : 'none';
                });
            });

            // Display session messages
            <?php if (isset($_SESSION['form_message'])): ?>
                displayNotification("<?php echo htmlspecialchars($_SESSION['form_message']); ?>", 
                                  "<?php echo htmlspecialchars($_SESSION['form_message_type']); ?>");
                <?php
                unset($_SESSION['form_message']);
                unset($_SESSION['form_message_type']);
                ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>