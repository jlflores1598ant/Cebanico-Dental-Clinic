<?php
session_start();
date_default_timezone_set("Asia/Manila");
require_once "db_connect.php";

$refInput  = strtoupper(trim($_GET["ref"] ?? $_POST["referenceNumber"] ?? ""));
$codeInput = trim($_POST["verificationCode"] ?? "");
$errorMsg  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Extract numeric appointment_id from CDC-0001 (also accepts plain digits)
    if (!preg_match("/^(?:CDC-)?(\d+)$/i", $refInput, $matches)) {
        $errorMsg = "Please enter a valid Reference No. (e.g., CDC-0001).";
    } elseif (!preg_match("/^\d{6}$/", $codeInput)) {
        $errorMsg = "Please enter the 6-digit verification code sent to your email.";
    } else {
        $appointmentId = (int)$matches[1];
        $formattedRef  = "CDC-" . str_pad((string)$appointmentId, 4, "0", STR_PAD_LEFT);
        $refInput      = $formattedRef;

        $stmt = $conn->prepare(
            "SELECT a.appointment_id, a.appointment_date, a.service, a.status,
                    a.verification_code, a.is_verified, r.full_name
             FROM appointments a
             INNER JOIN appointment_requests r ON a.request_id = r.request_id
             WHERE a.appointment_id = ?
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("i", $appointmentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row    = $result->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $errorMsg = "No appointment found for Reference No. " . htmlspecialchars($formattedRef) . ".";
            } elseif (!empty($row["verification_code"]) && hash_equals((string)$row["verification_code"], $codeInput)) {
                // Mark appointment as verified in MySQL
                if ((int)$row["is_verified"] === 0) {
                    $upd = $conn->prepare("UPDATE appointments SET is_verified = 1 WHERE appointment_id = ?");
                    if ($upd) {
                        $upd->bind_param("i", $appointmentId);
                        $upd->execute();
                        $upd->close();
                    }
                }

                $d = DateTime::createFromFormat("Y-m-d", $row["appointment_date"]);
                $prettyDate = $d ? $d->format("l, F j, Y") : $row["appointment_date"];

                // Store verified details for the pop-up on appointment.php
                $_SESSION["verification_complete"] = [
                    "ref"       => $formattedRef,
                    "full_name" => $row["full_name"],
                    "service"   => $row["service"],
                    "date"      => $prettyDate
                ];

                if (isset($conn) && $conn instanceof mysqli) {
                    $conn->close();
                }

                // Redirect back to appointment.php to show the Verification Complete pop-up
                header("Location: appointment.php?verified=1");
                exit;
            } else {
                $errorMsg = "The verification code does not match Reference No. " . htmlspecialchars($formattedRef) . ".";
            }
        } else {
            $errorMsg = "Database error: " . $conn->error;
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
    <title>Verify Code | Cebanico Dental Clinic</title>

    <link rel="icon" type="image/png" href="Images/Faviconlogo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="styles.css?v=32">
    <link rel="stylesheet" href="appointment.css?v=32">
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

    <main class="appointment-hero">
        <div class="slide-overlay-gradient"></div>
        <img src="Images/OutsideClinic.png" alt="Clinic Background" class="appointment-bg-img">

        <div class="appointment-layout container">

            <!-- Left Info Content -->
            <div class="appointment-info">
                <span class="eyebrow-text">Email Verification</span>
                <h1>Verify your <span class="hero-highlight">booking</span></h1>
                <p>Enter the Reference No. and 6-digit verification code sent to your email address to confirm your appointment request.</p>

                <div class="appointment-hours-note">
                    <p><strong>Where to find your code:</strong> Check your inbox (and spam folder) for an email from Cebanico Dental Clinic.</p>
                    <p><strong>Need a new booking?</strong> Return to the appointment form to schedule a visit.</p>
                </div>
            </div>

            <!-- Right Verification Form -->
            <div class="appointment-form-wrapper">
                <form class="appointment-form glass-card" action="verify-code.php" method="POST">

                    <?php if ($errorMsg !== "") { ?>
                        <div class="verify-error-banner" role="alert">
                            <?php echo htmlspecialchars($errorMsg); ?>
                        </div>
                    <?php } ?>

                    <div class="form-group">
                        <label for="referenceNumber">Reference No.</label>
                        <input
                            type="text"
                            id="referenceNumber"
                            name="referenceNumber"
                            placeholder="CDC-0001"
                            value="<?php echo htmlspecialchars($refInput); ?>"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="verificationCode">6-Digit Verification Code</label>
                        <input
                            type="text"
                            id="verificationCode"
                            name="verificationCode"
                            placeholder="482910"
                            maxlength="6"
                            pattern="\d{6}"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary appointment-submit-btn">Verify Code</button>

                    <div class="verify-code-divider"><span>or</span></div>

                    <a href="appointment.php" class="btn btn-secondary verify-code-btn">Back to Appointment Page</a>

                </form>
            </div>

        </div>
    </main>

    <button id="backToTopBtn" aria-label="Back to Top" onclick="scrollToTop()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>

    <script src="index.js?v=32"></script>
    <script>
        // Keep verification code input strictly 6 numeric digits
        const codeField = document.getElementById('verificationCode');
        if (codeField) {
            codeField.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }
    </script>
</body>
</html>