<?php
session_start();
date_default_timezone_set("Asia/Manila");
require_once "db_connect.php";

// Handle Log Out
if ((isset($_GET["logout"]) && $_GET["logout"] === "1") || ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "logout")) {
    unset(
        $_SESSION["patient"],
        $_SESSION["pending_signup_otp"],
        $_SESSION["verified_booking"],
        $_SESSION["verification_complete"]
    );
    header("Location: signin.php");
    exit;
}

// Require active patient session
if (!isset($_SESSION["patient"]) || !is_array($_SESSION["patient"]) || empty($_SESSION["patient"]["email"])) {
    header("Location: signin.php");
    exit;
}

function tableExists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string(preg_replace("/[^a-zA-Z0-9_]/", "", $table));
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
    return false;
}

function ensurePatientsTable(mysqli $conn): bool
{
    if (tableExists($conn, "patients")) {
        return true;
    }
    $sql = "CREATE TABLE IF NOT EXISTS `patients` (
        `patient_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL,
        `email` varchar(150) NOT NULL,
        `contact_number` varchar(20) NOT NULL DEFAULT '',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`patient_id`),
        UNIQUE KEY `uq_patient_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return (bool)$conn->query($sql);
}

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

$patientSession = $_SESSION["patient"];
$patientEmail   = strtolower(trim((string)($patientSession["email"] ?? "")));
$patientName    = trim((string)($patientSession["username"] ?? ""));
$patientContact = trim((string)($patientSession["contact_number"] ?? ""));

// Load registered name & contact from `patients`
if (ensurePatientsTable($conn)) {
    $pStmt = $conn->prepare("SELECT username, contact_number FROM patients WHERE LOWER(email) = LOWER(?) LIMIT 1");
    if ($pStmt) {
        $pStmt->bind_param("s", $patientEmail);
        $pStmt->execute();
        $pRes = $pStmt->get_result();
        if ($pRow = $pRes->fetch_assoc()) {
            if (!empty($pRow["username"])) {
                $patientName = trim((string)$pRow["username"]);
                $_SESSION["patient"]["username"] = $patientName;
            }
            if ($patientContact === "" && !empty($pRow["contact_number"])) {
                $patientContact = trim((string)$pRow["contact_number"]);
                $_SESSION["patient"]["contact_number"] = $patientContact;
            }
        }
        $pStmt->close();
    }
}

// Fallback to earliest appointment_requests record if `patients` has no row yet
if ($patientName === "" && tableExists($conn, "appointment_requests")) {
    $rStmt = $conn->prepare("SELECT full_name, contact_number FROM appointment_requests WHERE LOWER(email) = LOWER(?) ORDER BY request_id ASC LIMIT 1");
    if ($rStmt) {
        $rStmt->bind_param("s", $patientEmail);
        $rStmt->execute();
        $rRes = $rStmt->get_result();
        if ($rRow = $rRes->fetch_assoc()) {
            if (!empty($rRow["full_name"])) {
                $patientName = trim((string)$rRow["full_name"]);
                $_SESSION["patient"]["username"] = $patientName;
            }
            if ($patientContact === "" && !empty($rRow["contact_number"])) {
                $patientContact = trim((string)$rRow["contact_number"]);
                $_SESSION["patient"]["contact_number"] = $patientContact;
            }
        }
        $rStmt->close();
    }
}

$successMsg = "";
$errorMsg   = "";

// Handle Full Name / Username & Contact Number Update (Only available while signed in)
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_profile") {
    $newName    = preg_replace("/\s+/", " ", trim($_POST["username"] ?? ""));
    $newContact = preg_replace("/[^0-9]/", "", trim($_POST["contact_number"] ?? ""));

    if ($newName === "" || !preg_match("/^[A-Za-z\s]{2,}$/", $newName)) {
        $errorMsg = "Please enter a valid full name / username (letters and spaces only, at least 2 characters).";
    } elseif (!preg_match("/^09[0-9]{9}$/", $newContact)) {
        $errorMsg = "Please enter a valid 11-digit Philippine mobile number starting with 09.";
    } else {
        $dbSaved = true;

        if (ensurePatientsTable($conn)) {
            $upsertStmt = $conn->prepare(
                "INSERT INTO patients (username, email, contact_number)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    username = VALUES(username),
                    contact_number = VALUES(contact_number)"
            );
            if ($upsertStmt) {
                $upsertStmt->bind_param("sss", $newName, $patientEmail, $newContact);
                if (!$upsertStmt->execute()) {
                    $dbSaved = false;
                }
                $upsertStmt->close();
            } else {
                $dbSaved = false;
            }
        } else {
            $dbSaved = false;
        }

        if (tableExists($conn, "appointment_requests")) {
            $updReq = $conn->prepare(
                "UPDATE appointment_requests
                 SET full_name = ?, contact_number = ?
                 WHERE LOWER(email) = LOWER(?)"
            );
            if ($updReq) {
                $updReq->bind_param("sss", $newName, $newContact, $patientEmail);
                $updReq->execute();
                $updReq->close();
            }
        }

        if ($dbSaved) {
            $_SESSION["patient"]["username"]       = $newName;
            $_SESSION["patient"]["contact_number"] = $newContact;
            $patientName                           = $newName;
            $patientContact                        = $newContact;
            $successMsg                            = "Your profile details have been updated and saved.";
        } else {
            $errorMsg = "Could not save your profile updates to the database. Please try again.";
        }
    }
}

// Fetch Appointments for this Account
$appointments = [];
if (tableExists($conn, "appointments") && tableExists($conn, "appointment_requests")) {
    $hasApptTime   = tableHasColumn($conn, "appointments", "appointment_time");
    $selectTimeCol = $hasApptTime ? ", a.appointment_time" : ", '' AS appointment_time";

    $listStmt = $conn->prepare(
        "SELECT a.appointment_id, a.appointment_date, a.day_of_week, a.service, a.status
                {$selectTimeCol}
         FROM appointments a
         INNER JOIN appointment_requests r ON a.request_id = r.request_id
         WHERE LOWER(r.email) = LOWER(?)
         ORDER BY a.appointment_date DESC, a.appointment_id DESC"
    );

    if ($listStmt) {
        $listStmt->bind_param("s", $patientEmail);
        $listStmt->execute();
        $res = $listStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $appointments[] = $row;
        }
        $listStmt->close();
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
    <title>My Profile | Cebanico Dental Clinic</title>

    <link rel="icon" type="image/png" href="Images/Faviconlogo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="styles.css?v=40">
    <link rel="stylesheet" href="appointment.css?v=40">
    <style>
        .profile-template-box {
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 16px;
            padding: 22px 26px;
            margin-top: 20px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .profile-template-box p {
            margin: 0 0 14px 0;
            font-size: 0.98rem;
            line-height: 1.55;
            color: #e2e8f0;
        }
        .profile-template-box p:last-child {
            margin-bottom: 0;
        }
        .profile-template-box .inline-sky-link {
            color: #38bdf8;
            text-decoration: underline;
            font-weight: 500;
        }
        .profile-template-box .inline-sky-link:hover {
            color: #7dd3fc;
        }
        .profile-view-appts-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 22px;
            padding: 13px 26px;
            border-radius: 999px;
            background: rgba(21, 55, 79, 0.85);
            border: 1px solid rgba(56, 189, 248, 0.55);
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(2, 132, 199, 0.22);
            transition: all 0.25s ease;
        }
        .profile-view-appts-pill:hover {
            background: rgba(14, 116, 144, 0.95);
            border-color: #38bdf8;
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(14, 165, 233, 0.35);
        }
        .profile-recent-appts {
            margin-top: 26px;
            background: rgba(15, 23, 42, 0.62);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 16px;
            padding: 18px 22px;
            max-height: 290px;
            overflow-y: auto;
        }
        .profile-recent-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .profile-recent-header h3 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: #f8fafc;
        }
        .profile-recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-top: 1px solid rgba(148, 163, 184, 0.14);
            font-size: 0.88rem;
            color: #cbd5e1;
        }
        .profile-recent-item:first-of-type {
            border-top: none;
            padding-top: 4px;
        }
        .profile-recent-item strong {
            color: #ffffff;
            display: block;
        }
        .editable-field-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
        }
        .editable-field-header label {
            margin-bottom: 0 !important;
        }
        .edit-name-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 999px;
            background: rgba(56, 189, 248, 0.14);
            border: 1px solid rgba(56, 189, 248, 0.45);
            color: #38bdf8;
            font-family: 'Inter', sans-serif;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .edit-name-toggle-btn:hover,
        .edit-name-toggle-btn.is-editing {
            background: rgba(56, 189, 248, 0.25);
            border-color: #38bdf8;
            color: #ffffff;
        }
        .locked-field-input {
            opacity: 0.8;
            cursor: not-allowed;
        }
        .locked-field-hint {
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 4px;
            display: block;
        }
        .btn-logout-outline {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 20px;
            border-radius: 12px;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(248, 113, 113, 0.45);
            color: #fca5a5;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .btn-logout-outline:hover {
            background: rgba(239, 68, 68, 0.24);
            border-color: #f87171;
            color: #ffffff;
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
                    <li><a href="appointment.php" class="nav-contact-btn">Book Appointment</a></li>
                </ul>

                <a href="profile.php"
                   class="nav-profile-btn active is-signed-in"
                   aria-label="My Profile"
                   title="My Profile">
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

            <!-- Left Column: Account Info Box + View My Appointments Button + Appointments List -->
            <div class="appointment-info">
                <span class="eyebrow-text">Patient Account</span>
                <h1>Your <span class="hero-highlight">profile</span></h1>

                <div class="profile-template-box">
                    <p>
                        Signed in as: <?php echo htmlspecialchars($patientName !== "" ? $patientName : "Patient"); ?>
                        (<?php echo htmlspecialchars($patientEmail); ?>) &mdash;
                        <a href="signin.php" class="inline-sky-link">Switch account</a>
                    </p>
                    <p>Open Mon, Tue, Wed, Sat, Sun: 10AM &ndash; 6PM</p>
                    <p>Friday: by appointment only</p>
                    <p>Thursday: closed</p>
                </div>

                <a href="my-appointments.php" class="profile-view-appts-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                        <line x1="8" y1="14" x2="8" y2="14"></line>
                        <line x1="12" y1="14" x2="12" y2="14"></line>
                        <line x1="16" y1="14" x2="16" y2="14"></line>
                        <line x1="8" y1="18" x2="8" y2="18"></line>
                        <line x1="12" y1="18" x2="12" y2="18"></line>
                    </svg>
                    <span>View My Appointments</span>
                </a>

                <?php if (!empty($appointments)) { ?>
                    <div class="profile-recent-appts">
                        <div class="profile-recent-header">
                            <h3>Your Appointments (<?php echo count($appointments); ?>)</h3>
                            <a href="my-appointments.php" class="inline-sky-link" style="font-size: 0.85rem;">Manage all &rarr;</a>
                        </div>
                        <?php foreach ($appointments as $appt) {
                            $refNo      = "CDC-" . str_pad((string)$appt["appointment_id"], 4, "0", STR_PAD_LEFT);
                            $dateObj    = DateTime::createFromFormat("Y-m-d", (string)$appt["appointment_date"]);
                            $prettyDate = $dateObj ? $dateObj->format("M j, Y") : (string)$appt["appointment_date"];
                            $timeStr    = trim((string)($appt["appointment_time"] ?? ""));
                            $statusSlug = strtolower(str_replace(" ", "-", (string)$appt["status"]));
                        ?>
                            <div class="profile-recent-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($refNo); ?> &bull; <?php echo htmlspecialchars((string)$appt["service"]); ?></strong>
                                    <span><?php echo htmlspecialchars($prettyDate); ?><?php echo $timeStr !== "" ? " at " . htmlspecialchars($timeStr) : ""; ?></span>
                                </div>
                                <span class="appt-status-pill status-<?php echo htmlspecialchars($statusSlug); ?>">
                                    <?php echo htmlspecialchars((string)$appt["status"]); ?>
                                </span>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <!-- Right Column: Edit Full Name / Username, Contact Number & Log Out -->
            <div class="appointment-form-wrapper">
                <div class="appointment-form glass-card">

                    <?php if ($successMsg !== "") { ?>
                        <div class="otp-notice-banner" role="status">
                            &#10003; <?php echo htmlspecialchars($successMsg); ?>
                        </div>
                    <?php } ?>

                    <?php if ($errorMsg !== "") { ?>
                        <div class="verify-error-banner" role="alert">
                            <div><?php echo htmlspecialchars($errorMsg); ?></div>
                        </div>
                    <?php } ?>

                    <form id="editProfileForm" action="profile.php" method="POST" autocomplete="off" novalidate>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <div class="editable-field-header">
                                <label for="username">Full Name / Username</label>
                                <button type="button" id="toggleEditNameBtn" class="edit-name-toggle-btn" aria-pressed="false">
                                    &#9998; Edit Name
                                </button>
                            </div>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="locked-field-input"
                                placeholder="Juan Dela Cruz"
                                value="<?php echo htmlspecialchars($patientName); ?>"
                                readonly
                                required
                            >
                            <span id="usernameFieldHint" class="locked-field-hint">Click "Edit Name" to update your saved full name / username.</span>
                        </div>

                        <div class="form-group">
                            <label for="email">Verified Email Address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="locked-field-input"
                                value="<?php echo htmlspecialchars($patientEmail); ?>"
                                readonly
                                aria-readonly="true"
                            >
                        </div>

                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input
                                type="tel"
                                id="contact_number"
                                name="contact_number"
                                placeholder="09171234567"
                                value="<?php echo htmlspecialchars($patientContact); ?>"
                                inputmode="numeric"
                                maxlength="11"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary appointment-submit-btn">Save Profile Changes &rarr;</button>
                    </form>

                    <div class="verify-code-divider"><span>Session &amp; Quick Actions</span></div>

                    <div class="booking-secondary-actions" style="margin-bottom: 12px;">
                        <a href="appointment.php" class="btn btn-secondary verify-code-btn">Book Appointment</a>
                        <a href="my-appointments.php" class="btn btn-secondary verify-code-btn">My Appointments</a>
                    </div>

                    <form action="profile.php" method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn-logout-outline">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Log Out</span>
                        </button>
                    </form>

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

    <button id="backToTopBtn" aria-label="Back to Top" onclick="scrollToTop()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>

    <script src="index.js?v=40"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.getElementById('editProfileForm');
            const usernameInput = document.getElementById('username');
            const toggleEditNameBtn = document.getElementById('toggleEditNameBtn');
            const usernameFieldHint = document.getElementById('usernameFieldHint');
            const contactInput = document.getElementById('contact_number');

            const popup = document.getElementById('restrictionPopup');
            const popupTitle = document.getElementById('restrictionPopupTitle');
            const popupMsg = document.getElementById('restrictionPopupMsg');
            const closePopupBtn = document.getElementById('closeRestrictionPopup');
            let popupTimeout;

            function showRestrictionPopup(message, targetEl = null, title = "Input Restriction") {
                if (!popup || !popupMsg || !popupTitle) return;
                popupTitle.textContent = title;
                popupMsg.textContent = message;
                popup.classList.add('show');

                if (targetEl) {
                    targetEl.classList.remove('input-error');
                    void targetEl.offsetWidth;
                    targetEl.classList.add('input-error');
                    setTimeout(() => targetEl.classList.remove('input-error'), 1500);
                }

                clearTimeout(popupTimeout);
                popupTimeout = setTimeout(() => popup.classList.remove('show'), 4000);
            }

            if (closePopupBtn && popup) {
                closePopupBtn.addEventListener('click', () => {
                    popup.classList.remove('show');
                    clearTimeout(popupTimeout);
                });
            }

            if (toggleEditNameBtn && usernameInput) {
                const originalName = usernameInput.value;
                toggleEditNameBtn.addEventListener('click', () => {
                    const isCurrentlyLocked = usernameInput.hasAttribute('readonly');
                    if (isCurrentlyLocked) {
                        usernameInput.removeAttribute('readonly');
                        usernameInput.classList.remove('locked-field-input');
                        toggleEditNameBtn.classList.add('is-editing');
                        toggleEditNameBtn.setAttribute('aria-pressed', 'true');
                        toggleEditNameBtn.textContent = 'Cancel Edit';
                        if (usernameFieldHint) {
                            usernameFieldHint.textContent = 'Enter your updated full name / username and click "Save Profile Changes".';
                        }
                        usernameInput.focus();
                        usernameInput.setSelectionRange(usernameInput.value.length, usernameInput.value.length);
                    } else {
                        usernameInput.value = originalName;
                        usernameInput.setAttribute('readonly', 'readonly');
                        usernameInput.classList.add('locked-field-input');
                        toggleEditNameBtn.classList.remove('is-editing');
                        toggleEditNameBtn.setAttribute('aria-pressed', 'false');
                        toggleEditNameBtn.innerHTML = '&#9998; Edit Name';
                        if (usernameFieldHint) {
                            usernameFieldHint.textContent = 'Click "Edit Name" to update your saved full name / username.';
                        }
                    }
                });

                usernameInput.addEventListener('input', function () {
                    if (this.hasAttribute('readonly')) return;
                    const raw = this.value;
                    if (/[0-9]/.test(raw)) {
                        showRestrictionPopup("Numbers are not allowed in your name.", this, "Name Restriction");
                    } else if (/[^A-Za-z\s]/.test(raw)) {
                        showRestrictionPopup("Special characters are not allowed in your name.", this, "Name Restriction");
                    }
                    this.value = raw.replace(/[^A-Za-z\s]/g, '');
                });
            }

            if (contactInput) {
                contactInput.addEventListener('input', function () {
                    let digitsOnly = this.value.replace(/[^0-9]/g, '');
                    if (digitsOnly.length === 12 && digitsOnly.startsWith('639')) {
                        digitsOnly = '0' + digitsOnly.slice(2);
                    }
                    if (digitsOnly.length >= 1 && digitsOnly.charAt(0) !== '0') {
                        showRestrictionPopup("Philippine mobile numbers must start with 09.", this, "Contact Restriction");
                        digitsOnly = '';
                    } else if (digitsOnly.length >= 2 && digitsOnly.substring(0, 2) !== '09') {
                        showRestrictionPopup("Philippine mobile numbers must start with 09.", this, "Contact Restriction");
                        digitsOnly = '0';
                    }
                    if (digitsOnly.length > 11) {
                        digitsOnly = digitsOnly.slice(0, 11);
                    }
                    this.value = digitsOnly;
                });
            }

            if (form) {
                form.addEventListener('submit', (e) => {
                    const cleanName = usernameInput.value.replace(/\s+/g, ' ').trim();
                    if (cleanName.length < 2 || !/^[A-Za-z\s]{2,}$/.test(cleanName)) {
                        e.preventDefault();
                        usernameInput.removeAttribute('readonly');
                        usernameInput.classList.remove('locked-field-input');
                        usernameInput.focus();
                        showRestrictionPopup("Please enter a valid full name / username (letters and spaces only, at least 2 characters).", usernameInput, "Invalid Name");
                        return;
                    }

                    if (!/^09[0-9]{9}$/.test(contactInput.value)) {
                        e.preventDefault();
                        contactInput.focus();
                        showRestrictionPopup("Please enter an 11-digit Philippine mobile number starting with 09.", contactInput, "Invalid Contact Number");
                    }
                });
            }
        });
    </script>
</body>
</html>