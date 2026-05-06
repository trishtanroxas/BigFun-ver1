<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Login</title>
    <link rel="icon" type="image/png" href="assets/images/bfun.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/login.css">
</head>
<body>
  <div class="login-container">
    <h2>Log in</h2>
    <p>Need an account? <a href="signup.php">Create an account</a></p>
    <form id="loginForm">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required>
      </div>
      <div class="form-group password-wrapper">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <span class="toggle-password">👁</span>
        <div class="forgot-link"><a href="forgot-password.php">Forgot password?</a></div>
      </div>
      <button type="submit" class="login-btn">Log in</button>
    </form>
  </div>

  <div class="modal" id="feedbackModal">
    <div class="modal-content">
      <h2 id="modalTitle">Message</h2>
      <p id="modalMessage"></p>
      <button class="close-btn" onclick="closeModal()">Close</button>
    </div>
  </div>

  <script>
    // Simplified JS for demonstration
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const response = await fetch('index.php?route=login', {
        method: 'POST',
        body: formData
      });
      const result = await response.json();
      if (result.status === 'success') {
        window.location.href = result.redirect;
      } else {
        alert(result.message);
      }
    });
  </script>
</body>
</html>
