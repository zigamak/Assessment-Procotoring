<?php
// admin/dashboard.php
// The main dashboard page for administrators.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the admin specific header. This also handles role enforcement.
require_once '../includes/header_admin.php';

// Logic for fetching dashboard data (e.g., number of users, quizzes, recent activity)
// This is placeholder logic and would be expanded with actual database queries.
$total_users = 0;
$total_quizzes = 0;
$total_attempts = 0;

try {
    // Fetch total number of users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();

    // Fetch total number of quizzes
    $stmt = $pdo->query("SELECT COUNT(*) FROM quizzes");
    $total_quizzes = $stmt->fetchColumn();

    // Fetch total number of quiz attempts
    $stmt = $pdo->query("SELECT COUNT(*) FROM quiz_attempts");
    $total_attempts = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Admin Dashboard Data Fetch Error: " . $e->getMessage());
    display_message("Could not load dashboard data. Please try again later.", "error");
}

?>

<div class="container mx-auto p-4 py-8">
   <h1 class="text-3xl font-bold text-accent mb-6">Welcome, <?php echo ucfirst($logged_in_username); ?>!</h1>
    

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Dashboard Card 1: Total Users -->
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Total Users</h2>
                <p class="text-3xl font-bold text-theme-color mt-2"><?php echo htmlspecialchars($total_users); ?></p>
            </div>
            <div class="text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H2v-2a3 3 0 015.356-1.857M9 20v-2m3-2v2m1.048-9.293a1.5 1.5 0 00-2.095 2.095L14.4 17.657a1.5 1.5 0 002.095-2.095L13.048 9.707zm-7.66 0a1.5 1.5 0 00-2.095 2.095L7.4 17.657a1.5 1.5 0 002.095-2.095L5.348 9.707z" />
                </svg>
            </div>
        </div>

        <!-- Dashboard Card 2: Total Quizzes -->
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Total Quizzes</h2>
                <p class="text-3xl font-bold text-theme-color mt-2"><?php echo htmlspecialchars($total_quizzes); ?></p>
            </div>
            <div class="text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
            </div>
        </div>

        <!-- Dashboard Card 3: Total Quiz Attempts -->
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Total Attempts</h2>
                <p class="text-3xl font-bold text-theme-color mt-2"><?php echo htmlspecialchars($total_attempts); ?></p>
            </div>
            <div class="text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.592 1M12 8V4m0 8v4m-6 3H6m-6 0h.01M17 12h2.01M17 12V6M3 6h.01" />
                </svg>
            </div>
        </div>
    </div>

    <h2 class="text-2xl font-bold text-theme-color mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="users.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 flex flex-col items-center justify-center text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-theme-color mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M10 20v-2a3 3 0 013-3h4a3 3 0 013 3v2M3 8a4 4 0 100 8.002M9 16h6" />
            </svg>
            <span class="text-xl font-semibold text-gray-800">Manage Users</span>
            <p class="text-gray-600 text-sm mt-1">Add, edit, or remove user accounts.</p>
        </a>

        <a href="manage_quizzes.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 flex flex-col items-center justify-center text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-theme-color mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span class="text-xl font-semibold text-gray-800">Manage Quizzes</span>
            <p class="text-gray-600 text-sm mt-1">Create, edit, and configure assessments.</p>
        </a>

        <a href="view_results.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 flex flex-col items-center justify-center text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-theme-color mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 2v-6m2 9H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="text-xl font-semibold text-gray-800">View Results</span>
            <p class="text-gray-600 text-sm mt-1">Review student performance and quiz analytics.</p>
        </a>
    </div>

    <!-- You can add more sections here, e.g., recent activity, system health, etc. -->

</div>

<?php
// Include the admin specific footer
require_once '../includes/footer_admin.php';
?>
