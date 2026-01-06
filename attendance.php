<?php
require_once 'config/config.php';

$db = getDBConnection();
$message = '';
$error = '';

// Get all active teachers
$stmt = $db->query("SELECT id, name FROM teachers WHERE status = 'active' ORDER BY name");
$teachers = $stmt->fetchAll();

// Get selected teacher
$selectedTeacherId = (int)($_GET['teacher_id'] ?? 0);
$selectedTeacher = null;

if ($selectedTeacherId) {
    $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ? AND status = 'active'");
    $stmt->execute([$selectedTeacherId]);
    $selectedTeacher = $stmt->fetch();
    
    if (!$selectedTeacher) {
        $error = 'Teacher not found';
        $selectedTeacherId = 0;
    } else {
        // Check if teacher has biometric registered
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM webauthn_credentials WHERE teacher_id = ?");
        $stmt->execute([$selectedTeacherId]);
        $hasBiometric = $stmt->fetch()['count'] > 0;
        
        if (!$hasBiometric) {
            $error = 'This teacher does not have biometric credentials registered. Please contact administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Attendance - Clock In/Out</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="attendance-page">
    <div class="container">
        <h1>Teacher Attendance</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Teacher Selection -->
        <div class="card">
            <h2>Select Your Name</h2>
            <form method="GET" action="">
                <div class="teacher-selection">
                    <?php foreach ($teachers as $teacher): ?>
                        <label class="teacher-radio">
                            <input type="radio" name="teacher_id" value="<?php echo $teacher['id']; ?>" 
                                   <?php echo $selectedTeacherId == $teacher['id'] ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span><?php echo htmlspecialchars($teacher['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
        
        <!-- Clock In/Out Interface -->
        <?php if ($selectedTeacher && isset($hasBiometric) && $hasBiometric): ?>
            <div class="card">
                <h2>Welcome, <?php echo htmlspecialchars($selectedTeacher['name']); ?></h2>
                
                <div id="attendance-status" class="alert" style="display:none;"></div>
                
                <div class="attendance-actions">
                    <button id="clock-in-btn" class="btn btn-primary btn-large">Clock In</button>
                    <button id="clock-out-btn" class="btn btn-secondary btn-large">Clock Out</button>
                </div>
                
                <div class="attendance-info">
                    <p><strong>Instructions:</strong></p>
                    <ul>
                        <li>Use your fingerprint scanner to authenticate</li>
                        <li>Clock in before or at the start of your lesson</li>
                        <li>Clock out at the end of your lesson</li>
                        <li>Make sure you have a stable internet connection</li>
                    </ul>
                </div>
            </div>
            
            <!-- Today's Attendance -->
            <div class="card">
                <h2>Today's Attendance</h2>
                <div id="today-attendance">
                    <p>Loading...</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        const teacherId = <?php echo $selectedTeacherId; ?>;
        const rpId = '<?php echo RP_ID; ?>';
        
        if (teacherId) {
            loadTodayAttendance();
            
            document.getElementById('clock-in-btn').addEventListener('click', () => {
                performClockAction('in');
            });
            
            document.getElementById('clock-out-btn').addEventListener('click', () => {
                performClockAction('out');
            });
        }
        
        async function performClockAction(action) {
            const statusDiv = document.getElementById('attendance-status');
            const btn = action === 'in' ? document.getElementById('clock-in-btn') : document.getElementById('clock-out-btn');
            
            try {
                btn.disabled = true;
                statusDiv.style.display = 'block';
                statusDiv.className = 'alert alert-info';
                statusDiv.textContent = 'Preparing authentication...';
                
                // Get challenge
                const response = await fetch('api/webauthn_auth_challenge.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ teacher_id: teacherId })
                });
                
                const challengeData = await response.json();
                
                if (!challengeData.success) {
                    throw new Error(challengeData.error || 'Failed to get challenge');
                }
                
                statusDiv.textContent = 'Please use your fingerprint scanner...';
                
                // Convert challenge for WebAuthn API
                const publicKeyCredentialRequestOptions = {
                    challenge: Uint8Array.from(atob(challengeData.challenge), c => c.charCodeAt(0)),
                    allowCredentials: challengeData.allowCredentials.map(cred => ({
                        id: Uint8Array.from(atob(cred.id), c => c.charCodeAt(0)),
                        type: 'public-key',
                        transports: ['internal']
                    })),
                    timeout: 60000,
                    userVerification: 'required'
                };
                
                // Get credential
                const credential = await navigator.credentials.get({
                    publicKey: publicKeyCredentialRequestOptions
                });
                
                statusDiv.textContent = 'Verifying authentication...';
                
                // Send credential to server
                const credentialResponse = {
                    id: credential.id,
                    rawId: Array.from(new Uint8Array(credential.rawId)),
                    response: {
                        authenticatorData: Array.from(new Uint8Array(credential.response.authenticatorData)),
                        clientDataJSON: Array.from(new Uint8Array(credential.response.clientDataJSON)),
                        signature: Array.from(new Uint8Array(credential.response.signature)),
                        userHandle: credential.response.userHandle ? Array.from(new Uint8Array(credential.response.userHandle)) : null
                    },
                    type: credential.type
                };
                
                const verifyResponse = await fetch(`api/clock_${action}.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        teacher_id: teacherId,
                        credential: credentialResponse
                    })
                });
                
                const verifyData = await verifyResponse.json();
                
                if (verifyData.success) {
                    statusDiv.className = 'alert alert-success';
                    statusDiv.textContent = verifyData.message || `Clock ${action} successful!`;
                    loadTodayAttendance();
                } else {
                    throw new Error(verifyData.error || 'Authentication failed');
                }
            } catch (error) {
                statusDiv.className = 'alert alert-danger';
                statusDiv.textContent = 'Error: ' + error.message;
                console.error('Clock action error:', error);
            } finally {
                btn.disabled = false;
            }
        }
        
        async function loadTodayAttendance() {
            const response = await fetch(`api/get_today_attendance.php?teacher_id=${teacherId}`);
            const data = await response.json();
            
            const container = document.getElementById('today-attendance');
            if (data.success && data.attendance.length > 0) {
                let html = '<table class="data-table"><thead><tr><th>Time</th><th>Action</th><th>Status</th></tr></thead><tbody>';
                data.attendance.forEach(record => {
                    html += `<tr>
                        <td>${record.time}</td>
                        <td>${record.action}</td>
                        <td>${record.status}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p>No attendance records for today.</p>';
            }
        }
    </script>
</body>
</html>

