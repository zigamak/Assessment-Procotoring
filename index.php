<?php
// This is the main landing page of the assessment system.
// It will include the public header and footer.

// Include necessary files
require_once 'includes/functions.php'; // For any general utility functions
require_once 'includes/session.php';   // For session management

// Determine which header and footer to include based on user's login status or role
// For the index.php, we'll generally use the public header/footer unless a user is logged in
// This logic can be expanded later to redirect logged-in users to their respective dashboards

// Example: Check if a user is logged in and redirect to their dashboard
// if (isLoggedIn()) {
//     if ($_SESSION['user_role'] === 'admin') {
//         header('Location: admin/dashboard.php');
//         exit();
//     } elseif ($_SESSION['user_role'] === 'student') {
//         header('Location: student/dashboard.php');
//         exit();
//     }
// }

// Include the public header
require_once 'includes/header_public.php';
?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-4xl font-bold text-center mb-8" style="color: #1e4b31;">Welcome to the Assessment Proctoring System</h1>

    <p class="text-center text-lg mb-6">
        This system provides a robust platform for managing and taking online assessments.
        Whether you're an administrator managing quizzes or a student taking an exam,
        we aim to provide a seamless and secure experience.
    </p>

    <div class="flex flex-col md:flex-row justify-center items-center md:space-x-8 space-y-4 md:space-y-0">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full md:w-1/3 text-center">
            <h2 class="text-2xl font-semibold mb-4" style="color: #1e4b31;">Admin Access</h2>
            <p class="text-gray-700 mb-4">
                Administrators can manage users, create and assign quizzes, and view comprehensive results.
            </p>
            <a href="auth/login.php" class="inline-block bg-green-700 text-white px-6 py-3 rounded-md hover:bg-green-800 transition duration-300">
                Admin Login
            </a>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-lg w-full md:w-1/3 text-center">
            <h2 class="text-2xl font-semibold mb-4" style="color: #1e4b31;">Student Access</h2>
            <p class="text-gray-700 mb-4">
                Students can log in to take assigned quizzes and view their past assessment history.
            </p>
            <a href="auth/login.php" class="inline-block bg-green-700 text-white px-6 py-3 rounded-md hover:bg-green-800 transition duration-300">
                Student Login
            </a>
        </div>
    </div>

    <div class="text-center mt-12">
        <h2 class="text-3xl font-semibold mb-4" style="color: #1e4b31;">Public Quizzes</h2>
        <p class="text-gray-700 mb-6">
            Explore some of our public quizzes that can be accessed without an account.
        </p>
        <a href="quiz/public_quiz.php" class="inline-block bg-blue-600 text-white px-8 py-4 rounded-md text-xl hover:bg-blue-700 transition duration-300">
            Take a Public Quiz
        </a>
    </div>
</div>

<?php
// Include the public footer
require_once 'includes/footer_public.php';
?>
