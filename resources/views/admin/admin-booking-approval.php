<?php
session_start();
// Security check: Ensure an admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "backend/db.php";

$pending_actions = [];
$other_bookings = [];

// Get ALL bookings, and join signup to get customer name
$stmt = $conn->prepare("
    SELECT o.*, s.first_name, s.last_name 
    FROM orders o
    JOIN signup s ON o.user_id = s.id
    ORDER BY 
        CASE 
            WHEN o.booking_status = 'Pending Confirmation' THEN 1
            WHEN o.booking_status = 'Pending Cancellation' THEN 2
            ELSE 3
        END, 
        o.order_date DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (in_array($row['booking_status'], ['Pending Confirmation', 'Pending Cancellation'])) {
        $pending_actions[] = $row;
    } else {
        $other_bookings[] = $row;
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Booking Approval</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #802080; --primary-light: #FFEFFC; --light-gray: #f8f9fa; --border-color: #dee2e6; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 260px; background-color: #fff; border-right: 1px solid var(--border-color); padding: 20px; display: flex; flex-direction: column; }
        .main-content { margin-left: 260px; padding: 30px; }
        .sidebar .logo { text-align: center; margin-bottom: 30px; }
        .sidebar .logo img { max-height: 50px; }
        .sidebar .nav-pills .nav-link { color: #555; font-size: 15px; font-weight: 600; padding: 12px 15px; display: flex; align-items: center; gap: 10px; }
        .sidebar .nav-pills .nav-link.active, .sidebar .nav-pills .nav-link:hover { background-color: var(--primary-light); color: var(--primary-color); }
        .sidebar .logout-link { margin-top: auto; }
        .content-card { background-color: #fff; border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .page-header h1 { font-size: 1.75rem; font-weight: 600; font-family: 'Inria Sans', sans-serif; }
        .approval-table { vertical-align: middle; }
        .approval-table thead th { font-weight: 600; color: #6c757d; font-size: 0.9rem; }
        .fading-out { transition: opacity 0.5s ease-out; opacity: 0; }
        .date-overdue { color: #dc3545; font-weight: 700; }
        @media(max-width: 991.98px) { .sidebar { transform: translateX(-100%); z-index: 1050; } .sidebar.offcanvas.show { transform: translateX(0); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
        <div class="logo"><img src="assets/images/bgfunlogo.png" alt="BigFun Logo"></div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="admin-dashboard.php" class="nav-link"><span class="material-symbols-outlined">dashboard</span>Dashboard Overview</a></li>
            <li class="nav-item"><a href="admin-appointments.php" class="nav-link"><span class="material-symbols-outlined">event_note</span>Booked Appointments</a></li>
            <li class="nav-item"><a href="admin-booking-approval.php" class="nav-link active"><span class="material-symbols-outlined">checklist</span>Booking Approval</a></li>
            <li class="nav-item"><a href="admin-services.php" class="nav-link"><span class="material-symbols-outlined">design_services</span>Services Administration</a></li>
            <li class="nav-item"><a href="admin-customers.php" class="nav-link"><span class="material-symbols-outlined">group</span>Customers Management</a></li>
            <li class="nav-item"><a href="admin-manage.php" class="nav-link"><span class="material-symbols-outlined">person</span>Profile</a></li>
        </ul>
        <div class="logout-link"><a href="backend/logout-admin.php" class="btn btn-light w-100">Log Out</a></div>
    </div>

    <main class="main-content">
        <div class="page-header mb-4">
            <h1>Booking Approval Management</h1>
            <p class="text-muted">Confirm new bookings and manage customer cancellation requests.</p>
        </div>
        
        <div class="content-card mb-4">
            <div class="card-header bg-warning-subtle" style="border-radius: 15px 15px 0 0; padding: 1.25rem;">
                <h5 class="mb-0" style="font-family: 'Inria Sans', sans-serif; font-weight: 700;">Bookings Awaiting Action (<?php echo count($pending_actions); ?>)</h5>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table approval-table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Event Date</th>
                                <th>Status</th>
                                <th>Reason/Details</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="approval-table-body">
                            <?php if (empty($pending_actions)): ?>
                                <tr><td colspan="6" class="text-center p-5 text-muted">No bookings are currently awaiting action.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending_actions as $booking): ?>
                                    <tr id="booking-row-<?php echo $booking['id']; ?>">
                                        <td><strong>#<?php echo $booking['id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                        <td><?php echo date("M j, Y", strtotime($booking['date_event'])); ?></td>
                                        <td>
                                            <?php if ($booking['booking_status'] == 'Pending Confirmation'): ?>
                                                <span class="badge bg-primary">New Booking</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Cancellation Request</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['booking_status'] == 'Pending Cancellation'): ?>
                                                <em><?php echo htmlspecialchars($booking['cancellation_reason']); ?></em>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($booking['booking_status'] == 'Pending Confirmation'): ?>
                                                <button class="btn btn-sm btn-success confirm-booking-btn" data-order-id="<?php echo $booking['id']; ?>">Confirm</button>
                                                <button class="btn btn-sm btn-danger admin-cancel-btn" data-order-id="<?php echo $booking['id']; ?>">Cancel</button>
                                            <?php else: // Pending Cancellation ?>
                                                <button class="btn btn-sm btn-success approve-refund-btn" 
                                                    data-order-id="<?php echo $booking['id']; ?>"
                                                    data-total-paid="<?php echo $booking['total_amount'] - $booking['remaining_balance']; ?>">
                                                    Approve Refund
                                                </button>
                                                <button class="btn btn-sm btn-secondary deny-cancellation-btn" data-order-id="<?php echo $booking['id']; ?>">Deny Request</button>
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

        <div class="content-card">
            <div class="card-header bg-white" style="border-radius: 15px 15px 0 0; padding: 1.25rem;">
                <h5 class="mb-0" style="font-family: 'Inria Sans', sans-serif; font-weight: 700;">All Other Bookings</h5>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table approval-table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Event Date</th>
                                <th>Booking Status</th>
                                <th>Payment Status</th>
                                <th>Next Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($other_bookings)): ?>
                                <tr><td colspan="6" class="text-center p-5 text-muted">No other bookings found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($other_bookings as $booking): ?>
                                <tr>
                                    <td><strong>#<?php echo $booking['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($booking['date_event'])); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch($booking['booking_status']) {
                                                case 'Confirmed': echo 'bg-success'; break;
                                                case 'Cancelled': echo 'bg-danger'; break;
                                                case 'Refunded': echo 'bg-info'; break;
                                                default: echo 'bg-secondary';
                                            }
                                            ?>">
                                            <?php echo $booking['booking_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch($booking['payment_status']) {
                                                case 'Paid': echo 'bg-success-subtle text-success-emphasis'; break;
                                                case 'Partially Paid': echo 'bg-info-subtle text-info-emphasis'; break;
                                                case 'Pending': echo 'bg-warning-subtle text-warning-emphasis'; break;
                                                // --- ADDED: A new status from your backend logic ---
                                                case 'Refund Required': echo 'bg-danger-subtle text-danger-emphasis'; break; 
                                                default: echo 'bg-secondary-subtle text-secondary-emphasis';
                                            }
                                            ?>">
                                            <?php echo $booking['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($booking['next_due_date']) && $booking['next_due_date'] != '0000-00-00') {
                                            $due_date = new DateTime($booking['next_due_date']);
                                            $today = new DateTime('today');
                                            $is_overdue = $due_date < $today && in_array($booking['payment_status'], ['Pending', 'Partially Paid']);
                                            echo '<span class="' . ($is_overdue ? 'date-overdue' : '') . '">' . $due_date->format("M j, Y") . '</span>';
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <div class="modal fade" id="adminCancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Reason for Cancellation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form id="adminCancelForm">
                    <div class="modal-body">
                        <p>Provide a reason for cancelling Order #<strong id="cancel-modal-order-id"></strong>. The customer will be notified.</p>
                        <input type="hidden" name="action" value="admin_cancel_booking">
                        <input type="hidden" name="order_id" id="admin-cancel-order-id">
                        <textarea name="cancellation_reason" class="form-control" rows="3" placeholder="e.g., Scheduling conflict, unable to fulfill service..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Submit Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="approveRefundModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Approve Cancellation & Refund</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form id="approveRefundForm">
            <div class="modal-body">
              <p>You are about to approve the cancellation for <strong>Order #<span id="approve-order-id-text"></span></strong>.</p>
              <input type="hidden" name="action" value="approve_refund">
              <input type="hidden" name="order_id" id="approve-order-id">
              
              <div class="mb-3">
                  <label class="form-label">Total Amount Paid by Customer:</label>
                  <input type="text" id="approve-total-paid" class="form-control" disabled>
              </div>
              <div class="mb-3">
                  <label for="refund_amount" class="form-label">Refund Amount:</label>
                  <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="number" step="0.01" class="form-control" name="refund_amount" id="approve-refund-amount" required>
                  </div>
                  <small class="text-muted">You can issue a full or partial refund.</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-success">Confirm Refund</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="denyCancellationModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Deny Cancellation Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form id="denyCancellationForm">
            <div class="modal-body">
              <p>You are about to deny the cancellation for <strong>Order #<span id="deny-order-id-text"></span></strong>. The booking will be set back to "Confirmed".</p>
              <input type="hidden" name="action" value="deny_cancellation">
              <input type="hidden" name="order_id" id="deny-order-id">
              
              <div class="mb-3">
                  <label for="denial_reason" class="form-label">Reason for Denial (Sent to User):</label>
                  <textarea class="form-control" name="denial_reason" id="denial-reason" rows="3" required placeholder="e.g., 'Per our policy, cancellations are not allowed... A admin will contact you.'"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-danger">Confirm Denial</button>
            </div>
          </form>
        </div>
      </div>
    </div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const adminCancelModal = new bootstrap.Modal(document.getElementById('adminCancelModal'));
    const approveRefundModal = new bootstrap.Modal(document.getElementById('approveRefundModal'));
    const denyCancellationModal = new bootstrap.Modal(document.getElementById('denyCancellationModal'));

    // --- Unified Action Handler ---
    async function handleAdminAction(form, modalInstance, submitBtn) {
        const originalHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

        try {
            const formData = new FormData(form);
            const response = await fetch('backend/admin_process_booking.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (!response.ok || data.status !== 'success') {
                throw new Error(data.message || 'An unknown error occurred.');
            }

            // Visually remove the row from the table
            const orderId = formData.get('order_id');
            const row = document.getElementById(`booking-row-${orderId}`);
            if (row) {
                row.classList.add('fading-out');
                setTimeout(() => {
                    row.remove();
                    // We reload to see the item in the "All Other Bookings" table
                    window.location.reload(); 
                }, 500);
            } else {
                 window.location.reload();
            }
            alert(data.message); // Simple success feedback
            modalInstance.hide();

        } catch (error) {
            alert(`Error: ${error.message}`);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    }

    // --- Event Listeners for Buttons ---
    document.getElementById('approval-table-body').addEventListener('click', function(e) {
        const target = e.target;
        const orderId = target.dataset.orderId;

        // 1. Confirm New Booking (No Modal)
        if (target.matches('.confirm-booking-btn')) {
            if (confirm(`Are you sure you want to confirm Order #${orderId}?`)) {
                const formData = new FormData();
                formData.append('order_id', orderId);
                formData.append('action', 'confirm_booking');
                
                // This is a simple action, we can use a simplified handler
                const btn = target;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
                fetch('backend/admin_process_booking.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const row = document.getElementById(`booking-row-${orderId}`);
                            if (row) {
                                row.classList.add('fading-out');
                                setTimeout(() => window.location.reload(), 500);
                            } else {
                                window.location.reload();
                            }
                            alert(data.message);
                        } else {
                            throw new Error(data.message);
                        }
                    })
                    .catch(err => {
                        alert('Error: ' + err.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Confirm';
                    });
            }
        }
        
        // 2. Admin Initiates Cancellation (Opens Modal)
        if (target.matches('.admin-cancel-btn')) {
            document.getElementById('cancel-modal-order-id').textContent = orderId;
            document.getElementById('admin-cancel-order-id').value = orderId;
            
            // --- THIS IS THE FIX ---
            // The old line was: document.getElementById('admin-cancel-btn').value = '';
            // This finds the form by its ID and resets it, clearing the textarea
            document.getElementById('adminCancelForm').reset(); 
            
            adminCancelModal.show();
        }
        
        // 3. Approve Customer's Cancellation (Opens REFUND Modal)
        if (target.matches('.approve-refund-btn')) {
            const totalPaid = target.dataset.totalPaid;
            document.getElementById('approve-order-id-text').textContent = orderId;
            document.getElementById('approve-order-id').value = orderId;
            document.getElementById('approve-total-paid').value = '$' + parseFloat(totalPaid).toFixed(2);
            document.getElementById('approve-refund-amount').value = parseFloat(totalPaid).toFixed(2);
            approveRefundModal.show();
        }

        // 4. Deny Customer's Cancellation (Opens DENY Modal)
        if (target.matches('.deny-cancellation-btn')) {
            document.getElementById('deny-order-id-text').textContent = orderId;
            document.getElementById('deny-order-id').value = orderId;
            document.getElementById('denial-reason').value = '';
            denyCancellationModal.show();
        }
    });

    // --- Event Listeners for Modal Form Submissions ---
    document.getElementById('adminCancelForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleAdminAction(this, adminCancelModal, this.querySelector('button[type="submit"]'));
    });

    document.getElementById('approveRefundForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleAdminAction(this, approveRefundModal, this.querySelector('button[type="submit"]'));
    });

    document.getElementById('denyCancellationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleAdminAction(this, denyCancellationModal, this.querySelector('button[type="submit"]'));
    });
});
</script>
</body>
</html>




