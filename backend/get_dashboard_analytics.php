<?php
// Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');
include "db.php";

// --- Authentication and Setup ---
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Authentication required.']));
}

date_default_timezone_set('Australia/Brisbane'); 
$now = new DateTime();
$response = ['status' => 'success'];
$interval = null;
$format = '';
$sqlGroupByDate = '';

// --- Date Range & Grouping Calculation ---
if (isset($_GET['startDate']) && isset($_GET['endDate']) && !empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $startDate = new DateTime($_GET['startDate'] . ' 00:00:00');
    $endDate = new DateTime($_GET['endDate'] . ' 23:59:59');
    $diff = $startDate->diff($endDate);
    if ($diff->days <= 2) { $interval = new DateInterval('PT1H'); $format = 'G'; $sqlGroupByDate = "HOUR(op.payment_date)"; } 
    elseif ($diff->days <= 90) { $interval = new DateInterval('P1D'); $format = 'j M'; $sqlGroupByDate = "DATE(op.payment_date)"; } 
    else { $interval = new DateInterval('P1M'); $format = 'M Y'; $sqlGroupByDate = "YEAR(op.payment_date), MONTH(op.payment_date)"; }
} else {
    $period = $_GET['period'] ?? 'month';
    switch ($period) {
        case 'day':
            $startDate = (clone $now)->setTime(0, 0, 0); $endDate = (clone $now)->setTime(23, 59, 59);
            $interval = new DateInterval('PT1H'); $format = 'G'; $sqlGroupByDate = "HOUR(op.payment_date)";
            break;
        case 'week':
            $startDate = (clone $now)->modify('monday this week')->setTime(0, 0, 0); $endDate = (clone $now)->modify('sunday this week')->setTime(23, 59, 59);
            $interval = new DateInterval('P1D'); $format = 'D'; $sqlGroupByDate = "DATE(op.payment_date)";
            break;
        case 'year':
            $startDate = (clone $now)->modify('first day of january this year')->setTime(0, 0, 0); $endDate = (clone $now)->modify('last day of december this year')->setTime(23, 59, 59);
            $interval = new DateInterval('P1M'); $format = 'M'; $sqlGroupByDate = "MONTH(op.payment_date)";
            break;
        default: // 'month'
            $startDate = (clone $now)->modify('first day of this month')->setTime(0, 0, 0); $endDate = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
            $interval = new DateInterval('P1D'); $format = 'j'; $sqlGroupByDate = "DATE(op.payment_date)";
            break;
    }
}
$startDateStr = $startDate->format('Y-m-d H:i:s');
$endDateStr = $endDate->format('Y-m-d H:i:s');
$todayStr = (new DateTime())->format('Y-m-d H:i:s');

try {
    $orderStatusSubquery = "( SELECT o.id, o.total_amount, CASE WHEN o.booking_status = 'Cancelled' THEN 'Cancelled' WHEN COALESCE(p.paid_amount, 0) >= o.total_amount THEN 'Paid' WHEN COALESCE(p.paid_amount, 0) > 0 THEN 'Partially Paid' ELSE 'Unpaid' END as status FROM orders o LEFT JOIN ( SELECT order_id, SUM(payment_amount) as paid_amount FROM order_payments GROUP BY order_id ) p ON o.id = p.order_id )";

    // --- MODE 1: Get Detailed List for Modals ---
    if (isset($_GET['details'])) {
        $detailsType = $_GET['details']; $query = ""; $params = []; $types = "";
        switch ($detailsType) {
            case 'revenue_details':
                $query = "SELECT CONCAT(s.first_name, ' ', s.last_name) as customer, op.order_id, op.payment_amount as revenue, op.payment_date FROM order_payments op JOIN orders o ON op.order_id = o.id JOIN signup s ON o.user_id = s.id WHERE op.payment_date BETWEEN ? AND ? ORDER BY op.payment_date DESC";
                $params = [$startDateStr, $endDateStr]; $types = "ss"; break;
            case 'invoices_partial':
                $query = "SELECT o.id as invoice_id, CONCAT(s.first_name, ' ', s.last_name) as customer, o.date_event, o.total_amount, COALESCE(p.paid_amount, 0) as paid_amount, (o.total_amount - COALESCE(p.paid_amount, 0)) as balance_due FROM orders o JOIN signup s ON o.user_id = s.id JOIN {$orderStatusSubquery} as oss ON o.id = oss.id LEFT JOIN (SELECT order_id, SUM(payment_amount) as paid_amount FROM order_payments GROUP BY order_id) p ON o.id = p.order_id WHERE o.date_event BETWEEN ? AND ? AND oss.status = 'Partially Paid' ORDER BY o.date_event DESC";
                $params = [$startDateStr, $endDateStr]; $types = "ss"; break;
            case 'invoices_unpaid':
                $query = "SELECT o.id as invoice_id, CONCAT(s.first_name, ' ', s.last_name) as customer, o.date_event, o.total_amount FROM orders o JOIN signup s ON o.user_id = s.id JOIN {$orderStatusSubquery} as oss ON o.id = oss.id WHERE o.date_event BETWEEN ? AND ? AND oss.status = 'Unpaid' ORDER BY o.date_event DESC";
                $params = [$startDateStr, $endDateStr]; $types = "ss"; break;
        }
        if ($query) {
            $stmt = $conn->prepare($query);
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $response['details'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
        echo json_encode($response); exit();
    }

    // --- MODE 2: Get Summary Data for Dashboard ---
    function generateTimeSeries($dbData, $start, $end, $interval, $format) {
        $fullRange = [];
        $periodIterator = new DatePeriod($start, $interval, (clone $end)->modify('+1 second'));
        foreach ($periodIterator as $date) {
            $label = $date->format($format);
            if ($format === 'G') $label .= ':00';
            $fullRange[$label] = 0;
        }
        foreach ($dbData as $row) {
            if(empty($row['label_date'])) continue;
            $date = new DateTime($row['label_date']);
            $label = $date->format($format);
            if ($format === 'G') $label .= ':00';
            if (isset($fullRange[$label])) { $fullRange[$label] += (float)$row['value']; }
        }
        return ['labels' => array_keys($fullRange), 'values' => array_values($fullRange)];
    }

    // --- KPI Queries ---
    // ✨ MODIFICATION: Initialize all required KPIs for the 4 cards.
    $kpis = ['revenue_summary' => 0, 'partially_paid_orders' => 0, 'unpaid_orders' => 0, 'total_appointments' => 0, 'upcoming_in_period' => 0, 'cancelled_in_period' => 0];

    // Get counts for Partially Paid, Unpaid, and Cancelled statuses
    $kpi_query = "SELECT status, COUNT(oss.id) as count FROM {$orderStatusSubquery} as oss JOIN orders o ON oss.id = o.id WHERE o.date_event BETWEEN ? AND ? GROUP BY status";
    $stmt = $conn->prepare($kpi_query);
    $stmt->bind_param("ss", $startDateStr, $endDateStr);
    $stmt->execute();
    $kpi_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach($kpi_data as $row) {
        if ($row['status'] == 'Partially Paid') $kpis['partially_paid_orders'] = (int)$row['count'];
        if ($row['status'] == 'Unpaid') $kpis['unpaid_orders'] = (int)$row['count'];
        if ($row['status'] == 'Cancelled') $kpis['cancelled_in_period'] = (int)$row['count'];
    }
    $stmt->close();

    // Get Total Revenue
    $revenue_stmt = $conn->prepare("SELECT SUM(payment_amount) as total_revenue FROM order_payments WHERE payment_date BETWEEN ? AND ?");
    $revenue_stmt->bind_param("ss", $startDateStr, $endDateStr);
    $revenue_stmt->execute();
    $kpis['revenue_summary'] = (float)($revenue_stmt->get_result()->fetch_assoc()['total_revenue'] ?? 0);
    $revenue_stmt->close();

    // ✨ NEW: Get all appointment KPIs for the specified period
    $app_kpi_query = "SELECT 
        COUNT(id) as total,
        SUM(CASE WHEN date_event >= ? AND booking_status != 'Cancelled' THEN 1 ELSE 0 END) as upcoming
        FROM orders
        WHERE date_event BETWEEN ? AND ?";
    $app_stmt = $conn->prepare($app_kpi_query);
    $app_stmt->bind_param("sss", $todayStr, $startDateStr, $endDateStr);
    $app_stmt->execute();
    $app_results = $app_stmt->get_result()->fetch_assoc();
    $kpis['total_appointments'] = (int)($app_results['total'] ?? 0);
    $kpis['upcoming_in_period'] = (int)($app_results['upcoming'] ?? 0);
    $app_stmt->close();

    // --- Chart Queries ---
    $paid_revenue_query = "SELECT op.payment_date as label_date, SUM(op.payment_amount) as value FROM order_payments op JOIN {$orderStatusSubquery} as oss ON op.order_id = oss.id WHERE oss.status IN ('Paid', 'Partially Paid') AND op.payment_date BETWEEN ? AND ? GROUP BY {$sqlGroupByDate}";
    $stmt = $conn->prepare($paid_revenue_query); $stmt->bind_param("ss", $startDateStr, $endDateStr); $stmt->execute();
    $paid_revenue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

    $partial_payments_query = "SELECT op.payment_date as label_date, SUM(op.payment_amount) as value FROM order_payments op JOIN {$orderStatusSubquery} as oss ON op.order_id = oss.id WHERE oss.status = 'Partially Paid' AND op.payment_date BETWEEN ? AND ? GROUP BY {$sqlGroupByDate}";
    $stmt = $conn->prepare($partial_payments_query); $stmt->bind_param("ss", $startDateStr, $endDateStr); $stmt->execute();
    $partial_payments_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    
    $paid_ts = generateTimeSeries($paid_revenue_data, $startDate, $endDate, $interval, $format);
    $partial_ts_data_only = generateTimeSeries($partial_payments_data, $startDate, $endDate, $interval, $format);

    $status_chart_data = [];
    foreach($kpi_data as $row) { if ($row['status'] != 'Cancelled') { $status_chart_data[] = ['status' => $row['status'], 'count' => (int)$row['count']]; } }

    $services_query = "SELECT s.service_name, COUNT(oi.order_id) as count FROM order_items oi JOIN services s ON oi.service_id = s.id JOIN orders o ON oi.order_id = o.id WHERE o.date_event BETWEEN ? AND ? AND o.booking_status != 'Cancelled' GROUP BY s.service_name ORDER BY count DESC LIMIT 5";
    $stmt = $conn->prepare($services_query); $stmt->bind_param("ss", $startDateStr, $endDateStr); $stmt->execute();
    $services_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

    // --- Final Response Assembly ---
    $response['data'] = [
        'kpis' => $kpis,
        'revenue_chart' => [ 'labels' => $paid_ts['labels'], 'datasets' => [ 'paid' => $paid_ts['values'], 'partial' => $partial_ts_data_only['values'] ] ],
        'status_chart' => [ 'labels' => array_column($status_chart_data, 'status'), 'values' => array_column($status_chart_data, 'count') ],
        'services_chart' => [ 'labels' => array_column($services_data, 'service_name'), 'values' => array_column($services_data, 'count') ]
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response = ['status' => 'error', 'message' => $e->getMessage(), 'trace' => 'Error on line ' . $e->getLine() . ' in ' . $e->getFile()];
}

echo json_encode($response);
?>