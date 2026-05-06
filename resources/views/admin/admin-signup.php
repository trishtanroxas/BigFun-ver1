<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Admin Sign Up</title>
    <link rel="icon" type="image/png" href="assets/images/bfun.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="assets/style/signup.css">

    <style>
     .user-signup-link {
       text-align: center;
       margin-top: 25px;
       padding-top: 20px;
       border-top: 1px solid #eee;
     }
     .user-btn {
       display: inline-block;
       padding: 10px 24px;
       font-size: 1rem;
       font-weight: 500;
       color: #fff;
       background-color: #0d6efd; /* A primary blue */
       border: none;
       border-radius: 5px;
       text-decoration: none;
       transition: background-color 0.2s ease-in-out;
     }
     .user-btn:hover {
       background-color: #0b5ed7;
       color: #fff;
     }
     .spinner-border {
       display: none; /* Hidden by default */
       width: 1rem;
       height: 1rem;
       margin-left: 8px;
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

<div class="signup-container">
 <h2>Admin Sign Up</h2>
 <p>Already an admin? <a href="index.php?route=login-form">Log in</a></p>

 <form id="signupForm" action="backend/signup-admin.php" method="post" novalidate>
    <div class="form-group">
      <label for="email1">Admin Email Address</label>
      <input type="email" id="email1" name="email1" required>
    </div>

    <div class="form-group">
      <label for="email2">Confirm Admin Email</label>
      <input type="email" id="email2" name="email2" required>
    </div>

    <div class="form-group password-wrapper">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required aria-describedby="password-strength-meter password-rules">
      <span class="toggle-password" role="button" aria-label="Show password">👁</span>
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
    <button type="submit" class="signup-btn" disabled>
      Sign up as Admin
      <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
    </button>
 </form>

</div>

<div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="responseModalLabel">Sign Up Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalBodyContent">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
 </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
 const signupForm = document.getElementById('signupForm');
 const responseModal = new bootstrap.Modal(document.getElementById('responseModal'));
 const modalTitle = document.getElementById('responseModalLabel');
 const modalBody = document.getElementById('modalBodyContent');
 const submitButton = signupForm.querySelector('button[type="submit"]');
 const spinner = submitButton.querySelector('.spinner-border');

 // --- NEW: Password Validation Elements ---
 const email1Input = document.getElementById('email1');
 const email2Input = document.getElementById('email2');
 const passwordInput = document.getElementById('password');

 const rulesList = document.getElementById('password-rules');
 const ruleLength = document.getElementById('rule-length');
 const ruleUpper = document.getElementById('rule-upper');
 const ruleNumber = document.getElementById('rule-number');
 const ruleSpecial = document.getElementById('rule-special');
 
 const strengthMeter = document.getElementById('password-strength-meter');
 const strengthBar = document.getElementById('strength-bar');
 const strengthText = document.getElementById('strength-text');

 const specialCharRegex = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/;
 const upperCaseRegex = /[A-Z]/;
 const numberRegex = /[0-9]/;
 // --- End of New Elements ---

 // --- NEW: Show/Hide Rules on Focus/Blur ---
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

 // --- NEW: Main Validation Function ---
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

 // --- NEW: Combined Validation Check ---
 function checkAllValidations() {
    const emailsMatch = email1Input.value.trim() !== '' && email1Input.value.trim() === email2Input.value.trim();
    const passwordIsValid = validatePassword(passwordInput.value);
    
    // Enable button ONLY if both are true
    submitButton.disabled = !(emailsMatch && passwordIsValid);
 }

 // --- NEW: Add Listeners to all fields ---
 if (passwordInput && email1Input && email2Input) {
    passwordInput.addEventListener('input', checkAllValidations);
    email1Input.addEventListener('input', checkAllValidations);
    email2Input.addEventListener('input', checkAllValidations);
 }

 // --- MODIFIED: Form Submission Logic ---
 signupForm.addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default page reload

    // NEW: Final check
    if (submitButton.disabled) {
        modalTitle.textContent = 'Validation Error';
        modalBody.textContent = 'Please ensure emails match and password meets all requirements.';
        responseModal.show();
        return;
    }

    submitButton.disabled = true;
    spinner.style.display = 'inline-block';

    const formData = new FormData(signupForm);

    fetch('backend/signup-admin.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 'success') {
        modalTitle.textContent = '🎉 Almost there!';
        modalBody.innerHTML = `<p>${data.message}</p><p>You can close this window now.</p>`;
        signupForm.reset(); // Clear form on success
        
        // NEW: Clear validation and re-disable button
        validatePassword('');
        submitButton.disabled = true;

      } else {
        modalTitle.textContent = 'An Error Occurred';
        modalBody.textContent = data.message;
      }
      responseModal.show();
    })
    .catch(error => {
      console.error('Fetch Error:', error);
      modalTitle.textContent = 'System Error';
      modalBody.textContent = 'Could not connect to the server. Please try again.';
      responseModal.show();
    })
    .finally(() => {
      // Hide loading state
      // Button remains disabled (due to success or error) until user types again
      if (modalTitle.textContent === 'An Error Occurred') {
         submitButton.disabled = false; // Re-enable on error
      }
      spinner.style.display = 'none';
    });
 });

 // --- Password toggle logic (no change) ---
 const toggle = document.querySelector('.toggle-password');
 if (toggle && passwordInput) {
    toggle.addEventListener('click', function () {
      const isText = passwordInput.type === 'text';
      passwordInput.type = isText ? 'password' : 'text';
      this.textContent = isText ? '👁' : '🙈';
      passwordInput.focus();
    });
 }
});
</script>

</body>
</html>




