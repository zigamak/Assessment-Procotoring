<?php
// includes/email_templates/registration_confirmation_email.php
// Email template for registration confirmation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mackenny Assessment - Registration Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7fa;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
        }
        .header {
            background-color: #0a1930;
            padding: 20px;
            text-align: center;
        }
        .header img {
            max-width: 20%;
            height: auto;
        }
        .header h1 {
            color: #ffffff;
            font-size: 28px;
            margin: 10px 0 0;
        }
        .content {
            padding: 20px;
            color: #333333;
        }
        .content h2 {
            font-size: 24px;
            margin-bottom: 15px;
        }
        .content p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .footer {
            background-color: #f4f7fa;
            padding: 10px;
            text-align: center;
            font-size: 14px;
            color: #666666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://mackennytutors.com/wp-content/uploads/2025/05/Mackenny.png" alt="Mackenny Assessment Logo">
            <h1>Mackenny Assessment</h1>
        </div>
        <div class="content">
            <h2>Welcome, {{username}}!</h2>
            <p>Thank you for registering with Mackenny Assessment. Your application has been successfully submitted and is now under review.</p>
            <p>You will receive a notification once your application has been processed. Please check your inbox (and spam/junk folder) regularly for updates.</p>
            <p>If you have any questions, feel free to contact our support team at <a href="mailto:support@mackennytutors.com">support@mackennytutors.com</a>.</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Mackenny Assessment. All rights reserved.</p>
        </div>
    </div>
</body>
</html>