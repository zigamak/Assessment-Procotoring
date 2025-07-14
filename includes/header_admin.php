<?php
// includes/header_admin.php
// Header template for the Admin dashboard with sidebar navigation.

require_once 'session.php';
require_once 'functions.php';
require_once 'db.php'; // Ensure BASE_URL is available

// Enforce that only admins can access pages including this header
enforceRole('admin', BASE_URL . 'auth/login.php'); // Redirect to login if not admin

// Get the username from session for display
$logged_in_username = htmlspecialchars($_SESSION['username'] ?? 'Admin');

// Define the theme colors for consistent styling
$sidebar_bg_color = "#1a202c"; // Darker shade for admin sidebar
$text_color_light = "#e2e8f0"; // Light gray for text on dark background
$hover_color_sidebar = "#2d3748"; // Slightly lighter charcoal for hover
$accent_color = "#e53e3e"; // A nice red for active links/accents (your primary interactive color)
$header_bg_color = "#ffffff"; // White for the main header
$body_bg_color = "#f7fafc"; // Light gray background for the main content area

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Assessment System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

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
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="hover:opacity-80 transition duration-300">
                <img src="https://mackennytutors.com/wp-content/uploads/2025/05/Mackenny.png" alt="Mackenny Tutors Logo" class="h-10"> </a>
        </div>
        <nav class="flex-grow p-4 overflow-y-auto sidebar">
            <ul class="space-y-2">
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/dashboard.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-tachometer-alt mr-3"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/assessments.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'assessments.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-file-alt mr-3"></i> Assessments
                    </a>
                </li>
                
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/users.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-users mr-3"></i> Users
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/results.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'results.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-poll-h mr-3"></i> Results
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/payments.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-money-bill-wave mr-3"></i> Payments
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/settings.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'bg-accent text-white' : ''; ?>">
                        <i class="fas fa-fw fa-cog mr-3"></i> Settings
                    </a>
                </li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-700 text-sm">
            <div class="mb-2 text-sidebar-light">Welcome, <span class="font-semibold"><?php echo $logged_in_username; ?></span></div>
            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="block bg-red-600 text-white text-center py-2 rounded-md font-semibold hover:bg-red-700 transition duration-300">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col ml-64">
        <header class="bg-header p-4 shadow-md sticky top-0 z-30 border-b border-gray-200">
            <div class="container mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <button onclick="history.back()" class="bg-blue-50 text-blue-700 px-4 py-2 rounded-md hover:bg-blue-100 transition duration-300 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </button>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <?php
                            $current_page = basename($_SERVER['PHP_SELF']);
                            $page_titles = [
                                'dashboard.php' => 'Admin Dashboard Overview',
                                'assessments.php' => 'Manage Assessments',
                                'questions.php' => 'Manage Questions',
                                'users.php' => 'Manage Users',
                                'results.php' => 'View Results',
                                'payments.php' => 'View Payments',
                                'settings.php' => 'System Settings',
                            ];
                            echo $page_titles[$current_page] ?? 'Admin Panel';
                        ?>
                    </h1>
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
                            $(this).attr('data-placeholder', $(this).find('option:first').text()); // Set placeholder from first option
                            $(this).select2({
                                placeholder: $(this).attr('data-placeholder') || "-- Select an option --",
                                allowClear: $(this).data('allow-clear') || false,
                                width: 'resolve' // Ensures Select2 takes the full width of its container
                            });
                        }
                    });
                }

                // Call initializeSelect2 on document ready for initial page load
                $(document).ready(function() {
                    initializeSelect2();
                });
            </script>
           