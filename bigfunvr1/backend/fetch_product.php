<?php
// This script will act as a data source (API endpoint) for our JavaScript

include "db.php"; // Your database connection

$category = $_GET['category'] ?? 'All';

// Use prepared statements to prevent SQL injection
if ($category === 'All') {
    // If 'All', select all services
    $stmt = $conn->prepare("SELECT id, service_name, price, service_image, service_category FROM services ORDER BY service_name ASC");
} else {
    // Otherwise, select services matching the category
    $stmt = $conn->prepare("SELECT id, service_name, price, service_image, service_category FROM services WHERE service_category = ? ORDER BY service_name ASC");
    $stmt->bind_param("s", $category);
}

$stmt->execute();
$result = $stmt->get_result();

$products = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Build the array for JSON output
        $products[] = [
            "id" => $row['id'],
            "name" => $row['service_name'],
            "price" => $row['price'],
            "image" => 'uploads/' . $row['service_image'],
            "category" => $row['service_category']
        ];
    }
}

$stmt->close();
$conn->close();

// Set the header to indicate JSON content and echo the data
header('Content-Type: application/json');
echo json_encode($products);
?>