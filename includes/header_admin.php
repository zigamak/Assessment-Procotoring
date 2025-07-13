<?php
// includes/header_admin.php
// Header template for the Admin dashboard with sidebar navigation and back button.
// This header should only be included after role enforcement.

require_once 'session.php';
require_once 'functions.php';
require_once 'db.php'; // Ensure BASE_URL is available

// Enforce that only admins can access pages including this header
enforceRole('admin', BASE_URL . 'auth/login.php'); // Redirect to login if not admin

// Get the username from session for display
$logged_in_username = htmlspecialchars($_SESSION['username'] ?? 'Admin');

// Define the theme colors for consistent styling
// Changed theme_color to a dark shade for sidebar, header remains white via direct class
$sidebar_bg_color = "#0A192F"; // Dark charcoal for the sidebar background
$text_color_light = "#e2e8f0"; // Light gray for text on dark background
$hover_color_sidebar = "#2d3748"; // Slightly lighter charcoal for hover
$accent_color = "#4299e1"; // A nice blue for active links/accents

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Assessment System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #eff6ff; /* Light blue-gray background for a softer look */
        }
        /* Custom theme colors */
        .bg-sidebar {
            background-color: <?php echo $sidebar_bg_color; ?>;
        }
        .text-sidebar-light {
            color: <?php echo $text_color_light; ?>;
        }
        .hover\:bg-sidebar-hover:hover {
            background-color: <?php echo $hover_color_sidebar; ?>;
        }
        .bg-accent {
            background-color: <?php echo $accent_color; ?>;
        }
        .text-accent {
            color: <?php echo $accent_color; ?>;
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
        <div class="p-6 text-3xl font-bold border-b border-gray-700"> <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="hover:text-accent">Admin Panel</a>
        </div>
        <nav class="flex-grow p-4 overflow-y-auto sidebar">
            <ul class="space-y-2">
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/dashboard.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'bg-accent text-white' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m0 0l-7 7m7-7v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/manage_users.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php') ? 'bg-accent text-white' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M10 20v-2a3 3 0 013-3h4a3 3 0 013 3v2M3 8a4 4 0 100 8.002M9 16h6" />
                        </svg>
                        Manage Users
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/manage_quizzes.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_quizzes.php') ? 'bg-accent text-white' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Manage Assessments
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/view_results.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'view_results.php') ? 'bg-accent text-white' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 2v-6m2 9H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        View Results
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>admin/profile.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'bg-accent text-white' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Profile
                    </a>
                </li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-700 text-sm"> <div class="mb-2 text-sidebar-light">Welcome, <?php echo $logged_in_username; ?></div>
            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="block bg-red-600 text-white text-center py-2 rounded-md font-semibold hover:bg-red-700 transition duration-300">
                Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col ml-64">
        <header class="bg-white p-4 shadow-md sticky top-0 z-30">
            <div class="container mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <button onclick="history.back()" class="bg-blue-100 text-blue-700 px-4 py-2 rounded-md hover:bg-blue-200 transition duration-300 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>
                    <h1 class="text-xl font-semibold text-gray-800">
                        <?php
                            // Dynamically display page title based on current file
                            $current_page = basename($_SERVER['PHP_SELF']);
                            $page_titles = [
                                'dashboard.php' => 'Dashboard Overview',
                                'manage_users.php' => 'Manage User Accounts',
                                'manage_quizzes.php' => 'Manage Quizzes & Questions',
                                'view_results.php' => 'View Assessment Results',
                                'profile.php' => 'Admin Profile', // Added for the new profile page
                            ];
                            echo $page_titles[$current_page] ?? 'Admin Panel';
                        ?>
                    </h1>
                </div>
                </div>
        </header>
        <main class="flex-grow p-4">
