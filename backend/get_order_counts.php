<?php
header('Content-Type: application/json');
include "db.php"; // Ensure this path is correct

// Get month and year from query parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($month < 1 || $month > 12 || $year < 1970 || $year > date('Y') + 5) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid month or year']);
    exit;
}

// Calculate the first and last day of the month
$first_day = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
$last_day = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

// Initialize as an empty object for JSON { "YYYY-MM-DD": {"order_count": X} }
$order_counts_per_day = [];

try {
    // --- NEW LOGIC: Count total orders per day ---
    $stmt = $conn->prepare("
        SELECT
            DATE(o.date_event) as booking_date,
            COUNT(DISTINCT o.id) as order_count
        FROM orders o
        WHERE o.date_event BETWEEN ? AND ?
          AND o.booking_status != 'Cancelled'
        GROUP BY booking_date
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("ss", $first_day, $last_day);

    if (!$stmt->execute()) {
         throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    $result = $stmt->get_result();

    // --- NEW DATA STRUCTURE ---
    // Structure as { "2025-10-30": { "order_count": 3 } }
    while ($row = $result->fetch_assoc()) {
        $date = $row['booking_date'];
        $order_counts_per_day[$date] = [
            'order_count' => (int)$row['order_count']
        ];
    }
    // --- END NEW LOGIC ---

    $stmt->close();
    $conn->close();

    echo json_encode($order_counts_per_day);

} catch (Exception $e) {
    error_log("Error fetching order counts: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Could not retrieve booking counts.']);
}
?>