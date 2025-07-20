<?php
// admin/process_assessment.php
// Handles form submissions for adding, editing, and deleting assessments.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Make sure this includes sanitize_input, isLoggedIn, etc.

// Ensure only admins can access this script directly via POST
if (!isLoggedIn() || getUserRole() !== 'admin') {
    redirect('../auth/login.php'); // Or return an error JSON
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'add_assessment':
                $title = sanitize_input($_POST['title'] ?? '');
                $description = sanitize_input($_POST['description'] ?? '');
                $max_attempts = sanitize_input($_POST['max_attempts'] ?? 0);
                $duration_minutes = !empty($_POST['duration_minutes']) ? sanitize_input($_POST['duration_minutes']) : NULL;
                $created_by = getUserId();
                $is_paid = isset($_POST['is_paid']) ? 1 : 0;
                $assessment_fee = ($is_paid && !empty($_POST['assessment_fee'])) ? sanitize_input($_POST['assessment_fee']) : NULL;
                $open_datetime_str = sanitize_input($_POST['open_datetime'] ?? '');
                $open_datetime = !empty($open_datetime_str) ? $open_datetime_str : NULL;

                // Validation
                if (empty($title)) {
                    $_SESSION['form_message'] = "Assessment title is required.";
                    $_SESSION['form_message_type'] = 'error';
                    break;
                }
                if ($is_paid && ($assessment_fee === NULL || !is_numeric($assessment_fee) || $assessment_fee < 0)) {
                    $_SESSION['form_message'] = "Assessment fee is required and must be a valid positive number if the assessment is paid.";
                    $_SESSION['form_message_type'] = 'error';
                    break;
                }
                // Optional: Validate date/time format if needed beyond basic sanitization
                // if ($open_datetime !== NULL && !DateTime::createFromFormat('Y-m-d\TH:i', $open_datetime)) {
                //     $_SESSION['form_message'] = "Invalid Open Date/Time format.";
                //     $_SESSION['form_message_type'] = 'error';
                //     break;
                // }


                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO quizzes (title, description, max_attempts, duration_minutes, is_paid, assessment_fee, open_datetime, created_by)
                        VALUES (:title, :description, :max_attempts, :duration_minutes, :is_paid, :assessment_fee, :open_datetime, :created_by)
                    ");
                    $stmt->execute([
                        'title' => $title,
                        'description' => $description,
                        'max_attempts' => $max_attempts,
                        'duration_minutes' => $duration_minutes,
                        'is_paid' => $is_paid,
                        'assessment_fee' => $assessment_fee,
                        'open_datetime' => $open_datetime,
                        'created_by' => $created_by
                    ]);
                    $_SESSION['form_message'] = "Assessment added successfully!";
                    $_SESSION['form_message_type'] = 'success';
                } catch (PDOException $e) {
                    error_log("Add Assessment Error: " . $e->getMessage());
                    $_SESSION['form_message'] = "Database error while adding assessment.";
                    $_SESSION['form_message_type'] = 'error';
                }
                break;

            case 'edit_assessment':
                $quiz_id = sanitize_input($_POST['quiz_id'] ?? 0);
                $title = sanitize_input($_POST['title'] ?? '');
                $description = sanitize_input($_POST['description'] ?? '');
                $max_attempts = sanitize_input($_POST['max_attempts'] ?? 0);
                $duration_minutes = !empty($_POST['duration_minutes']) ? sanitize_input($_POST['duration_minutes']) : NULL;
                $is_paid = isset($_POST['is_paid']) ? 1 : 0;
                $assessment_fee = ($is_paid && !empty($_POST['assessment_fee'])) ? sanitize_input($_POST['assessment_fee']) : NULL;
                $open_datetime_str = sanitize_input($_POST['open_datetime'] ?? '');
                $open_datetime = !empty($open_datetime_str) ? $open_datetime_str : NULL;

                // Validation
                if (empty($quiz_id) || empty($title)) {
                    $_SESSION['form_message'] = "Assessment ID and title are required to edit.";
                    $_SESSION['form_message_type'] = 'error';
                    break;
                }
                if ($is_paid && ($assessment_fee === NULL || !is_numeric($assessment_fee) || $assessment_fee < 0)) {
                    $_SESSION['form_message'] = "Assessment fee is required and must be a valid positive number if the assessment is paid.";
                    $_SESSION['form_message_type'] = 'error';
                    break;
                }
                // Optional: Validate date/time format if needed
                // if ($open_datetime !== NULL && !DateTime::createFromFormat('Y-m-d\TH:i', $open_datetime)) {
                //     $_SESSION['form_message'] = "Invalid Open Date/Time format.";
                //     $_SESSION['form_message_type'] = 'error';
                //     break;
                // }


                try {
                    $stmt = $pdo->prepare("
                        UPDATE quizzes
                        SET title = :title, description = :description, max_attempts = :max_attempts,
                            duration_minutes = :duration_minutes, is_paid = :is_paid, assessment_fee = :assessment_fee,
                            open_datetime = :open_datetime
                        WHERE quiz_id = :quiz_id
                    ");
                    $stmt->execute([
                        'title' => $title,
                        'description' => $description,
                        'max_attempts' => $max_attempts,
                        'duration_minutes' => $duration_minutes,
                        'is_paid' => $is_paid,
                        'assessment_fee' => $assessment_fee,
                        'open_datetime' => $open_datetime,
                        'quiz_id' => $quiz_id
                    ]);
                    $_SESSION['form_message'] = "Assessment updated successfully!";
                    $_SESSION['form_message_type'] = 'success';
                } catch (PDOException $e) {
                    error_log("Edit Assessment Error: " . $e->getMessage());
                    $_SESSION['form_message'] = "Database error while updating assessment.";
                    $_SESSION['form_message_type'] = 'error';
                }
                break;

            case 'delete_assessment':
                $quiz_id = sanitize_input($_POST['quiz_id'] ?? 0);
                if (empty($quiz_id)) {
                    $_SESSION['form_message'] = "Assessment ID is required to delete.";
                    $_SESSION['form_message_type'] = 'error';
                } else {
                    try {
                        // Consider deleting related questions/attempts first if not using CASCADE DELETE
                        // $stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = :quiz_id");
                        // $stmt->execute(['quiz_id' => $quiz_id]);

                        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = :quiz_id");
                        $stmt->execute(['quiz_id' => $quiz_id]);
                        $_SESSION['form_message'] = "Assessment and all its questions deleted successfully!";
                        $_SESSION['form_message_type'] = 'success';
                    } catch (PDOException $e) {
                        error_log("Delete Assessment Error: " . $e->getMessage());
                        $_SESSION['form_message'] = "Database error while deleting assessment.";
                        $_SESSION['form_message_type'] = 'error';
                    }
                }
                break;
        }
    }
}
// Always redirect back to the assessments page after processing a POST request
header('Location: assessments.php');
exit;
?>