<?php
session_start();
date_default_timezone_set("Asia/Manila");
require_once "db_connect.php";

// Dedicated Make.com webhook for sign-up / sign-in email verification
$webhookUrl = "https://hook.us2.make.com/g4w8vvh2tkj72wj1e87qj7s98oeqsrfj";

// Dynamic self-target so this works whether saved as signin.php or signup.php
$currentPage = htmlspecialchars(basename($_SERVER["PHP_SELF"]));
$isSignedIn  = isset($_SESSION["patient"]) && is_array($_SESSION["patient"]) && !empty($_SESSION["patient"]["email"]);

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

/**
 * Looks up an existing registered patient record by email.
 * Checks `patients` first, then falls back to `appointment_requests` so existing bookings are honored.
 */
function findRegisteredPatientByEmail(mysqli $conn, string $email): ?array
{
    $normalizedEmail = strtolower(trim($email));
    if ($normalizedEmail === "") {
        return null;
    }

    if (ensurePatientsTable($conn)) {
        $stmt = $conn->prepare(
            "SELECT username, email, contact_number
             FROM patients
             WHERE LOWER(email) = LOWER(?)
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("s", $normalizedEmail);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            if ($row) {
                return [
                    "username"       => trim((string)$row["username"]),
                    "email"          => strtolower(trim((string)$row["email"])),
                    "contact_number" => trim((string)$row["contact_number"])
                ];
            }
        }
    }

    if (tableExists($conn, "appointment_requests")) {
        $reqStmt = $conn->prepare(
            "SELECT full_name, email, contact_number
             FROM appointment_requests
             WHERE LOWER(email) = LOWER(?)
             ORDER BY request_id ASC
             LIMIT 1"
        );
        if ($reqStmt) {
            $reqStmt->bind_param("s", $normalizedEmail);
            $reqStmt->execute();
            $res = $reqStmt->get_result();
            $row = $res->fetch_assoc();
            $reqStmt->close();
            if ($row) {
                $registeredName    = trim((string)$row["full_name"]);
                $registeredContact = trim((string)$row["contact_number"]);

                if (ensurePatientsTable($conn) && $registeredName !== "") {
                    $ins = $conn->prepare(
                        "INSERT IGNORE INTO patients (username, email, contact_number)
                         VALUES (?, ?, ?)"
                    );
                    if ($ins) {
                        $ins->bind_param("sss", $registeredName, $normalizedEmail, $registeredContact);
                        $ins->execute();
                        $ins->close();
                    }
                }

                return [
                    "username"       => $registeredName,
                    "email"          => $normalizedEmail,
                    "contact_number" => $registeredContact
                ];
            }
        }
    }

    return null;
}

/**
 * Registers a new email + username binding once verified.
 * Never overwrites an existing username for an already-registered email.
 */
function registerPatientIfNew(mysqli $conn, string $username, string $email, string $contact): array
{
    $normalizedEmail = strtolower(trim($email));
    $cleanName       = preg_replace("/\s+/", " ", trim($username));
    $cleanContact    = preg_replace("/[^0-9]/", "", trim($contact));

    $existing = findRegisteredPatientByEmail($conn, $normalizedEmail);
    if ($existing !== null) {
        if (strcasecmp($existing["username"], $cleanName) !== 0) {
            return [
                "ok"      => false,
                "error"   => "This email is already registered by a user, and the entered username does not match.",
                "patient" => null
            ];
        }

        if (ensurePatientsTable($conn) && $cleanContact !== "") {
            $upd = $conn->prepare(
                "UPDATE patients
                 SET contact_number = ?
                 WHERE LOWER(email) = LOWER(?)"
            );
            if ($upd) {
                $upd->bind_param("ss", $cleanContact, $normalizedEmail);
                $upd->execute();
                $upd->close();
            }
        }

        return [
            "ok"      => true,
            "error"   => "",
            "patient" => [
                "username"       => $existing["username"],
                "email"          => $normalizedEmail,
                "contact_number" => $cleanContact !== "" ? $cleanContact : $existing["contact_number"]
            ]
        ];
    }

    if (!ensurePatientsTable($conn)) {
        return [
            "ok"      => false,
            "error"   => "Could not initialize patient account storage. Please try again.",
            "patient" => null
        ];
    }

    $ins = $conn->prepare(
        "INSERT INTO patients (username, email, contact_number)
         VALUES (?, ?, ?)"
    );
    if (!$ins) {
        return [
            "ok"      => false,
            "error"   => "Database error while registering account: " . $conn->error,
            "patient" => null
        ];
    }

    $ins->bind_param("sss", $cleanName, $normalizedEmail, $cleanContact);
    $executed = $ins->execute();
    $errno    = $conn->errno;
    $errMsg   = $ins->error;
    $ins->close();

    if (!$executed) {
        if ($errno === 1062) {
            $raceExisting = findRegisteredPatientByEmail($conn, $normalizedEmail);
            if ($raceExisting !== null && strcasecmp($raceExisting["username"], $cleanName) !== 0) {
                return [
                    "ok"      => false,
                    "error"   => "This email is already registered by a user, and the entered username does not match.",
                    "patient" => null
                ];
            }
        }
        return [
            "ok"      => false,
            "error"   => "Could not save your account registration: " . $errMsg,
            "patient" => null
        ];
    }

    return [
        "ok"      => true,
        "error"   => "",
        "patient" => [
            "username"       => $cleanName,
            "email"          => $normalizedEmail,
            "contact_number" => $cleanContact
        ]
    ];
}

// Helper: Send 6-digit verification code through Make.com
function sendEmailVerificationCode(string $webhookUrl, string $username, string $email, string $contact, string $otpCode): array
{
    if (empty($webhookUrl)) {
        return ["success" => false, "error" => "Verification webhook URL is missing."];
    }

    $payload = [
        "event_type"         => "email_verification",
        "clinic_name"        => "Cebanico Dental Clinic",
        "full_name"          => $username,
        "username"           => $username,
        "email"              => $email,
        "to_email"           => $email,
        "contact_number"     => $contact,
        "verification_code"  => $otpCode,
        "otp_code"           => $otpCode,
        "expires_in_minutes" => 10
    ];

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

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        return [
            "success" => false,
            "error"   => $curlErr !== "" ? $curlErr : "Webhook returned status HTTP " . $httpCode
        ];
    }

    return ["success" => true, "error" => ""];
}

// Helper: Detect fake / keyboard-smashed email addresses
function isFakeOrGibberishEmail(string $email): ?string
{
    $parts = explode("@", strtolower($email));
    if (count($parts) !== 2) {
        return "Please enter a valid email address.";
    }

    [$local, $domain] = $parts;
    $lettersOnly = preg_replace("/[^a-z]/", "", $local);

    $blockedLocals = [
        "test", "testing", "fake", "dummy", "sample", "example",
        "none", "noemail", "noreply", "nobody", "admin", "user", "asdf", "qwerty"
    ];
    if (in_array($lettersOnly, $blockedLocals, true)) {
        return "Please enter your real personal email address, not a placeholder.";
    }

    if (preg_match("/(asdf|qwer|zxcv|hjkl|uiop|123456|abcdef)/i", $local)) {
        return "This email address looks invalid or randomly typed. Please enter a real email address.";
    }

    if (preg_match("/([a-z]{3,})\\1/i", $lettersOnly)) {
        return "This email address appears to contain random characters. Please enter a real email address.";
    }

    if (preg_match("/[bcdfghjklmnpqrstvwxyz]{6,}/i", $lettersOnly)) {
        return "This email address appears to be invalid. Please enter a real email address.";
    }

    return null;
}

// Handle fresh GET visits without logging out active site sessions
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION["signin_form_time"] = time();
    if (isset($_GET["reset"]) && $_GET["reset"] === "1") {
        unset($_SESSION["pending_signup_otp"]);
    }
}

$username   = preg_replace("/\s+/", " ", trim($_POST["username"] ?? ""));
$email      = strtolower(trim($_POST["email"] ?? ""));
$contact    = trim($_POST["contactNumber"] ?? "");
$errors     = [];
$popupAlert = null;
$infoNotice = "";
$otpStep    = isset($_SESSION["pending_signup_otp"]) && is_array($_SESSION["pending_signup_otp"]);

// Build map of registered email => username for instant pop-up feedback
$registeredEmailMap = [];
if (ensurePatientsTable($conn)) {
    $mapRes = $conn->query("SELECT email, username FROM patients");
    if ($mapRes instanceof mysqli_result) {
        while ($mRow = $mapRes->fetch_assoc()) {
            $k = strtolower(trim((string)$mRow["email"]));
            $v = trim((string)$mRow["username"]);
            if ($k !== "" && $v !== "") {
                $registeredEmailMap[$k] = $v;
            }
        }
        $mapRes->free();
    }
}
if (tableExists($conn, "appointment_requests")) {
    $reqMapRes = $conn->query("SELECT email, full_name FROM appointment_requests ORDER BY request_id ASC");
    if ($reqMapRes instanceof mysqli_result) {
        while ($rRow = $reqMapRes->fetch_assoc()) {
            $k = strtolower(trim((string)$rRow["email"]));
            $v = trim((string)$rRow["full_name"]);
            if ($k !== "" && $v !== "" && !isset($registeredEmailMap[$k])) {
                $registeredEmailMap[$k] = $v;
            }
        }
        $reqMapRes->free();
    }
}

// Disposable / temporary email providers
$disposableDomains = [
    "mailinator.com", "guerrillamail.com", "guerrillamail.net", "guerrillamail.org",
    "tempmail.com", "temp-mail.org", "temp-mail.io", "10minutemail.com",
    "10minutemail.net", "yopmail.com", "yopmail.fr", "yopmail.net",
    "throwawaymail.com", "trashmail.com", "trashmail.net", "fakeinbox.com",
    "sharklasers.com", "getnada.com", "dispostable.com", "maildrop.cc",
    "mohmal.com", "emailondeck.com", "mintemail.com", "mytemp.email",
    "burnermail.io", "tempr.email", "discard.email", "spamgourmet.com",
    "mailnesia.com", "mailcatch.com", "incognitomail.org", "tempinbox.com",
    "getairmail.com", "fakemailgenerator.com", "armyspy.com", "cuvox.de",
    "dayrep.com", "einrot.com", "fleckens.hu", "gustr.com", "jourrapide.com",
    "rhyta.com", "superrito.com", "teleworm.us"
];

// Common email domain typos
$domainTypos = [
    "gmial.com"   => "gmail.com",
    "gamil.com"   => "gmail.com",
    "gnail.com"   => "gmail.com",
    "gmai.com"    => "gmail.com",
    "gmail.con"   => "gmail.com",
    "gmail.co"    => "gmail.com",
    "yaho.com"    => "yahoo.com",
    "yahooo.com"  => "yahoo.com",
    "yahoo.con"   => "yahoo.com",
    "hotmal.com"  => "hotmail.com",
    "hotmai.com"  => "hotmail.com",
    "outlok.com"  => "outlook.com"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "signin";

    // ==========================================================
    // RESEND 6-DIGIT VERIFICATION CODE
    // ==========================================================
    if ($action === "resend_signup_otp" && isset($_SESSION["pending_signup_otp"])) {
        $otpStep  = true;
        $pending  = $_SESSION["pending_signup_otp"];
        $lastSent = (int)($pending["last_sent"] ?? 0);

        if ((time() - $lastSent) < 30) {
            $wait = 30 - (time() - $lastSent);
            $errors[] = "Please wait {$wait} seconds before requesting a new verification code.";
        } else {
            $newCode = (string)random_int(100000, 999999);
            $sendRes = sendEmailVerificationCode(
                $webhookUrl,
                $pending["username"],
                $pending["email"],
                $pending["contact"],
                $newCode
            );

            if ($sendRes["success"]) {
                $_SESSION["pending_signup_otp"]["otp"]        = $newCode;
                $_SESSION["pending_signup_otp"]["expires_at"] = time() + 600;
                $_SESSION["pending_signup_otp"]["last_sent"]  = time();
                $infoNotice = "A new 6-digit verification code has been sent to " . $pending["email"] . ".";
            } else {
                $errors[] = "Could not resend the verification email right now. Please try again shortly.";
            }
        }
    }
    // ==========================================================
    // STEP 2: VERIFY 6-DIGIT CODE & LOCK ONE USERNAME PER EMAIL
    // ==========================================================
    elseif ($action === "verify_signup_otp" && isset($_SESSION["pending_signup_otp"])) {
        $otpStep  = true;
        $pending  = $_SESSION["pending_signup_otp"];
        $otpInput = trim($_POST["signupOtp"] ?? "");

        if (!preg_match("/^\d{6}$/", $otpInput)) {
            $errors[] = "Please enter the 6-digit verification code sent to your email.";
        } elseif (time() > ($pending["expires_at"] ?? 0)) {
            unset($_SESSION["pending_signup_otp"]);
            $otpStep  = false;
            $errors[] = "Your verification code expired. Please enter your details and verify again.";
            $_SESSION["signin_form_time"] = time();
        } elseif (!hash_equals((string)$pending["otp"], $otpInput)) {
            $errors[] = "Incorrect verification code. Please check your inbox and try again.";
        } else {
            $regResult = registerPatientIfNew(
                $conn,
                (string)$pending["username"],
                (string)$pending["email"],
                (string)$pending["contact"]
            );

            if (!$regResult["ok"] || !is_array($regResult["patient"])) {
                unset($_SESSION["pending_signup_otp"]);
                $otpStep    = false;
                $email      = (string)$pending["email"];
                $contact    = (string)$pending["contact"];
                $username   = (string)$pending["username"];
                $msg        = "This email is already registered by a user, and the entered username does not match.";
                $errors[]   = $msg;
                $popupAlert = ["title" => "Email Already Registered", "msg" => $msg, "target" => "username"];
                $_SESSION["signin_form_time"] = time();
            } else {
                $_SESSION["patient"] = [
                    "username"       => $regResult["patient"]["username"],
                    "email"          => $regResult["patient"]["email"],
                    "contact_number" => $regResult["patient"]["contact_number"],
                    "verified_at"    => time()
                ];
                unset($_SESSION["pending_signup_otp"]);
                header("Location: appointment.php");
                exit;
            }
        }
    }
    // ==========================================================
    // STEP 1: VALIDATE INPUTS, CHECK REGISTERED NAME & SEND OTP
    // ==========================================================
    else {
        $otpStep    = false;
        $honeypot   = trim($_POST["website_url"] ?? "");
        $renderedAt = (int)($_SESSION["signin_form_time"] ?? 0);

        if ($honeypot !== "") {
            $errors[] = "Automated submission detected. Request blocked.";
        }
        if ($renderedAt > 0 && (time() - $renderedAt) < 1) {
            $errors[] = "Submission was too fast. Please try again.";
        }

        if ($username === "" || !preg_match("/^[A-Za-z\s]{2,}$/", $username)) {
            $errors[] = "Please enter a valid full name / username (letters and spaces only, at least 2 characters).";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        } else {
            $atPos  = strrpos($email, "@");
            $domain = ($atPos !== false) ? strtolower(substr($email, $atPos + 1)) : "";

            if ($domain === "" || strpos($domain, ".") === false) {
                $errors[] = "Please enter a valid email domain.";
            } elseif (isset($domainTypos[$domain])) {
                $errors[] = "Did you mean @" . $domainTypos[$domain] . "? Please fix the email typo.";
            } elseif (in_array($domain, $disposableDomains, true)) {
                $errors[] = "Disposable or temporary email addresses are not allowed. Please use a real personal email.";
            } else {
                $gibberishError = isFakeOrGibberishEmail($email);
                if ($gibberishError !== null) {
                    $errors[] = $gibberishError;
                } elseif (function_exists("checkdnsrr")) {
                    $hasMx = @checkdnsrr($domain, "MX");
                    $hasA  = $hasMx ? true : @checkdnsrr($domain, "A");
                    if (!$hasMx && !$hasA) {
                        $errors[] = "The domain (@" . $domain . ") is not a registered email provider. Please enter a real email address.";
                    }
                }
            }
        }

        // Check if this email already has a registered full name / username
        $registeredPatient = empty($errors) ? findRegisteredPatientByEmail($conn, $email) : null;
        if ($registeredPatient !== null && $registeredPatient["username"] !== "") {
            if (strcasecmp($registeredPatient["username"], $username) !== 0) {
                $mismatchMsg = "This email is already registered by a user, and the entered username does not match.";
                $errors[]    = $mismatchMsg;
                $popupAlert  = [
                    "title"  => "Email Already Registered",
                    "msg"    => $mismatchMsg,
                    "target" => "username"
                ];
            }
        }

        $contact = preg_replace("/[^0-9]/", "", $contact);
        if (!preg_match("/^09[0-9]{9}$/", $contact)) {
            $errors[] = "Please enter a valid 11-digit Philippine mobile number starting with 09.";
        }

        $_SESSION["signin_form_time"] = time();

        if (empty($errors)) {
            $effectiveName = ($registeredPatient !== null && $registeredPatient["username"] !== "")
                ? $registeredPatient["username"]
                : $username;

            $otpCode = (string)random_int(100000, 999999);
            $sendRes = sendEmailVerificationCode($webhookUrl, $effectiveName, $email, $contact, $otpCode);

            if ($sendRes["success"]) {
                $_SESSION["pending_signup_otp"] = [
                    "username"   => $effectiveName,
                    "email"      => $email,
                    "contact"    => $contact,
                    "otp"        => $otpCode,
                    "expires_at" => time() + 600,
                    "last_sent"  => time()
                ];
                $otpStep = true;
            } else {
                $errors[] = "Unable to send verification code to your email right now. Please try again.";
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
    <title>Patient Sign In | Cebanico Dental Clinic</title>

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
                    <li><a href="<?php echo $currentPage; ?>" class="nav-contact-btn active">Book Appointment</a></li>
                </ul>

                <a href="<?php echo $isSignedIn ? 'profile.php' : 'signin.php'; ?>"
                   class="nav-profile-btn <?php echo $isSignedIn ? 'is-signed-in' : ''; ?>"
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

            <!-- Left Info Content -->
            <div class="appointment-info">
                <span class="eyebrow-text">Patient Portal</span>
                <h1>Sign in to <span class="hero-highlight">continue</span></h1>
                <p>Enter your details below so we can verify your email address before proceeding to the appointment booking page.</p>

                <div class="appointment-hours-note">
                    <p><strong>One Name Per Email:</strong> Once an email is registered, your full name / username is tied to that email address.</p>
                    <p><strong>Email Verification Required:</strong> Every sign-in sends a 6-digit verification code to confirm your email is real and active.</p>
                </div>
            </div>

            <!-- Right Sign-In / Email Verification Form -->
            <div class="appointment-form-wrapper">
                <?php if ($otpStep && isset($_SESSION["pending_signup_otp"])) { ?>
                    <div class="appointment-form glass-card">
                        <?php if (!empty($errors)) { ?>
                            <div class="verify-error-banner" role="alert">
                                <?php foreach ($errors as $err) { ?>
                                    <div><?php echo htmlspecialchars($err); ?></div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if ($infoNotice !== "") { ?>
                            <div class="otp-notice-banner" role="status">
                                <?php echo htmlspecialchars($infoNotice); ?>
                            </div>
                        <?php } else { ?>
                            <div class="otp-notice-banner">
                                We sent a 6-digit verification code to <strong><?php echo htmlspecialchars($_SESSION["pending_signup_otp"]["email"]); ?></strong> for <strong><?php echo htmlspecialchars($_SESSION["pending_signup_otp"]["username"]); ?></strong>. Enter the code below to confirm your email address.
                            </div>
                        <?php } ?>

                        <form action="<?php echo $currentPage; ?>" method="POST" class="otp-verify-subform" autocomplete="off" novalidate>
                            <input type="hidden" name="action" value="verify_signup_otp">

                            <div class="form-group">
                                <label for="signupOtp">6-Digit Email Verification Code</label>
                                <input
                                    type="text"
                                    id="signupOtp"
                                    name="signupOtp"
                                    placeholder="123456"
                                    maxlength="6"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-primary appointment-submit-btn">Verify Email &amp; Continue &rarr;</button>
                        </form>

                        <form action="<?php echo $currentPage; ?>" method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="resend_signup_otp">
                            <button type="submit" class="btn btn-secondary verify-code-btn">Resend Verification Code</button>
                        </form>

                        <a href="<?php echo $currentPage; ?>?reset=1" class="otp-change-email-link">Entered the wrong email? Change email address</a>
                    </div>
                <?php } else { ?>
                    <form class="appointment-form glass-card" id="signinForm" action="<?php echo $currentPage; ?>" method="POST" autocomplete="off" novalidate>
                        <input type="hidden" name="action" value="signin">

                        <!-- Silent Honeypot Field -->
                        <div class="hp-field" aria-hidden="true">
                            <label for="website_url">Website</label>
                            <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                        </div>

                        <?php if (!empty($errors)) { ?>
                            <div class="verify-error-banner" role="alert">
                                <?php foreach ($errors as $err) { ?>
                                    <div><?php echo htmlspecialchars($err); ?></div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <div class="form-group">
                            <label for="username">Full Name / Username</label>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                placeholder="Juan Dela Cruz"
                                value="<?php echo htmlspecialchars($username); ?>"
                                autocomplete="off"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="name@example.com"
                                value="<?php echo htmlspecialchars($email); ?>"
                                autocomplete="off"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input
                                type="tel"
                                id="contactNumber"
                                name="contactNumber"
                                placeholder="09171234567"
                                value="<?php echo htmlspecialchars($contact); ?>"
                                inputmode="numeric"
                                maxlength="11"
                                autocomplete="off"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary appointment-submit-btn">Send Verification Code &rarr;</button>

                        <div class="verify-code-divider"><span>Already booked an appointment?</span></div>

                        <a href="verify-code.php" class="btn btn-secondary verify-code-btn">Verify Booking Code</a>

                    </form>
                <?php } ?>
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
                        <a href="<?php echo $currentPage; ?>" style="color: var(--primary, #0ea5e9); font-weight: 600; display: inline-block; margin-top: 12px;">Book an Appointment &rarr;</a>
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
            const registeredEmails = <?php echo json_encode($registeredEmailMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const serverPopupAlert = <?php echo json_encode($popupAlert, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            const form = document.getElementById('signinForm');
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email');
            const contactNumberInput = document.getElementById('contactNumber');
            const signupOtpInput = document.getElementById('signupOtp');

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
                    setTimeout(() => targetEl.classList.remove('input-error'), 1800);
                }

                clearTimeout(popupTimeout);
                popupTimeout = setTimeout(() => popup.classList.remove('show'), 4500);
            }

            if (closePopupBtn && popup) {
                closePopupBtn.addEventListener('click', () => {
                    popup.classList.remove('show');
                    clearTimeout(popupTimeout);
                });
            }

            if (serverPopupAlert && serverPopupAlert.msg) {
                const targetEl = serverPopupAlert.target === 'email'
                    ? emailInput
                    : (serverPopupAlert.target === 'contactNumber' ? contactNumberInput : usernameInput);
                showRestrictionPopup(serverPopupAlert.msg, targetEl, serverPopupAlert.title || "Email Already Registered");
            }

            if (signupOtpInput) {
                signupOtpInput.addEventListener('input', function () {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                });
            }

            if (!form) return;

            function normalizeName(val) {
                return val.replace(/\s+/g, ' ').trim().toLowerCase();
            }

            function checkRegisteredUsernameMismatch(triggerPopup = true) {
                const emailKey = emailInput.value.trim().toLowerCase();
                const enteredName = usernameInput.value.replace(/\s+/g, ' ').trim();
                if (emailKey && Object.prototype.hasOwnProperty.call(registeredEmails, emailKey)) {
                    const expectedName = String(registeredEmails[emailKey]).replace(/\s+/g, ' ').trim();
                    if (enteredName !== "" && normalizeName(enteredName) !== normalizeName(expectedName)) {
                        if (triggerPopup) {
                            showRestrictionPopup(
                                "This email is already registered by a user, and the entered username does not match.",
                                usernameInput,
                                "Email Already Registered"
                            );
                        }
                        return { mismatch: true };
                    }
                }
                return { mismatch: false };
            }

            const blockedDomains = [
                "mailinator.com", "guerrillamail.com", "tempmail.com", "temp-mail.org",
                "10minutemail.com", "yopmail.com", "throwawaymail.com", "trashmail.com",
                "fakeinbox.com", "sharklasers.com", "getnada.com", "dispostable.com",
                "maildrop.cc", "mohmal.com", "emailondeck.com"
            ];

            const typoSuggestions = {
                "gmial.com": "gmail.com",
                "gamil.com": "gmail.com",
                "gnail.com": "gmail.com",
                "gmail.con": "gmail.com",
                "yaho.com": "yahoo.com",
                "yahoo.con": "yahoo.com",
                "hotmal.com": "hotmail.com",
                "outlok.com": "outlook.com"
            };

            const blockedLocals = [
                "test", "testing", "fake", "dummy", "sample", "example",
                "none", "noemail", "noreply", "nobody", "admin", "user", "asdf", "qwerty"
            ];

            function validateRealEmailClient(emailVal) {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(emailVal)) {
                    return { valid: false, title: "Invalid Email", msg: "Please enter a valid email address." };
                }

                const [localPart, emailDomain] = emailVal.split('@');
                const lettersOnly = localPart.replace(/[^a-z]/g, '');

                if (typoSuggestions[emailDomain]) {
                    return {
                        valid: false,
                        title: "Email Typo Detected",
                        msg: `Did you mean @${typoSuggestions[emailDomain]}? Please fix the typo.`
                    };
                }

                if (blockedDomains.includes(emailDomain)) {
                    return {
                        valid: false,
                        title: "Temporary Email Blocked",
                        msg: "Disposable or temporary emails are not allowed. Use a real email address."
                    };
                }

                if (blockedLocals.includes(lettersOnly)) {
                    return {
                        valid: false,
                        title: "Fake Email Blocked",
                        msg: "Please enter your real personal email address, not a placeholder."
                    };
                }

                if (/(asdf|qwer|zxcv|hjkl|uiop|123456|abcdef)/i.test(localPart) ||
                    /([a-z]{3,})\1/i.test(lettersOnly) ||
                    /[bcdfghjklmnpqrstvwxyz]{6,}/i.test(lettersOnly)) {
                    return {
                        valid: false,
                        title: "Unrecognized Email Pattern",
                        msg: "This email address appears to contain random characters. Please enter a real email address."
                    };
                }

                return { valid: true };
            }

            usernameInput.addEventListener('input', function () {
                const raw = this.value;
                if (/[0-9]/.test(raw)) {
                    showRestrictionPopup("Numbers are not allowed in your name.", this, "Name Restriction");
                } else if (/[^A-Za-z\s]/.test(raw)) {
                    showRestrictionPopup("Special characters are not allowed in your name.", this, "Name Restriction");
                }
                this.value = raw.replace(/[^A-Za-z\s]/g, '');
                checkRegisteredUsernameMismatch(true);
            });

            emailInput.addEventListener('input', function () {
                const raw = this.value;
                if (/\s/.test(raw)) {
                    showRestrictionPopup("Spaces are not allowed in the email field.", this, "Email Restriction");
                } else if (/[^A-Za-z0-9@_.-]/.test(raw)) {
                    showRestrictionPopup("Invalid character detected in email field.", this, "Email Restriction");
                }
                this.value = raw.replace(/[^A-Za-z0-9@_.-]/g, '');
                checkRegisteredUsernameMismatch(true);
            });

            emailInput.addEventListener('change', () => checkRegisteredUsernameMismatch(true));
            usernameInput.addEventListener('change', () => checkRegisteredUsernameMismatch(true));

            contactNumberInput.addEventListener('input', function () {
                const raw = this.value;
                if (/[^0-9]/.test(raw)) {
                    showRestrictionPopup("Please enter numbers only for your contact number.", this, "Contact Restriction");
                }

                let digitsOnly = raw.replace(/[^0-9]/g, '');
                if (digitsOnly.length === 12 && digitsOnly.startsWith('639')) {
                    digitsOnly = '0' + digitsOnly.slice(2);
                }

                if (digitsOnly.length >= 1 && digitsOnly.charAt(0) !== '0') {
                    showRestrictionPopup("Philippine mobile numbers must start with 09.", this, "Prefix Restriction");
                    digitsOnly = '';
                } else if (digitsOnly.length >= 2 && digitsOnly.substring(0, 2) !== '09') {
                    showRestrictionPopup("Philippine mobile numbers must start with 09.", this, "Prefix Restriction");
                    digitsOnly = '0';
                }

                if (digitsOnly.length > 11) {
                    digitsOnly = digitsOnly.slice(0, 11);
                }
                this.value = digitsOnly;
            });

            form.addEventListener('submit', function (e) {
                if (usernameInput.value.trim().length < 2) {
                    e.preventDefault();
                    usernameInput.focus();
                    showRestrictionPopup("Please enter your full name / username.", usernameInput, "Name Required");
                    return;
                }

                const emailVal = emailInput.value.trim().toLowerCase();
                const emailCheck = validateRealEmailClient(emailVal);
                if (!emailCheck.valid) {
                    e.preventDefault();
                    emailInput.focus();
                    showRestrictionPopup(emailCheck.msg, emailInput, emailCheck.title);
                    return;
                }

                const mismatchCheck = checkRegisteredUsernameMismatch(false);
                if (mismatchCheck.mismatch) {
                    e.preventDefault();
                    usernameInput.focus();
                    showRestrictionPopup(
                        "This email is already registered by a user, and the entered username does not match.",
                        usernameInput,
                        "Email Already Registered"
                    );
                    return;
                }

                if (!/^09[0-9]{9}$/.test(contactNumberInput.value)) {
                    e.preventDefault();
                    contactNumberInput.focus();
                    showRestrictionPopup("Please enter an 11-digit Philippine mobile number starting with 09.", contactNumberInput, "Invalid Contact Number");
                }
            });
        });
    </script>
</body>
</html>