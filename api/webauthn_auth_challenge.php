<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$teacherId = (int)($data['teacher_id'] ?? 0);

if (!$teacherId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Teacher ID required']);
    exit;
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT credential_id FROM webauthn_credentials WHERE teacher_id = ?");
$stmt->execute([$teacherId]);
$credentials = $stmt->fetchAll();

if (empty($credentials)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'No credentials found']);
    exit;
}

// Generate challenge
$challenge = random_bytes(32);
$_SESSION['webauthn_challenge'] = base64_encode($challenge);
$_SESSION['webauthn_teacher_id'] = $teacherId;

// Prepare allowed credentials
$allowCredentials = [];
foreach ($credentials as $cred) {
    $allowCredentials[] = [
        'id' => $cred['credential_id'],
        'type' => 'public-key',
        'transports' => ['internal']
    ];
}

echo json_encode([
    'success' => true,
    'challenge' => base64_encode($challenge),
    'allowCredentials' => $allowCredentials
]);

