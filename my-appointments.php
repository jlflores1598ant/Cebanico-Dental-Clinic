<?php
session_start();
date_default_timezone_set("Asia/Manila");
require_once "db_connect.php";

// Require active patient session
if (!isset($_SESSION["patient"]) || !is_array($_SESSION["patient"]) || empty($_SESSION["patient"]["email"])) {
    header("Location: signin.php");
    exit;
}

$patientSession = $_SESSION["patient"];
$patientEmail   = strtolower(trim((string)$patientSession["email"]));
$webhookUrl     = "https://hook.us2.make.com/ch21y91yn7oblt5jo2d2y09qulq4bn46";

// Helper to safely check optional columns across database versions
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

$hasApptTimeInAppts = tableHasColumn($conn, "appointments", "appointment_time");
$hasApptTimeInReqs  = tableHasColumn($conn, "appointment_requests", "appointment_time");
$hasFullNameInAppts = tableHasColumn($conn, "appointments", "full_name");
$hasIsVerified      = tableHasColumn($conn, "appointments", "is_verified");

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

$allowedTimeSlots = [
    "10:00 AM",
    "11:00 AM",
    "12:00 PM",
    "1:00 PM",
    "2:00 PM",
    "3:00 PM",
    "4:00 PM",
    "5:00 PM"
];

$successMsg = "";
$errorMsg   = "";

// Helper to send webhook notifications when an appointment is updated or cancelled
function triggerAppointmentWebhook(string $webhookUrl, array $payload): void
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
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ==========================================================
// HANDLE POST ACTIONS: UPDATE OR CANCEL APPOINTMENT
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action        = trim($_POST["action"] ?? "");
    $appointmentId = (int)($_POST["appointment_id"] ?? 0);

    if ($appointmentId <= 0) {
        $errorMsg = "Invalid appointment selected.";
    } else {
        $ownerStmt = $conn->prepare(
            "SELECT a.appointment_id, a.request_id, a.appointment_date, a.service, a.status, a.remarks,
                    r.full_name, r.email, r.contact_number
             FROM appointments a
             INNER JOIN appointment_requests r ON a.request_id = r.request_id
             WHERE a.appointment_id = ? AND LOWER(r.email) = LOWER(?)
             LIMIT 1"
        );

        if (!$ownerStmt) {
            $errorMsg = "Database error: " . $conn->error;
        } else {
            $ownerStmt->bind_param("is", $appointmentId, $patientEmail);
            $ownerStmt->execute();
            $ownerRes = $ownerStmt->get_result();
            $targetAppt = $ownerRes->fetch_assoc();
            $ownerStmt->close();

            if (!$targetAppt) {
                $errorMsg = "Appointment not found or you do not have permission to modify it.";
            } elseif (!in_array($targetAppt["status"], ["Pending", "Confirmed"], true)) {
                $errorMsg = "Only Pending or Confirmed appointments can be updated or cancelled.";
            } elseif ($action === "update_appointment") {
                $newService = trim($_POST["service"] ?? "");
                $newDate    = trim($_POST["appointmentDate"] ?? "");
                $newTime    = trim($_POST["appointmentTime"] ?? "");
                $newContact = preg_replace("/[^0-9]/", "", trim($_POST["contactNumber"] ?? ""));

                $validationErrors = [];

                if (!in_array($newService, $allowedServices, true)) {
                    $validationErrors[] = "Please select a valid dental service.";
                }

                if (!preg_match("/^09[0-9]{9}$/", $newContact)) {
                    $validationErrors[] = "Please enter a valid 11-digit Philippine mobile number starting with 09.";
                }

                if ($hasApptTimeInAppts && $newTime !== "" && !in_array($newTime, $allowedTimeSlots, true)) {
                    $validationErrors[] = "Please select a valid preferred time slot.";
                }

                $dateObj = DateTime::createFromFormat("Y-m-d", $newDate);
                if (!$dateObj || $dateObj->format("Y-m-d") !== $newDate) {
                    $validationErrors[] = "Please choose a valid appointment date.";
                } else {
                    $dateObj->setTime(0, 0, 0);
                    $todayObj = new DateTime("today");
                    $todayObj->setTime(0, 0, 0);

                    $maxAllowedYear = (int)date("Y") + 1;
                    $maxDateObj = DateTime::createFromFormat("Y-m-d", $maxAllowedYear . "-12-31");
                    $maxDateObj->setTime(0, 0, 0);

                    if ($dateObj < $todayObj) {
                        $validationErrors[] = "Appointment date cannot be in the past.";
                    }
                    if ($dateObj > $maxDateObj) {
                        $validationErrors[] = "Appointments can only be scheduled up to December 31, " . $maxAllowedYear . ".";
                    }
                    if ((int)$dateObj->format("N") === 4) {
                        $validationErrors[] = "Cebanico Dental Clinic is closed on Thursdays. Please choose another day.";
                    }
                }

                if (!empty($validationErrors)) {
                    $errorMsg = implode(" ", $validationErrors);
                } else {
                    $newDayOfWeek = $dateObj->format("l");
                    $requestId    = (int)$targetAppt["request_id"];
                    $refNumber    = "CDC-" . str_pad((string)$appointmentId, 4, "0", STR_PAD_LEFT);

                    if ($hasApptTimeInReqs && $newTime !== "") {
                        $updReq = $conn->prepare(
                            "UPDATE appointment_requests
                             SET service = ?, appointment_date = ?, appointment_time = ?, contact_number = ?
                             WHERE request_id = ?"
                        );
                        $updReq->bind_param("ssssi", $newService, $newDate, $newTime, $newContact, $requestId);
                    } else {
                        $updReq = $conn->prepare(
                            "UPDATE appointment_requests
                             SET service = ?, appointment_date = ?, contact_number = ?
                             WHERE request_id = ?"
                        );
                        $updReq->bind_param("sssi", $newService, $newDate, $newContact, $requestId);
                    }
                    $updReq->execute();
                    $updReq->close();

                    if ($hasApptTimeInAppts && $newTime !== "") {
                        $updAppt = $conn->prepare(
                            "UPDATE appointments
                             SET service = ?, appointment_date = ?, appointment_time = ?, day_of_week = ?, status = 'Pending'
                             WHERE appointment_id = ?"
                        );
                        $updAppt->bind_param("ssssi", $newService, $newDate, $newTime, $newDayOfWeek, $appointmentId);
                    } else {
                        $updAppt = $conn->prepare(
                            "UPDATE appointments
                             SET service = ?, appointment_date = ?, day_of_week = ?, status = 'Pending'
                             WHERE appointment_id = ?"
                        );
                        $updAppt->bind_param("sssi", $newService, $newDate, $newDayOfWeek, $appointmentId);
                    }

                    if ($updAppt->execute()) {
                        $_SESSION["patient"]["contact_number"] = $newContact;
                        $patientSession["contact_number"]      = $newContact;
                        $successMsg = "Appointment " . $refNumber . " has been updated successfully.";

                        triggerAppointmentWebhook($webhookUrl, [
                            "event_type"       => "appointment_updated",
                            "reference_number" => $refNumber,
                            "appointment_id"   => $appointmentId,
                            "full_name"        => $targetAppt["full_name"],
                            "email"            => $targetAppt["email"],
                            "contact_number"   => $newContact,
                            "service"          => $newService,
                            "appointment_date" => $newDate,
                            "appointment_time" => $newTime,
                            "pretty_date"      => $dateObj->format("l, F j, Y"),
                            "day_of_week"      => $newDayOfWeek
                        ]);
                    } else {
                        $errorMsg = "Could not update appointment: " . $updAppt->error;
                    }
                    $updAppt->close();
                }
            } elseif ($action === "cancel_appointment") {
                $cancelReason = trim($_POST["cancel_reason"] ?? "");
                $refNumber    = "CDC-" . str_pad((string)$appointmentId, 4, "0", STR_PAD_LEFT);

                $existingRemarks = trim((string)($targetAppt["remarks"] ?? ""));
                $cancelNote      = "Cancelled by patient on " . date("M j, Y g:i A");
                if ($cancelReason !== "") {
                    $cancelNote .= " — Reason: " . $cancelReason;
                }
                $updatedRemarks = $existingRemarks !== "" ? ($existingRemarks . " | " . $cancelNote) : $cancelNote;

                $cancelStmt = $conn->prepare(
                    "UPDATE appointments
                     SET status = 'Cancelled', remarks = ?
                     WHERE appointment_id = ?"
                );
                $cancelStmt->bind_param("si", $updatedRemarks, $appointmentId);

                if ($cancelStmt->execute()) {
                    $successMsg = "Appointment " . $refNumber . " has been cancelled.";

                    triggerAppointmentWebhook($webhookUrl, [
                        "event_type"       => "appointment_cancelled",
                        "reference_number" => $refNumber,
                        "appointment_id"   => $appointmentId,
                        "full_name"        => $targetAppt["full_name"],
                        "email"            => $targetAppt["email"],
                        "contact_number"   => $targetAppt["contact_number"],
                        "service"          => $targetAppt["service"],
                        "appointment_date" => $targetAppt["appointment_date"],
                        "cancel_reason"    => $cancelReason
                    ]);
                } else {
                    $errorMsg = "Could not cancel appointment: " . $cancelStmt->error;
                }
                $cancelStmt->close();
            }
        }
    }
}

$selectTimeCol     = $hasApptTimeInAppts ? ", a.appointment_time" : ", '' AS appointment_time";
$selectVerifiedCol = $hasIsVerified      ? ", a.is_verified"      : ", 1 AS is_verified";

$appointments = [];
$listStmt = $conn->prepare(
    "SELECT a.appointment_id, a.request_id, a.appointment_date, a.day_of_week,
            a.service, a.status, a.remarks, a.created_at
            {$selectTimeCol}
            {$selectVerifiedCol},
            r.full_name, r.email, r.contact_number
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

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | Cebanico Dental Clinic</title>

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
                    <li><a href="appointment.php" class="nav-contact-btn active">Book Appointment</a></li>
                </ul>

                <a href="profile.php" aria-label="My Profile" title="My Profile" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;color:inherit;background:rgba(148,163,184,0.14);border:1px solid rgba(148,163,184,0.28);text-decoration:none;flex-shrink:0;">
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

    <div id="restrictionPopup" class="restriction-popup" role="alert" aria-live="assertive">
        <div class="restriction-popup-icon">!</div>
        <div class="restriction-popup-body">
            <strong id="restrictionPopupTitle">Input Restriction</strong>
            <p id="restrictionPopupMsg">Invalid input detected.</p>
        </div>
        <button type="button" id="closeRestrictionPopup" class="restriction-popup-close" aria-label="Close message">&times;</button>
    </div>

    <main class="appointment-hero my-appts-hero">
        <div class="slide-overlay-gradient"></div>
        <img src="Images/OutsideClinic.png" alt="Clinic Background" class="appointment-bg-img">

        <div class="my-appts-container container">

            <div class="my-appts-header">
                <div>
                    <span class="eyebrow-text">Patient Appointments</span>
                    <h1>My <span class="hero-highlight">Appointments</span></h1>
                    <p class="my-appts-subtitle">
                        Showing bookings for <strong><?php echo htmlspecialchars($patientSession["email"] ?? ""); ?></strong>
                        &mdash; <a href="signin.php" class="inline-sky-link">Switch account</a>
                    </p>
                </div>

                <div class="my-appts-top-actions">
                    <a href="appointment.php" class="btn btn-primary">+ Book New Appointment</a>
                </div>
            </div>

            <?php if ($successMsg !== "") { ?>
                <div class="my-appts-alert success" role="status">
                    <span>&#10003;</span>
                    <div><?php echo htmlspecialchars($successMsg); ?></div>
                </div>
            <?php } ?>

            <?php if ($errorMsg !== "") { ?>
                <div class="my-appts-alert error" role="alert">
                    <span>!</span>
                    <div><?php echo htmlspecialchars($errorMsg); ?></div>
                </div>
            <?php } ?>

            <?php if (empty($appointments)) { ?>
                <div class="my-appts-empty glass-card">
                    <div class="my-appts-empty-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                    <h2>No Appointments Found</h2>
                    <p>You have not booked any appointments with <strong><?php echo htmlspecialchars($patientSession["email"] ?? ""); ?></strong> yet.</p>
                    <a href="appointment.php" class="btn btn-primary">Book Your First Visit &rarr;</a>
                </div>
            <?php } else { ?>
                <div class="my-appts-grid">
                    <?php foreach ($appointments as $appt) {
                        $apptId      = (int)$appt["appointment_id"];
                        $refNo       = "CDC-" . str_pad((string)$apptId, 4, "0", STR_PAD_LEFT);
                        $status      = (string)$appt["status"];
                        $statusSlug  = strtolower(str_replace(" ", "-", $status));
                        $isEditable  = in_array($status, ["Pending", "Confirmed"], true);
                        $isVerified  = ((int)($appt["is_verified"] ?? 1)) === 1;
                        $rawDate     = (string)$appt["appointment_date"];
                        $dateObj     = DateTime::createFromFormat("Y-m-d", $rawDate);
                        $prettyDate  = $dateObj ? $dateObj->format("F j, Y") : $rawDate;
                        $shortDate   = $dateObj ? $dateObj->format("M j, Y") : $rawDate;
                        $dayName     = !empty($appt["day_of_week"]) ? $appt["day_of_week"] : ($dateObj ? $dateObj->format("l") : "");
                        $apptTime    = trim((string)($appt["appointment_time"] ?? ""));
                        $remarks     = trim((string)($appt["remarks"] ?? ""));
                    ?>
                        <article class="appt-record-card glass-card status-border-<?php echo htmlspecialchars($statusSlug); ?>">
                            <div class="appt-card-top">
                                <div>
                                    <span class="appt-ref-badge"><?php echo htmlspecialchars($refNo); ?></span>
                                    <?php if (!$isVerified && $isEditable) { ?>
                                        <a href="verify-code.php?ref=<?php echo urlencode($refNo); ?>" class="appt-verify-pill" title="Click to verify booking code">Unverified &bull; Verify</a>
                                    <?php } ?>
                                </div>
                                <span class="appt-status-pill status-<?php echo htmlspecialchars($statusSlug); ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </div>

                            <h3 class="appt-service-title"><?php echo htmlspecialchars($appt["service"]); ?></h3>

                            <ul class="appt-meta-list">
                                <li>
                                    <span class="meta-label">Date</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($prettyDate); ?> (<?php echo htmlspecialchars($dayName); ?>)</span>
                                </li>
                                <?php if ($apptTime !== "") { ?>
                                <li>
                                    <span class="meta-label">Preferred Time</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($apptTime); ?></span>
                                </li>
                                <?php } ?>
                                <li>
                                    <span class="meta-label">Patient</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($appt["full_name"]); ?></span>
                                </li>
                                <li>
                                    <span class="meta-label">Contact</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($appt["contact_number"]); ?></span>
                                </li>
                            </ul>

                            <?php if ($remarks !== "") { ?>
                                <div class="appt-remarks-box">
                                    <strong>Note:</strong> <?php echo htmlspecialchars($remarks); ?>
                                </div>
                            <?php } ?>

                            <?php if ($isEditable) { ?>
                                <div class="appt-card-actions">
                                    <button
                                        type="button"
                                        class="btn btn-primary appt-action-btn open-edit-modal-btn"
                                        data-id="<?php echo $apptId; ?>"
                                        data-ref="<?php echo htmlspecialchars($refNo); ?>"
                                        data-service="<?php echo htmlspecialchars($appt["service"]); ?>"
                                        data-date="<?php echo htmlspecialchars($rawDate); ?>"
                                        data-pretty-date="<?php echo htmlspecialchars($shortDate); ?>"
                                        data-time="<?php echo htmlspecialchars($apptTime); ?>"
                                        data-contact="<?php echo htmlspecialchars($appt["contact_number"]); ?>"
                                    >
                                        Update Appointment
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-danger-outline appt-action-btn open-cancel-modal-btn"
                                        data-id="<?php echo $apptId; ?>"
                                        data-ref="<?php echo htmlspecialchars($refNo); ?>"
                                        data-service="<?php echo htmlspecialchars($appt["service"]); ?>"
                                        data-pretty-date="<?php echo htmlspecialchars($prettyDate); ?>"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            <?php } else { ?>
                                <div class="appt-card-locked-note">
                                    This appointment is <strong><?php echo htmlspecialchars(strtolower($status)); ?></strong> and can no longer be modified.
                                </div>
                            <?php } ?>
                        </article>
                    <?php } ?>
                </div>
            <?php } ?>

        </div>
    </main>

    <div id="editAppointmentModal" class="appt-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="editModalHeading">
        <div class="appt-modal-card">
            <button type="button" class="appt-modal-close" id="closeEditModalX" aria-label="Close modal">&times;</button>

            <span class="eyebrow-text" style="margin-bottom: 6px;">Reschedule or Modify</span>
            <h2 id="editModalHeading">Update <span id="editModalRefLabel" class="hero-highlight">CDC-0000</span></h2>
            <p class="appt-modal-sub">Adjust your dental service, preferred date, or contact number below.</p>

            <form method="POST" action="my-appointments.php" id="editAppointmentForm" class="appt-modal-form" novalidate>
                <input type="hidden" name="action" value="update_appointment">
                <input type="hidden" name="appointment_id" id="editAppointmentId" value="">

                <div class="form-group">
                    <label for="editService">Type of service</label>
                    <select id="editService" name="service" required>
                        <?php foreach ($allowedServices as $srv) { ?>
                            <option value="<?php echo htmlspecialchars($srv); ?>"><?php echo htmlspecialchars($srv); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="editDateDisplay">Day of appointment</label>
                    <div class="custom-date-picker" id="editCustomDatePicker">
                        <input type="hidden" id="editAppointmentDate" name="appointmentDate" required>
                        <button type="button" id="editDateDisplay" class="date-display-btn" aria-haspopup="dialog" aria-expanded="false">
                            <span id="editDateText">Select date</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </button>

                        <div id="editCalendarPopover" class="calendar-popover open-down" role="dialog" aria-label="Choose new appointment date">
                            <div class="calendar-header">
                                <button type="button" id="editCalPrevMonth" class="cal-nav-btn" aria-label="Previous month">&#10094;</button>
                                <div class="cal-select-group">
                                    <select id="editCalMonthSelect" aria-label="Select month"></select>
                                    <select id="editCalYearSelect" aria-label="Select year"></select>
                                </div>
                                <button type="button" id="editCalNextMonth" class="cal-nav-btn" aria-label="Next month">&#10095;</button>
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

                            <div id="editCalDaysGrid" class="calendar-days"></div>

                            <div class="calendar-footer">
                                <span>Thursdays are closed</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($hasApptTimeInAppts) { ?>
                <div class="form-group">
                    <label for="editAppointmentTime">Preferred time</label>
                    <select id="editAppointmentTime" name="appointmentTime">
                        <option value="">Keep / Select time</option>
                        <?php foreach ($allowedTimeSlots as $slot) { ?>
                            <option value="<?php echo htmlspecialchars($slot); ?>"><?php echo htmlspecialchars($slot); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>

                <div class="form-group">
                    <label for="editContactNumber">Contact number</label>
                    <input
                        type="tel"
                        id="editContactNumber"
                        name="contactNumber"
                        placeholder="09171234567"
                        inputmode="numeric"
                        maxlength="11"
                        required
                    >
                </div>

                <div class="appt-modal-footer-btns">
                    <button type="button" class="btn btn-secondary" id="cancelEditModalBtn">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="cancelAppointmentModal" class="appt-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="cancelModalHeading">
        <div class="appt-modal-card cancel-modal-card">
            <button type="button" class="appt-modal-close" id="closeCancelModalX" aria-label="Close modal">&times;</button>

            <div class="cancel-modal-warning-icon" aria-hidden="true">!</div>
            <h2 id="cancelModalHeading">Cancel Appointment?</h2>
            <p class="appt-modal-sub">
                Are you sure you want to cancel <strong id="cancelModalRefLabel">CDC-0000</strong>
                (<span id="cancelModalServiceLabel">Service</span> on <span id="cancelModalDateLabel">Date</span>)?
            </p>

            <form method="POST" action="my-appointments.php" class="appt-modal-form">
                <input type="hidden" name="action" value="cancel_appointment">
                <input type="hidden" name="appointment_id" id="cancelAppointmentId" value="">

                <div class="form-group">
                    <label for="cancelReason">Reason for cancellation (optional)</label>
                    <input
                        type="text"
                        id="cancelReason"
                        name="cancel_reason"
                        placeholder="e.g., Schedule conflict, feeling unwell"
                        maxlength="200"
                    >
                </div>

                <div class="appt-modal-footer-btns">
                    <button type="button" class="btn btn-secondary" id="keepAppointmentBtn">Keep Appointment</button>
                    <button type="submit" class="btn btn-danger-solid">Yes, Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>

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

    <script src="index.js?v=37"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
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
                popupTimeout = setTimeout(() => popup.classList.remove('show'), 3800);
            }

            if (closePopupBtn && popup) {
                closePopupBtn.addEventListener('click', () => {
                    popup.classList.remove('show');
                    clearTimeout(popupTimeout);
                });
            }

            const editModal = document.getElementById('editAppointmentModal');
            const editForm = document.getElementById('editAppointmentForm');
            const editAppointmentId = document.getElementById('editAppointmentId');
            const editModalRefLabel = document.getElementById('editModalRefLabel');
            const editService = document.getElementById('editService');
            const editAppointmentTime = document.getElementById('editAppointmentTime');
            const editContactNumber = document.getElementById('editContactNumber');

            const editCustomDatePicker = document.getElementById('editCustomDatePicker');
            const editHiddenDateInput = document.getElementById('editAppointmentDate');
            const editDateDisplayBtn = document.getElementById('editDateDisplay');
            const editDateText = document.getElementById('editDateText');
            const editCalendarPopover = document.getElementById('editCalendarPopover');
            const editCalPrevMonth = document.getElementById('editCalPrevMonth');
            const editCalNextMonth = document.getElementById('editCalNextMonth');
            const editCalMonthSelect = document.getElementById('editCalMonthSelect');
            const editCalYearSelect = document.getElementById('editCalYearSelect');
            const editCalDaysGrid = document.getElementById('editCalDaysGrid');

            const now = new Date();
            const currentYear = now.getFullYear();
            const maxAllowedYear = currentYear + 1;
            const currentMonth = now.getMonth();
            const currentDay = now.getDate();

            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            const shortMonthNames = [
                "Jan", "Feb", "Mar", "Apr", "May", "Jun",
                "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
            ];

            let viewYear = currentYear;
            let viewMonth = currentMonth;

            function formatIsoDate(year, monthIndex, day) {
                return `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            }

            function formatDisplayDate(year, monthIndex, day) {
                return `${shortMonthNames[monthIndex]} ${day}, ${year}`;
            }

            function populateEditCalendarControls() {
                if (!editCalYearSelect || !editCalMonthSelect) return;
                editCalYearSelect.innerHTML = '';
                [currentYear, maxAllowedYear].forEach((yr) => {
                    const opt = document.createElement('option');
                    opt.value = String(yr);
                    opt.textContent = String(yr);
                    editCalYearSelect.appendChild(opt);
                });

                editCalMonthSelect.innerHTML = '';
                monthNames.forEach((name, idx) => {
                    const opt = document.createElement('option');
                    opt.value = String(idx);
                    opt.textContent = name;
                    editCalMonthSelect.appendChild(opt);
                });
            }

            function renderEditCalendar() {
                if (!editCalDaysGrid || !editCalMonthSelect || !editCalYearSelect) return;

                editCalYearSelect.value = String(viewYear);
                Array.from(editCalMonthSelect.options).forEach((opt) => {
                    const mIdx = Number(opt.value);
                    const isPastMonth = (viewYear === currentYear && mIdx < currentMonth);
                    opt.hidden = isPastMonth;
                    opt.disabled = isPastMonth;
                });
                editCalMonthSelect.value = String(viewMonth);

                if (editCalPrevMonth) {
                    editCalPrevMonth.disabled = (viewYear === currentYear && viewMonth === currentMonth);
                }
                if (editCalNextMonth) {
                    editCalNextMonth.disabled = (viewYear === maxAllowedYear && viewMonth === 11);
                }

                editCalDaysGrid.innerHTML = '';
                const firstDayOfWeek = new Date(viewYear, viewMonth, 1).getDay();
                const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
                const selectedIso = editHiddenDateInput ? editHiddenDateInput.value : '';
                const todayIso = formatIsoDate(currentYear, currentMonth, currentDay);

                for (let i = 0; i < firstDayOfWeek; i++) {
                    const empty = document.createElement('span');
                    empty.className = 'cal-empty';
                    editCalDaysGrid.appendChild(empty);
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'cal-day';
                    btn.textContent = String(day);

                    const cellDate = new Date(viewYear, viewMonth, day);
                    const cellIso = formatIsoDate(viewYear, viewMonth, day);
                    const isPastDay = (viewYear === currentYear && viewMonth === currentMonth && day < currentDay);
                    const isThursday = (cellDate.getDay() === 4);

                    if (cellIso === todayIso) btn.classList.add('is-today');
                    if (cellIso === selectedIso) btn.classList.add('is-selected');

                    if (isPastDay) {
                        btn.classList.add('is-disabled');
                        btn.addEventListener('click', () => {
                            showRestrictionPopup("You cannot select a past date.", editDateDisplayBtn, "Invalid Date");
                        });
                    } else if (isThursday) {
                        btn.classList.add('is-closed');
                        btn.title = "Clinic closed on Thursdays";
                        btn.addEventListener('click', () => {
                            showRestrictionPopup("Cebanico Dental Clinic is closed on Thursdays. Please choose another day.", editDateDisplayBtn, "Clinic Closed on Thursdays");
                        });
                    } else {
                        btn.addEventListener('click', () => {
                            editHiddenDateInput.value = cellIso;
                            editDateText.textContent = formatDisplayDate(viewYear, viewMonth, day);
                            editCalendarPopover.classList.remove('open');
                            editDateDisplayBtn.setAttribute('aria-expanded', 'false');
                        });
                    }

                    editCalDaysGrid.appendChild(btn);
                }
            }

            if (editCustomDatePicker) {
                populateEditCalendarControls();
                renderEditCalendar();

                editDateDisplayBtn.addEventListener('click', () => {
                    if (editCalendarPopover.classList.contains('open')) {
                        editCalendarPopover.classList.remove('open');
                        editDateDisplayBtn.setAttribute('aria-expanded', 'false');
                    } else {
                        renderEditCalendar();
                        editCalendarPopover.classList.add('open');
                        editDateDisplayBtn.setAttribute('aria-expanded', 'true');
                    }
                });

                editCalYearSelect.addEventListener('change', () => {
                    viewYear = Number(editCalYearSelect.value);
                    if (viewYear === currentYear && viewMonth < currentMonth) {
                        viewMonth = currentMonth;
                    }
                    renderEditCalendar();
                });

                editCalMonthSelect.addEventListener('change', () => {
                    viewMonth = Number(editCalMonthSelect.value);
                    renderEditCalendar();
                });

                editCalPrevMonth.addEventListener('click', () => {
                    if (viewYear === currentYear && viewMonth === currentMonth) return;
                    viewMonth -= 1;
                    if (viewMonth < 0) {
                        viewMonth = 11;
                        viewYear -= 1;
                    }
                    renderEditCalendar();
                });

                editCalNextMonth.addEventListener('click', () => {
                    if (viewYear === maxAllowedYear && viewMonth === 11) return;
                    viewMonth += 1;
                    if (viewMonth > 11) {
                        viewMonth = 0;
                        viewYear += 1;
                    }
                    renderEditCalendar();
                });

                document.addEventListener('click', (e) => {
                    if (!editCustomDatePicker.contains(e.target)) {
                        editCalendarPopover.classList.remove('open');
                        editDateDisplayBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            if (editContactNumber) {
                editContactNumber.addEventListener('input', function () {
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

            document.querySelectorAll('.open-edit-modal-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id || '';
                    const ref = btn.dataset.ref || '';
                    const service = btn.dataset.service || '';
                    const dateIso = btn.dataset.date || '';
                    const prettyDate = btn.dataset.prettyDate || dateIso;
                    const timeVal = btn.dataset.time || '';
                    const contact = btn.dataset.contact || '';

                    editAppointmentId.value = id;
                    editModalRefLabel.textContent = ref;
                    editService.value = service;
                    editHiddenDateInput.value = dateIso;
                    editDateText.textContent = prettyDate;
                    editContactNumber.value = contact;

                    if (editAppointmentTime) {
                        editAppointmentTime.value = timeVal;
                    }

                    const parts = dateIso.split('-').map(Number);
                    if (parts.length === 3 && parts[0] >= currentYear && parts[0] <= maxAllowedYear) {
                        viewYear = parts[0];
                        viewMonth = (viewYear === currentYear && (parts[1] - 1) < currentMonth) ? currentMonth : (parts[1] - 1);
                    } else {
                        viewYear = currentYear;
                        viewMonth = currentMonth;
                    }
                    renderEditCalendar();

                    editModal.classList.add('show');
                });
            });

            function closeEditModal() {
                if (!editModal) return;
                editModal.classList.remove('show');
                if (editCalendarPopover) editCalendarPopover.classList.remove('open');
            }

            const closeEditModalX = document.getElementById('closeEditModalX');
            const cancelEditModalBtn = document.getElementById('cancelEditModalBtn');
            if (closeEditModalX) closeEditModalX.addEventListener('click', closeEditModal);
            if (cancelEditModalBtn) cancelEditModalBtn.addEventListener('click', closeEditModal);
            if (editModal) {
                editModal.addEventListener('click', (e) => {
                    if (e.target === editModal) closeEditModal();
                });
            }

            if (editForm) {
                editForm.addEventListener('submit', (e) => {
                    if (!editHiddenDateInput.value) {
                        e.preventDefault();
                        showRestrictionPopup("Please select a valid appointment date.", editDateDisplayBtn, "Date Required");
                        return;
                    }
                    if (!/^09[0-9]{9}$/.test(editContactNumber.value)) {
                        e.preventDefault();
                        editContactNumber.focus();
                        showRestrictionPopup("Please enter an 11-digit Philippine mobile number starting with 09.", editContactNumber, "Invalid Contact Number");
                    }
                });
            }

            const cancelModal = document.getElementById('cancelAppointmentModal');
            const cancelAppointmentId = document.getElementById('cancelAppointmentId');
            const cancelModalRefLabel = document.getElementById('cancelModalRefLabel');
            const cancelModalServiceLabel = document.getElementById('cancelModalServiceLabel');
            const cancelModalDateLabel = document.getElementById('cancelModalDateLabel');
            const cancelReasonInput = document.getElementById('cancelReason');

            document.querySelectorAll('.open-cancel-modal-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    cancelAppointmentId.value = btn.dataset.id || '';
                    cancelModalRefLabel.textContent = btn.dataset.ref || '';
                    cancelModalServiceLabel.textContent = btn.dataset.service || '';
                    cancelModalDateLabel.textContent = btn.dataset.prettyDate || '';
                    if (cancelReasonInput) cancelReasonInput.value = '';
                    cancelModal.classList.add('show');
                });
            });

            function closeCancelModal() {
                if (cancelModal) cancelModal.classList.remove('show');
            }

            const closeCancelModalX = document.getElementById('closeCancelModalX');
            const keepAppointmentBtn = document.getElementById('keepAppointmentBtn');
            if (closeCancelModalX) closeCancelModalX.addEventListener('click', closeCancelModal);
            if (keepAppointmentBtn) keepAppointmentBtn.addEventListener('click', closeCancelModal);
            if (cancelModal) {
                cancelModal.addEventListener('click', (e) => {
                    if (e.target === cancelModal) closeCancelModal();
                });
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeEditModal();
                    closeCancelModal();
                }
            });
        });
    </script>
</body>
</html>