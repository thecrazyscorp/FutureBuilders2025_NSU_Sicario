<?php
session_start();

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

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

$user_id = $_SESSION['user_id'];

// Get patient profile
$stmt = $pdo->prepare("
    SELECT u.*, p.* 
    FROM users u 
    LEFT JOIN patient_profiles p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Get patient cases with doctor info
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        d.full_name as doctor_name,
        dp.specialty as doctor_specialty
    FROM cases c
    LEFT JOIN users d ON c.assigned_doctor_id = d.id
    LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
    WHERE c.patient_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases WHERE patient_id = ?");
$stmt->execute([$user_id]);
$total_cases = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM cases WHERE patient_id = ? AND status IN ('submitted', 'assigned')");
$stmt->execute([$user_id]);
$pending_cases = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];

$stmt = $pdo->prepare("SELECT COUNT(*) as closed FROM cases WHERE patient_id = ? AND status = 'closed'");
$stmt->execute([$user_id]);
$closed_cases = $stmt->fetch(PDO::FETCH_ASSOC)['closed'];

// Function to get severity badge style
function getSeverityStyle($severity) {
    switch($severity) {
        case 'GREEN':
            return 'background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;';
        case 'YELLOW':
            return 'background: #fff9c4; color: #f57f17; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;';
        case 'RED':
            return 'background: #ffebee; color: #c62828; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;';
        default:
            return 'background: #e0e0e0; color: #424242; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;';
    }
}

// Function to get status badge style
function getStatusStyle($status) {
    switch($status) {
        case 'submitted':
            return 'background: #e3f2fd; color: #1565c0;';
        case 'assigned':
            return 'background: #fff9c4; color: #f57f17;';
        case 'doctor_replied':
            return 'background: #e8f5e9; color: #2e7d32;';
        case 'consult_requested':
            return 'background: #f3e5f5; color: #6a1b9a;';
        case 'referred':
            return 'background: #ffe0b2; color: #e65100;';
        case 'closed':
            return 'background: #e0e0e0; color: #424242;';
        default:
            return 'background: #e0e0e0; color: #424242;';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Healthcare Portal</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5;">

    <!-- Header -->
    <div style="background: linear-gradient(135deg, #4CAF50 0%, #2e7d32 100%); color: white; padding: 20px 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h1 style="margin: 0; font-size: 24px;">Healthcare Portal</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">Welcome, <?php echo htmlspecialchars($patient['full_name']); ?></p>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <a href="submit_case.php" style="background: white; color: #4CAF50; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">+ New Case</a>
                <a href="logout.php" style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">Logout</a>
            </div>
        </div>
    </div>

    <div style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">
        
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            
            <div style="background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #4CAF50;">
                <div style="color: #666; font-size: 14px; margin-bottom: 8px;">Total Cases</div>
                <div style="font-size: 32px; font-weight: 700; color: #2e7d32;"><?php echo $total_cases; ?></div>
            </div>

            <div style="background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #ff9800;">
                <div style="color: #666; font-size: 14px; margin-bottom: 8px;">Pending Cases</div>
                <div style="font-size: 32px; font-weight: 700; color: #e65100;"><?php echo $pending_cases; ?></div>
            </div>

            <div style="background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #9e9e9e;">
                <div style="color: #666; font-size: 14px; margin-bottom: 8px;">Closed Cases</div>
                <div style="font-size: 32px; font-weight: 700; color: #424242;"><?php echo $closed_cases; ?></div>
            </div>
        </div>

        <!-- Profile Section -->
        <div style="background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: #2e7d32; font-size: 20px;">My Profile</h2>
                <a href="edit_profile.php" style="color: #4CAF50; text-decoration: none; font-weight: 600; font-size: 14px;">Edit Profile</a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <div style="color: #666; font-size: 13px; margin-bottom: 5px;">Email</div>
                    <div style="color: #212121; font-weight: 500;"><?php echo htmlspecialchars($patient['email']); ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px; margin-bottom: 5px;">Phone</div>
                    <div style="color: #212121; font-weight: 500;"><?php echo htmlspecialchars($patient['phone']); ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px; margin-bottom: 5px;">Gender</div>
                    <div style="color: #212121; font-weight: 500;"><?php echo $patient['gender'] ? ucfirst($patient['gender']) : 'Not set'; ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px; margin-bottom: 5px;">Date of Birth</div>
                    <div style="color: #212121; font-weight: 500;"><?php echo $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'Not set'; ?></div>
                </div>
                <div>
                    <div style="color: #666; font-size: 13px; margin-bottom: 5px;">Location</div>
                    <div style="color: #212121; font-weight: 500;">
                        <?php 
                        $location = array_filter([$patient['upazila'], $patient['district']]);
                        echo $location ? implode(', ', $location) : 'Not set';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section (Cases with Doctor Replies) -->
        <?php
        // Get cases with doctor replies
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                d.full_name as doctor_name,
                dp.specialty as doctor_specialty,
                cr.diagnosis_summary,
                cr.treatment_advice,
                cr.action as reply_action,
                cr.created_at as reply_date
            FROM cases c
            INNER JOIN case_replies cr ON c.id = cr.case_id
            LEFT JOIN users d ON cr.doctor_id = d.id
            LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
            WHERE c.patient_id = ?
            ORDER BY cr.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <?php if (!empty($results)): ?>
        <div style="background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: #2e7d32; font-size: 20px;">Recent Results</h2>
                <a href="result.php" style="color: #4CAF50; text-decoration: none; font-weight: 600; font-size: 14px;">View All →</a>
            </div>
            
            <?php foreach ($results as $result): ?>
                <div style="border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 15px; transition: all 0.3s; cursor: pointer;" 
                     onmouseover="this.style.borderColor='#4CAF50'; this.style.backgroundColor='#f9fdf9'" 
                     onmouseout="this.style.borderColor='#e0e0e0'; this.style.backgroundColor='white'"
                     onclick="window.location.href='result.php?case_id=<?php echo $result['id']; ?>'">
                    
                    <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                        <div style="flex: 1; min-width: 250px;">
                            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 10px;">
                                <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                    ✓ REPLIED
                                </span>
                                <span style="<?php echo getSeverityStyle($result['severity']); ?>">
                                    <?php echo $result['severity']; ?>
                                </span>
                            </div>
                            
                            <h3 style="margin: 0 0 8px 0; color: #212121; font-size: 16px; font-weight: 600;">
                                <?php echo htmlspecialchars($result['chief_complaint']); ?>
                            </h3>
                            
                            <div style="color: #4CAF50; font-size: 14px; margin-bottom: 8px; font-weight: 600;">
                                Dr. <?php echo htmlspecialchars($result['doctor_name']); ?>
                                <span style="color: #999; font-weight: 400;">• <?php echo ucfirst($result['doctor_specialty']); ?></span>
                            </div>
                        </div>
                        
                        <div style="text-align: right;">
                            <div style="color: #666; font-size: 13px; margin-bottom: 3px;">
                                Replied on
                            </div>
                            <div style="color: #2e7d32; font-size: 13px; font-weight: 600;">
                                <?php echo date('M d, Y', strtotime($result['reply_date'])); ?>
                            </div>
                            <div style="color: #999; font-size: 12px;">
                                <?php echo date('h:i A', strtotime($result['reply_date'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: #f9fdf9; border-left: 3px solid #4CAF50; padding: 12px 15px; border-radius: 4px; margin-bottom: 10px;">
                        <div style="color: #2e7d32; font-size: 12px; font-weight: 600; margin-bottom: 5px;">DIAGNOSIS</div>
                        <div style="color: #424242; font-size: 14px; line-height: 1.5;">
                            <?php echo htmlspecialchars(substr($result['diagnosis_summary'], 0, 120)) . (strlen($result['diagnosis_summary']) > 120 ? '...' : ''); ?>
                        </div>
                    </div>
                    
                    <?php if ($result['treatment_advice']): ?>
                    <div style="background: #fffef5; border-left: 3px solid #ff9800; padding: 12px 15px; border-radius: 4px; margin-bottom: 10px;">
                        <div style="color: #e65100; font-size: 12px; font-weight: 600; margin-bottom: 5px;">TREATMENT ADVICE</div>
                        <div style="color: #424242; font-size: 14px; line-height: 1.5;">
                            <?php echo htmlspecialchars(substr($result['treatment_advice'], 0, 120)) . (strlen($result['treatment_advice']) > 120 ? '...' : ''); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                        <div style="font-size: 12px; color: #666;">
                            <?php
                            $action_labels = [
                                'advice_given' => '💊 Advice Given',
                                'request_consult' => '📞 Consult Requested',
                                'refer_facility' => '🏥 Referred to Facility',
                                'close_case' => '✓ Case Closed'
                            ];
                            echo $action_labels[$result['reply_action']] ?? 'Action Taken';
                            ?>
                        </div>
                        <span style="color: #4CAF50; font-weight: 600; font-size: 14px;">View Full Result →</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Cases Section -->
        <div style="background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h2 style="margin: 0 0 20px 0; color: #2e7d32; font-size: 20px;">My Cases</h2>
            
            <?php if (empty($cases)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📋</div>
                    <div style="font-size: 16px; margin-bottom: 10px;">No cases yet</div>
                    <div style="font-size: 14px; margin-bottom: 20px;">Submit your first case to get medical advice</div>
                    <a href="submit_case.php" style="display: inline-block; background: #4CAF50; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;">Submit Case</a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <?php foreach ($cases as $case): ?>
                        <div style="border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 15px; transition: border-color 0.3s; cursor: pointer;" 
                             onmouseover="this.style.borderColor='#4CAF50'" 
                             onmouseout="this.style.borderColor='#e0e0e0'"
                             onclick="window.location.href='case_details.php?id=<?php echo $case['id']; ?>'">
                            
                            <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
                                <div style="flex: 1; min-width: 250px;">
                                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                                        <span style="<?php echo getSeverityStyle($case['severity']); ?>">
                                            <?php echo $case['severity']; ?>
                                        </span>
                                        <span style="<?php echo getStatusStyle($case['status']); ?> padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                            <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                        </span>
                                    </div>
                                    
                                    <h3 style="margin: 0 0 10px 0; color: #212121; font-size: 16px;">
                                        <?php echo htmlspecialchars($case['chief_complaint']); ?>
                                    </h3>
                                    
                                    <div style="color: #666; font-size: 14px; margin-bottom: 5px;">
                                        <strong>Suggested Specialty:</strong> <?php echo ucfirst($case['suggested_specialty']); ?>
                                    </div>
                                    
                                    <?php if ($case['doctor_name']): ?>
                                        <div style="color: #666; font-size: 14px;">
                                            <strong>Assigned Doctor:</strong> Dr. <?php echo htmlspecialchars($case['doctor_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="text-align: right;">
                                    <div style="color: #666; font-size: 13px; margin-bottom: 5px;">
                                        <?php echo date('M d, Y', strtotime($case['created_at'])); ?>
                                    </div>
                                    <div style="color: #999; font-size: 12px;">
                                        <?php echo date('h:i A', strtotime($case['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($case['ai_summary']): ?>
                                <div style="margin-top: 15px; padding: 12px; background: #f5f5f5; border-radius: 6px; font-size: 14px; color: #424242;">
                                    <strong>AI Summary:</strong> <?php echo htmlspecialchars(substr($case['ai_summary'], 0, 150)) . (strlen($case['ai_summary']) > 150 ? '...' : ''); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 15px; text-align: right;">
                                <span style="color: #4CAF50; font-weight: 600; font-size: 14px;">View Details →</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div style="background: white; border-top: 1px solid #e0e0e0; padding: 20px; text-align: center; color: #666; font-size: 14px; margin-top: 50px;">
        <p style="margin: 0;">© 2024 Healthcare Portal. All rights reserved.</p>
    </div>

</body>
</html>