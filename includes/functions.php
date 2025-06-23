<?php
// includes/functions.php
// Common utility functions for the assessment system.

/**
 * Sanitize input data to prevent XSS attacks and ensure data integrity.
 * @param mixed $data The data to sanitize.
 * @return mixed The sanitized data.
 */
function sanitize_input($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize_input($value);
        }
    } else {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * Redirects the user to a specified URL.
 * @param string $location The URL to redirect to.
 */
function redirect($location) {
    header("Location: " . $location);
    exit();
}

/**
 * Displays a system message (e.g., success, error, info).
 * This function is a placeholder; you'd typically store messages in sessions
 * and display them on the target page.
 * @param string $message The message content.
 * @param string $type The type of message (e.g., 'success', 'error', 'info').
 */
function display_message($message, $type = 'info') {
    // In a real application, you would store these in $_SESSION
    // and retrieve them on the next page load, then display them
    // using appropriate HTML/CSS (e.g., a modal or dismissible alert).
    echo "<div class='p-3 my-3 rounded-md text-white ";
    switch ($type) {
        case 'success':
            echo "bg-green-500";
            break;
        case 'error':
            echo "bg-red-500";
            break;
        case 'warning':
            echo "bg-yellow-500";
            break;
        case 'info':
        default:
            echo "bg-blue-500";
            break;
    }
    echo "'>" . sanitize_input($message) . "</div>";
}

// Add more general utility functions as needed.
// For example, functions to format dates, validate emails, etc.
