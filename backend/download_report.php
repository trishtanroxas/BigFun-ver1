<?php
// Enable error reporting for debugging during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "db.php"; // Your database connection file

// --- Authentication ---
// 🔒 Ensure an admin is logged in before allowing a report download.
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401); 
    exit("Authentication required."); 
}

// --- Date Range Calculation ---
date_default_timezone_set('Australia/Brisbane');
$now = new DateTime();
$startDateStr = '';
$endDateStr = '';

// Check for and prioritize a custom date range from the dashboard's date pickers.
if (isset($_GET['startDate']) && !empty($_GET['startDate']) && isset($_GET['endDate']) && !empty($_GET['endDate'])) {
    $startDate = (new DateTime($_GET['startDate']))->setTime(0, 0, 0);
    $endDate = (new DateTime($_GET['endDate']))->setTime(23, 59, 59);
    $filename = "report_from_" . $_GET['startDate'] . "_to_" . $_GET['endDate'] . ".csv";
} else {
    // If no custom range, use the selected period button (e.g., 'month', 'week').
    $period = $_GET['period'] ?? 'month'; // Default to 'month'.
    $filename = "report_" . $period . "_" . $now->format('Y-m-d') . ".csv";

    switch ($period) {
        case 'day':
            $startDate = (clone $now)->setTime(0, 0, 0);
            $endDate = (clone $now)->setTime(23, 59, 59);
            break;
        case 'week':
            $startDate = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
            $endDate = (clone $now)->modify('sunday this week')->setTime(23, 59, 59);
            break;
        case 'year':
            $startDate = (clone $now)->modify('first day of january this year')->setTime(0, 0, 0);
            $endDate = (clone $now)->modify('last day of december this year')->setTime(23, 59, 59);
            break;
        default: // 'month'
            $startDate = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
            $endDate = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
            break;
    }
}

// Format the DateTime objects into strings suitable for SQL queries.
$startDateStr = $startDate->format('Y-m-d H:i:s');
$endDateStr = $endDate->format('Y-m-d H:i:s');

try {
    // --- Data Fetching ---
    
    // Subquery to efficiently calculate the total amount paid for each order.
    $payments_subquery = "(SELECT order_id, SUM(payment_amount) as paid_amount FROM order_payments GROUP BY order_id)";

    // ✨ FIXED: The main query now uses the exact column names from your database schema.
    // The non-existent 'assigned_operator' column has been removed.
    $query = "
        SELECT 
            o.id,
            o.date_event,
            o.type_event,
            o.total_amount,
            o.booking_status,
            CONCAT(s.first_name, ' ', s.last_name) AS customer_name,
            s.email,
            -- This CASE statement dynamically calculates the payment status for accuracy.
            CASE 
                WHEN o.booking_status = 'Cancelled' THEN 'Cancelled' 
                WHEN COALESCE(p.paid_amount, 0) >= o.total_amount THEN 'Paid' 
                WHEN COALESCE(p.paid_amount, 0) > 0 THEN 'Partially Paid' 
                ELSE 'Unpaid' 
            END as calculated_payment_status
        FROM 
            orders o
        -- Join to the 'signup' table to get customer information.
        LEFT JOIN 
            signup s ON o.user_id = s.id
        -- Join to the pre-calculated sum of payments for each order.
        LEFT JOIN 
            {$payments_subquery} p ON o.id = p.order_id
        WHERE 
            -- Filter orders based on the event date falling within the selected range.
            o.date_event BETWEEN ? AND ?
        ORDER BY 
            o.date_event DESC
    ";

    // Use prepared statements to prevent SQL injection.
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDateStr, $endDateStr);
    $stmt->execute();
    $result = $stmt->get_result();

    // --- CSV File Generation ---

    // Set HTTP headers to force the browser to download the generated file.
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Open PHP's output stream to write the CSV data directly to the response.
    $output = fopen('php://output', 'w');
    
    // Add a UTF-8 BOM to ensure special characters display correctly in Excel.
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // ✨ FIXED: The header row for the CSV file now omits 'Assigned Operator'.
    $headers = [
        'Order ID', 'Customer Name', 'Email', 'Event Date', 
        'Event Type', 'Total Amount', 'Payment Status', 'Booking Status'
    ];
    fputcsv($output, $headers);

    // Loop through the database results and write each order as a new row in the CSV.
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // ✨ FIXED: The data row now matches the corrected header row.
            fputcsv($output, [
                $row['id'],
                $row['customer_name'],
                $row['email'],
                $row['date_event'],
                $row['type_event'],
                $row['total_amount'],
                $row['calculated_payment_status'],
                $row['booking_status']
            ]);
        }
    }

    // Clean up resources.
    $stmt->close();
    fclose($output);

} catch (Exception $e) {
    // If any part of the process fails, send an error message.
    http_response_code(500);
    error_log("Report Generation Failed: " . $e->getMessage());
    exit("An error occurred while generating the report. Please check server logs for details.");
}

// End the script execution.
exit();
?>

