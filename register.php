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
    // Get common fields
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate required fields
    if (empty($full_name) || empty($role) || empty($phone) || empty($email) || empty($password)) {
        $_SESSION['error'] = "All required fields must be filled";
        header("Location: register.php");
        exit();
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert into users table
        $stmt = $pdo->prepare("INSERT INTO users (role, full_name, phone, email, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$role, $full_name, $phone, $email, $password_hash]);
        
        $user_id = $pdo->lastInsertId();
        
        // Insert role-specific profile
        if ($role === 'patient') {
            $gender = !empty($_POST['gender']) ? $_POST['gender'] : NULL;
            $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : NULL;
            $district = !empty($_POST['district']) ? trim($_POST['district']) : NULL;
            $upazila = !empty($_POST['upazila']) ? trim($_POST['upazila']) : NULL;
            $address_line = !empty($_POST['address_line']) ? trim($_POST['address_line']) : NULL;
            $emergency_contact_name = !empty($_POST['emergency_contact_name']) ? trim($_POST['emergency_contact_name']) : NULL;
            $emergency_contact_phone = !empty($_POST['emergency_contact_phone']) ? trim($_POST['emergency_contact_phone']) : NULL;
            
            $stmt = $pdo->prepare("INSERT INTO patient_profiles (user_id, gender, date_of_birth, district, upazila, address_line, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $gender, $date_of_birth, $district, $upazila, $address_line, $emergency_contact_name, $emergency_contact_phone]);
            
        } elseif ($role === 'doctor') {
            $specialty = !empty($_POST['specialty']) ? $_POST['specialty'] : 'general';
            $license_no = !empty($_POST['license_no']) ? trim($_POST['license_no']) : NULL;
            $workplace = !empty($_POST['workplace']) ? trim($_POST['workplace']) : NULL;
            $bio = !empty($_POST['bio']) ? trim($_POST['bio']) : NULL;
            $consultation_fee = !empty($_POST['consultation_fee']) ? $_POST['consultation_fee'] : NULL;
            
            $stmt = $pdo->prepare("INSERT INTO doctor_profiles (user_id, specialty, license_no, workplace, bio, consultation_fee) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $specialty, $license_no, $workplace, $bio, $consultation_fee]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Registration successful! Please login.";
        header("Location: login.php");
        exit();
        
    } catch(PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        if ($e->getCode() == 23000) {
            $_SESSION['error'] = "Email or phone already exists";
        } else {
            $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        }
        header("Location: register.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Healthcare Portal</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center;">

    <div style="background: white; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); width: 90%; max-width: 500px; padding: 40px; margin: 20px;">
        
        <h2 style="color: #2e7d32; margin-bottom: 10px; text-align: center;">Create Account</h2>
        <p style="text-align: center; color: #666; margin-bottom: 25px;">Already have an account? <a href="login.php" style="color: #4CAF50; text-decoration: none; font-weight: 600;">Login here</a></p>

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

        <form method="POST" action="register.php">
            
            <!-- Common Fields -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Full Name *</label>
                <input type="text" name="full_name" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#4CAF50'" onblur="this.style.borderColor='#e0e0e0'">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Role *</label>
                <select name="role" id="roleSelect" onchange="toggleRoleFields()" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border 0.3s; box-sizing: border-box; cursor: pointer;" onfocus="this.style.borderColor='#4CAF50'" onblur="this.style.borderColor='#e0e0e0'">
                    <option value="">Select Role</option>
                    <option value="patient">Patient</option>
                    <option value="doctor">Doctor</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Phone *</label>
                <input type="tel" name="phone" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#4CAF50'" onblur="this.style.borderColor='#e0e0e0'">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Email *</label>
                <input type="email" name="email" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#4CAF50'" onblur="this.style.borderColor='#e0e0e0'">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Password *</label>
                <input type="password" name="password" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#4CAF50'" onblur="this.style.borderColor='#e0e0e0'">
            </div>

            <!-- Patient Specific Fields -->
            <div id="patientFields" style="display: none; background: #f1f8f4; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #2e7d32; margin-top: 0; margin-bottom: 15px; font-size: 16px;">Patient Information</h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Gender</label>
                    <select name="gender" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Date of Birth</label>
                    <input type="date" name="date_of_birth" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">District</label>
                    <input type="text" name="district" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Upazila</label>
                    <input type="text" name="upazila" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Address</label>
                    <textarea name="address_line" rows="2" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box; resize: vertical; font-family: inherit;"></textarea>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 0;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Emergency Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <!-- Doctor Specific Fields -->
            <div id="doctorFields" style="display: none; background: #f1f8f4; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #2e7d32; margin-top: 0; margin-bottom: 15px; font-size: 16px;">Doctor Information</h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Specialty *</label>
                    <select name="specialty" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <option value="general">General</option>
                        <option value="pediatrics">Pediatrics</option>
                        <option value="gynecology">Gynecology</option>
                        <option value="dermatology">Dermatology</option>
                        <option value="cardiology">Cardiology</option>
                        <option value="ent">ENT</option>
                        <option value="medicine">Medicine</option>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">License Number</label>
                    <input type="text" name="license_no" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Workplace</label>
                    <input type="text" name="workplace" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Bio</label>
                    <textarea name="bio" rows="3" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box; resize: vertical; font-family: inherit;"></textarea>
                </div>

                <div style="margin-bottom: 0;">
                    <label style="display: block; margin-bottom: 8px; color: #424242; font-weight: 500;">Consultation Fee</label>
                    <input type="number" name="consultation_fee" step="0.01" placeholder="0.00" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <button type="submit" style="width: 100%; padding: 14px; background: #4CAF50; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s;" onmouseover="this.style.background='#45a049'" onmouseout="this.style.background='#4CAF50'">Register</button>
        </form>
    </div>

    <script>
        function toggleRoleFields() {
            const role = document.getElementById('roleSelect').value;
            const patientFields = document.getElementById('patientFields');
            const doctorFields = document.getElementById('doctorFields');

            if (role === 'patient') {
                patientFields.style.display = 'block';
                doctorFields.style.display = 'none';
            } else if (role === 'doctor') {
                patientFields.style.display = 'none';
                doctorFields.style.display = 'block';
            } else {
                patientFields.style.display = 'none';
                doctorFields.style.display = 'none';
            }
        }
    </script>
</body>
</html>