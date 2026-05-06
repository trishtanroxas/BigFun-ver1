<?php
session_start();
$data = json_decode(file_get_contents("php://input"), true);
$_SESSION['cart'] = $data;

// Optionally return JSON
echo json_encode(["status" => "success", "cart" => $_SESSION['cart']]);
