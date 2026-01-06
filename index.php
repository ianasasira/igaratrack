<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="home-page">
    <div class="home-container">
        <div class="home-content">
            <h1>Teacher Attendance & Performance Tracking System</h1>
            <p class="subtitle">Secure biometric-based attendance tracking using WebAuthn</p>
            
            <div class="home-actions">
                <a href="attendance.php" class="btn btn-primary btn-large">Teacher Attendance</a>
                <a href="admin/login.php" class="btn btn-secondary btn-large">Admin Login</a>
            </div>
            
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3>Secure Biometric</h3>
                    <p>Fingerprint authentication using WebAuthn technology</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Performance Analytics</h3>
                    <p>Comprehensive reports and analytics dashboard</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Lesson-Based Tracking</h3>
                    <p>Track attendance based on lesson schedules</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

