<?php
// InfinityFree Database Credentials
$host = "sqlXXX.infinityfree.com";         // Replace with your MySQL Host Name
$user = "if0_3xxxxxxx";                 // Replace with your MySQL User Name
$password = "your_infinityfree_password"; // Replace with your Account/Panel Password
$dbname = "if0_3xxxxxxx_cebanico_dental"; // Replace with your Full Database Name

// Create connection using MySQLi
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: uncomment below to test if it connects successfully
// echo "Connected successfully";
?>