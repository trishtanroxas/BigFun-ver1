<?php
session_start();

// 🔒 Prevent cached page access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

include "backend/db.php";

// Fetch logged in user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM signup WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch cart count from the database
$cart_count = 0;
if (isset($user_id)) {
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $cart_count = $count_result['total_items'] ?? 0;
    $count_stmt->close();
}


// Flash messages
$msg = $_SESSION['msg'] ?? null;
$type = $_SESSION['msg_type'] ?? null;
unset($_SESSION['msg'], $_SESSION['msg_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-color: #802080;
      --light-pink: #FFEFFC;
      --light-gray: #f8f9fa;
      --border-color: #dee2e6;
    }
    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--light-gray);
    }

    /* --- Main Layout --- */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 260px;
      background-color: #fff;
      border-right: 1px solid var(--border-color);
      padding: 20px;
      display: flex;
      flex-direction: column;
    }
    .main-content {
      margin-left: 260px;
      padding: 30px;
    }
    .mobile-header {
      display: none;
      background-color: #fff;
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
    }
    .menu-icon {
      font-size: 28px;
      color: var(--primary-color);
      cursor: pointer;
    }

    /* --- Sidebar Content --- */
    .sidebar .logo { text-align: center; margin-bottom: 30px; }
    .sidebar .logo img { max-height: 50px; }
    .sidebar .nav-pills .nav-link {
      color: #555;
      font-size: 15px;
      font-weight: 600;
      padding: 12px 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .sidebar .nav-pills .nav-link.active,
    .sidebar .nav-pills .nav-link:hover {
      background-color: var(--light-pink);
      color: var(--primary-color);
    }
    .sidebar .logout-link { margin-top: auto; }
    .sidebar .btn-cart {
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
        border: none;
    }

    /* --- Main Content Card & Tabs --- */
    .content-card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .content-card h1 { font-family: 'Inria Sans', sans-serif; }
    .nav-tabs .nav-link {
      font-weight: 600;
      border: none;
      border-bottom: 3px solid transparent;
      color: #6c757d;
    }
    .nav-tabs .nav-link.active {
      color: var(--primary-color);
      border-bottom-color: var(--primary-color);
      background-color: transparent;
    }
    .form-label { font-weight: 600; }
    .btn-submit { background:var(--primary-color); border:none; color:white; font-weight:600; }
    .btn-submit:hover { background-color: #661966; }

    /* --- Responsive --- */
    @media(max-width: 991.98px) {
      .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
        z-index: 1050;
      }
      .sidebar.offcanvas.show {
        transform: translateX(0);
      }
      .main-content {
        margin-left: 0;
      }
      .mobile-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
    }
  </style>
</head>
<body>

  <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
    <div class="logo">
      <a href="book-dashboard.php"><img src="images/bgfunlogo.png" alt="BigFun Logo"></a>
    </div>
    
    <button class="btn btn-cart w-100 mb-4" onclick="window.location.href='cart.php'">
        Cart (<?php echo $cart_count; ?>)
    </button>

    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item"><a href="book-dashboard.php" class="nav-link"><span class="material-symbols-outlined">storefront</span>Services</a></li>
      <li class="nav-item"><a href="booking-status.php" class="nav-link"><span class="material-symbols-outlined">history</span>Booking Status</a></li>
      <li class="nav-item"><a href="invoices.php" class="nav-link"><span class="material-symbols-outlined">receipt_long</span>Invoice and Payment</a></li>
      <li class="nav-item"><a href="profile-manage.php" class="nav-link active"><span class="material-symbols-outlined">person</span>Profile</a></li>
    </ul>

    <div class="logout-link">
      <a href="backend/logout.php" class="btn btn-light w-100">Log Out</a>
    </div>
  </div>

  <header class="mobile-header">
    <img src="images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
    <span class="material-symbols-outlined menu-icon" data-bs-toggle="offcanvas" data-bs-target="#sidebar">menu</span>
  </header>

  <div class="main-content">
    
    <?php if ($msg): ?>
      <div class="alert alert-<?php echo $type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    
    <div class="card content-card">
      <div class="card-header bg-white border-0 p-4">
        <h1 class="mb-0">Profile Management</h1>
        <p class="text-muted">Update your personal details and manage your password.</p>
        <ul class="nav nav-tabs mt-3" id="profileTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details-pane" type="button" role="tab">Profile Details</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-pane" type="button" role="tab">Change Password</button>
          </li>
        </ul>
      </div>
      <div class="card-body p-4">
        <div class="tab-content" id="profileTabsContent">
          
          <div class="tab-pane fade show active" id="details-pane" role="tabpanel">
            <form action="backend/update-profile.php" method="POST">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">Middle Initial</label><input type="text" class="form-control" name="middle_initial" value="<?php echo htmlspecialchars($user['middle_initial'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">Country</label><input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">City</label><input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">Postal Code</label><input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>"></div>
                <div class="col-12"><label class="form-label">Address</label><input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"></div>
                
                <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" class="form-control" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email Address</label>
                  <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly disabled>
                  <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                </div>
              </div>
              <button type="submit" class="btn btn-submit mt-4">Save Profile</button>
            </form>
          </div>

          <div class="tab-pane fade" id="password-pane" role="tabpanel">
            <form action="backend/change-password.php" method="POST">
              <div class="row g-3">
                <div class="col-12"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                <div class="col-12"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" required></div>
                <div class="col-12"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" required></div>
              </div>
              <button type="submit" class="btn btn-submit mt-4">Update Password</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>