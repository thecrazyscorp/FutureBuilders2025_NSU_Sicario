<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "rural_health_ai";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed");
}
//session_start();
?>
