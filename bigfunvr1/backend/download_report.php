<?php
session_start();
include "db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login-admin.php");
    exit();
}

$period = $_GET['period'] ?? 'month';

// Determine date range
date_default_timezone_set('Australia/Brisbane');
$now = new DateTime();
switch ($period) {
    case 'day':
        $startDate = $now->format('Y-m-d 00:00:00');
        $endDate = $now->format('Y-m-d 23:59:59');
        break;
    case 'week':
        $startDate = (clone $now)->modify('monday this week')->format('Y-m-d 00:00:00');
        $endDate = (clone $now)->modify('sunday this week')->format('Y-m-d 23:59:59');
        break;
    case 'year':
        $startDate = (clone $now)->modify('first day of january this year')->format('Y-m-d 00:00:00');
        $endDate = (clone $now)->modify('last day of december this year')->format('Y-m-d 23:59:59');
        break;
    case 'month':
    default:
        $startDate = (clone $now)->modify('first day of this month')->format('Y-m-d 00:00:00');
        $endDate = (clone $now)->modify('last day of this month')->format('Y-m-d 23:59:59');
        break;
}

$filename = "report_" . $period . "_" . date('Y-m-d') . ".csv";

// Set headers to trigger file download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Fetch data
$stmt = $conn->prepare("SELECT * FROM orders WHERE date_event BETWEEN ? AND ? ORDER BY date_event DESC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Open output stream
$output = fopen('php://output', 'w');

// Add header row
fputcsv($output, ['Order ID', 'Customer Name', 'Email', 'Event Date', 'Event Type', 'Total Amount', 'Payment Status', 'Booking Status', 'Assigned Operator']);

// Add data rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['full_name'],
            $row['email'],
            $row['date_event'],
            $row['type_event'],
            $row['total_amount'],
            $row['payment_status'],
            $row['booking_status'],
            $row['assigned_operator'] ?? 'N/A'
        ]);
    }
}

fclose($output);
exit();
?>