<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header_admin.php';

if (!isAdmin()) {
    redirect(BASE_URL . 'login.php');
}

if (!isset($_GET['attempt_id']) || !is_numeric($_GET['attempt_id'])) {
    $message = display_message("No attempt selected or invalid attempt ID.", "error");
} else {
    $attempt_id = (int)$_GET['attempt_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT pi.image_id, pi.capture_time, pi.image_path, u.username
            FROM proctoring_images pi
            JOIN quiz_attempts qa ON pi.attempt_id = qa.attempt_id
            JOIN users u ON pi.user_id = u.user_id
            WHERE pi.attempt_id = :attempt_id
            ORDER BY pi.capture_time
        ");
        $stmt->execute(['attempt_id' => $attempt_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("View Proctoring Images Error: " . $e->getMessage());
        $message = display_message("An error occurred while fetching images.", "error");
    }
}
?>

<div class="container mx-auto p-4 py-8">
    <?php echo isset($message) ? $message : ''; ?>
    <?php if (!empty($images)): ?>
        <h1 class="text-3xl font-bold text-theme-color mb-6">Proctoring Images for Attempt ID: <?php echo htmlspecialchars($attempt_id); ?></h1>
        <p class="text-gray-700 mb-4">Student: <?php echo htmlspecialchars($images[0]['username']); ?></p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($images as $image): ?>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <img src="<?php echo BASE_URL . htmlspecialchars($image['image_path']); ?>" alt="Proctoring Image" class="w-full h-auto rounded-md mb-2" onerror="this.onerror=null;this.src='https://placehold.co/400x200/cccccc/333333?text=Image+Not+Found';">
                    <p class="text-gray-600 text-sm">Captured at: <?php echo htmlspecialchars($image['capture_time']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">No Images Available</h2>
            <p class="text-gray-600">No proctoring images found for this attempt.</p>
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition duration-300">
                Back to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer_admin.php'; ?>