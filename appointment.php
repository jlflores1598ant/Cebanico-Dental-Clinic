<?php
date_default_timezone_set("Asia/Manila");

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
    <link rel="stylesheet" href="styles.css?v=27">
    <link rel="stylesheet" href="appointment.css?v=27">
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
                    <li><a href="appointment.php" class="nav-contact-btn active">Book Appointment</a></li>
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
                    <p><strong>Open Mon, Tue, Wed, Sat, Sun:</strong> 10AM – 6PM</p>
                    <p><strong>Friday:</strong> by appointment only</p>
                    <p><strong>Thursday:</strong> closed</p>
                </div>
            </div>

            <!-- Right Form Container -->
            <div class="appointment-form-wrapper">
                <form class="appointment-form glass-card" action="process-appointment.php" method="POST" novalidate>
                    
                    <div class="form-group">
                        <label for="fullName">Full name</label>
                        <input type="text" id="fullName" name="fullName" placeholder="Juan Dela Cruz" autocomplete="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="name@example.com" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label for="contactNumber">Contact number</label>
                        <input type="tel" id="contactNumber" name="contactNumber" placeholder="09171234567" inputmode="numeric" maxlength="11" autocomplete="tel" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="service">Type of service</label>
                            <select id="service" name="service" required>
                                <option value="" disabled selected>Select a service</option>
                                <?php foreach ($services as$srv) { ?>
                                    <option value="<?php echo htmlspecialchars($srv); ?>"><?php echo htmlspecialchars($srv); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group half-width">
                            <label for="appointmentDateDisplay">Day of appointment</label>
                            <div class="custom-date-wrapper" id="customDateWrapper">
                                <input type="text" id="appointmentDateDisplay" placeholder="mm/dd/yyyy" readonly required>
                                <input type="hidden" id="appointmentDate" name="appointmentDate" required>
                                <span class="calendar-trigger-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                </span>

                                <div class="custom-calendar-popover" id="customCalendarPopover" role="dialog" aria-label="Choose appointment date">
                                    <div class="calendar-header">
                                        <button type="button" class="cal-nav-btn" id="calPrevMonth" aria-label="Previous month">&#10094;</button>
                                        <div class="cal-select-group">
                                            <select id="calMonthSelect" aria-label="Select month"></select>
                                            <select id="calYearSelect" aria-label="Select year"></select>
                                        </div>
                                        <button type="button" class="cal-nav-btn" id="calNextMonth" aria-label="Next month">&#10095;</button>
                                    </div>
                                    <div class="calendar-weekdays">
                                        <span>Su</span>
                                        <span>Mo</span>
                                        <span>Tu</span>
                                        <span>We</span>
                                        <span class="thursday-col">Th</span>
                                        <span>Fr</span>
                                        <span>Sa</span>
                                    </div>
                                    <div class="calendar-days" id="calendarDays"></div>
                                    <div class="calendar-footer-note">Closed on Thursdays</div>
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

                </form>
            </div>

        </div>
    </main>

    <button id="backToTopBtn" aria-label="Back to Top" onclick="scrollToTop()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>

    <script src="index.js?v=27"></script>
    <script src="appointment.js?v=27"></script>
</body>
</html>