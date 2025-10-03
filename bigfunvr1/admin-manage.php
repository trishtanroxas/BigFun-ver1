<?php
session_start();

// 🔒 Prevent cached page access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login-admin.php");
    exit();
}

include "backend/db.php";

// Fetch admin details
$admin_id = $_SESSION['admin_id'];
$admin = [];
if($conn) {
    $stmt = $conn->prepare("SELECT * FROM admin_signup WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Flash messages
$msg = $_SESSION['msg'] ?? null;
$type = $_SESSION['msg_type'] ?? null;
unset($_SESSION['msg'], $_SESSION['msg_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Profile Management</title>
  <link rel="icon" type="image/png" href="images/bfun.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

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
    .sidebar .logo {
      text-align: center;
      margin-bottom: 30px;
    }
    .sidebar .logo img {
      max-height: 50px;
    }
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
    .sidebar .logout-link {
      margin-top: auto;
    }

    /* --- Main Content Card & Tabs --- */
    .content-card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .nav-tabs .nav-link {
      color: var(--dark-gray);
      font-weight: 600;
      border: none;
      border-bottom: 2px solid transparent;
    }
    .nav-tabs .nav-link.active {
      color: var(--primary-color);
      border-bottom: 2px solid var(--primary-color);
      background-color: transparent;
    }
    .tab-content {
      padding-top: 20px;
    }
    .form-label { font-weight: 600; color: #555; }
    .btn-primary { background-color: var(--primary-color); border: none; }
    .btn-primary:hover { background-color: #661966; }

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
      <img src="images/bgfunlogo.png" alt="BigFun Logo">
    </div>
    
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item"><a href="admin-dashboard.php" class="nav-link"><span class="material-symbols-outlined">dashboard</span>Dashboard Overview</a></li>
      <li class="nav-item"><a href="admin-appointments.php" class="nav-link"><span class="material-symbols-outlined">event_note</span>Booked Appointments</a></li>
      <li class="nav-item"><a href="admin-services.php" class="nav-link"><span class="material-symbols-outlined">design_services</span>Services Administration</a></li>
      <li class="nav-item"><a href="admin-customers.php" class="nav-link"><span class="material-symbols-outlined">group</span>Customers Management</a></li>
      <li class="nav-item"><a href="admin-manage.php" class="nav-link active"><span class="material-symbols-outlined">person</span>Profile</a></li>
    </ul>

    <div class="logout-link">
      <a href="backend/logout-admin.php" class="btn btn-light w-100">Log Out</a>
    </div>
  </div>

  <header class="mobile-header">
    <img src="images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
    <span class="material-symbols-outlined menu-icon" data-bs-toggle="offcanvas" data-bs-target="#sidebar">menu</span>
  </header>

  <div class="main-content">
    <h1 class="mb-4">Manage Profile</h1>

    <div class="card content-card">
      <div class="card-header bg-white border-0 pt-3">
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details-pane" type="button" role="tab">Profile Details</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-pane" type="button" role="tab">Security</button></li>
        </ul>
      </div>
      <div class="card-body p-4">
        <div class="tab-content" id="profileTabsContent">
          
          <div class="tab-pane fade show active" id="details-pane" role="tabpanel">
            <form action="backend/admin-update.php" method="POST">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">Middle Initial</label><input type="text" class="form-control" name="middle_initial" value="<?php echo htmlspecialchars($admin['middle_initial'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">Country</label><input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($admin['country'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">City</label><input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($admin['city'] ?? ''); ?>"></div>
                <div class="col-md-6"><label class="form-label">Postal Code</label><input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($admin['postal_code'] ?? ''); ?>"></div>
                <div class="col-12"><label class="form-label">Address</label><input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($admin['address'] ?? ''); ?>"></div>
                <div class="col-12"><label class="form-label">Email Address</label><input type="email" class="form-control" readonly value="<?php echo htmlspecialchars($admin['email']); ?>"></div>
                <div class="col-12 mt-4"><button type="submit" class="btn btn-primary">Save Profile</button></div>
              </div>
            </form>
          </div>

          <div class="tab-pane fade" id="password-pane" role="tabpanel">
            <form action="backend/admin-change-password.php" method="POST">
              <div class="row g-3">
                <div class="col-12"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                <div class="col-12"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" required></div>
                <div class="col-12"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" required></div>
                <div class="col-12 mt-4"><button type="submit" class="btn btn-primary">Update Password</button></div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="msgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?php echo $type === "success" ? "Success" : "Error"; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><?php echo htmlspecialchars($msg ?? ""); ?></div>
        <div class="modal-footer"><button class="btn btn-primary" data-bs-dismiss="modal">OK</button></div>
      </div>
    </div>
  </div>

  <script>
    <?php if ($msg): ?>
      const msgModal = new bootstrap.Modal(document.getElementById('msgModal'));
      msgModal.show();
    <?php endif; ?>
  </script>
</body>
</html>