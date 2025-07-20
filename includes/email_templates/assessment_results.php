<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Results</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .container { background: #f9f9f9; padding: 20px; border-radius: 8px; }
        h1 { color: #392B7D; }
        .score-box { background: linear-gradient(180deg, #6742F1 0%, #392B7D 100%); color: white; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0; }
        .score { font-size: 2em; font-weight: bold; }
        .status { font-size: 1.2em; margin-top: 10px; }
        .details p { margin: 10px 0; }
        .footer { margin-top: 20px; font-size: 0.9em; color: #666; }
        a { color: #392B7D; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Assessment Results</h1>
        <p>Dear <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>,</p>
        <p>Congratulations on completing the assessment "<strong><?php echo htmlspecialchars($quiz['title']); ?></strong>"!</p>
        
        <div class="score-box">
            <div class="score"><?php echo htmlspecialchars($percentage); ?>%</div>
            <div class="status"><?php echo $percentage >= 70 ? 'Great Job!' : 'Needs Improvement'; ?></div>
        </div>
        
        <div class="details">
            <p><strong>Assessment:</strong> <?php echo htmlspecialchars($quiz['title']); ?></p>
            <p><strong>Score:</strong> <?php echo htmlspecialchars($student_score); ?> / <?php echo htmlspecialchars($max_score); ?></p>
            <p><strong>Percentage:</strong> <?php echo htmlspecialchars($percentage); ?>%</p>
            <p><strong>Completed:</strong> <?php echo date('g:i A, F j, Y', time()); ?></p>
        </div>
        
        <p>You can view detailed results, including your answers, on your <a href="<?php echo BASE_URL; ?>student/assessments.php?attempt_id=<?php echo htmlspecialchars($attempt_id); ?>">assessment dashboard</a>.</p>
        
        <div class="footer">
            <p>Thank you for using our assessment platform!</p>
            <p>© <?php echo date('Y'); ?> Assessment Platform. All rights reserved.</p>
        </div>
    </div>
</body>
</html>