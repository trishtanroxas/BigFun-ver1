<?php
session_start();
// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require 'backend/email_template.php';
include "backend/notification_helper.php"; // --- ADDED for notifications ---

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

include "backend/db.php";

// Check for success flags from a previous order
$show_success_modal = $_SESSION['order_success'] ?? false;
$order_payment_method = $_SESSION['order_payment_method'] ?? null;
unset($_SESSION['order_success'], $_SESSION['order_payment_method']);

// Check for the specific booking conflict flag
$show_booking_error_modal = $_SESSION['show_booking_error_modal'] ?? false;
$booking_error_msg = '';
if ($show_booking_error_modal) {
    $booking_error_msg = $_SESSION['msg'];
    unset($_SESSION['show_booking_error_modal'], $_SESSION['msg'], $_SESSION['msg_type']);
}


// Fetch logged in user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM signup WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_initial'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// Fetch cart items
$cart_items = [];
$subtotal = 0; // Use $subtotal for clarity
$cart_stmt = $conn->prepare("
    SELECT ci.id as cart_item_id, ci.quantity, s.id as service_id, s.service_name, s.price, s.service_image
    FROM cart_items ci 
    JOIN services s ON ci.service_id = s.id 
    WHERE ci.user_id = ?
");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$result = $cart_stmt->get_result();
if ($result->num_rows > 0) {
    while ($item = $result->fetch_assoc()) {
        $item['price'] = (float)$item['price']; // Ensure price is a float
        $cart_items[] = $item;
        $subtotal += $item['price'] * $item['quantity'];
    }
} else if (!$show_success_modal) { // Allow page load if success modal is about to show
    $_SESSION['msg'] = "Your cart is empty.";
    $_SESSION['msg_type'] = "info";
    header("Location: book-dashboard.php");
    exit;
}
$cart_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 1. NEW: 4-HOUR USER COOLDOWN VALIDATION ---
    // This requires a `created_at` TIMESTAMP column on your `orders` table
    $cooldown_hours = 4;
    $check_cooldown_stmt = $conn->prepare("
        SELECT created_at FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $check_cooldown_stmt->bind_param("i", $user_id); // $user_id is already set
    $check_cooldown_stmt->execute();
    $cooldown_result = $check_cooldown_stmt->get_result();

    if ($cooldown_result->num_rows > 0) {
        $last_order = $cooldown_result->fetch_assoc();
        try {
            $last_order_time = new DateTime($last_order['created_at']);
            $current_time = new DateTime();

            $interval_seconds = $current_time->getTimestamp() - $last_order_time->getTimestamp();
            $cooldown_seconds = $cooldown_hours * 3600;

            if ($interval_seconds < $cooldown_seconds) {
                $time_remaining_seconds = $cooldown_seconds - $interval_seconds;

                $time_remaining_hours = floor($time_remaining_seconds / 3600);
                $time_remaining_minutes = ceil(($time_remaining_seconds % 3600) / 60); // Use ceil for minutes

                $error_msg = "You have recently placed an order. Please wait ";
                if ($time_remaining_hours > 0) {
                    $error_msg .= "{$time_remaining_hours} hour(s) and ";
                }
                $error_msg .= "{$time_remaining_minutes} minute(s) before placing another.";

                $_SESSION['msg'] = $error_msg;
                $_SESSION['msg_type'] = "danger";
                $_SESSION['show_booking_error_modal'] = true; // Re-use the booking error modal
                header("Location: checkout.php");
                exit;
            }
        } catch (Exception $e) {
            // Error parsing date? Log it but allow proceeding.
            error_log("Error parsing last order time for user $user_id: " . $e->getMessage());
        }
    }
    $check_cooldown_stmt->close();
    // --- END NEW COOLDOWN VALIDATION ---


    // --- Collect and Validate Basic Data ---
    $date_event = $_POST['date_event'] ?? '';
    $start_time_str = $_POST['start_time'] ?? '';
    $end_time_str = $_POST['end_time'] ?? '';
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $type_event = isset($_POST['type_event']) ? implode(", ", $_POST['type_event']) : '';
    $location_checklist = isset($_POST['location_checklist']) ? implode(", ", $_POST['location_checklist']) : '';
    $notes = trim($_POST['notes'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $installment_plan = ($payment_method === 'Installment') ? trim($_POST['installment_plan']) : '';
    $terms_agreed = isset($_POST['terms_agree']);

    // --- 2. 4-HOUR BUFFER VALIDATION (Service-Specific) --- (Was #1)
    try {
        $user_start_dt = new DateTime($start_time_str);
        $user_end_dt = new DateTime($end_time_str);
        // --- THIS IS THE FIX ---
        // Add +1 day if the end time is earlier than the start time (e.g., 10 PM to 2 AM)
        if ($user_end_dt < $user_start_dt) {
            $user_end_dt->modify('+1 day');
        }
        // --- END FIX ---
    } catch (Exception $e) {
        // Invalid time format, will be caught by later validation anyway
        // We can safely continue as the $user_start_dt will be null
    }

    // Define services that need a buffer and the buffer time in hours
    $services_with_buffer = [
        'Premium Mechanical Bull QLD' => 4,
        // 'Another Service' => 2  // You can add more services here
    ];

    foreach ($cart_items as $item) {
        $service_name = $item['service_name'];
        if (array_key_exists($service_name, $services_with_buffer)) {

            $buffer_hours = $services_with_buffer[$service_name];

            $conflict_stmt = $conn->prepare("
                SELECT o.start_time, o.end_time 
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN services s ON oi.service_id = s.id
                WHERE o.date_event = ? 
                  AND s.service_name = ? 
                  AND o.booking_status NOT IN ('Cancelled', 'Refunded', 'Pending Cancellation')
            ");
            $conflict_stmt->bind_param("ss", $date_event, $service_name);
            $conflict_stmt->execute();
            $existing_bookings = $conflict_stmt->get_result();

            while ($existing_booking = $existing_bookings->fetch_assoc()) {
                try {
                    $existing_start_dt = new DateTime($existing_booking['start_time']);
                    $existing_end_dt = new DateTime($existing_booking['end_time']);
                    if ($existing_end_dt < $existing_start_dt) {
                        $existing_end_dt->modify('+1 day');
                    }

                    // This is the critical part: add buffer to the existing booking's end time
                    $existing_buffer_end_dt = (clone $existing_end_dt)->modify("+$buffer_hours hours");

                    // Check for overlap: (UserStart < ExistingBufferEnd) AND (UserEnd > ExistingStart)
                    if ($user_start_dt < $existing_buffer_end_dt && $user_end_dt > $existing_start_dt) {

                        $conflict_msg = "Booking Conflict: The '{$service_name}' is already booked at a time that conflicts with your request. " .
                            "It requires a {$buffer_hours}-hour buffer after its use. It is busy from " .
                            $existing_start_dt->format('g:i A') . " until " . $existing_buffer_end_dt->format('g:i A') .
                            " (including buffer). Please choose a different time.";

                        $_SESSION['msg'] = $conflict_msg;
                        $_SESSION['msg_type'] = "danger";
                        $_SESSION['show_booking_error_modal'] = true;
                        header("Location: checkout.php");
                        exit;
                    }
                } catch (Exception $e) {
                    // Skip this booking if its times are invalid
                }
            }
            $conflict_stmt->close();
        }
    }
    // --- END SERVICE BUFFER VALIDATION ---


    // --- 3. GLOBAL BOOKING SLOT VALIDATION (was #2) ---
    $GLOBAL_LIMIT_BACKEND = 5;

    $check_stmt = $conn->prepare("
        SELECT COUNT(id) as count 
        FROM orders 
        WHERE DATE(date_event) = ? AND booking_status != 'Cancelled'
    ");
    $check_stmt->bind_param("s", $date_event);
    $check_stmt->execute();
    $count_result = $check_stmt->get_result()->fetch_assoc();
    $current_bookings = $count_result['count'];
    $check_stmt->close();

    if ($current_bookings >= $GLOBAL_LIMIT_BACKEND) {
        $date_formatted = date("F j, Y", strtotime($date_event));
        $_SESSION['msg'] = "Sorry, we are fully booked for {$date_formatted}. Please choose another date.";
        $_SESSION['msg_type'] = "danger";
        $_SESSION['show_booking_error_modal'] = true;
        header("Location: checkout.php");
        exit;
    }
    // --- END GLOBAL VALIDATION ---

    // --- 4. Calculate Duration and Discount (was #3) ---
    $discount_percent = 0;
    $discount_amount = 0;
    $duration_hours = 0;
    try {
        if (!empty($start_time_str) && !empty($end_time_str)) {
            // We already created these DateTime objects above
            // $start_dt = new DateTime($start_time_str); 
            // $end_dt = new DateTime($end_time_str);
            // if ($end_dt < $start_dt) { $end_dt->modify('+1 day'); }

            if ($user_start_dt != $user_end_dt) {
                $interval = $user_start_dt->diff($user_end_dt);
                $duration_hours = $interval->h + ($interval->days * 24) + ($interval->i / 60);
            }
            if ($duration_hours > 3) {
                $discount_percent = 0.10;
            } elseif ($duration_hours >= 1) {
                $discount_percent = 0.05;
            }

            $discount_amount = round($subtotal * $discount_percent, 2);
        }
    } catch (Exception $e) {
        $discount_amount = 0;
        $discount_percent = 0;
    }
    $discounted_subtotal = $subtotal - $discount_amount;

    // --- 5. Calculate Delivery Fee (was #4) ---

    $predefinedLocations = [
        // --- 5 Addresses < 40km (Free Delivery) ---
        "Queen St, Brisbane City QLD 4000, Australia" => ['distance' => 22.0, 'fee' => 0.00],
        "322 Moggill Rd, Indooroopilly QLD 4068, Australia" => ['distance' => 13.0, 'fee' => 0.00],
        "Cnr Mains Rd &, McCullough St, Sunnybank QLD 4109, Australia" => ['distance' => 18.0, 'fee' => 0.00],
        "Ipswich QLD 4305, Australia" => ['distance' => 18.0, 'fee' => 0.00],
        "Forest Lake QLD 4078, Australia" => ['distance' => 7.0, 'fee' => 0.00],

        // --- 5 Addresses > 40km (Fee Applies = distance * 2) ---
        "Surfers Paradise, Gold Coast QLD 4217, Australia" => ['distance' => 80.0, 'fee' => 160.00], // 80 * 2
        "Mooloolaba, Sunshine Coast QLD 4557, Australia" => ['distance' => 120.0, 'fee' => 240.00], // 120 * 2
        "Toowoomba QLD 4350, Australia" => ['distance' => 115.0, 'fee' => 230.00], // 115 * 2
        "Caboolture QLD 4510, Australia" => ['distance' => 70.0, 'fee' => 140.00], // 70 * 2
        "Redcliffe Jetty, Redcliffe QLD 4020, Australia" => ['distance' => 55.0, 'fee' => 110.00]  // 55 * 2
    ];

    $delivery_fee = 0.00; // Default to 0

    if (isset($predefinedLocations[$address])) {
        $delivery_fee = $predefinedLocations[$address]['fee'];
    }
    // --- END REVISED DELIVERY CALCULATION ---


    // --- 6. Determine Final Total, Payment Status based on Method (was #5) ---
    $final_total = $discounted_subtotal + $delivery_fee; // Base total
    $paid_date = NULL;
    $payment_status = 'Pending';
    $deposit_status = 'Pending';
    $deposit_amount = 0;
    $remaining_balance = $final_total;
    $next_due_date = NULL;
    $interest_rate = 0;
    $interest_amount = 0;

    // --- NEW: Set Booking Status ---
    // All new bookings must be confirmed by an admin
    $booking_status = 'Pending Confirmation';

    switch ($payment_method) {
        case 'Card':
            // Card payment is still "Paid" financially, but booking needs confirmation
            $payment_status = 'Paid';
            $deposit_status = 'Paid';
            $deposit_amount = $final_total;
            $remaining_balance = 0;
            $paid_date = date("Y-m-d H:i:s");
            break;
        case 'Installment':
            if (!empty($installment_plan)) {
                $payment_status = 'Pending';
                $deposit_status = 'Pending';
                switch ($installment_plan) {
                    case '1':
                        $interest_rate = 0.05;
                        break;
                    case '2':
                        $interest_rate = 0.10;
                        break;
                    case '3':
                        $interest_rate = 0.15;
                        break;
                }
                $interest_amount = round($discounted_subtotal * $interest_rate, 2);
                $final_total = $discounted_subtotal + $interest_amount + $delivery_fee;
                $remaining_balance = $final_total;
                $next_due_date = date('Y-m-d', strtotime('+1 month'));
            }
            break;
        case 'Cash':
            $payment_status = 'Pending';
            $deposit_status = 'Pending';
            $remaining_balance = $final_total;
            break;
    }

    // --- 7. Handle Card Details (was #6) ---
    $card_type = '';
    $masked_card = '';
    $raw_card_expiry = '';
    if ($payment_method === 'Card' && isset($_POST['card_number'])) {
        $raw_card_number = $_POST['card_number'];
        $digits = preg_replace('/\D/', '', $raw_card_number);
        if (preg_match('/^4/', $digits)) $card_type = 'Visa';
        elseif (preg_match('/^5[1-5]/', $digits)) $card_type = 'Mastercard';
        elseif (preg_match('/^3[47]/', $digits)) $card_type = 'Amex';
        else $card_type = 'Other';
        $last4 = ($digits !== '') ? substr($digits, -4) : '';
        $masked_card = $last4 ? '**** **** **** ' . $last4 : '';
        $raw_card_expiry = $_POST['card_expiry'] ?? '';
    }

    // --- 8. Insert Order into Database (was #7) ---

    // --- MODIFIED: Added created_at column and NOW() value ---
    $stmt = $conn->prepare("INSERT INTO orders 
        (user_id, contact_number, address, type_event, location_checklist, start_time, end_time, date_event, notes, payment_method, installment_plan, card_type, card_number, card_expiry, total_amount, deposit_amount, deposit_status, remaining_balance, next_due_date, payment_status, paid_date, discount_amount, discount_percent, delivery_fee, booking_status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    // This is the ORIGINAL, CORRECT bind_param string (25 letters for 25 variables)
    $stmt->bind_param(
        "isssssssssssssddsdsssddds",
        $user_id,
        $contact_number,
        $address,
        $type_event,
        $location_checklist,
        $start_time_str,
        $end_time_str,
        $date_event,
        $notes,
        $payment_method,
        $installment_plan,
        $card_type,
        $masked_card,
        $raw_card_expiry,
        $final_total,
        $deposit_amount,
        $deposit_status,
        $remaining_balance,
        $next_due_date,
        $payment_status,
        $paid_date,
        $discount_amount,
        $discount_percent,
        $delivery_fee,
        $booking_status
    );

    if ($stmt->execute()) {
        $order_id = $stmt->insert_id;
        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, service_id, quantity, price_per_item) VALUES (?, ?, ?, ?)");
        foreach ($cart_items as $item) {
            $item_stmt->bind_param("iiid", $order_id, $item['service_id'], $item['quantity'], $item['price']);
            $item_stmt->execute();
        }
        $item_stmt->close();

        $clear_cart_stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $clear_cart_stmt->bind_param("i", $user_id);
        $clear_cart_stmt->execute();
        $clear_cart_stmt->close();

        // Prepare details for email
        $order_details = [
            'full_name' => $full_name,
            'email' => $user['email'],
            'contact_number' => $contact_number,
            'address' => $address,
            'date_event' => $date_event,
            'start_time' => $start_time_str,
            'end_time' => $end_time_str,
            'type_event' => $type_event,
            'location_checklist' => $location_checklist,
            'notes' => $notes,
            'payment_method' => $payment_method,
            'installment_plan' => $installment_plan,
            'total_amount' => $final_total,
            'payment_status' => $payment_status,
            'interest_rate' => $interest_rate * 100,
            'card_type' => $card_type,
            'card_number' => $masked_card,
            'next_due_date' => $next_due_date,
            'subtotal' => $subtotal,
            'discount_amount' => $discount_amount,
            'discount_percent' => $discount_percent * 100,
            'delivery_fee' => $delivery_fee
        ];

        // Send Email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'alex1925tan@gmail.com';
            $mail->Password   = 'REDACTED_SMTP_PASSWORD';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('alex1925tan@gmail.com', 'BigFun');
            $mail->addAddress($user['email'], $full_name);
            $mail->isHTML(true);

            // --- MODIFIED: Subject now says "Pending Confirmation" ---
            $mail->Subject = 'Your BigFun Order is Awaiting Confirmation (Order #' . $order_id . ')';
            $mail->Body    = generateOrderEmailBody($order_id, $user, $cart_items, $order_details);
            $mail->send();
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
        }

        // --- ADDED: Create Notification for User ---
        create_notification(
            $conn,
            $user_id,
            "Booking Received (Order #{$order_id})",
            "Your booking for {$date_event} is awaiting confirmation from an admin. We'll notify you once it's confirmed.",
            "booking-status.php"
        );
        // --- END NOTIFICATION ---

        // ** SET SUCCESS FLAGS **
        $_SESSION['order_success'] = true;
        $_SESSION['order_payment_method'] = $payment_method;
        header("Location: checkout.php");
        exit;
    } else {
        $_SESSION['msg'] = "Error placing order: " . htmlspecialchars($stmt->error);
        $_SESSION['msg_type'] = "danger";
        header("Location: checkout.php");
        exit;
    }
}
$msg = $_SESSION['msg'] ?? null;
$type = $_SESSION['msg_type'] ?? null;
unset($_SESSION['msg'], $_SESSION['msg_type']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Checkout - BigFun</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        :root {
            --primary-color: #802080;
            --primary-light: #FFEFFC;
            --section-bg: #ffffff;
            --section-border: #e0e0e0;
        }

        body {
            background: var(--primary-light);
            font-family: 'Inter', sans-serif;
        }

        .checkout-section {
            background-color: var(--section-bg);
            border: 1px solid var(--section-border);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .checkout-section-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--section-border);
            background-color: #f8f9fa;
            font-weight: 700;
            font-size: 1.25rem;
            color: #333;
        }

        .checkout-section-body {
            padding: 1.5rem;
        }

        .payment-option {
            border: 2px solid var(--section-border);
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .payment-option:last-child {
            margin-bottom: 0;
        }

        .payment-option input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ccc;
            border-radius: 50%;
            margin: 0;
            transition: all 0.1s;
            position: relative;
        }

        .payment-option input[type="radio"]:checked {
            border-color: var(--primary-color);
            background-color: var(--primary-color);
        }

        .payment-option input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: white;
        }

        .payment-option:has(input:checked) {
            border-color: var(--primary-color);
            background-color: var(--primary-light);
            box-shadow: 0 0 0 2px var(--primary-color);
        }

        .payment-details {
            padding: 1.5rem;
            border: 1px solid var(--section-border);
            border-radius: 8px;
            margin-left: 2.5rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
        }

        .order-summary-card {
            position: sticky;
            top: 20px;
            background-color: var(--section-bg);
            border: 1px solid var(--section-border);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 1.5rem;
        }

        .order-summary-card h4 {
            border-bottom: 1px solid var(--section-border);
            padding-bottom: 0.75rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        #summary-items-list .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
        }

        #summary-items-list .summary-item span:first-child {
            color: #555;
            max-width: 70%;
            word-wrap: break-word;
        }

        #summary-items-list .summary-item span:last-child {
            font-weight: 500;
            text-align: right;
        }

        .summary-item-discount span:first-child,
        .summary-item-discount span:last-child {
            color: #198754;
        }

        .summary-item-interest,
        .summary-item-delivery {
            color: #6c757d;
        }

        .summary-grand-total {
            border-top: 2px solid #333;
            margin-top: 1rem;
            padding-top: 1rem;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .delivery-fee-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            font-size: 0.9em;
        }

        #delivery-fee-spinner {
            display: none;
        }

        .btn-place-order:disabled {
            cursor: not-allowed;
            opacity: 0.65;
        }

        .card-number-wrapper {
            position: relative;
        }

        .card-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
        }

        #errorList {
            text-align: left;
            padding-left: 20px;
        }

        #errorList li {
            margin-bottom: 8px;
        }

        /* Calendar Styles */
        .flatpickr-day.fully-booked {
            text-decoration: none !important;
            background-color: #f8d7da !important;
            color: #842029 !important;
            cursor: not-allowed !important;
            border-color: #f5c2c7 !important;
        }

        .day-slots {
            font-size: 0.65rem;
            font-weight: bold;
            display: block;
            text-align: center;
            position: absolute;
            bottom: 4px;
            left: 0;
            right: 0;
            pointer-events: none;
        }

        .day-slots-full {
            color: #dc3545;
        }

        .day-slots-available {
            color: #198754;
        }

        #date-feedback {
            font-weight: bold;
            margin-top: 8px;
            min-height: 20px;
        }
    </style>
</head>

<body>

    <datalist id="real-address-list">
        <option value="Queen St, Brisbane City QLD 4000, Australia">
        <option value="322 Moggill Rd, Indooroopilly QLD 4068, Australia">
        <option value="Cnr Mains Rd &, McCullough St, Sunnybank QLD 4109, Australia">
        <option value="Ipswich QLD 4305, Australia">
        <option value="Forest Lake QLD 4078, Australia">
        <option value="Surfers Paradise, Gold Coast QLD 4217, Australia">
        <option value="Mooloolaba, Sunshine Coast QLD 4557, Australia">
        <option value="Toowoomba QLD 4350, Australia">
        <option value="Caboolture QLD 4510, Australia">
        <option value="Redcliffe Jetty, Redcliffe QLD 4020, Australia">
    </datalist>
    <div class="container py-5">
        <div class="text-center mb-4">
            <a href="book-dashboard.php"><img src="assets/images/bgfunlogo.png" alt="BigFun Logo" style="max-height: 60px;"></a>
            <h1 class="mt-3" style="font-weight: 700; color: var(--primary-color);">Checkout</h1>
        </div>

        <?php if ($msg && !$show_booking_error_modal): ?>
            <div class="alert alert-<?php echo htmlspecialchars($type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm" novalidate>
            <div class="row g-5">
                <div class="col-lg-7">
                    <div class="checkout-section">
                        <div class="checkout-section-header">1. Customer Information</div>
                        <div class="checkout-section-body">
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label">Full Name</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly></div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Delivery Address</label>
                                    <input type="text" name="address" id="deliveryAddress" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required list="real-address-list" placeholder="Start typing an address...">
                                    <small class="text-muted">Select a predefined address from the list or type your own.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="checkout-section">
                        <div class="checkout-section-header">2. Event Details</div>
                        <div class="checkout-section-body">
                            <h6>Type of Event</h6>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="type_event[]" value="Private Event"><label class="form-check-label ms-2">Private Event</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="type_event[]" value="Corporate Event"><label class="form-check-label ms-2">Corporate Event</label></div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="type_event[]" value="Community/School"><label class="form-check-label ms-2">Community/School</label></div>

                            <h6 class="mt-3">Location Checklist</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="location_checklist[]" value="warehouse to on-site" id="location-warehouse">
                                <label class="form-check-label ms-2" for="location-warehouse">Warehouse to On-site</label>
                            </div>
                            <h6 class="mt-3">Event Schedule</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Date</label>
                                    <input type="text" id="datePicker" name="date_event" class="form-control" placeholder="Select a date..." required readonly>
                                    <div id="date-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" name="start_time" id="startTime" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">End Time</label>
                                    <input type="time" name="end_time" id="endTime" class="form-control" required>
                                </div>
                            </div>
                            <div class="mt-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3" placeholder="Any special instructions..."></textarea></div>
                        </div>
                    </div>

                    <div class="checkout-section">
                        <div class="checkout-section-header">3. Payment Method</div>
                        <div class="checkout-section-body">
                            <label class="payment-option" for="pay-card">
                                <input type="radio" name="payment_method" value="Card" class="form-check-input" id="pay-card">
                                <i class="fa-regular fa-credit-card"></i> Pay with Card (Full Amount)
                            </label>
                            <div id="cardFields" class="payment-details" style="display:none;">
                                <div class="card-number-wrapper mb-2">
                                    <input type="text" name="card_number" class="form-control" placeholder="Card Number" id="cardNumber" maxlength="19" required>
                                    <span class="card-icon" id="cardIcon"></span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="text" name="card_expiry" class="form-control" placeholder="MM / YY" id="cardExpiry" maxlength="7" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="card_cvv" class="form-control" placeholder="CVV" id="cardCvv" maxlength="4" required>
                                    </div>
                                </div>
                            </div>

                            <label class="payment-option" for="pay-install">
                                <input type="radio" name="payment_method" value="Installment" class="form-check-input" id="pay-install">
                                <i class="fa-solid fa-calendar-days"></i> Pay via Installment
                            </label>
                            <div id="installmentFields" class="payment-details" style="display:none;">
                                <label class="form-label">Select Plan</label>
                                <select class="form-select" name="installment_plan" id="installmentPlan" required>
                                    <option value="">Choose a plan...</option>
                                    <option value="1">1 Month (5% interest)</option>
                                    <option value="2">2 Months (10% interest)</option>
                                    <option value="3">3 Months (15% interest)</option>
                                </select>
                                <div id="installmentAmount" class="mt-2 text-muted small"></div>
                            </div>

                            <label class="payment-option" for="pay-cash">
                                <input type="radio" name="payment_method" value="Cash" class="form-check-input" id="pay-cash">
                                <i class="fa-solid fa-money-bill-wave"></i> Pay with Cash on Delivery
                            </label>
                        </div>
                    </div>

                    <div class="checkout-section checkout-section-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="termsAgree" name="terms_agree" required>
                            <label class="form-check-label ms-2" for="termsAgree">
                                I acknowledge that I have read, understood, and agree to the
                                <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" style="color: var(--primary-color);">Terms and Conditions</a>.
                            </label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="cart.php" class="btn btn-outline-secondary">&laquo; Back to Cart</a>
                        <button type="submit" class="btn btn-success btn-lg btn-place-order px-5">Place Order</button>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card order-summary-card">
                        <h4>Order Summary</h4>

                        <div id="cart-items-list">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="summary-item" style="display: flex; justify-content: space-between; padding: 0.3rem 0; font-weight: 600;">
                                    <span><?php echo htmlspecialchars($item['service_name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                                    <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr style="margin: 1rem 0;">

                        <div id="summary-breakdown-container">
                            <div id="summary-items-list">
                            </div>

                            <div class="d-flex justify-content-between summary-grand-total">
                                <span>Grand Total</span>
                                <span id="summary-grand-total-value">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                        </div>
                        <div class="delivery-fee-section" id="delivery-fee-section">
                            <p class="mb-1 fw-bold">Delivery Fee Calculation:</p>
                            <div id="delivery-fee-status" class="text-muted">
                                Enter a predefined address to see the fee.
                            </div>
                            <div class="spinner-border spinner-border-sm text-primary" role="status" id="delivery-fee-spinner">
                                <span class="visually-hidden">Calculating...</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4" style="border-radius: 15px; border:none;">
                <div class="modal-body">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h3 class="mt-3" style="font-family: 'Inria Sans', sans-serif;">Order Received!</h3>

                    <?php if ($order_payment_method === 'Installment'): ?>
                        <p class="text-muted">Your booking is awaiting admin confirmation. Your first payment is due in one month. Check "Invoices & Payments".</p>
                    <?php elseif ($order_payment_method === 'Cash'): ?>
                        <p class="text-muted">Thank you! Your booking is awaiting admin confirmation. We'll contact you to arrange cash payment.</p>
                    <?php else: ?>
                        <p class="text-muted">Thank you! Your payment was successful. Your booking is now awaiting admin confirmation.</p>
                    <?php endif; ?>

                    <a href="book-dashboard.php" class="btn mt-2" style="background-color: var(--primary-color); color: white;">Continue Shopping</a>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Missing Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please correct the following issues:</p>
                    <ul id="errorList"></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="bookingErrorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class_name="modal-title text-danger"><i class_name="fas fa-calendar-times me-2"></i>Booking Slot Unavailable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="bookingErrorMessage"></p>
                </div>
                <div class="modal-footer">
                    <a href="cart.php" class="btn btn-warning">Edit Cart</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Choose Another Date</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Booking Confirmation</h6>
                    <p>Bookings subject to availability & payment/confirmation. We reserve right to decline/cancel.</p>
                    <h6>2. Payment</h6>
                    <p>Full (Card), scheduled (Installment), or upon delivery (Cash). Late payments may cause cancellation.</p>
                    <h6>3. Cancellations and Refunds</h6>
                    <p>Partial refund >7 days before event (fee applies). Non-refundable within 7 days. See full policy.</p>
                    <h6>4. Equipment Use and Safety</h6>
                    <p>Ensure safe environment. Follow staff instructions. Misuse/ignoring guidelines voids liability.</p>
                    <h6>5. Delivery and Setup</h6>
                    <p>Times approximate. Extra charges for difficult access. Ensure area meets requirements.</p>
                    <p><strong>By checking out, you agree to these terms.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- STATE & CONSTANTS ---
            const interestRates = {
                '1': 0.05,
                '2': 0.10,
                '3': 0.15
            };
            const GLOBAL_ORDER_LIMIT = 5;
            const jsCartItems = <?php echo json_encode($cart_items); ?>;

            // --- REVISED DELIVERY CONSTANTS ---
            // This JS object MUST match the PHP array exactly.
            const predefinedLocations = {
                // --- 5 Addresses < 40km (Free Delivery) ---
                "Queen St, Brisbane City QLD 4000, Australia": {
                    distance: 22.0,
                    fee: 0.00
                },
                "322 Moggill Rd, Indooroopilly QLD 4068, Australia": {
                    distance: 13.0,
                    fee: 0.00
                },
                "Cnr Mains Rd &, McCullough St, Sunnybank QLD 4109, Australia": {
                    distance: 18.0,
                    fee: 0.00
                },
                "Ipswich QLD 4305, Australia": {
                    distance: 18.0,
                    fee: 0.00
                },
                "Forest Lake QLD 4078, Australia": {
                    distance: 7.0,
                    fee: 0.00
                },

                // --- 5 Addresses > 40km (Fee Applies = distance * 2) ---
                "Surfers Paradise, Gold Coast QLD 4217, Australia": {
                    distance: 80.0,
                    fee: 160.00
                },
                "Mooloolaba, Sunshine Coast QLD 4557, Australia": {
                    distance: 120.0,
                    fee: 240.00
                },
                "Toowoomba QLD 4350, Australia": {
                    distance: 115.0,
                    fee: 230.00
                },
                "Caboolture QLD 4510, Australia": {
                    distance: 70.0,
                    fee: 140.00
                },
                "Redcliffe Jetty, Redcliffe QLD 4020, Australia": {
                    distance: 55.0,
                    fee: 110.00
                }
            };
            // --- END NEW CONSTANTS ---

            let orderState = {
                subtotal: <?php echo $subtotal; ?>,
                discountPercent: 0,
                discountAmount: 0,
                interestRate: 0,
                interestAmount: 0,
                deliveryFee: 0,
                grandTotal: <?php echo $subtotal; ?>
            };

            // --- DOM ELEMENT REFERENCES ---
            const deliveryAddressInput = document.getElementById('deliveryAddress');
            const deliveryFeeStatusDiv = document.getElementById('delivery-fee-status');
            const deliveryFeeSpinner = document.getElementById('delivery-fee-spinner');
            const checkoutForm = document.getElementById('checkoutForm');
            const startTimeInput = document.getElementById('startTime');
            const endTimeInput = document.getElementById('endTime');
            const datePickerInput = document.getElementById('datePicker');
            const dateFeedback = document.getElementById('date-feedback');
            const installmentPlanSelect = document.getElementById('installmentPlan');
            const installmentAmountDiv = document.getElementById('installmentAmount');
            const cardFieldsDiv = document.getElementById('cardFields');
            const installmentFieldsDiv = document.getElementById('installmentFields');
            const cardNumberInput = document.getElementById('cardNumber');
            const cardExpiryInput = document.getElementById('cardExpiry');
            const cardCvvInput = document.getElementById('cardCvv');
            const cardIcon = document.getElementById('cardIcon');
            const termsAgreeCheckbox = document.getElementById('termsAgree');
            const placeOrderButton = checkoutForm.querySelector('.btn-place-order');
            const summaryBreakdownList = document.getElementById('summary-items-list');
            const summaryGrandTotalValue = document.getElementById('summary-grand-total-value');

            // --- MODAL TRIGGERS (FIXED) ---
            const successModal = document.getElementById('successModal') ? new bootstrap.Modal(document.getElementById('successModal')) : null;
            const bookingErrorModal = document.getElementById('bookingErrorModal') ? new bootstrap.Modal(document.getElementById('bookingErrorModal')) : null;
            const errorModal = document.getElementById('errorModal') ? new bootstrap.Modal(document.getElementById('errorModal')) : null;

            <?php if ($show_success_modal): ?>
                if (successModal) {
                    successModal.show();
                }
            <?php endif; ?>

            <?php if ($show_booking_error_modal): ?>
                if (bookingErrorModal && document.getElementById('bookingErrorMessage')) {
                    document.getElementById('bookingErrorMessage').textContent = <?php echo json_encode($booking_error_msg); ?>;
                    bookingErrorModal.show();
                }
            <?php endif; ?>


            // --- HELPER FUNCTIONS ---
            function roundToTwo(num) {
                return Math.round(num * 100) / 100;
            }

            function formatCurrency(num) {
                return '$' + num.toFixed(2);
            }

            function formatPercent(num) {
                return (num * 100).toFixed(0);
            }


            // --- CORE LOGIC (Summary & Delivery) ---
            function recalculateAndRender() {
                const startTimeStr = startTimeInput ? startTimeInput.value : null;
                const endTimeStr = endTimeInput ? endTimeInput.value : null;
                const selectedPaymentMethodRadio = document.querySelector('input[name="payment_method"]:checked');
                const selectedMethod = selectedPaymentMethodRadio ? selectedPaymentMethodRadio.value : null;
                const installmentPlan = installmentPlanSelect ? installmentPlanSelect.value : null;

                orderState.discountPercent = 0;
                orderState.discountAmount = 0;
                orderState.interestRate = 0;
                orderState.interestAmount = 0;

                let discountedSubtotal = orderState.subtotal;
                if (startTimeStr && endTimeStr) {
                    try {
                        const startDate = new Date(`1970-01-01T${startTimeStr}:00`);
                        const endDate = new Date(`1970-01-01T${endTimeStr}:00`);
                        let durationHours = 0;
                        if (startDate.getTime() !== endDate.getTime()) {
                            if (endDate < startDate) {
                                endDate.setDate(endDate.getDate() + 1);
                            }
                            const durationMinutes = (endDate - startDate) / (1000 * 60);
                            durationHours = durationMinutes / 60;
                        }
                        if (durationHours > 3) {
                            orderState.discountPercent = 0.10;
                        } else if (durationHours >= 1) {
                            orderState.discountPercent = 0.05;
                        }
                        orderState.discountAmount = roundToTwo(orderState.subtotal * orderState.discountPercent);
                        discountedSubtotal = orderState.subtotal - orderState.discountAmount;
                    } catch (e) {}
                }

                if (selectedMethod === 'Installment' && installmentPlan && interestRates[installmentPlan]) {
                    orderState.interestRate = interestRates[installmentPlan];
                    orderState.interestAmount = roundToTwo(discountedSubtotal * orderState.interestRate);
                }

                orderState.grandTotal = roundToTwo(discountedSubtotal + orderState.interestAmount + orderState.deliveryFee);
                renderSummary();
            }

            function renderSummary() {
                if (!summaryBreakdownList || !summaryGrandTotalValue) return;
                let html = '';
                html += `<div class="summary-item"><span>Subtotal</span><span>${formatCurrency(orderState.subtotal)}</span></div>`;
                if (orderState.discountAmount > 0) {
                    html += `<div class="summary-item summary-item-discount"><span>Duration Discount (${formatPercent(orderState.discountPercent)}%)</span><span>-${formatCurrency(orderState.discountAmount)}</span></div>`;
                }
                if (orderState.interestAmount > 0) {
                    html += `<div class="summary-item summary-item-interest"><span>Installment Interest (${formatPercent(orderState.interestRate)}%)</span><span>+${formatCurrency(orderState.interestAmount)}</span></div>`;
                }
                if (orderState.deliveryFee > 0) {
                    html += `<div class="summary-item summary-item-delivery"><span>Delivery Fee</span><span>+${formatCurrency(orderState.deliveryFee)}</span></div>`;
                }
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                if (paymentMethod) {
                    html += `<div class="summary-item"><span>Payment Method</span><span>${paymentMethod.value}</span></div>`;
                }
                summaryBreakdownList.innerHTML = html;
                summaryGrandTotalValue.textContent = formatCurrency(orderState.grandTotal);
                if (paymentMethod && paymentMethod.value === 'Installment') {
                    updateInstallmentDisplayText();
                }
            }

            // --- UPDATED `calculateDistanceAndFee` FUNCTION (REVISED PREDICTABLE LOGIC) ---
            function calculateDistanceAndFee() {
                if (!deliveryAddressInput) return;
                const destinationAddress = deliveryAddressInput.value.trim();

                // Hide spinner, this logic is instant
                if (deliveryFeeSpinner) deliveryFeeSpinner.style.display = 'none';

                let fee = 0.00;
                let statusText = "";

                // --- NEW LOGIC ---
                if (predefinedLocations.hasOwnProperty(destinationAddress)) {
                    // Address is in our predefined list
                    const locationData = predefinedLocations[destinationAddress];
                    fee = locationData.fee;

                    if (fee > 0) {
                        statusText = `Address matched (${locationData.distance} km). Fee: ${formatCurrency(fee)}`;
                    } else {
                        statusText = `Address matched (${locationData.distance} km). Free Delivery.`;
                    }
                } else if (destinationAddress === "") {
                    // Address field is empty
                    statusText = 'Select a predefined address from the list or type your own.';
                } else {
                    // Address is not empty, but not in our list
                    statusText = "Custom address. Defaulting to free delivery.";
                }
                // --- END NEW LOGIC ---

                // Update the state and UI instantly.
                orderState.deliveryFee = fee;
                if (deliveryFeeStatusDiv) deliveryFeeStatusDiv.textContent = statusText;
                recalculateAndRender();
            }

            function updateInstallmentDisplayText() {
                if (!installmentPlanSelect || !installmentAmountDiv) return;
                const months = installmentPlanSelect.value;
                const discountedSubtotal = orderState.subtotal - orderState.discountAmount;
                if (months && interestRates[months]) {
                    const interest = roundToTwo(discountedSubtotal * interestRates[months]);
                    const totalWithFeeAndInterest = discountedSubtotal + interest + orderState.deliveryFee;
                    const perMonth = totalWithFeeAndInterest / parseInt(months);
                    installmentAmountDiv.innerHTML = `Approx. <b>${formatCurrency(perMonth)}</b> / month for ${months} month(s).<br>Total interest: ${formatCurrency(interest)}`;
                } else {
                    installmentAmountDiv.innerHTML = "Select a plan to see monthly estimate.";
                }
            }

            // --- CALENDAR LOGIC (Unchanged) ---
            let orderCounts = {}; // Stores { "YYYY-MM-DD": {"order_count": X} }
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            function cal_formatDate(date) {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            async function fetchOrderCounts(month, year, instance) {
                try {
                    const response = await fetch(`backend/get_order_counts.php?month=${month}&year=${year}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const contentType = response.headers.get("content-type");

                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        const newCounts = await response.json();
                        if (newCounts.error) {
                            throw new Error(newCounts.error);
                        }
                        orderCounts = {
                            ...orderCounts,
                            ...newCounts
                        };
                    } else {
                        const textResponse = await response.text();
                        throw new Error("Received non-JSON response from get_order_counts: " + textResponse);
                    }

                    const disabledDates = Object.keys(orderCounts).filter(date => {
                        return orderCounts[date].order_count >= GLOBAL_ORDER_LIMIT;
                    });
                    instance.set('disable', disabledDates);

                    instance.redraw();
                    if (instance.selectedDates.length > 0) {
                        updateDateFeedback(instance.selectedDates[0], instance);
                    }
                } catch (error) {
                    console.error("Failed to fetch order counts:", error);
                    if (dateFeedback) {
                        dateFeedback.textContent = 'Error loading availability.';
                        dateFeedback.className = 'text-warning mt-2 fw-bold';
                    }
                }
            }

            function updateDateFeedback(selectedDate, instance) {
                if (!selectedDate || !dateFeedback) {
                    if (dateFeedback) dateFeedback.textContent = '';
                    return;
                }

                const dateStr = cal_formatDate(selectedDate);
                const selectedDayToCompare = new Date(selectedDate);
                selectedDayToCompare.setHours(0, 0, 0, 0);

                if (selectedDayToCompare < today) {
                    dateFeedback.textContent = 'Past date selected.';
                    dateFeedback.className = 'text-danger mt-2 fw-bold';
                    return;
                }

                const dayData = orderCounts[dateStr] || {
                    order_count: 0
                };
                const currentCount = parseInt(dayData.order_count, 10);
                const remaining = GLOBAL_ORDER_LIMIT - currentCount;

                if (remaining <= 0) {
                    dateFeedback.textContent = `Sorry, this date is fully booked.`;
                    dateFeedback.className = 'text-danger mt-2 fw-bold';
                } else {
                    dateFeedback.textContent = `Available! ${remaining} slot(s) left.`;
                    dateFeedback.className = 'text-success mt-2 fw-bold';
                }
            }

            if (datePickerInput) {
                flatpickr("#datePicker", {
                    dateFormat: "Y-m-d",
                    minDate: "today",
                    onReady: (d, s, i) => fetchOrderCounts(i.currentMonth + 1, i.currentYear, i),
                    onMonthChange: (d, s, i) => fetchOrderCounts(i.currentMonth + 1, i.currentYear, i),
                    onDayCreate: (dObj, dStr, fp, dayElem) => {
                        dayElem.querySelectorAll('.day-slots').forEach(el => el.remove());
                        const dateObj = dayElem.dateObj;
                        const dateStrKey = cal_formatDate(dateObj);
                        const dayData = orderCounts[dateStrKey] || {
                            order_count: 0
                        };
                        const count = parseInt(dayData.order_count, 10);
                        const dayToCompare = new Date(dateObj);
                        dayToCompare.setHours(0, 0, 0, 0);

                        if (dayToCompare < today) return;

                        if (count > 0) {
                            const slotSpan = document.createElement('span');
                            slotSpan.className = 'day-slots';
                            if (count >= GLOBAL_ORDER_LIMIT) {
                                dayElem.classList.add('fully-booked');
                                slotSpan.classList.add('day-slots-full');
                                slotSpan.textContent = 'Full';
                            } else {
                                const remaining = GLOBAL_ORDER_LIMIT - count;
                                slotSpan.classList.add('day-slots-available');
                                slotSpan.textContent = `${remaining} left`;
                            }
                            dayElem.appendChild(slotSpan);
                        }
                    },
                    onChange: (d, s, i) => {
                        if (d.length > 0) {
                            updateDateFeedback(d[0], i);
                        } else {
                            if (dateFeedback) dateFeedback.textContent = '';
                        }
                    }
                });
            }


            // --- EVENT LISTENERS ---
            if (startTimeInput) startTimeInput.addEventListener('input', recalculateAndRender);
            if (endTimeInput) endTimeInput.addEventListener('input', recalculateAndRender);
            if (installmentPlanSelect) installmentPlanSelect.addEventListener('change', recalculateAndRender);

            if (deliveryAddressInput) deliveryAddressInput.addEventListener('input', calculateDistanceAndFee);

            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const selectedMethod = this.value;
                    if (cardFieldsDiv) cardFieldsDiv.style.display = (selectedMethod === "Card") ? "block" : "none";
                    if (installmentFieldsDiv) installmentFieldsDiv.style.display = (selectedMethod === "Installment") ? "block" : "none";
                    if (cardNumberInput) cardNumberInput.required = (selectedMethod === "Card");
                    if (cardExpiryInput) cardExpiryInput.required = (selectedMethod === "Card");
                    if (cardCvvInput) cardCvvInput.required = (selectedMethod === "Card");
                    if (installmentPlanSelect) installmentPlanSelect.required = (selectedMethod === "Installment");
                    if (selectedMethod !== 'Installment' && installmentPlanSelect) {
                        installmentPlanSelect.value = "";
                    }
                    recalculateAndRender();
                });
            });

            if (cardNumberInput) cardNumberInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                e.target.value = value.replace(/(.{4})/g, '$1 ').trim();
                let iconClass = '';
                if (/^4/.test(value)) iconClass = 'fab fa-cc-visa';
                else if (/^5[1-5]/.test(value)) iconClass = 'fab fa-cc-mastercard';
                else if (/^3[47]/.test(value)) iconClass = 'fab fa-cc-amex';
                if (cardIcon) cardIcon.className = 'card-icon ' + iconClass;
                e.target.classList.toggle('is-valid', value.length >= 13 && value.length <= 16);
            });
            if (cardExpiryInput) cardExpiryInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 2) value = value.slice(0, 2) + ' / ' + value.slice(2, 4);
                e.target.value = value;
            });
            if (cardCvvInput) cardCvvInput.addEventListener('input', (e) => e.target.value = e.target.value.replace(/\D/g, ''));


            // --- FORM VALIDATION ON SUBMIT (FIXED) ---
            if (checkoutForm) checkoutForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const errorMessages = [];
                const invalidElements = [];
                checkoutForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

                const checkField = (selector, message) => {
                    const field = checkoutForm.querySelector(selector);
                    if (!field || !field.value.trim()) {
                        errorMessages.push(message);
                        if (field) invalidElements.push(field);
                    }
                };

                checkField('[name="contact_number"]', 'Contact Number is required.');
                checkField('[name="address"]', 'Delivery Address is required.');
                checkField('[name="date_event"]', 'Event Date is required.');
                checkField('[name="start_time"]', 'Event Start Time is required.');
                checkField('[name="end_time"]', 'Event End Time is required.');

                if (!termsAgreeCheckbox || !termsAgreeCheckbox.checked) {
                    errorMessages.push('You must agree to the Terms and Conditions.');
                    if (termsAgreeCheckbox) invalidElements.push(termsAgreeCheckbox);
                }

                const paymentMethod = checkoutForm.querySelector('input[name="payment_method"]:checked');
                if (!paymentMethod) {
                    errorMessages.push('A Payment Method must be selected.');
                } else {
                    if (paymentMethod.value === 'Card') {
                        if (!cardNumberInput || !cardNumberInput.classList.contains('is-valid')) {
                            errorMessages.push('A valid Card Number is required.');
                            if (cardNumberInput) invalidElements.push(cardNumberInput);
                        }
                        checkField('[name="card_expiry"]', 'Card Expiry Date is required.');
                        // --- THIS IS THE JAVASCRIPT FIX ---
                        checkField('[name="card_cvv"]', 'Card CVV is required.');
                    } else if (paymentMethod.value === 'Installment') {
                        checkField('[name="installment_plan"]', 'An Installment Plan must be selected.');
                    }
                }

                if (errorMessages.length > 0) {
                    invalidElements.forEach(el => el.classList.add('is-invalid'));
                    if (document.getElementById('errorList')) {
                        document.getElementById('errorList').innerHTML = errorMessages.map(msg => `<li>${msg}</li>`).join('');
                    }
                    if (errorModal) errorModal.show();
                    if (invalidElements.length > 0) {
                        invalidElements[0].focus();
                        invalidElements[0].scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                } else {
                    if (placeOrderButton) {
                        placeOrderButton.disabled = true;
                        placeOrderButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Placing Order...';
                    }
                    checkoutForm.submit();
                }
            });

            // --- INITIAL RUN ---
            recalculateAndRender();
            if (deliveryAddressInput && deliveryAddressInput.value.trim()) {
                calculateDistanceAndFee(); // Calculate fee on load if address is pre-filled
            }

        });
    </script>
</body>

</html>




