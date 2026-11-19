<?php
require_once "db_connect.php";

// Only accept form submissions
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: appointment.php");
    exit;
}

// ---------- 1. Collect and clean the input ----------
$fullName = trim($_POST["fullName"]        ?? "");
$email    = trim($_POST["email"]           ?? "");
$contact  = trim($_POST["contactNumber"]   ?? "");
$service  = trim($_POST["service"]         ?? "");
$apptDate = trim($_POST["appointmentDate"] ?? "");
$consent  = isset($_POST["consent"]) ? 1 : 0;

// ---------- 2. Server-side validation ----------
$errors = [];

$allowedServices = [
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

if ($fullName === "" || !preg_match("/^[A-Za-z\s]+$/", $fullName)) {
    $errors[] = "Please enter a valid full name (letters and spaces only).";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
}
if (!preg_match("/^[0-9]{7,15}$/", $contact)) {
    $errors[] = "Please enter a valid contact number (numbers only).";
}
if (!in_array($service, $allowedServices, true)) {
    $errors[] = "Please select a valid service.";
}
if ($consent !== 1) {
    $errors[] = "You must agree to the consent statement.";
}

$dateObj = DateTime::createFromFormat("Y-m-d", $apptDate);
if (!$dateObj || $dateObj->format("Y-m-d") !== $apptDate) {
    $errors[] = "Please choose a valid appointment date.";
} else {
    if ($dateObj < new DateTime("today")) {
        $errors[] = "Appointment date cannot be in the past.";
    }
    // Clinic is closed on Thursdays (N = 4)
    if ($dateObj->format("N") == 4) {
        $errors[] = "The clinic is closed on Thursdays. Please choose another day.";
    }
}

// ---------- 3. Save to BOTH tables (all-or-nothing) ----------
$success       = false;
$appointmentId = 0;
$dayOfWeek     = "";

if (empty($errors)) {
    $dayOfWeek = $dateObj->format("l");

    try {
        $conn->begin_transaction();

        // TABLE 1: everything the user typed in
        $stmt1 = $conn->prepare(
            "INSERT INTO appointment_requests
                (full_name, email, contact_number, service, appointment_date, consent)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt1->bind_param("sssssi", $fullName, $email, $contact, $service, $apptDate, $consent);
        $stmt1->execute();
        $requestId = $conn->insert_id;
        $stmt1->close();

        // TABLE 2: arranged per appointment, linked using request_id
        $stmt2 = $conn->prepare(
            "INSERT INTO appointments
                (request_id, appointment_date, day_of_week, service, status)
             VALUES (?, ?, ?, ?, 'Pending')"
        );
        $stmt2->bind_param("isss", $requestId, $apptDate, $dayOfWeek, $service);
        $stmt2->execute();
        $appointmentId = $conn->insert_id;
        $stmt2->close();

        $conn->commit();
        $success = true;

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log("Appointment save failed: " . $e->getMessage());
        $errors[] = "Something went wrong while saving your request. Please try again.";
    }
}

$conn->close();

// Display helpers
$prettyDate = $success ? $dateObj->format("l, F j, Y") : "";
$refNumber  = $success ? "CDC-" . str_pad((string)$appointmentId, 4, "0", STR_PAD_LEFT) : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? "Request Received" : "Request Not Sent"; ?> | Cebanico Dental Clinic</title>

    <link rel="icon" type="image/png" href="Images/Faviconlogo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <script>
        // Prevent flash of wrong theme
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="styles.css?v=21">
    <link rel="stylesheet" href="appointment.css?v=21">
    <link rel="stylesheet" href="process-appointment.css?v=1">
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

        <?php if ($success) { ?>

            <!-- ================= SUCCESS ================= -->
            <div class="result-card is-success">

                <div class="confetti" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span>
                </div>

                <div class="result-icon success">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path class="draw" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>

                <span class="status-badge success fade-up delay-1">Request Received</span>

                <h1 class="fade-up delay-2">
                    Thank you, <span class="hero-highlight"><?php echo htmlspecialchars($fullName); ?></span>!
                </h1>

                <p class="result-sub fade-up delay-3">
                    We have received your appointment request. We will confirm your slot by call or email.
                </p>

                <ul class="details-list">
                    <li>
                        <span class="label">Reference No.</span>
                        <span class="value ref"><?php echo htmlspecialchars($refNumber); ?></span>
                    </li>
                    <li>
                        <span class="label">Service</span>
                        <span class="value"><?php echo htmlspecialchars($service); ?></span>
                    </li>
                    <li>
                        <span class="label">Date</span>
                        <span class="value"><?php echo htmlspecialchars($prettyDate); ?></span>
                    </li>
                    <li>
                        <span class="label">Status</span>
                        <span class="value pending">Pending confirmation</span>
                    </li>
                </ul>

                <div class="result-actions fade-up delay-6">
                    <a href="index.php" class="btn btn-primary">Back to Home</a>
                    <a href="appointment.php" class="btn btn-secondary">Book Another</a>
                </div>
            </div>

        <?php } else { ?>

            <!-- ================= ERROR ================= -->
            <div class="result-card is-error">

                <div class="result-icon error">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path class="draw"   d="M6 6l12 12"></path>
                        <path class="draw-2" d="M18 6L6 18"></path>
                    </svg>
                </div>

                <span class="status-badge error fade-up delay-1">Request Not Sent</span>

                <h1 class="fade-up delay-2">We could not process your request</h1>

                <p class="result-sub fade-up delay-3">
                    Please check the details below and try again.
                </p>

                <ul class="error-list">
                    <?php foreach ($errors as $err) { ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php } ?>
                </ul>

                <div class="result-actions fade-up delay-6">
                    <a href="appointment.php" class="btn btn-danger">Go Back to the Form</a>
                    <a href="index.php" class="btn btn-secondary">Back to Home</a>
                </div>
            </div>

        <?php } ?>

        </div>
    </main>

    <script src="index.js?v=21"></script>
</body>
</html>