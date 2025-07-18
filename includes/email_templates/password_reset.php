<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Mackenny Assessments</title>
    <style>
        /* Basic inline styles for email client compatibility */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eeeeee;
        }
        .header h1 {
            color: #1a202c; /* Dark blue for theme */
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 20px 0;
        }
        .content p {
            margin-bottom: 15px;
        }
        .button-container {
            text-align: center;
            padding: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 25px;
            background-color: #1a202c; /* Dark blue for button */
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
            font-size: 12px;
            color: #777777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mackenny Assessments</h1>
        </div>
        <div class="content">
            <p>Hello {{username}},</p>
            <p>You have requested to **reset your password** for your Mackenny Assessments account.</p>
            <p>Please click the button below to set a new password:</p>
            <div class="button-container">
                <a href="{{reset_link}}" class="button">Reset Your Password</a>
            </div>
            <p>This link will expire in **1 hour**.</p>
            <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Mackenny Assessments. All rights reserved.</p>
        </div>
    </div>
</body>
</html>