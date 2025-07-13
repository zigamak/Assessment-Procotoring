<?php
// includes/header_student.php
// Header template for the Student dashboard, now implementing a sidebar navigation.
// This header should only be included after role enforcement.

require_once 'session.php';
require_once 'functions.php';
require_once 'db.php'; // Ensure BASE_URL is available

// Enforce that only students can access pages including this header
enforceRole('student', BASE_URL . 'auth/login.php'); // Redirect to login if not student

// Get the username from session for display
$logged_in_username = htmlspecialchars($_SESSION['username'] ?? 'Student');

// Define the theme colors for consistent styling for the student portal
$sidebar_bg_color_student = "#0A192F"; // Dark slate gray for the student sidebar
$text_color_light_student = "#ecf0f1"; // Light gray for text on dark background
$hover_color_sidebar_student = "#34495e"; // Slightly lighter slate for hover
$accent_color_student = "#2ecc71"; // A vibrant green for active links/accents (student theme)

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Assessment System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Light gray background */
        }
        /* Custom theme colors for student portal */
        .bg-sidebar-student {
            background-color: <?php echo $sidebar_bg_color_student; ?>;
        }
        .text-sidebar-light-student {
            color: <?php echo $text_color_light_student; ?>;
        }
        .hover\:bg-sidebar-hover-student:hover {
            background-color: <?php echo $hover_color_sidebar_student; ?>;
        }
        .bg-accent-student {
            background-color: <?php echo $accent_color_student; ?>;
        }
        .text-accent-student {
            color: <?php echo $accent_color_student; ?>;
        }

        /* Custom scrollbar for sidebar if needed */
        .sidebar::-webkit-scrollbar {
            width: 8px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: <?php echo $sidebar_bg_color_student; ?>; /* Match sidebar background */
            border-radius: 10px;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: <?php echo $hover_color_sidebar_student; ?>; /* Match hover color for thumb */
            border-radius: 10px;
        }
    </style>
</head>
<body class="flex min-h-screen">
    <aside class="w-64 bg-sidebar-student text-sidebar-light-student shadow-lg flex flex-col fixed inset-y-0 left-0 z-40">
        <div class="p-6 text-3xl font-bold border-b border-gray-700">
            <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="hover:text-accent-student">Student Portal</a>
        </div>
        <nav class="flex-grow p-4 sidebar">
            <ul class="space-y-2">
                <li>
                    <a href="<?php echo BASE_URL; ?>student/dashboard.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover-student transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'bg-accent-student text-white' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m0 0l-7 7m7-7v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>student/view_history.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover-student transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'view_history.php') ? 'bg-accent-student text-white' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 2v-6m2 9H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        View History
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>student/profile.php"
                       class="flex items-center p-3 rounded-md hover:bg-sidebar-hover-student transition duration-300
                       <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'bg-accent-student text-white' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Profile
                    </a>
                </li>
            </ul>
        </nav>
        <div class="p-4 border-t border-gray-700 text-sm">
            <div class="mb-2 text-sidebar-light-student">Welcome, <?php echo $logged_in_username; ?></div>
            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="block bg-red-600 text-white text-center py-2 rounded-md font-semibold hover:bg-red-700 transition duration-300">
                Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col ml-64"> <header class="bg-white p-4 shadow-md sticky top-0 z-30">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-xl font-semibold text-gray-800">
                    <?php
                        // Dynamically display page title based on current file
                        $current_page = basename($_SERVER['PHP_SELF']);
                        $page_titles = [
                            'dashboard.php' => 'Dashboard Overview',
                            'take_quiz.php' => 'Take an Assessment', // Renamed for consistency
                            'view_history.php' => 'Your Assessment History', // Renamed for consistency
                            'profile.php' => 'Your Profile', // New title for the profile page
                        ];
                        echo $page_titles[$current_page] ?? 'Student Portal';
                    ?>
                </h1>
                </div>
        </header>
        <main class="flex-grow p-4">