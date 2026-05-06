<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BigFun</title>
    <link rel="icon" type="image/png" href="assets/images/bfun.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <!-- Links -->
     <link rel="stylesheet" href="assets/style/index.css">
    </head>
<body>

<!-- NAVIGATION -->
<div class="site-nav">
        <div class="logo">
            <a href="index.php">
            <img src="assets/images/bgfunlogo.png" alt="BigFun logo">
            </a>
        </div>

  <!-- Desktop Navigation -->
    <nav class="nav-links d-none d-md-flex">
            <a class="me-5 mt-1 text-dark text-decoration-none" href="index.php">Home</a>
            <a class="me-5 mt-1 text-dark text-decoration-none" href="index.php?route=contact">Contact</a>
            <a class="me-5 mt-1 text-dark text-decoration-none" href="index.php?route=about">About Us</a>
            <a class="me-5 mt-1 text-dark text-decoration-none" href="index.php?route=services">Help</a>
            <a class="btn rounded-book" href="index.php?route=login-form">Book Now</a>
    </nav>

    <!-- Mobile Menu Icon -->
    <span class="material-symbols-outlined menu-btn d-md-none">menu</span>
</div>

<!-- Mobile Modal Nav -->
<div class="mobile-nav" id="mobileNav">
    <span class="material-symbols-outlined close-btn">close</span>
    <a href="index.php">Home</a>
    <a href="index.php?route=contact">Contact</a>
    <a href="index.php?route=about">About Us</a>
    <a href="index.php?route=services">Help</a>
    <a class="btn text-white rounded-book mt-3" href="index.php?route=login-form">Book Now</a>
</div>

<!-- HERO -->
<section class="hero">
    <div class="copy" >
        <h1>Because <em>FUN</em><br>Is Always<br>Better When It's <span class="big">BIG</span></h1>
        <p class="mt-3 lead">Sign up today and unlock your BigFUN experience — book your spot now!</p>
        <div>
        <a class="mt-3 me-2 btn rounded-login" href="index.php?route=login-form">Login</a>
        <a class="mt-3 btn rounded-signup" href="index.php?route=signup">Sign up</a>
        </div>
    </div>

    <!-- Contact info moved outside .copy -->
    <div class="contact-info">
        <small>Call Us Today!</small>
        <small>1800 244 386 | Mon. - Fri. | 8 am - 8 pm</small>
    </div>
</section>

<!-- Queensland -->
<section class="servicing">
  <div class="servicing-content">
    <div class="servicing-left">
      <h2>Servicing Queensland</h2>
      <p><strong>Western Downs, Darling Downs, Toowoomba, Brisbane, Gold Coast & Sunshine Coast.</strong></p>
    </div>
    <div class="servicing-right">
      <p>
        Queensland’s Largest Party Hire Company. It’s not just in the name.
        Bigfun offers an incredibly diverse collection of inflatable games and
        Jumping Castles to suit any party!
      </p>
    </div>
  </div>
</section>

<!-- Services Carousel -->
<section class="services-carousel">
  <!-- Header -->
  <div class="section-header">
      <h2>Hire Services</h2>
      <p>Choose from our wide range of entertainment and safety services to make your event unforgettable and stress-free.</p>
  </div>

  <!-- Carousel -->
  <div class="carousel-container">
      <div class="carousel-track">
        <!-- Card 1 -->
        <div class="card">
          <h3>Premium Mechanical Bull QLD</h3>
          <!-- Image removed per user request -->
          <div class="info">
            <p class="price">AU$1,290.00</p>
            <p class="details">3 Hours | 1 Operator</p>
          </div>
          <div class="actions">
            <a href="#" class="btn-service">Service</a>
            <a class="btn-more">See More</a>
          </div>
        </div>
        <!-- Card 2 -->
        <div class="card">
          <h3>Adult Jumping Castle</h3>
          <img src="assets/images/jumpcastle.png" alt="Adult Jumping Castle">
          <div class="info">
            <p class="price">AU$620.00</p>
            <p class="details">3 Hours | 1 Operator</p>
          </div>
          <div class="actions">
            <a href="#" class="btn-service">Service</a>
            <a class="btn-more">See More</a>
          </div>
        </div>
        <!-- Card 3 -->
        <div class="card">
          <h3>Sumo Suits</h3>
          <img src="assets/images/sumosuit.jpeg" alt="Sumo Suits">
          <div class="info">
            <p class="price">$280.00</p>
            <p class="details">3 Hours | 1 Operator</p>
          </div>
          <div class="actions">
            <a href="#" class="btn-service">Service</a>
            <a class="btn-more">See More</a>
          </div>
        </div>
        <!-- Card 4 -->
        <div class="card">
          <h3>Gladiator</h3>
          <img src="assets/images/gladiator.jpeg" alt="Gladiator">
          <div class="info">
            <p class="price">$490.00</p>
            <p class="details">3 Hours | 1 Operator</p>
          </div>
          <div class="actions">
            <a href="#" class="btn-service">Service</a>
            <a class="btn-more">See More</a>
          </div>
        </div>
        <!-- Card 5 -->
        <div class="card">
          <h3>Giant Games</h3>
          <img src="assets/images/giantgames.jpg" alt="Giant Games">
          <div class="info">
            <p class="price">$340.00</p>
            <p class="details">3 Hours | 1 Operator</p>
          </div>
          <div class="actions">
            <a href="#" class="btn-service">Service</a>
            <a class="btn-more">See More</a>
          </div>
        </div>
        <!-- Card 6 -->
        <div class="card">
          <h3>Footy Pass</h3>
          <img src="assets/images/footypass.jpeg" alt="Footy Pass">
          <div class="info">
            <p class="price">$280.00</p>
            <p class="details">3 Hours | 1 Operator</p>
          </div>
          <div class="actions">
            <a href="#" class="btn-service">Service</a>
            <a class="btn-more">See More</a>
          </div>
        </div>
        <!-- Card 7 -->
        <div class="card">
          <h3>Twin Lane Basketball</h3>
          <img src="assets/images/twinlane.jpeg" alt="Twin Lane Basketball">
          <div class="info">
            <p class="price">$490.00</p>
            <p class="details">3 Hours | 1 Operator</p>
          </div>
          <div class="actions">
            <a href="#" class="btn-service">Service</a>
            <a class="btn-more">See More</a>
          </div>
        </div>
        <!-- Card 8 -->
        <div class="card">
          <h3>Jumping Castles (Kids)</h3>
          <img src="assets/images/spiderman.png" alt="Jumping Castles (Kids)">
          <div class="info">
            <p class="price">$420.00</p>
            <p class="details">3 Hours | 1 Operator</p>
          </div>
          <div class="actions">
            <a href="#" class="btn-service">Service</a>
            <a class="btn-more">See More</a>
          </div>
        </div>
      </div>

      <!-- Carousel Buttons -->
      <button class="carousel-btn prev">‹</button>
      <button class="carousel-btn next">›</button>
    </div>

    <div class="section-footer">
      <p>At BigFun, we provide top-quality attractions and safety services to make your event memorable, safe, and fun for everyone.</p>
    </div>
</section>

<!-- Modal -->
<div class="modal" id="serviceModal">
    <div class="modal-content">
      <span class="modal-close">&times;</span>
      <img id="modalImage" src="" alt="">
      <h3 id="modalTitle"></h3>
      <p class="modal-price" id="modalPrice"></p>
      <p class="modal-details" id="modalDetails"></p>
      <p class="modal-desc">
        This service is designed to bring fun and excitement to your event with professional operators and high-quality equipment.
      </p>
      <a href="index.php?route=login-form" class="btn-purchase" style="text-decoration: none;" role="button">Book Now</a>
    </div>
</div>

<!-- Partnership Section -->
<section class="partnership">
  <h2>Partnership</h2>
  <p>Grow with us — bring more joy to more families through <span class="highlight">BigFun</span>.</p>

<div class="logo-carousel">
  <div class="logo-track">
    <!-- One full set -->
    <img src="assets/elements/AFP_logo.png" alt="AFP">
    <img src="assets/elements/australian-government-stacked-black.png" alt="Australian Government">
    <img src="assets/elements/channel-9-logo-png_seeklogo-314516.png" alt="Channel 9">
    <img src="assets/elements/Brisbane_Broncos_logo.svg.png" alt="Broncos">
    <img src="assets/elements/Coles_logo.svg.png" alt="Coles">
    <img src="assets/elements/Bunnings-logo.png" alt="Bunnings">
    <img src="assets/elements/David_Jones_Limited_Logo.svg.png" alt="David Jones">
    <img src="assets/elements/AMP_Logo.svg.png" alt="AMP">
    <img src="assets/elements/Telstra-Corporation-Logo-2006-2011.png" alt="Telstra">
    <img src="assets/elements/Optus-Logo-2013-2048x1152.png" alt="Optus">
    <img src="assets/elements/Seven_Network_logo.svg.png" alt="Seven Network">
    <img src="assets/elements/Virgin-Australia-Logo.png" alt="Virgin">
    <img src="assets/elements/ANZ-Logo-2009.svg.png" alt="ANZ">
    <img src="assets/elements/Westpac-Banking-Corporation-Logo-2003-present.png" alt="Westpac">
    <img src="assets/elements/Myer_Logo.svg.png" alt="Myer">
    <img src="assets/elements/DC-Shoes-Logo.png" alt="DC Shoes">
    <img src="assets/elements/BrisbaneLions_A.png" alt="Brisbane Lions">
    <img src="assets/elements/Caltex_brand_logo.svg.png" alt="Caltex">
    <img src="assets/elements/anyfit.png" alt="Anyfit">

    <!-- Duplicate set immediately after -->
    <img src="assets/elements/AFP_logo.png" alt="AFP">
    <img src="assets/elements/australian-government-stacked-black.png" alt="Australian Government">
    <img src="assets/elements/channel-9-logo-png_seeklogo-314516.png" alt="Channel 9">
    <img src="assets/elements/Brisbane_Broncos_logo.svg.png" alt="Broncos">
    <img src="assets/elements/Coles_logo.svg.png" alt="Coles">
    <img src="assets/elements/Bunnings-logo.png" alt="Bunnings">
    <img src="assets/elements/David_Jones_Limited_Logo.svg.png" alt="David Jones">
    <img src="assets/elements/AMP_Logo.svg.png" alt="AMP">
    <img src="assets/elements/Telstra-Corporation-Logo-2006-2011.png" alt="Telstra">
    <img src="assets/elements/Optus-Logo-2013-2048x1152.png" alt="Optus">
    <img src="assets/elements/Seven_Network_logo.svg.png" alt="Seven Network">
    <img src="assets/elements/Virgin-Australia-Logo.png" alt="Virgin">
    <img src="assets/elements/ANZ-Logo-2009.svg.png" alt="ANZ">
    <img src="assets/elements/Westpac-Banking-Corporation-Logo-2003-present.png" alt="Westpac">
    <img src="assets/elements/Myer_Logo.svg.png" alt="Myer">
    <img src="assets/elements/DC-Shoes-Logo.png" alt="DC Shoes">
    <img src="assets/elements/BrisbaneLions_A.png" alt="Brisbane Lions">
    <img src="assets/elements/Caltex_brand_logo.svg.png" alt="Caltex">
    <img src="assets/elements/anyfit.png" alt="Anyfit">
  </div>
</div>


  <p class="tagline">Empowering Companies, Inspiring Confidence.</p>
</section>

<!-- Contact Section -->
<section class="contact-section py-5">
  <div class="container">
    <div class="row">
      <div class="col-md-8 mx-auto text-center">
        <h2 class="contact-title">Got questions?</h2>
        <p class="contact-text">
          Our team is ready to assist—just send us a message and we’ll provide all the information you need.
        </p>
      </div>
    </div>

    <form id="contactForm" method="POST" action="homephp/send_message.php" class="mt-4">
      <div class="mb-3">
        <input type="email" name="from_email" class="form-control" placeholder="From" required>
      </div>
      <div class="mb-3">
        <input type="email" name="to_email" class="form-control" value="alex1925tan@gmail.com" readonly>
      </div>
      <div class="mb-3">
        <input type="text" name="subject" class="form-control" placeholder="Subject" required>
      </div>
      <div class="mb-3">
        <textarea name="body" class="form-control" rows="6" placeholder="Body" required></textarea>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-lg btn-send">Send</button>
      </div>
    </form>
  </div>
</section>

<!-- Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Message Sent</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Your message has been sent successfully! A copy has been emailed to you.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

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
      <img src="assets/images/bgfunlogo.png" alt="BigFun Logo">
    </div>
  </div>
</footer>

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

    /* ---------------- Carousel ---------------- */
    const track = document.querySelector('.carousel-track');
    const cards = document.querySelectorAll('.card');
    const prevBtn = document.querySelector('.prev');
    const nextBtn = document.querySelector('.next');
    let currentIndex = 0;

    function updateSlide() {
      const cardWidth = cards[0].offsetWidth + 30; // include gap
      track.style.transform = `translateX(-${currentIndex * cardWidth}px)`;
    }

    nextBtn.onclick = () => {
      if (currentIndex < cards.length - 3) currentIndex++;
      updateSlide();
    };
    prevBtn.onclick = () => {
      if (currentIndex > 0) currentIndex--;
      updateSlide();
    };

    /* ---------------- Services Modal ---------------- */
    const modal = document.getElementById('serviceModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    const modalPrice = document.getElementById('modalPrice');
    const modalDetails = document.getElementById('modalDetails');
    const modalClose = document.querySelector('.modal-close');

    document.querySelectorAll('.btn-more').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const card = btn.closest('.card');
        modalTitle.textContent = card.querySelector('h3').textContent;
        const img = card.querySelector('img');
        if (img) {
            modalImage.src = img.src;
            modalImage.style.display = 'block';
        } else {
            modalImage.style.display = 'none';
        }
        modalPrice.textContent = card.querySelector('.price').textContent;
        modalDetails.textContent = card.querySelector('.details').textContent;
        modal.style.display = 'flex';
      });
    });

    modalClose.onclick = () => modal.style.display = 'none';
    window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };

    /* ---------------- Logo Carousel Partnership ---------------- */
    document.addEventListener("DOMContentLoaded", () => {
      const track = document.querySelector(".logo-track");
      if (track) track.innerHTML += track.innerHTML;
    });

    // Contact Form Modal
    document.addEventListener("DOMContentLoaded", function() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('sent') === '1') {
        var modal = new bootstrap.Modal(document.getElementById('messageModal'));
        modal.show();
      }
    });
</script>

</body>
</html>
