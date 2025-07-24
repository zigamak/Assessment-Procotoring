<?php
// admin/get_paid_assessments.php
// Fetches paid assessments for a user

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$user_id = sanitize_input($_GET['user_id'] ?? 0);

if (empty($user_id)) {
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT q.title, p.payment_date
        FROM payments p
        JOIN quizzes q ON p.quiz_id = q.quiz_id
        WHERE p.user_id = :user_id AND p.status = 'completed'
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($assessments);
} catch (PDOException $e) {
    error_log("Get Paid Assessments Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>