<?php
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo '<p class="no-history">Access denied.</p>';
    exit();
}

// Check if patient_id is provided
if (!isset($_GET['patient_id']) || !is_numeric($_GET['patient_id'])) {
    echo '<p class="no-history">Invalid patient ID.</p>';
    exit();
}

$patient_id = intval($_GET['patient_id']);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'rural_health_ai');
if ($conn->connect_error) {
    echo '<p class="no-history">Database connection error.</p>';
    exit();
}

// Fetch patient's medical history with all replies
$history_query = "SELECT c.id, c.chief_complaint, c.severity, c.status, c.created_at,
                         c.ai_summary, c.symptoms, c.red_flags, c.additional_notes
                  FROM cases c
                  WHERE c.patient_id = ?
                  ORDER BY c.created_at DESC
                  LIMIT 15";

$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$cases = $stmt->get_result();

if ($cases->num_rows === 0) {
    echo '<p class="no-history">No medical history found for this patient.</p>';
    $conn->close();
    exit();
}

echo '<div class="history-timeline">';

while ($case = $cases->fetch_assoc()) {
    $symptoms = json_decode($case['symptoms'], true);
    $red_flags = json_decode($case['red_flags'], true);
    $severity_class = strtolower($case['severity']);
    
    echo '<div class="history-item ' . $severity_class . '">';
    echo '<div class="history-header">';
    echo '<div>';
    echo '<div class="history-date">📅 ' . date('M d, Y h:i A', strtotime($case['created_at'])) . '</div>';
    echo '<div class="history-complaint">' . htmlspecialchars($case['chief_complaint'] ?: 'Case #' . $case['id']) . '</div>';
    echo '</div>';
    echo '<span class="severity-badge ' . $severity_class . '">' . $case['severity'] . '</span>';
    echo '</div>';
    
    // Display symptoms if available
    if (!empty($symptoms)) {
        echo '<div class="case-section">';
        echo '<div class="case-label">Symptoms</div>';
        echo '<div class="case-text">';
        $symptom_list = [];
        foreach ($symptoms as $key => $value) {
            if ($value === true || $value === 'yes' || $value === 1) {
                $symptom_list[] = ucfirst(str_replace('_', ' ', $key));
            } elseif (is_string($value) && $value !== 'no') {
                $symptom_list[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
            }
        }
        echo htmlspecialchars(implode(', ', $symptom_list));
        echo '</div>';
        echo '</div>';
    }
    
    // Display additional notes if available
    if ($case['additional_notes']) {
        echo '<div class="case-section">';
        echo '<div class="case-label">Notes</div>';
        echo '<div class="case-text">' . nl2br(htmlspecialchars($case['additional_notes'])) . '</div>';
        echo '</div>';
    }
    
    // Display AI summary
    if ($case['ai_summary']) {
        echo '<div class="case-section">';
        echo '<div class="case-label" style="color: #0f766e;">🤖 AI Assessment</div>';
        echo '<div class="case-text">' . nl2br(htmlspecialchars($case['ai_summary'])) . '</div>';
        echo '</div>';
    }
    
    // Display red flags if any
    if (!empty($red_flags) && is_array($red_flags)) {
        echo '<div class="case-section">';
        echo '<div class="case-label" style="color: #dc2626;">⚠ Red Flags</div>';
        echo '<div class="case-text">' . htmlspecialchars(implode(', ', $red_flags)) . '</div>';
        echo '</div>';
    }
    
    // Fetch all replies for this case
    $replies_query = "SELECT cr.id, cr.diagnosis_summary, cr.treatment_advice, 
                             cr.prescription_note, cr.action, cr.created_at,
                             u.full_name as doctor_name
                      FROM case_replies cr
                      JOIN users u ON cr.doctor_id = u.id
                      WHERE cr.case_id = ?
                      ORDER BY cr.created_at DESC";
    
    $reply_stmt = $conn->prepare($replies_query);
    $reply_stmt->bind_param("i", $case['id']);
    $reply_stmt->execute();
    $replies = $reply_stmt->get_result();
    
    if ($replies->num_rows > 0) {
        while ($reply = $replies->fetch_assoc()) {
            echo '<div class="reply-section">';
            echo '<div class="reply-header">';
            echo '<div class="reply-doctor">👨‍⚕️ Dr. ' . htmlspecialchars($reply['doctor_name']) . '</div>';
            echo '<div class="reply-date">' . date('M d, Y h:i A', strtotime($reply['created_at'])) . '</div>';
            echo '</div>';
            
            if ($reply['diagnosis_summary']) {
                echo '<div class="case-section">';
                echo '<div class="case-label">Diagnosis</div>';
                echo '<div class="reply-diagnosis">' . nl2br(htmlspecialchars($reply['diagnosis_summary'])) . '</div>';
                echo '</div>';
            }
            
            if ($reply['treatment_advice']) {
                echo '<div class="case-section">';
                echo '<div class="case-label">Treatment Advice</div>';
                echo '<div class="reply-diagnosis">' . nl2br(htmlspecialchars($reply['treatment_advice'])) . '</div>';
                echo '</div>';
            }
            
            if ($reply['prescription_note']) {
                echo '<div class="case-section">';
                echo '<div class="case-label">Prescription Notes</div>';
                echo '<div class="reply-diagnosis">' . nl2br(htmlspecialchars($reply['prescription_note'])) . '</div>';
                echo '</div>';
            }
            
            $action_text = str_replace('_', ' ', $reply['action']);
            echo '<span class="action-badge ' . $reply['action'] . '">' . ucwords($action_text) . '</span>';
            echo '</div>';
        }
    } else {
        echo '<div style="font-size: 13px; color: #6b7280; font-style: italic; margin-top: 12px;">';
        echo 'No doctor replies yet';
        echo '</div>';
    }
    
    // Case status
    echo '<div style="font-size: 12px; color: #6b7280; margin-top: 12px; font-weight: 600;">';
    echo 'Status: ' . ucwords(str_replace('_', ' ', $case['status']));
    echo '</div>';
    
    echo '</div>'; // Close history-item
}

echo '</div>'; // Close history-timeline

$conn->close();
?>