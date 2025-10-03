<?php
$status = $_GET['status'] ?? "invalid";

$title = "Invalid Link ❌";
$message = "The verification link is invalid or expired. Redirecting you to login page...";
$redirect = "../login.php";

if ($status === "verified") {
    $title = "Email Verified 🎉";
    $message = "Your email has been successfully verified. Redirecting you to the login page...";
} elseif ($status === "already_verified") {
    $title = "Already Verified ⚡";
    $message = "Your email has already been verified. Redirecting you to the login page...";
} elseif ($status === "check_inbox") {
    $title = "Check Your Inbox 📩";
    $message = "We’ve sent a verification link to your email. Please open it to verify your account.";
    $redirect = "../login.php"; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email Verification</title>
  <link href="https://fonts.googleapis.com/css2?family=Inria+Serif:wght@700&family=Inter&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f9f9f9;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }
    .message-box {
      background: #fff;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 420px;
    }
    h2 {
      font-family: 'Inria Serif', serif;
      margin-bottom: 1rem;
    }
    p {
      margin-bottom: 1.5rem;
    }
    .btn {
      display: inline-block;
      padding: 0.8rem 1.5rem;
      border-radius: 25px;
      background: #8C367C;
      color: #fff;
      text-decoration: none;
      font-size: 0.95rem;
    }
  </style>
  <script>
    setTimeout(() => {
      window.location.href = "<?= $redirect ?>";
    }, 9000);
  </script>
</head>
<body>
  <div class="message-box">
    <h2><?= htmlspecialchars($title) ?></h2>
    <p><?= htmlspecialchars($message) ?></p>
    <a href="<?= $redirect ?>" class="btn">Go Now</a>
  </div>
</body>
</html>
