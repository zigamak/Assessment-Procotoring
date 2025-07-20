<?php
// This file is an HTML email template.
// It uses placeholders like {{username}}, {{email}}, etc., which will be replaced by PHP.
// Do NOT include PHP logic other than direct HTML output or structural tags.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmed - Mackenny Assessment</title>
    <style>
        body { font-family: -apple-system, BlinkMacMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.05); overflow: hidden; }
        .header { background-color: #0a1930; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .header img { max-width: 150px; height: auto; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto; }
        .header h1 { font-size: 28px; margin: 0; padding: 0; }
        .content { padding: 20px; border-bottom: 1px solid #eeeeee; }
        .details { background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #e0e0e0; }
        .details p { margin: 8px 0; font-size: 15px; }
        .details strong { color: #555; }
        .footer { text-align: center; font-size: 0.85em; color: #777; margin-top: 20px; padding: 15px; }
        .footer a { color: #777; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Mackenny Assessment Logo">
            <h1>Payment Confirmed!</h1>
        </div>
        <div class="content">
            <p>Dear {{username}},</p>
            <p>Thank you for your payment to Mackenny Assessment. We confirm receipt of your payment for the assessment: **{{quiz_title}}**.</p>

            <div class="details">
                <p><strong>Assessment:</strong> {{quiz_title}}</p>
                <p><strong>Amount Paid:</strong> ₦{{amount}}</p>
                <p><strong>Transaction Reference:</strong> {{transaction_reference}}</p>
                <p><strong>Payment Date:</strong> {{payment_date}}</p>
            </div>

            <p>You have successfully secured your spot for the **{{quiz_title}}** assessment.</p>
            <p>You will receive a separate email with your login details and instructions to access your dashboard and assessment a few days before your scheduled assessment date.</p>

            <p>If you have any questions regarding your payment or upcoming assessment, please don't hesitate to contact our support team at <a href="mailto:support@mackennyassessment.com" style="color: #0a1930;">support@mackennyassessment.com</a>.</p>

            <p>We look forward to your assessment!</p>
            <p>Sincerely,</p>
            <p>The Mackenny Assessment Team</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Mackenny Assessment. All rights reserved.</p>
            <p>
                <a href="<?php echo BASE_URL; ?>privacy-policy.php" style="color: #777; text-decoration: underline;">Privacy Policy</a> |
                <a href="<?php echo BASE_URL; ?>terms-of-service.php" style="color: #777; text-decoration: underline;">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>