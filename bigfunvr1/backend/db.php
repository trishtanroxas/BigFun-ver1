<?php
$servername = "localhost";
$username   = "root";   // change if needed
$password   = "";       // change if you have a MySQL password
$dbname     = "bigfun_test";

try {
    // Enable mysqli exceptions (requires mysqli extension)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Set charset for safety
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // Handle connection error gracefully
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}
?>
