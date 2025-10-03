<?php
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

// --- Date Range & Grouping Calculation ---
if (isset($_GET['startDate']) && isset($_GET['endDate'])) {
    // Handle Custom Date Range
    $startDate = new DateTime($_GET['startDate'] . ' 00:00:00');
    $endDate = new DateTime($_GET['endDate'] . ' 23:59:59');
    $diff = $startDate->diff($endDate);
    if ($diff->days <= 2) { // Day view
        $interval = new DateInterval('PT1H'); $format = 'G'; $sqlGroupByDate = "HOUR(op.payment_date)"; $sqlGroupByOrderDate = "HOUR(o.date_event)";
    } elseif ($diff->days <= 90) { // Week/Month view
        $interval = new DateInterval('P1D'); $format = 'j M'; $sqlGroupByDate = "DATE(op.payment_date)"; $sqlGroupByOrderDate = "DATE(o.date_event)";
    } else { // Year view
        $interval = new DateInterval('P1M'); $format = 'M Y'; $sqlGroupByDate = "YEAR(op.payment_date), MONTH(op.payment_date)"; $sqlGroupByOrderDate = "YEAR(o.date_event), MONTH(o.date_event)";
    }
} else {
    // Handle Preset Periods
    $period = $_GET['period'] ?? 'month';
    switch ($period) {
        case 'day':
            $startDate = (clone $now)->setTime(0, 0, 0); $endDate = (clone $now)->setTime(23, 59, 59);
            $interval = new DateInterval('PT1H'); $format = 'G'; $sqlGroupByDate = "HOUR(op.payment_date)"; $sqlGroupByOrderDate = "HOUR(o.date_event)";
            break;
        case 'week':
            $startDate = (clone $now)->modify('monday this week')->setTime(0, 0, 0); $endDate = (clone $now)->modify('sunday this week')->setTime(23, 59, 59);
            $interval = new DateInterval('P1D'); $format = 'D'; $sqlGroupByDate = "DATE(op.payment_date)"; $sqlGroupByOrderDate = "DATE(o.date_event)";
            break;
        case 'year':
            $startDate = (clone $now)->modify('first day of january this year')->setTime(0, 0, 0); $endDate = (clone $now)->modify('last day of december this year')->setTime(23, 59, 59);
            $interval = new DateInterval('P1M'); $format = 'M'; $sqlGroupByDate = "MONTH(op.payment_date)"; $sqlGroupByOrderDate = "MONTH(o.date_event)";
            break;
        case 'month':
        default:
            $startDate = (clone $now)->modify('first day of this month')->setTime(0, 0, 0); $endDate = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
            $interval = new DateInterval('P1D'); $format = 'j'; $sqlGroupByDate = "DATE(op.payment_date)"; $sqlGroupByOrderDate = "DATE(o.date_event)";
            break;
    }
}
$startDateStr = $startDate->format('Y-m-d H:i:s');
$endDateStr = $endDate->format('Y-m-d H:i:s');

try {
    // --- MODE 1: Get Detailed List for Modals (FIXED) ---
    if (isset($_GET['details'])) {
        $detailsType = $_GET['details'];
        $query = ""; 
        $params = [];
        $types = "";

        // This subquery defines order statuses and is used in the queries below
        $statusSubquery = "(SELECT o.id, CASE WHEN COALESCE(p.paid_amount, 0) >= o.total_amount THEN 'Paid' WHEN COALESCE(p.paid_amount, 0) > 0 THEN 'Partially Paid' ELSE 'Unpaid' END as status FROM orders o LEFT JOIN (SELECT order_id, SUM(payment_amount) as paid_amount FROM order_payments GROUP BY order_id) p ON o.id = p.order_id) as oss";

        switch ($detailsType) {
            case 'invoices_partial':
                $query = "SELECT o.id as invoice_id, o.full_name as customer, o.date_event, o.total_amount, COALESCE(p.paid_amount, 0) as paid_amount, (o.total_amount - COALESCE(p.paid_amount, 0)) as balance_due FROM orders o JOIN {$statusSubquery} ON o.id = oss.id LEFT JOIN (SELECT order_id, SUM(payment_amount) as paid_amount FROM order_payments GROUP BY order_id) p ON o.id = p.order_id WHERE o.date_event BETWEEN ? AND ? AND oss.status = 'Partially Paid' ORDER BY o.date_event DESC";
                $params = [$startDateStr, $endDateStr];
                $types = "ss";
                break;
            case 'invoices_unpaid':
                $query = "SELECT o.id as invoice_id, o.full_name as customer, o.date_event, o.total_amount FROM orders o JOIN {$statusSubquery} ON o.id = oss.id WHERE o.date_event BETWEEN ? AND ? AND oss.status = 'Unpaid' ORDER BY o.date_event DESC";
                $params = [$startDateStr, $endDateStr];
                $types = "ss";
                break;
            case 'upcoming':
                // This query does not depend on the date filter
                $query = "SELECT id as booking_id, full_name as customer, date_event, start_time, assigned_operator FROM orders WHERE date_event >= CURDATE() ORDER BY date_event ASC, start_time ASC";
                break;
        }
        
        if ($query) {
            $stmt = $conn->prepare($query);
            if (!empty($params)) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $response['details'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
        echo json_encode($response);
        exit();
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
            $date = new DateTime($row['label_date']);
            $label = $date->format($format);
            if ($format === 'G') $label .= ':00';
            if (isset($fullRange[$label])) { $fullRange[$label] += (float)$row['value']; }
        }
        return ['labels' => array_keys($fullRange), 'values' => array_values($fullRange)];
    }

    $orderStatusSubquery = "(SELECT o.id, o.total_amount, CASE WHEN COALESCE(p.paid_amount, 0) >= o.total_amount THEN 'Paid' WHEN COALESCE(p.paid_amount, 0) > 0 THEN 'Partially Paid' ELSE 'Unpaid' END as status FROM orders o LEFT JOIN (SELECT order_id, SUM(payment_amount) as paid_amount FROM order_payments GROUP BY order_id) p ON o.id = p.order_id)";

    $kpi_query = "SELECT status, COUNT(oss.id) as count, SUM(IF(status='Paid', oss.total_amount, 0)) as revenue FROM {$orderStatusSubquery} as oss JOIN orders o ON oss.id = o.id WHERE o.date_event BETWEEN ? AND ? GROUP BY status";
    $kpi_data = $conn->execute_query($kpi_query, [$startDateStr, $endDateStr])->fetch_all(MYSQLI_ASSOC);
    
    $kpis = ['revenue_summary' => 0, 'partially_paid_orders' => 0, 'unpaid_orders' => 0];
    foreach($kpi_data as $row) {
        if ($row['status'] == 'Paid') $kpis['revenue_summary'] = $row['revenue'];
        if ($row['status'] == 'Partially Paid') $kpis['partially_paid_orders'] = $row['count'];
        if ($row['status'] == 'Unpaid') $kpis['unpaid_orders'] = $row['count'];
    }
    $kpis['upcoming_bookings'] = $conn->execute_query("SELECT COUNT(id) as total FROM orders WHERE date_event >= CURDATE()")->fetch_assoc()['total'];
    
    $paid_revenue_query = "SELECT op.payment_date as label_date, SUM(op.payment_amount) as value FROM order_payments op JOIN {$orderStatusSubquery} as oss ON op.order_id = oss.id WHERE oss.status = 'Paid' AND op.payment_date BETWEEN ? AND ? GROUP BY {$sqlGroupByDate}";
    $paid_revenue_data = $conn->execute_query($paid_revenue_query, [$startDateStr, $endDateStr])->fetch_all(MYSQLI_ASSOC);

    $partial_payments_query = "SELECT op.payment_date as label_date, SUM(op.payment_amount) as value FROM order_payments op JOIN {$orderStatusSubquery} as oss ON op.order_id = oss.id WHERE oss.status = 'Partially Paid' AND op.payment_date BETWEEN ? AND ? GROUP BY {$sqlGroupByDate}";
    $partial_payments_data = $conn->execute_query($partial_payments_query, [$startDateStr, $endDateStr])->fetch_all(MYSQLI_ASSOC);
    
    $paid_ts = generateTimeSeries($paid_revenue_data, $startDate, $endDate, $interval, $format);
    $partial_ts = generateTimeSeries($partial_payments_data, $startDate, $endDate, $interval, $format);

    $status_chart_data = [];
    foreach($kpi_data as $row) { $status_chart_data[] = ['status' => $row['status'], 'count' => $row['count']]; }
    $services_data = $conn->execute_query("SELECT s.service_name, COUNT(oi.order_id) as count FROM order_items oi JOIN services s ON oi.service_id = s.id JOIN orders o ON oi.order_id = o.id WHERE o.date_event BETWEEN ? AND ? GROUP BY s.service_name ORDER BY count DESC LIMIT 5", [$startDateStr, $endDateStr])->fetch_all(MYSQLI_ASSOC);

    $response['data'] = [
        'kpis' => $kpis,
        'revenue_chart' => [ 'labels' => $paid_ts['labels'], 'datasets' => [ 'paid' => $paid_ts['values'], 'partial' => $partial_ts['values'] ] ],
        'status_chart' => [ 'labels' => array_column($status_chart_data, 'status'), 'values' => array_column($status_chart_data, 'count') ],
        'services_chart' => [ 'labels' => array_column($services_data, 'service_name'), 'values' => array_column($services_data, 'count') ]
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>