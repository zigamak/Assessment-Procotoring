<?php
// includes/email_templates/welcome_email.php
// This file contains the HTML template for the welcome email.
// Placeholders: {{username}}, {{auto_login_link}}
?>
<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
    <h2 style="color: #0a1930; text-align: center;">Welcome to Mackenny Assessment!</h2>
    <p>Dear {{username}},</p>
    <p>We are thrilled to welcome you to Mackenny Assessment! Your account has been successfully created.</p>
    <p>Get ready to embark on a journey of learning and progress. With your personalized dashboard, you can track your achievements, access valuable learning resources, and prepare effectively for your assessments.</p>
    <p>To get started and log in to your dashboard, please click the button below:</p>
    <p style="text-align: center; margin: 20px 0;">
        <a href="{{auto_login_link}}" style="display: inline-block; padding: 10px 20px; background-color: #1e4b31; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">
            Start Your Assessment Journey!
        </a>
    </p>
    <p>This link is for one-time use and will automatically log you in.</p>
    <p>If the button doesn't work, you can copy and paste the following link into your browser:</p>
    <p style="word-break: break-all;"><a href="{{auto_login_link}}">{{auto_login_link}}</a></p>
    <p>We wish you the best in your assessments!</p>
    <p>Sincerely,<br>The Mackenny Assessment Team</p>
    <hr style="border: 0; border-top: 1px solid #eee; margin-top: 20px;">
    <p style="text-align: center; font-size: 0.8em; color: #888;">This is an automated email, please do not reply.</p>
</div>