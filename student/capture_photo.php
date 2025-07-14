<?php
// student/capture_photo.php
// Receives Base64 encoded images from client-side proctoring and saves them.

require_once '../includes/session.php'; // For session_start() and potentially user ID check
require_once '../includes/db.php';     // For $pdo database connection
require_once '../includes/functions.php'; // For sanitize_input() and logging functions if any

header('Content-Type: application/json'); // Respond with JSON

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit();
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate incoming data
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON data received.';
    echo json_encode($response);
    exit();
}

$quiz_id = isset($data['quiz_id']) ? filter_var($data['quiz_id'], FILTER_VALIDATE_INT) : null;
$attempt_id = isset($data['attempt_id']) ? filter_var($data['attempt_id'], FILTER_VALIDATE_INT) : null;
$user_id = isset($data['user_id']) ? filter_var($data['user_id'], FILTER_VALIDATE_INT) : null;
$image_data = isset($data['image_data']) ? $data['image_data'] : '';

// Basic validation
if (!$quiz_id || !$attempt_id || !$user_id || empty($image_data)) {
    $response['message'] = 'Missing or invalid required parameters.';
    echo json_encode($response);
    exit();
}

// --- Security Check: Verify user ID against session (crucial!) ---
// This prevents one user from uploading images for another user's attempt.
if (getUserId() !== $user_id) {
    $response['message'] = 'Unauthorized photo upload attempt.';
    error_log("Security Alert: User ID mismatch for photo upload. Session User: " . getUserId() . ", Provided User: " . $user_id);
    echo json_encode($response);
    exit();
}

// --- Validate attempt_id and quiz_id actually belong to the user ---
// This is an extra layer of security to ensure the attempt_id is valid for the user and quiz.
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE attempt_id = :attempt_id AND user_id = :user_id AND quiz_id = :quiz_id");
    $stmt->execute([
        'attempt_id' => $attempt_id,
        'user_id' => $user_id,
        'quiz_id' => $quiz_id
    ]);
    if ($stmt->fetchColumn() == 0) {
        $response['message'] = 'Invalid attempt_id, user_id, or quiz_id combination.';
        error_log("Security Alert: Invalid attempt/user/quiz combo for photo upload. Attempt: $attempt_id, User: $user_id, Quiz: $quiz_id");
        echo json_encode($response);
        exit();
    }
} catch (PDOException $e) {
    error_log("DB error during photo upload validation: " . $e->getMessage());
    $response['message'] = 'Database error during validation.';
    echo json_encode($response);
    exit();
}


// Remove the "data:image/png;base64," prefix
$image_data = str_replace('data:image/png;base64,', '', $image_data);
$image_data = base64_decode($image_data);

if ($image_data === false) {
    $response['message'] = 'Invalid Base64 image data.';
    echo json_encode($response);
    exit();
}

// Define upload directory structure: uploads/proctoring_images/user_ID/quiz_ID/attempt_ID/
$upload_base_dir = '../uploads/proctoring_images/';
$user_dir = $upload_base_dir . $user_id . '/';
$quiz_dir = $user_dir . $quiz_id . '/';
$attempt_dir = $quiz_dir . $attempt_id . '/';

// Create directories if they don't exist
if (!is_dir($attempt_dir)) {
    if (!mkdir($attempt_dir, 0755, true)) { // 0755 permissions, recursive
        $response['message'] = 'Failed to create upload directory.';
        error_log("Failed to create directory: " . $attempt_dir);
        echo json_encode($response);
        exit();
    }
}

// Generate a unique filename using timestamp and a random component
$filename = uniqid('proctor_') . '_' . time() . '.png';
$file_path = $attempt_dir . $filename;
$relative_file_path = 'uploads/proctoring_images/' . $user_id . '/' . $quiz_id . '/' . $attempt_id . '/' . $filename;


// Save the image file
if (file_put_contents($file_path, $image_data) === false) {
    $response['message'] = 'Failed to save image file.';
    error_log("Failed to write image file: " . $file_path);
    echo json_encode($response);
    exit();
}

// Insert image path into the database
try {
    $stmt = $pdo->prepare("INSERT INTO proctoring_images (attempt_id, user_id, quiz_id, image_path, capture_time) VALUES (:attempt_id, :user_id, :quiz_id, :image_path, NOW())");
    $stmt->execute([
        'attempt_id' => $attempt_id,
        'user_id' => $user_id,
        'quiz_id' => $quiz_id,
        'image_path' => $relative_file_path
    ]);

    $response['success'] = true;
    $response['message'] = 'Photo captured and saved successfully.';
    $response['image_path'] = $relative_file_path; // Return the path for logging/debugging
} catch (PDOException $e) {
    // If DB insert fails, try to delete the file to avoid orphaned files
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    $response['message'] = 'Failed to record image in database: ' . $e->getMessage();
    error_log("DB insert error for proctoring image: " . $e->getMessage());
}

echo json_encode($response);
?>