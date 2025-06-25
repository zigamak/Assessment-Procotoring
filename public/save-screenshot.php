<?php
header('Content-Type: application/json');

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the JSON data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['image']) || !isset($input['filename'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$imageData = $input['image'];
$filename = $input['filename'];

// Validate filename to prevent directory traversal
$filename = basename($filename);
if (!preg_match('/^[a-zA-Z0-9_]+_[a-zA-Z0-9_]+_[a-zA-Z0-9]+th[0-9]{4}_[0-9]{1,2}:[0-9]{2}(AM|PM)\.png$/', $filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename format']);
    exit;
}

// Remove the data URL prefix (e.g., "data:image/png;base64,")
$imageData = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);
$imageData = base64_decode($imageData);

if ($imageData === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image data']);
    exit;
}

// Define the directory to save screenshots (ensure it exists and is writable)
$uploadDir = 'screenshots/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Save the image
$filepath = $uploadDir . $filename;
if (file_put_contents($filepath, $imageData)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save image']);
}
?>