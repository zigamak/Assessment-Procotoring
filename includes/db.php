<?php
// includes/db.php
// Database connection configuration and general application settings.

// Define database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'assessment_db'); // You might want to change this to your actual database name
define('DB_USER', 'root');         // Your database username
define('DB_PASS', '');             // Your database password (leave empty if no password)
define('DB_CHARSET', 'utf8mb4');

// Define Base URL for the application
// This helps with consistent URL generation, especially for redirects or assets.
// Example: http://localhost/assessment_system/ or https://yourdomain.com/
define('BASE_URL', 'http://localhost/assessment/');
define('SITE_NAME', 'QuizMaster'); // Your application's name for emails


// --- SMTP Email Settings (for Forgot Password, etc.) ---
define('SMTP_HOST', 'eventio.africa');     // e.g., 'smtp.gmail.com' or 'smtp.mailgun.org'
define('SMTP_USERNAME', 'mackenny@eventio.africa'); // e.g., your email address for sending
define('SMTP_PASSWORD', '*3;jW[12A$NS'); // e.g., your email password or app-specific password
define('SMTP_PORT', 465);                      // Common ports: 465 for SMTPS, 587 for STARTTLS
define('SMTP_FROM_EMAIL', 'mackenny@eventio.africa'); // Email address messages will be sent FROM
define('SMTP_FROM_NAME', 'Mackenny Assessment');             // Name displayed as sender


// Data Source Name string
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// Options for PDO connection
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Disable emulation for better performance and security
];

// Establish database connection
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // You can uncomment the line below for debugging, but remove it in production
    // echo "Database connected successfully!";
} catch (PDOException $e) {
    // Log the error message (e.g., to a file, not directly to the browser in production)
    error_log("Database Connection Error: " . $e->getMessage());
    // Display a user-friendly error message
    die("<h1>Database connection failed. Please try again later.</h1>");
}
