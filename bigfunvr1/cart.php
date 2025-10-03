<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

include "backend/db.php";

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM signup WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch cart items from the database using a JOIN
$cart_items = [];
$cart_total = 0;
$cart_count = 0;
$categories = ['All']; // For mobile sidebar

$cart_stmt = $conn->prepare("
    SELECT
        ci.id as cart_item_id, ci.quantity,
        s.id as service_id, s.service_name, s.price, s.service_image
    FROM cart_items ci
    JOIN services s ON ci.service_id = s.id
    WHERE ci.user_id = ?
");
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$result = $cart_stmt->get_result();

if ($result->num_rows > 0) {
    while ($item = $result->fetch_assoc()) {
        $cart_items[] = $item;
        $cart_total += $item['price'] * $item['quantity'];
        $cart_count += $item['quantity'];
    }
}
$cart_stmt->close();

// Fetch categories for mobile sidebar
$categories_sql = "SELECT DISTINCT service_category FROM services WHERE service_category IS NOT NULL AND service_category != '' ORDER BY service_category ASC";
$categories_result = $conn->query($categories_sql);
if ($categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row['service_category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Cart - BigFun</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">
  <style>
    :root { --primary-color: #802080; --primary-light: #FFEFFC; }
    body { font-family: 'Inter', sans-serif; background: var(--primary-light); }
    header { display: flex; justify-content: space-between; align-items: center; padding: 20px 6%; background: var(--primary-light); }
    header img { height: 54px; }
    .header-right { display: flex; align-items: center; gap: 20px; }
    .header-right span { font-size: 14px; color: #333; font-weight: 600; }
    .header-right a { text-decoration: none; color: var(--primary-color); font-weight: 600; }
    .btn-cart { padding: 10px 20px; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; font-weight: 600; }
    
    .menu-icon { display: none; cursor: pointer; font-size: 24px; color: var(--primary-color); }
    .sidebar { position: fixed; top: 0; right: 0; height: 100%; width: 260px; background: var(--primary-light); padding: 30px 20px; box-shadow: -2px 0 6px rgba(0,0,0,0.1); z-index: 1000; transform: translateX(100%); transition: transform 0.3s ease-in-out; overflow-y: auto; text-align: center; }
    .sidebar.active { transform: translateX(0); }
    .sidebar h6 { font-size: 17px; font-weight: bold; text-transform: uppercase; color: var(--primary-color); margin: 25px 0 15px; }
    .sidebar a { display: block; margin: 12px 0; text-decoration: none; color: #555; padding: 8px 0; font-size: 15px; }
    .sidebar .close-btn { font-size: 22px; font-weight: bold; color: var(--primary-color); background: none; border: none; cursor: pointer; position: absolute; top: 15px; right: 15px; }
    .sidebar .logout { display: block; margin: 30px auto 10px; padding: 10px; text-align: center; background: var(--primary-color); color: #fff; border-radius: 6px; font-weight: 600; text-decoration: none; width: 80%; }
    
    .cart-item-card { background: #fff; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .cart-item-card img { width: 100px; height: 100px; object-fit: cover; border-radius: 10px; }
    .quantity-input { max-width: 70px; }
    .order-summary-card { background: #fff; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); position: sticky; top: 20px; }
    .btn-checkout { background-color: #28a745; color: white; padding: 0.75rem; font-weight: 600; }
    .remove-btn { color: var(--primary-color); font-weight: 600; cursor: pointer; font-size: 1.5rem; line-height: 1; }
    .item-total { font-weight: 700; }
    @media(max-width:768px) { .header-right { display: none; } .menu-icon { display: block; } }
  </style>
</head>
<body>
  <header>
    <img src="images/bgfunlogo.png" alt="BigFun Logo">
    <div class="header-right">
      <span>Hello, <?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?></span>
      <a href="profile-manage.php">Profile Management</a>
      <button class="btn-cart" onclick="window.location.href='cart.php'">Cart (<span id="cart-count"><?php echo $cart_count; ?></span>)</button>
    </div>
    <div class="menu-icon" onclick="toggleSidebar()">☰</div>
  </header>

  <div class="sidebar" id="sidebar">
    <button class="close-btn" onclick="toggleSidebar()">×</button>
    <button class="btn-cart mb-4" onclick="window.location.href='cart.php'">Cart (<span id="mobile-cart-count"><?php echo $cart_count; ?></span>)</button>
    <h6>Management</h6>
    <a href="overview.php">Overview Panel</a>
    <a href="appointments.php">My Appointments</a>
    <a href="invoices.php">Invoices & Payments</a>
    <a href="profile.php">Profile & Settings</a>
    <h6>Services Category</h6>
    <?php foreach ($categories as $category): ?>
        <a href="book-dashboard.php?category=<?php echo urlencode($category); ?>"><?php echo htmlspecialchars($category); ?></a>
    <?php endforeach; ?>
    <a href="backend/logout.php" class="logout">Log Out</a>
  </div>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 style="font-family: 'Inria Sans', sans-serif;">Service Cart</h1>
        <a href="book-dashboard.php" class="text-decoration-none fw-bold" style="color: var(--primary-color);">Continue Shopping</a>
    </div>

    <div class="row g-4">
        <?php if (empty($cart_items)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">Your cart is empty.</div>
            </div>
        <?php else: ?>
            <div class="col-lg-8">
                <div id="cart-items-container">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="card cart-item-card mb-3 p-3" id="cart-item-<?php echo $item['cart_item_id']; ?>">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-2 col-3"><img src="uploads/<?php echo htmlspecialchars($item['service_image']); ?>" alt="<?php echo htmlspecialchars($item['service_name']); ?>"></div>
                            <div class="col-md-4 col-9"><h5 class="mb-0 fs-6"><?php echo htmlspecialchars($item['service_name']); ?></h5><small class="text-muted">$<?php echo number_format($item['price'], 2); ?> each</small></div>
                            <div class="col-md-3 col-6">
                                <input type="number" class="form-control form-control-sm quantity-input mx-auto" value="<?php echo $item['quantity']; ?>" min="1" data-item-id="<?php echo $item['cart_item_id']; ?>" data-price="<?php echo $item['price']; ?>" onchange="updateCartItem(this)">
                            </div>
                            <div class="col-md-2 col-3 text-center"><span class="item-total" id="item-total-<?php echo $item['cart_item_id']; ?>">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span></div>
                            <div class="col-md-1 col-3 text-end">
                                <span class="remove-btn" title="Remove item" data-item-id="<?php echo $item['cart_item_id']; ?>" onclick="removeCartItem(this)">&times;</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="order-summary-card p-4">
                    <h4 class="mb-3">Order Summary</h4>
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span id="summary-subtotal">$<?php echo number_format($cart_total, 2); ?></span></div>
                    <div class="d-flex justify-content-between mb-3"><span>Shipping</span><span>Calculated at checkout</span></div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-4"><span>Total</span><span id="summary-total">$<?php echo number_format($cart_total, 2); ?></span></div>
                    <a href="checkout.php" class="btn btn-checkout w-100">Proceed to Checkout</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- JAVASCRIPT FOR MOBILE SIDEBAR AND DYNAMIC CART ---

    const cartCountEl = document.getElementById('cart-count');
    const mobileCartCountEl = document.getElementById('mobile-cart-count');
    const summarySubtotalEl = document.getElementById('summary-subtotal');
    const summaryTotalEl = document.getElementById('summary-total');
    const cartItemsContainer = document.getElementById('cart-items-container');

    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('active');
    }

    async function sendCartUpdate(cartItemId, quantity) {
        const formData = new FormData();
        formData.append('cart_item_id', cartItemId);
        formData.append('quantity', quantity);

        try {
            const response = await fetch('backend/update_cart.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                cartCountEl.textContent = data.cart_count;
                if(mobileCartCountEl) mobileCartCountEl.textContent = data.cart_count;
                if(summarySubtotalEl) summarySubtotalEl.textContent = '$' + data.cart_total;
                if(summaryTotalEl) summaryTotalEl.textContent = '$' + data.cart_total;
                
                // If cart is now empty, show message
                if (data.cart_count == 0) {
                    // This logic assumes the summary card is a direct child of its column wrapper
                    const summaryWrapper = document.querySelector('.order-summary-card').parentElement;
                    const cartItemsWrapper = cartItemsContainer.parentElement;
                    
                    // Replace cart items and summary with an "empty" message that spans the whole area
                    const row = cartItemsWrapper.parentElement;
                    row.innerHTML = '<div class="col-12"><div class="alert alert-info text-center">Your cart is empty.</div></div>';
                }

            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Error updating cart:', error);
            alert('An error occurred. Please refresh the page.');
        }
    }

    function updateCartItem(inputElement) {
        const cartItemId = inputElement.dataset.itemId;
        const quantity = parseInt(inputElement.value);

        if (quantity < 1) {
            inputElement.value = 1; return;
        }

        // This sends the update to the server, which recalculates the *overall* totals
        sendCartUpdate(cartItemId, quantity);

        // --- CLIENT-SIDE UPDATE FOR INSTANT FEEDBACK ---
        
        // FIXED: Read the raw price directly from the data-price attribute.
        // This is reliable and not affected by currency symbols or commas.
        const price = parseFloat(inputElement.dataset.price);

        // REMOVED: These old lines were fragile and caused the bug.
        // const itemCard = document.getElementById(`cart-item-${cartItemId}`);
        // const price = parseFloat(itemCard.querySelector('.text-muted').textContent.replace('$', '').replace(' each', ''));
        
        const itemTotalEl = document.getElementById(`item-total-${cartItemId}`);
        // Update the specific item's total on the screen
        itemTotalEl.textContent = '$' + (price * quantity).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function removeCartItem(buttonElement) {
        const cartItemId = buttonElement.dataset.itemId;
        if (confirm('Are you sure you want to remove this item?')) {
            const itemCard = document.getElementById(`cart-item-${cartItemId}`);
            if (itemCard) {
                itemCard.style.transition = 'opacity 0.3s';
                itemCard.style.opacity = '0';
                setTimeout(() => itemCard.remove(), 300);
            }
            // Quantity 0 is the signal to the backend to delete the item
            sendCartUpdate(cartItemId, 0); 
        }
    }
</script>
</body>
</html>