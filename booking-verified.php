<?php
session_start();
date_default_timezone_set("Asia/Manila");
require_once "db_connect.php";

$verifiedBooking = $_SESSION["verified_booking"] ?? null;
$refQuery        = strtoupper(trim($_GET["ref"] ?? ""));

// Fallback lookup by ?ref= if the page is refreshed
if (!is_array($verifiedBooking) && $refQuery !== "") {
    $numericId = (int)preg_replace("/[^0-9]/", "", $refQuery);
    if ($numericId > 0) {
        $stmt = $conn->prepare(
            "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.day_of_week,
                    a.service, a.is_verified, a.status,
                    r.full_name, r.email, r.contact_number
             FROM appointments a
             INNER JOIN appointment_requests r ON a.request_id = r.request_id
             WHERE a.appointment_id = ? AND a.is_verified = 1
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("i", $numericId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if ($row) {
                $dateObj    = DateTime::createFromFormat("Y-m-d", $row["appointment_date"]);
                $prettyDate = $dateObj ? $dateObj->format("F j, Y") : $row["appointment_date"];
                $verifiedBooking = [
                    "ref"              => "CDC-" . str_pad((string)$row["appointment_id"], 4, "0", STR_PAD_LEFT),
                    "appointment_id"   => (int)$row["appointment_id"],
                    "full_name"        => $row["full_name"],
                    "email"            => $row["email"],
                    "contact_number"   => $row["contact_number"],
                    "service"          => $row["service"],
                    "appointment_date" => $row["appointment_date"],
                    "pretty_date"      => $prettyDate,
                    "appointment_time" => $row["appointment_time"] ?? "",
                    "day_of_week"      => $row["day_of_week"],
                    "status"           => $row["status"]
                ];
            }
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

if (!is_array($verifiedBooking)) {
    header("Location: verify-code.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Verified | Cebanico Dental Clinic</title>

    <link rel="icon" type="image/png" href="Images/Faviconlogo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="styles.css?v=39">
    <link rel="stylesheet" href="appointment.css?v=39">
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

                <a href="profile.php" class="nav-profile-btn" aria-label="My Profile" title="My Profile">
                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </a>

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

    <main class="appointment-hero">
        <div class="slide-overlay-gradient"></div>
        <img src="Images/OutsideClinic.png" alt="Clinic Background" class="appointment-bg-img">

        <div class="appointment-layout container">

            <div class="appointment-info">
                <span class="eyebrow-text">Verification Complete</span>
                <h1>Booking <span class="hero-highlight">verified!</span></h1>
                <p>Thank you, <strong><?php echo htmlspecialchars((string)$verifiedBooking["full_name"]); ?></strong>. Your appointment request has been verified and sent to Cebanico Dental Clinic.</p>

                <div class="appointment-hours-note">
                    <p><strong>Reference Number:</strong> <code><?php echo htmlspecialchars((string)$verifiedBooking["ref"]); ?></code></p>
                    <p><strong>Clinic Address:</strong> 0176 Manila E Rd, Binangonan, Rizal (Friday &ndash; Wednesday, 10:00 AM &ndash; 6:00 PM)</p>
                </div>
            </div>

            <div class="appointment-form-wrapper">
                <div class="appointment-form glass-card">
                    <div class="otp-notice-banner" role="status">
                        &#10003; Your booking <strong><?php echo htmlspecialchars((string)$verifiedBooking["ref"]); ?></strong> is now verified!
                    </div>

                    <ul class="appt-meta-list" style="margin: 16px 0 24px 0;">
                        <li>
                            <span class="meta-label">Reference No.</span>
                            <span class="meta-value"><strong><?php echo htmlspecialchars((string)$verifiedBooking["ref"]); ?></strong></span>
                        </li>
                        <li>
                            <span class="meta-label">Patient Name</span>
                            <span class="meta-value"><?php echo htmlspecialchars((string)$verifiedBooking["full_name"]); ?></span>
                        </li>
                        <li>
                            <span class="meta-label">Email</span>
                            <span class="meta-value"><?php echo htmlspecialchars((string)$verifiedBooking["email"]); ?></span>
                        </li>
                        <li>
                            <span class="meta-label">Contact Number</span>
                            <span class="meta-value"><?php echo htmlspecialchars((string)$verifiedBooking["contact_number"]); ?></span>
                        </li>
                        <li>
                            <span class="meta-label">Dental Service</span>
                            <span class="meta-value"><?php echo htmlspecialchars((string)$verifiedBooking["service"]); ?></span>
                        </li>
                        <li>
                            <span class="meta-label">Date</span>
                            <span class="meta-value"><?php echo htmlspecialchars((string)$verifiedBooking["pretty_date"]); ?> (<?php echo htmlspecialchars((string)$verifiedBooking["day_of_week"]); ?>)</span>
                        </li>
                        <?php if (!empty($verifiedBooking["appointment_time"])) { ?>
                        <li>
                            <span class="meta-label">Preferred Time</span>
                            <span class="meta-value"><?php echo htmlspecialchars((string)$verifiedBooking["appointment_time"]); ?></span>
                        </li>
                        <?php } ?>
                        <li>
                            <span class="meta-label">Status</span>
                            <span class="meta-value">Verified (<?php echo htmlspecialchars((string)$verifiedBooking["status"]); ?>)</span>
                        </li>
                    </ul>

                    <a href="my-appointments.php" class="btn btn-primary appointment-submit-btn">View My Appointments &rarr;</a>

                    <div class="verify-code-divider"><span>Additional options</span></div>

                    <div class="booking-secondary-actions">
                        <a href="appointment.php" class="btn btn-secondary verify-code-btn">Book Another Visit</a>
                        <a href="index.php" class="btn btn-secondary verify-code-btn">Return Home</a>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer id="contact">
        <div class="container reveal">
            <div class="footer-top">
                <div class="footer-col">
                    <h3>Get in Touch</h3>
                    <div class="footer-content">
                        <p>Have questions or ready to get started? Reach out to us. Your perfect smile is just a call away.</p>
                        <a href="appointment.php" style="color: var(--primary, #0ea5e9); font-weight: 600; display: inline-block; margin-top: 12px;">Book an Appointment &rarr;</a>
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

    <script src="index.js?v=39"></script>
</body>
</html>