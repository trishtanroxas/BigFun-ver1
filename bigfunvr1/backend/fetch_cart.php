<?php
session_start();
header('Content-Type: application/json');

if(isset($_SESSION['cart'])) {
  echo json_encode($_SESSION['cart']);
} else {
  echo json_encode([]);
}
