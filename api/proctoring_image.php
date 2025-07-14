<?php
// api/proctoring_image.php

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // For sanitize_input
require_once '../includes/proctoring_functions.php'; // For recordProctoringImage

header('Content-Type: application/json');

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate and sanitize input
$attempt_id = filter_var($data['attempt_id'] ?? null, FILTER_VALIDATE_INT);
$user_id = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT);
$quiz_id = filter_var($data['quiz_id'] ?? null, FILTER_VALIDATE_INT);
$imageData = $data['image_data'] ?? ''; // Base64 encoded image

if (!$attempt_id || !$user_id || !$quiz_id || empty($imageData)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Missing required data.']);
    exit;
}

// Decode base64 image data
// Data URI format: data:image/jpeg;base64, 실제데이터
list($type, $imageData) = explode(';', $imageData);
list(, $imageData) = explode(',', $imageData);
$imageData = base64_decode($imageData);

if ($imageData === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid image data.']);
    exit;
}

// Define storage directory and file name
$uploadDir = '../proctor_images/'; // Create this directory and ensure it's writable!
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true); // Create directory if it doesn't exist
}

$fileName = 'proctor_' . $attempt_id . '_' . $user_id . '_' . time() . '.jpg';
$filePath = $uploadDir . $fileName;

// Save the image file
if (file_put_contents($filePath, $imageData)) {
    // Record image path in the database
    if (recordProctoringImage($attempt_id, $user_id, $quiz_id, $filePath)) {
        echo json_encode(['status' => 'success', 'message' => 'Image recorded successfully.']);
    } else {
        // If DB insertion fails, delete the saved image
        unlink($filePath);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to record image path in database.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save image file.']);
}

exit;
?>