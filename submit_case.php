<?php
session_start();

// Check if user is logged in as patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$patient_id = $_SESSION['user_id'];
$patient_name = $_SESSION['full_name'] ?? 'Patient';

// Database connection
$conn = new mysqli('localhost', 'root', '', 'rural_health_ai');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle case submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_case'])) {
    $symptoms = $_POST['symptoms'] ?? '{}';
    $chief_complaint = $_POST['chief_complaint'] ?? '';
    $additional_notes = $_POST['additional_notes'] ?? '';
    $severity = $_POST['severity'] ?? 'GREEN';
    $suggested_specialty = $_POST['suggested_specialty'] ?? 'general';
    $red_flags = $_POST['red_flags'] ?? '[]';
    $ai_summary = $_POST['ai_summary'] ?? '';
    $ai_explanation = $_POST['ai_explanation'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO cases (patient_id, chief_complaint, symptoms, additional_notes, severity, suggested_specialty, red_flags, ai_summary, ai_explanation, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')");
    $stmt->bind_param("issssssss", $patient_id, $chief_complaint, $symptoms, $additional_notes, $severity, $suggested_specialty, $red_flags, $ai_summary, $ai_explanation);
    
    if ($stmt->execute()) {
        $case_id = $conn->insert_id;
        header('Location: result.php?case_id=' . $case_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI Health Assistant | ShasthoBondhu</title>
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

.patient-name {
    font-weight: 600;
    color: #374151;
}

.btn-nav {
    border: 2px solid #0f766e;
    color: #0f766e;
    padding: 8px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
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
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 20px;
}

/* Welcome Section */
.welcome-section {
    text-align: center;
    margin-bottom: 30px;
}

.welcome-section h1 {
    font-size: 32px;
    color: #0f766e;
    margin-bottom: 10px;
}

.welcome-section p {
    font-size: 16px;
    color: #6b7280;
}

/* Chat Container */
.chat-container {
    background: white;
    border-radius: 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
    height: 600px;
    display: flex;
    flex-direction: column;
}

/* Chat Header */
.chat-header {
    background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
    color: white;
    padding: 20px;
    text-align: center;
}

.chat-header h2 {
    font-size: 20px;
    margin-bottom: 5px;
}

.chat-header p {
    font-size: 14px;
    opacity: 0.9;
}

/* Chat Messages */
.chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background: #f9fafb;
}

.message {
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message.ai {
    justify-content: flex-start;
}

.message.user {
    justify-content: flex-end;
}

.message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.message.ai .message-avatar {
    background: #ecfeff;
    color: #0f766e;
    margin-right: 12px;
}

.message.user .message-avatar {
    background: #dbeafe;
    color: #1e40af;
    margin-left: 12px;
}

.message-content {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 12px;
    line-height: 1.5;
}

.message.ai .message-content {
    background: white;
    color: #1f2937;
    border: 1px solid #e5e7eb;
}

.message.user .message-content {
    background: #0f766e;
    color: white;
}

/* Quick Options */
.quick-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.quick-option {
    background: white;
    border: 2px solid #0f766e;
    color: #0f766e;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s;
}

.quick-option:hover {
    background: #0f766e;
    color: white;
}

/* Analyzing Bar */
.analyzing-bar {
    display: none;
    background: #ecfeff;
    border: 1px solid #0f766e;
    border-radius: 12px;
    padding: 16px;
    margin: 12px 0;
    text-align: center;
}

.analyzing-bar.active {
    display: block;
    animation: fadeIn 0.3s ease-in;
}

.analyzing-text {
    color: #0f766e;
    font-weight: 600;
    margin-bottom: 8px;
}

.progress-bar {
    height: 6px;
    background: #d1fae5;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0f766e, #14b8a6, #0f766e);
    background-size: 200% 100%;
    animation: progressAnimation 1.5s ease-in-out infinite;
}

@keyframes progressAnimation {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Chat Input */
.chat-input-container {
    padding: 20px;
    background: white;
    border-top: 1px solid #e5e7eb;
}

.chat-input-wrapper {
    display: flex;
    gap: 12px;
    align-items: center;
}

.chat-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 24px;
    font-size: 15px;
    outline: none;
    transition: border-color 0.2s;
}

.chat-input:focus {
    border-color: #0f766e;
}

.chat-input:disabled {
    background: #f3f4f6;
    cursor: not-allowed;
}

.btn-send {
    background: #0f766e;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 24px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-send:hover {
    background: #115e59;
}

.btn-send:disabled {
    background: #cbd5e1;
    cursor: not-allowed;
}

/* Voice Button */
.btn-voice {
    background: #f0fdfa;
    color: #0f766e;
    border: 2px solid #0f766e;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: all 0.2s;
}

.btn-voice:hover {
    background: #0f766e;
    color: white;
}

.btn-voice.recording {
    background: #dc2626;
    border-color: #dc2626;
    color: white;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Hidden Form */
.hidden-form {
    display: none;
}

/* Disclaimer */
.disclaimer-box {
    background: #fef3c7;
    border: 2px solid #f59e0b;
    border-radius: 12px;
    padding: 16px;
    margin-top: 20px;
    text-align: center;
}

.disclaimer-box p {
    color: #92400e;
    font-size: 14px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .chat-container {
        height: 500px;
    }
    
    .message-content {
        max-width: 85%;
    }
    
    .container {
        padding: 20px 10px;
    }
}
</style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="logo">🩺 ShasthoBondhu</div>
    <div class="nav-right">
        <span class="patient-name"><?php echo htmlspecialchars($patient_name); ?></span>
        <a href="patient_dashboard.php" class="btn-nav">My Cases</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1>🤖 AI Health Assistant</h1>
        <p>Describe your symptoms and I'll help assess your situation</p>
    </div>

    <!-- Chat Container -->
    <div class="chat-container">
        <!-- Chat Header -->
        <div class="chat-header">
            <h2>Health Assessment Chat</h2>
            <p>Speak or type in Bangla or English</p>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="message ai">
                <div class="message-avatar">🤖</div>
                <div class="message-content">
                    <p>Hello! I'm your AI health assistant. How are you feeling today? Please describe your symptoms.</p>
                    <div class="quick-options">
                        <button class="quick-option" onclick="selectQuickOption('I have a fever')">I have a fever 🌡️</button>
                        <button class="quick-option" onclick="selectQuickOption('I have a headache')">Headache 🤕</button>
                        <button class="quick-option" onclick="selectQuickOption('Stomach pain')">Stomach pain</button>
                        <button class="quick-option" onclick="selectQuickOption('Cough and cold')">Cough/Cold 🤧</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analyzing Bar -->
        <div class="analyzing-bar" id="analyzingBar">
            <div class="analyzing-text">🔍 Analyzing your symptoms...</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="chat-input-container">
            <div class="chat-input-wrapper">
                <button class="btn-voice" id="voiceBtn" onclick="toggleVoice()" title="Voice input">
                    🎤
                </button>
                <input 
                    type="text" 
                    class="chat-input" 
                    id="chatInput" 
                    placeholder="Type your symptoms here..."
                    onkeypress="handleKeyPress(event)"
                >
                <button class="btn-send" id="sendBtn" onclick="sendMessage()">
                    Send
                </button>
            </div>
        </div>
    </div>

    <!-- Disclaimer -->
    <div class="disclaimer-box">
        <p>⚠️ This is an AI assistant for initial assessment only. It does not replace professional medical advice.</p>
    </div>
</div>
<div id="networkStatus" style="text-align:center;font-size:13px;color:#6b7280;margin-top:8px;">
    📡 Checking network…
</div>


<!-- Hidden Form for Submission -->
<form method="POST" id="caseForm" class="hidden-form">
    <input type="hidden" name="submit_case" value="1">
    <input type="hidden" name="chief_complaint" id="formChiefComplaint">
    <input type="hidden" name="symptoms" id="formSymptoms">
    <input type="hidden" name="additional_notes" id="formNotes">
    <input type="hidden" name="severity" id="formSeverity">
    <input type="hidden" name="suggested_specialty" id="formSpecialty">
    <input type="hidden" name="red_flags" id="formRedFlags">
    <input type="hidden" name="ai_summary" id="formAiSummary">
    <input type="hidden" name="ai_explanation" id="formAiExplanation">
</form>

<script>
// Chat state

// ---------- OFFLINE STORAGE HELPERS ----------
const OFFLINE_CASE_KEY = 'offline_pending_case';

function saveCaseOffline(caseData) {
    localStorage.setItem(OFFLINE_CASE_KEY, JSON.stringify(caseData));
}

function getOfflineCase() {
    const data = localStorage.getItem(OFFLINE_CASE_KEY);
    return data ? JSON.parse(data) : null;
}

function clearOfflineCase() {
    localStorage.removeItem(OFFLINE_CASE_KEY);
}

let conversationHistory = [];
let symptomsData = {};
let isRecording = false;
let currentStep = 'initial';

// Add message to chat
function addMessage(text, isUser = false) {
    const messagesContainer = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isUser ? 'user' : 'ai'}`;
    
    messageDiv.innerHTML = `
        <div class="message-avatar">${isUser ? '👤' : '🤖'}</div>
        <div class="message-content">
            <p>${text}</p>
        </div>
    `;
    
    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    // Store in conversation history
    conversationHistory.push({
        role: isUser ? 'user' : 'assistant',
        content: text
    });
}

// Add quick options
function addQuickOptions(options) {
    const messagesContainer = document.getElementById('chatMessages');
    const lastMessage = messagesContainer.lastElementChild;
    const messageContent = lastMessage.querySelector('.message-content');
    
    const optionsDiv = document.createElement('div');
    optionsDiv.className = 'quick-options';
    
    options.forEach(option => {
        const button = document.createElement('button');
        button.className = 'quick-option';
        button.textContent = option.text;
        button.onclick = () => selectQuickOption(option.value);
        optionsDiv.appendChild(button);
    });
    
    messageContent.appendChild(optionsDiv);
}

// Quick option selection
function selectQuickOption(text) {
    document.getElementById('chatInput').value = text;
    sendMessage();
}

// Show analyzing bar
function showAnalyzing() {
    document.getElementById('analyzingBar').classList.add('active');
}

// Hide analyzing bar
function hideAnalyzing() {
    document.getElementById('analyzingBar').classList.remove('active');
}

// Disable input
function disableInput(disabled) {
    document.getElementById('chatInput').disabled = disabled;
    document.getElementById('sendBtn').disabled = disabled;
    document.getElementById('voiceBtn').disabled = disabled;
}

// Send message
async function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message
    addMessage(message, true);
    input.value = '';
    
    // Disable input while processing
    disableInput(true);
    showAnalyzing();
    
    // Simulate AI processing (you'll replace this with actual AI API call)
    setTimeout(() => {
        processUserMessage(message);
        hideAnalyzing();
        disableInput(false);
    }, 1500);
}

// Process user message and generate AI response
function processUserMessage(message) {
    const lowerMessage = message.toLowerCase();
    
    if (currentStep === 'initial') {
        // Extract chief complaint
        symptomsData.chief_complaint = message;
        
        // Analyze for urgency keywords
        const urgentKeywords = ['chest pain', 'breathing', 'unconscious', 'bleeding', 'severe pain', 'dizzy', 'fainted'];
        const isUrgent = urgentKeywords.some(keyword => lowerMessage.includes(keyword));
        
        if (isUrgent) {
            addMessage("I understand. This sounds like it could be urgent. Let me ask you a few important questions.");
            symptomsData.severity = 'RED';
        } else if (lowerMessage.includes('fever') || lowerMessage.includes('pain')) {
            addMessage("I see. Let me gather more information to better understand your condition.");
            symptomsData.severity = 'YELLOW';
        } else {
            addMessage("Thank you for sharing. Let me ask you some follow-up questions.");
            symptomsData.severity = 'GREEN';
        }
        
        currentStep = 'duration';
        setTimeout(() => {
            addMessage("How long have you been experiencing these symptoms?");
            addQuickOptions([
                {text: 'Less than 24 hours', value: 'Less than 24 hours'},
                {text: '1-3 days', value: '1-3 days'},
                {text: 'More than 3 days', value: 'More than a week'},
                {text: 'Several weeks', value: 'Several weeks'}
            ]);
        }, 800);
        
    } else if (currentStep === 'duration') {
        symptomsData.duration = message;
        currentStep = 'severity_level';
        
        setTimeout(() => {
            addMessage("On a scale of 1-10, how would you rate your discomfort?");
            addQuickOptions([
                {text: 'Mild (1-3)', value: 'mild'},
                {text: 'Moderate (4-6)', value: 'moderate'},
                {text: 'Severe (7-10)', value: 'severe'}
            ]);
        }, 800);
        
    } else if (currentStep === 'severity_level') {
        symptomsData.pain_level = message;
        currentStep = 'additional_symptoms';
        
        setTimeout(() => {
            addMessage("Are you experiencing any of the following?");
            addQuickOptions([
                {text: 'Fever 🌡️', value: 'fever'},
                {text: 'Nausea/Vomiting 🤢', value: 'nausea'},
                {text: 'Dizziness', value: 'dizziness'},
                {text: 'None of these', value: 'none'}
            ]);
        }, 800);
        
    } else if (currentStep === 'additional_symptoms') {
        symptomsData.additional = message;
        currentStep = 'medical_history';
        
        setTimeout(() => {
            addMessage("Do you have any pre-existing medical conditions or allergies?");
            addQuickOptions([
                {text: 'Diabetes', value: 'diabetes'},
                {text: 'Hypertension', value: 'hypertension'},
                {text: 'Asthma', value: 'asthma'},
                {text: 'None', value: 'none'}
            ]);
        }, 800);
        
    } else if (currentStep === 'medical_history') {
        symptomsData.medical_history = message;
        currentStep = 'complete';
        
        setTimeout(() => {
            addMessage("Thank you for providing all this information. Let me analyze your symptoms and create a case for medical review.");
            showAnalyzing();
            
            setTimeout(() => {
                submitCase();
            }, 2000);
        }, 800);
    }
}

// Submit case to database
function submitCase() {
    const symptomsJson = JSON.stringify(symptomsData);
    const specialty = determineSpecialty(symptomsData.chief_complaint);
    const redFlags = detectRedFlags(symptomsData);
    const aiSummary = generateAiSummary();
    const aiExplanation = generateExplanation();

    const casePayload = {
        chief_complaint: symptomsData.chief_complaint,
        symptoms: symptomsJson,
        additional_notes: JSON.stringify(conversationHistory),
        severity: symptomsData.severity || 'YELLOW',
        suggested_specialty: specialty,
        red_flags: JSON.stringify(redFlags),
        ai_summary: aiSummary,
        ai_explanation: aiExplanation
    };

    hideAnalyzing();

    // 🛑 OFFLINE MODE
    if (!navigator.onLine) {
        saveCaseOffline(casePayload);

        addMessage(
            "📶 Internet connection is unstable. Your symptoms have been saved safely and will be submitted automatically when the connection is restored."
        );

        return;
    }

    // ✅ ONLINE MODE → submit now
    submitCaseForm(casePayload);
}


function submitCaseForm(casePayload) {
    document.getElementById('formChiefComplaint').value = casePayload.chief_complaint;
    document.getElementById('formSymptoms').value = casePayload.symptoms;
    document.getElementById('formNotes').value = casePayload.additional_notes;
    document.getElementById('formSeverity').value = casePayload.severity;
    document.getElementById('formSpecialty').value = casePayload.suggested_specialty;
    document.getElementById('formRedFlags').value = casePayload.red_flags;
    document.getElementById('formAiSummary').value = casePayload.ai_summary;
    document.getElementById('formAiExplanation').value = casePayload.ai_explanation;

    addMessage("✅ Submitting your case for doctor review…");

    setTimeout(() => {
        document.getElementById('caseForm').submit();
    }, 1500);
}



// Determine specialty based on symptoms
function determineSpecialty(complaint) {
    const lower = complaint.toLowerCase();
    if (lower.includes('heart') || lower.includes('chest')) return 'cardiology';
    if (lower.includes('skin') || lower.includes('rash')) return 'dermatology';
    if (lower.includes('child') || lower.includes('baby')) return 'pediatrics';
    if (lower.includes('pregnancy') || lower.includes('menstrual')) return 'gynecology';
    if (lower.includes('ear') || lower.includes('nose') || lower.includes('throat')) return 'ent';
    return 'general';
}

// Detect red flags
function detectRedFlags(symptoms) {
    const flags = [];
    const complaint = (symptoms.chief_complaint || '').toLowerCase();
    
    if (complaint.includes('chest pain')) flags.push('Chest pain reported');
    if (complaint.includes('breathing')) flags.push('Difficulty breathing');
    if (symptoms.pain_level && symptoms.pain_level.includes('severe')) flags.push('Severe pain level');
    if (symptoms.duration && symptoms.duration.includes('weeks')) flags.push('Prolonged symptoms');
    
    return flags;
}

// Generate AI summary
function generateAiSummary() {
    return `Patient reports: ${symptomsData.chief_complaint}. Duration: ${symptomsData.duration || 'not specified'}. Pain level: ${symptomsData.pain_level || 'not specified'}. Additional symptoms: ${symptomsData.additional || 'none'}. Medical history: ${symptomsData.medical_history || 'none reported'}.`;
}

// Generate explanation
function generateExplanation() {
    if (symptomsData.severity === 'RED') {
        return 'URGENT: Symptoms indicate potential need for immediate medical attention.';
    } else if (symptomsData.severity === 'YELLOW') {
        return 'Moderate priority: Medical review recommended within 24-48 hours.';
    } else {
        return 'Low priority: Routine medical consultation recommended.';
    }
}

// Handle Enter key
function handleKeyPress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

// Voice input toggle (placeholder)
function toggleVoice() {
    const voiceBtn = document.getElementById('voiceBtn');
    isRecording = !isRecording;
    
    if (isRecording) {
        voiceBtn.classList.add('recording');
        voiceBtn.innerHTML = '⏹️';
        // Here you would integrate actual speech recognition
        // For now, just show a message
        setTimeout(() => {
            toggleVoice();
            addMessage("(Voice recognition would be integrated here)", true);
        }, 3000);
    } else {
        voiceBtn.classList.remove('recording');
        voiceBtn.innerHTML = '🎤';
    }
}

// ---------- AUTO SUBMIT WHEN ONLINE ----------
window.addEventListener('online', () => {
    const pendingCase = getOfflineCase();
    if (!pendingCase) return;

    addMessage("📡 Internet connection restored. Submitting your saved case now…");

    clearOfflineCase();
    submitCaseForm(pendingCase);
});
function updateNetworkStatus() {
    const el = document.getElementById('networkStatus');
    if (!el) return;

    if (navigator.onLine) {
        el.textContent = '🟢 Online';
    } else {
        el.textContent = '🔴 Offline — your input will be saved';
    }
}

window.addEventListener('online', updateNetworkStatus);
window.addEventListener('offline', updateNetworkStatus);
updateNetworkStatus();

</script>

</body>
</html>

<?php
$conn->close();
?>
