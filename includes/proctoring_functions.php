<?php
// includes/proctoring_functions.php

/**
 * Inserts a proctoring log entry into the database.
 *
 * @param int $attempt_id The ID of the quiz attempt.
 * @param int $user_id The ID of the user.
 * @param string $event_type The type of event (e.g., 'focus_lost', 'image_capture').
 * @param string|null $log_data Additional data for the log entry.
 * @return bool True on success, false on failure.
 */
function recordProctoringLog($attempt_id, $user_id, $event_type, $log_data = null) {
    global $pdo; // Assuming $pdo is available globally from db.php

    try {
        $stmt = $pdo->prepare("INSERT INTO proctoring_logs (attempt_id, user_id, event_type, log_data) VALUES (:attempt_id, :user_id, :event_type, :log_data)");
        return $stmt->execute([
            'attempt_id' => $attempt_id,
            'user_id' => $user_id,
            'event_type' => $event_type,
            'log_data' => $log_data
        ]);
    } catch (PDOException $e) {
        error_log("Proctoring Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Inserts a proctoring image path into the database.
 *
 * @param int $attempt_id The ID of the quiz attempt.
 * @param int $user_id The ID of the user.
 * @param int $quiz_id The ID of the quiz.
 * @param string $image_path The file path to the captured image.
 * @return bool True on success, false on failure.
 */
function recordProctoringImage($attempt_id, $user_id, $quiz_id, $image_path) {
    global $pdo; // Assuming $pdo is available globally from db.php

    try {
        $stmt = $pdo->prepare("INSERT INTO proctoring_images (attempt_id, user_id, quiz_id, image_path) VALUES (:attempt_id, :user_id, :quiz_id, :image_path)");
        return $stmt->execute([
            'attempt_id' => $attempt_id,
            'user_id' => $user_id,
            'quiz_id' => $quiz_id,
            'image_path' => $image_path
        ]);
    } catch (PDOException $e) {
        error_log("Proctoring Image Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches proctoring logs for a given attempt ID.
 *
 * @param int $attempt_id The ID of the quiz attempt.
 * @return array An array of log entries.
 */
function getProctoringLogsByAttempt($attempt_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM proctoring_logs WHERE attempt_id = :attempt_id ORDER BY timestamp ASC");
        $stmt->execute(['attempt_id' => $attempt_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Proctoring Logs Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches proctoring images for a given attempt ID.
 *
 * @param int $attempt_id The ID of the quiz attempt.
 * @return array An array of image entries.
 */
function getProctoringImagesByAttempt($attempt_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM proctoring_images WHERE attempt_id = :attempt_id ORDER BY capture_time ASC");
        $stmt->execute(['attempt_id' => $attempt_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Proctoring Images Error: " . $e->getMessage());
        return [];
    }
}

?>