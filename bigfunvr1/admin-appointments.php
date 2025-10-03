<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login-admin.php");
    exit();
}

include "backend/db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Booked Appointments</title>
    <link rel="icon" type="image/png" href="images/bfun.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-color: #802080; 
            --primary-light: #FFEFFC;
            --light-gray: #f8f9fa; 
            --border-color: #dee2e6;
            --text-dark: #343a40;
            --text-muted: #6c757d;
            --danger-text: #dc3545;
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

        /* Page-specific Styles */
        .content-card {
            background-color: #fff;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 25px;
        }
        .financials-card h4, .calendar-view-card h4 {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
        }
        .financials-table { font-size: 0.95rem; }
        .financials-table td { padding: 8px 4px; border: none; }
        .financials-table .label { color: var(--text-dark); }
        .financials-table .value { font-weight: 600; text-align: right; }
        .financials-total-row .value { color: var(--primary-color); }
        .financials-ytd-row { border-top: 1px solid var(--border-color); margin-top: 10px; padding-top: 10px; }
        .financials-ytd-row .label { color: var(--primary-color); font-weight: 600; }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
        }
        .calendar-header h5 { margin: 0; font-weight: 600; }
        .calendar-header .btn { background-color: rgba(255,255,255,0.2); border: none; color: white; }

        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 10px;
        }
        .filter-section .form-select, .filter-section .form-control {
            font-size: 0.9rem;
        }
        .filter-section .search-bar-wrapper {
            position: relative;
            flex-grow: 1; /* Allows search bar to take remaining space */
        }
        .search-bar-wrapper .material-symbols-outlined {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .search-bar-wrapper .form-control { padding-left: 45px; border-radius: 10px; }

        .calendar-body { margin-top: 25px; }
        .day-group { margin-bottom: 25px; }
        .day-header {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 1.1rem;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
        }
        .booking-card {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            padding: 15px 0;
            font-size: 0.875rem;
            align-items: start;
        }
        .booking-card + .booking-card { border-top: 1px solid var(--border-color); }
        .booking-card .booking-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 4px;
            display: block;
        }
        .booking-card .booking-value {
            font-weight: 600;
            color: var(--text-dark);
        }
        .booking-card .booking-value.text-danger { color: var(--danger-text) !important; font-weight: 700; }
        .no-bookings-row {
            padding: 20px 0;
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
        }

        @media(max-width: 991.98px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 1050; } .sidebar.offcanvas.show { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-header { display: flex; justify-content: space-between; align-items: center; } }
    </style>
</head>
<body>

    <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
        <div class="logo"><img src="images/bgfunlogo.png" alt="BigFun Logo"></div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="admin-dashboard.php" class="nav-link"><span class="material-symbols-outlined">dashboard</span>Dashboard Overview</a></li>
            <li class="nav-item"><a href="admin-appointments.php" class="nav-link active"><span class="material-symbols-outlined">event_note</span>Booked Appointments</a></li>
            <li class="nav-item"><a href="admin-services.php" class="nav-link"><span class="material-symbols-outlined">design_services</span>Services Administration</a></li>
            <li class="nav-item"><a href="admin-customers.php" class="nav-link"><span class="material-symbols-outlined">group</span>Customers Management</a></li>
            <li class="nav-item"><a href="admin-manage.php" class="nav-link"><span class="material-symbols-outlined">person</span>Profile</a></li>
        </ul>
        <div class="logout-link"><a href="backend/logout-admin.php" class="btn btn-light w-100">Log Out</a></div>
    </div>

    <header class="mobile-header">
        <img src="images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
        <span class="material-symbols-outlined menu-icon" data-bs-toggle="offcanvas" data-bs-target="#sidebar">menu</span>
    </header>

    <div class="main-content">
        
        <div class="row g-4">
            <div class="col-12">
                <div class="content-card financials-card">
                    <h4>Monthly Financials</h4>
                    <div class="row">
                        <div class="col-md-7">
                            <table class="table financials-table">
                                <thead><tr><th></th><th class="text-end">Bookings</th><th class="text-end">Amounts</th><th class="text-end">Profit</th></tr></thead>
                                <tbody id="financials-month-body"></tbody>
                            </table>
                        </div>
                        <div class="col-md-5 d-flex align-items-center"><div class="w-100" id="financials-summary-extra"></div></div>
                    </div>
                </div>
            </div>
            
            <div class="col-12">
                <div class="content-card calendar-view-card">
                    <h4 class="mb-3">Calendar View</h4>
                    
                    <div class="filter-section">
                        <select class="form-select" id="serviceFilter"><option value="">All Services</option></select>
                        <select class="form-select" id="paymentStatusFilter"><option value="">All Payment Statuses</option></select>
                        <div class="search-bar-wrapper">
                            <span class="material-symbols-outlined">search</span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search customer, venue...">
                        </div>
                    </div>

                    <div class="calendar-header">
                        <button id="prevMonthBtn" class="btn"><span class="material-symbols-outlined">arrow_back_ios_new</span></button>
                        <h5 id="currentMonthYear"></h5>
                        <button id="nextMonthBtn" class="btn"><span class="material-symbols-outlined">arrow_forward_ios</span></button>
                    </div>
                    
                    <div id="calendar-body" class="calendar-body"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="detailsModalTitle">Booking Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailsModalBody"></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarBody = document.getElementById('calendar-body');
    const financialsMonthBody = document.getElementById('financials-month-body');
    const financialsExtraBody = document.getElementById('financials-summary-extra');
    const currentMonthYearEl = document.getElementById('currentMonthYear');
    const prevMonthBtn = document.getElementById('prevMonthBtn');
    const nextMonthBtn = document.getElementById('nextMonthBtn');
    
    // Filter elements
    const searchInput = document.getElementById('searchInput');
    const serviceFilter = document.getElementById('serviceFilter');
    const paymentStatusFilter = document.getElementById('paymentStatusFilter');

    let currentDate = new Date(); // Default to the current date
    let allBookings = []; // Store all bookings for the month to filter locally

    async function loadDataForMonth(month, year) {
        const monthName = new Date(year, month - 1, 1).toLocaleString('default', { month: 'long', year: 'numeric' });
        currentMonthYearEl.textContent = monthName;

        const prevMonth = new Date(year, month - 2, 1);
        prevMonthBtn.innerHTML = `<span class="material-symbols-outlined">arrow_back_ios_new</span> ${prevMonth.toLocaleString('default', { month: 'long' })}`;

        const nextMonth = new Date(year, month, 1);
        nextMonthBtn.innerHTML = `${nextMonth.toLocaleString('default', { month: 'long' })} <span class="material-symbols-outlined">arrow_forward_ios</span>`;

        calendarBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>';
        financialsMonthBody.innerHTML = '<tr><td colspan="4" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>';

        try {
            const response = await fetch(`backend/get_admin_bookings.php?month=${month}&year=${year}`);
            const data = await response.json();

            if (data.status === 'success') {
                allBookings = data.bookings;
                renderFinancials(data.financials_month, data.financials_ytd, year);
                populateFilters(data.filter_data);
                applyFilters(); // Initial render
            } else {
                calendarBody.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        } catch (error) {
            console.error('Failed to load data:', error);
            calendarBody.innerHTML = `<div class="alert alert-danger">Failed to load bookings. Check console for details.</div>`;
        }
    }

    function populateFilters(filterData) {
        // Services
        serviceFilter.innerHTML = '<option value="">All Services</option>';
        filterData.services.forEach(s => {
            const option = new Option(s.service_name, s.service_name);
            serviceFilter.add(option);
        });

        // Payment Statuses (these are static)
        const statuses = ['Pending', 'Partially Paid', 'Paid', 'Refunded'];
        paymentStatusFilter.innerHTML = '<option value="">All Payment Statuses</option>';
        statuses.forEach(s => {
            const option = new Option(s, s);
            paymentStatusFilter.add(option);
        });
    }

    function applyFilters() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedService = serviceFilter.value;
        const selectedPaymentStatus = paymentStatusFilter.value;

        const filteredBookings = allBookings.filter(b => {
            const searchTextMatch = `${b.full_name} ${b.address}`.toLowerCase().includes(searchTerm);
            const serviceMatch = !selectedService || (b.services_booked && b.services_booked.includes(selectedService));
            const paymentStatusMatch = !selectedPaymentStatus || b.payment_status === selectedPaymentStatus;
            
            return searchTextMatch && serviceMatch && paymentStatusMatch;
        });
        
        renderCalendar(filteredBookings);
    }
    
    function renderCalendar(bookingsToRender) {
        calendarBody.innerHTML = '';
        const currentYear = currentDate.getFullYear();
        const currentMonth = currentDate.getMonth() + 1;
        
        const bookingsByDay = {};
        bookingsToRender.forEach(b => {
            const dayOfMonth = new Date(b.date_event).getDate();
            if (!bookingsByDay[dayOfMonth]) {
                bookingsByDay[dayOfMonth] = [];
            }
            bookingsByDay[dayOfMonth].push(b);
        });

        const numDaysInMonth = new Date(currentYear, currentMonth, 0).getDate();

        for (let day = 1; day <= numDaysInMonth; day++) {
            const dayOfWeek = new Date(currentYear, currentMonth - 1, day).toLocaleString('default', { weekday: 'short' });
            
            const dayGroup = document.createElement('div');
            dayGroup.className = 'day-group';
            dayGroup.innerHTML = `<div class="day-header">${dayOfWeek}, ${currentDate.toLocaleString('default', { month: 'short' })} ${day}</div>`;

            const bookingsForThisDay = bookingsByDay[day] || [];
            
            if (bookingsForThisDay.length > 0) {
                bookingsForThisDay.forEach(b => {
                    const isDoubleBooked = false; // Placeholder for future logic
                    const termsAgreed = true; // Placeholder for future logic

                    const bookingCard = document.createElement('div');
                    bookingCard.className = 'booking-card';
                    
                    bookingCard.innerHTML = `
                        <div>
                            <span class="booking-label">Booking Schedule & Venue</span>
                            <span class="booking-value">${formatTime(b.start_time)} - ${formatTime(b.end_time)}<br><small class="text-muted">${b.address || 'N/A'}</small></span>
                        </div>
                        <div><span class="booking-label">Customer Name</span><span class="booking-value">${b.full_name}</span></div>
                        <div><span class="booking-label">Event Location</span><span class="booking-value">${b.type_event || 'N/A'}</span></div>
                        <div>
                            <span class="booking-label">Service Booked</span>
                            <span class="booking-value">${b.services_booked || 'N/A'}${isDoubleBooked ? '<div class="text-danger fw-bold">Double Booked</div>' : ''}</span>
                        </div>
                        <div>
                            <span class="booking-label">Financial Summary</span>
                            <span class="booking-value">
                                Total $${parseFloat(b.total_amount).toFixed(2)}<br>
                                <small class="text-muted">Deposit Paid $${parseFloat(b.deposit_paid).toFixed(2)}</small><br>
                                <small class="${termsAgreed ? 'text-success' : 'text-danger fw-bold'}">${termsAgreed ? 'Terms Agreed' : 'Terms Not Agreed'}</small>
                            </span>
                        </div>
                    `;
                    dayGroup.appendChild(bookingCard);
                });
            } else {
                dayGroup.innerHTML += `<div class="no-bookings-row">No bookings for this day.</div>`;
            }
            calendarBody.appendChild(dayGroup);
        }
    }
    
    function renderFinancials(monthData, ytdData, year) {
        financialsMonthBody.innerHTML = `
            <tr><td class="label">Booking this month:</td><td class="value">${monthData.total_bookings}</td><td class="value">$${parseFloat(monthData.total_amount).toFixed(2)}</td><td class="value">$${parseFloat(monthData.total_profit).toFixed(2)}</td></tr>
            <tr class="financials-total-row border-top"><td class="label fw-bold">Total (${monthData.saturday_count} Saturdays)</td><td class="value fw-bold">${monthData.total_bookings}</td><td class="value fw-bold">$${parseFloat(monthData.total_amount).toFixed(2)}</td><td class="value fw-bold">$${parseFloat(monthData.total_profit).toFixed(2)}</td></tr>
            <tr class="financials-ytd-row"><td class="label">${year-1} - ${year} Total (${monthData.saturday_count} Saturdays)</td><td class="value">${ytdData.total_bookings}</td><td class="value">$${parseFloat(ytdData.total_amount).toFixed(2)}</td><td class="value">$${parseFloat(ytdData.total_profit).toFixed(2)}</td></tr>`;
        
        financialsExtraBody.innerHTML = `
            <table class="table table-borderless"><tbody>
                <tr><td class="label ps-5">Total number of deposit:</td><td class="value pe-5">$${parseFloat(monthData.total_deposits).toFixed(2)}</td></tr>
                <tr><td class="label ps-5">Total of remaining balances:</td><td class="value pe-5">$${parseFloat(monthData.remaining_balance).toFixed(2)}</td></tr>
            </tbody></table>`;
    }

    function formatTime(timeStr) {
        if (!timeStr) return '';
        let [h, m] = timeStr.split(':');
        return new Date(1970, 0, 1, h, m).toLocaleTimeString('en-AU', { hour: 'numeric', minute: '2-digit', hour12: true }).replace(' ', '').toUpperCase();
    }
    
    // Event Listeners
    prevMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); loadDataForMonth(currentDate.getMonth() + 1, currentDate.getFullYear()); });
    nextMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); loadDataForMonth(currentDate.getMonth() + 1, currentDate.getFullYear()); });
    
    searchInput.addEventListener('keyup', applyFilters);
    serviceFilter.addEventListener('change', applyFilters);
    paymentStatusFilter.addEventListener('change', applyFilters);

    // Initial Load
    loadDataForMonth(currentDate.getMonth() + 1, currentDate.getFullYear());
});
</script>
</body>
</html>