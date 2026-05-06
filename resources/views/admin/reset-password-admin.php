<?php
    $token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Set New Admin Password</title>
    <link rel="icon" type="image/png" href="assets/images/bfun.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style/login.css">
    <style>
     .password-wrapper { position: relative; }
     .toggle-password {
       position: absolute;
       top: 65%; /* Adjusted vertical alignment */
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
     .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; outline: 0; background-color: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
     .modal.show { opacity: 1; }
     .modal-content { position: relative; background-color: #fff; border-radius: 8px; padding: 25px 30px; width: 90%; max-width: 450px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
     .modal-content h2 { font-family: 'Inria Sans', sans-serif; font-size: 1.8rem; font-weight: 700; color: #333; margin-bottom: 10px; }
     .modal-content p { font-size: 1rem; color: #555; margin-bottom: 25px; }
     .close-btn { padding: 10px 24px; font-size: 1rem; font-weight: 600; color: #fff; background-color: #802080; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; }
     .close-btn:hover { background-color: #661966; }
    </style>
</head>
<body>
  <div class="login-container">
    <h2>Set New Admin Password</h2>
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
          <div class="strength-bar-container"><div id="strength-bar" class="strength-none"></div></div>
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
    document.querySelectorAll('.toggle-password').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const pf = this.previousElementSibling;
            if (pf && (pf.type === 'password' || pf.type === 'text')) {
                pf.type = pf.type === 'password' ? 'text' : 'password';
                this.textContent = pf.type === 'password' ? '👁' : '🙈';
            }
        });
    });

    const pI = document.getElementById('password');
    const cPI = document.getElementById('confirm_password');
    const sB = document.querySelector('#resetForm button[type="submit"]');
    const rL = document.getElementById('password-rules');
    const rLen = document.getElementById('rule-length');
    const rUp = document.getElementById('rule-upper');
    const rNum = document.getElementById('rule-number');
    const rSp = document.getElementById('rule-special');
    const sM = document.getElementById('password-strength-meter');
    const sBar = document.getElementById('strength-bar');
    const sTxt = document.getElementById('strength-text');
    const spEx = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/;
    const upEx = /[A-Z]/;
    const numEx = /[0-9]/;

    if (pI && rL && sM) {
        pI.addEventListener('focus', () => { rL.style.display = 'block'; sM.style.display = 'block'; });
        pI.addEventListener('blur', () => { rL.style.display = 'none'; sM.style.display = 'none'; });
    }

    function validatePassword(password) {
        let score = 0;
        let isL = password.length >= 8;
        let isU = upEx.test(password);
        let isN = numEx.test(password);
        let isS = spEx.test(password);
        isL ? rLen.classList.add('valid') : rLen.classList.remove('valid');
        isU ? rUp.classList.add('valid') : rUp.classList.remove('valid');
        isN ? rNum.classList.add('valid') : rNum.classList.remove('valid');
        isS ? rSp.classList.add('valid') : rSp.classList.remove('valid');
        if (isL) score++; if (isU) score++; if (isN) score++; if (isS) score++;
        sBar.className = ''; sTxt.className = '';
        switch (score) {
            case 0: case 1: sTxt.textContent = 'Low'; sTxt.classList.add('text-low'); sBar.classList.add('strength-low'); break;
            case 2: case 3: sTxt.textContent = 'Medium'; sTxt.classList.add('text-medium'); sBar.classList.add('strength-medium'); break;
            case 4: sTxt.textContent = 'Strong'; sTxt.classList.add('text-strong'); sBar.classList.add('strength-strong'); break;
            default: sTxt.textContent = ''; sBar.classList.add('strength-none');
        }
        if (password.length === 0) { sTxt.textContent = ''; sBar.className = 'strength-none'; }
        return isL && isU && isN && isS;
    }

    function checkAllValidations() {
        const pV = validatePassword(pI.value);
        const pM = pI.value.trim() !== '' && pI.value.trim() === cPI.value.trim();
        sB.disabled = !(pV && pM);
    }

    if (pI && cPI) {
        pI.addEventListener('input', checkAllValidations);
        cPI.addEventListener('input', checkAllValidations);
    }

    const modal = document.getElementById('feedbackModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    function showModal(title, message, redirectUrl = null) {
      modal.style.display = "flex";
      modalTitle.textContent = title;
      modalMessage.textContent = message;
      const cB = modal.querySelector('.close-btn');
      const nCB = cB.cloneNode(true);
      cB.parentNode.replaceChild(nCB, cB);
      if (redirectUrl) {
          nCB.style.display = 'none';
          // --- Use a standard delay ---
          setTimeout(() => { window.location.href = redirectUrl; }, 2000);
      } else {
          nCB.style.display = 'block';
          nCB.addEventListener('click', closeModal);
      }
      setTimeout(() => { modal.classList.add("show"); }, 10);
    }
    function closeModal() {
      modal.classList.remove("show");
      setTimeout(() => { modal.style.display = "none"; }, 300);
    }
    modal.querySelector('.close-btn').addEventListener('click', closeModal);

    document.getElementById('resetForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      if (sB.disabled) {
          showModal("Error", "Please ensure your password meets all rules and that passwords match.");
          return;
      }
      const formData = new FormData(this);
      const token = formData.get('token');
      if (!token) {
          showModal("Error", "Invalid or missing reset token.");
          return;
      }

      const button = e.target.querySelector('button');
      button.disabled = true;
      button.textContent = "Updating...";

      // --- MODIFIED FETCH with Debugging ---
      try {
          const response = await fetch('backend/update-password-admin.php', {
              method: 'POST',
              body: formData
          });

          const responseText = await response.text(); // Get raw text
          console.log("Raw Response from backend:", responseText); // Log raw text

          // Try to parse the text as JSON
          const result = JSON.parse(responseText);
          console.log("Parsed Result:", result);   // Log parsed object

          if (result.status === 'success') {
              showModal("Success!", result.message, 'admin-login.php');
          } else {
              // Show error modal based on JSON response
              showModal("Error", result.message || "An unknown error occurred."); // Use message from JSON or a default
              button.disabled = false;
              button.textContent = "Update Password";
          }
      } catch (error) {
          // Catch errors during fetch OR JSON.parse
          console.error('Fetch or JSON Parse Error:', error);
          // Show a generic error because the response wasn't valid JSON
          showModal("Error", "Failed to process the server's response. Please check the console for details.");
          button.disabled = false;
          button.textContent = "Update Password";
      }
      // --- END OF MODIFIED FETCH ---
    });
  </script>
</body>
</html>




