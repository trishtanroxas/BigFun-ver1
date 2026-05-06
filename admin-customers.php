<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Customers Management</title>
    <link rel="icon" type="image/png" href="images/bfun.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-color: #802080; --primary-light: #FFEFFC; --light-gray: #f8f9fa; 
            --border-color: #dee2e6; --text-dark: #343a40; --text-muted: #6c757d;
        }
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
        .content-card { background-color: #fff; border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 25px; }
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 600; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .search-bar-wrapper { position: relative; width: 300px; }
        .search-bar-wrapper .material-symbols-outlined { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-bar-wrapper .form-control { padding-left: 45px; border-radius: 10px; }
        .customer-table { vertical-align: middle; }
        .customer-table thead th { font-weight: 600; color: var(--text-muted); border-bottom-width: 1px; cursor: pointer; user-select: none; }
        .customer-table thead th .material-symbols-outlined { font-size: 1rem; vertical-align: middle; opacity: 0; }
        .customer-table thead th.asc .material-symbols-outlined, .customer-table thead th.desc .material-symbols-outlined { opacity: 1; }
        .customer-table .customer-name { font-weight: 600; }
        .pagination-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; }
        .pagination-info { font-size: 0.9rem; color: var(--text-muted); }
        
        /* Modal Styles */
        .modal-body .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .info-section h6 { font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
        .info-item { margin-bottom: 1rem; }
        .info-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; display: block; margin-bottom: 0.25rem; }
        .info-value { font-weight: 600; }
        /* .info-value.censored { font-family: monospace; } -- No longer needed */
        .booking-history-table { font-size: 0.9rem; }
        .booking-history-table .action-cell { min-width: 150px; text-align: right; }
        .verification-row { background-color: #fffbe6 !important; }

        @media(max-width: 991.98px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 1050; } .sidebar.offcanvas.show { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-header { display: flex; justify-content: space-between; align-items: center; } }
    </style>
</head>
<body>

    <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
        <div class="logo"><img src="images/bgfunlogo.png" alt="BigFun Logo"></div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="admin-dashboard.php" class="nav-link"><span class="material-symbols-outlined">dashboard</span>Dashboard Overview</a></li>
            <li class="nav-item"><a href="admin-appointments.php" class="nav-link"><span class="material-symbols-outlined">event_note</span>Booked Appointments</a></li>
            <li class="nav-item"><a href="admin-booking-approval.php" class="nav-link"><span class="material-symbols-outlined">checklist</span>Booking Approval</a></li>
            <li class="nav-item"><a href="admin-services.php" class="nav-link"><span class="material-symbols-outlined">design_services</span>Services Administration</a></li>
            <li class="nav-item"><a href="admin-customers.php" class="nav-link active"><span class="material-symbols-outlined">group</span>Customers Management</a></li>
            <li class="nav-item"><a href="admin-manage.php" class="nav-link"><span class="material-symbols-outlined">person</span>Profile</a></li>
        </ul>
        <div class="logout-link"><a href="backend/logout-admin.php" class="btn btn-light w-100">Log Out</a></div>
    </div>

    <header class="mobile-header">
        <img src="images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
        <span class="material-symbols-outlined menu-icon" data-bs-toggle="offcanvas" data-bs-target="#sidebar">menu</span>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>Customers Management</h1>
            <p class="text-muted">View, search, and manage your customer list.</p>
        </div>
        
        <div class="content-card">
            <div class="toolbar">
                <div class="search-bar-wrapper">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email...">
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table customer-table table-hover">
                    <thead>
                        <tr>
                            <th data-sort="full_name">Name <span class="material-symbols-outlined">arrow_downward</span></th>
                            <th data-sort="email">Email <span class="material-symbols-outlined">arrow_downward</span></th>
                            <th data-sort="join_date">Join Date <span class="material-symbols-outlined">arrow_downward</span></th>
                            <th data-sort="total_bookings">Total Bookings <span class="material-symbols-outlined">arrow_downward</span></th>
                            <th data-sort="total_spent">Total Spent <span class="material-symbols-outlined">arrow_downward</span></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customers-table-body"></tbody>
                </table>
                 <div id="table-loader" class="text-center p-5 d-none"><div class="spinner-border text-primary"></div></div>
            </div>

            <div class="pagination-footer">
                <div id="pagination-info" class="pagination-info"></div>
                <div id="pagination-controls" class="btn-group"></div>
            </div>
        </div>
    </main>
    
    <div class="modal fade" id="detailsModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Customer Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailsModalBody"></div></div></div></div>
    
    <div class="modal fade" id="cancelReasonModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reason for Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Please provide a reason for cancelling Order #<strong id="cancelOrderId"></strong>.</p>
                    <textarea id="cancellationReason" class="form-control" rows="4" placeholder="e.g., Customer request, scheduling conflict..."></textarea>
                    <div id="cancelError" class="text-danger mt-2 d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="submitCancellationBtn">Submit Cancellation</button>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    let state = {
        customers: [], totalCustomers: 0, currentPage: 1, rowsPerPage: 10,
        sortBy: 'join_date', sortOrder: 'desc', searchQuery: ''
    };

    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const cancelReasonModal = new bootstrap.Modal(document.getElementById('cancelReasonModal'));
    const tableBody = document.getElementById('customers-table-body');
    const tableLoader = document.getElementById('table-loader');
    const searchInput = document.getElementById('searchInput');

    async function fetchCustomers() {
        tableLoader.classList.remove('d-none');
        tableBody.innerHTML = '';
        const { currentPage, rowsPerPage, sortBy, sortOrder, searchQuery } = state;
        const url = `backend/get_admin_customers.php?page=${currentPage}&limit=${rowsPerPage}&sort=${sortBy}&order=${sortOrder}&search=${encodeURIComponent(searchQuery)}`;
        try {
            const response = await fetch(url);
            const data = await response.json();
            if(data.status === 'success') {
                state.customers = data.customers;
                state.totalCustomers = data.total_customers;
                renderTable();
                renderPagination();
            } else { throw new Error(data.message); }
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Failed to load data: ${error.message}</td></tr>`;
        } finally {
            tableLoader.classList.add('d-none');
        }
    }

    function renderTable() {
        if (state.customers.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center p-4 text-muted">No customers found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = state.customers.map(customer => `
            <tr>
                <td class="customer-name">${customer.full_name}</td>
                <td>${customer.email}</td>
                <td>${new Date(customer.join_date).toLocaleDateString()}</td>
                <td>${customer.total_bookings}</td>
                <td>$${parseFloat(customer.total_spent).toFixed(2)}</td>
                <td><button class="btn btn-sm btn-outline-primary view-details-btn" data-customer-id="${customer.id}">View Details</button></td>
            </tr>
        `).join('');
    }

    function renderPagination() {
        const { currentPage, rowsPerPage, totalCustomers } = state;
        const totalPages = Math.ceil(totalCustomers / rowsPerPage);
        const infoEl = document.getElementById('pagination-info');
        const start = totalCustomers > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0;
        const end = Math.min(start + rowsPerPage - 1, totalCustomers);
        infoEl.textContent = `Showing ${start} to ${end} of ${totalCustomers} customers`;
        document.getElementById('pagination-controls').innerHTML = `
            <button class="btn btn-outline-secondary" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">Previous</button>
            <button class="btn btn-outline-secondary" ${currentPage >= totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">Next</button>
        `;
    }

    async function showDetailsModal(customerId) {
        const modalBody = document.getElementById('detailsModalBody');
        modalBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
        detailsModal.show();
        
        try {
            const response = await fetch(`backend/get_admin_customers.php?customer_id=${customerId}`);
            const data = await response.json();

            if (data.status === 'success') {
                const { details, bookings } = data;
                let bookingsHtml = '<p class="text-muted">No booking history found.</p>';

                if (bookings.length > 0) {
                    bookingsHtml = `
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover booking-history-table">
                                <thead><tr><th>ID</th><th>Event Date</th><th>Amount</th><th>Payment Status</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                    ${bookings.map(b => {
                                        let actionButtons = '';
                                        
                                        // --- NEW ACTION BUTTON LOGIC ---
                                        if (b.booking_status === 'Pending Confirmation') {
                                            actionButtons = `
                                                <button class="btn btn-sm btn-success confirm-booking-btn" data-order-id="${b.id}">Confirm</button>
                                                <button class="btn btn-sm btn-danger cancel-booking-btn" data-order-id="${b.id}">Cancel</button>
                                            `;
                                        } else if (b.payment_method === 'Cash' && b.payment_status === 'For Verification') {
                                             actionButtons = `<button class="btn btn-sm btn-info verify-payment-btn" data-order-id="${b.id}" data-ref-code="${b.reference_code || ''}">Verify Payment</button>`;
                                        } else if (b.booking_status === 'Confirmed' || b.booking_status === 'Cancelled') {
                                             actionButtons = `<span class="text-muted fst-italic">${b.booking_status}</span>`;
                                        } else {
                                            actionButtons = '<span class="text-muted">No actions</span>';
                                        }

                                        return `
                                            <tr id="modal-order-row-${b.id}" class="${b.payment_status === 'For Verification' ? 'verification-row' : ''}">
                                                <td>#${b.id}</td>
                                                <td>${new Date(b.date_event).toLocaleDateString()}</td>
                                                <td>$${parseFloat(b.total_amount).toFixed(2)}</td>
                                                <td class="payment-status-cell">${b.payment_status}</td>
                                                <td class="action-cell">${actionButtons}</td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }

                modalBody.innerHTML = `
                    <div class="info-grid">
                        <div class="info-section">
                            <h6>Personal Information</h6>
                            <div class="info-item"><span class="info-label">Full Name</span><span class="info-value">${details.full_name}</span></div>
                            <div class="info-item"><span class="info-label">Email Address</span><span class="info-value">${details.email}</span></div>
                            <div class="info-item"><span class="info-label">Contact Number</span><span class="info-value">${details.contact_number}</span></div>
                            <div class="info-item"><span class="info-label">Member Since</span><span class="info-value">${new Date(details.join_date).toLocaleDateString()}</span></div>
                        </div>
                        <div class="info-section">
                            <h6>Booking History & Approval</h6>
                            <p class="text-muted small">Manage pending bookings and verify cash payments here.</p>
                            ${bookingsHtml}
                        </div>
                    </div>
                `;
            } else { throw new Error(data.message); }
        } catch (error) {
            modalBody.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        }
    }

    async function handleBookingAction(button, orderId, newStatus, reason = '') {
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        try {
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('status', newStatus);
            if (reason) {
                formData.append('cancellation_reason', reason);
            }

            const response = await fetch('backend/update_booking_status.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                const row = document.getElementById(`modal-order-row-${orderId}`);
                // Find the correct cell to update. Since booking status is gone, this logic simplifies.
                // We just update the action cell.
                row.querySelector('.action-cell').innerHTML = `<span class="text-success fw-bold">${newStatus}</span>`;
                alert(data.message);
                if (newStatus === 'Cancelled') {
                    cancelReasonModal.hide();
                }
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            alert(`Error: ${error.message}`);
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

    // --- Event Listeners ---
    document.querySelector('.customer-table thead').addEventListener('click', e => {
        const header = e.target.closest('th');
        if (!header?.dataset.sort) return;
        const sortKey = header.dataset.sort;
        if (state.sortBy === sortKey) {
            state.sortOrder = state.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            state.sortBy = sortKey; state.sortOrder = 'asc';
        }
        document.querySelectorAll('.customer-table thead th').forEach(th => th.classList.remove('asc', 'desc'));
        header.classList.add(state.sortOrder);
        fetchCustomers();
    });

    document.getElementById('pagination-controls').addEventListener('click', e => {
        if(e.target.tagName === 'BUTTON' && !e.target.disabled) {
            state.currentPage = parseInt(e.target.dataset.page);
            fetchCustomers();
        }
    });

    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.searchQuery = searchInput.value; state.currentPage = 1;
            fetchCustomers();
        }, 300);
    });
    
    tableBody.addEventListener('click', e => {
        const button = e.target.closest('.view-details-btn');
        if(button) showDetailsModal(button.dataset.customerId);
    });
    
    // --- NEW: Event Delegation for ALL Modal Actions ---
    document.getElementById('detailsModalBody').addEventListener('click', async e => {
        const confirmBtn = e.target.closest('.confirm-booking-btn');
        const cancelBtn = e.target.closest('.cancel-booking-btn');
        const verifyBtn = e.target.closest('.verify-payment-btn');

        // Handle Confirm Booking
        if (confirmBtn) {
            const orderId = confirmBtn.dataset.orderId;
            if (confirm(`Are you sure you want to confirm Order #${orderId}?`)) {
                handleBookingAction(confirmBtn, orderId, 'Confirmed');
            }
        }

        // Handle Cancel Booking (shows the reason modal)
        if (cancelBtn) {
            const orderId = cancelBtn.dataset.orderId;
            document.getElementById('cancelOrderId').textContent = orderId;
            document.getElementById('cancellationReason').value = '';
            document.getElementById('submitCancellationBtn').dataset.orderId = orderId;
            cancelReasonModal.show();
        }

        // Handle Verify Payment
        if (verifyBtn) {
            const orderId = verifyBtn.dataset.orderId;
            const refCode = verifyBtn.dataset.refCode;
            if (!confirm(`Verify payment for Order #${orderId} with reference code "${refCode}"?`)) return;
            
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verifying...';
            try {
                const formData = new FormData();
                formData.append('order_id', orderId);
                const response = await fetch('backend/verify_reference_code.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.status === 'success') {
                    const row = document.getElementById(`modal-order-row-${orderId}`);
                    row.querySelector('.payment-status-cell').textContent = 'Paid';
                    row.querySelector('.action-cell').innerHTML = '<span class="text-success fw-bold">Verified</span>';
                    row.classList.remove('verification-row');
                    alert(data.message);
                } else { throw new Error(data.message); }
            } catch (error) {
                alert(`Error: ${error.message}`);
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = 'Verify Payment';
            }
        }
    });

    // --- NEW: Listener for the Cancellation Reason Modal ---
    document.getElementById('submitCancellationBtn').addEventListener('click', function(e) {
        const orderId = this.dataset.orderId;
        const reason = document.getElementById('cancellationReason').value.trim();
        const errorDiv = document.getElementById('cancelError');

        if (!reason) {
            errorDiv.textContent = 'A reason is required for cancellation.';
            errorDiv.classList.remove('d-none');
            return;
        }
        errorDiv.classList.add('d-none');
        // This function now needs the button element from the *other* modal.
        // Let's find the original button that was clicked to open this.
        // This is tricky. A better way is to just pass the button from the *current* modal.
        handleBookingAction(this, orderId, 'Cancelled', reason);
    });

    // Initial fetch to load data
    fetchCustomers();
});
</script>
</body>
</html>