<?php
session_start();
date_default_timezone_set("Asia/Manila");
require_once "db_connect.php";

// Hook for booking appointment verification
$webhookUrl = "https://hook.us2.make.com/ch21y91yn7oblt5jo2d2y09qulq4bn46";

function tableHasColumn(mysqli $conn, string $table, string $column): bool
{
    $safeTable  = preg_replace("/[^a-zA-Z0-9_]/", "", $table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
    return false;
}

function sendVerificationCompleteWebhook(string $webhookUrl, array $payload): void
{
    if (empty($webhookUrl)) {
        return;
    }

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    curl_exec($ch);
    curl_close($ch);
}

$hasApptTime = tableHasColumn($conn, "appointments", "appointment_time");
$timeSelect  = $hasApptTime ? ", a.appointment_time" : ", '' AS appointment_time";

$refInput  = strtoupper(trim($_GET["ref"] ?? $_POST["reference_number"] ?? ""));
$codeInput = trim($_POST["verification_code"] ?? "");
$justSent  = isset($_GET["sent"]) && $_GET["sent"] === "1";
$errors    = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $numericId = (int)preg_replace("/[^0-9]/", "", $refInput);

    if (!preg_match("/^\d{6}$/", $codeInput)) {
        $errors[] = "Please enter the 6-digit verification code sent to your email.";
    }

    if (empty($errors)) {
        if ($numericId > 0) {
            $stmt = $conn->prepare(
                "SELECT a.appointment_id, a.request_id, a.appointment_date, a.day_of_week,
                        a.service, a.verification_code, a.is_verified, a.status
                        {$timeSelect},
                        r.full_name, r.email, r.contact_number
                 FROM appointments a
                 INNER JOIN appointment_requests r ON a.request_id = r.request_id
                 WHERE a.appointment_id = ?
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param("i", $numericId);
            }
        } else {
            $stmt = $conn->prepare(
                "SELECT a.appointment_id, a.request_id, a.appointment_date, a.day_of_week,
                        a.service, a.verification_code, a.is_verified, a.status
                        {$timeSelect},
                        r.full_name, r.email, r.contact_number
                 FROM appointments a
                 INNER JOIN appointment_requests r ON a.request_id = r.request_id
                 WHERE a.verification_code = ? AND a.status <> 'Cancelled'
                 ORDER BY a.appointment_id DESC
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param("s", $codeInput);
            }
        }

        if (!$stmt) {
            $errors[] = "Database error: " . $conn->error;
        } else {
            $stmt->execute();
            $res  = $stmt->get_result();
            $appt = $res->fetch_assoc();
            $stmt->close();

            if (!$appt) {
                $errors[] = $numericId > 0
                    ? "No appointment found with reference number " . htmlspecialchars($refInput) . "."
                    : "No appointment found matching that 6-digit verification code.";
            } elseif ((string)$appt["status"] === "Cancelled") {
                $errors[] = "This appointment has already been cancelled.";
            } elseif (!hash_equals((string)$appt["verification_code"], $codeInput)) {
                $errors[] = "Incorrect verification code. Please check your email and try again.";
            } else {
                $verifiedApptId = (int)$appt["appointment_id"];

                $upd = $conn->prepare(
                    "UPDATE appointments
                     SET is_verified = 1
                     WHERE appointment_id = ?"
                );
                $upd->bind_param("i", $verifiedApptId);
                $upd->execute();
                $upd->close();

                $refFormatted = "CDC-" . str_pad((string)$verifiedApptId, 4, "0", STR_PAD_LEFT);
                $dateObj      = DateTime::createFromFormat("Y-m-d", $appt["appointment_date"]);
                $prettyDate   = $dateObj ? $dateObj->format("F j, Y") : $appt["appointment_date"];
                $apptTime     = trim((string)($appt["appointment_time"] ?? ""));

                sendVerificationCompleteWebhook($webhookUrl, [
                    "event_type"        => "appointment_verified",
                    "clinic_name"       => "Cebanico Dental Clinic",
                    "reference_number"  => $refFormatted,
                    "appointment_id"    => $verifiedApptId,
                    "request_id"        => (int)$appt["request_id"],
                    "full_name"         => $appt["full_name"],
                    "email"             => $appt["email"],
                    "to_email"          => $appt["email"],
                    "contact_number"    => $appt["contact_number"],
                    "service"           => $appt["service"],
                    "appointment_date"  => $appt["appointment_date"],
                    "appointment_time"  => $apptTime,
                    "pretty_date"       => $prettyDate,
                    "day_of_week"       => $appt["day_of_week"],
                    "verification_code" => $codeInput,
                    "is_verified"       => 1,
                    "status"            => $appt["status"]
                ]);

                if (!isset($_SESSION["patient"]) || !is_array($_SESSION["patient"]) || empty($_SESSION["patient"]["email"])) {
                    $_SESSION["patient"] = [
                        "username"       => $appt["full_name"],
                        "email"          => strtolower(trim((string)$appt["email"])),
                        "contact_number" => $appt["contact_number"],
                        "verified_at"    => time()
                    ];
                }

                $_SESSION["verified_booking"] = [
                    "ref"              => $refFormatted,
                    "appointment_id"   => $verifiedApptId,
                    "full_name"        => $appt["full_name"],
                    "email"            => $appt["email"],
                    "contact_number"   => $appt["contact_number"],
                    "service"          => $appt["service"],
                    "appointment_date" => $appt["appointment_date"],
                    "pretty_date"      => $prettyDate,
                    "appointment_time" => $apptTime,
                    "day_of_week"      => $appt["day_of_week"],
                    "status"           => $appt["status"]
                ];

                $conn->close();
                header("Location: booking-verified.php?ref=" . urlencode($refFormatted));
                exit;
            }
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Booking Code | Cebanico Dental Clinic</title>

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
                <span class="eyebrow-text">Booking Verification</span>
                <h1>Verify your <span class="hero-highlight">booking</span></h1>
                <p>Enter your booking reference number and the 6-digit verification code sent to your email address.</p>

                <div class="appointment-hours-note">
                    <p><strong>Reference Format:</strong> Example <code>CDC-0001</code></p>
                    <p><strong>Need to reschedule?</strong> You can view, update, or cancel your bookings anytime under <strong>My Appointments</strong>.</p>
                </div>
            </div>

            <div class="appointment-form-wrapper">
                <form class="appointment-form glass-card" action="verify-code.php" method="POST" autocomplete="off" novalidate>

                    <?php if ($justSent && empty($errors)) { ?>
                        <div class="otp-notice-banner" role="status">
                            Your appointment request was submitted! Check your email for your 6-digit verification code.
                        </div>
                    <?php } ?>

                    <?php if (!empty($errors)) { ?>
                        <div class="verify-error-banner" role="alert">
                            <?php foreach ($errors as $err) { ?>
                                <div><?php echo htmlspecialchars($err); ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="form-group">
                        <label for="reference_number">Booking Reference Number</label>
                        <input
                            type="text"
                            id="reference_number"
                            name="reference_number"
                            placeholder="CDC-0001"
                            value="<?php echo htmlspecialchars($refInput); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="verification_code">6-Digit Verification Code</label>
                        <input
                            type="text"
                            id="verification_code"
                            name="verification_code"
                            placeholder="123456"
                            maxlength="6"
                            inputmode="numeric"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary appointment-submit-btn">Confirm Verification &rarr;</button>

                    <div class="verify-code-divider"><span>Quick navigation</span></div>

                    <div class="booking-secondary-actions">
                        <a href="my-appointments.php" class="btn btn-secondary verify-code-btn">View My Appointments</a>
                        <a href="appointment.php" class="btn btn-secondary verify-code-btn">Back to Booking</a>
                    </div>

                </form>
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
    <script>
        const codeField = document.getElementById('verification_code');
        if (codeField) {
            codeField.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }
    </script>
</body>
</html>