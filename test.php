<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hero Slideshow</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@600&family=Inria+Serif:wght@300&family=Inter:wght@700;800&display=swap" rel="stylesheet">

    <style>
        /* Basic reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* =====================================================
        YOUR ORIGINAL CSS - MODIFIED
        =====================================================
        */

        /* Default Hero (Desktop / Laptop) */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff; /* MODIFIED: Was #0b0b0b (black) */
            padding: 8vh 6%;
            position: relative;
            overflow: hidden; /* Added to contain the slides */
            background-color: #000; /* ADDED: Fixes white flicker */
        }
        
        /* ADDED: Dark overlay for text readability */
        .hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 2; /* Sits on top of slides, below content */
        }

        .hero .copy {
            max-width: 1100px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            /* MODIFIED: z-index to sit above overlay */
            position: relative;
            z-index: 3;
        }

        .rounded-login,
        .rounded-signup {
            border-radius: 30px;
            padding: 5px 60px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif; /* Corrected font-family name */
            transition: all 0.3s ease;
            /* Added for <a> tag styling */
            text-decoration: none;
            display: inline-block;
        }
        .rounded-login {
            color: #8C367C;
            background-color: #ffffff;
        }
        .rounded-signup {
            background-color: #8C367C;
            color: #ffffff;
        }

        /* Hover effects */
        .rounded-login:hover {
            background-color: #8C367C;
            color: #ffffff;
        }
        .rounded-signup:hover {
            background-color: #ffffff;
            color: #8C367C;
        }

        /* Heading */
        .hero h1 {
            font-family: 'Inter', sans-serif; /* Corrected font-family name */
            font-size: clamp(40px, 8vw, 100px);
            line-height: 1.3;
            margin-bottom: 60px;
            font-weight: 800;
        }
        .hero h1 em { font-style: italic; color: #8f3b72; font-weight: 700; }
        .hero h1 span.big { color: #12984a; font-style: italic; font-weight: 700; }

        /* Paragraph */
        .hero p.lead {
            font-size: 23px;
            margin-bottom: 22px;
            opacity: 0.9;
            font-family: 'Inria Serif', serif; /* Corrected font-family name */
            line-height: 1.2;
            font-weight: 200;
        }

        /* Call Us section at bottom (Desktop default) */
        .hero .contact-info {
            position: absolute;
            bottom: 40px;
            left: 6%;
            right: 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: clamp(16px, 1.6vw, 20px);
            font-weight: 200;
            /* MODIFIED: z-index to sit above overlay */
            position: absolute;
            z-index: 3;
        }

        /* =====================================================
        YOUR RESPONSIVE CSS - UNCHANGED
        =====================================================
        */

        /* RESPONSIVE ADJUSTMENTS  */
        /* Tablet (≤992px) */
        @media (max-width: 992px) {
            .hero h1 {
                font-size: clamp(32px, 6vw, 70px);
                margin-bottom: 40px;
                line-height: 1.2;
            }

            .hero p.lead {
                font-size: 20px;
                line-height: 1.4;
            }

            .rounded-login,
            .rounded-signup {
                padding: 10px 40px;
                font-size: 15px;
            }

            .hero .contact-info {
                bottom: 30px;
                font-size: 16px;
            }
        }
        /* Mobile (≤768px) */
        @media (max-width: 768px) {
            .hero {
                padding: 6vh 5%;
                text-align: center;
            }

            .hero .copy {
                align-items: center;
                text-align: center;
            }

            .hero h1 {
                font-size: clamp(55px, 7vw, 60px);
                margin-bottom: 25px;
                line-height: 1.2;
            }

            .hero p.lead {
                font-size: 18px;
                line-height: 1.5;
                max-width: 90%;
                margin: 0 auto 25px;
            }

            /* Target the div holding the buttons */
            .hero .copy > div {
                display: flex;
                flex-direction: column;
                gap: 15px;
                align-items: center;
            }

            .rounded-login,
            .rounded-signup {
                width: auto;          /* no fixed width */
                min-width: 160px;     /* keep good size */
                max-width: 260px;     /* prevent too wide */
                padding: 12px 40px;   /* consistent pill shape */
                font-size: 16px;
                border-radius: 30px;
            }

            /* Contact info centered */
            .hero .contact-info {
                flex-direction: column;
                text-align: center;
                gap: 6px;
                bottom: 20px;
            }
        }
        /* Extra Small Mobile (≤480px) */
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 40px;
                margin-bottom: 20px;
            }

            .hero p.lead {
                font-size: 15px;
                line-height: 1.4;
            }

            .rounded-login,
            .rounded-signup {
                font-size: 14px;
                padding: 10px 30px;
                min-width: 140px;
            }
        }

        /* =====================================================
        NEW SLIDESHOW CSS
        =====================================================
        */
        .slideshow-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1; /* MODIFIED: Sits behind overlay */
        }

        .slideshow-background .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            opacity: 0;
            transition: opacity 1.5s ease-in-out; /* Fade effect */
        }

        .slideshow-background .slide.active {
            opacity: 1; /* Makes the current slide visible */
        }

    </style>
</head>
<body>

    <section class="hero">

        <div class="slideshow-background">
            <img src="../images/premiumbull.jpg" alt="Slideshow image 1" class="slide">
            <img src="../images/sumosuit.jpeg" alt="Slideshow image 2" class="slide">
            <img src="../images/twinlane.jpeg" alt="Slideshow image 3" class="slide">
            <img src="../images/adrenaline-rush.jpeg" alt="Slideshow image 4" class="slide">
            <img src="../images/footypass.jpeg" alt="Slideshow image 5" class="slide">
            <img src="../images/gladiator.jpeg" alt="Slideshow image 6" class="slide">
            <img src="../images/mechanical-bull-adult-castle.jpg" alt="Slideshow image 7" class="slide">
            <img src="../images/mechanical-surf.jpg" alt="Slideshow image 8" class="slide">
        </div>
        
        <div class="copy" >
            <h1>Because <em>FUN</em><br>Is Always<br>Better When It's <span class="big">BIG</span></h1>
            <p class="mt-3 lead">Sign up today and unlock your BigFUN experience — book your spot now!</p>
            <div>
                <a class="mt-3 me-2 btn rounded-login" href="login.php">Login</a>
                <a class="mt-3 btn rounded-signup" href="signup.php">Sign up</a>
            </div>
        </div>

        <div class="contact-info">
            <small>Call Us Today!</small>
            <small>1800 244 386 | Mon. - Fri. | 8 am - 8 pm</small>
        </div>
    </section>

    <section style="height: 100vh; padding: 50px; background-color: #f4f4f4;">
        <h2>Content After the Hero</h2>
        <p>This is just here so you can see the hero section is 100vh.</p>
    </section>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            
            // Get all the images with the class 'slide'
            const slides = document.querySelectorAll(".slideshow-background .slide");
            
            // Set the starting slide index
            let currentSlide = 0;

            // Check if there are any slides to show
            if (slides.length > 0) {
                // Make the first slide active (visible) immediately
                slides[currentSlide].classList.add("active");

                // Function to change to the next slide
                function nextSlide() {
                    // Remove 'active' class from the current slide to fade it out
                    slides[currentSlide].classList.remove("active");

                    // Move to the next slide index
                    currentSlide = (currentSlide + 1) % slides.length; // Loop back to 0

                    // Add 'active' class to the new current slide to fade it in
                    slides[currentSlide].classList.add("active");
                }

                // Change slide every 5 seconds (5000 milliseconds)
                setInterval(nextSlide, 5000);
            }
        });
    </script>

</body>
</html>

