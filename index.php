<?php
// Define our services in a PHP array for easy maintenance
$services = [
    [
        "title" => "Consultation",
        "desc" => "Comprehensive dental checkup, x-ray reviews, and personalized treatment planning to ensure your optimal oral health.",
        "img" => "Images/Consultation.jpg"
    ],
    [
        "title" => "Oral Prophylaxis (Cleaning)",
        "desc" => "Professional teeth cleaning to remove plaque, tartar, and stains, preventing cavities and gum disease.",
        "img" => "Images/Cleaning.jpg"
    ],
    [
        "title" => "Tooth Extraction (Bunot)",
        "desc" => "Safe and painless removal of damaged, severely decayed, or problematic teeth, including wisdom teeth extraction.",
        "img" => "Images/Extraction.jpg"
    ],
    [
        "title" => "Tooth Restoration (Pasta)",
        "desc" => "High-quality tooth-colored composite fillings to repair cavities, chipped teeth, and restore your natural smile.",
        "img" => "Images/Restoration(PASTA).jpg"
    ],
    [
        "title" => "Dentures (Pustiso)",
        "desc" => "Custom-fitted removable partial or complete dentures to replace missing teeth and restore chewing function.",
        "img" => "Images/Denture.jpg"
    ],
    [
        "title" => "Teeth Whitening",
        "desc" => "Professional bleaching treatments to safely and effectively brighten discolored or stained teeth.",
        "img" => "Images/Whitening.jpg"
    ],
    [
        "title" => "Braces",
        "desc" => "Orthodontic treatment setup using metal or ceramic brackets to correct misaligned teeth and bite issues.",
        "img" => "Images/Braces.png"
    ],
    [
        "title" => "EMAX Crown",
        "desc" => "High-quality ceramic crowns to restore the appearance and function of damaged or decayed teeth.",
        "img" => "Images/EMAX Crown.jpg"
    ],
    [
        "title" => "Root Canal Treatment",
        "desc" => "Endodontic therapy to save severely infected or decaying teeth by cleaning out the infected pulp and sealing it.",
        "img" => "Images/RootCanal.jpg"
    ],
    [
        "title" => "Fixed Bridge - Tooth Preparation",
        "desc" => "Preparation and placement of permanent prosthetic bridges to seamlessly fill the gaps left by missing teeth.",
        "img" => "Images/FixedBridge.jpg"
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cebanico Dental Clinic</title>
    
    <!-- Link to external CSS -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Navigation -->
    <header>
        <div class="container">
            <nav>
                <a href="#home" class="logo">
                    <img src="Images/PRIMARY-LOGO.png" alt="MyWebsite Logo">
                </a>
                
                <!-- Hamburger Icon -->
                <div class="hamburger" id="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <ul class="nav-links" id="navLinks">
                    <li><a href="#home" class="active">Home</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Merged Hero Slider -->
    <section class="hero-slider" id="home">
        <div class="slider-container">

            <!-- Slide 1 Background and Content -->
            <div class="slide active">
                <img src="Images/FrontDeskWlogo.png" alt="Slide 1" >
                <div class="slide-content-overlay">
                    <h1>Mabuhay, <span class="hero-highlight">Welcome!</span></h1>
                    <p>We’re here to keep your smiles bright!</p>
                    <div class="buttons">
                        <a href="#services" class="btn btn-primary">Get Started</a>
                        <a href="#about" class="btn btn-secondary">Learn More</a>
                    </div>
                </div>
            </div> 

            <!-- Slide 2 Background and Content (Clinic Hours) -->
            <div class="slide">
                <img src="Images/OutsideClinic.png" alt="Slide 2" >
                
                <div class="slide-content-overlay slide-split">
                    <!-- Upgraded Aesthetic Title on the left -->
                    <div class="split-left aesthetic-left">
                        <span class="eyebrow-text">Plan Your Visit</span>
                        <h1>Clinic <span class="hero-highlight">Hours</span></h1>
                        <p class="desktop-only-p">We are dedicated to providing top-tier dental care. Check our schedule to drop by, or book a private appointment for specialized treatments.</p>
                    </div>
                    
                    <!-- Content on the right -->
                    <div class="split-right">
                        <ul class="schedule-list">
                            <li><span>Monday:</span> 10AM – 6PM</li>
                            <li><span>Tuesday:</span> 10AM – 6PM</li>
                            <li><span>Wednesday:</span> 10AM – 6PM</li>
                            <li><span>Thursday:</span> Closed</li>
                            <li><span>Friday:</span> Strictly by Appointment</li>
                            <li><span>Saturday:</span> 10AM – 6PM</li>
                            <li><span>Sunday:</span> 10AM – 6PM</li>
                        </ul>
                        <div class="buttons">
                            <a href="#contact" class="btn btn-primary">Book Appointment</a>
                            <a href="tel:+639563708675" class="btn btn-secondary">Call Us Now</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Controls -->
            <a class="prev" role="button" tabindex="0" onclick="changeSlide(-1)" aria-label="Previous Slide">&#10094;</a>
            <a class="next" role="button" tabindex="0" onclick="changeSlide(1)" aria-label="Next Slide">&#10095;</a>

            <!-- Slider Dots -->
            <div class="slider-dots">
                <span class="dot active" onclick="currentSlide(1)"></span>
                <span class="dot" onclick="currentSlide(2)"></span>
            </div>

        </div>
    </section>

    <!-- Services Section (Infinite 3D Carousel) -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-title">
                <h2>Our Services</h2>
            </div>
            
            <div class="carousel-container" id="servicesCarousel">
                
                <?php 
                foreach ($services as $index => $service) { 
                    $safeTitle = htmlspecialchars($service['title'], ENT_QUOTES);
                    $safeDesc = htmlspecialchars($service['desc'], ENT_QUOTES);
                ?>
                    <div class="carousel-item" onclick="handleCarouselClick(<?php echo $index; ?>, '<?php echo $safeTitle; ?>', '<?php echo $safeDesc; ?>')">
                        <div class="carousel-inner">
                            <img src="<?php echo $service['img']; ?>" alt="<?php echo $service['title']; ?>">
                            <div class="carousel-overlay">
                                <h3><?php echo $service['title']; ?></h3>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                
            </div>
            
            <div class="carousel-controls">
                <button onclick="navigateCarousel(-1)"><span>&#10094;</span> Prev</button>
                <button onclick="navigateCarousel(1)">Next <span>&#10095;</span></button>
            </div>
        </div>
    </section>

    <!-- Modal for Service Descriptions -->
    <div id="serviceModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Service Title</h2>
            <p id="modalDesc">Service description goes here.</p>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button id="backToTopBtn" aria-label="Back to Top" onclick="scrollToTop()">&#8679;</button>

    <!-- Compact Contact / Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-top">
                
                <!-- Column 1: Info -->
                <div class="footer-col">
                    <h3>Get in Touch <span class="toggle-icon">+</span></h3>
                    <div class="footer-content">
                        <p>Have questions or ready to get started? Reach out to us.</p>
                    </div>
                </div>
                
                <!-- Column 2: Location & Socials (Middle) -->
                <div class="footer-col">
                    <h3>Visit Us <span class="toggle-icon">+</span></h3>
                    <div class="footer-content">
                        <div class="footer-contact-links">
                            <div class="contact-item">
                                <span>📍</span> 
                                <a href="https://maps.app.goo.gl/WMmEotM1g4ev1zdP8" target="_blank">Cebanico Dental Clinic</a>
                            </div>
                            <div class="contact-item social-icons">
                                <a href="https://www.facebook.com/cebanicodental" target="_blank" class="social-link">
                                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.312h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/></svg>
                                    Cebanico Dental Clinic
                                </a>
                            </div>
                            <div class="contact-item social-icons">
                                <a href="https://www.instagram.com/cebanicodental?utm_source=ig_web_button_share_sheet&stkn=ZDNlZDc0MzIxNw%3D%3D&fbclid=IwY2xjawUlx_hleHRuA2FlbQIxMABwZG9mBWJyaWQRMU9qWk1sRm5kTFRBNkpSNDlzcnRjBmFwcF9pZBAyMjIwMzkxNzg4MjAwODkyAAEeTFpwlj9fOAawyyS1PFXr-x-qnw-95K1zPGJTSBohO2Awufv10Nda0orrjm4_aem_mufFhZ-EhnzMO1z2n_apug" target="_blank" class="social-link">
                                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                    cebanicodental
                                </a>
                            </div>
                    <h3>Visit Us</h3>
                    <div class="footer-contact-links">
                        <div class="contact-item">
                            <span>📍</span> 
                            <a href="https://maps.app.goo.gl/WMmEotM1g4ev1zdP8" target="_blank">Cebanico Dental Clinic</a>
                        </div>
                        <div class="contact-item social-icons">
                            <a href="https://www.facebook.com/cebanicodental" target="_blank" class="social-link">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.312h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/></svg>
                                Cebanico Dental Clinic
                            </a>
                        </div>
                        <div class="contact-item social-icons">
                            <a href="https://www.instagram.com/cebanicodental?utm_source=ig_web_button_share_sheet&stkn=ZDNlZDc0MzIxNw%3D%3D&fbclid=IwY2xjawUlx_hleHRuA2FlbQIxMABwZG9mBWJyaWQRMU9qWk1sRm5kTFRBNkpSNDlzcnRjBmFwcF9pZBAyMjIwMzkxNzg4MjAwODkyAAEeTFpwlj9fOAawyyS1PFXr-x-qnw-95K1zPGJTSBohO2Awufv10Nda0orrjm4_aem_mufFhZ-EhnzMO1z2n_apug" target="_blank" class="social-link">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                cebanicodental
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Column 3: Contact Details -->
                <div class="footer-col">
                    <h3>Contact <span class="toggle-icon">+</span></h3>
                    <div class="footer-content">
                        <div class="footer-contact-links">
                            <div class="contact-item">
                                <span>📞</span> <a href="tel:+639563708675">0956 370 8675</a>
                            </div>
                            <div class="contact-item">
                                <span>✉️</span> <a href="mailto:cebanicodentalclinic@gmail.com">cebanicodentalclinic@gmail.com</a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> Cebanico Dental Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Link to external JavaScript -->
    <script src="index.js"></script>

</body>
</html>