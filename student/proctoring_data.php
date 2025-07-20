<?php
// student/proctoring_data.php
require_once '../includes/session.php';
require_once '../includes/db.php'; // Make sure this connects to your database
require_once '../includes/functions.php';

// Ensure this script is only accessible via POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Ensure user is logged in as a student
// You might have a more robust check in session.php or functions.php
if (!isLoggedIn() || getUserRole() !== 'student') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log error if JSON parsing fails
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.', 'error_detail' => json_last_error_msg()]);
    error_log("Proctoring data: Invalid JSON received. Raw input: " . $input);
    exit;
}

// Extract data
$attempt_id = $data['attempt_id'] ?? null;
$user_id = $data['user_id'] ?? null;
$event_type = $data['event_type'] ?? null;
$log_data = $data['log_data'] ?? null;
$image_data = $data['image_data'] ?? null; // For image uploads (base64)
$quiz_id = $data['quiz_id'] ?? null; // Added based on your JS passing it

// Basic validation
if (!$attempt_id || !$user_id || !$event_type) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    error_log("Proctoring data: Missing parameters - attempt_id: $attempt_id, user_id: $user_id, event_type: $event_type");
    exit;
}

try {
    $pdo->beginTransaction();

    if ($event_type === 'image_capture' && $image_data) {
        // Handle image upload
        $upload_dir = 'uploads/proctoring_images/';
        // Create nested directories: uploads/proctoring_images/{quiz_id}/{user_id}/{attempt_id}/
        // This helps manage a large number of images and provides a clear structure.
        $target_dir_relative = $upload_dir . $quiz_id . '/' . $user_id . '/' . $attempt_id . '/';
        $target_dir_absolute = '../' . $target_dir_relative; // Relative to current script location

        if (!is_dir($target_dir_absolute)) {
            mkdir($target_dir_absolute, 0777, true); // Create recursively with write permissions
        }

        $filename = 'proctor_' . uniqid() . '_' . time() . '.png';
        $filepath_absolute = $target_dir_absolute . $filename;
        $filepath_relative_for_db = $target_dir_relative . $filename; // Store this in DB

        // Decode base64 image data (remove 'data:image/png;base64,' prefix if present)
        $image_data = str_replace('data:image/png;base64,', '', $image_data);
        $image_data = base64_decode($image_data);

        if ($image_data === false) {
            throw new Exception("Failed to decode base64 image data.");
        }

        if (file_put_contents($filepath_absolute, $image_data)) {
            // Save image path to database
            $stmt_img = $pdo->prepare("INSERT INTO proctoring_images (attempt_id, user_id, image_path, capture_time, event_type) VALUES (:attempt_id, :user_id, :image_path, NOW(), :event_type)");
            $stmt_img->execute([
                'attempt_id' => $attempt_id,
                'user_id' => $user_id,
                'image_path' => $filepath_relative_for_db,
                'event_type' => $event_type
            ]);
        } else {
            throw new Exception("Failed to save image file to disk.");
        }
    }

    // Always log the event, even if it's an image capture (the log_data might be additional info)
    $stmt_log = $pdo->prepare("INSERT INTO proctoring_logs (attempt_id, user_id, event_type, log_data, timestamp) VALUES (:attempt_id, :user_id, :event_type, :log_data, NOW())");
    $stmt_log->execute([
        'attempt_id' => $attempt_id,
        'user_id' => $user_id,
        'event_type' => $event_type,
        'log_data' => $log_data // This will be the JSON string from JS
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Proctoring data saved successfully.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    error_log("Proctoring data: PDO Error - " . $e->getMessage());
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    error_log("Proctoring data: General Error - " . $e->getMessage());
}
?>