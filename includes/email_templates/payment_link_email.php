<?php
// includes/email_templates/payment_link_email.php
// Email template for sending payment links with auto-login functionality

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Link for {{quiz_title}}</title>
    <style>
        /* Minimalist CSS for email compatibility */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #0a1930;
            color: #fff;
            padding: 10px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        /* Button specific styles - critical for inline */
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 20px 0;
            background-color: #4f46e5;
            color: #ffffff !important; /* Force white text */
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold; /* Make the text bold for better visibility */
        }
        .button:hover {
            background-color: #4338ca;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mackenny Assessment</h1>
        </div>
        <p>Hello {{first_name}},</p>
        <p>You have been verified to participate in the "{{quiz_title}}" assessment. Please proceed with the payment of ₦{{amount}} to access the assessment.</p>
        <p style="text-align: center;">
            <a href="{{payment_link}}" class="button">Make Payment</a>
        </p>
        <p>This link will automatically log you in and direct you to the payment page. After successful payment, you will be redirected to your dashboard.</p>
        <p>If you have any questions, please contact our support team.</p>
        <p>Best regards,<br>The Mackenny Assessment Team</p>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Mackenny Assessment. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

<?php
$email_content = ob_get_clean();
echo $email_content;
?>