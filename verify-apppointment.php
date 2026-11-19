<?php
date_default_timezone_set("Asia/Manila");
require_once "db_connect.php";

$refInput   = strtoupper(trim($_GET["ref"] ?? $_POST["referenceNumber"] ?? ""));
$codeInput  = trim($_POST["verificationCode"] ?? "");
$verified   = false;
$errorMsg   = "";
$apptRecord = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Extract numeric appointment_id from CDC-0001
    if (!preg_match("/^(?:CDC-)?(\d+)$/i", $refInput, $matches)) {
        $errorMsg = "Please enter a valid Reference No. (e.g., CDC-0001).";
    } elseif (!preg_match("/^\d{6}$/", $codeInput)) {
        $errorMsg = "Please enter the 6-digit verification code sent to your email.";
    } else {
        $appointmentId = (int)$matches[1];
        $refInput      = "CDC-" . str_pad((string)$appointmentId, 4, "0", STR_PAD_LEFT);

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
                $errorMsg = "No appointment found for Reference No. " . htmlspecialchars($refInput) . ".";
            } elseif (!empty($row["verification_code"]) && hash_equals((string)$row["verification_code"], $codeInput)) {
                // Match found! Mark as verified in database
                if ((int)$row["is_verified"] === 0) {
                    $upd = $conn->prepare("UPDATE appointments SET is_verified = 1 WHERE appointment_id = ?");
                    if ($upd) {
                        $upd->bind_param("i", $appointmentId);
                        $upd->execute();
                        $upd->close();
                    }
                }
                $verified   = true;
                $apptRecord = $row;
            } else {
                $errorMsg = "The verification code does not match Reference No. " . htmlspecialchars($refInput) . ".";
            }
        } else {
            $errorMsg = "Database error: " . $conn->error;
        }
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

$prettyDate = "";
if ($verified && !empty($apptRecord["appointment_date"])) {
    $d = DateTime::createFromFormat("Y-m-d", $apptRecord["appointment_date"]);
    $prettyDate = $d ? $d->format("l, F j, Y") : $apptRecord["appointment_date"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Appointment | Cebanico Dental Clinic</title>

    <link rel="icon" type="image/png" href="Images/Faviconlogo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="styles.css?v=25">
    <link rel="stylesheet" href="appointment.css?v=25">
    <link rel="stylesheet" href="process-appointment.css?v=2">
    <style>
        .verify-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            text-align: left;
            margin: 1.5rem 0;
        }
        .verify-form label {
            font-size: 0.9rem;
            font-weight: 600;
            display: block;
            margin-bottom: 0.35rem;
        }
        .verify-form input {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: 10px;
            border: 1px solid rgba(128, 128, 128, 0.35);
            background: rgba(255, 255, 255, 0.08);
            color: inherit;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            letter-spacing: 0.05em;
            box-sizing: border-box;
        }
        .verify-alert {
            background: rgba(220, 38, 38, 0.14);
            border: 1px solid rgba(220, 38, 38, 0.45);
            color: #ef4444;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
    </style>
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

    <main class="appointment-hero result-hero">
        <div class="slide-overlay-gradient"></div>
        <img src="Images/OutsideClinic.png" alt="Clinic Background" class="appointment-bg-img">
        <div class="result-glow"></div>

        <div class="result-layout">

        <?php if ($verified && $apptRecord) { ?>

            <!-- ================= VERIFIED ================= -->
            <div class="result-card is-success">
                <div class="result-icon success">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path class="draw" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>

                <span class="status-badge success">Email Verified</span>
                <h1>Appointment Verified!</h1>
                <p class="result-sub">
                    Thank you, <strong><?php echo htmlspecialchars($apptRecord["full_name"]); ?></strong>. Your verification code matched Reference No. <strong><?php echo htmlspecialchars($refInput); ?></strong>.
                </p>

                <ul class="details-list">
                    <li>
                        <span class="label">Reference No.</span>
                        <span class="value ref"><?php echo htmlspecialchars($refInput); ?></span>
                    </li>
                    <li>
                        <span class="label">Service</span>
                        <span class="value"><?php echo htmlspecialchars($apptRecord["service"]); ?></span>
                    </li>
                    <li>
                        <span class="label">Date</span>
                        <span class="value"><?php echo htmlspecialchars($prettyDate); ?></span>
                    </li>
                    <li>
                        <span class="label">Verification</span>
                        <span class="value">Verified (Pending clinic confirmation)</span>
                    </li>
                </ul>

                <div class="result-actions">
                    <a href="index.php" class="btn btn-primary">Back to Home</a>
                    <a href="appointment.php" class="btn btn-secondary">Book Another</a>
                </div>
            </div>

        <?php } else { ?>

            <!-- ================= VERIFY FORM ================= -->
            <div class="result-card">
                <span class="status-badge">Email Verification</span>
                <h1>Verify Your Appointment</h1>
                <p class="result-sub">
                    Enter your Reference No. and the 6-digit verification code sent to your email.
                </p>

                <?php if ($errorMsg !== "") { ?>
                    <div class="verify-alert"><?php echo htmlspecialchars($errorMsg); ?></div>
                <?php } ?>

                <form method="POST" action="verify-appointment.php" class="verify-form">
                    <div>
                        <label for="referenceNumber">Reference No.</label>
                        <input
                            type="text"
                            id="referenceNumber"
                            name="referenceNumber"
                            placeholder="e.g., CDC-0001"
                            value="<?php echo htmlspecialchars($refInput); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label for="verificationCode">6-Digit Verification Code</label>
                        <input
                            type="text"
                            id="verificationCode"
                            name="verificationCode"
                            placeholder="e.g., 482910"
                            maxlength="6"
                            pattern="\d{6}"
                            inputmode="numeric"
                            required
                        >
                    </div>

                    <div class="result-actions">
                        <button type="submit" class="btn btn-primary">Verify Appointment</button>
                        <a href="index.php" class="btn btn-secondary">Back to Home</a>
                    </div>
                </form>
            </div>

        <?php } ?>

        </div>
    </main>

    <script src="index.js?v=25"></script>

</body>
</html>