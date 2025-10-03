<?php
session_start();
header('Content-Type: application/json');
include "db.php";

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Censor functions
function censor_email($email) {
    if (empty($email) || !strpos($email, '@')) {
        return 'N/A';
    }
    list($user, $domain) = explode('@', $email);
    return substr($user, 0, 2) . str_repeat('*', 5) . '@' . $domain;
}

function censor_contact($number) {
    if (empty($number) || strlen($number) < 8) {
        return 'N/A';
    }
    return substr($number, 0, 4) . str_repeat('*', strlen($number) - 8) . substr($number, -4);
}


// --- MODE 1: Get Details for a Single Customer ---
if (isset($_GET['customer_id'])) {
    $customerId = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid customer ID.']);
        exit();
    }

    try {
        // Fetch customer details, combining first_name and last_name
        $details_query = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, contact_number, created_at as join_date FROM signup WHERE id = ?";
        $stmt = $conn->prepare($details_query);
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $details_result = $stmt->get_result()->fetch_assoc();

        if (!$details_result) {
             throw new Exception("Customer not found.");
        }

        // Apply censoring
        $details_result['email_censored'] = censor_email($details_result['email']);
        $details_result['contact_number_censored'] = censor_contact($details_result['contact_number']);

        // Fetch booking history
        $bookings_query = "SELECT id, date_event, total_amount, payment_status FROM orders WHERE user_id = ? ORDER BY date_event DESC";
        $stmt = $conn->prepare($bookings_query);
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $bookings_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'status' => 'success',
            'details' => $details_result,
            'bookings' => $bookings_result
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}


// --- MODE 2: Get Paginated List of All Customers ---
try {
    // Pagination and Sorting parameters
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10]]);
    $sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_STRING, ['options' => ['default' => 'join_date']]);
    $order = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_STRING, ['options' => ['default' => 'desc']]);
    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);

    // Whitelist sortable columns to prevent SQL injection
    $sort_whitelist = ['full_name', 'email', 'join_date', 'total_bookings', 'total_spent'];
    $sort_column = 'u.created_at'; // default sort
    if (in_array($sort, $sort_whitelist)) {
        if ($sort === 'full_name') {
            $sort_column = 'full_name';
        } elseif ($sort === 'join_date') {
            $sort_column = 'u.created_at';
        } else {
            $sort_column = $sort;
        }
    }
    
    // Whitelist sort order
    if (!in_array(strtolower($order), ['asc', 'desc'])) {
        $order = 'desc';
    }

    $offset = ($page - 1) * $limit;
    $params = [];
    $types = "";

    // Build the query
    $base_query = "
        FROM signup u
        LEFT JOIN orders o ON u.id = o.user_id
    ";
    
    $where_clause = "";
    if (!empty($search)) {
        // Search on the combined full name
        $where_clause = "WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(DISTINCT u.id) as total " . $base_query . $where_clause;
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_customers = $stmt->get_result()->fetch_assoc()['total'];

    // Get customer data
    $data_query = "
        SELECT 
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            u.email,
            u.created_at as join_date,
            COUNT(o.id) as total_bookings,
            COALESCE(SUM(o.total_amount), 0) as total_spent
        " . $base_query . $where_clause . "
        GROUP BY u.id
        ORDER BY {$sort_column} {$order}
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($data_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'success',
        'customers' => $customers,
        'total_customers' => $total_customers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>