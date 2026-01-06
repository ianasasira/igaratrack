<?php
require_once '../config/config.php';
requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$teacherId = (int)($data['teacher_id'] ?? 0);
$credential = $data['credential'] ?? null;

if (!$teacherId || !$credential) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (!isset($_SESSION['webauthn_challenge']) || $_SESSION['webauthn_teacher_id'] != $teacherId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit;
}

try {
    // Decode credential data
    $credentialId = base64_encode($credential['rawId']);
    $attestationObject = base64_encode(pack('C*', ...$credential['response']['attestationObject']));
    $clientDataJSON = base64_encode(pack('C*', ...$credential['response']['clientDataJSON']));
    
    // Verify client data
    $clientData = json_decode(base64_decode($clientDataJSON), true);
    if (!$clientData || $clientData['type'] !== 'webauthn.create') {
        throw new Exception('Invalid client data');
    }
    
    // Verify challenge
    $expectedChallenge = base64_encode(base64_decode($_SESSION['webauthn_challenge']));
    if (!hash_equals($expectedChallenge, $clientData['challenge'])) {
        throw new Exception('Challenge mismatch');
    }
    
    // Verify origin
    if ($clientData['origin'] !== RP_ORIGIN) {
        throw new Exception('Origin mismatch');
    }
    
    // Parse attestation object (simplified - in production, use proper CBOR parser)
    // For now, we'll store the raw data
    $db = getDBConnection();
    
    // Extract public key from attestation object (simplified)
    // In production, use a proper CBOR/COSE library
    $publicKey = $attestationObject; // Store raw for now
    
    // Store credential
    $stmt = $db->prepare("INSERT INTO webauthn_credentials (teacher_id, credential_id, public_key) VALUES (?, ?, ?)");
    $stmt->execute([$teacherId, $credentialId, $publicKey]);
    
    logAudit('admin', $_SESSION['admin_id'], 'register_biometric', "Registered biometric for teacher ID: $teacherId");
    
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_teacher_id'], $_SESSION['webauthn_user_id']);
    
    echo json_encode(['success' => true, 'message' => 'Biometric registered successfully']);
} catch (Exception $e) {
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_teacher_id'], $_SESSION['webauthn_user_id']);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

