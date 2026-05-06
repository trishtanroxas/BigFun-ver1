<?php
session_start();

// 🔒 Prevent cached page access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); // Redirect to admin login might be better: admin-login.php
    exit();
}

include "backend/db.php";

// Fetch admin details
$admin_id = $_SESSION['admin_id'];
$admin = [];
if($conn) {
    // *** Use backticks for table/column names if they might be reserved words or contain special chars ***
    $stmt = $conn->prepare("SELECT * FROM `admin_signup` WHERE `id` = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();
    } else {
        error_log("Failed to prepare admin fetch statement: " . $conn->error);
        // Handle error appropriately, maybe set a default admin array or show an error
    }
} else {
     error_log("Database connection failed in admin-manage.php");
     // Handle error
}

// Flash messages
$msg = $_SESSION['msg'] ?? null;
$type = $_SESSION['msg_type'] ?? 'info'; // Default type
unset($_SESSION['msg'], $_SESSION['msg_type']);

// --- Get password change attempts ---
$password_attempts = $_SESSION['admin_change_password_attempts'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Profile Management</title>
  <link rel="icon" type="image/png" href="assets/images/bfun.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Inria+Sans:wght@700&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-color: #802080;
      --light-pink: #FFEFFC;
      --light-gray: #f8f9fa;
      --border-color: #dee2e6;
      --dark-gray: #6c757d; /* Added for consistency */
    }
    body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); }
    .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 260px; background-color: #fff; border-right: 1px solid var(--border-color); padding: 20px; display: flex; flex-direction: column; }
    .main-content { margin-left: 260px; padding: 30px; }
    .mobile-header { display: none; background-color: #fff; padding: 15px 20px; border-bottom: 1px solid var(--border-color); }
    .menu-icon { font-size: 28px; color: var(--primary-color); cursor: pointer; }
    .sidebar .logo { text-align: center; margin-bottom: 30px; }
    .sidebar .logo img { max-height: 50px; }
    .sidebar .nav-pills .nav-link { color: #555; font-size: 15px; font-weight: 600; padding: 12px 15px; display: flex; align-items: center; gap: 10px; }
    .sidebar .nav-pills .nav-link.active, .sidebar .nav-pills .nav-link:hover { background-color: var(--light-pink); color: var(--primary-color); }
    .sidebar .logout-link { margin-top: auto; }
    .content-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .content-card h1 { font-family: 'Inria Sans', sans-serif; } /* Added */
    .nav-tabs .nav-link { color: var(--dark-gray); font-weight: 600; border: none; border-bottom: 2px solid transparent; }
    .nav-tabs .nav-link.active { color: var(--primary-color); border-bottom: 2px solid var(--primary-color); background-color: transparent; }
    .tab-content { padding-top: 20px; }
    .form-label { font-weight: 600; color: #555; }
    .btn-primary { background-color: var(--primary-color); border: none; }
    .btn-primary:hover { background-color: #661966; }
    @media(max-width: 991.98px) {
      .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; z-index: 1050; }
      .sidebar.offcanvas.show { transform: translateX(0); }
      .main-content { margin-left: 0; }
      .mobile-header { display: flex; justify-content: space-between; align-items: center; }
    }

    /* --- ADDED: Styles for Password Section --- */
    .password-wrapper { position: relative; }
    .toggle-password { 
        position: absolute; 
        top: 70%; /* <-- FIXED: Was 65%, changed to 70% to align with label */ 
        right: 15px; 
        transform: translateY(-50%); 
        cursor: pointer; 
        color: #888; 
        user-select: none; 
        z-index: 3; 
    }
    #password-rules { list-style: none; padding: 0; margin: 15px 0 0 5px; font-size: 0.9em; display: none; }
    #password-rules li { color: #888; margin-bottom: 5px; transition: all 0.2s; padding-left: 8px; }
    #password-rules li.valid { color: #28a745; }
    #password-strength-meter { display: none; margin: 10px 0 0 5px; font-size: 0.9em; }
    .strength-bar-container { width: 100%; height: 8px; background-color: #eee; border-radius: 4px; overflow: hidden; margin-top: 5px; }
    #strength-bar { height: 100%; width: 0%; transition: all 0.3s ease; }
    #strength-bar.strength-low { width: 33%; background-color: #dc3545; }
    #strength-bar.strength-medium { width: 66%; background-color: #fd7e14; }
    #strength-bar.strength-strong { width: 100%; background-color: #28a745; }
    #strength-text.text-low { color: #dc3545; }
    #strength-text.text-medium { color: #fd7e14; }
    #strength-text.text-strong { color: #28a745; }
    /* --- End of Added Styles --- */

  </style>
</head>
<body>

  <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
    <div class="logo">
      <img src="assets/images/bgfunlogo.png" alt="BigFun Logo">
    </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="admin-dashboard.php" class="nav-link"><span class="material-symbols-outlined">dashboard</span>Dashboard Overview</a></li>
            <li class="nav-item"><a href="admin-appointments.php" class="nav-link"><span class="material-symbols-outlined">event_note</span>Booked Appointments</a></li>
            <li class="nav-item"><a href="admin-booking-approval.php" class="nav-link"><span class="material-symbols-outlined">checklist</span>Booking Approval</a></li>
            <li class="nav-item"><a href="admin-services.php" class="nav-link"><span class="material-symbols-outlined">design_services</span>Services Administration</a></li>
            <li class="nav-item"><a href="admin-customers.php" class="nav-link"><span class="material-symbols-outlined">group</span>Customers Management</a></li>
            <li class="nav-item"><a href="admin-manage.php" class="nav-link active"><span class="material-symbols-outlined">person</span>Profile</a></li>
        </ul>
    <div class="logout-link">
      <a href="backend/logout-admin.php" class="btn btn-light w-100">Log Out</a>
    </div>
  </div>

  <header class="mobile-header">
     <img src="assets/images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
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
                <div class="col-12"><label class="form-label">Email Address</label><input type="email" class="form-control" readonly value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>"></div>
                <div class="col-12 mt-4"><button type="submit" class="btn btn-primary">Save Profile</button></div>
              </div>
            </form>
          </div>

          <div class="tab-pane fade" id="password-pane" role="tabpanel">
            <form action="backend/admin-change-password.php" method="POST" id="changePasswordForm" data-attempts="<?php echo $password_attempts; ?>">
              <div class="row g-3">

                <div class="col-12 password-wrapper">
                  <label class="form-label">Current Password</label>
                  <input type="password" class="form-control" name="current_password" required>
                  <span class="toggle-password">👁</span>
                </div>

                <div class="col-12 password-wrapper">
                  <label class="form-label">New Password</label>
                  <input type="password" class="form-control" name="new_password" required aria-describedby="password-strength-meter password-rules">
                  <span class="toggle-password">👁</span>
                </div>

                <div class="col-12">
                  <div id="password-strength-meter">
                      <span id="strength-text"></span>
                      <div class="strength-bar-container">
                          <div id="strength-bar" class="strength-none"></div>
                      </div>
                  </div>
                  <ul id="password-rules">
                    <li id="rule-length">At least 8 characters</li>
                    <li id="rule-upper">At least one uppercase letter (A-Z)</li>
                    <li id="rule-number">At least one number (0-9)</li>
                    <li id="rule-special">At least one special character (!@#$...)</li>
                  </ul>
                </div>

                <div class="col-12 password-wrapper">
                  <label class="form-label">Confirm New Password</label>
                  <input type="password" class="form-control" name="confirm_password" required>
                  <span class="toggle-password">👁</span>
                </div>

                <div class="col-12 mt-4 d-flex align-items-center" style="gap: 10px;">
                  <button type="submit" class="btn btn-primary" disabled>Update Password</button>
                  <a href="forgot-password-admin.php" class="btn btn-outline-secondary" id="forgotPasswordBtn" style="display: none;">Forgot Password?</a>
                </div>

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
        <div class="modal-header">
            <h5 class="modal-title"><?php echo ($type === "success" || $type === "info") ? "Information" : "Error"; ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body"><?php echo htmlspecialchars($msg ?? ""); ?></div>
        <div class="modal-footer"><button class="btn btn-primary" data-bs-dismiss="modal">OK</button></div>
      </div>
    </div>
  </div>

  <script>
    // Show flash message modal if message exists
    <?php if ($msg): ?>
      const msgModalElement = document.getElementById('msgModal');
      if (msgModalElement) {
          const msgModal = new bootstrap.Modal(msgModalElement);
          msgModal.show();
      }
    <?php endif; ?>

    // --- ADDED: Password Change Features Script ---
    document.addEventListener('DOMContentLoaded', function() {

      // --- Password Toggle Script (Handles all 3 fields) ---
      document.querySelectorAll('#password-pane .toggle-password').forEach(toggle => {
          toggle.addEventListener('click', function() {
              const passwordField = this.previousElementSibling;
              if (passwordField && (passwordField.type === 'password' || passwordField.type === 'text')) {
                  if (passwordField.type === 'password') {
                      passwordField.type = 'text';
                      this.textContent = '🙈';
                  } else {
                      passwordField.type = 'password';
                      this.textContent = '👁';
                  }
              }
          });
      });

      // --- Password Validation Code ---
      const changePasswordForm = document.getElementById('changePasswordForm');
      // Ensure selectors are specific to the password pane if elements exist elsewhere
      const newPasswordInput = document.querySelector('#password-pane input[name="new_password"]');
      const confirmPasswordInput = document.querySelector('#password-pane input[name="confirm_password"]');
      const currentPasswordInput = document.querySelector('#password-pane input[name="current_password"]');
      const submitButton = document.querySelector('#password-pane button[type="submit"]');
      const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');

      // Elements MUST exist for script to run without errors
      if (!changePasswordForm || !newPasswordInput || !confirmPasswordInput || !currentPasswordInput || !submitButton || !forgotPasswordBtn) {
          console.error("One or more password change form elements not found!");
          return; // Stop script if elements are missing
      }

      // Checklist elements
      const rulesList = document.getElementById('password-rules');
      const ruleLength = document.getElementById('rule-length');
      const ruleUpper = document.getElementById('rule-upper');
      const ruleNumber = document.getElementById('rule-number');
      const ruleSpecial = document.getElementById('rule-special');

      // Meter elements
      const strengthMeter = document.getElementById('password-strength-meter');
      const strengthBar = document.getElementById('strength-bar');
      const strengthText = document.getElementById('strength-text');

      // Check if meter/rule elements exist
      if (!rulesList || !ruleLength || !ruleUpper || !ruleNumber || !ruleSpecial || !strengthMeter || !strengthBar || !strengthText) {
           console.error("One or more password strength/rule elements not found!");
           // Continue cautiously, meter/rules won't work
      }


      // Regex
      const specialCharRegex = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/;
      const upperCaseRegex = /[A-Z]/;
      const numberRegex = /[0-9]/;

      // Show/Hide listeners (only for the *new* password field)
      if (newPasswordInput && rulesList && strengthMeter) {
          newPasswordInput.addEventListener('focus', () => {
            rulesList.style.display = 'block';
            strengthMeter.style.display = 'block';
          });
          newPasswordInput.addEventListener('blur', () => {
            rulesList.style.display = 'none';
            strengthMeter.style.display = 'none';
          });
      }

      // --- Attempt checking logic ---
      const maxAttempts = 3;
      const initialAttempts = parseInt(changePasswordForm.dataset.attempts, 10) || 0;

      // 1. Show "Forgot Password" button if attempts are 2 or more
      if (initialAttempts >= 2) {
          forgotPasswordBtn.style.display = 'inline-block';
      }

      // 2. Lock the form if attempts are 3 or more
      if (initialAttempts >= maxAttempts) {
          currentPasswordInput.disabled = true;
          newPasswordInput.disabled = true;
          confirmPasswordInput.disabled = true;
          submitButton.disabled = true;
          // Optionally hide strength meter/rules if form is locked
          if(rulesList) rulesList.style.display = 'none';
          if(strengthMeter) strengthMeter.style.display = 'none';
      }
      // --- End of attempt logic ---


      // Validation function (handles meter and checklist)
      function validatePassword(password) {
          // Check if elements exist before using them
          if (!ruleLength || !ruleUpper || !ruleNumber || !ruleSpecial || !strengthBar || !strengthText) {
              // Basic length check if full UI isn't available
              return password.length >= 8;
          }

          let score = 0;
          let isLengthValid = password.length >= 8;
          let isUpperValid = upperCaseRegex.test(password);
          let isNumberValid = numberRegex.test(password);
          let isSpecialValid = specialCharRegex.test(password);

          isLengthValid ? ruleLength.classList.add('valid') : ruleLength.classList.remove('valid');
          isUpperValid ? ruleUpper.classList.add('valid') : ruleUpper.classList.remove('valid');
          isNumberValid ? ruleNumber.classList.add('valid') : ruleNumber.classList.remove('valid');
          isSpecialValid ? ruleSpecial.classList.add('valid') : ruleSpecial.classList.remove('valid');

          if (isLengthValid) score++;
          if (isUpperValid) score++;
          if (isNumberValid) score++;
          if (isSpecialValid) score++;

          strengthBar.className = 'strength-none'; // Reset bar class
          strengthText.className = '';         // Reset text class

          switch (score) {
              case 0: case 1:
                  strengthText.textContent = 'Low';
                  strengthText.classList.add('text-low');
                  strengthBar.classList.add('strength-low');
                  break;
              case 2: case 3:
                  strengthText.textContent = 'Medium';
                  strengthText.classList.add('text-medium');
                  strengthBar.classList.add('strength-medium');
                  break;
              case 4:
                  strengthText.textContent = 'Strong';
                  strengthText.classList.add('text-strong');
                  strengthBar.classList.add('strength-strong');
                  break;
              default:
                  strengthText.textContent = '';
                  // Default case already sets bar to 'strength-none' via reset
          }

          if (password.length === 0) {
              strengthText.textContent = '';
              strengthBar.className = 'strength-none';
              // Reset rule classes
              ruleLength.classList.remove('valid');
              ruleUpper.classList.remove('valid');
              ruleNumber.classList.remove('valid');
              ruleSpecial.classList.remove('valid');

          }

          return isLengthValid && isUpperValid && isNumberValid && isSpecialValid;
      }

      // Combined Validation Check
      function checkAllValidations() {
          const currentAttempts = parseInt(changePasswordForm.dataset.attempts, 10) || 0;
          if (currentAttempts >= maxAttempts) {
              submitButton.disabled = true;
              return; // Stop validation if form is locked
          }

          const passwordIsValid = validatePassword(newPasswordInput.value);
          const passwordsMatch = newPasswordInput.value.trim() !== '' &&
                                 newPasswordInput.value.trim() === confirmPasswordInput.value.trim();
          const currentPasswordFilled = currentPasswordInput.value.trim() !== '';

          submitButton.disabled = !(passwordIsValid && passwordsMatch && currentPasswordFilled);
      }

      // Add Listeners to ALL THREE fields
      currentPasswordInput.addEventListener('input', checkAllValidations);
      newPasswordInput.addEventListener('input', checkAllValidations);
      confirmPasswordInput.addEventListener('input', checkAllValidations);

      // Initial check in case form is pre-filled or locked on load
      checkAllValidations();

    });
    // --- END OF ADDED SCRIPT ---
  </script>
</body>
</html>




