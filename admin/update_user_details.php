<?php
// admin/update_user_details.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the user is logged in and is an admin (optional, but highly recommended)
    if (!isLoggedIn() || getUserRole() !== 'admin') {
        $response['message'] = 'Unauthorized access.';
        echo json_encode($response);
        exit;
    }

    $user_id = sanitize_input($_POST['user_id'] ?? 0);
    $field = sanitize_input($_POST['field'] ?? '');
    $value = sanitize_input($_POST['value'] ?? '');

    if (empty($user_id) || empty($field)) {
        $response['message'] = 'Missing user ID or field to update.';
        echo json_encode($response);
        exit;
    }

    // List of allowed fields to update for security
    $allowed_fields = [
        'username', 'email', 'first_name', 'last_name', 'city', 'state',
        'country', 'date_of_birth', 'grade', 'address', 'gender', 'school_name'
    ];

    if (!in_array($field, $allowed_fields)) {
        $response['message'] = 'Invalid field for update.';
        echo json_encode($response);
        exit;
    }

    // Basic validation for specific fields
    if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit;
    }

    try {
        // Check for uniqueness for username and email
        if ($field === 'username' || $field === 'email') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$field} = :value AND user_id != :user_id");
            $stmt->execute(['value' => $value, 'user_id' => $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $response['message'] = ucfirst($field) . ' already exists for another user.';
                echo json_encode($response);
                exit;
            }
        }

        $sql = "UPDATE users SET {$field} = :value WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(['value' => $value, 'user_id' => $user_id])) {
            $response['success'] = true;
            $response['message'] = 'User ' . $field . ' updated successfully.';
        } else {
            $response['message'] = 'Failed to update user ' . $field . '.';
        }
    } catch (PDOException $e) {
        error_log("Update User Details Error: " . $e->getMessage());
        $response['message'] = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);