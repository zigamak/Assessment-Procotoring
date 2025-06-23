<?php
// includes/header_public.php
// Public header template for pages accessible to all users (logged out).

// Include necessary files to ensure BASE_URL, session, and functions are available
require_once 'session.php';
require_once 'functions.php';
require_once 'db.php'; // Explicitly include db.php to access BASE_URL

// Define the theme color for consistent styling
$theme_color = "#1e4b31";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Light gray background */
        }
        .main-header {
            background-color: <?php echo $theme_color; ?>;
        }
        .text-theme-color {
            color: <?php echo $theme_color; ?>;
        }
        /* Additional custom styles can go here if not covered by Tailwind */
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <header class="main-header text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="text-2xl font-bold">
                <a href="<?php echo BASE_URL; ?>index.php" class="hover:text-gray-200">Assessment System</a>
            </div>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="<?php echo BASE_URL; ?>index.php" class="hover:text-gray-200 px-3 py-2 rounded-md transition duration-300">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>quiz/public_quiz.php" class="hover:text-gray-200 px-3 py-2 rounded-md transition duration-300">Public Quizzes</a></li>
                    <li><a href="<?php echo BASE_URL; ?>auth/login.php" class="bg-white text-theme-color px-4 py-2 rounded-md font-semibold hover:bg-gray-100 transition duration-300">Login</a></li>
                    <!-- Registration link if public registration is allowed -->
                    <!-- <li><a href="<?php echo BASE_URL; ?>auth/register.php" class="hover:text-gray-200 px-3 py-2 rounded-md transition duration-300">Register</a></li> -->
                </ul>
            </nav>
        </div>
    </header>
    <main class="flex-grow">
