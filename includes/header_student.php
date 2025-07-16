<?php
// includes/header_student.php
// Header template for the Student dashboard with sidebar navigation and back button.
// This header should only be included after role enforcement.

require_once 'session.php';
require_once 'functions.php';
require_once 'db.php'; // Ensure BASE_URL is available and provides $pdo connection

// Enforce that only students can access pages including this header
enforceRole('student', BASE_URL . 'auth/login.php'); // Redirect to login if not student

// Get the username from session for display
$logged_in_username = htmlspecialchars($_SESSION['username'] ?? 'Student');
$logged_in_user_id = $_SESSION['user_id'] ?? null; // Get user ID from session

// Function to fetch user details (updated to include first_name and last_name)
// This function needs to be defined once, preferably in functions.php or a dedicated model file.
// For demonstration, I'm including it here, but ideally, it's globally available.
if (!function_exists('fetchUserDetails')) {
    function fetchUserDetails($pdo, $user_id) {
        try {
            $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, role, passport_image_path, city, state, country, first_name, last_name FROM users WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching user details: " . $e->getMessage());
            return null;
        }
    }
}

$user_details = null;
$user_full_name = $logged_in_username; // Default to username
$user_profile_image = BASE_URL . 'assets/images/default_profile.png'; // Default placeholder image path (adjust as needed for students)

if ($logged_in_user_id && isset($pdo)) { // Ensure $pdo is available from db.php
    $user_details = fetchUserDetails($pdo, $logged_in_user_id);
    if ($user_details) {
        $first_name = htmlspecialchars($user_details['first_name'] ?? '');
        $last_name = htmlspecialchars($user_details['last_name'] ?? '');
        if (!empty($first_name) || !empty($last_name)) {
            $user_full_name = trim($first_name . ' ' . $last_name);
        } else {
            $user_full_name = htmlspecialchars($user_details['username'] ?? 'Student');
        }

        if (!empty($user_details['passport_image_path'])) {
            // Updated path for student header only
            $user_profile_image = BASE_URL . 'uploads/verification/' . $user_details['passport_image_path'];
        }
    }
}

// Define the theme colors for consistent styling
$sidebar_bg_color = "#1a202c"; // Darker shade for student sidebar
$text_color_light = "#e2e8f0"; // Light gray for text on dark background
$hover_color_sidebar = "#2d3748"; // Slightly lighter charcoal for hover
$accent_color = "#4299e1"; // A nice blue for active links/accents (your primary interactive color)
$header_bg_color = "#ffffff"; // White for the main header
$body_bg_color = "#f7fafc"; // Light gray background for the main content area

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Assessment System</title>
    <link rel="icon" type="image/png" href="https://mackennytutors.com/wp-content/uploads/2025/05/Mackenny.png"> <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: <?php echo $body_bg_color; ?>;
        }
        /* Custom theme colors via Tailwind JIT classes */
        .bg-sidebar { background-color: <?php echo $sidebar_bg_color; ?>; }
        .text-sidebar-light { color: <?php echo $text_color_light; ?>; }
        .hover\:bg-sidebar-hover:hover { background-color: <?php echo $hover_color_sidebar; ?>; }
        .bg-accent { background-color: <?php echo $accent_color; ?>; }
        .text-accent { color: <?php echo $accent_color; ?>; }
        .bg-header { background-color: <?php echo $header_bg_color; ?>; }

        /* Select2 specific styling adjustments to match Tailwind forms */
        .select2-container .select2-selection--single {
            height: 38px !important; /* Tailwind's form-input height */
            border: 1px solid #d1d5db !important; /* border-gray-300 */
            border-radius: 0.375rem !important; /* rounded-md */
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important; /* Adjust arrow height */
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important; /* Vertically align text */
            padding-left: 0.75rem !important; /* px-3 */
        }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: <?php echo $accent_color; ?> !important;
            color: white !important;
        }
        .select2-dropdown {
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); /* shadow-md */
            z-index: 50; /* Ensure dropdown is above other content */
        }
        .select2-search input {
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem !important;
        }
        .select2-container--default .select2-selection--single.select2-container--focus .select2-selection__rendered,
        .select2-container--default .select2-selection--single.select2-container--focus {
            border-color: <?php echo $accent_color; ?> !important; /* Focus ring color */
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5) !important; /* focus:ring-opacity-50 */
        }

        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 8px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: <?php echo $sidebar_bg_color; ?>; /* Match sidebar background */
            border-radius: 10px;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: <?php echo $hover_color_sidebar; ?>; /* Match hover color for thumb */
            border-radius: 10px;
        }
    </style>
</head>
<body class="flex min-h-screen">
    <aside class="w-64 bg-sidebar text-sidebar-light shadow-lg flex flex-col fixed inset-y-0 left-0 z-40">
        <div class="p-6 text-3xl font-bold border-b border-gray-700 flex items-center justify-center">
            <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="hover:opacity-80 transition duration-300">
                <img src="https://mackennytutors.com/wp-content/uploads/2025/05/Mackenny.png" alt="Mackenny Tutors Logo" class="h-10">
            </a>
        </div>
        <nav class="flex-grow p-4 overflow-y-auto sidebar">
            <ul class="space-y-2">
                <li>
                    <a href="<?php echo BASE_URL; ?>student/dashboard.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-tachometer-alt mr-3"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>student/assessments.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'assessments.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-history mr-3"></i> Assessments
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>student/payments.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-money-bill-wave mr-3"></i> Payments
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>student/profile.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-user-circle mr-3"></i> Profile
                    </a>
                </li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-700 text-sm">
            <div class="mb-2 text-sidebar-light">Welcome, <span class="font-semibold"><?php echo $logged_in_username; ?></span></div>
            <a href="<?php echo BASE_URL; ?>auth/logout.php" id="sidebarLogoutLink" class="block bg-red-600 text-white text-center py-2 rounded-md font-semibold hover:bg-red-700 transition duration-300">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col ml-64">
        <header class="bg-header p-4 shadow-md sticky top-0 z-30 border-b border-gray-200">
            <div class="container mx-auto flex justify-between items-center h-10">
                <div class="flex items-center">
                    <a href="javascript:history.back()" class="text-gray-600 hover:text-gray-800 mr-4">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <?php
                            $current_page = basename($_SERVER['PHP_SELF']);
                            $page_titles = [
                                'dashboard.php' => ucfirst($user_full_name) . "'s Dashboard", // Capitalize the first letter of username
                                'assessments.php' => 'Assessments',
                                'payments.php' => 'Payments',
                                'profile.php' => 'Student Profile',
                                 // Added settings page title
                            ];
                            echo $page_titles[$current_page] ?? 'Student Panel';
                        ?>
                    </h1>
                </div>

                <div class="relative flex items-center space-x-4 cursor-pointer" id="profileDropdownToggle">
                    <img src="<?php echo $user_profile_image; ?>" alt="<?php echo $user_full_name; ?>" class="h-10 w-10 rounded-full object-cover">
                    <span class="font-semibold text-gray-800 hidden md:block"><?php echo $user_full_name; ?></span>
                    <i class="fas fa-chevron-down text-gray-400"></i>

                    <div id="profileDropdownMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden top-full">
                        <a href="<?php echo BASE_URL; ?>student/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Profile</a>
                        <a href="<?php echo BASE_URL; ?>auth/logout.php" id="dropdownLogoutLink" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-grow p-6">
            <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

            <script>
                // A simple function to initialize Select2 on any element with the 'select2-enabled' class
                function initializeSelect2() {
                    $('.select2-enabled').each(function() {
                        if (!$(this).data('select2')) { // Check if Select2 is already initialized
                            // Use data-placeholder attribute if set, otherwise fallback to first option text, then a default
                            const placeholderText = $(this).attr('data-placeholder') || $(this).find('option:first').text() || "-- Select an option --";
                            $(this).select2({
                                placeholder: placeholderText,
                                allowClear: $(this).data('allow-clear') || false,
                                width: 'resolve' // Ensures Select2 takes the full width of its container
                            });
                        }
                    });
                }

                // Call initializeSelect2 on document ready for initial page load
                $(document).ready(function() {
                    initializeSelect2();

                    // Profile dropdown toggle
                    const profileDropdownToggle = document.getElementById('profileDropdownToggle');
                    const profileDropdownMenu = document.getElementById('profileDropdownMenu');

                    if (profileDropdownToggle && profileDropdownMenu) {
                        profileDropdownToggle.addEventListener('click', function() {
                            profileDropdownMenu.classList.toggle('hidden');
                        });

                        // Close the dropdown if the user clicks outside of it
                        window.addEventListener('click', function(event) {
                            if (!profileDropdownToggle.contains(event.target) && !profileDropdownMenu.contains(event.target)) {
                                profileDropdownMenu.classList.add('hidden');
                            }
                        });
                    }

                    // --- Custom Logout Confirmation Modal Logic ---
                    const logoutModal = document.getElementById('logoutConfirmModal');
                    const cancelLogoutBtn = document.getElementById('cancelLogout');
                    const confirmLogoutBtn = document.getElementById('confirmLogout');
                    let logoutRedirectUrl = ''; // To store the URL to redirect to

                    // Function to show the modal
                    function showLogoutConfirmModal(event) {
                        event.preventDefault(); // Prevent default link behavior
                        logoutRedirectUrl = event.currentTarget.href; // Get the href from the clicked link
                        logoutModal.classList.remove('hidden');
                    }

                    // Function to hide the modal
                    function hideLogoutConfirmModal() {
                        logoutModal.classList.add('hidden');
                        logoutRedirectUrl = ''; // Clear the stored URL
                    }

                    // Attach event listeners to logout links
                    document.getElementById('sidebarLogoutLink').addEventListener('click', showLogoutConfirmModal);
                    document.getElementById('dropdownLogoutLink').addEventListener('click', showLogoutConfirmModal);

                    // Attach event listeners to modal buttons
                    cancelLogoutBtn.addEventListener('click', hideLogoutConfirmModal);
                    confirmLogoutBtn.addEventListener('click', function() {
                        hideLogoutConfirmModal();
                        if (logoutRedirectUrl) {
                            window.location.href = logoutRedirectUrl; // Redirect to logout page
                        }
                    });

                    // Close modal if clicking outside the content area
                    logoutModal.addEventListener('click', function(event) {
                        if (event.target === logoutModal) {
                            hideLogoutConfirmModal();
                        }
                    });

                    // Optional: Close with Escape key
                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape' && !logoutModal.classList.contains('hidden')) {
                            hideLogoutConfirmModal();
                        }
                    });
                    // --- End Custom Logout Confirmation Modal Logic ---

                }); // End of document.ready
            </script>

            <div id="logoutConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center hidden z-50">
                <div class="bg-white rounded-lg shadow-xl p-8 max-w-sm w-full mx-4">
                    <div class="text-center mb-6">
                        <i class="fas fa-sign-out-alt text-red-500 text-4xl mb-4"></i>
                        <h3 class="text-2xl font-semibold text-gray-800">Confirm Logout</h3>
                    </div>
                    <p class="text-gray-700 text-center mb-8">Are you sure you want to log out of your student dashboard?</p>
                    <div class="flex justify-center space-x-4">
                        <button id="cancelLogout" type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                            Cancel
                        </button>
                        <button id="confirmLogout" type="button" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
            