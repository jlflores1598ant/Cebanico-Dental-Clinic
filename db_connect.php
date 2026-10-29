<?php
// Database connection settings (XAMPP defaults)
$db_host = "localhost";
$db_user = "root";          // default XAMPP username
$db_pass = "";              // default XAMPP password is EMPTY
$db_name = "cebanico_dental";

// Make MySQL errors throw exceptions so we can catch them
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Do not show technical details to visitors
    error_log("DB connection failed: " . $e->getMessage());
    die("Sorry, we are unable to process requests right now. Please try again later.");
}
?>