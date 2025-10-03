<?php
function generatePaymentConfirmationEmail($user, $order, $payment_amount) {
    $full_name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
    $remaining_balance = floatval($order['remaining_balance']);
    $next_due_date_formatted = !empty($order['next_due_date']) ? date("F j, Y", strtotime($order['next_due_date'])) : 'N/A';

    $payment_details_html = '';
    if ($remaining_balance > 0) {
        $payment_details_html = "<p style='margin: 0; font-size: 14px; color: #555;'>Your next payment is due by: <strong>{$next_due_date_formatted}</strong>.</p>";
    } else {
        $payment_details_html = "<p style='margin: 0; font-size: 16px; color: #198754; font-weight: bold;'>Congratulations, this order is now fully paid!</p>";
    }

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Confirmation</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f4f4;">
            <tr>
                <td align="center">
                    <table width="600" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                        <tr>
                            <td align="center" style="padding: 20px 0; border-bottom: 1px solid #eeeeee;">
                                <img src="https://www.yourwebsite.com/images/bgfunlogo.png" alt="BigFun Logo" style="max-height: 50px;">
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 30px;">
                                <h1 style="color: #802080; font-size: 24px; margin-top: 0;">Payment Received!</h1>
                                <p style="font-size: 16px; color: #555; line-height: 1.6;">Hello {$full_name},</p>
                                <p style="font-size: 16px; color: #555; line-height: 1.6;">
                                    This is a confirmation that we have successfully received your payment of 
                                    <strong style="color: #802080;">$
    HTML
    . number_format($payment_amount, 2)
    . <<<HTML
    </strong> for your Order <strong>#{$order['id']}</strong>.
                                </p>
                                
                                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-top: 25px; background-color: #f8f8f8; border-radius: 8px; padding: 20px;">
                                    <tr>
                                        <td>
                                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #555;">New Remaining Balance:</p>
                                            <p style="margin: 0; font-size: 28px; font-weight: bold; color: #333;">$
    HTML
    . number_format($remaining_balance, 2)
    . <<<HTML
    </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-top: 15px; border-top: 1px solid #ddd; margin-top: 15px;">
                                            {$payment_details_html}
                                        </td>
                                    </tr>
                                </table>

                                <p style="font-size: 16px; color: #555; line-height: 1.6; margin-top: 25px;">
                                    You can view your updated invoice details anytime from your dashboard.
                                </p>
                                
                                <table border="0" cellspacing="0" cellpadding="0" style="margin: 25px auto;">
                                    <tr>
                                        <td align="center" style="background-color: #802080; border-radius: 5px;">
                                            <a href="https://www.yourwebsite.com/invoices.php" target="_blank" style="padding: 12px 25px; border: 1px solid #802080; color: #ffffff; text-decoration: none; display: inline-block; font-weight: bold; font-size: 16px;">
                                                View My Invoices
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding: 20px; font-size: 12px; color: #888888; border-top: 1px solid #eeeeee;">
                                &copy; 
    HTML
    . date('Y')
    . <<<HTML
     BigFun. All rights reserved.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    HTML;
}
?>