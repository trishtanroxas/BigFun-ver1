<?php
// This file should contain ALL your email helper functions

/**
 * Helper function to format a detail row for an HTML table.
 * (Needed by generateLockoutEmailBody)
 */
function format_detail_row($label, $value) {
    if (!empty($value) && trim($value) !== '') {
        return "<tr>
                    <td style='padding: 5px 0; color: #555; vertical-align: top; width: 140px;'>" . htmlspecialchars($label) . "</td>
                    <td style='padding: 5px 0; font-weight: bold; color: #333;'>" . nl2br(htmlspecialchars($value)) . "</td>
                </tr>";
    }
    return '';
}


/**
 * Generates the HTML body for the account lockout security alert.
 * This is the function your login script needs.
 */
function generateLockoutEmailBody($attacker_info) {

    // Use the helper function to format details
    $details_html = format_detail_row('IP Address', $attacker_info['ip'])
                  . format_detail_row('Approx. Location', $attacker_info['location'])
                  . format_detail_row('Internet Provider', $attacker_info['isp'])
                  . format_detail_row('Time of Lock', date('F j, Y, g:i a T'));

    // --- Define CSS Styles ---
    $style = "
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f7; margin: 0; padding: 20px; line-height: 1.5; }
        .wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.07); overflow: hidden; }
        .header { background-color: #FFEFFC; text-align: center; padding: 25px 20px; }
        .header img { max-height: 50px; }
        .content { padding: 30px; }
        .content h1 { color: #802080; font-size: 26px; font-weight: 600; margin: 0 0 10px; }
        .content p { color: #333; margin: 0 0 20px; }
        .card { background-color: #ffffff; border: 1px solid #e9e9e9; border-radius: 8px; padding: 20px; margin-top: 25px; }
        .section-title { font-size: 18px; font-weight: bold; color: #333333; margin: 0 0 15px; padding-bottom: 8px; border-bottom: 2px solid #f0f0f0; }
        .footer { text-align: center; margin-top: 30px; padding: 0 20px 20px; font-size: 0.85em; color: #888888; }
        .cta-box { background-color: #FFFBEB; border-left: 4px solid #F59E0B; padding: 20px; margin-top: 30px; border-radius: 4px; }
        .cta-button { display: inline-block; padding: 12px 25px; margin-top: 20px; background-color: #802080; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
    ";

    // --- Determine Correct Reset Link based on context (User vs Admin lock) ---
    // Since this function is used for both, make the link dynamic or decide based on URL/session if possible
    // For simplicity here, we'll point to the ADMIN forgot password as this is called from login-admin.php context
    // Ideally, you might pass another parameter or have separate functions.
    $forgot_password_link = 'https://www.yourwebsite.com/forgot-password-admin.php'; // Link to admin forgot password

    // --- Assemble the Final HTML Email ---
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Security Alert</title>
        <style>{$style}</style>
    </head>
    <body>
    <div class='wrapper'>
        <div class='header'>
            <img src='https://www.yourwebsite.com/images/bgfunlogo.png' alt='BigFun Logo'>
        </div>
        <div class='content'>
            <h1>Security Alert: Account Locked</h1>
            <p>Your BigFun ADMIN account has been temporarily locked after 3 consecutive failed login attempts.</p>

            <div class='cta-box'>
                <h3 class='section-title' style='margin-bottom: 10px; border: none;'>What to do:</h3>
                <p style='margin:0; line-height: 1.6;'>
                    Please unlock your account by using the "Forgot Password" link.
                </p>
                <a href='{$forgot_password_link}' class='cta-button'>Reset Admin Password</a>
            </div>

            <div class='card'>
                <h3 class='section-title'>Attempt Details</h3>
                <p style="font-size: 0.9em; color: #555;">These details are based on the IP address that triggered the lock:</p>
                <table style='width:100%;' cellspacing='0' cellpadding='0'><tbody>{$details_html}</tbody></table>
            </div>

        </div>
        <div class='footer'>
            <p>If you have any questions, please contact support.<br>&copy; 2025 BigFun. All rights reserved.</p>
        </div>
    </div>
    </body>
    </html>
    HTML;
}

?>