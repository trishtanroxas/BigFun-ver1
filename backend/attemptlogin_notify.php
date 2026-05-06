<?php

/**
 * This file contains the helper function to generate the HTML email body
 * for the account lockout notification, matching the new design template.
 */

/**
 * Helper function to format a security detail row for an HTML table.
 * It skips any rows where the value is empty or 'N/A'.
 */
function format_security_detail_row($label, $value) {
    $safe_value = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    
    if (!empty($safe_value) && $safe_value !== 'N/A') {
        // Style user agent differently to make it readable
        $value_style = 'padding: 8px 0; font-weight: 500; color: #000;';
        if ($label === 'Device / User Agent') {
            $value_style .= 'font-family: monospace; font-size: 12px; word-break: break-all;';
        }

        return "<tr>
                    <td style='padding: 8px 0; color: #777; font-weight: 600; vertical-align: top; width: 140px;'>" . htmlspecialchars($label) . "</td>
                    <td style='" . $value_style . "'>" . nl2br($safe_value) . "</td>
                </tr>";
    }
    return '';
}

/**
 * Generates the HTML email body for the security alert using the new template.
 *
 * @param array $attacker_info An associative array containing 'ip', 'location', 'isp', and 'user_agent'.
 * @return string The formatted HTML email body.
 */
function generateLockoutEmailBody($attacker_info) {
    
    // --- 1. Prepare Security Details ---
    $security_details_html = format_security_detail_row('IP Address', $attacker_info['ip'])
                           . format_security_detail_row('Approx. Location', $attacker_info['location'])
                           . format_security_detail_row('ISP', $attacker_info['isp'])
                           . format_security_detail_row('Device / User Agent', $attacker_info['user_agent']);

    // --- 2. Create Dynamic Base URL ---
    // This logic creates a root URL that works for localhost or a live domain
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST']; // e.g., 'localhost', 'localhost:8080', or 'www.yourwebsite.com'
    
    // Assumes 'forgot-password.php' is in the root directory of your site.
    // If it's in a subfolder (e.g., /auth/forgot-password.php), change the path here.
    $forgot_password_url = $protocol . "://" . $host . "/forgot-password.php";

    // --- 3. Define CSS Styles (Adapted for Security Alert) ---
    // Colors changed: purple (#802080) and light purple (#FFEFFC) 
    // are replaced with red (#d9534f) and light red (#fdf7f7)
    $style = "
        body { font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f7; margin: 0; padding: 20px; line-height: 1.6; }
        .wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.07); overflow: hidden; }
        .header { background-color: #fdf7f7; text-align: center; padding: 25px 20px; border-bottom: 1px solid #f0f0f0; }
        .header img { max-height: 50px; }
        .content { padding: 30px; }
        .content h1 { color: #d9534f; font-size: 26px; font-weight: 700; margin: 0 0 10px; }
        .content p { color: #333; margin: 0 0 20px; }
        .card { background-color: #ffffff; border: 1px solid #e9e9e9; border-radius: 8px; padding: 20px; margin-top: 25px; }
        .section-title { font-size: 18px; font-weight: bold; color: #333333; margin: 0 0 15px; padding-bottom: 8px; border-bottom: 2px solid #f0f0f0; }
        
        .footer { text-align: center; margin-top: 30px; padding: 0 20px 20px; font-size: 0.85em; color: #888888; }
        .cta-box { background-color: #fdf7f7; border-left: 4px solid #d9534f; padding: 20px; margin-top: 30px; border-radius: 4px; }
        .cta-button { display: inline-block; padding: 12px 25px; margin-top: 20px; background-color: #d9534f; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
    ";

    // --- 4. Assemble the Final HTML Email ---
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
            <!-- Using the same logo path as your template. Update alt tag. -->
            <img src='httpsG://www.yourwebsite.com/images/bgfunlogo.png' alt='BigFun Security'>
        </div>
        <div class='content'>
            <h1>Security Alert: Account Locked</h1>
            <p>Hello,<br>Your BigFun account has been automatically locked after 3 consecutive failed login attempts.</p>
            
            <div class='card'>
                <h3 class='section-title'>Attempt Details</h3>
                <p style="font-size: 14px; color: #555;">Here is the information we captured about the final failed attempt:</p>
                <table style='width:100%; margin-top: 15px;' cellspacing='0' cellpadding='0'>
                    <tbody>
                        {$security_details_html}
                    </tbody>
                </table>
            </div>

            <div class='cta-box'>
                <h3 class='section-title' style='margin-bottom: 10px; border: none;'>Next Steps</h3>
                <p style='margin:0; line-height: 1.6;'>
                    To unlock your account and ensure its security, you must reset your password. 
                    Please use the 'Forgot Password' link on the login page or click the button below.
                </p>
                
                <!-- ** DYNAMIC LINK: This now points to your localhost or live site ** -->
                <a href='{$forgot_password_url}' class='cta-button'>Reset Your Password</a>
            </div>

        </div>
        <div class='footer'>
            <p>If you did not attempt to log in, your account may be at risk. Please reset your password immediately.<br>&copy; " . date("Y") . " BigFun. All rights reserved.</p>
        </div>
    </div>
    </body>
    </html>
    HTML;
}
?>