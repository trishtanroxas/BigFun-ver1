<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Combined Email Templates ---

function generateUserCancelRequestEmail($customer_name, $order_id)
{
    $current_year = date('Y');
    return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;color:#333}.container{max-width:600px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:8px}.header{background-color:#ffc107;color:#333;padding:10px 20px;text-align:center;border-radius:8px 8px 0 0}.content h1{color:#333}.info-box{background-color:#f9f9f9;padding:15px;border-radius:5px;margin-top:20px}.footer{text-align:center;font-size:0.9em;color:#777;margin-top:20px}</style></head><body><div class="container"><div class="header"><h2>Cancellation Request Received</h2></div><div class="content"><h1>We've Received Your Request</h1><p>Hello {$customer_name},</p><p>This is to confirm that we have received your request to cancel <strong>Order #{$order_id}</strong>. Our team will review your request shortly.</p><p>You will receive another email once the cancellation and refund have been approved and processed by an administrator.</p><div class="info-box"><strong>Order ID:</strong> #{$order_id}<br><strong>Current Status:</strong> Pending Cancellation</div><p>Thank you for your patience.</p><p>Sincerely,<br>The BigFun Team</p></div><div class="footer"><p>&copy; {$current_year} BigFun Entertainment.</p></div></div></body></html>
HTML;
}

function generateAdminCancellationAlert($customer_name, $order_id, $event_date, $request_time, $reason)
{
    $reason_html = !empty($reason) ? htmlspecialchars($reason) : 'No reason provided.';
    return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;color:#333}.container{max-width:600px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:8px}.header{background-color:#0d6efd;color:white;padding:10px 20px;text-align:center;border-radius:8px 8px 0 0}.content h1{color:#0d6efd}.info-box{background-color:#f9f9f9;padding:15px;border-radius:5px;margin-top:20px}</style></head><body><div class="container"><div class="header"><h2>Admin Action Required: Cancellation Request</h2></div><div class="content"><h1>A Cancellation Request is Awaiting Approval</h1><p>A customer has requested to cancel a booking. Please review the details below and approve or deny the request in the admin panel.</p><div class="info-box"><strong>Order ID:</strong> #{$order_id}<br><strong>Customer Name:</strong> {$customer_name}<br><strong>Event Date:</strong> {$event_date}<br><strong>Request Time:</strong> {$request_time}<br><strong>Reason Provided:</strong><br><p>{$reason_html}</p></div><p>This order's status has been updated to 'Pending Cancellation'.</p></div></div></body></html>
HTML;
}


// --- Combined Backend Logic for Cancellation Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_cancellation') {
    include "backend/db.php";
    require 'vendor/autoload.php';
    include "backend/notification_helper.php"; // ADDED: Include the notification helper

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $order_id = intval($_POST['order_id'] ?? 0);
    $reason_option = trim($_POST['reason_option'] ?? '');
    $other_reason = trim($_POST['other_reason'] ?? '');
    $final_reason = $reason_option;
    if ($reason_option === 'Other' && !empty($other_reason)) {
        $final_reason = "Other: " . $other_reason;
    } elseif (empty($reason_option)) {
        $final_reason = "No reason provided";
    }

    if ($order_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid Order ID.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT o.*, s.first_name, s.last_name, s.email FROM orders o JOIN signup s ON o.user_id = s.id WHERE o.id = ? AND o.user_id = ? FOR UPDATE");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) throw new Exception("Order not found or you do not have permission to modify it.");

        $data = $result->fetch_assoc();
        $stmt->close();

        if (!in_array($data['booking_status'], ['Confirmed', 'Pending Confirmation'])) throw new Exception("This booking cannot be cancelled as it is already {$data['booking_status']}.");

        $event_date_obj = new DateTime($data['date_event']);
        $now = new DateTime();
        $interval = $now->diff($event_date_obj);
        $hours_until_event = ($interval->days * 24) + $interval->h;

        if (!$interval->invert && $hours_until_event <= 48) {
            throw new Exception("Bookings cannot be cancelled within 48 hours of the event.");
        }

        $request_time = date("Y-m-d H:i:s");
        $update_stmt = $conn->prepare("UPDATE orders SET booking_status = 'Pending Cancellation', payment_status = 'Cancelled', cancellation_reason = ?, cancellation_timestamp = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $final_reason, $request_time, $order_id);
        $update_stmt->execute();
        $update_stmt->close();

        $customer_name = trim($data['first_name'] . ' ' . $data['last_name']);

        // Send emails... (omitting for brevity, your code is correct here)
        // Note: You might want to call your email functions here

        // --- ADDED: CREATE NOTIFICATION ---
        create_notification(
            $conn,
            $user_id,
            "Cancellation Request Received",
            "Your request to cancel order #{$order_id} has been submitted. An admin will review it shortly.",
            "booking-status.php"
        );
        // --- END NOTIFICATION ---

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Your cancellation request has been submitted for admin approval.']);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    $conn->close();
    exit;
}

// --- Regular Page Load Logic ---
include "backend/db.php";
include "backend/notification_helper.php"; // --- ADDED: Include helper for deadline check ---

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_count = 0;
$count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$result = $count_stmt->get_result();
$cart_data = $result->fetch_assoc();
$cart_count = $cart_data['total_items'] ?? 0;
$count_stmt->close();

// --- NEW: Fetch Unread Notification Count ---
$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result()->fetch_assoc();
$unread_count = $notif_result['unread_count'] ?? 0;
$notif_stmt->close();
// ------------------------------------------

$upcoming_bookings = [];
$past_bookings = [];
$cancelled_bookings = [];

// --- MODIFIED: Select new `last_deadline_notified` column ---
// Make sure you have run the ALTER TABLE command from the previous step
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY date_event DESC");
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$result = $order_stmt->get_result();

if ($result->num_rows > 0) {
    $today = new DateTime('today');
    $all_orders = $result->fetch_all(MYSQLI_ASSOC); // Fetch all orders first

    // --- NEW: Check for missed deadlines BEFORE sorting ---
    // This statement assumes your `last_deadline_notified` column exists
    $update_notified_stmt = $conn->prepare("UPDATE orders SET last_deadline_notified = ? WHERE id = ?");

    foreach ($all_orders as $order) {
        $is_installment = $order['payment_method'] === 'Installment';
        $is_unpaid = in_array($order['payment_status'], ['Pending', 'Partially Paid']);
        $has_due_date = !empty($order['next_due_date']) && $order['next_due_date'] !== '0000-00-00';

        if ($is_installment && $is_unpaid && $has_due_date) {
            try {
                $due_date = new DateTime($order['next_due_date']);
                $last_notified = $order['last_deadline_notified'] ? new DateTime($order['last_deadline_notified']) : null;

                // Check if due date is in the past AND we haven't notified for this specific date
                if ($due_date < $today && (!$last_notified || $last_notified < $due_date)) {

                    // 1. Create Notification
                    create_notification(
                        $conn,
                        $user_id,
                        "Missed Payment Deadline",
                        "Your installment payment for order #" . $order['id'] . " was due on " . $due_date->format('F j, Y') . ". Please pay now to avoid issues.",
                        "invoices.php"
                    );

                    // 2. Update order to "remember" this notification
                    $due_date_str = $due_date->format('Y-m-d');
                    $update_notified_stmt->bind_param("si", $due_date_str, $order['id']);
                    $update_notified_stmt->execute();
                }
            } catch (Exception $e) {
                // Handle invalid date format, etc.
                error_log("Error processing deadline for order #" . $order['id'] . ": " . $e->getMessage());
            }
        }
    }
    $update_notified_stmt->close();
    // --- END NEW DEADLINE CHECK ---


    // --- Now, sort the orders as usual ---
    foreach ($all_orders as $row) {
        if (in_array($row['booking_status'], ['Cancelled', 'Refunded'])) {
            $cancelled_bookings[] = $row;
        } else {
            $event_date = new DateTime($row['date_event']);
            if ($event_date >= $today) {
                $upcoming_bookings[] = $row;
            } else {
                if ($row['booking_status'] === 'Pending Confirmation') {
                    $row['derived_status'] = 'Not Serviced';
                } else {
                    $row['derived_status'] = 'Completed';
                }
                $past_bookings[] = $row;
            }
        }
    }
}
$order_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Booking Status - BigFun</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #802080;
            --primary-light: #FFEFFC;
            --light-gray: #f8f9fa;
            --border-color: #dee2e6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-gray);
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            background-color: #fff;
            border-right: 1px solid var(--border-color);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
        }

        .mobile-header {
            display: none;
            background-color: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .menu-icon {
            font-size: 28px;
            color: var(--primary-color);
            cursor: pointer;
        }

        .sidebar .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .sidebar .logo img {
            max-height: 50px;
        }

        .sidebar .nav-pills .nav-link {
            color: #555;
            font-size: 15px;
            font-weight: 600;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar .nav-pills .nav-link.active,
        .sidebar .nav-pills .nav-link:hover {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        .sidebar .logout-link {
            margin-top: auto;
        }

        .sidebar .btn-cart {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
        }

        .nav-tabs .nav-link {
            font-weight: 600;
            color: #6c757d;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-color: var(--primary-color) var(--primary-color) #fff;
        }

        .booking-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            border-left: 5px solid var(--primary-color);
        }

        .booking-card.is-cancelled {
            border-left-color: #dc3545;
        }

        .booking-card.is-past {
            border-left-color: #6c757d;
        }

        .booking-card.is-pending-cancellation {
            border-left-color: #ffc107;
        }

        .booking-card.is-not-serviced {
            border-left-color: #ffc107;
        }

        .booking-card.is-refunded {
            border-left-color: #17a2b8;
        }

        .booking-card .card-header {
            background-color: transparent;
            font-family: 'Inria Sans', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .status-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: .4em .75em;
        }

        @media(max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
                z-index: 1050;
            }

            .sidebar.offcanvas.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
        }
    </style>
</head>

<body>

    <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
        <div class="logo"><a href="book-dashboard.php"><img src="assets/images/bgfunlogo.png" alt="BigFun Logo"></a></div>
        <button class="btn btn-cart w-100 mb-4" onclick="window.location.href='cart.php'">Cart (<?php echo $cart_count; ?>)</button>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="book-dashboard.php" class="nav-link"><span class="material-symbols-outlined">storefront</span>Services</a></li>
            <li class="nav-item"><a href="booking-status.php" class="nav-link active"><span class="material-symbols-outlined">history</span>Booking Status</a></li>
            <li class="nav-item"><a href="invoices.php" class="nav-link"><span class="material-symbols-outlined">receipt_long</span>Invoice and Payment</a></li>

            <!-- NEW NOTIFICATION LINK -->
            <li class="nav-item">
                <a href="notification.php" class="nav-link" id="sidebar-notify-link">
                    <span class="material-symbols-outlined">notifications</span>
                    Notification
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto" id="sidebar-notif-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item"><a href="profile-manage.php" class="nav-link"><span class="material-symbols-outlined">person</span>Profile</a></li>
        </ul>
        <div class="logout-link"><a href="backend/logout.php" class="btn btn-light w-100">Log Out</a></div>
    </div>

    <header class="mobile-header">
        <img src="assets/images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
        <span class="material-symbols-outlined menu-icon" data-bs-toggle="offcanvas" data-bs-target="#sidebar">menu</span>
    </header>

    <div class="main-content">
        <h1 class="mb-4">My Bookings</h1>

        <ul class="nav nav-tabs mb-4" id="bookingTabs" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming-pane" type="button">Upcoming</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past-pane" type="button">Past</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled-pane" type="button">Cancelled</button></li>
        </ul>

        <div class="tab-content" id="bookingTabsContent">
            <div class="tab-pane fade show active" id="upcoming-pane" role="tabpanel">
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <div>
                        <label for="bookingStatusFilter" class="form-label fw-bold">Filter by Booking Status</label>
                        <select class="form-select" id="bookingStatusFilter">
                            <option value="all" selected>All</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Pending Confirmation">Pending Confirmation</option>
                            <option value="Pending Cancellation">Pending Cancellation</option>
                        </select>
                    </div>
                    <div>
                        <label for="paymentStatusFilter" class="form-label fw-bold">Filter by Payment Status</label>
                        <select class="form-select" id="paymentStatusFilter">
                            <option value="all" selected>All</option>
                            <option value="Paid">Paid</option>
                            <option value="Partially Paid">Partially Paid</option>
                            <option value="Pending">Pending</option>
                            <option value="For Verification">For Verification</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div id="upcoming-bookings-list">
                    <?php if (empty($upcoming_bookings)): ?>
                        <div class="alert alert-info no-bookings-message">You have no upcoming bookings.</div>
                        <?php else: foreach ($upcoming_bookings as $order):
                            $booking_status = $order['booking_status'];
                            $status_config = [
                                'Confirmed' => ['class' => 'bg-success', 'card_class' => ''],
                                'Pending Confirmation' => ['class' => 'bg-warning text-dark', 'card_class' => ''],
                                'Pending Cancellation' => ['class' => 'bg-warning text-dark', 'card_class' => 'is-pending-cancellation'],
                                'default' => ['class' => 'bg-secondary', 'card_class' => '']
                            ];
                            $config = $status_config[$booking_status] ?? $status_config['default'];
                            $payment_status_class = match ($order['payment_status']) {
                                'Paid' => 'bg-success-subtle text-success-emphasis',
                                'Pending' => 'bg-warning-subtle text-warning-emphasis',
                                'Partially Paid' => 'bg-info-subtle text-info-emphasis',
                                'For Verification' => 'bg-primary-subtle text-primary-emphasis',
                                'Cancelled' => 'bg-secondary-subtle text-secondary-emphasis',
                                default => 'bg-secondary-subtle'
                            };
                        ?>
                            <div class="card booking-card mb-3 <?php echo $config['card_class']; ?>" data-booking-status="<?php echo htmlspecialchars($booking_status); ?>" data-payment-status="<?php echo htmlspecialchars($order['payment_status']); ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">Order #<?php echo $order['id']; ?><span class="badge rounded-pill <?php echo $config['class']; ?> status-badge"><?php echo htmlspecialchars($booking_status); ?></span></div>
                                <div class="card-body">
                                    <div class="row align-items-center gy-3">
                                        <div class="col-md-4"><strong>Event Date:</strong><br><?php echo date("l, F j, Y", strtotime($order['date_event'])); ?></div>
                                        <div class="col-md-3"><strong>Total Amount:</strong><br>$<?php echo number_format($order['total_amount'], 2); ?></div>
                                        <div class="col-md-2"><strong>Payment:</strong><br><span class="badge rounded-pill <?php echo $payment_status_class; ?> status-badge"><?php echo htmlspecialchars($order['payment_status']); ?></span></div>
                                        <div class="col-md-3 text-md-end actions-cell">
                                            <button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View Details</button>
                                            <?php
                                            if (in_array($booking_status, ['Confirmed', 'Pending Confirmation'])) {
                                                $event_date_obj = new DateTime($order['date_event']);
                                                $now = new DateTime();
                                                $interval = $now->diff($event_date_obj);
                                                $hours_until_event = ($interval->days * 24) + $interval->h;
                                                if (!$interval->invert && $hours_until_event > 48) {
                                                    echo '<button class="btn btn-sm btn-outline-danger cancel-btn" data-order-id="' . $order['id'] . '" data-order-date="' . date("F j, Y", strtotime($order['date_event'])) . '">Cancel</button>';
                                                } else {
                                                    echo '<button class="btn btn-sm btn-outline-danger" disabled data-bs-toggle="tooltip" title="Cannot cancel within 48 hours of the event.">Cancel</button>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>
            </div>
            <div class="tab-pane fade" id="past-pane" role="tabpanel">
                <?php if (empty($past_bookings)): ?>
                    <div class="alert alert-info">You have no past bookings.</div>
                    <?php else: foreach ($past_bookings as $order):
                        $is_not_serviced = ($order['derived_status'] === 'Not Serviced');
                        $card_class = $is_not_serviced ? 'is-not-serviced' : 'is-past';
                        $badge_class = $is_not_serviced ? 'bg-warning text-dark' : 'bg-primary';
                        $status_text = $is_not_serviced ? 'Not Serviced' : 'Completed';
                        $details_text = $is_not_serviced ? 'This booking was never confirmed. Please contact an admin if you believe this is an error.' : '';
                    ?>
                        <div class="card booking-card <?php echo $card_class; ?> mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">Order #<?php echo $order['id']; ?><span class="badge rounded-pill <?php echo $badge_class; ?> status-badge"><?php echo $status_text; ?></span></div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4"><strong>Event Date:</strong><br><?php echo date("l, F j, Y", strtotime($order['date_event'])); ?></div>
                                    <div class="col-md-5"><strong>Total:</strong><br>$<?php echo number_format($order['total_amount'], 2); ?><?php if ($is_not_serviced): ?><p class="small text-danger mb-0 mt-1"><?php echo $details_text; ?></p><?php endif; ?></div>
                                    <div class="col-md-3 text-end"><button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View</button></div>
                                </div>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
            <div class="tab-pane fade" id="cancelled-pane" role="tabpanel">
                <?php if (empty($cancelled_bookings)): ?>
                    <div class="alert alert-info">You have no cancelled bookings.</div>
                    <?php else: foreach ($cancelled_bookings as $order):
                        $is_refunded = ($order['booking_status'] === 'Refunded');
                        $card_class = $is_refunded ? 'is-refunded' : 'is-cancelled';
                        $badge_class = $is_refunded ? 'bg-info text-dark' : 'bg-danger';
                        $status_text = $is_refunded ? 'Refunded' : 'Cancelled';
                        $refund_details = $is_refunded && !empty($order['refund_amount']) ? "Refund of $" . number_format($order['refund_amount'], 2) . " processed on " . date("F j, Y", strtotime($order['refund_date'])) . "." : '';
                    ?>
                        <div class="card booking-card <?php echo $card_class; ?> mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">Order #<?php echo $order['id']; ?><span class="badge rounded-pill <?php echo $badge_class; ?> status-badge"><?php echo $status_text; ?></span></div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4"><strong>Event Date:</strong><br><?php echo date("l, F j, Y", strtotime($order['date_event'])); ?></div>
                                    <div class="col-md-5"><strong>Total:</strong><br>$<?php echo number_format($order['total_amount'], 2); ?><?php if ($is_refunded): ?><p class="small text-info-emphasis mb-0 mt-1"><?php echo $refund_details; ?></p><?php endif; ?></div>
                                    <div class="col-md-3 text-end"><button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View</button></div>
                                </div>
                            </div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent"></div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="cancelConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Cancellation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="cancelForm"><input type="hidden" name="action" value="request_cancellation">
                        <p>Are you sure you want to request cancellation for your booking on <strong id="cancel-date-text"></strong>?</p>
                        <div class="mb-3"><label for="cancel-reason-option" class="form-label">Reason for Cancellation:</label><select class="form-select" id="cancel-reason-option" name="reason_option" required>
                                <option value="" selected disabled>Please select a reason...</option>
                                <option value="Change of Plans">Change of Plans</option>
                                <option value="Event Date Changed">Event Date Changed</option>
                                <option value="Found a Different Service">Found a Different Service</option>
                                <option value="Budgetary Reasons">Budgetary Reasons</option>
                                <option value="Other">Other</option>
                            </select></div>
                        <div class="mb-3" id="other-reason-wrapper" style="display: none;"><label for="cancel-other-reason" class="form-label">Please specify:</label><textarea class="form-control" id="cancel-other-reason" name="other_reason" rows="3"></textarea></div>
                        <p class="text-danger small">An admin will need to approve your request. This action cannot be undone.</p><input type="hidden" id="cancel-order-id" name="order_id">
                        <div class="modal-footer pb-0 border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back</button><button type="submit" class="btn btn-danger">Yes, Request Cancellation</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

            const detailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            const cancelModal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));

            document.querySelector('.main-content').addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-details-btn');
                if (viewBtn) {
                    const orderId = viewBtn.dataset.orderId;
                    const contentDiv = document.getElementById('orderDetailsContent');
                    contentDiv.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>';
                    detailsModal.show();
                    fetch(`backend/get_order_details.php?order_id=${orderId}`).then(res => res.json()).then(data => {
                        if (data.status === 'success') {
                            // *** NEW: Render the cancellation notice if it exists ***
                            let noticeHtml = '';
                            if (data.order.cancellation_notice) {
                                noticeHtml = `<div class="alert alert-warning" role="alert"><strong>Status Update:</strong> ${data.order.cancellation_notice}</div>`;
                            }

                            let itemsHtml = data.items.map(i => `<tr><td>${i.service_name} (x${i.quantity})</td><td class="text-end">$${(i.price_per_item * i.quantity).toFixed(2)}</td></tr>`).join('');
                            let paymentsHtml = data.payments.map(p => `<tr><td>${new Date(p.payment_date).toLocaleDateString()}</td><td>${p.payment_type}</td><td class="text-end">$${parseFloat(p.payment_amount).toFixed(2)}</td></tr>`).join('');

                            contentDiv.innerHTML = `
                        ${noticeHtml}
                        <h5>Order #${data.order.id}</h5>
                        <p>
                            <strong>Booking Status:</strong> ${data.order.booking_status}<br>
                            <strong>Payment Status:</strong> ${data.order.payment_status}
                        </p>
                        <h6>Items</h6>
                        <table class="table table-sm"><tbody>${itemsHtml}</tbody></table>
                        <h6>Payments</h6>
                        <table class="table table-sm"><thead><tr><th>Date</th><th>Type</th><th class="text-end">Amount</th></tr></thead><tbody>${paymentsHtml||'<tr><td colspan=3 class="text-center">No payments.</td></tr>'}</tbody></table>
                        <hr>
                        <h5 class="text-end">Total: $${parseFloat(data.order.total_amount).toFixed(2)} | Remaining: $${parseFloat(data.order.remaining_balance).toFixed(2)}</h5>
                    `;
                        } else {
                            throw new Error(data.message);
                        }
                    }).catch(err => {
                        contentDiv.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
                    });
                }
                const cancelBtn = e.target.closest('.cancel-btn');
                if (cancelBtn) {
                    document.getElementById('cancel-order-id').value = cancelBtn.dataset.orderId;
                    document.getElementById('cancel-date-text').textContent = cancelBtn.dataset.orderDate;
                    document.getElementById('cancelForm').reset();
                    document.getElementById('other-reason-wrapper').style.display = 'none';
                    cancelModal.show();
                }
            });

            document.getElementById('cancel-reason-option').addEventListener('change', function() {
                const otherReasonWrapper = document.getElementById('other-reason-wrapper');
                otherReasonWrapper.style.display = (this.value === 'Other') ? 'block' : 'none';
                document.getElementById('cancel-other-reason').required = (this.value === 'Other');
            });

            document.getElementById('cancelForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
                try {
                    const formData = new FormData(this);
                    const response = await fetch('booking-status.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || `Server error: ${response.statusText}`);
                    }

                    if (data.status === 'success') {
                        cancelModal.hide();
                        alert('Your cancellation request has been submitted.');
                        window.location.reload();
                    } else {
                        throw new Error(data.message || 'An unknown error occurred.');
                    }
                } catch (error) {
                    alert(`Error: ${error.message}`);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Yes, Request Cancellation';
                }
            });

            const bookingStatusFilter = document.getElementById('bookingStatusFilter');
            const paymentStatusFilter = document.getElementById('paymentStatusFilter');
            const upcomingList = document.getElementById('upcoming-bookings-list');

            function applyFilters() {
                const selectedBookingStatus = bookingStatusFilter.value;
                const selectedPaymentStatus = paymentStatusFilter.value;
                const cards = upcomingList.querySelectorAll('.booking-card');
                let visibleCount = 0;
                cards.forEach(card => {
                    const bookingMatch = (selectedBookingStatus === 'all' || card.dataset.bookingStatus === selectedBookingStatus);
                    const paymentMatch = (selectedPaymentStatus === 'all' || card.dataset.paymentStatus === selectedPaymentStatus);
                    if (bookingMatch && paymentMatch) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                let noBookingsMsg = upcomingList.querySelector('.no-bookings-message');
                if (noBookingsMsg) noBookingsMsg.remove();
                if (visibleCount === 0 && cards.length > 0) {
                    const newAlert = document.createElement('div');
                    newAlert.className = 'alert alert-info no-bookings-message';
                    newAlert.textContent = 'No upcoming bookings match the selected filters.';
                    upcomingList.appendChild(newAlert);
                }
            }
            bookingStatusFilter.addEventListener('change', applyFilters);
            paymentStatusFilter.addEventListener('change', applyFilters);
        });
    </script>
</body>

</html>




