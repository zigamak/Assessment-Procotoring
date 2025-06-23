<?php
// includes/session.php
// Session management and role-based access control logic.

// Start the session if it hasn't been started already.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 * @return bool True if a user session exists, false otherwise.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Gets the ID of the currently logged-in user.
 * @return int|null The user ID if logged in, otherwise null.
 */
function getUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

/**
 * Gets the role of the currently logged-in user.
 * @return string|null The user role (e.g., 'admin', 'student') if logged in, otherwise null.
 */
function getUserRole() {
    return isLoggedIn() ? $_SESSION['user_role'] : null;
}

/**
 * Sets a user's session variables upon successful login.
 * @param int $user_id The ID of the logged-in user.
 * @param string $username The username of the logged-in user.
 * @param string $role The role of the logged-in user ('admin' or 'student').
 */
function loginUser($user_id, $username, $role) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['last_activity'] = time(); // Record last activity time
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR']; // Record IP address
}

/**
 * Destroys the current user session, effectively logging them out.
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session.
    session_destroy();
}

/**
 * Checks if the logged-in user has a specific role.
 * @param string $required_role The role to check against (e.g., 'admin', 'student').
 * @return bool True if the user has the required role, false otherwise.
 */
function checkRole($required_role) {
    return isLoggedIn() && (getUserRole() === $required_role);
}

/**
 * Enforces role-based access control. If the user does not have the required role,
 * they are redirected to a specified unauthorized page (or login page).
 * @param string $required_role The role required to access the current page.
 * @param string $redirect_page The page to redirect to if unauthorized (default: login).
 */
function enforceRole($required_role, $redirect_page = '../auth/login.php') {
    if (!isLoggedIn()) {
        redirect($redirect_page . '?message=not_logged_in');
    } elseif (!checkRole($required_role)) {
        // Log the unauthorized attempt for security
        error_log("Unauthorized access attempt by User ID: " . getUserId() . " (Role: " . getUserRole() . ") to a page requiring role: " . $required_role);
        redirect($redirect_page . '?message=unauthorized_access');
    }
}

/**
 * Simple session timeout logic.
 * Redirects user to login if inactive for a certain period.
 * Call this function at the beginning of secure pages.
 */
function checkSessionTimeout($timeout_seconds = 1800) { // 30 minutes
    if (isLoggedIn()) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_seconds)) {
            logoutUser(); // Log out the user
            redirect('../auth/login.php?message=session_expired'); // Redirect to login with message
        }
        $_SESSION['last_activity'] = time(); // Update last activity time
    }
}
