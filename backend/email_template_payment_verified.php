<?php
function generatePaymentVerifiedEmailBody($customer_name, $order_id, $total_amount) {
    // Format data for display
    $formatted_amount = number_format($total_amount, 2);
    $current_year = date('Y');

    // Email Body (HTML)
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .header { background-color: #802080; color: white; padding: 10px 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .content h1 { color: #802080; }
        .footer { text-align: center; font-size: 0.9em; color: #777; margin-top: 20px; }
        .info-box { background-color: #f9f9f9; border: 1px solid #eee; padding: 15px; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>BigFun Entertainment</h2>
        </div>
        <div class="content">
            <h1>Payment Verified!</h1>
            <p>Hello {$customer_name},</p>
            <p>We are pleased to inform you that your cash payment for <strong>Order #{$order_id}</strong> has been successfully verified by our team.</p>
            <p>Your order is now marked as fully paid.</p>
            
            <div class="info-box">
                <strong>Order ID:</strong> #{$order_id}<br>
                <strong>Amount Paid:</strong> \${$formatted_amount}<br>
                <strong>Status:</strong> Paid
            </div>

            <p>Thank you for your business. We look forward to seeing you at your event!</p>
            <p>Sincerely,<br>The BigFun Team</p>
        </div>
        <div class="footer">
            <p>&copy; {$current_year} BigFun Entertainment. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}
?>