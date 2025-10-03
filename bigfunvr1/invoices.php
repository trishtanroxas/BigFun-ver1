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
        .is-cancelled td { color: #6c757d; }
        .card-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 24px; }
        .is-valid { border-color: #198754 !important; }
        .is-invalid { border-color: #dc3545 !important; }
        @media(max-width: 991.98px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 1050; } .sidebar.offcanvas.show { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-header { display: flex; justify-content: space-between; align-items: center; } }
    </style>
</head>
<body>

    <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
        <div class="logo"><a href="book-dashboard.php"><img src="images/bgfunlogo.png" alt="BigFun Logo"></a></div>
        <button class="btn btn-cart w-100 mb-4" onclick="window.location.href='cart.php'">Cart (<?php echo $cart_count; ?>)</button>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="book-dashboard.php" class="nav-link"><span class="material-symbols-outlined">storefront</span>Services</a></li>
            <li class="nav-item"><a href="booking-status.php" class="nav-link"><span class="material-symbols-outlined">history</span>Booking Status</a></li>
            <li class="nav-item"><a href="invoices.php" class="nav-link active"><span class="material-symbols-outlined">receipt_long</span>Invoice and Payment</a></li>
            <li class="nav-item"><a href="profile-manage.php" class="nav-link"><span class="material-symbols-outlined">person</span>Profile</a></li>
        </ul>
        <div class="logout-link"><a href="backend/logout.php" class="btn btn-light w-100">Log Out</a></div>
    </div>

    <header class="mobile-header">
        <img src="images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
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
                            <?php foreach ($orders as $order): ?>
                                <?php if ($order['booking_status'] === 'Cancelled'): ?>
                                    <tr id="order-row-<?php echo $order['id']; ?>" class="is-cancelled">
                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                        <td><?php echo date("M j, Y", strtotime($order['order_date'])); ?></td>
                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td><span class="badge rounded-pill bg-danger status-badge">Cancelled</span></td>
                                        <td class="text-end actions-cell">
                                            <button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View Details</button>
                                            <button class="btn btn-sm btn-outline-danger remove-order-btn" data-order-id="<?php echo $order['id']; ?>" title="Remove this record">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php else: 
                                    $status_class = match($order['payment_status']) { 'Paid' => 'bg-success', 'Pending' => 'bg-warning text-dark', 'Partially Paid' => 'bg-info text-dark', default => 'bg-secondary' };
                                    $remaining_balance = floatval($order['remaining_balance']);
                                    $next_due_date = !empty($order['next_due_date']) ? new DateTime($order['next_due_date']) : null;
                                    $is_overdue = $next_due_date && $next_due_date < new DateTime('today');
                                ?>
                                    <tr id="order-row-<?php echo $order['id']; ?>">
                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                        <td><?php echo date("M j, Y", strtotime($order['order_date'])); ?></td>
                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td class="remaining-amount">$<?php echo number_format($remaining_balance, 2); ?></td>
                                        <td class="next-due <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                            <?php echo ($remaining_balance > 0.01) ? ($next_due_date ? $next_due_date->format("M j, Y") : 'N/A') : 'Paid in Full'; ?>
                                        </td>
                                        <td><span class="badge rounded-pill <?php echo $status_class; ?> status-badge"><?php echo htmlspecialchars($order['payment_status']); ?></span></td>
                                        <td class="text-end actions-cell">
                                            <button class="btn btn-sm btn-outline-secondary view-details-btn" data-order-id="<?php echo $order['id']; ?>">View Details</button>
                                            <?php if ($order['payment_status'] != 'Paid' && $remaining_balance > 0): ?>
                                                <button class="btn btn-sm btn-primary pay-now-btn" data-order-id="<?php echo $order['id']; ?>" data-amount="<?php echo number_format($remaining_balance, 2, '.', ''); ?>" data-payment-type="Full Payment">
                                                    Pay Full Balance
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="orderDetailsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Order Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="orderDetailsContent"></div></div></div></div>
    <div class="modal fade" id="paymentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Complete Your Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>You are about to pay the full remaining balance for this order.</p><h4 class="text-center">Amount Due: <span id="payment-amount"></span></h4><form id="paymentForm"><input type="hidden" id="payment-order-id" name="order_id"><input type="hidden" id="payment-order-amount" name="amount"><input type="hidden" id="payment-type" name="payment_type"><div class="p-3 border rounded-3 my-3"><div class="card-number-wrapper mb-2"><input type="text" class="form-control" placeholder="Card Number" id="modalCardNumber" required><span class="card-icon" id="modalCardIcon"></span></div><div class="row g-2"><div class="col-md-6"><input type="text" class="form-control" placeholder="MM / YY" id="modalCardExpiry" required></div><div class="col-md-6"><input type="text" class="form-control" placeholder="CVV" id="modalCardCvv" required></div></div></div><button type="submit" class="btn btn-primary w-100">Confirm Payment</button></form></div></div></div></div>
    <div class="modal fade" id="paymentSuccessModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content text-center p-4"><div class="modal-body"><span class="material-symbols-outlined text-success" style="font-size: 80px; font-variation-settings: 'FILL' 1;">task_alt</span><h3 class="mt-3" style="font-family: 'Inria Sans', sans-serif;">Payment Successful!</h3><p class="text-muted" id="success-modal-message">Thank you! Your order is now fully paid.</p><button type="button" class="btn" style="background-color: var(--primary-color); color: white;" data-bs-dismiss="modal">Close</button></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const successModal = new bootstrap.Modal(document.getElementById('paymentSuccessModal'));

    document.querySelector('#invoices-table-body').addEventListener('click', async function(e) {
        
        const viewBtn = e.target.closest('.view-details-btn');
        const payBtn = e.target.closest('.pay-now-btn');
        const removeBtn = e.target.closest('.remove-order-btn');

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
                    
                    let statusHtml = `<strong>Booking Status:</strong> ${data.order.booking_status}`;
                    if (data.order.booking_status !== 'Cancelled') {
                        statusHtml += ` | <strong>Payment Status:</strong> ${data.order.payment_status}`;
                    }
                    
                    contentDiv.innerHTML = `
                        <h5>Order #${data.order.id} Details</h5>
                        <p>${statusHtml} | <strong>Event Date:</strong> ${new Date(data.order.date_event).toLocaleDateString()}</p>
                        <h6>Items Booked</h6>
                        <table class="table table-sm"><tbody>${itemsHtml}</tbody></table>
                        <h6>Payment History</h6>
                        <table class="table table-sm">
                            <thead><tr><th>Date</th><th>Type</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>${paymentsHtml || '<tr><td colspan=3 class="text-center">No payments made yet.</td></tr>'}</tbody>
                        </table>
                        <hr>
                        <h5 class="text-end">Total: $${parseFloat(data.order.total_amount).toFixed(2)} | Remaining: $${parseFloat(data.order.remaining_balance).toFixed(2)}</h5>
                    `;
                } else { contentDiv.innerHTML = `<p class="text-danger">${data.message}</p>`; }
            } catch (error) { contentDiv.innerHTML = `<p class="text-danger">Could not load details. Please try again.</p>`; }
        }
        
        if (payBtn) {
            document.getElementById('payment-amount').textContent = '$' + parseFloat(payBtn.dataset.amount).toFixed(2);
            document.getElementById('payment-order-id').value = payBtn.dataset.orderId;
            document.getElementById('payment-order-amount').value = payBtn.dataset.amount;
            document.getElementById('payment-type').value = payBtn.dataset.paymentType;
            paymentModal.show();
        }

        if (removeBtn) {
            const orderId = removeBtn.dataset.orderId;
            if (confirm(`Are you sure you want to permanently remove order #${orderId} from your history? This action cannot be undone.`)) {
                try {
                    const formData = new FormData();
                    formData.append('order_id', orderId);
                    const response = await fetch('backend/remove_order.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.status === 'success') {
                        const rowToRemove = document.getElementById(`order-row-${orderId}`);
                        if (rowToRemove) {
                            rowToRemove.classList.add('fading-out');
                            setTimeout(() => {
                                rowToRemove.remove();
                                if (document.querySelector('#invoices-table-body tr') === null) {
                                    document.querySelector('#invoices-table-body').innerHTML = '<tr class="no-orders-row"><td colspan="7" class="text-center p-5">You have no orders yet.</td></tr>';
                                }
                            }, 500);
                        }
                    } else { alert(`Error: ${data.message}`); }
                } catch (error) { alert('An error occurred. Could not remove the order record.'); }
            }
        }
    });

    document.getElementById('paymentForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const orderId = document.getElementById('payment-order-id').value;
        const payButton = this.querySelector('button[type="submit"]');
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        const formData = new FormData(this);
        try {
            const response = await fetch('backend/pay_invoice.php', { method: 'POST', body: formData });
            const data = await response.json();
            paymentModal.hide();
            if (data.status === 'success') {
                successModal.show();
                const orderRow = document.getElementById(`order-row-${orderId}`);
                if (orderRow) {
                    orderRow.dataset.status = 'Paid';
                    orderRow.querySelector('.remaining-amount').textContent = '$0.00';
                    orderRow.querySelector('.next-due').textContent = 'Paid in Full';
                    orderRow.querySelector('.next-due').classList.remove('overdue');
                    const statusBadge = orderRow.querySelector('.status-badge');
                    statusBadge.className = 'badge rounded-pill bg-success status-badge';
                    statusBadge.textContent = 'Paid';
                    orderRow.querySelector('.actions-cell').querySelectorAll('.pay-now-btn').forEach(btn => btn.remove());
                }
            } else { alert(data.message || 'Payment failed'); }
        } catch (error) { alert('An error occurred. Please try again.'); }
        finally {
            payButton.disabled = false;
            payButton.innerHTML = 'Confirm Payment';
        }
    });
    
    const cardNumberInput = document.getElementById('modalCardNumber');
    const cardExpiryInput = document.getElementById('modalCardExpiry');
    const cardCvvInput = document.getElementById('modalCardCvv');
    const cardIcon = document.getElementById('modalCardIcon');
    cardNumberInput.addEventListener('input',function(e){let t=e.target.value.replace(/\D/g,""),a="";if(/^3[47]/.test(t)){t=t.substring(0,15);for(let e=0;e<t.length;e++)(4===e||10===e)&&(a+=" "),a+=t[e]}else{t=t.substring(0,16);for(let e=0;e<t.length;e++)e>0&&e%4==0&&(a+=" "),a+=t[e]}e.target.value=a,cardIcon.innerHTML=/^4/.test(t)?'<i class="fa-brands fa-cc-visa" style="color:#1a1f71;"></i>':/^5[1-5]/.test(t)?'<i class="fa-brands fa-cc-mastercard" style="color:#f69e1f;"></i>':/^3[47]/.test(t)?'<i class="fa-brands fa-cc-amex" style="color:#2e77bc;"></i>':"";let l=0,s=!1;for(let e=t.length-1;e>=0;e--){let a=parseInt(t.charAt(e));s&&((a*=2)>9&&(a-=9)),l+=a,s=!s}const n=l%10==0;cardNumberInput.classList.remove("is-valid","is-invalid"),t.length>0&&(n&&((/^3[47]/.test(t)&&15===t.length)||16===t.length)?cardNumberInput.classList.add("is-valid"):cardNumberInput.classList.add("is-invalid"))}),cardExpiryInput.addEventListener("input",function(e){let t=e.target.value.replace(/\D/g,"").substring(0,4);e.target.value=t.length>2?t.substring(0,2)+" / "+t.substring(2):t}),cardCvvInput.addEventListener("input",function(e){let t=/^3[47]/.test(cardNumberInput.value.replace(/\D/g,""));e.target.value=e.target.value.replace(/\D/g,"").substring(0,t?4:3)});
});
</script>
</body>
</html>