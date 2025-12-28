<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Optionally, redirect to the landing page
header("Location: index.php");
exit;
?>