<?php
// InfinityFree Database Connection
$host     = "sql102.infinityfree.com"; // <-- REPLACE WITH YOUR ACTUAL MYSQL HOST (e.g., sql300.infinityfree.com)
$user     = "if0_42990683";            // Your InfinityFree Database Username
$password = "Raye0913";     // <-- REPLACE WITH YOUR INFINITYFREE ACCOUNT/PANEL PASSWORD
$dbname   = "if0_42990683_Cebanicodc";   // Your Exact Database Name

// Create connection using MySQLi
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // If connection fails, show the error safely instead of a silent 500 crash
    die("Database Connection Failed: " . $conn->connect_error);
}
?>