<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Forgot Password</title>
    <link rel="icon" type="image/png" href="assets/images/bfun.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <!-- Links to the same login CSS for the background -->
    <link rel="stylesheet" href="assets/style/login.css">
    
    <!-- Custom modal styles are in login.css -->
</head>

<body>
  <div class="login-container">
    <h2>Forgot Password</h2>
    <p>Enter your email and we'll send you a reset link. <a href="index.php?route=login-form">Back to login</a></p>

    <form id="forgotForm">
      
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required>
      </div>

      <button type="submit" class="login-btn">
        Send Reset Link
        <!-- Added spinner for loading state -->
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none; margin-left: 8px;"></span>
      </button>
    </form>

  </div>

  <!-- 
    REVERTED to your original custom modal to fix styling
  -->
  <div class="modal" id="feedbackModal">
    <div class="modal-content">
      <h2 id="modalTitle">Message</h2>
      <p id="modalMessage"></p>
      <button class="close-btn" onclick="closeModal()">Close</button>
    </div>
  </div>


  <script>
    // --- REVERTED to your original modal helpers ---
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

    // Handle form submission
    document.getElementById('forgotForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const button = e.target.querySelector('button');
      const spinner = button.querySelector('.spinner-border');

      // Show loading state
      button.disabled = true;
      spinner.style.display = 'inline-block';

      try {
        const response = await fetch('backend/send-reset-link.php', {
          method: 'POST',
          body: formData
        });

        // --- ADDED DEBUGGING ---
        // 1. Get the raw text from the response
        const rawResponse = await response.text();
        console.log("Raw response from server:", rawResponse);

        // 2. Now, try to parse it as JSON
        const result = JSON.parse(rawResponse);
        // -------------------------

        if (result.status === 'success') {
          showModal("Check Your Email", result.message);
          e.target.reset(); // Clear the form
        } else {
          showModal("Error", result.message);
        }
      } catch (error) {
        // This will fire if the JSON.parse fails
        console.error("Fetch/JSON Parse error:", error);
        showModal("System Error", "Could not connect to the server. Please try again.");
      }

      // Hide loading state
      button.disabled = false;
      spinner.style.display = 'none';
    });
  </script>
</body>
</html>




