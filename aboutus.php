<?php
$year = date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Cebanico Dental Clinic</title>

    <link rel="icon" type="image/png" href="Images/Faviconlogo.png">

    <script>
        // Prevent FOUC (Flash of Unstyled Content) by checking theme early
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="styles.css?v=19">
</head>
<body>

    <header class="floating-header">
        <nav>
            <a href="index.php" class="logo">
                <img src="Images/PRIMARY-LOGO.png" alt="Cebanico Dental Clinic Logo" class="logo-light">
                <img src="Images/PRIMARY-LOGO-WHITE.png" alt="Cebanico Dental Clinic Logo" class="logo-dark">
            </a>
            
            <div class="nav-right">
                <ul class="nav-links" id="navLinks">
                    <li><a href="index.php#home">Home</a></li>
                    <li><a href="index.php#services">Services</a></li>
                    <li><a href="aboutus.php" class="active">About</a></li>
                    <li><a href="index.php#contact" class="nav-contact-btn">Contact</a></li>
                </ul>
                
                <div class="theme-toggle" id="themeToggle" aria-label="Toggle Dark Mode" title="Toggle Theme">
                    <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                </div>

                <div class="hamburger" id="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </nav>
    </header>

    <div class="about-welcome" style="margin-top: 85px;">
        <h2>Welcome to Cebanico Dental Clinic!</h2>
    </div>

    <section class="about-page" id="about">
        <div class="container">
            <div class="about-grid">

                <div class="about-image-col fade-in-left">
                    <div class="about-image-wrapper">
                        <div class="image-frame">
                            <img src="Images/DENTISTIMG.jpg" alt="Our Clinic">
                        </div>
                        <div class="doctor-caption">Doc. Ma. Althez A. Cebanico</div>
                    </div>
                </div>

                <div class="about-text-box fade-in-left fade-delay-2">

                    <div class="about-text-content">
                        <p>Cebanico Dental Clinic is dedicated to providing high-quality, comprehensive oral healthcare designed to protect and restore your smile in a professional and comfortable environment.</p>
                        <p>Our practice delivers a broad range of essential treatments, including professional dental cleanings, advanced root canal therapy to preserve natural teeth, and safe, gentle tooth extractions.</p>
                        <p>Driven by patient-centered values and clinical precision, we prioritize your long-term dental health through personalized care tailored to your specific needs.</p>
                    </div>

                    <div class="about-highlights" id="aboutHighlights">
                        <div class="highlight-viewport">

                            <div class="highlight-slide active">
                                <div class="highlight-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/>
                                        <path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/>
                                        <circle cx="20" cy="10" r="2"/>
                                    </svg>
                                </div>
                                <h3>Qualified Practitioners</h3>
                                <p>Optimizes clinical outcomes</p>
                            </div>

                            <div class="highlight-slide">
                                <div class="highlight-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"/>
                                        <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                                        <path d="M9 9h.01"/>
                                        <path d="M15 9h.01"/>
                                    </svg>
                                </div>
                                <h3>Enhanced Oral Hygiene</h3>
                                <p>Assures optimal dentition</p>
                            </div>

                            <div class="highlight-slide">
                                <div class="highlight-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                                        <path d="M16 2v4"/>
                                        <path d="M8 2v4"/>
                                        <path d="M3 10h18"/>
                                        <path d="m9 16 2 2 4-4"/>
                                    </svg>
                                </div>
                                <h3>Streamlined Scheduling</h3>
                                <p>Facilitates seamless reservations</p>
                            </div>

                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <button id="backToTopBtn" aria-label="Back to Top" onclick="scrollToTop()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>

    <footer id="contact">
        <div class="container reveal">
            <div class="footer-top">
                <div class="footer-col">
                    <h3>Get in Touch <span class="toggle-icon">+</span></h3>
                    <div class="footer-content">
                        <p>Have questions or ready to get started? Reach out to us. Your perfect smile is just a call away.</p>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Visit Us <span class="toggle-icon">+</span></h3>
                    <div class="footer-content">
                        <div class="footer-contact-links">
                            <div class="contact-item">
                                <span>📍</span>
                                <a href="https://maps.app.goo.gl/WMmEotM1g4ev1zdP8" target="_blank">Cebanico Dental Clinic</a>
                            </div>
                        </div>
                    </div>
                </div>

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
                            <div class="contact-item social-icons">
                                <a href="https://www.facebook.com/cebanicodental" target="_blank" class="social-link">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.312h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/></svg>
                                    Cebanico Dental
                                </a>
                            </div>
                            <div class="contact-item social-icons">
                                <a href="https://www.instagram.com/cebanicodental" target="_blank" class="social-link">
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                    cebanicodental
                                </a>
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

    <script src="index.js?v=19"></script>

</body>
</html>