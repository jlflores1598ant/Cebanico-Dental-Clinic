<?php
session_start();
date_default_timezone_set("Asia/Manila");

// Require patient sign-in before accessing the booking form
if (!isset($_SESSION["patient"]) || !is_array($_SESSION["patient"])) {
    header("Location: signin.php");
    exit;
}

$patientSession = $_SESSION["patient"];

// Check if redirected back after successful verification
$verificationPopup = null;
if (isset($_SESSION["verification_complete"]) && is_array($_SESSION["verification_complete"])) {
    $verificationPopup = $_SESSION["verification_complete"];
    unset($_SESSION["verification_complete"]);
}

// Define our services array for the dropdown options
$services = [
    "Consultation",
    "Oral Prophylaxis (Cleaning)",
    "Tooth Extraction (Bunot)",
    "Tooth Restoration (Pasta)",
    "Dentures (Pustiso)",
    "Teeth Whitening",
    "Braces Installation",
    "Braces Adjustment",
    "EMAX Crown",
    "Root Canal Treatment",
    "Fixed Bridge - Tooth Preparation"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | Cebanico Dental Clinic</title>
    
    <link rel="icon" type="image/png" href="Images/Faviconlogo.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="styles.css?v=37">
    <link rel="stylesheet" href="appointment.css?v=37">
</head>
<body>

    <header class="floating-header">
        <nav>
            <a href="index.php" class="logo">
                <img src="Images/PRIMARY-LOGO.png" alt="Cebanico Dental Clinic Logo" class="main-nav-logo">
            </a>
            
            <div class="nav-right">
                <ul class="nav-links" id="navLinks">
                    <li><a href="index.php#home">Home</a></li>
                    <li><a href="index.php#services">Services</a></li>
                    <li><a href="aboutus.php">About</a></li>
                    <li><a href="signin.php" class="nav-contact-btn active">Book Appointment</a></li>
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

    <!-- Floating Pop-up Restriction Message -->
    <div id="restrictionPopup" class="restriction-popup" role="alert" aria-live="assertive">
        <div class="restriction-popup-icon">!</div>
        <div class="restriction-popup-body">
            <strong id="restrictionPopupTitle">Input Restriction</strong>
            <p id="restrictionPopupMsg">Invalid input detected.</p>
        </div>
        <button type="button" id="closeRestrictionPopup" class="restriction-popup-close" aria-label="Close message">&times;</button>
    </div>

    <?php if ($verificationPopup !== null) { ?>
    <!-- Verification Complete Pop-Up Modal -->
    <div id="verificationCompleteModal" class="verification-modal-backdrop show" role="dialog" aria-modal="true" aria-labelledby="verifyCompleteHeading">
        <div class="verification-modal-card">
            <button type="button" id="closeVerificationModalX" class="verification-modal-close" aria-label="Close pop-up">&times;</button>

            <div class="verification-modal-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <span class="verification-modal-badge">Email Verified</span>
            <h2 id="verifyCompleteHeading">Verification Complete!</h2>
            <p class="verification-modal-sub">
                Thank you, <strong><?php echo htmlspecialchars($verificationPopup["full_name"] ?? "Patient"); ?></strong>! Your appointment request has been verified.
            </p>

            <ul class="verification-modal-details">
                <li>
                    <span>Reference No.</span>
                    <strong class="ref-code"><?php echo htmlspecialchars($verificationPopup["ref"] ?? ""); ?></strong>
                </li>
                <li>
                    <span>Service</span>
                    <strong><?php echo htmlspecialchars($verificationPopup["service"] ?? ""); ?></strong>
                </li>
                <li>
                    <span>Date</span>
                    <strong><?php echo htmlspecialchars($verificationPopup["date"] ?? ""); ?></strong>
                </li>
            </ul>

            <button type="button" id="closeVerificationModalBtn" class="btn btn-primary verification-modal-btn">Done</button>
        </div>
    </div>
    <?php } ?>

    <main class="appointment-hero">
        <div class="slide-overlay-gradient"></div>
        <img src="Images/OutsideClinic.png" alt="Clinic Background" class="appointment-bg-img">
        
        <div class="appointment-layout container">
            
            <!-- Left Info Content -->
            <div class="appointment-info">
                <span class="eyebrow-text">Appointments</span>
                <h1>Book your <span class="hero-highlight">visit</span></h1>
                <p>Tell us what you need and when. We will confirm your slot by call or email.</p>
                
                <div class="appointment-hours-note">
                    <p><strong>Signed in as:</strong> <?php echo htmlspecialchars($patientSession["username"] ?? ""); ?> (<?php echo htmlspecialchars($patientSession["email"] ?? ""); ?>) &mdash; <a href="signin.php" style="color: #38bdf8; text-decoration: underline;">Switch account</a></p>
                    <p><strong>Open Mon, Tue, Wed, Sat, Sun:</strong> 10AM – 6PM</p>
                    <p><strong>Friday:</strong> by appointment only</p>
                    <p><strong>Thursday:</strong> closed</p>
                </div>

                <div class="view-appointments-cta">
                    <a href="my-appointments.php" class="btn btn-secondary view-my-appt-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                            <path d="M8 14h.01"></path>
                            <path d="M12 14h.01"></path>
                            <path d="M16 14h.01"></path>
                            <path d="M8 18h.01"></path>
                            <path d="M12 18h.01"></path>
                        </svg>
                        <span>View My Appointments</span>
                    </a>
                </div>
            </div>

            <!-- Right Form Container -->
            <div class="appointment-form-wrapper">
                <form class="appointment-form glass-card" action="process-appointment.php" method="POST" novalidate>
                    
                    <div class="form-group">
                        <label for="fullName">Full name</label>
                        <input type="text" id="fullName" name="fullName" placeholder="Juan Dela Cruz" value="<?php echo htmlspecialchars($patientSession["username"] ?? ""); ?>" autocomplete="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($patientSession["email"] ?? ""); ?>" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label for="contactNumber">Contact number</label>
                        <input type="tel" id="contactNumber" name="contactNumber" placeholder="09171234567" value="<?php echo htmlspecialchars($patientSession["contact_number"] ?? ""); ?>" inputmode="numeric" maxlength="11" autocomplete="tel" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="service">Type of service</label>
                            <select id="service" name="service" required>
                                <option value="" disabled selected>Select a service</option>
                                <?php foreach ($services as $srv) { ?>
                                    <option value="<?php echo htmlspecialchars($srv); ?>"><?php echo htmlspecialchars($srv); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group half-width">
                            <label for="appointmentDateDisplay">Day of appointment</label>
                            <div class="custom-date-picker" id="customDatePicker">
                                <input type="hidden" id="appointmentDate" name="appointmentDate" required>
                                <button type="button" id="appointmentDateDisplay" class="date-display-btn" aria-haspopup="dialog" aria-expanded="false">
                                    <span id="appointmentDateText" class="date-placeholder">Select date</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                </button>

                                <div id="calendarPopover" class="calendar-popover" role="dialog" aria-label="Choose appointment date">
                                    <div class="calendar-header">
                                        <button type="button" id="calPrevMonth" class="cal-nav-btn" aria-label="Previous month">&#10094;</button>
                                        <div class="cal-select-group">
                                            <select id="calMonthSelect" aria-label="Select month"></select>
                                            <select id="calYearSelect" aria-label="Select year"></select>
                                        </div>
                                        <button type="button" id="calNextMonth" class="cal-nav-btn" aria-label="Next month">&#10095;</button>
                                    </div>

                                    <div class="calendar-weekdays">
                                        <span>Sun</span>
                                        <span>Mon</span>
                                        <span>Tue</span>
                                        <span>Wed</span>
                                        <span>Thu</span>
                                        <span>Fri</span>
                                        <span>Sat</span>
                                    </div>

                                    <div id="calDaysGrid" class="calendar-days"></div>

                                    <div class="calendar-footer">
                                        <span>Thursdays are closed</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group checkbox-group">
                        <label class="checkbox-container">
                            <input type="checkbox" id="consent" name="consent" required>
                            <span class="checkmark"></span>
                            I agree that Cebanico Dental Clinic may use my details to contact me about this appointment.
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary appointment-submit-btn">Request appointment</button>

                    <div class="verify-code-divider"><span>Manage or verify your booking</span></div>

                    <div class="booking-secondary-actions">
                        <a href="my-appointments.php" class="btn btn-secondary verify-code-btn">View My Appointments</a>
                        <a href="verify-code.php" class="btn btn-secondary verify-code-btn">Verify Code</a>
                    </div>

                </form>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer id="contact">
        <div class="container reveal">
            <div class="footer-top">
                <div class="footer-col">
                    <h3>Get in Touch</h3>
                    <div class="footer-content">
                        <p>Have questions or ready to get started? Reach out to us. Your perfect smile is just a call away.</p>
                        <a href="signin.php" style="color: var(--primary, #0ea5e9); font-weight: 600; display: inline-block; margin-top: 12px;">Book an Appointment &rarr;</a>
                    </div>
                </div>

                <div class="footer-col">
                    <h3>Visit Us</h3>
                    <div class="footer-content">
                        <a href="https://www.google.com/maps/search/?api=1&query=0176+Manila+E+Rd+Binangonan+Rizal" target="_blank" rel="noopener noreferrer" class="contact-item">
                            <span>📍</span>
                            <span>0176 Manila E Rd, Binangonan, Rizal</span>
                        </a>
                    </div>
                </div>

                <div class="footer-col">
                    <h3>Contact</h3>
                    <div class="footer-content">
                        <div class="footer-contact-links">
                            <a href="tel:+639563708675" class="contact-item">
                                <span>📞</span>
                                <span>0956 370 8675</span>
                            </a>
                            <a href="mailto:cebanicodentalclinic@gmail.com" class="contact-item">
                                <span>✉️</span>
                                <span>cebanicodentalclinic@gmail.com</span>
                            </a>
                            <a href="https://www.facebook.com/cebanicodental" target="_blank" rel="noopener noreferrer" class="contact-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h7v-7h-2v-3h2V9.5C12 7.57 13.57 6 15.5 6H18v3h-2c-.55 0-1 .45-1 1v1h3v3h-3v7h4c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"></path>
                                </svg>
                                <span>Cebanico Dental</span>
                            </a>
                            <a href="https://www.instagram.com/cebanicodental" target="_blank" rel="noopener noreferrer" class="contact-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                </svg>
                                <span>cebanicodental</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> Cebanico Dental Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <button id="backToTopBtn" aria-label="Back to Top" onclick="scrollToTop()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>

    <script src="index.js?v=37"></script>
    <script src="appointment.js?v=37"></script>
    <script>
        (function () {
            const modal = document.getElementById('verificationCompleteModal');
            if (!modal) return;

            const closeBtn = document.getElementById('closeVerificationModalBtn');
            const closeX   = document.getElementById('closeVerificationModalX');

            function closeVerificationPopup() {
                modal.classList.remove('show');
                if (window.history && window.history.replaceState) {
                    const cleanUrl = window.location.pathname;
                    window.history.replaceState({}, document.title, cleanUrl);
                }
            }

            if (closeBtn) closeBtn.addEventListener('click', closeVerificationPopup);
            if (closeX) closeX.addEventListener('click', closeVerificationPopup);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeVerificationPopup();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    closeVerificationPopup();
                }
            });
        })();
    </script>
</body>
</html>