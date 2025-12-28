<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'rural_health_ai';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = trim($_POST['login_identifier']);
    $password = $_POST['password'];
    
    if (empty($login_identifier) || empty($password)) {
        $_SESSION['error'] = "Please enter both email/phone and password";
        header("Location: login.php");
        exit();
    }
    
    try {
        // Check if identifier is email or phone
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR phone = ?) AND is_active = 1");
        $stmt->execute([$login_identifier, $login_identifier]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            
            // Redirect based on role
            if ($user['role'] === 'patient') {
                header("Location: patient_dashboard.php");
            } elseif ($user['role'] === 'doctor') {
                header("Location: doctor_dashboard.php");
            } elseif ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            }
            exit();
            
        } else {
            $_SESSION['error'] = "Invalid email/phone or password";
            header("Location: login.php");
            exit();
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Login failed: " . $e->getMessage();
        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Healthcare Portal</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center;">

    <div style="background: white; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); width: 90%; max-width: 450px; padding: 40px; margin: 20px;">
        
        <h2 style="color: #2e7d32; margin-bottom: 10px; text-align: center;">Welcome Back</h2>
        <p style="text-align: center; color: #666; margin-bottom: 25px;">Don't have an account? <a href="register.php" style="color: #4CAF50; text-decoration: none; font-weight: 600;">Register here</a></p>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #c62828;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #4CAF50;">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Email or Phone</label>
                <input type="text" name="login_identifier" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#4CAF50'" onblur="this.style.borderColor='#e0e0e0'" placeholder="Enter your email or phone">
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Password</label>
                <input type="password" name="password" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#4CAF50'" onblur="this.style.borderColor='#e0e0e0'" placeholder="Enter your password">
            </div>
            
            <button type="submit" style="width: 100%; padding: 14px; background: #4CAF50; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s;" onmouseover="this.style.background='#45a049'" onmouseout="this.style.background='#4CAF50'">Login</button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="#" style="color: #4CAF50; text-decoration: none; font-size: 14px;">Forgot Password?</a>
        </div>
    </div>

</body>
</html>