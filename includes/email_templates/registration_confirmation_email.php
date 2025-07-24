<?php
// includes/email_templates/registration_confirmation_email.php
// Email template for registration confirmation, using full name and including header/footer

// Include header and footer for consistent email styling
require_once 'header.php';
?>

<div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
    <h2 style="color: #0a1930; font-size: 24px; font-weight: bold; margin-bottom: 20px; text-align: center;">
        Welcome to Mackenny Assessment, <?= htmlspecialchars($first_name . ' ' . $last_name) ?>!
    </h2>
    
    <p style="color: #333333; font-size: 16px; line-height: 1.6; margin-bottom: 15px;">
        Thank you for registering with Mackenny Assessment. Your application has been successfully submitted and is now under review. Our team will carefully evaluate your details, and you will be notified via email once your application is approved or if further information is required.
    </p>

    <h3 style="color: #0a1930; font-size: 18px; font-weight: bold; margin-top: 20px; margin-bottom: 10px;">
        Your Submitted Details
    </h3>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">Full Name</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars($first_name . ' ' . $last_name) ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">Email</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars($email) ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">Username</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars($username) ?: 'Auto-generated upon approval' ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">School Name</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars($school_name) ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">Grade</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars($grade) ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">Location</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars($city . ', ' . $state . ', ' . $country) ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">Address</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars($address) ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">Date of Birth</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars(date('F j, Y', strtotime($date_of_birth))) ?>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px; font-weight: bold;">Gender</td>
            <td style="padding: 8px; border-bottom: 1px solid #e0e0e0; color: #333333; font-size: 14px;">
                <?= htmlspecialchars(ucfirst($gender)) ?>
            </td>
        </tr>
    </table>

    <p style="color: #333333; font-size: 16px; line-height: 1.6; margin-bottom: 15px;">
        Please ensure all details are correct. If you need to make any changes, contact our support team at 
        <a href="mailto:support@mackennyassessment.com" style="color: #0a1930; text-decoration: underline;">support@mackennyassessment.com</a>.
    </p>

    <p style="color: #333333; font-size: 16px; line-height: 1.6; margin-bottom: 15px;">
        Once your application is approved, you will receive further instructions to access your personalized dashboard, where you can track your progress and access learning materials.
    </p>


</div>

<?php
require_once 'footer.php';
?>