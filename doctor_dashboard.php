<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: login.php');
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'rural_health_ai');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$doctor_id = $_SESSION['user_id'];

// Fetch doctor profile
$doctor_query = "SELECT u.full_name, u.email, u.phone, 
                        dp.specialty, dp.workplace, dp.bio, dp.consultation_fee, dp.is_available
                 FROM users u
                 JOIN doctor_profiles dp ON u.id = dp.user_id
                 WHERE u.id = ?";
$stmt = $conn->prepare($doctor_query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_availability'])) {
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $update = $conn->prepare("UPDATE doctor_profiles SET is_available = ? WHERE user_id = ?");
        $update->bind_param("ii", $is_available, $doctor_id);
        $update->execute();
        header('Location: doctor_dashboard.php');
        exit();
    }
    
    if (isset($_POST['send_reply'])) {
        $case_id = $_POST['case_id'];
        $diagnosis = $_POST['diagnosis_summary'];
        $treatment = $_POST['treatment_advice'];
        $prescription = $_POST['prescription_note'];
        $action = $_POST['action'];
        
        // Insert reply
        $reply_stmt = $conn->prepare("INSERT INTO case_replies (case_id, doctor_id, diagnosis_summary, treatment_advice, prescription_note, action) VALUES (?, ?, ?, ?, ?, ?)");
        $reply_stmt->bind_param("iissss", $case_id, $doctor_id, $diagnosis, $treatment, $prescription, $action);
        $reply_stmt->execute();
        
        // Update case status
        $new_status = 'doctor_replied';
        if ($action === 'request_consult') {
            $new_status = 'consult_requested';
        } elseif ($action === 'refer_facility') {
            $new_status = 'referred';
        } elseif ($action === 'close_case') {
            $new_status = 'closed';
        }
        
        $update_case = $conn->prepare("UPDATE cases SET status = ?, assigned_doctor_id = ? WHERE id = ?");
        $update_case->bind_param("sii", $new_status, $doctor_id, $case_id);
        $update_case->execute();
        
        header('Location: doctor_dashboard.php?success=1');
        exit();
    }
}

// Fetch pending cases (submitted or assigned to this doctor)
$cases_query = "SELECT c.id, c.patient_id, c.chief_complaint, c.symptoms, c.additional_notes,
                       c.severity, c.suggested_specialty, c.red_flags, c.ai_summary, c.ai_explanation,
                       c.status, c.created_at,
                       u.full_name as patient_name, u.phone as patient_phone,
                       pp.gender, pp.date_of_birth, pp.district, pp.upazila
                FROM cases c
                JOIN users u ON c.patient_id = u.id
                LEFT JOIN patient_profiles pp ON c.patient_id = pp.user_id
                WHERE (c.assigned_doctor_id = ? OR (c.status = 'submitted' AND c.suggested_specialty = ?))
                AND c.status NOT IN ('closed')
                ORDER BY 
                    CASE c.severity 
                        WHEN 'RED' THEN 1 
                        WHEN 'YELLOW' THEN 2 
                        WHEN 'GREEN' THEN 3 
                    END,
                    c.created_at ASC";
$stmt = $conn->prepare($cases_query);
$stmt->bind_param("is", $doctor_id, $doctor['specialty']);
$stmt->execute();
$cases = $stmt->get_result();

// Get statistics - FIXED QUERY
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN severity = 'RED' THEN 1 ELSE 0 END) as red_count,
                    SUM(CASE WHEN severity = 'YELLOW' THEN 1 ELSE 0 END) as yellow_count,
                    SUM(CASE WHEN severity = 'GREEN' THEN 1 ELSE 0 END) as green_count
                FROM cases 
                WHERE (assigned_doctor_id = ? OR (status = 'submitted' AND suggested_specialty = ?))
                AND status NOT IN ('closed')";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("is", $doctor_id, $doctor['specialty']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Function to get patient medical history
function getPatientHistory($conn, $patient_id) {
    $history_query = "SELECT c.id, c.chief_complaint, c.severity, c.status, c.created_at,
                             c.ai_summary, c.symptoms, c.red_flags,
                             GROUP_CONCAT(
                                 CONCAT(
                                     cr.created_at, '||',
                                     u.full_name, '||',
                                     cr.diagnosis_summary, '||',
                                     cr.treatment_advice, '||',
                                     cr.action
                                 ) ORDER BY cr.created_at DESC SEPARATOR '::::'
                             ) as replies
                      FROM cases c
                      LEFT JOIN case_replies cr ON c.id = cr.case_id
                      LEFT JOIN users u ON cr.doctor_id = u.id
                      WHERE c.patient_id = ?
                      GROUP BY c.id
                      ORDER BY c.created_at DESC
                      LIMIT 10";
    
    $stmt = $conn->prepare($history_query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    return $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Doctor Dashboard | ShasthoBondhu</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}

body {
    background: #f8fafc;
    color: #1f2937;
}

/* Navbar */
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 8%;
    background: #ffffff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.logo {
    font-size: 22px;
    font-weight: 700;
    color: #0f766e;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.doctor-name {
    font-weight: 600;
    color: #374151;
}

.btn-logout {
    border: 2px solid #dc2626;
    color: #dc2626;
    padding: 8px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}

/* Container */
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 8%;
}

/* Profile Card */
.profile-card {
    background: white;
    border-radius: 18px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.profile-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.profile-info h2 {
    font-size: 28px;
    color: #0f766e;
    margin-bottom: 10px;
}

.specialty-badge {
    display: inline-block;
    background: #ecfeff;
    color: #0f766e;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-transform: capitalize;
}

.availability-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
}

.availability-toggle label {
    font-weight: 600;
    color: #374151;
}

.toggle-switch {
    position: relative;
    width: 50px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: .4s;
    border-radius: 26px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #0f766e;
}

input:checked + .slider:before {
    transform: translateX(24px);
}

.profile-details {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.detail-item p:first-child {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 4px;
}

.detail-item p:last-child {
    font-weight: 600;
    color: #1f2937;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 24px;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-align: center;
}

.stat-number {
    font-size: 36px;
    font-weight: 700;
    color: #0f766e;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 600;
}

.stat-card.red .stat-number { color: #dc2626; }
.stat-card.yellow .stat-number { color: #f59e0b; }
.stat-card.green .stat-number { color: #10b981; }

/* Cases Section */
.cases-section {
    background: white;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.section-header {
    font-size: 24px;
    color: #0f766e;
    margin-bottom: 24px;
}

.case-card {
    background: #f9fafb;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    border-left: 5px solid #cbd5e1;
}

.case-card.red { border-left-color: #dc2626; }
.case-card.yellow { border-left-color: #f59e0b; }
.case-card.green { border-left-color: #10b981; }

.case-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.patient-info h3 {
    font-size: 18px;
    color: #1f2937;
    margin-bottom: 6px;
}

.patient-meta {
    font-size: 13px;
    color: #6b7280;
}

.severity-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
}

.severity-badge.red {
    background: #fee2e2;
    color: #dc2626;
}

.severity-badge.yellow {
    background: #fef3c7;
    color: #f59e0b;
}

.severity-badge.green {
    background: #d1fae5;
    color: #10b981;
}

.case-content {
    margin-bottom: 16px;
}

.case-section {
    margin-bottom: 14px;
}

.case-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.case-text {
    color: #374151;
    line-height: 1.6;
}

.ai-summary {
    background: #ecfeff;
    padding: 14px;
    border-radius: 8px;
    margin-top: 12px;
}

.ai-summary .case-label {
    color: #0f766e;
}

.red-flags {
    background: #fee2e2;
    padding: 14px;
    border-radius: 8px;
    margin-top: 12px;
}

.red-flags .case-label {
    color: #dc2626;
}

.case-actions {
    display: flex;
    gap: 12px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: #0f766e;
    color: white;
}

.btn-secondary {
    background: #e5e7eb;
    color: #374151;
}

.btn-history {
    background: #dbeafe;
    color: #1e40af;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background-color: white;
    margin: 40px auto;
    padding: 40px;
    border-radius: 18px;
    width: 90%;
    max-width: 900px;
    max-height: 85vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.modal-header h3 {
    font-size: 24px;
    color: #0f766e;
}

.close {
    font-size: 32px;
    font-weight: 700;
    color: #6b7280;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    color: #1f2937;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #0f766e;
}

.success-message {
    background: #d1fae5;
    color: #065f46;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 600;
}

/* Medical History Styles */
.history-timeline {
    margin-top: 24px;
}

.history-item {
    background: #f9fafb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    border-left: 4px solid #cbd5e1;
}

.history-item.red { border-left-color: #dc2626; }
.history-item.yellow { border-left-color: #f59e0b; }
.history-item.green { border-left-color: #10b981; }

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.history-date {
    font-size: 13px;
    color: #6b7280;
    font-weight: 600;
}

.history-complaint {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}

.reply-section {
    background: white;
    padding: 14px;
    border-radius: 8px;
    margin-top: 12px;
    border-left: 3px solid #0f766e;
}

.reply-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.reply-doctor {
    font-weight: 600;
    color: #0f766e;
    font-size: 14px;
}

.reply-date {
    font-size: 12px;
    color: #6b7280;
}

.reply-diagnosis {
    color: #374151;
    line-height: 1.6;
    margin-bottom: 8px;
}

.action-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 8px;
}

.action-badge.advice_given {
    background: #d1fae5;
    color: #065f46;
}

.action-badge.request_consult {
    background: #dbeafe;
    color: #1e40af;
}

.action-badge.refer_facility {
    background: #fef3c7;
    color: #92400e;
}

.action-badge.close_case {
    background: #e5e7eb;
    color: #374151;
}

.no-history {
    text-align: center;
    color: #6b7280;
    padding: 40px;
    font-style: italic;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .profile-details {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-details {
        grid-template-columns: 1fr;
    }
    
    .case-header {
        flex-direction: column;
        gap: 12px;
    }
    
    .case-actions {
        flex-direction: column;
    }
    
    .modal-content {
        margin: 20px;
        padding: 24px;
        width: calc(100% - 40px);
    }
}
</style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="logo">🩺 ShasthoBondhu</div>
    <div class="nav-right">
        <span class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></span>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">
    <?php if (isset($_GET['success'])): ?>
    <div class="success-message">
        ✓ Reply sent successfully! Case updated.
    </div>
    <?php endif; ?>

    <!-- Profile Card -->
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-info">
                <h2>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h2>
                <span class="specialty-badge"><?php echo htmlspecialchars($doctor['specialty']); ?></span>
            </div>
            
            <form method="POST" class="availability-toggle">
                <label>Available:</label>
                <label class="toggle-switch">
                    <input type="checkbox" name="is_available" <?php echo $doctor['is_available'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <span class="slider"></span>
                </label>
                <input type="hidden" name="update_availability" value="1">
            </form>
        </div>
        
        <div class="profile-details">
            <div class="detail-item">
                <p>Email</p>
                <p><?php echo htmlspecialchars($doctor['email']); ?></p>
            </div>
            <div class="detail-item">
                <p>Phone</p>
                <p><?php echo htmlspecialchars($doctor['phone'] ?: 'Not set'); ?></p>
            </div>
            <div class="detail-item">
                <p>Workplace</p>
                <p><?php echo htmlspecialchars($doctor['workplace'] ?: 'Not set'); ?></p>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Active Cases</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo $stats['red_count']; ?></div>
            <div class="stat-label">Urgent (Red)</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-number"><?php echo $stats['yellow_count']; ?></div>
            <div class="stat-label">Moderate (Yellow)</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo $stats['green_count']; ?></div>
            <div class="stat-label">Minor (Green)</div>
        </div>
    </div>

    <!-- Cases List -->
    <div class="cases-section">
        <h2 class="section-header">Pending Cases</h2>
        
        <?php if ($cases->num_rows === 0): ?>
            <p style="text-align: center; color: #6b7280; padding: 40px;">No pending cases at the moment.</p>
        <?php else: ?>
            <?php while ($case = $cases->fetch_assoc()): ?>
                <?php 
                $symptoms = json_decode($case['symptoms'], true);
                $red_flags = json_decode($case['red_flags'], true);
                $age = '';
                if ($case['date_of_birth']) {
                    $dob = new DateTime($case['date_of_birth']);
                    $now = new DateTime();
                    $age = $dob->diff($now)->y . ' years';
                }
                ?>
                
                <div class="case-card <?php echo strtolower($case['severity']); ?>">
                    <div class="case-header">
                        <div class="patient-info">
                            <h3><?php echo htmlspecialchars($case['patient_name']); ?></h3>
                            <p class="patient-meta">
                                <?php echo $age ? $age . ' | ' : ''; ?>
                                <?php echo htmlspecialchars($case['gender'] ?: ''); ?>
                                <?php echo $case['district'] ? ' | ' . htmlspecialchars($case['district']) : ''; ?>
                                <?php echo $case['upazila'] ? ', ' . htmlspecialchars($case['upazila']) : ''; ?>
                                <br>
                                Phone: <?php echo htmlspecialchars($case['patient_phone']); ?> | 
                                Submitted: <?php echo date('M d, Y h:i A', strtotime($case['created_at'])); ?>
                            </p>
                        </div>
                        <span class="severity-badge <?php echo strtolower($case['severity']); ?>">
                            <?php echo $case['severity']; ?>
                        </span>
                    </div>
                    
                    <div class="case-content">
                        <?php if ($case['chief_complaint']): ?>
                        <div class="case-section">
                            <div class="case-label">Chief Complaint</div>
                            <div class="case-text"><?php echo htmlspecialchars($case['chief_complaint']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($symptoms)): ?>
                        <div class="case-section">
                            <div class="case-label">Symptoms Reported</div>
                            <div class="case-text">
                                <?php 
                                $symptom_list = [];
                                foreach ($symptoms as $key => $value) {
                                    if ($value === true || $value === 'yes' || $value === 1) {
                                        $symptom_list[] = ucfirst(str_replace('_', ' ', $key));
                                    } elseif (is_string($value) && $value !== 'no') {
                                        $symptom_list[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
                                    }
                                }
                                echo implode(', ', $symptom_list);
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($case['ai_summary']): ?>
                        <div class="ai-summary">
                            <div class="case-label">AI Assessment</div>
                            <div class="case-text"><?php echo nl2br(htmlspecialchars($case['ai_summary'])); ?></div>
                            <?php if ($case['ai_explanation']): ?>
                                <div class="case-text" style="margin-top: 8px; font-weight: 600;">
                                    <?php echo nl2br(htmlspecialchars($case['ai_explanation'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($red_flags) && is_array($red_flags)): ?>
                        <div class="red-flags">
                            <div class="case-label">⚠ Red Flags Detected</div>
                            <div class="case-text">
                                <?php echo implode(', ', array_map('htmlspecialchars', $red_flags)); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="case-actions">
                        <button class="btn btn-primary" onclick="openReplyModal(<?php echo $case['id']; ?>, '<?php echo htmlspecialchars($case['patient_name'], ENT_QUOTES); ?>')">
                            Send Reply
                        </button>
                        <button class="btn btn-history" onclick="openHistoryModal(<?php echo $case['patient_id']; ?>, '<?php echo htmlspecialchars($case['patient_name'], ENT_QUOTES); ?>')">
                            Medical History
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>



<!-- Reply Modal -->
<div id="replyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Send Reply to <span id="modalPatientName"></span></h3>
            <span class="close" onclick="closeReplyModal()">&times;</span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="case_id" id="modalCaseId">
            
            <div class="form-group">
                <label>Diagnosis Summary *</label>
                <textarea name="diagnosis_summary" required placeholder="Your assessment of the patient's condition..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Treatment Advice</label>
                <textarea name="treatment_advice" placeholder="Recommended treatment, medication, lifestyle changes..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Prescription Notes</label>
                <textarea name="prescription_note" placeholder="Generic prescription information (keep it safe and general for hackathon)..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Action *</label>
                <select name="action" required>
                    <option value="advice_given">Advice Given (Close after review)</option>
                    <option value="request_consult">Request In-Person Consultation</option>
                    <option value="refer_facility">Refer to Medical Facility</option>
                    <option value="close_case">Close Case (Resolved)</option>
                </select>
            </div>
            
            <div class="case-actions">
                <button type="submit" name="send_reply" class="btn btn-primary">Send Reply</button>
                <button type="button" class="btn btn-secondary" onclick="closeReplyModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Medical History Modal -->
<div id="historyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Medical History: <span id="historyPatientName"></span></h3>
            <span class="close" onclick="closeHistoryModal()">&times;</span>
        </div>
        
        <div id="historyContent">
            <p style="text-align: center; color: #6b7280; padding: 20px;">Loading history...</p>
        </div>
    </div>
</div>

<script>
function openReplyModal(caseId, patientName) {
    document.getElementById('modalCaseId').value = caseId;
    document.getElementById('modalPatientName').textContent = patientName;
    document.getElementById('replyModal').style.display = 'block';
}

function closeReplyModal() {
    document.getElementById('replyModal').style.display = 'none';
}

function openHistoryModal(patientId, patientName) {
    document.getElementById('historyPatientName').textContent = patientName;
    document.getElementById('historyModal').style.display = 'block';
    
    // Load patient history via AJAX
    fetch('getpatienthistory.php?patient_id=' + patientId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('historyContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('historyContent').innerHTML = 
                '<p class="no-history">Error loading medical history. Please try again.</p>';
        });
}

function closeHistoryModal() {
    document.getElementById('historyModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const replyModal = document.getElementById('replyModal');
    const historyModal = document.getElementById('historyModal');
    if (event.target == replyModal) {
        closeReplyModal();
    }
    if (event.target == historyModal) {
        closeHistoryModal();
    }
}
</script>

</body>
</html>

<?php
$conn->close();
?>