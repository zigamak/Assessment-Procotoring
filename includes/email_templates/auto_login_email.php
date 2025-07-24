<?php
// includes/email_templates/auto_login_email.php
// Email template for sending auto-login links to users.

// This file is intended to be included (ob_start, ob_get_clean)
// and should not be accessed directly.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{subject}}</title>
    <style>
        body {
            font-family: 'Inter', sans-serif; /* Using Inter as per instructions */
            line-height: 1.6;
            color: #ffffff; /* Changed text color for dark background */
            background-color: #1a202c; /* Main theme color */
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #2d3748; /* Slightly lighter dark background for container */
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); /* Adjusted shadow for dark theme */
            border: 1px solid #4a5568; /* Border for dark theme */
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #4a5568; /* Border for dark theme */
            margin-bottom: 20px;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            margin: 0;
        }
        .content {
            padding: 0 15px;
        }
        .content p {
            margin-bottom: 15px;
            color: #e2e8f0; /* Lighter text color for content */
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 25px;
            background-color: #667eea; /* A vibrant blue for the button */
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .button:hover {
            background-color: #5a67d8; /* Darker blue on hover */
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #4a5568; /* Border for dark theme */
            font-size: 12px;
            color: #cbd5e0; /* Lighter color for footer text */
        }
        .note {
            font-size: 13px;
            color: #cbd5e0; /* Lighter color for note text */
            text-align: center;
            margin-top: 20px;
            padding: 10px;
            background-color: #2d3748; /* Background for note in dark theme */
            border-left: 4px solid #667eea; /* Accent color for note border */
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mackenny Assessment</h1>
        </div>
        <div class="content">
            <p>Dear {{username}},</p>
            <p>This email contains an auto-login link to your Mackenny Assessment dashboard. This link was automatically generated for your convenience.</p>
            <p>You can use the button below to securely access your account without needing to enter your password:</p>

            <div class="button-container">
                <a href="{{auto_login_link}}" class="button">Access Your Dashboard</a>
            </div>

            
            <p>If you did not request this link, please ignore this email. Your account remains secure.</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Mackenny Assessment. All rights reserved.</p>
        </div>
    </div>
</body>
</html>