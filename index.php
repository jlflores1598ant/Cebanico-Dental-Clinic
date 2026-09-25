<?php
// Static homepage
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Website</title>
    
    <!-- Link to external CSS -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Navigation -->
    <header>
        <div class="container">
            <nav>
                <a href="#" class="logo">
                    <img src="Images/PRIMARY-LOGO.png" alt="MyWebsite Logo">
                </a>
                <ul class="nav-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Slider -->
    <section class="hero-slider" id="home">
        <div class="slider-container">

            <!-- Slide 1 -->
            <div class="slide active">
                <a href="#about">
                    <img src="Images/FrontDesk.JPEG" alt="Slide 1">
                </a>
                <div class="slide-content">
                    <h1>Build Something <a href="#about" class="hero-highlight">Awesome.</a></h1>
                    <p>A clean and modern website built with PHP, HTML, and CSS. Click the image to learn more.</p>
                    <div class="buttons">
                        <a href="#services" class="btn btn-primary">Get Started</a>
                        <a href="#about" class="btn btn-secondary">Learn More</a>
                    </div>
                </div>
            </div>

            <!-- Slide 2 -->
            <div class="slide">
                <a href="#services">
                    <img src="https://placehold.co/1200x600/dbeafe/111827?text=Click+to+view+Services" alt="Slide 2">
                </a>
                <div class="slide-content">
                    <h1>Fast & <a href="#services" class="hero-highlight">Modern.</a></h1>
                    <p>Optimized code that loads quickly on desktop and mobile devices. Click the image for our services.</p>
                    <div class="buttons">
                        <a href="#services" class="btn btn-primary">View Services</a>
                    </div>
                </div>
            </div>

            <!-- Navigation Controls -->
            <a class="prev" role="button" tabindex="0" onclick="changeSlide(-1)">&#10094;</a>
            <a class="next" role="button" tabindex="0" onclick="changeSlide(1)">&#10095;</a>

        </div>
    </section>

    <!-- Services (Previously Features) -->
    <section class="features" id="services">
        <div class="container">
            <div class="section-title">
                <h2>Our Services</h2>
                <p>Everything you need to create a great online presence.</p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="icon">🚀</div>
                    <h3>Fast</h3>
                    <p>Lightweight and optimized code that loads quickly on desktop and mobile devices.</p>
                </div>
                <div class="feature-card">
                    <div class="icon">🎨</div>
                    <h3>Modern Design</h3>
                    <p>A clean and professional design that can easily be customized.</p>
                </div>
                <div class="feature-card">
                    <div class="icon">📱</div>
                    <h3>Responsive</h3>
                    <p>Looks great on phones, tablets, laptops, and desktop screens.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About -->
    <section class="about" id="about">
        <div class="container">
            <div class="section-title">
                <h2>About Us</h2>
                <p>We create simple and effective digital experiences that help people and businesses establish their presence online.</p>
            </div>
        </div>
    </section>

    <!-- Compact Contact / Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-top">
                <div class="footer-info">
                    <h3>Get in Touch</h3>
                    <p>Have questions or ready to get started? Reach out to us.</p>
                </div>
                <div class="footer-contact-links">
                    <div class="contact-item">
                        <span>📍</span> 123 Business St, New York, NY 10001
                    </div>
                    <div class="contact-item">
                        <span>📞</span> <a href="tel:+11234567890">+1 (123) 456-7890</a>
                    </div>
                    <div class="contact-item">
                        <span>✉️</span> <a href="mailto:hello@example.com">hello@example.com</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> MyWebsite. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Link to external JavaScript -->
    <script src="index.js"></script>

</body>
</html>