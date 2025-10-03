<?php
include "db.php"; // Your database connection

// --- Business Logic: Define the daily order limit here ---
define('ORDER_LIMIT', 5);

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$stmt = $conn->prepare("
    SELECT 
        date_event, 
        COUNT(id) as order_count 
    FROM orders 
    WHERE 
        MONTH(date_event) = ? AND YEAR(date_event) = ? 
    GROUP BY date_event
");
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$order_data = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $order_count = (int)$row['order_count'];
        $remaining_slots = max(0, ORDER_LIMIT - $order_count); // Ensure it doesn't go below 0

        // ❗ MODIFIED: Return a more detailed object for each date
        $order_data[$row['date_event']] = [
            'order_count' => $order_count,
            'remaining_slots' => $remaining_slots
        ];
    }
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($order_data);
?>