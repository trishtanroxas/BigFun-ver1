<?php
session_start();

// Initialize cart session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Hardcoded product list with categories
$products = [
    1 => ["name" => "Cash Cube", "price" => 599.00, "image" => "images/cash-cube.jpg", "category" => "Promotional Items"],
    2 => ["name" => "Deluxe Cash Cube", "price" => 500.00, "image" => "images/deluxe-cash.jpg", "category" => "Promotional Items"],
    3 => ["name" => "Premium Mechanical Bull QLD", "price" => 1200.00, "image" => "images/premiumbull.jpg", "category" => "Mechanical Rides"],
    4 => ["name" => "Mechanical Surfboard", "price" => 1200.00, "image" => "images/mechanical-surf.jpg", "category" => "Mechanical Rides"],
    5 => ["name" => "Pass the Footy", "price" => 495.00, "image" => "images/footypass.jpeg", "category" => "Sport Games (QLD)"],
    6 => ["name" => "Mega Castle", "price" => 995.00, "image" => "images/jumpcastle.png", "category" => "Slides - Jumping Castles (QLD)"]
];

// Handle add-to-cart
if (isset($_GET['add'])) {
    $id = $_GET['add'];

    if (isset($products[$id])) {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['quantity']++;
        } else {
            $_SESSION['cart'][$id] = [
                "name" => $products[$id]['name'],
                "price" => $products[$id]['price'],
                "image" => $products[$id]['image'],
                "quantity" => 1
            ];
        }
    }

    header("Location: products.php"); // refresh to prevent duplicate add
    exit;
}

// Calculate cart item count
$cart_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_count += $item['quantity'];
}

// Selected category
$selected_category = isset($_GET['category']) ? $_GET['category'] : "All";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BigFun Products</title>
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:wght@700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="test.css">
  <style>
    .add-btn {
      position: absolute;
      top: 15px;
      right: 15px;
      background: #fff;
      color: #9b4d96;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      font-weight: bold;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      text-decoration: none;
    }
    .add-btn:hover {
      background: #9b4d96;
      color: #fff;
    }
    .sidebar ul {
      list-style: none;
      padding: 0;
    }
    .sidebar ul li {
      margin: 6px 0;
    }
    .sidebar ul li a {
      text-decoration: none;
      color: #000;
    }
    .sidebar ul li a.active {
      font-weight: bold;
      color: #9b4d96;
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-custom d-flex justify-content-between align-items-center">
    <div class="logo">Big<span style="color:#58c02f;">Fun</span></div>
    <div class="d-flex align-items-center">
      <div class="me-3 text-end">
        <p class="mb-0">Hello, User</p>
        <small class="text-muted">Account and Dashboard</small>
      </div>
      <a href="cart.php" class="btn">
        Cart <span class="badge bg-light text-dark"><?php echo $cart_count; ?></span>
      </a>
    </div>
  </nav>

  <div class="container-fluid mt-3">
    <div class="row">
      <!-- Sidebar -->
      <div class="col-md-3">
        <div class="sidebar">
          <input type="text" class="form-control mb-3" placeholder="Search">
          <h5>Product Category</h5>
          <ul>
            <li><a href="products.php" class="<?php echo $selected_category=='All'?'active':''; ?>">All</a></li>
            <li><a href="products.php?category=Promotional Items" class="<?php echo $selected_category=='Promotional Items'?'active':''; ?>">Promotional Items</a></li>
            <li><a href="products.php?category=Mechanical Rides" class="<?php echo $selected_category=='Mechanical Rides'?'active':''; ?>">Mechanical Rides</a></li>
            <li><a href="products.php?category=Carnival Rides (QLD)" class="<?php echo $selected_category=='Carnival Rides (QLD)'?'active':''; ?>">Carnival Rides (QLD)</a></li>
            <li><a href="products.php?category=Inflatable Rides" class="<?php echo $selected_category=='Inflatable Rides'?'active':''; ?>">Inflatable Rides</a></li>
            <li><a href="products.php?category=Sport Games (QLD)" class="<?php echo $selected_category=='Sport Games (QLD)'?'active':''; ?>">Sport Games (QLD)</a></li>
            <li><a href="products.php?category=Slides - Jumping Castles (QLD)" class="<?php echo $selected_category=='Slides - Jumping Castles (QLD)'?'active':''; ?>">Slides - Jumping Castles (QLD)</a></li>
            <li><a href="products.php?category=Obstacle Courses (QLD)" class="<?php echo $selected_category=='Obstacle Courses (QLD)'?'active':''; ?>">Obstacle Courses (QLD)</a></li>
          </ul>
          <a href="#" class="btn btn-outline-danger mt-3 w-100">Log Out</a>
        </div>
      </div>

      <!-- Products -->
      <div class="col-md-9">
        <div class="row g-4">
          <?php foreach ($products as $id => $product): ?>
            <?php if ($selected_category == "All" || $product['category'] == $selected_category): ?>
              <div class="col-md-4">
                <div class="card-custom position-relative">
                  <a href="?add=<?php echo $id; ?>" class="add-btn">+</a>
                  <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
                  <h4><?php echo $product['name']; ?></h4>
                  <p>$<?php echo number_format($product['price'], 2); ?></p>
                  <a href="#">See more</a>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
