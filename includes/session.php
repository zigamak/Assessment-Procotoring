<?php
// includes/session.php
// Manages user sessions and authentication status

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is logged in.
 * @return bool True if logged in, false otherwise.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Retrieves the ID of the currently logged-in user.
 * @return int|null The user's ID, or null if not logged in.
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Retrieves the role of the currently logged-in user.
 * Assumes the role is stored in $_SESSION['role'].
 * @return string|null The user's role (e.g., 'admin', 'student'), or null if not logged in.
 */
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Logs a user into the session.
 * @param int $user_id The ID of the user.
 * @param string $username The username of the user.
 * @param string $role The role of the user.
 */
function loginUser($user_id, $username, $role) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    session_regenerate_id(true); // Prevent session fixation
}

/**
 * Destroys the current session and logs the user out.
 */
function logout() {
    $_SESSION = array(); // Clear all session variables
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy(); // Destroy the session
}

?>
