<?php
session_start();

// --- 1. Check if user is logged in as patient ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$patient_id = $_SESSION['user_id'];
$patient_name = $_SESSION['full_name'] ?? 'Patient';

// --- 2. Database connection ---
$conn = new mysqli('localhost', 'root', '', 'rural_health_ai');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 3. Translation and language check functions ---
function translateToBangla($text) {
    $data = [
        'q' => $text,
        'source' => 'en',
        'target' => 'bn',
        'format' => 'text'
    ];
    
    $ch = curl_init('https://libretranslate.com/translate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $result = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($result, true);
    return $res['translatedText'] ?? $text;
}

function isBangla($text) {
    return preg_match('/\p{Bengali}/u', $text);
}

// --- 4. Handle case submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_case'])) {
    $chief_complaint = $_POST['chief_complaint'] ?? '';
    $additional_notes = $_POST['additional_notes'] ?? '';
    $symptoms_text = $_POST['symptoms'] ?? '';
    $audio_uploaded = $_FILES['audio_file']['tmp_name'] ?? null;

    // --- 5. If audio uploaded, transcribe via Whisper ---
    if ($audio_uploaded && file_exists($audio_uploaded)) {
        $audio_uploaded_escaped = escapeshellarg($audio_uploaded);
        $python_script = escapeshellarg(__DIR__ . "\\transcribe.py"); // Your local Python Whisper script

        // Call Python script
        $cmd = "python $python_script $audio_uploaded_escaped";
        $transcribed_text = shell_exec($cmd);

        if ($transcribed_text) {
            $symptoms_text = $transcribed_text;
        }
    }

    // --- 6. Call Ollama LLaMA2 for AI processing ---
    $prompt = "You are a helpful rural doctor assistant.
Patient symptoms: $symptoms_text
If user input is Bangla, respond in Bangla. Otherwise, respond in English.
Return JSON with:
- specialty: (general, pediatrics, gynecology, dermatology, cardiology, ent, medicine)
- severity: (GREEN, YELLOW, RED)
- ai_summary: short summary of patient case
- ai_explanation: explanation of severity
Example JSON: {\"specialty\":\"\",\"severity\":\"\",\"ai_summary\":\"\",\"ai_explanation\":\"\"}";

    $prompt_escaped = escapeshellarg($prompt);
    $ollama_cmd = "ollama query llama2:7b $prompt_escaped --json";
    $ai_output_json = shell_exec($ollama_cmd);
    $ai_output = json_decode($ai_output_json, true);

    // --- 7. Fallback if AI fails ---
    $specialty = $ai_output['specialty'] ?? 'general';
    $severity = $ai_output['severity'] ?? 'GREEN';
    $ai_summary_raw = $ai_output['ai_summary'] ?? "Patient case summary not available.";
    $ai_explanation_raw = $ai_output['ai_explanation'] ?? "Explanation not available.";

    // --- 8. Detect language of user input ---
    if (isBangla($chief_complaint)) {
        $ai_summary = translateToBangla($ai_summary_raw);
        $ai_explanation = translateToBangla($ai_explanation_raw);
    } else {
        $ai_summary = $ai_summary_raw;
        $ai_explanation = $ai_explanation_raw;
    }

    // --- 9. Detect red flags locally ---
    $red_flags = [];
    $lower = strtolower($symptoms_text);
    if (strpos($lower, 'chest pain') !== false) $red_flags[] = "Chest pain reported";
    if (strpos($lower, 'breathing') !== false) $red_flags[] = "Difficulty breathing";
    if (strpos($lower, 'unconscious') !== false) $red_flags[] = "Unconscious episode";

    // --- 10. Insert into cases table ---
    $stmt = $conn->prepare("INSERT INTO cases 
        (patient_id, chief_complaint, symptoms, additional_notes, severity, suggested_specialty, red_flags, ai_summary, ai_explanation, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')");

    $symptoms_json = json_encode(['text' => $symptoms_text]);
    $red_flags_json = json_encode($red_flags);

    $stmt->bind_param(
        "issssssss",
        $patient_id,
        $chief_complaint,
        $symptoms_json,
        $additional_notes,
        $severity,
        $specialty,
        $red_flags_json,
        $ai_summary,
        $ai_explanation
    );

    if ($stmt->execute()) {
        $case_id = $conn->insert_id;
        header("Location: result.php?case_id=$case_id");
        exit();
    } else {
        echo "<p style='color:red;'>Error saving case: ".$stmt->error."</p>";
    }
}

$conn->close();
?>


<!-- ============================ HTML / CSS / JS ============================ -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI Health Assistant | ShasthoBondhu</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Your existing CSS here -->
<style>
/* --- Keep all your existing CSS from your base code --- */
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
                <button class="btn-voice" id="voiceBtn" onclick="toggleVoice()" title="Voice input">🎤</button>
                <input type="text" class="chat-input" id="chatInput" placeholder="Type your symptoms here..." onkeypress="handleKeyPress(event)">
                <button class="btn-send" id="sendBtn" onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>

    <!-- Disclaimer -->
    <div class="disclaimer-box">
        <p>⚠️ This is an AI assistant for initial assessment only. It does not replace professional medical advice.</p>
    </div>
</div>

<!-- Hidden Form -->
<form method="POST" id="caseForm" class="hidden-form" enctype="multipart/form-data">
    <input type="hidden" name="submit_case" value="1">
    <input type="hidden" name="chief_complaint" id="formChiefComplaint">
    <input type="hidden" name="symptoms" id="formSymptoms">
    <input type="hidden" name="additional_notes" id="formNotes">
    <input type="hidden" name="severity" id="formSeverity">
    <input type="hidden" name="suggested_specialty" id="formSpecialty">
    <input type="hidden" name="red_flags" id="formRedFlags">
    <input type="hidden" name="ai_summary" id="formAiSummary">
    <input type="hidden" name="ai_explanation" id="formAiExplanation">
    <input type="file" name="audio_file" id="formAudioFile" style="display:none;">
</form>

<script>
// Chat state
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
    // Prepare data
    const symptomsJson = JSON.stringify(symptomsData);
    const specialty = determineSpecialty(symptomsData.chief_complaint);
    const redFlags = detectRedFlags(symptomsData);
    const aiSummary = generateAiSummary();
    const aiExplanation = generateExplanation();
   
    // Fill hidden form
    document.getElementById('formChiefComplaint').value = symptomsData.chief_complaint;
    document.getElementById('formSymptoms').value = symptomsJson;
    document.getElementById('formNotes').value = JSON.stringify(conversationHistory);
    document.getElementById('formSeverity').value = symptomsData.severity || 'YELLOW';
    document.getElementById('formSpecialty').value = specialty;
    document.getElementById('formRedFlags').value = JSON.stringify(redFlags);
    document.getElementById('formAiSummary').value = aiSummary;
    document.getElementById('formAiExplanation').value = aiExplanation;
   
    hideAnalyzing();
   
    // Show final message
    addMessage(`✅ Your case has been created and assigned to a ${specialty} specialist. You'll be redirected to view your case details.`);
   
    // Submit form after 2 seconds
    setTimeout(() => {
        document.getElementById('caseForm').submit();
    }, 2000);
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
let recognition;

function toggleVoice() {
    const voiceBtn = document.getElementById('voiceBtn');

    if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
        alert('Your browser does not support speech recognition.');
        return;
    }

    if (isRecording) {
        // Stop recording safely
        recognition.stop();
        return;
    }

    isRecording = true;
    voiceBtn.classList.add('recording');
    voiceBtn.innerHTML = '⏹️';

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();

    recognition.lang = 'en-US'; // English only
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    recognition.start();

    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        // Add to chat box and input field
        addMessage(transcript, true);
        document.getElementById('chatInput').value = transcript;
        sendMessage();
    };

    recognition.onerror = (event) => {
        console.error('Speech recognition error:', event.error);
        alert('Speech recognition error: ' + event.error);
        stopRecording();
    };

    recognition.onend = () => {
        // Ensure we reset recording state
        stopRecording();
    };

    function stopRecording() {
        isRecording = false;
        voiceBtn.classList.remove('recording');
        voiceBtn.innerHTML = '🎤';
    }
}

</script>
</body>
</html>