<?php
session_start();
include "db.php";

header('Content-Type: application/json');

// Security check: ensure an ADMIN is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

// Get the month and year from the JavaScript fetch request
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$response = ['status' => 'error', 'bookings' => []];

// Prepare a statement to get all bookings for the given month and year
// We also JOIN to get the names of the services booked
$stmt = $conn->prepare("
    SELECT 
        o.id, o.full_name, o.date_event, o.start_time, o.end_time, o.payment_status, o.booking_status,
        GROUP_CONCAT(s.service_name SEPARATOR ', ') as services_booked
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN services s ON oi.service_id = s.id
    WHERE 
        MONTH(o.date_event) = ? AND YEAR(o.date_event) = ?
    GROUP BY o.id
    ORDER BY o.date_event, o.start_time ASC
");
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    $response['status'] = 'success';
    $response['bookings'] = $result->fetch_all(MYSQLI_ASSOC);
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>