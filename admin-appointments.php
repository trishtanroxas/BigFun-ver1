<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
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

        /* Page-specific Styles */
        .content-card {
            background-color: #fff;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
        }

        .financials-card h4,
        .calendar-view-card h4 {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
        }

        .financials-table {
            font-size: 0.95rem;
        }

        .financials-table td {
            padding: 8px 4px;
            border: none;
        }

        .financials-table .label {
            color: var(--text-dark);
        }

        .financials-table .value {
            font-weight: 600;
            text-align: right;
        }

        .financials-total-row .value {
            color: var(--primary-color);
        }

        .financials-ytd-row {
            border-top: 1px solid var(--border-color);
            margin-top: 10px;
            padding-top: 10px;
        }

        .financials-ytd-row .label {
            color: var(--primary-color);
            font-weight: 600;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            margin-top: 20px;
            /* Added margin since filters are gone */
        }

        .calendar-header h5 {
            margin: 0;
            font-weight: 600;
        }

        /* --- THIS RULE WAS MODIFIED --- */
        .calendar-header .btn {
            background-color: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            /* Added for alignment */
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* --- END OF MODIFICATION --- */

        .calendar-body {
            margin-top: 25px;
        }

        .day-group {
            margin-bottom: 25px;
        }

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

        .booking-card+.booking-card {
            border-top: 1px solid var(--border-color);
        }

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

        .booking-card .booking-value.text-danger {
            color: var(--danger-text) !important;
            font-weight: 700;
        }

        .no-bookings-row {
            padding: 20px 0;
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
        }

        .terms-link {
            text-decoration: none;
            cursor: pointer;
        }

        .terms-link:hover {
            text-decoration: underline;
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
        <div class="logo"><img src="images/bgfunlogo.png" alt="BigFun Logo"></div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="admin-dashboard.php" class="nav-link"><span class="material-symbols-outlined">dashboard</span>Dashboard Overview</a></li>
            <li class="nav-item"><a href="admin-appointments.php" class="nav-link active"><span class="material-symbols-outlined">event_note</span>Booked Appointments</a></li>
            <li class="nav-item"><a href="admin-booking-approval.php" class="nav-link"><span class="material-symbols-outlined">checklist</span>Booking Approval</a></li>
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
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th class="text-end">Bookings</th>
                                        <th class="text-end">Amounts</th>
                                        <th class="text-end">Profit</th>
                                    </tr>
                                </thead>
                                <tbody id="financials-month-body"></tbody>
                            </table>
                        </div>
                        <div class="col-md-5 d-flex align-items-center">
                            <div class="w-100" id="financials-summary-extra"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="content-card calendar-view-card">
                    <h4 class="mb-3">Calendar View</h4>

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

    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalTitle">Booking Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsModalBody"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms & Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Booking and Deposit</h6>
                    <p>A non-refundable deposit is required to secure your booking date and time. The deposit amount will be specified at the time of booking and will be deducted from your total event cost.</p>

                    <h6>2. Payment</h6>
                    <p>The remaining balance is due on the date of the event, prior to the start of the service. We accept payment by cash, bank transfer, or credit card (a surcharge may apply).</p>

                    <h6>3. Cancellations and Rescheduling</h6>
                    <p>Cancellations must be made at least 14 days prior to the event date. The deposit is non-refundable. If you need to reschedule, please contact us as soon as possible. We will do our best to accommodate your new date, subject to availability. Rescheduling within 7 days of the event may incur an additional fee.</p>

                    <h6>4. Location and Setup</h6>
                    <p>The client is responsible for providing a safe and suitable location for the event, including adequate space for our equipment and access to a reliable power source. Our team will arrive approximately 1-2 hours prior to the event start time for setup.</p>

                    <h6>5. Weather</h6>
                    <p>For outdoor events, the client must provide an alternative indoor location in case of inclement weather. If a suitable alternative is not available, the event may be cancelled, and the deposit will be forfeited.</p>

                    <h6>6. Liability</h6>
                    <p>We are not responsible for any injuries or damage to property that may occur during the event, unless caused by the negligence of our staff. The client assumes full responsibility for the supervision of all guests, especially children.</p>

                    <p>By paying the deposit, you agree to these terms and conditions.</p>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarBody = document.getElementById('calendar-body');
            const financialsMonthBody = document.getElementById('financials-month-body');
            const financialsExtraBody = document.getElementById('financials-summary-extra');
            const currentMonthYearEl = document.getElementById('currentMonthYear');
            const prevMonthBtn = document.getElementById('prevMonthBtn');
            const nextMonthBtn = document.getElementById('nextMonthBtn');

            let currentDate = new Date(); // Default to the current date
            let allBookings = []; // Store all bookings for the month

            async function loadDataForMonth(month, year) {
                const monthName = new Date(year, month - 1, 1).toLocaleString('default', {
                    month: 'long',
                    year: 'numeric'
                });
                currentMonthYearEl.textContent = monthName;

                const prevMonth = new Date(year, month - 2, 1);
                prevMonthBtn.innerHTML = `<span class="material-symbols-outlined">arrow_back_ios_new</span> ${prevMonth.toLocaleString('default', { month: 'short' })}`;

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
                        renderCalendar(allBookings); // Render all bookings directly
                    } else {
                        calendarBody.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                    }
                } catch (error) {
                    console.error('Failed to load data:', error);
                    calendarBody.innerHTML = `<div class="alert alert-danger">Failed to load bookings. Check console for details.</div>`;
                }
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
                    const dayOfWeek = new Date(currentYear, currentMonth - 1, day).toLocaleString('default', {
                        weekday: 'short'
                    });

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

                            const termsHtml = termsAgreed ?
                                `<a href="#" class="terms-link text-success" data-bs-toggle="modal" data-bs-target="#termsModal">Terms Agreed</a>` :
                                `<small class="text-danger fw-bold">Terms Not Agreed</small>`;

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
                                ${termsHtml}
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
                return new Date(1970, 0, 1, h, m).toLocaleTimeString('en-AU', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                }).replace(' ', '').toUpperCase();
            }

            // Event Listeners
            prevMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                loadDataForMonth(currentDate.getMonth() + 1, currentDate.getFullYear());
            });
            nextMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                loadDataForMonth(currentDate.getMonth() + 1, currentDate.getFullYear());
            });

            // Initial Load
            loadDataForMonth(currentDate.getMonth() + 1, currentDate.getFullYear());
        });
    </script>
</body>

</html>