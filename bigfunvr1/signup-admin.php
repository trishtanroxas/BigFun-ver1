<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Admin Sign Up</title>
    <link rel="icon" type="image/png" href="images/bfun.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style/signup.css">
</head>
<body>
<div class="signup-container">
  <h2>Admin Sign Up</h2>
  <p>Already an admin? <a href="login-admin.php">Log in</a></p>

  <!-- ✅ Simple signup form -->
  <form id="signupForm" action="backend/signup-admin.php" method="post" novalidate>
    <div class="form-group">
      <label for="email1">Email Address</label>
      <input type="email" id="email1" name="email1" required>
    </div>

    <div class="form-group">
      <label for="email2">Confirm Email Address</label>
      <input type="email" id="email2" name="email2" required>
    </div>

    <div class="form-group password-wrapper">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <span class="toggle-password" role="button" aria-label="Show password">👁</span>
    </div>

    <button type="submit" class="signup-btn">Sign up as Admin</button>
  </form>
</div>

<script>
  // toggle password
  document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.toggle-password');
    const password = document.getElementById('password');

    if (!toggle || !password) return;

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      const isText = password.type === 'text';
      password.type = isText ? 'password' : 'text';
      this.textContent = isText ? '👁' : '🙈';
      password.focus({preventScroll: true});
    });
  });
</script>

</body>
</html>
