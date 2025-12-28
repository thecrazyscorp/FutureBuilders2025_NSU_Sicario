<?php
//session_start();
require 'db.php'; // your DB connection file

// Only patient can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit;
}

$patient_id = $_SESSION['user_id'];

// Optional: load specific case if provided
$case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;

// 1️⃣ Fetch latest case or specific case
if ($case_id) {
    $stmt = $conn->prepare("SELECT * FROM cases WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $case_id, $patient_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM cases WHERE patient_id=? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $patient_id);
}
$stmt->execute();
$case_result = $stmt->get_result();
$latest_case = $case_result->fetch_assoc();
$stmt->close();

// 2️⃣ Fetch all cases for sidebar history
$history_stmt = $conn->prepare("SELECT id, chief_complaint, severity, created_at FROM cases WHERE patient_id=? ORDER BY created_at DESC");
$history_stmt->bind_param("i", $patient_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$cases_history = $history_result->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

// 3️⃣ Fetch latest doctor reply if exists
$doctor_reply = null;
if ($latest_case) {
    $reply_stmt = $conn->prepare("
        SELECT cr.*, u.full_name AS doctor_name, dp.specialty
        FROM case_replies cr
        JOIN users u ON cr.doctor_id=u.id
        LEFT JOIN doctor_profiles dp ON dp.user_id=u.id
        WHERE cr.case_id=?
        ORDER BY cr.created_at DESC
        LIMIT 1
    ");
    $reply_stmt->bind_param("i", $latest_case['id']);
    $reply_stmt->execute();
    $reply_result = $reply_stmt->get_result();
    $doctor_reply = $reply_result->fetch_assoc();
    $reply_stmt->close();
}

// 4️⃣ Fetch referral if exists
$referral = null;
if ($latest_case) {
    $ref_stmt = $conn->prepare("
        SELECT c.name, c.phone, c.address
        FROM case_referrals r
        JOIN clinics c ON r.clinic_id=c.id
        WHERE r.case_id=?
        LIMIT 1
    ");
    $ref_stmt->bind_param("i", $latest_case['id']);
    $ref_stmt->execute();
    $ref_result = $ref_stmt->get_result();
    $referral = $ref_result->fetch_assoc();
    $ref_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Case Result | ShasthoBondhu</title>
<style>
* { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
body { margin:0; background:#f8fafc; color:#1f2937; }
.container { display: grid; grid-template-columns:280px 1fr; min-height:100vh; }
.sidebar { background:#fff; border-right:1px solid #e5e7eb; padding:20px; }
.sidebar h3 { margin-bottom:14px; color:#0f766e; }
.case-item { padding:12px; border-radius:10px; margin-bottom:10px; cursor:pointer; background:#f9fafb; }
.case-item:hover { background:#ecfeff; }
.case-item small { color:#6b7280; }
.main { padding:30px 40px; }
.patient-info { margin-bottom:20px; }
.patient-info h2 { margin-bottom:4px; }
.patient-info p { color:#6b7280; font-size:14px; }
.badge { display:inline-block; padding:6px 14px; border-radius:999px; font-weight:700; font-size:14px; margin-bottom:16px; }
.GREEN { background:#dcfce7; color:#166534; }
.YELLOW { background:#fef9c3; color:#854d0e; }
.RED { background:#fee2e2; color:#991b1b; }
.card { background:#fff; border-radius:16px; padding:22px; margin-bottom:20px; box-shadow:0 10px 20px rgba(0,0,0,0.05); }
.card h3 { margin-bottom:10px; color:#0f766e; }
.label { font-size:13px; color:#6b7280; margin-bottom:4px; }
.value { font-size:15px; line-height:1.5; }
.doctor-meta { font-size:14px; color:#374151; margin-bottom:10px; }
.navbar { display:flex; justify-content:space-between; align-items:center; padding:18px 8%; background:#ffffff; box-shadow:0 2px 10px rgba(0,0,0,0.05);}
.logo { font-size:22px; font-weight:700; color:#0f766e; }
.btn-logout { border:2px solid #0f766e; color:#0f766e; padding:8px 18px; border-radius:8px; text-decoration:none; font-weight:600;}
@media(max-width:900px){.container{grid-template-columns:1fr;}.sidebar{border-right:none;border-bottom:1px solid #e5e7eb;}}
</style>
</head>
<body>

<nav class="navbar">
    <div class="logo">🩺 ShasthoBondhu</div>
    <a href="logout.php" class="btn-logout">Logout</a>
</nav>

<div class="container">
    <aside class="sidebar">
        <h3>My Previous Cases</h3>
        <?php if(count($cases_history) > 0): ?>
            <?php foreach($cases_history as $c): ?>
                <div class="case-item" onclick="window.location='result.php?case_id=<?= $c['id'] ?>'">
                    <strong><?= htmlspecialchars($c['chief_complaint'] ?: 'No title') ?></strong><br>
                    <small><?= date('d M Y', strtotime($c['created_at'])) ?> • <?= $c['severity'] ?></small>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No cases submitted yet.</p>
        <?php endif; ?>
    </aside>

    <main class="main">

    <?php if($latest_case): ?>
        <div class="patient-info">
            <h2>Latest Case Result</h2>
            <p>Submitted on <?= date('d M Y', strtotime($latest_case['created_at'])) ?></p>
        </div>

        <span class="badge <?= $latest_case['severity'] ?>"><?= $latest_case['severity'] ?><?php if($latest_case['severity']=='RED') echo ' – Urgent'; ?></span>

        <div class="card">
            <h3>AI Triage Summary</h3>
            <div class="value"><?= htmlspecialchars($latest_case['ai_summary'] ?: 'No AI summary yet.') ?></div>
        </div>

        <div class="card">
            <h3>Why This Is Urgent</h3>
            <div class="value"><?= htmlspecialchars($latest_case['ai_explanation'] ?: 'No explanation available.') ?></div>
        </div>

        <?php if($doctor_reply): ?>
        <div class="card">
            <h3>Doctor’s Response</h3>
            <div class="doctor-meta">Dr. <?= htmlspecialchars($doctor_reply['doctor_name']) ?> • <?= htmlspecialchars($doctor_reply['specialty']) ?></div>
            <div class="label">Assessment</div>
            <div class="value"><?= htmlspecialchars($doctor_reply['diagnosis_summary']) ?></div>
            <div class="label" style="margin-top:12px;">Advice</div>
            <div class="value"><?= htmlspecialchars($doctor_reply['treatment_advice'] ?: 'No advice yet.') ?></div>
        </div>
        <?php else: ?>
        <div class="card">
            <h3>Doctor’s Response</h3>
            <div class="value">No doctor response yet.</div>
        </div>
        <?php endif; ?>

        <?php if($referral): ?>
        <div class="card">
            <h3>Referred Facility</h3>
            <div class="value">
                <?= htmlspecialchars($referral['name']) ?><br>
                <?= htmlspecialchars($referral['address']) ?><br>
                Phone: <?= htmlspecialchars($referral['phone']) ?>
            </div>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="card">
            <h3>No Cases Yet</h3>
            <div class="value">You have not submitted any cases yet. <a href="submit_case.php">Submit your first case</a>.</div>
        </div>
    <?php endif; ?>

    </main>
</div>
</body>
</html>
