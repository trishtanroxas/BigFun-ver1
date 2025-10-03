<?php
session_start();

// 🔒 Prevent cached page access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login-admin.php");
    exit();
}

include "backend/db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/png" href="images/bfun.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #802080; --light-pink: #FFEFFC; --light-gray: #f8f9fa;
            --dark-gray: #6c757d; --border-color: #dee2e6; --success: #198754;
            --warning: #ffc107; --danger: #dc3545;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 260px; background-color: #fff; border-right: 1px solid var(--border-color); padding: 20px; display: flex; flex-direction: column; z-index: 1040; }
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }
        .mobile-header { display: none; background-color: #fff; padding: 15px 20px; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1050; }
        .menu-icon { font-size: 28px; color: var(--primary-color); cursor: pointer; }
        .sidebar .logo { text-align: center; margin-bottom: 30px; }
        .sidebar .logo img { max-height: 50px; }
        .sidebar .nav-pills .nav-link { color: #555; font-size: 15px; font-weight: 600; padding: 12px 15px; display: flex; align-items: center; gap: 10px; }
        .sidebar .nav-pills .nav-link.active, .sidebar .nav-pills .nav-link:hover { background-color: var(--light-pink); color: var(--primary-color); }
        .sidebar .logout-link { margin-top: auto; }
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 600; }
        .filter-bar { margin-bottom: 2rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
        .filter-bar .btn-group .btn { font-size: 0.9rem; }
        .custom-range-form { display: flex; gap: 0.5rem; align-items: center; }
        .custom-range-form .form-control { font-size: 0.9rem; }
        .dashboard-card { display: flex; flex-direction: column; position: relative; background-color: #fff; border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; padding: 1.5rem; margin-bottom: 1.5rem; height: 100%; }
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.08); }
        .card-title-container { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .card-title-container .material-symbols-outlined { color: var(--primary-color); font-size: 2rem; }
        .card-title-container h5 { margin: 0; font-size: 1rem; font-weight: 600; color: #333; }
        .dashboard-card .card-value { font-size: 2rem; font-weight: 700; color: #212529; }
        .chart-container { background-color: #fff; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; }
        .chart-wrapper { position: relative; height: 350px; width: 100%; }
        .loading-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.7); z-index: 10; display: none; align-items: center; justify-content: center; border-radius: 12px; }
        .loading-overlay.active { display: flex; }
        .card-footer-link { margin-top: auto; font-size: 0.85rem; font-weight: 600; text-decoration: none; position: relative; z-index: 2; }
        .card-footer-link:hover { color: var(--primary-color); }
        @media(max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.offcanvas.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-header { display: flex; justify-content: space-between; align-items: center; }
        }
    </style>
</head>
<body>
    <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
        <div class="logo"><img src="images/bgfunlogo.png" alt="BigFun Logo"></div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="admin-dashboard.php" class="nav-link active"><span class="material-symbols-outlined">dashboard</span>Dashboard Overview</a></li>
            <li class="nav-item"><a href="admin-appointments.php" class="nav-link"><span class="material-symbols-outlined">event_note</span>Booked Appointments</a></li>
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

    <main class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1>Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, here's a summary of your business activity.</p>
            </div>
            <a id="downloadReportBtn" class="btn btn-primary" href="backend/download_report.php?period=month">
                <span class="material-symbols-outlined me-1" style="font-size: 1.25rem;">download</span> Download Report
            </a>
        </div>
        
        <div class="filter-bar">
            <div class="btn-group" role="group" id="period-filter">
                <button type="button" class="btn btn-outline-primary" data-period="day">Today</button>
                <button type="button" class="btn btn-outline-primary" data-period="week">This Week</button>
                <button type="button" class="btn btn-outline-primary active" data-period="month">This Month</button>
                <button type="button" class="btn btn-outline-primary" data-period="year">This Year</button>
            </div>
            <div class="custom-range-form">
                <input type="date" id="startDate" class="form-control">
                <span>to</span>
                <input type="date" id="endDate" class="form-control">
                <button id="applyRangeBtn" class="btn btn-primary">Apply</button>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6"><div class="dashboard-card"><div class="card-title-container"><span class="material-symbols-outlined">request_quote</span><h5>Total Revenue</h5></div><p class="card-value" id="revenueSummary">AU$ 0</p><small class="text-muted mt-auto">Based on fully paid invoices</small></div></div>
            <div class="col-xl-3 col-md-6"><div class="dashboard-card"><div class="card-title-container"><span class="material-symbols-outlined" style="color: var(--warning);">hourglass_top</span><h5>Partially Paid</h5></div><p class="card-value" id="partiallyPaidOrders">0</p><a href="#" class="card-footer-link" data-details-type="invoices_partial">See Details</a></div></div>
            <div class="col-xl-3 col-md-6"><div class="dashboard-card"><div class="card-title-container"><span class="material-symbols-outlined" style="color: var(--danger);">error</span><h5>Unpaid Invoices</h5></div><p class="card-value" id="unpaidOrders">0</p><a href="#" class="card-footer-link" data-details-type="invoices_unpaid">See Details</a></div></div>
            <div class="col-xl-3 col-md-6"><div class="dashboard-card"><div class="card-title-container"><span class="material-symbols-outlined" style="color: var(--success);">event_available</span><h5>Upcoming Bookings</h5></div><p class="card-value" id="upcomingBookings">0</p><a href="#" class="card-footer-link" data-details-type="upcoming">See Details</a></div></div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3">Revenue & Payments Over Time</h5>
                    <div class="chart-wrapper">
                        <canvas id="revenueChart"></canvas>
                        <div class="loading-overlay"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3">Payment Status Breakdown</h5>
                    <div class="chart-wrapper">
                        <canvas id="statusChart"></canvas>
                        <div class="loading-overlay"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3">Top 5 Services</h5>
                    <div class="chart-wrapper">
                        <canvas id="servicesChart"></canvas>
                        <div class="loading-overlay"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <div class="modal fade" id="detailsModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="detailsModalTitle">Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailsModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let charts = {};
    let currentFilter = { type: 'period', value: 'month' }; // Keep track of the current filter
    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));

    const CHART_COLORS = {
        purple: '#802080', pink: 'rgba(128, 32, 128, 0.1)', green: '#198754', yellow: '#ffc107',
        yellow_light: 'rgba(255, 193, 7, 0.1)', grey: '#6c757d', red: '#dc3545', blue: '#0dcaf0'
    };
    const formatCurrency = (value) => `AU$ ${parseFloat(value || 0).toLocaleString('en-AU', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

    const createOrUpdateChart = (id, type, data, options) => {
        const ctx = document.getElementById(id)?.getContext('2d');
        if (!ctx) return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(ctx, { type, data, options });
    };

    // --- Main Data Fetching Function ---
    const fetchAnalytics = async (filter) => {
        document.querySelectorAll('.loading-overlay').forEach(el => el.classList.add('active'));
        document.querySelectorAll('.loading-overlay').forEach(el => el.innerHTML = '<div class="spinner-border text-primary"></div>');
        
        let url = 'backend/get_dashboard_analytics.php?';
        if (filter.type === 'period') {
            url += `period=${filter.value}`;
        } else if (filter.type === 'range') {
            url += `startDate=${filter.start}&endDate=${filter.end}`;
        }

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (data.status === 'success') {
                updateUI(data.data);
            } else { throw new Error(data.message || 'An unknown error occurred.'); }
        } catch (error) {
            console.error('Fetch Error:', error);
            document.querySelectorAll('.chart-wrapper').forEach(wrapper => {
                wrapper.innerHTML = `<div class="d-flex align-items-center justify-content-center h-100 text-danger p-3">${error.message}</div>`;
            });
        } finally {
            document.querySelectorAll('.loading-overlay').forEach(el => el.classList.remove('active'));
        }
    };

    // --- UI Update Function ---
    const updateUI = (data) => {
        const manageChartState = (chartId, chartData, createChartCallback) => {
            const wrapper = document.getElementById(chartId)?.parentElement;
            if (!wrapper) return;
            let hasData = false;
            if (chartData && chartData.datasets) {
                 hasData = Object.values(chartData.datasets).some(dataset => dataset.some(v => v > 0));
            } else if (chartData && chartData.values) {
                 hasData = chartData.values.some(v => v > 0);
            }
            if (hasData) {
                if (!wrapper.querySelector('canvas')) {
                    wrapper.innerHTML = `<canvas id="${chartId}"></canvas><div class="loading-overlay"></div>`;
                }
                createChartCallback();
            } else {
                wrapper.innerHTML = `<div class="d-flex align-items-center justify-content-center h-100 text-muted fst-italic p-3">No data to display for this period.</div>`;
            }
        };

        // Update KPI Cards
        document.getElementById('revenueSummary').textContent = formatCurrency(data.kpis.revenue_summary);
        document.getElementById('partiallyPaidOrders').textContent = data.kpis.partially_paid_orders;
        document.getElementById('unpaidOrders').textContent = data.kpis.unpaid_orders;
        document.getElementById('upcomingBookings').textContent = data.kpis.upcoming_bookings;

        const baseChartOptions = { responsive: true, maintainAspectRatio: false };
        const currencyTooltip = { plugins: { tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${formatCurrency(context.raw)}` } } } };

        // Revenue Chart with two lines
        manageChartState('revenueChart', data.revenue_chart, () => {
            createOrUpdateChart('revenueChart', 'line', {
                labels: data.revenue_chart.labels,
                datasets: [
                    { label: 'Fully Paid Revenue', data: data.revenue_chart.datasets.paid, borderColor: CHART_COLORS.purple, backgroundColor: CHART_COLORS.pink, fill: true, tension: 0.3 },
                    { label: 'Partial Payments', data: data.revenue_chart.datasets.partial, borderColor: CHART_COLORS.yellow, backgroundColor: CHART_COLORS.yellow_light, fill: true, tension: 0.3 }
                ]
            }, { ...baseChartOptions, ...currencyTooltip, scales: { y: { beginAtZero: true } } });
        });
        // Payment Status Chart
        manageChartState('statusChart', data.status_chart, () => {
            createOrUpdateChart('statusChart', 'doughnut', {
                labels: data.status_chart.labels,
                datasets: [{ data: data.status_chart.values, backgroundColor: [CHART_COLORS.green, CHART_COLORS.yellow, CHART_COLORS.red], borderColor: '#fff', borderWidth: 4 }]
            }, { ...baseChartOptions, cutout: '70%', plugins: { legend: { position: 'bottom' } } });
        });
        // Top Services Chart
        manageChartState('servicesChart', data.services_chart, () => {
            createOrUpdateChart('servicesChart', 'bar', {
                labels: data.services_chart.labels,
                datasets: [{ label: 'Bookings', data: data.services_chart.values, backgroundColor: CHART_COLORS.blue, borderRadius: 4 }]
            }, { ...baseChartOptions, indexAxis: 'y', plugins: { legend: { display: false } } });
        });
    };

    // --- Event Listeners ---
    document.querySelectorAll('#period-filter .btn').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelector('#period-filter .btn.active')?.classList.remove('active');
            button.classList.add('active');
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            currentFilter = { type: 'period', value: button.dataset.period };
            document.getElementById('downloadReportBtn').href = `backend/download_report.php?period=${currentFilter.value}`;
            fetchAnalytics(currentFilter);
        });
    });

    // --- Event Listener for Custom Date Range ---
    document.getElementById('applyRangeBtn').addEventListener('click', () => {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        if (startDate && endDate) {
            document.querySelector('#period-filter .btn.active')?.classList.remove('active');
            currentFilter = { type: 'range', start: startDate, end: endDate };
            document.getElementById('downloadReportBtn').href = `backend/download_report.php?startDate=${startDate}&endDate=${endDate}`;
            fetchAnalytics(currentFilter);
        } else {
            alert('Please select both a start and end date.');
        }
    });

    document.querySelectorAll('a[data-details-type]').forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            const type = e.target.dataset.detailsType;
            const modalTitle = document.getElementById('detailsModalTitle');
            const modalBody = document.getElementById('detailsModalBody');
            
            modalTitle.textContent = e.target.closest('.dashboard-card').querySelector('h5').textContent + ' Details';
            modalBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border"></div></div>';
            detailsModal.show();

            let url = `backend/get_dashboard_analytics.php?details=${type}`;
            if (currentFilter.type === 'period') {
                url += `&period=${currentFilter.value}`;
            } else if (currentFilter.type === 'range') {
                url += `&startDate=${currentFilter.start}&endDate=${currentFilter.end}`;
            }

            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.status === 'success' && data.details) {
                    if (data.details.length > 0) {
                        let tableHTML = '<div class="table-responsive"><table class="table table-striped table-hover table-sm">';
                        tableHTML += '<thead><tr>';
                        Object.keys(data.details[0]).forEach(key => {
                            tableHTML += `<th>${key.replace(/_/g, ' ').toUpperCase()}</th>`;
                        });
                        tableHTML += '</tr></thead><tbody>';
                        data.details.forEach(row => {
                            tableHTML += '<tr>';
                            Object.entries(row).forEach(([key, val]) => {
                                let formattedVal = val || 'N/A';
                                if (key.includes('amount') || key.includes('balance')) {
                                    formattedVal = formatCurrency(val);
                                }
                                tableHTML += `<td>${formattedVal}</td>`;
                            });
                            tableHTML += '</tr>';
                        });
                        tableHTML += '</tbody></table></div>';
                        modalBody.innerHTML = tableHTML;
                    } else {
                        modalBody.innerHTML = '<div class="alert alert-info text-center">No details found for this period.</div>';
                    }
                } else { throw new Error(data.message || 'Could not load details.'); }
            } catch (error) {
                modalBody.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            }
        });
    });

    // Initial Load
    fetchAnalytics(currentFilter);
});
</script>
</body>
</html>