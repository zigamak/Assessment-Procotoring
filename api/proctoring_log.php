<?php
// api/proctoring_log.php

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // For sanitize_input
require_once '../includes/proctoring_functions.php'; // For recordProctoringLog

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
$event_type = sanitize_input($data['event_type'] ?? '');
$log_data = $data['log_data'] ?? null; // Keep as string, it's already JSON from JS

if (!$attempt_id || !$user_id || empty($event_type)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Missing required data.']);
    exit;
}

// Record the log
if (recordProctoringLog($attempt_id, $user_id, $event_type, $log_data)) {
    echo json_encode(['status' => 'success', 'message' => 'Log recorded successfully.']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to record log.']);
}

exit;
?>