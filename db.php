<?php
// DB credentials
$host = "localhost";        // usually localhost
$db_name = "rhai"; //placeholder name
$db_user = "root";          // change if needed
$db_pass = "";              // change if you have a password

// Create connection
$conn = new mysqli($host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for Bangla & emoji support
$conn->set_charset("utf8mb4");

// Optional: set timezone for consistent timestamps
date_default_timezone_set('Asia/Dhaka');

// Now $conn can be used in your PHP pages
?>
