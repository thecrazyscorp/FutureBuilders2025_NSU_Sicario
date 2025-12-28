<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare(
        "INSERT INTO users (role, full_name, email, password_hash) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssss", $role, $name, $email, $password);
    $stmt->execute();

    $user_id = $stmt->insert_id;

    if ($role === 'patient') {
        $conn->query("INSERT INTO patient_profiles (user_id) VALUES ($user_id)");
    } else if ($role === 'doctor') {
        $conn->query("INSERT INTO doctor_profiles (user_id) VALUES ($user_id)");
    }

    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="style.css">
<title>Register</title>
</head>
<body>

<div class="container">
<h2>Register</h2>

<form method="POST">
  <select name="role" required>
    <option value="patient">Patient</option>
    <option value="doctor">Doctor</option>
  </select>

  <input type="text" name="full_name" placeholder="Full Name" required>
  <input type="email" name="email" placeholder="Email" required>
  <input type="password" name="password" placeholder="Password" required>

  <button type="submit">Create Account</button>
</form>

<p style="text-align:center;margin-top:10px;">
  <a href="login.php">Already have an account?</a>
</p>
</div>

</body>
</html>
