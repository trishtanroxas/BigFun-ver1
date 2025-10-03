<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Sign Up</title>
    <link rel="icon" type="image/png" href="images/bfun.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <link rel="stylesheet" href="style/signup.css">

    <style>
      .admin-signup-link {
        text-align: center;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #eee;
      }
      .admin-btn {
        display: inline-block;
        padding: 10px 24px;
        font-size: 1rem;
        font-weight: 500;
        color: #fff;
        background-color: #6c757d; /* A neutral, secondary grey */
        border: none;
        border-radius: 5px;
        text-decoration: none;
        transition: background-color 0.2s ease-in-out;
      }
      .admin-btn:hover {
        background-color: #5a6268;
        color: #fff;
      }
    </style>
</head>
<body>
<div class="signup-container">
  <h2>Sign up</h2>
  <p>Already have an account? <a href="login.php">Log in</a></p>

  <form id="signupForm" action="backend/signup.php" method="post" novalidate>
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

    <button type="submit" class="signup-btn">Sign up</button>
  </form>

  <div class="admin-signup-link">
    <a href="signup-admin.php" class="admin-btn">Sign up as an Admin</a>
  </div>

</div>

  <script>
    // robust toggle: wait for DOM ready, then attach one listener
    document.addEventListener('DOMContentLoaded', function () {
      const toggle = document.querySelector('.toggle-password');
      const password = document.getElementById('password');

      // guard: element must exist
      if (!toggle || !password) return;

      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        // toggle type
        const isText = password.type === 'text';
        password.type = isText ? 'password' : 'text';

        // change icon
        this.textContent = isText ? '👁' : '🙈';

        // keep focus on password
        password.focus({preventScroll: true});
      });
    });
  </script>

</body>

</html>