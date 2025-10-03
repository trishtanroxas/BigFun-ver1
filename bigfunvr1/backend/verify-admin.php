<?php
include "db.php";

$status = "";
$type = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // 🔎 Check if token exists and not yet verified
    $stmt = $conn->prepare("SELECT id FROM admin_signup WHERE token = ? AND is_verified = 0 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // ✅ Mark as verified
        $update = $conn->prepare("UPDATE admin_signup SET is_verified = 1, verified_at = NOW() WHERE id = ?");
        $update->bind_param("i", $row['id']);
        $update->execute();

        $status = "✅ Admin email verified successfully. You may now log in.";
        $type = "success";
    } else {
        $status = "❌ Invalid or already used token.";
        $type = "danger";
    }
} else {
    $status = "⚠️ No token provided.";
    $type = "warning";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Email Verification</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow border-0">
          <div class="card-body text-center p-5">
            <?php if ($type): ?>
              <div class="alert alert-<?php echo $type; ?> mb-4" role="alert">
                <?php echo $status; ?>
              </div>
            <?php endif; ?>

            <?php if ($type === "success"): ?>
              <a href="login-admin.php" class="btn btn-primary">Go to Admin Login</a>
            <?php else: ?>
              <a href="signup-admin.php" class="btn btn-secondary">Back to Sign Up</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
