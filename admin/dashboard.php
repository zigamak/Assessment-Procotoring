<?php
// admin/dashboard.php
// The main dashboard page for administrators.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

// Logic for fetching dashboard data (e.g., number of users, quizzes, recent activity)
$total_users = 0;
$total_quizzes = 0;
$total_attempts = 0;

// Fetch the logged-in admin's username
$logged_in_username = htmlspecialchars($_SESSION['username'] ?? 'Admin'); // Default to 'Admin' if not set

try {
    // Fetch total number of users (excluding admin users)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $total_users = $stmt->fetchColumn();

    // Fetch total number of quizzes
    $stmt = $pdo->query("SELECT COUNT(*) FROM quizzes");
    $total_quizzes = $stmt->fetchColumn();

    // Fetch total number of quiz attempts
    $stmt = $pdo->query("SELECT COUNT(*) FROM quiz_attempts");
    $total_attempts = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Admin Dashboard Data Fetch Error: " . $e->getMessage());
    echo display_message("Could not load dashboard data. Please try again later.", "error");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/heroicons@2.1.1/dist/heroicons.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4 py-8 max-w-7xl">
        <h1 class="text-4xl font-extrabold text-indigo-600 mb-8 flex items-center">
            <svg class="w-8 h-8 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Welcome, <?php echo ucfirst($logged_in_username); ?>!
        </h1>

        <!-- Dashboard Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="bg-gradient-to-r from-indigo-50 to-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 transform hover:scale-105 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-700 mb-2">Total Students</h2>
                        <p class="text-5xl font-bold text-indigo-600"><?php echo htmlspecialchars($total_users); ?></p>
                        <p class="text-sm text-gray-500 mt-1">Registered students</p>
                        <a href="users.php" class="mt-4 inline-block bg-indigo-100 text-indigo-600 px-4 py-2 rounded-full font-semibold hover:bg-indigo-200 transition duration-300">
                            Manage Users →
                        </a>
                    </div>
                    <svg class="w-12 h-12 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="bg-gradient-to-r from-teal-50 to-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 transform hover:scale-105 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-700 mb-2">Total Quizzes</h2>
                        <p class="text-5xl font-bold text-teal-600"><?php echo htmlspecialchars($total_quizzes); ?></p>
                        <p class="text-sm text-gray-500 mt-1">Active assessments</p>
                        <a href="assessments.php" class="mt-4 inline-block bg-teal-100 text-teal-600 px-4 py-2 rounded-full font-semibold hover:bg-teal-200 transition duration-300">
                            Manage Quizzes →
                        </a>
                    </div>
                    <svg class="w-12 h-12 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="bg-gradient-to-r from-rose-50 to-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 transform hover:scale-105 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-700 mb-2">Total Attempts</h2>
                        <p class="text-5xl font-bold text-rose-600"><?php echo htmlspecialchars($total_attempts); ?></p>
                        <p class="text-sm text-gray-500 mt-1">Student attempts</p>
                        <a href="results.php" class="mt-4 inline-block bg-rose-100 text-rose-600 px-4 py-2 rounded-full font-semibold hover:bg-rose-200 transition duration-300">
                            View Results →
                        </a>
                    </div>
                    <svg class="w-12 h-12 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <h2 class="text-2xl font-bold text-indigo-600 mb-6 flex items-center">
            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            Quick Actions
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <a href="users.php" class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 flex flex-col items-center justify-center text-center group transform hover:scale-105">
                <div class="rounded-full p-4 bg-blue-100 text-blue-600 mb-4 group-hover:bg-blue-200 transition-colors duration-300">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <span class="text-xl font-semibold text-gray-800 group-hover:text-indigo-600 transition-colors duration-300">Manage Users</span>
                <p class="text-gray-600 text-sm mt-1">Add, edit, or remove user accounts</p>
            </a>
            <a href="assessments.php" class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 flex flex-col items-center justify-center text-center group transform hover:scale-105">
                <div class="rounded-full p-4 bg-green-100 text-green-600 mb-4 group-hover:bg-green-200 transition-colors duration-300">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <span class="text-xl font-semibold text-gray-800 group-hover:text-green-600 transition-colors duration-300">Manage Quizzes</span>
                <p class="text-gray-600 text-sm mt-1">Create, edit, and configure assessments</p>
            </a>
            <a href="results.php" class="bg-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300 flex flex-col items-center justify-center text-center group transform hover:scale-105">
                <div class="rounded-full p-4 bg-yellow-100 text-yellow-600 mb-4 group-hover:bg-yellow-200 transition-colors duration-300">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <span class="text-xl font-semibold text-gray-800 group-hover:text-yellow-600 transition-colors duration-300">View Results</span>
                <p class="text-gray-600 text-sm mt-1">Review student performance and analytics</p>
            </a>
        </div>

    </div>

    <?php
    // Include the admin specific footer
    require_once '../includes/footer_admin.php';
    ?>
</body>
</html>