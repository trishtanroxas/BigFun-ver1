<?php
session_start();
include "db.php";

$response = ['status' => 'error', 'message' => 'Invalid Request.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $cart_item_id = intval($_POST['cart_item_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? -1);

    if ($cart_item_id > 0) {
        if ($quantity > 0) {
            // Update quantity
            $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("iii", $quantity, $cart_item_id, $user_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($quantity === 0) {
            // Remove item (quantity set to 0)
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cart_item_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        // Recalculate totals to send back
        $total = 0;
        $cart_count = 0;
        $stmt = $conn->prepare("
            SELECT ci.quantity, s.price 
            FROM cart_items ci 
            JOIN services s ON ci.service_id = s.id 
            WHERE ci.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($item = $result->fetch_assoc()) {
            $total += $item['price'] * $item['quantity'];
            $cart_count += $item['quantity'];
        }
        $stmt->close();

        $response = [
            'status' => 'success',
            'cart_total' => number_format($total, 2),
            'cart_count' => $cart_count
        ];
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>