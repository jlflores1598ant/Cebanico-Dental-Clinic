<?php
// Define our services in a PHP array for easy maintenance
$services = [
    [
        "title" => "Consultation",
        "desc" => "Comprehensive dental checkup, x-ray reviews, and personalized treatment planning to ensure your optimal oral health.",
        "img" => "https://placehold.co/800x600/2563eb/ffffff?text=Consultation"
    ],
    [
        "title" => "Oral Prophylaxis (Cleaning)",
        "desc" => "Professional teeth cleaning to remove plaque, tartar, and stains, preventing cavities and gum disease.",
        "img" => "https://placehold.co/800x600/1d4ed8/ffffff?text=Cleaning"
    ],
    [
        "title" => "Tooth Extraction (Bunot)",
        "desc" => "Safe and painless removal of damaged, severely decayed, or problematic teeth, including wisdom teeth extraction.",
        "img" => "https://placehold.co/800x600/1e3a8a/ffffff?text=Extraction"
    ],
    [
        "title" => "Surgery",
        "desc" => "Advanced minor oral surgeries including impacted wisdom tooth removal, bone grafting, and gingivectomy.",
        "img" => "https://placehold.co/800x600/2563eb/ffffff?text=Surgery"
    ],
    [
        "title" => "Tooth Restoration (Pasta)",
        "desc" => "High-quality tooth-colored composite fillings to repair cavities, chipped teeth, and restore your natural smile.",
        "img" => "https://placehold.co/800x600/1d4ed8/ffffff?text=Restoration"
    ],
    [
        "title" => "Dentures (Pustiso)",
        "desc" => "Custom-fitted removable partial or complete dentures to replace missing teeth and restore chewing function.",
        "img" => "https://placehold.co/800x600/1e3a8a/ffffff?text=Dentures"
    ],
    [
        "title" => "Teeth Whitening",
        "desc" => "Professional bleaching treatments to safely and effectively brighten discolored or stained teeth.",
        "img" => "https://placehold.co/800x600/2563eb/ffffff?text=Whitening"
    ],
    [
        "title" => "Braces Installation",
        "desc" => "Orthodontic treatment setup using metal or ceramic brackets to correct misaligned teeth and bite issues.",
        "img" => "https://placehold.co/800x600/1d4ed8/ffffff?text=Braces+Install"
    ],
    [
        "title" => "Braces Adjustment",
        "desc" => "Routine orthodontic tightening and wire replacements to ensure continuous progress in your teeth alignment.",
        "img" => "https://placehold.co/800x600/1e3a8a/ffffff?text=Braces+Adjust"
    ],
    [
        "title" => "Root Canal Treatment",
        "desc" => "Endodontic therapy to save severely infected or decaying teeth by cleaning out the infected pulp and sealing it.",
        "img" => "https://placehold.co/800x600/2563eb/ffffff?text=Root+Canal"
    ],
    [
        "title" => "Fixed Bridge - Tooth Preparation",
        "desc" => "Preparation and placement of permanent prosthetic bridges to seamlessly fill the gaps left by missing teeth.",
        "img" => "https://placehold.co/800x600/1d4ed8/ffffff?text=Fixed+Bridge"
    ]
];
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

    <!-- Merged Hero Slider -->
    <section class="hero-slider" id="home">
        <div class="slider-container">

            <!-- Persistent Overlay Content -->
            <div class="slide-content-overlay">
                <h1>Mabuhay, <a href="#about" class="hero-highlight">Welcome!</a></h1>
                <p>We’re here to keep your smiles bright!</p>
                <div class="buttons">
                    <a href="#services" class="btn btn-primary">Get Started</a>
                    <a href="#about" class="btn btn-secondary">Learn More</a>
                </div>
            </div>

            <!-- Slide 1 Background -->
            <div class="slide active">
                <img src="Images/FrontDeskWlogo.png" alt="Slide 1" >
            </div> <!-- Added missing closing div here -->

            <!-- Slide 2 Background -->
            <div class="slide">
                <img src="Images/OutsideClinic.png" alt="Slide 2" >
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
                <p>Click on any side image to bring it to the center. Click the center image to view its description.</p>
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