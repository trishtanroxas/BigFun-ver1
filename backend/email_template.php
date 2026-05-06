<?php

/**
 * Helper function to format a detail row for an HTML table.
 * It skips any rows where the value is empty.
 */
function format_detail_row($label, $value) {
    if (!empty($value) && trim($value) !== '') {
        return "<tr>
                    <td style='padding: 8px 0; color: #777; font-weight: 600; vertical-align: top; width: 140px;'>" . htmlspecialchars($label) . "</td>
                    <td style='padding: 8px 0; font-weight: 500; color: #000;'>" . nl2br(htmlspecialchars($value)) . "</td>
                </tr>";
    }
    return '';
}

/**
 * Generates the HTML body for the order confirmation email with an enhanced, modern design.
 */
function generateOrderEmailBody($order_id, $user, $cart_items, $order_details) {
    
    // --- 1. Prepare Order Items List ---
    $items_html = '';
    foreach ($cart_items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $items_html .= "
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #eeeeee;'>" . htmlspecialchars($item['service_name']) . " <span style='color:#888;'>(x" . htmlspecialchars($item['quantity']) . ")</span></td>
                <td style='padding: 12px; border-bottom: 1px solid #eeeeee; text-align: right; font-weight: bold;'>$" . number_format($item_total, 2) . "</td>
            </tr>";
    }
    
    // --- 2. Prepare Full Billing Summary ---
    // This now correctly calculates all line items based on the order_details
    $billing_summary_html = "
        <tr class='summary-row'>
            <td style='padding: 8px 0; color: #555;'>Subtotal</td>
            <td style='padding: 8px 0; text-align: right; font-weight: 500;'>$" . number_format($order_details['subtotal'], 2) . "</td>
        </tr>";

    if (isset($order_details['discount_amount']) && $order_details['discount_amount'] > 0) {
        $discount_percent_text = isset($order_details['discount_percent']) ? " (" . $order_details['discount_percent'] . "%)" : "";
        $billing_summary_html .= "
            <tr class='summary-row'>
                <td style='padding: 8px 0; color: #198754;'>Duration Discount{$discount_percent_text}</td>
                <td style='padding: 8px 0; text-align: right; font-weight: 500; color: #198754;'>-$" . number_format($order_details['discount_amount'], 2) . "</td>
            </tr>";
    }

    if (isset($order_details['delivery_fee']) && $order_details['delivery_fee'] > 0) {
        $zone_text = !empty($order_details['delivery_zone']) ? " (" . htmlspecialchars($order_details['delivery_zone']) . ")" : "";
        $billing_summary_html .= "
            <tr class='summary-row'>
                <td style='padding: 8px 0; color: #555;'>Delivery Fee{$zone_text}</td>
                <td style='padding: 8px 0; text-align: right; font-weight: 500;'>+$" . number_format($order_details['delivery_fee'], 2) . "</td>
            </tr>";
    }

    // Calculate interest amount (it's not passed directly, but total, subtotal, discount, and delivery are)
    $interest_amount = $order_details['total_amount'] - ($order_details['subtotal'] - $order_details['discount_amount'] + $order_details['delivery_fee']);
    if ($interest_amount > 0.01) { // Use 0.01 to handle potential float rounding issues
         $interest_rate_text = isset($order_details['interest_rate']) ? " (" . $order_details['interest_rate'] . "%)" : "";
         $billing_summary_html .= "
            <tr class='summary-row'>
                <td style='padding: 8px 0; color: #555;'>Installment Interest{$interest_rate_text}</td>
                <td style='padding: 8px 0; text-align: right; font-weight: 500;'>+$" . number_format($interest_amount, 2) . "</td>
            </tr>";
    }

    $billing_summary_html .= "
        <tr class='grand-total'>
            <td style='padding: 15px 0 0; border-top: 2px solid #333; font-size: 1.2em; font-weight: bold; color: #000;'>Grand Total</td>
            <td style='padding: 15px 0 0; border-top: 2px solid #333; font-size: 1.2em; font-weight: bold; color: #802080; text-align: right;'>$" . number_format($order_details['total_amount'], 2) . "</td>
        </tr>";


    // --- 3. Prepare Detailed Information Sections ---
    $masked_card_info = '';
    if ($order_details['payment_method'] === 'Card' && !empty($order_details['card_number'])) {
        $card_brand = !empty($order_details['card_type']) ? $order_details['card_type'] . ' ' : '';
        $masked_card_info = $card_brand . 'ending in ' . substr($order_details['card_number'], -4);
    }
    
    $personal_info_html = format_detail_row('Full Name', $order_details['full_name'])
                        . format_detail_row('Contact No.', $order_details['contact_number'])
                        . format_detail_row('Email', $order_details['email'])
                        . format_detail_row('Delivery Zone', $order_details['delivery_zone'])
                        . format_detail_row('Street Address', $order_details['address']);
    
    $event_details_html = format_detail_row('Event Date', date('F j, Y', strtotime($order_details['date_event'])))
                        . format_detail_row('Event Time', date('g:i A', strtotime($order_details['start_time'])) . ' - ' . date('g:i A', strtotime($order_details['end_time'])))
                        . format_detail_row('Event Type(s)', $order_details['type_event'])
                        . format_detail_row('Location Check', $order_details['location_checklist'])
                        . format_detail_row('Additional Notes', $order_details['notes']);

    $payment_details_html = format_detail_row('Payment Method', $order_details['payment_method'])
                          . format_detail_row('Payment Status', $order_details['payment_status'])
                          . format_detail_row('Card Details', $masked_card_info)
                          . format_detail_row('Installment Plan', !empty($order_details['installment_plan']) ? $order_details['installment_plan'] . ' Months' : '')
                          . format_detail_row('Next Payment Due', !empty($order_details['next_due_date']) ? date('F j, Y', strtotime($order_details['next_due_date'])) : '');

    // --- 4. Prepare Dynamic Call-to-Action ---
    $call_to_action_html = '';
    if ($order_details['payment_status'] !== 'Paid') {
        $action_text = '';
        $show_button = true;

        switch ($order_details['payment_method']) {
            case 'Installment':
                $due_date_formatted = date('F j, Y', strtotime($order_details['next_due_date']));
                $action_text = "To confirm your booking, your first installment payment is due on <strong>{$due_date_formatted}</strong>. Please visit your dashboard to settle the payment.";
                break;
            case 'Cash':
                $action_text = "Our team will contact you shortly to arrange the cash payment. Please prepare the exact amount. Thank you!";
                $show_button = false;
                break;
            default:
                $action_text = "To confirm your booking, a payment is required. Please visit your dashboard to settle the payment.";
                break;
        }

        $call_to_action_html = "
            <div class='cta-box'>
                <h3 class='section-title' style='margin-bottom: 10px; border: none;'>Next Steps</h3>
                <p style='margin:0; line-height: 1.6;'>{$action_text}</p>";
        
        if ($show_button) {
            // ** IMPORTANT: Change this URL to your real website **
            $call_to_action_html .= "<a href='https://www.yourwebsite.com/invoices.php' class='cta-button'>View Invoice & Make Payment</a>";
        }
        $call_to_action_html .= "</div>";
    }

    // --- 5. Define CSS Styles for the Email ---
    $style = "
        body { font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f7; margin: 0; padding: 20px; line-height: 1.6; }
        .wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.07); overflow: hidden; }
        .header { background-color: #FFEFFC; text-align: center; padding: 25px 20px; }
        .header img { max-height: 50px; }
        .content { padding: 30px; }
        .content h1 { color: #802080; font-size: 26px; font-weight: 700; margin: 0 0 10px; }
        .content p { color: #333; margin: 0 0 20px; }
        .card { background-color: #ffffff; border: 1px solid #e9e9e9; border-radius: 8px; padding: 20px; margin-top: 25px; }
        .section-title { font-size: 18px; font-weight: bold; color: #333333; margin: 0 0 15px; padding-bottom: 8px; border-bottom: 2px solid #f0f0f0; }
        
        .item-table { width: 100%; border-collapse: collapse; }
        .item-table td { padding: 12px; border-bottom: 1px solid #eeeeee; }

        .total-summary { width: 100%; margin-top: 20px; }
        .total-summary td { padding: 8px 0; text-align: right; }
        .total-summary .grand-total td { 
            font-weight: bold; 
            font-size: 1.3em; 
            color: #802080; 
            border-top: 2px solid #333; 
            padding-top: 15px; 
        }
        .total-summary .grand-total td:first-child {
            text-align: left;
            color: #000;
        }

        .footer { text-align: center; margin-top: 30px; padding: 0 20px 20px; font-size: 0.85em; color: #888888; }
        .cta-box { background-color: #FFEFFC; border-left: 4px solid #802080; padding: 20px; margin-top: 30px; border-radius: 4px; }
        .cta-button { display: inline-block; padding: 12px 25px; margin-top: 20px; background-color: #802080; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
    ";

    // --- 6. Assemble the Final HTML Email ---
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Confirmation</title>
        <style>{$style}</style>
    </head>
    <body>
    <div class='wrapper'>
        <div class='header'>
            <img src='https://www.yourwebsite.com/images/bgfunlogo.png' alt='BigFun Logo'>
        </div>
        <div class='content'>
            <h1>Booking Received!</h1>
            <p>Hello {$user['first_name']},<br>Thank you for your booking! We've received your request for order <strong>#{$order_id}</strong>.</p>
            
            <div class='card'>
                <h3 class='section-title'>Billing Summary</h3>
                <table class='item-table' cellspacing='0' cellpadding='0'>
                    <tbody>{$items_html}</tbody>
                </table>
                <table class='total-summary' cellspacing='0' cellpadding='0'>
                    <tbody>{$billing_summary_html}</tbody>
                </table>
            </div>

            <div class='card'>
                <h3 class='section-title'>Personal Information</h3>
                <table style='width:100%;' cellspacing='0' cellpadding='0'><tbody>{$personal_info_html}</tbody></table>
            </div>

            <div class='card'>
                <h3 class='section-title'>Event Details</h3>
                <table style='width:100%;' cellspacing='0' cellpadding='0'><tbody>{$event_details_html}</tbody></table>
            </div>

            <div class='card'>
                <h3 class='section-title'>Payment Information</h3>
                <table style='width:100%;' cellspacing='0' cellpadding='0'><tbody>{$payment_details_html}</tbody></table>
            </div>
            
            {$call_to_action_html}

        </div>
        <div class='footer'>
            <p>If you have any questions, please contact our support team.<br>&copy; 2025 BigFun. All rights reserved.</p>
        </div>
    </div>
    </body>
    </html>
    HTML;
}
?>