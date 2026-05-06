<?php
session_start();
header('Content-Type: application/json');
include "db.php";

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);

if (!$month || !$year) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Month and year are required.']);
    exit();
}

$response = [
    'status' => 'error',
    'message' => 'An unknown error occurred.'
];

try {
    // ------------------- Bookings query -------------------
    $bookings_query = "
        SELECT 
            o.id,
            CONCAT(s.first_name, ' ', s.middle_initial, ' ', s.last_name) AS full_name,
            o.address,
            o.date_event,
            o.start_time,
            o.end_time,
            o.type_event,
            o.payment_status,
            o.booking_status,
            o.total_amount,
            o.deposit_amount,
            o.deposit_status,
            o.remaining_balance,
            (SELECT COALESCE(SUM(payment_amount), 0) 
             FROM order_payments 
             WHERE order_id = o.id) as deposit_paid,
            (SELECT GROUP_CONCAT(sv.service_name SEPARATOR ', ') 
             FROM order_items oi 
             JOIN services sv ON oi.service_id = sv.id 
             WHERE oi.order_id = o.id) as services_booked
        FROM orders o
        LEFT JOIN signup s ON o.user_id = s.id
        WHERE MONTH(o.date_event) = ? AND YEAR(o.date_event) = ?
        ORDER BY o.date_event, o.start_time
    ";
    $stmt = $conn->prepare($bookings_query);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $bookings_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ------------------- Monthly Financials -------------------
    $financials_month_query = "
        SELECT 
            COUNT(id) as total_bookings,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(total_amount), 0) as total_profit,
            (SELECT COUNT(DISTINCT(DATE(date_event))) 
             FROM orders 
             WHERE DAYOFWEEK(date_event) = 7 
             AND MONTH(date_event) = ? AND YEAR(date_event) = ?) as saturday_count,
            (SELECT COALESCE(SUM(payment_amount), 0) 
             FROM order_payments op 
             JOIN orders o ON op.order_id = o.id 
             WHERE MONTH(o.date_event) = ? AND YEAR(o.date_event) = ?) as total_deposits
        FROM orders
        WHERE MONTH(date_event) = ? AND YEAR(date_event) = ?
    ";
    $stmt = $conn->prepare($financials_month_query);
    $stmt->bind_param("iiiiii", $month, $year, $month, $year, $month, $year);
    $stmt->execute();
    $financials_month = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $financials_month['remaining_balance'] = $financials_month['total_amount'] - $financials_month['total_deposits'];

    // ------------------- Year-to-date Financials -------------------
    $financials_ytd_query = "
        SELECT 
            COUNT(id) as total_bookings,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(total_amount), 0) as total_profit
        FROM orders
        WHERE YEAR(date_event) = ?
    ";
    $stmt = $conn->prepare($financials_ytd_query);
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $financials_ytd = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // ------------------- Services Filter -------------------
    $services_query = "SELECT DISTINCT service_name FROM services ORDER BY service_name";
    $services_result = $conn->query($services_query)->fetch_all(MYSQLI_ASSOC);

    // ------------------- Final Response -------------------
    $response['status'] = 'success';
    $response['bookings'] = $bookings_result;
    $response['financials_month'] = $financials_month;
    $response['financials_ytd'] = $financials_ytd;
    $response['filter_data'] = [
        'services' => $services_result,
    ];
    unset($response['message']);

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = "Database error: " . $e->getMessage();
}

// ✅ only close connection, no need to close stmt again
$conn->close();

echo json_encode($response);
