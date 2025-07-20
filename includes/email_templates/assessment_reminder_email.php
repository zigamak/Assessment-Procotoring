<?php
// includes/email_templates/assessment_reminder_email.php
// Email template for assessment reminder and payment confirmation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{subject}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0a1930; color: #fff; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .button { display: inline-block; padding: 10px 20px; background-color: #0a1930; color: #fff; text-decoration: none; border-radius: 5px; }
        .footer { text-align: center; font-size: 12px; color: #777; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{subject}}</h1>
        </div>
        <div class="content">
            <p>Dear {{username}},</p>
            <p>{{message}}</p>
            <h2>Assessment Details</h2>
            <p><strong>Title:</strong> {{quiz_title}}</p>
            <p><strong>Description:</strong> {{description}}</p>
            <p><strong>Open Date & Time:</strong> {{open_datetime}}</p>
            <p><strong>Duration:</strong> {{duration_minutes}} minutes</p>
            <p><strong>Amount Paid:</strong> ₦{{amount}}</p>
            <p><strong>Grade Level:</strong> {{grade}}</p>
            <p><strong>Transaction Reference:</strong> {{transaction_reference}}</p>
            <p><strong>Payment Date:</strong> {{payment_date}}</p>
            <p>Access your assessment using the link below:</p>
            <p><a href="{{auto_login_link}}" class="button">Access Your Assessment</a></p>
            <p>If the button doesn’t work, copy and paste this link: {{auto_login_link}}</p>
        </div>
        <div class="footer">
            <p>Need help? Contact us at <a href="mailto:support@mackennyassessment.com">support@mackennyassessment.com</a>.</p>
            <p>&copy; 2025 Mackenny Assessment. All rights reserved.</p>
        </div>
    </div>
</body>
</html>