<?php
session_start();

// Enforce security headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check user login status
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

include "backend/db.php";

$user_id = $_SESSION['user_id'];

// Get cart count
$cart_count = 0;
$count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$result = $count_stmt->get_result();
$cart_data = $result->fetch_assoc();
$cart_count = $cart_data['total_items'] ?? 0;
$count_stmt->close();

// Fetch all orders and categorize them
$upcoming_bookings = [];
$past_bookings = [];
$cancelled_bookings = [];

// Order by date, newest first
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY date_event DESC");
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$result = $order_stmt->get_result();

if ($result->num_rows > 0) {
    $today = new DateTime('today');
    while ($row = $result->fetch_assoc()) {
        if ($row['booking_status'] === 'Cancelled') {
            $cancelled_bookings[] = $row;
        } else {
            $event_date = new DateTime($row['date_event']);
            if ($event_date >= $today) {
                $upcoming_bookings[] = $row;
            } else {
                $past_bookings[] = $row;
            }
        }
    }
}
$order_stmt->close();
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
      .content-card h1 { font-family: 'Inria Sans', sans-serif; }
      .nav-tabs .nav-link { font-weight: 600; color: #6c757d; }
      .nav-tabs .nav-link.active { color: var(--primary-color); border-color: var(--primary-color) var(--primary-color) #fff; }
      .booking-card { background-color: #fff; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border-left: 5px solid var(--primary-color); }
      .booking-card.is-cancelled { border-left-color: #dc3545; }
      .booking-card.is-past { border-left-color: #6c757d; }
      .booking-card .card-header { background-color: transparent; font-family: 'Inria Sans', sans-serif; font-weight: 700; font-size: 1.2rem; }
      .status-badge { font-size: 0.8rem; font-weight: 600; padding: .4em .75em; }
      @media(max-width: 991.98px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 1050; } .sidebar.offcanvas.show { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-header { display: flex; justify-content: space-between; align-items: center; } }
    </style>
</head>
<body>

  <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
    <div class="logo"><a href="book-dashboard.php"><img src="images/bgfunlogo.png" alt="BigFun Logo"></a></div>
    <button class="btn btn-cart w-100 mb-4" onclick="window.location.href='cart.php'">Cart (<?php echo $cart_count; ?>)</button>
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item"><a href="book-dashboard.php" class="nav-link"><span class="material-symbols-outlined">storefront</span>Services</a></li>
      <li class="nav-item"><a href="booking-status.php" class="nav-link active"><span class="material-symbols-outlined">history</span>Booking Status</a></li>
      <li class="nav-item"><a href="invoices.php" class="nav-link"><span class="material-symbols-outlined">receipt_long</span>Invoice and Payment</a></li>
      <li class="nav-item"><a href="profile-manage.php" class="nav-link"><span class="material-symbols-outlined">person</span>Profile</a></li>
    </ul>
    <div class="logout-link"><a href="backend/logout.php" class="btn btn-light w-100">Log Out</a></div>
  </div>

  <header class="mobile-header">
    <img src="images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
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
                </select>
            </div>
            <div>
                <label for="paymentStatusFilter" class="form-label fw-bold">Filter by Payment Status</label>
                <select class="form-select" id="paymentStatusFilter">
                    <option value="all" selected>All</option>
                    <option value="Paid">Paid</option>
                    <option value="Partially Paid">Partially Paid</option>
                    <option value="Pending">Pending</option>
                </select>
            </div>
        </div>
        <div id="upcoming-bookings-list">
        <?php if(empty($upcoming_bookings)): ?>
            <div class="alert alert-info no-bookings-message">You have no upcoming bookings.</div>
        <?php else: foreach($upcoming_bookings as $order): 
            $booking_status_class = match($order['booking_status']) { 'Confirmed' => 'bg-success', default => 'bg-warning text-dark' };
            $payment_status_class = match($order['payment_status']) { 'Paid' => 'bg-success-subtle text-success-emphasis', 'Pending' => 'bg-warning-subtle text-warning-emphasis', 'Partially Paid' => 'bg-info-subtle text-info-emphasis', default => 'bg-secondary-subtle' };
        ?>
        <div class="card booking-card mb-3" data-booking-status="<?php echo htmlspecialchars($order['booking_status']); ?>" data-payment-status="<?php echo htmlspecialchars($order['payment_status']); ?>">
          <div class="card-header d-flex justify-content-between align-items-center">Order #<?php echo $order['id']; ?><span class="badge rounded-pill <?php echo $booking_status_class; ?> status-badge"><?php echo htmlspecialchars($order['booking_status']); ?></span></div>
          <div class="card-body">
            <div class="row align-items-center gy-3">
                <div class="col-md-4"><strong>Event Date:</strong><br><?php echo date("l, F j, Y", strtotime($order['date_event'])); ?></div>
                <div class="col-md-3"><strong>Total Amount:</strong><br>$<?php echo number_format($order['total_amount'], 2); ?></div>
                <div class="col-md-2"><strong>Payment:</strong><br><span class="badge rounded-pill <?php echo $payment_status_class; ?> status-badge"><?php echo htmlspecialchars($order['payment_status']); ?></span></div>
                <div class="col-md-3 text-md-end actions-cell">
                    <button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View Details</button>
                    <?php if ($order['booking_status'] !== 'Confirmed'): ?>
                    <button class="btn btn-sm btn-outline-danger cancel-btn" data-order-id="<?php echo $order['id']; ?>" data-order-date="<?php echo date("F j, Y", strtotime($order['date_event'])); ?>">Cancel</button>
                    <?php endif; ?>
                </div>
            </div>
          </div>
        </div>
        <?php endforeach; endif; ?>
        </div>
      </div>
      
      <div class="tab-pane fade" id="past-pane" role="tabpanel">
        <?php if(empty($past_bookings)): ?>
            <div class="alert alert-info">You have no past bookings.</div>
        <?php else: foreach($past_bookings as $order): ?>
        <div class="card booking-card is-past mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">Order #<?php echo $order['id']; ?><span class="badge rounded-pill bg-primary status-badge">Completed</span></div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4"><strong>Event Date:</strong><br><?php echo date("l, F j, Y", strtotime($order['date_event'])); ?></div>
                    <div class="col-md-5"><strong>Total Amount:</strong><br>$<?php echo number_format($order['total_amount'], 2); ?></div>
                    <div class="col-md-3 text-end"><button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View Details</button></div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
      
      <div class="tab-pane fade" id="cancelled-pane" role="tabpanel">
        <?php if(empty($cancelled_bookings)): ?>
            <div class="alert alert-info">You have no cancelled bookings.</div>
        <?php else: foreach($cancelled_bookings as $order): ?>
        <div class="card booking-card is-cancelled mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">Order #<?php echo $order['id']; ?><span class="badge rounded-pill bg-danger status-badge">Cancelled</span></div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4"><strong>Event Date:</strong><br><?php echo date("l, F j, Y", strtotime($order['date_event'])); ?></div>
                    <div class="col-md-5"><strong>Total Amount:</strong><br>$<?php echo number_format($order['total_amount'], 2); ?></div>
                    <div class="col-md-3 text-end"><button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View Details</button></div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <div class="modal fade" id="orderDetailsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Order Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="orderDetailsContent"></div></div></div></div>
  <div class="modal fade" id="cancelConfirmModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirm Cancellation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="cancelForm"><p>Are you sure you want to cancel your booking for <strong id="cancel-date-text"></strong>?</p><div class="mb-3"><label for="cancel-reason" class="form-label">Reason for Cancellation (Optional):</label><textarea class="form-control" id="cancel-reason" name="reason" rows="3"></textarea></div><p class="text-danger small">This action cannot be undone.</p><input type="hidden" id="cancel-order-id" name="order_id"><div class="modal-footer pb-0 border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back</button><button type="submit" class="btn btn-danger">Yes, Cancel Booking</button></div></form></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));

    // ** REWORKED JAVASCRIPT **
    
    // --- Event Delegation for Buttons ---
    document.querySelector('.main-content').addEventListener('click', async function(e) {
        // View Details Button
        const viewBtn = e.target.closest('.view-details-btn');
        if (viewBtn) {
            const orderId = viewBtn.dataset.orderId;
            const contentDiv = document.getElementById('orderDetailsContent');
            contentDiv.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>';
            detailsModal.show();
            
            try {
                const response = await fetch(`backend/get_order_details.php?order_id=${orderId}`);
                const data = await response.json();
                if (data.status === 'success') {
                    let itemsHtml = data.items.map(item => `<tr><td>${item.service_name} (x${item.quantity})</td><td class="text-end">$${parseFloat(item.price_per_item * item.quantity).toFixed(2)}</td></tr>`).join('');
                    let paymentsHtml = data.payments.map(p => `<tr><td>${new Date(p.payment_date).toLocaleDateString()}</td><td>${p.payment_type}</td><td class="text-end">$${parseFloat(p.payment_amount).toFixed(2)}</td></tr>`).join('');
                    contentDiv.innerHTML = `<h5>Order #${data.order.id} Details</h5><p><strong>Booking Status:</strong> ${data.order.booking_status}<br><strong>Payment Status:</strong> ${data.order.payment_status}</p><h6>Items Booked</h6><table class="table table-sm"><tbody>${itemsHtml}</tbody></table><h6>Payment History</h6><table class="table table-sm"><thead><tr><th>Date</th><th>Type</th><th class="text-end">Amount</th></tr></thead><tbody>${paymentsHtml || '<tr><td colspan=3 class="text-center">No payments made yet.</td></tr>'}</tbody></table><hr><h5 class="text-end">Total: $${parseFloat(data.order.total_amount).toFixed(2)} | Remaining: $${parseFloat(data.order.remaining_balance).toFixed(2)}</h5>`;
                } else {
                    contentDiv.innerHTML = `<p class="text-danger">${data.message || 'Could not load details.'}</p>`;
                }
            } catch (error) {
                contentDiv.innerHTML = '<p class="text-danger">An error occurred while fetching details.</p>';
            }
        }

        // Cancel Button
        const cancelBtn = e.target.closest('.cancel-btn');
        if (cancelBtn) {
            document.getElementById('cancel-order-id').value = cancelBtn.dataset.orderId;
            document.getElementById('cancel-date-text').textContent = cancelBtn.dataset.orderDate;
            document.getElementById('cancelForm').reset();
            cancelModal.show();
        }
    });

    // --- Cancellation Form Submission ---
    document.getElementById('cancelForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cancelling...';
        
        try {
            const formData = new FormData(this);
            const response = await fetch('backend/cancel_booking.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                // Use a more modern notification if you have one, like a toast
                alert('Your booking has been successfully cancelled.');
                window.location.reload(); 
            } else {
                alert(data.message || 'Cancellation failed. Please try again.');
            }
        } catch (error) {
            alert('An error occurred. Please check your connection and try again.');
        } finally {
            cancelModal.hide();
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Yes, Cancel Booking';
        }
    });

    // --- ** NEW FILTER LOGIC ** ---
    const bookingStatusFilter = document.getElementById('bookingStatusFilter');
    const paymentStatusFilter = document.getElementById('paymentStatusFilter');
    const upcomingList = document.getElementById('upcoming-bookings-list');

    function applyFilters() {
        const selectedBookingStatus = bookingStatusFilter.value;
        const selectedPaymentStatus = paymentStatusFilter.value;
        const cards = upcomingList.querySelectorAll('.booking-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const cardBookingStatus = card.dataset.bookingStatus;
            const cardPaymentStatus = card.dataset.paymentStatus;

            const bookingMatch = (selectedBookingStatus === 'all' || cardBookingStatus === selectedBookingStatus);
            const paymentMatch = (selectedPaymentStatus === 'all' || cardPaymentStatus === selectedPaymentStatus);

            if (bookingMatch && paymentMatch) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        let noBookingsMsg = upcomingList.querySelector('.no-bookings-message');
        // Remove existing message if it exists
        if (noBookingsMsg) {
            noBookingsMsg.remove();
        }

        // Add a new message if no cards are visible and there are cards to filter
        if (visibleCount === 0 && cards.length > 0) {
            const newAlert = document.createElement('div');
            newAlert.className = 'alert alert-info no-bookings-message';
            newAlert.textContent = 'No upcoming bookings match the selected filters.';
            upcomingList.appendChild(newAlert);
        }
    }

    // Attach event listeners to both filters
    bookingStatusFilter.addEventListener('change', applyFilters);
    paymentStatusFilter.addEventListener('change', applyFilters);
});
</script>
</body>
</html>