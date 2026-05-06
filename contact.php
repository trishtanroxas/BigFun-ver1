<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun - Contact</title>
    <link rel="icon" type="image/png" href="images/bfun.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <!-- Links -->
     <link rel="stylesheet" href="style/contact.css">
    </head>
<body>
    <!-- NAVIGATION -->
<div class="site-nav">
        <div class="logo">
            <a href="index.php">
            <img src="images/bgfunlogo.png" alt="BigFun logo">
            </a>
        </div>

  <!-- Desktop Navigation -->
    <nav class="nav-links d-none d-md-flex">
            <a class="me-5 mt-1 text-dark text-decoration-none" href="index.php">Home</a>
            <a class="me-5 mt-1 text-dark text-decoration-none" href="contact.php">Contact</a>
            <a class="me-5 mt-1 text-dark text-decoration-none" href="about.php">About Us</a>
            <a class="me-5 mt-1 text-dark text-decoration-none" href="services.php">Help</a>
            <a class="btn rounded-book" href="#">Book Now</a>
    </nav>

    <!-- Mobile Menu Icon -->
    <span class="material-symbols-outlined menu-btn d-md-none">menu</span>
</div>

<!-- Mobile Modal Nav -->
<div class="mobile-nav" id="mobileNav">
    <span class="material-symbols-outlined close-btn">close</span>
    <a href="index.php">Home</a>
    <a href="contact.php">Contact</a>
    <a href="about.php">About Us</a>
    <a href="services.php">Help</a>
    <a class="btn text-white rounded-book mt-3" href="#">Book Now</a>
</div>

<!-- Main Content -->
<section class="contact-info container py-5">
  <div class="row">
    <div class="col-md-10 mx-auto">
      <h2>Contact Us</h2>
      
      <p class="contact-description">
        We’d love to hear from you! Whether you’re planning an event, need more information,
        or have any questions, our team is here to help.
      </p>

      <div class="contact-details">
        <p><strong>Phone:</strong> 1800 244 386</p>
        <p><strong>Email:</strong> hire.enquiries@bigfunqld.com.au</p>
      </div>

      <div class="business-hours mt-4">
        <p><strong>Business Hours:</strong></p>
        <p>Monday – Friday: 8:00 AM – 8:00 PM</p>
        <p>Saturday: 9:00 AM – 5:00 PM</p>
        <p>Sunday: 9:00 AM – 1:00 PM</p>
      </div>
    </div>
  </div>
</section>


<!-- Footer -->
<footer class="site-footer">
  <div class="footer-content container text-center">
    <p>© 2025 BigFun. All rights reserved.</p>

    <p>
      <i class="bi bi-telephone"></i> 1800 244 386 |
      <i class="bi bi-envelope"></i> hire.enquiries@bigfunqld.com.au
    </p>

    <div class="terms">
      <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> | 
      <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
    </div>

    <div class="footer-logo">
      <img src="images/bgfunlogo.png" alt="BigFun Logo">
    </div>
  </div>
</footer>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Terms & Conditions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Bookings are subject to availability. Cancellations may incur fees. Please review our full terms before confirming.</p>
      </div>
    </div>
  </div>
</div>

<!-- Privacy Policy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Privacy Policy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body privacy-policy">
        <h6>Who we are</h6>
        <p>Our website address is: https://bigfun.au.</p>

        <h6>Comments</h6>
        <p>When visitors leave comments on the site we collect the data shown in the comments form, and also the visitor’s IP address and browser user agent string to help spam detection.</p>
        <p>An anonymized string created from your email address (also called a hash) may be provided to the Gravatar service to see if you are using it. The Gravatar service privacy policy is available here: <a href="https://automattic.com/privacy/" target="_blank">https://automattic.com/privacy/</a>. After approval of your comment, your profile picture is visible to the public in the context of your comment.</p>

        <h6>Media</h6>
        <p>If you upload images to the website, you should avoid uploading images with embedded location data (EXIF GPS) included. Visitors can download and extract any location data from images on the website.</p>

        <h6>Cookies</h6>
        <p>If you leave a comment, you may opt-in to saving your name, email, and website in cookies. These cookies last for one year. If you visit our login page, we will set a temporary cookie to check if your browser accepts cookies. It is discarded when you close your browser.</p>
        <p>When you log in, we set cookies to save your login info and screen display choices. Login cookies last 2 days, screen options cookies last 1 year. If "Remember Me" is selected, login persists 2 weeks. If you log out, cookies are removed.</p>
        <p>If you edit or publish an article, a cookie will be saved indicating the post ID of the article, expiring after 1 day.</p>

        <h6>Embedded content from other websites</h6>
        <p>Articles may include embedded content (videos, images, etc.) that behave exactly as if you visited the source website. These sites may collect data, use cookies, and track your interaction.</p>

        <h6>Who we share your data with</h6>
        <p>If you request a password reset, your IP will be included in the reset email.</p>

        <h6>How long we retain your data</h6>
        <p>Comments and metadata are retained indefinitely. Registered users’ data is stored in their profile and editable by them or administrators.</p>

        <h6>Your rights over your data</h6>
        <p>You can request an export of your personal data we hold, or request deletion. This excludes data we must keep for legal, admin, or security reasons.</p>

        <h6>Where your data is sent</h6>
        <p>Visitor comments may be checked through an automated spam detection service.</p>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript -->
<script>
  /* ---------------- Header ---------------- */
    const menuBtn = document.querySelector('.menu-btn');
    const closeBtn = document.querySelector('.close-btn');
    const mobileNav = document.getElementById('mobileNav');

    menuBtn.addEventListener('click', () => {
        mobileNav.style.right = "0"; // slide in
    });

    closeBtn.addEventListener('click', () => {
        mobileNav.style.right = "-100%"; // slide out
    });
</script>

</body>
</html>