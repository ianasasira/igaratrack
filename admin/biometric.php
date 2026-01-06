<?php
require_once '../config/config.php';
requireAdminLogin();

$db = getDBConnection();
$teacherId = (int)($_GET['teacher_id'] ?? 0);

if (!$teacherId) {
    header('Location: teachers.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    header('Location: teachers.php');
    exit;
}

// Check if already registered
$stmt = $db->prepare("SELECT COUNT(*) as count FROM webauthn_credentials WHERE teacher_id = ?");
$stmt->execute([$teacherId]);
$hasCredentials = $stmt->fetch()['count'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Biometric - <?php echo htmlspecialchars($teacher['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <h1>Register Biometric for <?php echo htmlspecialchars($teacher['name']); ?></h1>
        
        <?php if ($hasCredentials): ?>
            <div class="alert alert-info">
                This teacher already has biometric credentials registered. You can register additional credentials below.
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>WebAuthn Fingerprint Registration</h2>
            <p>Please use a device with fingerprint scanner (smartphone recommended).</p>
            
            <div id="registration-status" class="alert" style="display:none;"></div>
            
            <div class="form-actions">
                <button id="register-btn" class="btn btn-primary">Register Fingerprint</button>
                <a href="teachers.php" class="btn btn-secondary">Back to Teachers</a>
            </div>
        </div>
    </div>
    
    <script>
        const teacherId = <?php echo $teacherId; ?>;
        const rpId = '<?php echo RP_ID; ?>';
        const rpName = '<?php echo RP_NAME; ?>';
        
        document.getElementById('register-btn').addEventListener('click', async () => {
            const statusDiv = document.getElementById('registration-status');
            const btn = document.getElementById('register-btn');
            
            try {
                btn.disabled = true;
                statusDiv.style.display = 'block';
                statusDiv.className = 'alert alert-info';
                statusDiv.textContent = 'Preparing registration...';
                
                // Get challenge from server
                const response = await fetch('../api/webauthn_register_challenge.php', {
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
                
                // Convert challenge data for WebAuthn API
                const publicKeyCredentialCreationOptions = {
                    challenge: Uint8Array.from(atob(challengeData.challenge), c => c.charCodeAt(0)),
                    rp: {
                        name: rpName,
                        id: rpId
                    },
                    user: {
                        id: Uint8Array.from(challengeData.user_id, c => c.charCodeAt(0)),
                        name: challengeData.user_name,
                        displayName: challengeData.user_display_name
                    },
                    pubKeyCredParams: [
                        { alg: -7, type: 'public-key' }, // ES256
                        { alg: -257, type: 'public-key' } // RS256
                    ],
                    authenticatorSelection: {
                        authenticatorAttachment: 'platform',
                        userVerification: 'required'
                    },
                    timeout: 60000,
                    attestation: 'direct'
                };
                
                // Create credential
                const credential = await navigator.credentials.create({
                    publicKey: publicKeyCredentialCreationOptions
                });
                
                statusDiv.textContent = 'Verifying registration...';
                
                // Send credential to server
                const credentialResponse = {
                    id: credential.id,
                    rawId: Array.from(new Uint8Array(credential.rawId)),
                    response: {
                        attestationObject: Array.from(new Uint8Array(credential.response.attestationObject)),
                        clientDataJSON: Array.from(new Uint8Array(credential.response.clientDataJSON))
                    },
                    type: credential.type
                };
                
                const verifyResponse = await fetch('../api/webauthn_register_verify.php', {
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
                    statusDiv.textContent = 'Biometric registration successful!';
                    btn.textContent = 'Register Another';
                } else {
                    throw new Error(verifyData.error || 'Registration verification failed');
                }
            } catch (error) {
                statusDiv.className = 'alert alert-danger';
                statusDiv.textContent = 'Error: ' + error.message;
                console.error('Registration error:', error);
            } finally {
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>

