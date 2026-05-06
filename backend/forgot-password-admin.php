<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Admin Reset Password</title>
    <link rel="icon" type="image/png" href="images/bfun.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/login.css">
</head>
<body>
  <div class="login-container">
    <h2>Reset Admin Password</h2>
    <p>Enter your email to receive a password reset link.</p>

    <form id="resetRequestForm">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required>
      </div>
      <button type="submit" class="login-btn">Send Reset Link</button>
    </form>
    <div class="forgot-link" style="margin-top: 15px; text-align: center;">
        <a href="admin-login.php">Back to login</a>
    </div>
  </div>

  <div class="modal" id="feedbackModal">
    <div class="modal-content">
      <h2 id="modalTitle">Message</h2>
      <p id="modalMessage"></p>
      <button class="close-btn" onclick="closeModal()">Close</button>
    </div>
  </div>
  
  <link rel="stylesheet" href="style/login.css"> <script>
    // Modal helpers
    const modal = document.getElementById('feedbackModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    function showModal(title, message) {
      modal.style.display = "flex";
      modalTitle.textContent = title;
      modalMessage.textContent = message;
      setTimeout(() => { modal.classList.add("show"); }, 10);
    }
    function closeModal() {
      modal.classList.remove("show");
      setTimeout(() => { modal.style.display = "none"; }, 300);
    }
    modal.querySelector('.close-btn').addEventListener('click', closeModal);

    // Handle form submission
    document.getElementById('resetRequestForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const button = e.target.querySelector('button');
      button.disabled = true;
      button.textContent = "Sending...";
      
      const formData = new FormData(this);
      const response = await fetch('backend/forgot-password-admin-request.php', {
        method: 'POST',
        body: formData
      });
      const result = await response.json();

      if (result.status === 'success') {
        showModal("Success", result.message);
      } else {
        showModal("Error", result.message);
      }
      button.disabled = false;
      button.textContent = "Send Reset Link";
    });
  </script>
</body>
</html>