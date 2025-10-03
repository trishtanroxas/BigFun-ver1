<?php
session_start();
// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require Composer's autoloader. Make sure you have run 'composer require phpmailer/phpmailer' in your project directory
require 'vendor/autoload.php';
// Include our custom email template function
require 'backend/email_template.php';


header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

include "backend/db.php";

// Check for success flags from a previous order FIRST
$show_success_modal = $_SESSION['order_success'] ?? false;
$order_payment_method = $_SESSION['order_payment_method'] ?? null;
unset($_SESSION['order_success'], $_SESSION['order_payment_method']);

// Fetch logged in user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM signup WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_initial'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// Fetch cart items from the DATABASE
$cart_items = [];
$total = 0;
$cart_stmt = $conn->prepare("
    SELECT ci.id as cart_item_id, ci.quantity, s.id as service_id, s.service_name, s.price, s.service_image
    FROM cart_items ci JOIN services s ON ci.service_id = s.id WHERE ci.user_id = ?
");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$result = $cart_stmt->get_result();
if ($result->num_rows > 0) {
    while ($item = $result->fetch_assoc()) {
        $cart_items[] = $item;
        $total += $item['price'] * $item['quantity'];
    }
} else if (!$show_success_modal) {
    // Only redirect if cart is empty AND we are NOT showing the success modal from a previous order
    $_SESSION['msg'] = "Your cart is empty.";
    $_SESSION['msg_type'] = "info";
    header("Location: book-dashboard.php");
    exit;
}
$cart_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_event = $_POST['date_event'] ?? '';

    // --- Check order limit before proceeding ---
    $limit_stmt = $conn->prepare("SELECT COUNT(id) as order_count FROM orders WHERE date_event = ?");
    $limit_stmt->bind_param("s", $date_event);
    $limit_stmt->execute();
    $count_result = $limit_stmt->get_result()->fetch_assoc();
    $limit_stmt->close();

    if ($count_result['order_count'] >= 5) {
        $_SESSION['msg'] = "Sorry, the selected date ({$date_event}) is now fully booked. Please choose another date.";
        $_SESSION['msg_type'] = "danger";
        header("Location: checkout.php");
        exit;
    }
    
    // Collect form data
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);
    $type_event = isset($_POST['type_event']) ? implode(", ", $_POST['type_event']) : '';
    $location_checklist = isset($_POST['location_checklist']) ? implode(", ", $_POST['location_checklist']) : '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $installment_plan = ($payment_method === 'Installment') ? trim($_POST['installment_plan']) : '';
    
    // Initialize order variables
    $final_total = $total;
    // REMOVED: $downpayment_amount = NULL;
    $paid_date = NULL;
    $payment_status = 'Pending';
    $remaining_balance = $total;
    $next_due_date = NULL;

    switch ($payment_method) {
        case 'Card':
            $payment_status = 'Paid';
            $paid_date = date("Y-m-d H:i:s");
            $remaining_balance = 0;
            break;
        case 'Installment':
            if (!empty($installment_plan)) {
                $payment_status = 'Pending';
                $interest_rate = 0;
                switch ($installment_plan) {
                    case '1': $interest_rate = 0.05; break; // 5% for 1 month
                    case '2': $interest_rate = 0.10; break; // 10% for 2 months
                    case '3': $interest_rate = 0.15; break; // 15% for 3 months
                }
                $final_total = $total * (1 + $interest_rate);
                $remaining_balance = $final_total;
                $next_due_date = date('Y-m-d', strtotime('+1 month')); // Next payment due in 1 month
            }
            break;
        case 'Cash':
            $payment_status = 'Pending';
            break;
    }
    
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
    
    // FIXED: Removed 'downpayment_amount' from the INSERT statement to match the database.
    $stmt = $conn->prepare("INSERT INTO orders (user_id, full_name, email, contact_number, address, type_event, location_checklist, start_time, end_time, date_event, notes, payment_method, installment_plan, card_type, card_number, card_expiry, total_amount, remaining_balance, next_due_date, payment_status, paid_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssssssssssdssss", $user_id, $full_name, $user['email'], $contact_number, $address, $type_event, $location_checklist, $start_time, $end_time, $date_event, $notes, $payment_method, $installment_plan, $card_type, $masked_card, $raw_card_expiry, $final_total, $remaining_balance, $next_due_date, $payment_status, $paid_date);

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

        // This array passes all the necessary info to the email template
        $order_details = [
            'full_name' => $full_name,
            'email' => $user['email'],
            'contact_number' => $contact_number,
            'address' => $address, 
            'date_event' => $date_event, 
            'start_time' => $start_time, 
            'end_time' => $end_time, 
            'type_event' => $type_event, 
            'location_checklist' => $location_checklist, 
            'notes' => $notes, 
            'payment_method' => $payment_method, 
            'installment_plan' => $installment_plan, 
            'total_amount' => $final_total, 
            'payment_status' => $payment_status,
            'interest_rate' => ($interest_rate ?? 0) * 100,
            'card_type' => $card_type,
            'card_number' => $masked_card,
            'next_due_date' => $next_due_date
        ];

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
            $mail->Subject = 'Your BigFun Order Confirmation #' . $order_id;
            $mail->Body    = generateOrderEmailBody($order_id, $user, $cart_items, $order_details);
            $mail->send();
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
        
        $_SESSION['order_success'] = true;
        $_SESSION['order_payment_method'] = $payment_method;
        header("Location: checkout.php");
        exit;
    } else {
        $_SESSION['msg'] = "Error placing order: " . $stmt->error;
        $_SESSION['msg_type'] = "danger";
        header("Location: checkout.php");
        exit;
    }
}

// Flash messages
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
    :root { --primary-color: #802080; --primary-light: #FFEFFC; }
    body { background: var(--primary-light); font-family: 'Inter', sans-serif; }
    .order-summary-card { position: sticky; top: 20px; }
    .payment-option { border: 1px solid #ddd; border-radius: 8px; padding: 1rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 10px; }
    .payment-option:has(input:checked) { border-color: var(--primary-color); background-color: var(--primary-light); box-shadow: 0 0 0 2px var(--primary-color); }
    .card-number-wrapper { position: relative; }
    .card-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 24px; }
    .is-valid { border-color: #198754; }
    .is-invalid { border-color: #dc3545; }
    #installmentAmount { font-weight: 600; color: #d63384; margin-top: 8px; font-size: 0.9rem; } 
    #installmentAmount small { color: #6c757d; font-weight: normal; } /* Style for the new interest text */
    .day-slots { font-size: 0.65rem; font-weight: bold; color: #198754; display: block; text-align: center; }
    .flatpickr-day.fully-booked { text-decoration: line-through; background-color: #f8d7da !important; color: #842029 !important; cursor: not-allowed !important; border-color: #f5c2c7 !important; }
    #date-feedback { font-weight: bold; margin-top: 8px; min-height: 20px; }
  </style>
</head>
<body>
<div class="container py-5">
    <div class="text-center mb-4">
        <a href="book-dashboard.php"><img src="images/bgfunlogo.png" alt="BigFun Logo" style="max-height: 60px;"></a>
        <h1 class="mt-3">Checkout</h1>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">
        <div class="row g-5">
            <div class="col-lg-7">
                <div class="accordion" id="checkoutAccordion">
                    
                    <div class="accordion-item rounded-3 mb-3">
                        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">1. Customer Information</button></h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#checkoutAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label">Full Name</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" readonly></div>
                                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly></div>
                                    <div class="col-md-6"><label class="form-label">Contact Number</label><input type="tel" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" required></div>
                                    <div class="col-12"><label class="form-label">Delivery Address</label><input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address']); ?>" required></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item rounded-3 mb-3">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">2. Event Details</button></h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#checkoutAccordion">
                            <div class="accordion-body">
                                <h6>Type of Event</h6>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="type_event[]" value="Private Event"><label class="form-check-label ms-1">Private Event</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="type_event[]" value="Corporate Event"><label class="form-check-label ms-1">Corporate Event</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="type_event[]" value="Community/School"><label class="form-check-label ms-1">Community/School</label></div>

                                <h6 class="mt-3">Location Checklist</h6>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="location_checklist[]" value="Power onsite"><label class="form-check-label ms-1">Power onsite (if >20m away)</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="location_checklist[]" value="Grass setup"><label class="form-check-label ms-1">Ride is on grass</label></div>

                                <h6 class="mt-3">Event Schedule</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Date</label>
                                        <input type="text" id="datePicker" name="date_event" class="form-control" placeholder="Select a date..." required readonly>
                                        <div id="date-feedback"></div>
                                    </div>
                                    <div class="col-md-4"><label class="form-label">Start Time</label><input type="time" name="start_time" class="form-control" required></div>
                                    <div class="col-md-4"><label class="form-label">End Time</label><input type="time" name="end_time" class="form-control" required></div>
                                </div>
                                <div class="mt-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item rounded-3 mb-3">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">3. Payment Method</button></h2>
                        <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#checkoutAccordion">
                            <div class="accordion-body">
                                <div class="d-grid gap-3">
                                    <label class="payment-option"><input type="radio" name="payment_method" value="Card" class="form-check-input"> <i class="fa-regular fa-credit-card"></i> Pay with Card</label>
                                    <div id="cardFields" class="p-3 border rounded-3 ms-4" style="display:none;">
                                        <div class="card-number-wrapper mb-2"><input type="text" name="card_number" class="form-control" placeholder="Card Number" id="cardNumber" maxlength="19"><span class="card-icon" id="cardIcon"></span></div>
                                        <div class="row g-2">
                                            <div class="col-md-6"><input type="text" name="card_expiry" class="form-control" placeholder="MM / YY" id="cardExpiry"></div>
                                            <div class="col-md-6"><input type="text" name="card_cvv" class="form-control" placeholder="CVV" id="cardCvv" maxlength="4"></div>
                                        </div>
                                    </div>
                                    <label class="payment-option"><input type="radio" name="payment_method" value="Installment" class="form-check-input"> <i class="fa-solid fa-calendar-days"></i> Pay via Installment</label>
                                    <div id="installmentFields" class="p-3 border rounded-3 ms-4" style="display:none;">
                                        <label class="form-label">Select Plan</label>
                                        <select class="form-select" name="installment_plan" id="installmentPlan">
                                            <option value="">Choose a plan...</option>
                                            <option value="1">1 Month (5% interest)</option>
                                            <option value="2">2 Months (10% interest)</option>
                                            <option value="3">3 Months (15% interest)</option>
                                        </select>
                                        <div id="installmentAmount" class="mt-2"></div>
                                    </div>
                                    <label class="payment-option"><input type="radio" name="payment_method" value="Cash" class="form-check-input" required> <i class="fa-solid fa-money-bill-wave"></i> Pay with Cash on Delivery</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="cart.php" class="text-decoration-none fw-bold" style="color: var(--primary-color);">&laquo; Back to Cart</a>
                    <button type="submit" class="btn btn-success btn-lg">Order Received</button>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card order-summary-card">
                    <div class="card-body p-4">
                        <h4 class="mb-3">Order Summary</h4>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($cart_items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?php echo htmlspecialchars($item['service_name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                                <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </li>
                            <?php endforeach; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0" id="payment-method-summary-item" style="display: none;">
                                <span class="text-muted">Payment Method</span>
                                <span class="text-muted" id="payment-method-summary-text"></span>
                            </li>
                            </ul>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total</span>
                            <span id="summary-total">$<?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4" style="border-radius: 15px; border:none;">
      <div class="modal-body">
        <h3 class="mt-3" style="font-family: 'Inria Sans', sans-serif;">Order Received!</h3>
        <?php if ($order_payment_method === 'Installment'): ?>
            <p class="text-muted">Your booking is confirmed. Your first payment is due in one month. Please check your "Invoices & Payments" page for details.</p>
        <?php else: ?>
            <p class="text-muted">Thank you for choosing Big Fun. We have sent a confirmation to your email address.</p>
        <?php endif; ?>
        <a href="book-dashboard.php" class="btn mt-2" style="background-color: var(--primary-color); color: white;">Continue Shopping</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
// ALL JAVASCRIPT IS UNCHANGED AND REMAINS THE SAME AS THE ORIGINAL FILE
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($show_success_modal): ?>
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
    <?php endif; ?>

    const ORDER_LIMIT = 5;
    const dateFeedback = document.getElementById('date-feedback');
    let orderCounts = {};

    const fp = flatpickr("#datePicker", {
        dateFormat: "Y-m-d",
        minDate: "today",
        onReady: (d,s,i) => fetchOrderCounts(i.currentMonth + 1, i.currentYear, i),
        onMonthChange: (d,s,i) => fetchOrderCounts(i.currentMonth + 1, i.currentYear, i),
        onDayCreate: (dObj, dStr, fp, dayElem) => {
            const date = dayElem.dateObj.toISOString().slice(0, 10);
            const data = orderCounts[date] || { order_count: 0 };
            const count = data.order_count;
            if (count > 0 && count < ORDER_LIMIT) {
                const remaining = ORDER_LIMIT - count;
                dayElem.innerHTML += `<span class='day-slots'>${remaining} left</span>`;
            }
        },
        onChange: (selectedDates, dateStr, instance) => {
            const dayData = orderCounts[dateStr] || { order_count: 0 };
            const remaining = ORDER_LIMIT - dayData.order_count;
            if (remaining > 0) {
                dateFeedback.textContent = `Great! ${remaining} slot(s) remaining for this date.`;
                dateFeedback.className = 'text-success mt-2 fw-bold';
            } else {
                dateFeedback.textContent = '';
            }
        }
    });

    async function fetchOrderCounts(month, year, instance) {
        try {
            const response = await fetch(`backend/get_order_counts.php?month=${month}&year=${year}`);
            orderCounts = await response.json();
            const disabledDates = Object.keys(orderCounts).filter(date => orderCounts[date].order_count >= ORDER_LIMIT);
            instance.set('disable', disabledDates);
        } catch (error) {
            console.error("Failed to fetch order counts:", error);
        }
    }

    const installmentPlanSelect = document.getElementById('installmentPlan');
    const installmentAmountDiv = document.getElementById('installmentAmount');
    const summaryTotalEl = document.getElementById('summary-total');
    const paymentSummaryItem = document.getElementById('payment-method-summary-item');
    const paymentSummaryText = document.getElementById('payment-method-summary-text');
    const cartTotal = <?php echo $total; ?>;
    const interestRates = { '1': 0.05, '2': 0.10, '3': 0.15 };

    function updateInstallmentDisplay() {
        const months = installmentPlanSelect.value;
        if (months && interestRates[months]) {
            const rate = interestRates[months];
            const interestAmount = cartTotal * rate;
            const totalWithFee = cartTotal + interestAmount;
            const perMonth = totalWithFee / parseInt(months);
            
            installmentAmountDiv.innerHTML = `<b>$${perMonth.toFixed(2)}</b> / month for ${months} month(s).<br><small>Total interest: +$${interestAmount.toFixed(2)}</small>`;
            summaryTotalEl.textContent = '$' + totalWithFee.toFixed(2);
            
        } else {
            installmentAmountDiv.innerHTML = "";
            summaryTotalEl.textContent = '$' + cartTotal.toFixed(2);
        }
    }
    installmentPlanSelect.addEventListener('change', updateInstallmentDisplay);

    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            paymentSummaryItem.style.display = 'flex';
            paymentSummaryText.textContent = this.value;

            if (this.value !== "Installment") {
                summaryTotalEl.textContent = '$' + cartTotal.toFixed(2);
                installmentPlanSelect.value = "";
                installmentAmountDiv.innerHTML = "";
            } else {
                paymentSummaryText.textContent = "Installment";
                updateInstallmentDisplay();
            }
            
            document.getElementById("cardFields").style.display = (this.value === "Card") ? "block" : "none";
            document.getElementById("installmentFields").style.display = (this.value === "Installment") ? "block" : "none";
        });
    });

    // --- Card formatting and validation ---
    const cardNumberInput = document.getElementById('cardNumber');
    const cardExpiryInput = document.getElementById('cardExpiry');
    const cardCvvInput = document.getElementById('cardCvv');
    const cardIcon = document.getElementById('cardIcon');

    cardNumberInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        let formattedValue = '';
        if (/^3[47]/.test(value)) { value = value.substring(0, 15); for (let i = 0; i < value.length; i++) { if (i === 4 || i === 10) formattedValue += ' '; formattedValue += value[i]; } } 
        else { value = value.substring(0, 16); for (let i = 0; i < value.length; i++) { if (i > 0 && i % 4 === 0) formattedValue += ' '; formattedValue += value[i]; } }
        e.target.value = formattedValue;
        if (/^4/.test(value)) { cardIcon.innerHTML = '<i class="fa-brands fa-cc-visa" style="color:#1a1f71;"></i>'; } 
        else if (/^5[1-5]/.test(value)) { cardIcon.innerHTML = '<i class="fa-brands fa-cc-mastercard" style="color:#f69e1f;"></i>'; } 
        else if (/^3[47]/.test(value)) { cardIcon.innerHTML = '<i class="fa-brands fa-cc-amex" style="color:#2e77bc;"></i>'; } 
        else { cardIcon.innerHTML = ''; }
        let sum = 0; let shouldDouble = false;
        for (let i = value.length - 1; i >= 0; i--) { let digit = parseInt(value.charAt(i)); if (shouldDouble) { if ((digit *= 2) > 9) digit -= 9; } sum += digit; shouldDouble = !shouldDouble; }
        const isValid = (sum % 10) === 0;
        cardNumberInput.classList.remove('is-valid', 'is-invalid');
        if (value.length > 0) { if (isValid && ((/^3[47]/.test(value) && value.length === 15) || value.length === 16)) { cardNumberInput.classList.add('is-valid'); } else { cardNumberInput.classList.add('is-invalid'); } }
    });

    cardExpiryInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '').substring(0,4);
        if (value.length > 2) { e.target.value = value.substring(0,2) + ' / ' + value.substring(2); } 
        else { e.target.value = value; }
    });

    cardCvvInput.addEventListener('input', function(e) {
        let amex = /^3[47]/.test(cardNumberInput.value.replace(/\D/g, ''));
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, amex ? 4 : 3);
    });
});
</script>
</body>
</html>