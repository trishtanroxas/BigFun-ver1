<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

include "backend/db.php";

$user_id = $_SESSION['user_id'];

$cart_count = 0;
$count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$cart_count = $count_stmt->get_result()->fetch_assoc()['total_items'] ?? 0;
$count_stmt->close();

// --- NEW: Fetch Unread Notification Count ---
$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result()->fetch_assoc();
$unread_count = $notif_result['unread_count'] ?? 0;
$notif_stmt->close();
// ------------------------------------------

$orders = [];
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$result = $order_stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$order_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices & Payment - BigFun</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --primary-color: #802080; --primary-light: #FFEFFC; --light-gray: #f8f9fa; --border-color: #dee2e6; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 260px; background-color: #fff; border-right: 1px solid var(--border-color); padding: 20px; display: flex; flex-direction: column; }
        .main-content { margin-left: 260px; padding: 30px; }
        .mobile-header { display: none; background-color: #fff; padding: 15px 20px; border-bottom: 1px solid var(--border-color); }
        .menu-icon { font-size: 28px; color: var(--primary-color); cursor: pointer; }
        .sidebar .logo { text-align: center; margin-bottom: 30px; }
        .sidebar .logo img { max-height: 50px; }
        .sidebar .nav-pills .nav-link { color: #555; font-size: 15px; font-weight: 600; padding: 12px 15px; display: flex; align-items: center; gap: 10px; }
        .sidebar .nav-pills .nav-link.active, .sidebar .nav-pills .nav-link:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar .logout-link { margin-top: auto; }
        .sidebar .btn-cart { background-color: var(--primary-color); color: white; font-weight: 600; border: none; }
        .content-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .content-card h1 { font-family: 'Inria Sans', sans-serif; }
        .table thead th { text-transform: uppercase; font-size: 0.8rem; font-weight: 700; background-color: var(--light-gray); }
        .status-badge { font-size: 0.8rem; font-weight: 600; padding: .35em .65em; }
        .next-due.overdue { color: #dc3545; font-weight: 700; }
        .fading-out { transition: opacity 0.5s ease-out; opacity: 0; }
        .is-inactive td { color: #6c757d; }
        .is-inactive .badge { filter: saturate(0.5); }
        .card-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 24px; }
        .is-valid { border-color: #198754 !important; }
        .is-invalid { border-color: #dc3545 !important; }
        .actions-cell .btn { margin-bottom: 5px; margin-right: 5px; } /* Ensure buttons stack nicely */
        @media(max-width: 991.98px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 1050; } .sidebar.offcanvas.show { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-header { display: flex; justify-content: space-between; align-items: center; } }
    </style>
</head>
<body>

    <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
        <div class="logo"><a href="book-dashboard.php"><img src="assets/images/bgfunlogo.png" alt="BigFun Logo"></a></div>
        <button class="btn btn-cart w-100 mb-4" onclick="window.location.href='cart.php'">Cart (<?php echo $cart_count; ?>)</button>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="book-dashboard.php" class="nav-link"><span class="material-symbols-outlined">storefront</span>Services</a></li>
            <li class="nav-item"><a href="booking-status.php" class="nav-link"><span class="material-symbols-outlined">history</span>Booking Status</a></li>
            <li class="nav-item"><a href="invoices.php" class="nav-link active"><span class="material-symbols-outlined">receipt_long</span>Invoice and Payment</a></li>
            
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
        <div class="card content-card">
            <div class="card-body p-4">
                <h1 class="mb-2">My Invoices & Payments</h1>
                <p class="text-muted mb-4">Review your order history and manage payments.</p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>Order ID</th><th>Order Date</th><th>Total</th><th>Remaining</th><th>Next Due</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody id="invoices-table-body">
                        <?php if (empty($orders)): ?>
                            <tr class="no-orders-row"><td colspan="7" class="text-center p-5">You have no orders yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): 
                                $booking_status = $order['booking_status'];
                                $payment_status = $order['payment_status'];
                                $is_inactive = in_array($booking_status, ['Cancelled', 'Pending Cancellation', 'Refunded']);
                                $status_text = $payment_status;
                                $status_class = '';
                                $row_class = '';
                                
                                if ($is_inactive) {
                                    $row_class = 'is-inactive';
                                    switch($booking_status) {
                                        case 'Pending Cancellation':
                                            $status_text = 'Pending Cancel';
                                            $status_class = 'bg-warning text-dark';
                                            break;
                                        case 'Refunded':
                                            $status_text = 'Refunded';
                                            $status_class = 'bg-info text-dark';
                                            break;
                                        case 'Cancelled':
                                            $status_text = 'Cancelled';
                                            $status_class = 'bg-danger';
                                            break;
                                    }
                                } else {
                                    $status_map = [
                                        'Paid' => 'bg-success', 
                                        'Pending' => 'bg-warning text-dark', 
                                        'Partially Paid' => 'bg-info text-dark', 
                                        'For Verification' => 'bg-primary',
                                        'default' => 'bg-secondary'
                                    ];
                                    $status_class = $status_map[$payment_status] ?? $status_map['default'];
                                }
                            ?>
                                <tr id="order-row-<?php echo $order['id']; ?>" class="<?php echo $row_class; ?>">
                                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                                    <td><?php echo date("M j, Y", strtotime($order['order_date'])); ?></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    
                                    <?php if($is_inactive): ?>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    <?php else: 
                                        $remaining_balance = floatval($order['remaining_balance']);
                                        // Fix 1: Check for valid, non-empty date string
                                        $next_due_date = (!empty($order['next_due_date']) && $order['next_due_date'] !== '0000-00-00') ? new DateTime($order['next_due_date']) : null;
                                        $is_overdue = $next_due_date && $next_due_date < new DateTime('today');
                                    ?>
                                        <!-- Fix 2: Check for 'Paid' status in Remaining column -->
                                        <td class="remaining-amount">
                                            <?php 
                                            if ($payment_status === 'Paid') {
                                                echo '$0.00';
                                            } else {
                                                echo '$' . number_format($remaining_balance, 2); 
                                            }
                                            ?>
                                        </td>
                                        <td class="next-due <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                            <?php
                                            if ($payment_status === 'Paid' || $remaining_balance < 0.01) {
                                                echo 'Paid in Full';
                                            } else {
                                                // Fix 1: Only show date if method is Installment and date is valid
                                                if ($order['payment_method'] === 'Installment' && $next_due_date) {
                                                    echo $next_due_date->format("M j, Y");
                                                } else {
                                                    echo 'N/A';
                                                }
                                            }
                                            ?>
                                        </td>
                                    <?php endif; ?>

                                    <td><span class="badge rounded-pill <?php echo $status_class; ?> status-badge"><?php echo htmlspecialchars($status_text); ?></span></td>
                                    
                                    <td class="text-end actions-cell">
                                        <button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View Details</button>
                                        
                                        <?php if ($is_inactive): ?>
                                            <?php if(in_array($booking_status, ['Cancelled', 'Refunded'])): ?>
                                            <button class="btn btn-sm btn-outline-danger remove-order-btn" data-order-id="<?php echo $order['id']; ?>" title="Remove this record"><i class="fa-solid fa-trash-can"></i></button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php
                                            // Fix 3: New Button Logic
                                            if ($payment_status != 'Paid' && $remaining_balance > 0) {
                                                if ($order['payment_method'] === 'Cash' && $payment_status === 'Pending') {
                                                    echo '<button class="btn btn-sm btn-info submit-ref-btn" data-order-id="' . $order['id'] . '">Submit Ref Code</button>';
                                                
                                                } elseif ($order['payment_method'] === 'Installment' && $payment_status !== 'For Verification') {
                                                    $total_amount = floatval($order['total_amount']);
                                                    $installment_plan = intval($order['installment_plan']);
                                                    $installment_amount = 0;
                                                    if ($installment_plan > 0) {
                                                        // Calculate monthly installment based on total amount and plan
                                                        $installment_amount = round($total_amount / $installment_plan, 2);
                                                    }
                                                    // Show "Pay Installment" button
                                                    echo '<button class="btn btn-sm btn-success pay-installment-btn" data-order-id="' . $order['id'] . '" data-amount="' . number_format($installment_amount, 2, '.', '') . '">Pay Installment ($' . number_format($installment_amount, 2) . ')</button>';
                                                    
                                                    // Also show "Pay Full Balance" button
                                                    echo '<button class="btn btn-sm btn-primary pay-now-btn" data-order-id="' . $order['id'] . '" data-amount="' . number_format($remaining_balance, 2, '.', '') . '">Pay Full Balance</button>';

                                                } elseif ($order['payment_method'] === 'Card' && $payment_status !== 'For Verification') {
                                                    // Existing Full Balance Button for one-time card payments
                                                    echo '<button class="btn btn-sm btn-primary pay-now-btn" data-order-id="' . $order['id'] . '" data-amount="' . number_format($remaining_balance, 2, '.', '') . '">Pay Full Balance</button>';
                                                }
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="orderDetailsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Order Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="orderDetailsContent"></div></div></div></div>
    
    <!-- Fix 3: Added hidden payment-type field to modal form -->
    <div class="modal fade" id="paymentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Complete Your Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>You are about to make a payment for this order.</p><h4 class="text-center">Amount Due: <span id="payment-amount"></span></h4><form id="paymentForm"><input type="hidden" id="payment-order-id" name="order_id"><input type="hidden" id="payment-order-amount" name="amount"><input type="hidden" id="payment-type" name="payment_type"><div class="p-3 border rounded-3 my-3"><div class="card-number-wrapper mb-2"><input type="text" class="form-control" placeholder="Card Number" id="modalCardNumber" required><span class="card-icon" id="modalCardIcon"></span></div><div class="row g-2"><div class="col-md-6"><input type="text" class="form-control" placeholder="MM / YY" id="modalCardExpiry" required></div><div class="col-md-6"><input type="text" class="form-control" placeholder="CVV" id="modalCardCvv" required></div></div></div><button type="submit" class="btn btn-primary w-100">Confirm Payment</button></form></div></div></div></div>
    
    <div class="modal fade" id="paymentSuccessModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center p-4"><div class="modal-body"><span class="material-symbols-outlined text-success" style="font-size: 80px; font-variation-settings: 'FILL' 1;">task_alt</span><h3 class="mt-3" style="font-family: 'Inria Sans', sans-serif;">Payment Successful!</h3><p class="text-muted" id="success-modal-message">Thank you! Your order is now fully paid.</p><button type="button" class="btn" style="background-color: var(--primary-color); color: white;" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="refCodeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Submit Cash Payment Reference</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>After paying onsite, enter the reference code provided by our staff to mark this order for verification.</p><form id="refCodeForm"><input type="hidden" id="ref-order-id" name="order_id"><div class="mb-3"><label for="reference-code" class="form-label">Reference Code</label><input type="text" class="form-control" id="reference-code" name="reference_code" required></div><button type="submit" class="btn btn-primary w-100">Submit for Verification</button></form></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const successModal = new bootstrap.Modal(document.getElementById('paymentSuccessModal'));
    const refCodeModal = new bootstrap.Modal(document.getElementById('refCodeModal'));

    document.querySelector('#invoices-table-body').addEventListener('click', async function(e) {
        
        const viewBtn = e.target.closest('.view-details-btn');
        const payBtn = e.target.closest('.pay-now-btn');
        const installmentPayBtn = e.target.closest('.pay-installment-btn'); // Fix 3: Listen for new button
        const removeBtn = e.target.closest('.remove-order-btn');
        const refBtn = e.target.closest('.submit-ref-btn');

        if (viewBtn) {
            const orderId = viewBtn.dataset.orderId;
            const contentDiv = document.getElementById('orderDetailsContent');
            contentDiv.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>';
            detailsModal.show();
            try {
                const response = await fetch(`backend/get_order_details.php?order_id=${orderId}`);
                const data = await response.json();
                if (data.status === 'success') {
                    let noticeHtml = '';
                    if (data.order.cancellation_notice) {
                        noticeHtml = `<div class="alert alert-warning" role="alert"><strong>Status Update:</strong> ${data.order.cancellation_notice}</div>`;
                    }
                    
                    let itemsHtml = data.items.map(item => `<tr><td>${item.service_name} (x${item.quantity})</td><td class="text-end">$${parseFloat(item.price_per_item * item.quantity).toFixed(2)}</td></tr>`).join('');
                    let paymentsHtml = data.payments.map(p => `<tr><td>${new Date(p.payment_date).toLocaleDateString()}</td><td>${p.payment_type}</td><td class="text-end">$${parseFloat(p.payment_amount).toFixed(2)}</td></tr>`).join('');
                    
                    contentDiv.innerHTML = `
                        ${noticeHtml}
                        <h5>Order #${data.order.id} Details</h5>
                        <p><strong>Booking Status:</strong> ${data.order.booking_status}<br><strong>Payment Status:</strong> ${data.order.payment_status}</p>
                        <h6>Items</h6><table class="table table-sm"><tbody>${itemsHtml}</tbody></table>
                        <h6>Payments</h6><table class="table table-sm"><thead><tr><th>Date</th><th>Type</th><th class="text-end">Amount</th></tr></thead><tbody>${paymentsHtml || '<tr><td colspan=3 class="text-center">No payments made.</td></tr>'}</tbody></table>
                        <hr><h5 class="text-end">Total: $${parseFloat(data.order.total_amount).toFixed(2)} | Remaining: $${parseFloat(data.order.remaining_balance).toFixed(2)}</h5>
                    `;
                } else { contentDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`; }
            } catch (error) { contentDiv.innerHTML = `<div class="alert alert-danger">Could not load details.</div>`; }
        }
        
        // Fix 3: Handle "Pay Full Balance" button
        if (payBtn) {
            document.getElementById('payment-amount').textContent = '$' + parseFloat(payBtn.dataset.amount).toFixed(2);
            document.getElementById('payment-order-id').value = payBtn.dataset.orderId;
            document.getElementById('payment-order-amount').value = payBtn.dataset.amount;
            document.getElementById('payment-type').value = 'full'; // Set payment type
            paymentModal.show();
        }
        
        // Fix 3: Handle "Pay Installment" button
        if (installmentPayBtn) {
            document.getElementById('payment-amount').textContent = '$' + parseFloat(installmentPayBtn.dataset.amount).toFixed(2);
            document.getElementById('payment-order-id').value = installmentPayBtn.dataset.orderId;
            document.getElementById('payment-order-amount').value = installmentPayBtn.dataset.amount;
            document.getElementById('payment-type').value = 'installment'; // Set payment type
            paymentModal.show();
        }

        if (removeBtn) {
            const orderId = removeBtn.dataset.orderId;
            if (confirm(`Are you sure you want to permanently remove order #${orderId} from your history? This cannot be undone.`)) {
                try {
                    const formData = new FormData();
                    formData.append('order_id', orderId);
                    const response = await fetch('backend/remove_order.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.status === 'success') {
                        const row = document.getElementById(`order-row-${orderId}`);
                        row.classList.add('fading-out');
                        setTimeout(() => row.remove(), 500);
                    } else { alert(`Error: ${data.message}`); }
                } catch (error) { alert('Could not remove order.'); }
            }
        }
        
        if (refBtn) {
            document.getElementById('ref-order-id').value = refBtn.dataset.orderId;
            refCodeModal.show();
        }
    });

    document.getElementById('refCodeForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const orderId = document.getElementById('ref-order-id').value;
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
        try {
            const formData = new FormData(this);
            const response = await fetch('backend/submit_reference_code.php', { method: 'POST', body: formData });
            const data = await response.json();
            refCodeModal.hide();
            if (data.status === 'success') {
                const orderRow = document.getElementById(`order-row-${orderId}`);
                if (orderRow) {
                    const statusBadge = orderRow.querySelector('.status-badge');
                    statusBadge.className = 'badge rounded-pill bg-primary status-badge';
                    statusBadge.textContent = 'For Verification';
                    orderRow.querySelector('.submit-ref-btn')?.remove();
                }
                alert(data.message);
            } else { alert(`Error: ${data.message}`); }
        } catch (error) { alert('An error occurred. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit for Verification';
            this.reset();
        }
    });

    // Fix 3: Updated Payment Form Submission
    document.getElementById('paymentForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const orderId = document.getElementById('payment-order-id').value;
        const payButton = this.querySelector('button[type="submit"]');
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        const formData = new FormData(this);
        const paymentType = formData.get('payment_type');
        
        try {
            // This backend script MUST be updated to handle 'payment_type'
            const response = await fetch('backend/pay_invoice.php', { method: 'POST', body: formData });
            const data = await response.json(); 
            paymentModal.hide();
            
            if (data.status === 'success') {
                
                // Customize success message
                if (paymentType === 'installment') {
                    document.getElementById('success-modal-message').textContent = 'Your installment payment was successful.';
                } else {
                    document.getElementById('success-modal-message').textContent = 'Thank you! Your order is now fully paid.';
                }
                successModal.show();
                
                const orderRow = document.getElementById(`order-row-${orderId}`);
                if (orderRow) {
                    // Update row based on data from backend
                    // Assumes backend returns: new_remaining_balance, new_payment_status, new_next_due_date
                    orderRow.querySelector('.remaining-amount').textContent = '$' + parseFloat(data.new_remaining_balance).toFixed(2);
                    
                    if (data.new_payment_status === 'Paid') {
                        orderRow.querySelector('.next-due').textContent = 'Paid in Full';
                        const statusBadge = orderRow.querySelector('.status-badge');
                        statusBadge.className = 'badge rounded-pill bg-success status-badge';
                        statusBadge.textContent = 'Paid';
                        orderRow.querySelector('.pay-now-btn')?.remove();
                        orderRow.querySelector('.pay-installment-btn')?.remove();
                    
                    } else if (data.new_payment_status === 'Partially Paid') {
                        // Update Next Due date
                        const nextDueCell = orderRow.querySelector('.next-due');
                        if (data.new_next_due_date && data.new_next_due_date !== '0000-00-00') {
                             nextDueCell.textContent = new Date(data.new_next_due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                             nextDueCell.classList.remove('overdue'); // Assume new date is not overdue
                        } else {
                             nextDueCell.textContent = 'N/A';
                        }
                        
                        // Update Status Badge
                        const statusBadge = orderRow.querySelector('.status-badge');
                        statusBadge.className = 'badge rounded-pill bg-info text-dark status-badge';
                        statusBadge.textContent = 'Partially Paid';
                        
                        // Update "Pay Full Balance" button amount
                        const payFullBtn = orderRow.querySelector('.pay-now-btn');
                        if (payFullBtn) {
                            payFullBtn.dataset.amount = parseFloat(data.new_remaining_balance).toFixed(2);
                        }
                    }
                }
            } else { alert(data.message || 'Payment failed'); }
        } catch (error) { alert('An error occurred.'); }
        finally {
            payButton.disabled = false;
            payButton.innerHTML = 'Confirm Payment';
        }
    });
    
    // Card validation logic (unchanged)
    const cardNumberInput=document.getElementById("modalCardNumber"),cardExpiryInput=document.getElementById("modalCardExpiry"),cardCvvInput=document.getElementById("modalCardCvv"),cardIcon=document.getElementById("modalCardIcon");cardNumberInput.addEventListener("input",function(e){let t=e.target.value.replace(/\D/g,""),a="";if(/^3[47]/.test(t)){t=t.substring(0,15);for(let e=0;e<t.length;e++)(4===e||10===e)&&(a+=" "),a+=t[e]}else{t=t.substring(0,16);for(let e=0;e<t.length;e++)e>0&&e%4==0&&(a+=" "),a+=t[e]}e.target.value=a,cardIcon.innerHTML=/^4/.test(t)?'<i class="fa-brands fa-cc-visa" style="color:#1a1f71;"></i>':/^5[1-5]/.test(t)?'<i class="fa-brands fa-cc-mastercard" style="color:#f69e1f;"></i>':/^3[47]/.test(t)?'<i class="fa-brands fa-cc-amex" style="color:#2e77bc;"></i>':"";let l=0,s=!1;for(let e=t.length-1;e>=0;e--){let a=parseInt(t.charAt(e));s&&((a*=2)>9&&(a-=9)),l+=a,s=!s}const n=l%10==0;cardNumberInput.classList.remove("is-valid","is-invalid"),t.length>0&&(n&&((/^3[47]/.test(t)&&15===t.length)||16===t.length)?cardNumberInput.classList.add("is-valid"):cardNumberInput.classList.add("is-invalid"))}),cardExpiryInput.addEventListener("input",function(e){let t=e.target.value.replace(/\D/g,"").substring(0,4);e.target.value=t.length>2?t.substring(0,2)+" / "+t.substring(2):t}),cardCvvInput.addEventListener("input",function(e){let t=/^3[47]/.test(cardNumberInput.value.replace(/\D/g,""));e.target.value=e.target.value.replace(/\D/g,"").substring(0,t?4:3)});
});
</script>
</body>
</html>




