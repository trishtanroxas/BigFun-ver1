<?php
session_start();
// This file doesn't send email, but we keep the AJAX handler for marking notifications as read.
// The email functions are not needed here, but the AJAX handler for notifications is.

// --- NEW: AJAX Handler to mark notification as read ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_notif_read') {
    include "backend/db.php";
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
        exit;
    }

    $notification_id = intval($_POST['notification_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($notification_id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
    }
    $conn->close();
    exit;
}


// --- Regular Page Load Logic ---
include "backend/db.php";

if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_count = 0;
$count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$result = $count_stmt->get_result();
$cart_data = $result->fetch_assoc();
$cart_count = $cart_data['total_items'] ?? 0;
$count_stmt->close();

// --- Fetch Unread Notification Count ---
$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result()->fetch_assoc();
$unread_count = $notif_result['unread_count'] ?? 0;
$notif_stmt->close();

// --- Fetch All Notifications ---
$notifications = [];
$all_notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$all_notif_stmt->bind_param("i", $user_id);
$all_notif_stmt->execute();
$notif_list_result = $all_notif_stmt->get_result();
while ($row = $notif_list_result->fetch_assoc()) {
    $notifications[] = $row;
}
$all_notif_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications - BigFun</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">
    <style>
      :root { --primary-color: #802080; --primary-light: #FFEFFC; --light-gray: #f8f9fa; --border-color: #dee2e6; }
      body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); }
      .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 260px; background-color: #fff; border-right: 1px solid var(--border-color); padding: 20px; display: flex; flex-direction: column; }
      .main-content { margin-left: 260px; padding: 30px; }
      .mobile-header { display: none; background-color: #fff; padding: 15px 20px; border-bottom: 1px solid var(--border-color); }
      .menu-icon { font-size: 28px; color: var(--primary-color); cursor: pointer; }
      .sidebar .logo { text-align: center; margin-bottom: 30px; }
      .sidebar .logo img { max-height: 50px; }
      .sidebar .nav-pills .nav-link { color: #555; font-size: 15px; font-weight: 600; padding: 12px 15px; display: flex; align-items: center; gap: 10px; }
      .sidebar .nav-pills .nav-link.active, .sidebar .nav-pills .nav-link:hover { background-color: var(--primary-light); color: var(--primary-color); }
      .sidebar .logout-link { margin-top: auto; }
      .sidebar .btn-cart { background-color: var(--primary-color); color: white; font-weight: 600; border: none; }
      
      /* Notification Styles */
      .notification-item.unread { background-color: #f8f9fa; }
      .notification-item h5.fw-bold { color: var(--primary-color); }
      .notification-item { cursor: pointer; border-radius: 8px !important; margin-bottom: 8px; border: 1px solid var(--border-color); }
      .notification-item:hover { background-color: #f1f1f1; }
      .list-group-item { border: 1px solid var(--border-color); } /* Override bootstrap default */

      /* Modal Style */
      #notificationModalBody {
        white-space: pre-wrap; /* Respects newlines from nl2br */
        word-wrap: break-word; /* Breaks long links or text */
      }

      @media(max-width: 991.98px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 1050; } .sidebar.offcanvas.show { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-header { display: flex; justify-content: space-between; align-items: center; } }
    </style>
</head>
<body>

  <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
    <div class="logo"><a href="book-dashboard.php"><img src="assets/images/bgfunlogo.png" alt="BigFun Logo"></a></div>
    <button class="btn btn-cart w-100 mb-4" onclick="window.location.href='cart.php'">Cart (<?php echo $cart_count; ?>)</button>
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item"><a href="book-dashboard.php" class="nav-link"><span class="material-symbols-outlined">storefront</span>Services</a></li>
      <li class="nav-item"><a href="booking-status.php" class="nav-link"><span class="material-symbols-outlined">history</span>Booking Status</a></li>
      <li class="nav-item"><a href="invoices.php" class="nav-link"><span class="material-symbols-outlined">receipt_long</span>Invoice and Payment</a></li>
      
      <!-- NOTIFICATION LINK IS NOW ACTIVE AND IN ORDER -->
      <li class="nav-item">
        <a href="notification.php" class="nav-link active" id="sidebar-notify-link">
            <span class="material-symbols-outlined">notifications</span>
            Notification
            <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger rounded-pill ms-auto" id="sidebar-notif-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
      </li>
      <li class="nav-item"><a href="profile-manage.php" class="nav-link"><span class="material-symbols-outlined">person</span>Profile</a></li>
    </ul>
    <div class="logout-link"><a href="backend/logout.php" class="btn btn-light w-100">Log Out</a></div>
  </div>

  <header class="mobile-header">
    <img src="assets/images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
    <span class="material-symbols-outlined menu-icon" data-bs-toggle="offcanvas" data-bs-target="#sidebar">menu</span>
  </header>

  <div class="main-content">
    <h1 class="mb-4">Notifications</h1>
    
    <!-- This is the main content for the notification page -->
    <div class="list-group" id="notification-list">
        <?php if(empty($notifications)): ?>
            <div class="alert alert-info">You have no notifications.</div>
        <?php else: foreach($notifications as $notif): ?>
            <div class="list-group-item list-group-item-action notification-item <?php echo ($notif['is_read'] == 0) ? 'unread list-group-item-light' : ''; ?>"
                 data-notif-id="<?php echo $notif['id']; ?>"
                 data-link="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>">
                
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1 <?php echo ($notif['is_read'] == 0) ? 'fw-bold' : ''; ?>"><?php echo htmlspecialchars($notif['title']); ?></h5>
                    <small class="text-muted"><?php echo date("F j, Y, g:i a", strtotime($notif['created_at'])); ?></small>
                </div>
                <!-- We store the raw message in a data attribute for the modal -->
                <p class="mb-1" data-message="<?php echo htmlspecialchars($notif['message']); ?>">
                    <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                </p>
                
                <?php if($notif['is_read'] == 0): ?>
                    <span class="badge bg-primary rounded-pill">New</span>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <!-- End of main content -->

  </div>

  <!-- NEW: Notification Modal -->
  <div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="notificationModalTitle">Notification</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="notificationModalBody">
          ...
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <a href="#" id="notificationModalLink" class="btn btn-primary" style="display: none;">Go to Details</a>
        </div>
      </div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- NEW: Modal Instance ---
    const notifModal = new bootstrap.Modal(document.getElementById('notificationModal'));
    const modalTitle = document.getElementById('notificationModalTitle');
    const modalBody = document.getElementById('notificationModalBody');
    const modalLink = document.getElementById('notificationModalLink');

    // Tooltips are not used on this page, but good to keep if you add them
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    /**
     * NEW: This function finds URLs and email addresses in a string and makes them clickable.
     */
    function linkify(text) {
        // Regex for URLs
        let urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
        text = text.replace(urlRegex, function(url) {
            return '<a href="' + url + '" target="_blank">' + url + '</a>';
        });

        // Regex for emails
        let emailRegex = /([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9._-]+)/ig;
        text = text.replace(emailRegex, function(email) {
            return '<a href="mailto:' + email + '">' + email + '</a>';
        });

        return text.replace(/\n/g, '<br>'); // Replicate nl2br
    }

    // --- Notification Click Handler (MODIFIED) ---
    document.getElementById('notification-list').addEventListener('click', async function(e) {
        const notifItem = e.target.closest('.notification-item');
        if (notifItem) {
            // e.preventDefault(); // We don't need this anymore
            
            const notifId = notifItem.dataset.notifId;
            const link = notifItem.dataset.link;
            const isUnread = notifItem.classList.contains('unread');
            
            // Get data from the item
            const title = notifItem.querySelector('h5').textContent;
            const message = notifItem.querySelector('p').dataset.message; // Get raw message

            // 1. Populate and show modal
            modalTitle.textContent = title;
            modalBody.innerHTML = linkify(message); // Use linkify to make emails/links clickable

            if (link && link !== '#') {
                modalLink.href = link;
                modalLink.style.display = 'inline-block';
            } else {
                modalLink.style.display = 'none';
            }
            notifModal.show();
            
            // 2. Mark as read in the background (if unread)
            if (isUnread) {
                const formData = new FormData();
                formData.append('action', 'mark_notif_read');
                formData.append('notification_id', notifId);

                try {
                    // We post to this page itself, as the AJAX handler is at the top
                    const response = await fetch('notification.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.status === 'success') {
                        notifItem.classList.remove('unread', 'list-group-item-light');
                        notifItem.querySelector('h5')?.classList.remove('fw-bold');
                        notifItem.querySelector('.badge.bg-primary')?.remove();
                        // Update counts
                        updateBadgeCounts();
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    console.error('Failed to mark as read:', error.message);
                }
            }
        }
    });

    function updateBadgeCounts() {
        const sidebarBadge = document.getElementById('sidebar-notif-badge');
        
        if (sidebarBadge) {
            let count = parseInt(sidebarBadge.textContent, 10);
            count--;
            if (count <= 0) {
                sidebarBadge.remove();
            } else {
                sidebarBadge.textContent = count;
            }
        }
    }
});
</script>
</body>
</html>




