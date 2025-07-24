<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{email_title}}</title>
    <style type="text/css">
        /* Client-specific Styles */
        body { margin: 0; padding: 0; min-width: 100%; background-color: #f7f7f7; }
        table { border-spacing: 0; font-family: Arial, sans-serif; color: #333333; }
        td { padding: 0; }
        img { border: 0; }

        /* General Styling */
        .wrapper {
            width: 100%;
            table-layout: fixed;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        .webkit {
            max-width: 600px;
            margin: 0 auto;
        }
        .outer-table {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-collapse: separate; /* Required for border-radius */
            border-spacing: 0;
        }

        /* Header */
        .header {
            background-color: #0a1930; /* Dark blue */
            padding: 30px 20px;
            text-align: center;
            color: #ffffff;
            border-radius: 8px 8px 0 0;
        }
        .header h2 {
            margin: 0;
            font-size: 26px;
            font-weight: bold;
            line-height: 1.2;
        }

        /* Content Area */
        .content-area {
            padding: 30px;
            font-size: 15px;
            line-height: 1.7;
        }
        .content-area p {
            margin-top: 0;
            margin-bottom: 15px;
        }
        .content-area h3 {
            color: #0a1930;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 1px solid #eeeeee;
            padding-bottom: 5px;
        }

        /* Details Table */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        .details-table th,
        .details-table td {
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            text-align: left;
            vertical-align: top;
        }
        .details-table th {
            background-color: #f2f2f2;
            color: #0a1930;
            font-weight: bold;
            white-space: nowrap;
            width: 35%; /* Adjust as needed */
        }
        .details-table td {
            background-color: #ffffff;
        }
        .details-table tr:nth-child(even) th,
        .details-table tr:nth-child(even) td {
            background-color: #fcfcfc;
        }

        /* Important Instructions List */
        .content-area ul {
            list-style: disc;
            padding-left: 25px;
            margin-top: 15px;
            margin-bottom: 20px;
            font-size: 15px;
        }
        .content-area ul li {
            margin-bottom: 10px;
        }
        .content-area ul li strong {
            color: #0a1930;
        }

        /* Button */
        .button-wrapper {
            text-align: center;
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .button {
            display: inline-block;
            padding: 14px 28px;
            background-color: #0a1930;
            color: #ffffff !important; /* !important for email clients */
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            border: 1px solid #0a1930;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            -webkit-transition: all 0.3s ease;
            -moz-transition: all 0.3s ease;
            transition: all 0.3s ease;
        }
        .button:hover {
            background-color: #1a3050; /* Slightly lighter on hover */
            border-color: #1a3050;
        }

        /* Footer */
        .footer {
            background-color: #e8e8e8; /* Light gray */
            text-align: center;
            font-size: 0.85em;
            color: #777777;
            padding: 20px;
            border-radius: 0 0 8px 8px;
        }
        .footer p {
            margin: 0 0 5px 0;
        }
        .footer a {
            color: #0a1930;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }

        /* Responsive Styles */
        @media screen and (max-width: 600px) {
            .outer-table, .webkit { width: 100% !important; }
            .content-area { padding: 20px !important; }
            .header { padding: 20px !important; }
            .header h2 { font-size: 22px !important; }
            /* Force table rows to stack on small screens */
            .details-table th, .details-table td { padding: 10px !important; display: block; width: auto !important; }
            .details-table th { background-color: #0a1930; color: #ffffff; border-bottom: none; }
            .details-table td { border-top: none; padding-top: 5px !important; padding-bottom: 20px !important; }
            .details-table tr { margin-bottom: 20px; display: block; border: 1px solid #e0e0e0; border-radius: 5px; overflow: hidden; }
            .button { padding: 12px 24px !important; font-size: 15px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; min-width: 100%; background-color: #f7f7f7;">
    <center class="wrapper" style="width: 100%; table-layout: fixed; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
        <div class="webkit" style="max-width: 600px; margin: 0 auto;">
            <table class="outer-table" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); border-collapse: separate; border-spacing: 0;">
                <tr>
                    <td class="header" style="background-color: #0a1930; padding: 30px 20px; text-align: center; color: #ffffff; border-radius: 8px 8px 0 0;">
                        <h2 style="margin: 0; font-size: 26px; font-weight: bold; line-height: 1.2;">{{header_text}}</h2>
                    </td>
                </tr>
                <tr>
                    <td class="content-area" style="padding: 30px; font-size: 15px; line-height: 1.7;">
                        <p style="margin-top: 0; margin-bottom: 15px;">Dear {{first_name}} {{last_name}},</p>
                        <p style="margin-top: 0; margin-bottom: 15px;">We are pleased to confirm that your payment for the following assessment has been successfully processed.</p>

                        <h3 style="color: #0a1930; margin-top: 30px; margin-bottom: 15px; font-size: 18px; border-bottom: 1px solid #eeeeee; padding-bottom: 5px;">Assessment Details</h3>
                        <table class="details-table" cellpadding="0" cellspacing="0" border="0" style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px;">
                            <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Assessment Title</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #ffffff;">{{quiz_title}}</td>
                            </tr>
                            <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #fcfcfc; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Description</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #fcfcfc;">{{description}}</td>
                            </tr>
                            <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Start Date and Time</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2;">{{open_datetime}}</td>
                            </tr>
                            <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #fcfcfc; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Duration</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #fcfcfc;">{{duration_minutes}} minutes</td>
                            </tr>
                            <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Maximum Attempts</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2;">{{max_attempts}}</td>
                            </tr>
                            <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #fcfcfc; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Assessment Fee</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #fcfcfc;">₦{{amount}}</td>
                            </tr>
                             <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Grade</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2;">{{grade}}</td>
                            </tr>
                             <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #fcfcfc; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Transaction Reference</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #fcfcfc;">{{transaction_reference}}</td>
                            </tr>
                             <tr>
                                <th style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2; color: #0a1930; font-weight: bold; white-space: nowrap; width: 35%;">Payment Date</th>
                                <td style="padding: 12px 15px; border: 1px solid #e0e0e0; text-align: left; vertical-align: top; background-color: #f2f2f2;">{{payment_date}}</td>
                            </tr>
                        </table>

                        <h3 style="color: #0a1930; margin-top: 30px; margin-bottom: 15px; font-size: 18px; border-bottom: 1px solid #eeeeee; padding-bottom: 5px;">Important Instructions</h3>
                        <p style="margin-top: 0; margin-bottom: 15px;">Please note the following regarding your assessment:</p>
                        <ul style="list-style: disc; padding-left: 25px; margin-top: 15px; margin-bottom: 20px; font-size: 15px;">
                            <li style="margin-bottom: 10px;"><strong>Arrival Time:</strong> You are required to be ready and logged in at least 5 minutes before the assessment start time. The assessment portal will open 5 minutes prior to the scheduled start time ({{open_datetime}}).</li>
                            <li style="margin-bottom: 10px;"><strong>Assessment Window:</strong> The assessment will only be accessible on the scheduled date and time. Once the assessment duration ({{duration_minutes}} minutes) has elapsed, the portal will close, and you will no longer be able to take the assessment.</li>
                            <li style="margin-bottom: 10px;"><strong>Preparation:</strong> Ensure you have a stable internet connection and a quiet environment to complete the assessment without interruptions.</li>
                        </ul>

                        <p style="margin-top: 0; margin-bottom: 15px;">You now have full access to this assessment. Click the button below to access your dashboard and prepare for the assessment.</p>
                        <div class="button-wrapper" style="text-align: center; padding-top: 20px; padding-bottom: 20px;">
                            <a href="{{auto_login_link}}" class="button" style="display: inline-block; padding: 14px 28px; background-color: #0a1930; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold; border: 1px solid #0a1930; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); -webkit-transition: all 0.3s ease; -moz-transition: all 0.3s ease; transition: all 0.3s ease;">Go to Dashboard</a>
                        </div>
                        <p style="margin-top: 0; margin-bottom: 15px;">Thank you for choosing Mackenny Assessment!</p>
                    </td>
                </tr>
                <tr>
                    <td class="footer" style="background-color: #e8e8e8; text-align: center; font-size: 0.85em; color: #777777; padding: 20px; border-radius: 0 0 8px 8px;">
                        <p style="margin: 0 0 5px 0;">&copy; <?php echo date("Y"); ?> Mackenny Assessment. All rights reserved.</p>
                        <p style="margin: 0;">If you have any questions, contact us at <a href="mailto:support@mackennyassessment.com" style="color: #0a1930; text-decoration: none;">support@mackennyassessment.com</a>.</p>
                    </td>
                </tr>
            </table>
            </div>
    </center>
</body>
</html>