<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Login</title>
    <link rel="icon" type="image/png" href="images/bfun.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <link rel="stylesheet" href="style/login.css">
    
    <style>
      /* Main container for the toggle switch */
      .role-selector {
        display: flex;
        border: 1px solid #ccc;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      }
      
      /* Hide the default radio buttons */
      .role-selector input[type="radio"] {
        display: none;
      }
      
      /* Style the labels to look like buttons */
      .role-selector label {
        flex: 1; /* Make both labels take up equal space */
        text-align: center;
        padding: 12px;
        cursor: pointer;
        background-color: #f1f1f1;
        color: #555;
        transition: all 0.3s ease;
        font-weight: 500;
      }
      
      /* Add a subtle line between the buttons */
      .role-selector label:first-of-type {
        border-right: 1px solid #ccc;
      }
      
      /* Style for the selected (checked) label */
      .role-selector input[type="radio"]:checked + label {
        background-color: #337ab7; /* A professional blue */
        color: white;
        font-weight: bold;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
      }
    </style>
</head>

<body>
  <div class="login-container">
    <h2>Log in</h2>
    <p>Need an account? <a href="signup.php">Create an account</a></p>

    <form id="loginForm">

      <div class="role-selector">
          <input type="radio" id="role-user" name="role" value="user" checked>
          <label for="role-user">User</label>
          <input type="radio" id="role-admin" name="role" value="admin">
          <label for="role-admin">Admin</label>
      </div>
      
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required>
      </div>

      <div class="form-group password-wrapper">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <span class="toggle-password">👁</span>
        <div class="forgot-link"><a href="#">Forgot password?</a></div>
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
    // Toggle password visibility
    document.querySelector('.toggle-password').addEventListener('click', function() {
      const password = document.getElementById('password');
      if (password.type === 'password') {
        password.type = 'text';
        this.textContent = '🙈';
      } else {
        password.type = 'password';
        this.textContent = '👁';
      }
    });

    // Modal helpers
    const modal = document.getElementById('feedbackModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');

    function showModal(title, message) {
      modal.style.display = "flex";
      modalTitle.textContent = title;
      modalMessage.textContent = message;
      setTimeout(() => {
        modal.classList.add("show");
      }, 10);
    }

    function closeModal() {
      modal.classList.remove("show");
      setTimeout(() => {
        modal.style.display = "none";
      }, 300);
    }

    // Handle login
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      // Get selected role and set backend endpoint
      const selectedRole = document.querySelector('input[name="role"]:checked').value;
      const endpoint = selectedRole === 'admin' ? 'backend/login-admin.php' : 'backend/login.php';

      const response = await fetch(endpoint, {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.status === 'success') {
        showModal("Welcome", "Login successful! Redirecting...");
        setTimeout(() => {
          window.location.href = result.redirect;
        }, 1500);
      } else {
        showModal("Login Error", result.message);
      }
    });
  </script>
</body>
</html>