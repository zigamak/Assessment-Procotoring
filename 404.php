<?php
// Set the HTTP response code to 404 Not Found
http_response_code(404);

// Include the session management file
require_once 'includes/session.php'; // Adjust this path if session.php is in a different location

// Start the session if it hasn't been started already (session.php handles this, but good to be explicit)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
$loggedIn = isLoggedIn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles for the Inter font */
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 md:p-12 text-center max-w-md w-full">
        <div class="flex justify-center mb-6">
            <!-- A simple SVG icon for visual appeal -->
            <svg class="w-24 h-24 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <h1 class="text-5xl md:text-6xl font-extrabold text-gray-800 mb-4">404</h1>
        <h2 class="text-2xl md:text-3xl font-semibold text-gray-700 mb-4">Page Not Found</h2>
        <p class="text-gray-600 mb-8 leading-relaxed">
            Oops! The page you're looking for might have been removed, had its name changed, or is temporarily unavailable.
        </p>
        <div class="space-y-4">
            <?php if ($loggedIn): ?>
                <a href="/dashboard.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 shadow-md">
                    Go to Dashboard
                </a>
            <?php else: ?>
                <a href="/" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 shadow-md">
                    Go to Homepage
                </a>
                <a href="/login.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 shadow-md">
                    Login
                </a>
            <?php endif; ?>
        </div>
        <p class="text-gray-500 text-sm mt-8">
            If you believe this is an error, please contact support.
        </p>
    </div>
</body>
</html>
