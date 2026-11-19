<?php
session_start();
date_default_timezone_set("Asia/Manila");
require_once "db_connect.php";

// Hook for booking appointment creation & verification
$webhookUrl = "https://hook.us2.make.com/ch21y91yn7oblt5jo2d2y09qulq4bn46";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: appointment.php");
    exit;
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

function sendAppointmentWebhook(string $webhookUrl, array $payload): void
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

$fullName        = trim($_POST["fullName"] ?? "");
$email           = strtolower(trim($_POST["email"] ?? ""));
$contactNumber   = preg_replace("/[^0-9]/", "", trim($_POST["contactNumber"] ?? ""));
$service         = trim($_POST["service"] ?? "");
$appointmentDate = trim($_POST["appointmentDate"] ?? "");
$appointmentTime = trim($_POST["appointmentTime"] ?? "");
$consent         = isset($_POST["consent"]) ? 1 : 0;

$errors = [];

if ($fullName === "" || !preg_match("/^[A-Za-z\s]{2,}$/", $fullName)) {
    $errors[] = "Please enter a valid full name.";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
}
if (!preg_match("/^09[0-9]{9}$/", $contactNumber)) {
    $errors[] = "Please enter a valid 11-digit Philippine mobile number starting with 09.";
}
if (!in_array($service, $allowedServices, true)) {
    $errors[] = "Please select a valid dental service.";
}

$dateObj = DateTime::createFromFormat("Y-m-d", $appointmentDate);
if (!$dateObj || $dateObj->format("Y-m-d") !== $appointmentDate) {
    $errors[] = "Please select a valid appointment date.";
} else {
    $dateObj->setTime(0, 0, 0);
    $todayObj = new DateTime("today");
    $todayObj->setTime(0, 0, 0);

    $maxYear = (int)date("Y") + 1;
    $maxDate = DateTime::createFromFormat("Y-m-d", $maxYear . "-12-31");
    $maxDate->setTime(0, 0, 0);

    if ($dateObj < $todayObj) {
        $errors[] = "Appointment date cannot be in the past.";
    }
    if ($dateObj > $maxDate) {
        $errors[] = "Appointments can only be booked up to December 31, {$maxYear}.";
    }
    if ((int)$dateObj->format("N") === 4) {
        $errors[] = "Cebanico Dental Clinic is closed on Thursdays.";
    }
}

if ($consent !== 1) {
    $errors[] = "You must agree to the contact consent checkbox.";
}

if (!empty($errors)) {
    $_SESSION["booking_errors"] = $errors;
    header("Location: appointment.php");
    exit;
}

$dayOfWeek        = $dateObj->format("l");
$prettyDate       = $dateObj->format("l, F j, Y");
$verificationCode = (string)random_int(100000, 999999);
$timeParam        = $appointmentTime !== "" ? $appointmentTime : "10:00 AM";

$hasReqTime      = tableHasColumn($conn, "appointment_requests", "appointment_time");
$hasReqConsGiven = tableHasColumn($conn, "appointment_requests", "consent_given");
$hasReqCons      = tableHasColumn($conn, "appointment_requests", "consent");

$hasApptFullName = tableHasColumn($conn, "appointments", "full_name");
$hasApptTime     = tableHasColumn($conn, "appointments", "appointment_time");
$hasVerifCode    = tableHasColumn($conn, "appointments", "verification_code");
$hasIsVerif      = tableHasColumn($conn, "appointments", "is_verified");

$conn->begin_transaction();

try {
    // 1. Insert into appointment_requests
    if ($hasReqTime && $hasReqConsGiven && $hasReqCons) {
        $reqStmt = $conn->prepare(
            "INSERT INTO appointment_requests (full_name, email, contact_number, service, appointment_date, appointment_time, consent_given, consent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $reqStmt->bind_param("ssssssii", $fullName, $email, $contactNumber, $service, $appointmentDate, $timeParam, $consent, $consent);
    } elseif ($hasReqTime && $hasReqConsGiven) {
        $reqStmt = $conn->prepare(
            "INSERT INTO appointment_requests (full_name, email, contact_number, service, appointment_date, appointment_time, consent_given)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $reqStmt->bind_param("ssssssi", $fullName, $email, $contactNumber, $service, $appointmentDate, $timeParam, $consent);
    } elseif ($hasReqTime) {
        $reqStmt = $conn->prepare(
            "INSERT INTO appointment_requests (full_name, email, contact_number, service, appointment_date, appointment_time)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $reqStmt->bind_param("ssssss", $fullName, $email, $contactNumber, $service, $appointmentDate, $timeParam);
    } else {
        $reqStmt = $conn->prepare(
            "INSERT INTO appointment_requests (full_name, email, contact_number, service, appointment_date)
             VALUES (?, ?, ?, ?, ?)"
        );
        $reqStmt->bind_param("sssss", $fullName, $email, $contactNumber, $service, $appointmentDate);
    }

    if (!$reqStmt || !$reqStmt->execute()) {
        throw new Exception("Failed to save appointment request: " . $conn->error);
    }
    $requestId = (int)$conn->insert_id;
    $reqStmt->close();

    // 2. Insert into appointments
    if ($hasApptFullName && $hasApptTime && $hasVerifCode && $hasIsVerif) {
        $apptStmt = $conn->prepare(
            "INSERT INTO appointments (request_id, full_name, appointment_date, appointment_time, day_of_week, service, verification_code, is_verified, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'Pending')"
        );
        $apptStmt->bind_param("issssss", $requestId, $fullName, $appointmentDate, $timeParam, $dayOfWeek, $service, $verificationCode);
    } elseif ($hasApptTime && $hasVerifCode && $hasIsVerif) {
        $apptStmt = $conn->prepare(
            "INSERT INTO appointments (request_id, appointment_date, appointment_time, day_of_week, service, verification_code, is_verified, status)
             VALUES (?, ?, ?, ?, ?, ?, 0, 'Pending')"
        );
        $apptStmt->bind_param("isssss", $requestId, $appointmentDate, $timeParam, $dayOfWeek, $service, $verificationCode);
    } elseif ($hasVerifCode && $hasIsVerif) {
        $apptStmt = $conn->prepare(
            "INSERT INTO appointments (request_id, appointment_date, day_of_week, service, verification_code, is_verified, status)
             VALUES (?, ?, ?, ?, ?, 0, 'Pending')"
        );
        $apptStmt->bind_param("issss", $requestId, $appointmentDate, $dayOfWeek, $service, $verificationCode);
    } else {
        $apptStmt = $conn->prepare(
            "INSERT INTO appointments (request_id, appointment_date, day_of_week, service, status)
             VALUES (?, ?, ?, ?, 'Pending')"
        );
        $apptStmt->bind_param("isss", $requestId, $appointmentDate, $dayOfWeek, $service);
    }

    if (!$apptStmt || !$apptStmt->execute()) {
        throw new Exception("Failed to schedule appointment: " . $conn->error);
    }
    $appointmentId = (int)$conn->insert_id;
    $apptStmt->close();

    $conn->commit();

    $refNumber = "CDC-" . str_pad((string)$appointmentId, 4, "0", STR_PAD_LEFT);

    // 3. Trigger Make.com webhook with booking verification code
    sendAppointmentWebhook($webhookUrl, [
        "event_type"        => "appointment_created",
        "clinic_name"       => "Cebanico Dental Clinic",
        "reference_number"  => $refNumber,
        "appointment_id"    => $appointmentId,
        "request_id"        => $requestId,
        "full_name"         => $fullName,
        "email"             => $email,
        "to_email"          => $email,
        "contact_number"    => $contactNumber,
        "service"           => $service,
        "appointment_date"  => $appointmentDate,
        "appointment_time"  => $timeParam,
        "pretty_date"       => $prettyDate,
        "day_of_week"       => $dayOfWeek,
        "verification_code" => $verificationCode,
        "status"            => "Pending"
    ]);

    $conn->close();
    header("Location: verify-code.php?ref=" . urlencode($refNumber) . "&sent=1");
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    $_SESSION["booking_errors"] = [$e->getMessage()];
    header("Location: appointment.php");
    exit;
}