<?php
    // Get the token from the URL
    $token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Reset Password</title>
    <link rel="icon" type="image/png" href="assets/images/bfun.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <link rel="stylesheet" href="assets/style/login.css">

    <style>
     /* --- Style for Eye Toggle --- */
     .password-wrapper {
       position: relative;
     }
     .toggle-password {
       position: absolute;
       top: 70%;
       right: 15px;
       transform: translateY(-50%);
       cursor: pointer;
       color: #888;
       user-select: none;
     }
     
     /* --- NEW: CSS FOR CHECKLIST --- */
     #password-rules {
       list-style: none;
       padding: 0;
       margin: 15px 0 0 5px;
       font-size: 0.9em;
       display: none; /* Hidden by default */
     }
     #password-rules li {
       color: #888;
       margin-bottom: 5px;
       transition: all 0.2s;
       padding-left: 8px; 
     }
     #password-rules li.valid {
       color: #28a745;
     }
     
     /* --- NEW: CSS FOR STRENGTH METER --- */
     #password-strength-meter {
        display: none; /* Hidden by default */
        margin: 10px 0 0 5px;
        font-size: 0.9em;
     }
     .strength-bar-container {
        width: 100%;
        height: 8px;
        background-color: #eee;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 5px;
     }
     #strength-bar {
        height: 100%;
        width: 0%;
        transition: all 0.3s ease;
     }
     #strength-text {
        font-weight: 500;
     }
     #strength-bar.strength-low {
        width: 33%;
        background-color: #dc3545; /* Red */
     }
     #strength-bar.strength-medium {
        width: 66%;
        background-color: #fd7e14; /* Orange */
     }
     #strength-bar.strength-strong {
        width: 100%;
        background-color: #28a745; /* Green */
     }
     #strength-text.text-low { color: #dc3545; }
     #strength-text.text-medium { color: #fd7e14; }
     #strength-text.text-strong { color: #28a745; }
     /* --------------------------------------- */

    </style>
</head>

<body>
 <div class="login-container">
    <h2>Set New Password</h2>
    <p>Please enter your new password below.</p>

    <form id="resetForm">
      
      <input type="hidden" name="token" value="<?php echo $token; ?>">

      <div class="form-group password-wrapper">
        <label for="password">New Password</label>
        <input type="password" id="password" name="password" required aria-describedby="password-strength-meter password-rules">
        <span class="toggle-password">👁</span>
      </div>

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
      <div class="form-group password-wrapper">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <span class="toggle-password">👁</span>
      </div>

      <button type="submit" class="login-btn" disabled>Update Password</button>
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
    // --- Toggle Password Script (Handles both fields) ---
    document.querySelectorAll('.toggle-password').forEach(toggle => {
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

    // --- NEW: Password Validation Code ---
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const resetForm = document.getElementById('resetForm');
    const submitButton = resetForm.querySelector('button[type="submit"]');

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

    // Regex
    const specialCharRegex = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/;
    const upperCaseRegex = /[A-Z]/;
    const numberRegex = /[0-9]/;

    // Show/Hide listeners (only for the *first* password field)
    if (passwordInput && rulesList && strengthMeter) {
        passwordInput.addEventListener('focus', () => {
          rulesList.style.display = 'block';
          strengthMeter.style.display = 'block';
        });
        passwordInput.addEventListener('blur', () => {
          rulesList.style.display = 'none';
          strengthMeter.style.display = 'none';
        });
    }

    // Validation function (handles meter and checklist)
    function validatePassword(password) {
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

        strengthBar.className = '';
        strengthText.className = '';
        
        switch (score) {
            case 0:
            case 1:
                strengthText.textContent = 'Low';
                strengthText.classList.add('text-low');
                strengthBar.classList.add('strength-low');
                break;
            case 2:
            case 3:
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
                strengthBar.classList.add('strength-none');
        }

        if (password.length === 0) {
            strengthText.textContent = '';
            strengthBar.className = 'strength-none';
        }
        
        return isLengthValid && isUpperValid && isNumberValid && isSpecialValid;
    }

    // --- REWRITTEN: Combined Validation Check ---
    function checkAllValidations() {
        // Rule 1: Check if the new password meets all complexity rules
        const passwordIsValid = validatePassword(passwordInput.value);
        
        // Rule 2: Check if the passwords match (and are not empty)
        const passwordsMatch = passwordInput.value.trim() !== '' && 
                               passwordInput.value.trim() === confirmPasswordInput.value.trim();
        
        // Enable button ONLY if both rules are true
        submitButton.disabled = !(passwordIsValid && passwordsMatch);
    }

    // --- NEW: Add Listeners to BOTH password fields ---
    if (passwordInput && confirmPasswordInput) {
        passwordInput.addEventListener('input', checkAllValidations);
        confirmPasswordInput.addEventListener('input', checkAllValidations);
    }
    // --- End of New Password Code ---


    // --- Modal helpers (existing code) ---
    const modal = document.getElementById('feedbackModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');

    function showModal(title, message, redirectUrl = null) {
      modal.style.display = "flex";
      modalTitle.textContent = title;
      modalMessage.textContent = message;
      
      const closeBtn = modal.querySelector('.close-btn');
      const newCloseBtn = closeBtn.cloneNode(true);
      closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
      
      if (redirectUrl) {
          newCloseBtn.style.display = 'none';
          setTimeout(() => {
              window.location.href = redirectUrl;
          }, 2000);
      } else {
          newCloseBtn.style.display = 'block';
          newCloseBtn.addEventListener('click', closeModal);
      }
      
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
    
    modal.querySelector('.close-btn').addEventListener('click', closeModal);
    // --- End of Modal helpers ---


    // --- MODIFIED: Handle form submission ---
    document.getElementById('resetForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      // --- NEW: Safety check ---
      if (submitButton.disabled) {
          showModal("Error", "Please ensure your password meets all rules and that passwords match.");
          return;
      }
      
      const formData = new FormData(this);
      const token = formData.get('token');

      if (!token) {
          showModal("Error", "Invalid or missing reset token.");
          return;
      }
      
      // This check is now redundant because of the button disabling,
      // but it's good to keep as a final check.
      if (formData.get('password') !== formData.get('confirm_password')) {
          showModal("Error", "The passwords do not match.");
          return;
      }
      
      const button = e.target.querySelector('button');
      button.disabled = true;
      button.textContent = "Updating...";

      const response = await fetch('backend/update-password.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.status === 'success') {
        showModal("Success!", result.message, 'login.php');
      } else {
        showModal("Error", result.message);
        // Note: We don't re-enable the button here. 
        // We force the user to re-type to trigger the validation.
        // Or, you can re-enable it if you prefer:
        // button.disabled = false;
        // button.textContent = "Update Password";
        // checkAllValidations(); // Re-run validation
      }
    });
 </script>
</body>
</html>




