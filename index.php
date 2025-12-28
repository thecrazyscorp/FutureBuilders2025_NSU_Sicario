<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ShasthoBondhu | AI Rural Healthcare</title>
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

.btn-login {
    border: 2px solid #0f766e;
    color: #0f766e;
    padding: 8px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}

/* Hero */
.hero {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 40px;
    padding: 70px 8%;
    align-items: center;
}

.hero-text h1 {
    font-size: 40px;
    margin-bottom: 16px;
}

.hero-text p {
    font-size: 18px;
    line-height: 1.6;
    margin-bottom: 26px;
    color: #374151;
}

.hero-actions a {
    margin-right: 14px;
}

.btn-primary {
    background: #0f766e;
    color: #fff;
    padding: 12px 22px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
}

.btn-outline {
    border: 2px solid #0f766e;
    color: #0f766e;
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
}

.disclaimer {
    margin-top: 22px;
    font-size: 13px;
    color: #b91c1c;
}

/* Hero Visual (Improved) */
.hero-visual {
    background: linear-gradient(145deg, #ecfeff, #f0fdfa);
    border-radius: 28px;
    padding: 40px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.08);
}

.visual-card {
    background: white;
    border-radius: 18px;
    padding: 26px;
    margin-bottom: 18px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.05);
}

.visual-title {
    color: #0f766e;
    font-weight: 700;
    margin-bottom: 6px;
}

.visual-text {
    font-size: 15px;
    color: #374151;
}

/* How it works */
.how {
    padding: 60px 8%;
    background: #ffffff;
    text-align: center;
}

.how h2 {
    font-size: 32px;
    margin-bottom: 40px;
}

.steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.step {
    background: #f0fdfa;
    padding: 30px;
    border-radius: 18px;
}

.step span {
    display: inline-block;
    width: 36px;
    height: 36px;
    background: #0f766e;
    color: white;
    border-radius: 50%;
    line-height: 36px;
    font-weight: 700;
    margin-bottom: 14px;
}

.step h3 {
    margin-bottom: 10px;
    color: #065f46;
}

/* Footer */
.footer {
    padding: 30px;
    text-align: center;
    background: #0f766e;
    color: white;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 900px) {
    .hero {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .hero-visual {
        margin-top: 30px;
    }

    .steps {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<!-- Navbar -->
<?php
session_start();

// Check if user is logged in
$login_redirect = 'login.php';
if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'){
    $login_redirect = 'patient_dashboard.php';
} elseif(isset($_SESSION['user_id']) && $_SESSION['role'] === 'doctor'){
    $login_redirect = 'doctor_dashboard.php';
}
?>
<nav class="navbar">
    <div class="logo">🩺 ShasthoBondhu</div>
    <a href="<?= $login_redirect ?>" class="btn-login">Login</a>
</nav>


<!-- Hero Section -->
<section class="hero">
    <div class="hero-text">
        <h1>Healthcare Access for Every Village</h1>
        <p>
            ShasthoBondhu helps people in rural Bangladesh
            describe symptoms using voice or text in Bangla or English.
            AI assists in assessing urgency and routing cases for
            timely medical review.
        </p>

        <div class="hero-actions">
            <a href="register.php" class="btn-primary">Try it now</a>
            <a href="#how" class="btn-outline">How it works</a>
        </div>

        <p class="disclaimer">
            ⚠ This system does not provide medical diagnosis.
            It assists only in triage and referral.
        </p>
    </div>

    <!-- Improved Visual -->
    <div class="hero-visual">
        <div class="visual-card">
            <div class="visual-title">Multilingual Input</div>
            <div class="visual-text">
                Supports Bangla, English, and mixed speech or text.
            </div>
        </div>

        <div class="visual-card">
            <div class="visual-title">AI-Assisted Triage</div>
            <div class="visual-text">
                Safe, rule-based urgency assessment with explainability.
            </div>
        </div>

        <div class="visual-card">
            <div class="visual-title">Doctor Review</div>
            <div class="visual-text">
                Cases are reviewed asynchronously by medical professionals.
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section id="how" class="how">
    <h2>How ShasthoBondhu Works</h2>

    <div class="steps">
        <div class="step">
            <span>1</span>
            <h3>Describe Symptoms</h3>
            <p>Speak or type symptoms in Bangla, English, or both.</p>
        </div>

        <div class="step">
            <span>2</span>
            <h3>Urgency Assessment</h3>
            <p>AI assists in classifying cases as Green, Yellow, or Red.</p>
        </div>

        <div class="step">
            <span>3</span>
            <h3>Medical Review</h3>
            <p>Doctors provide guidance, referral, or escalation.</p>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <p>
        © 2025 ShasthoBondhu — Hackathon MVP  
        <br>
        AI assists. Doctors decide.
    </p>
</footer>

</body>
</html>
