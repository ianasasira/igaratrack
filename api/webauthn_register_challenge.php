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

if (!$teacherId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Teacher ID required']);
    exit;
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Teacher not found']);
    exit;
}

// Generate challenge
$challenge = random_bytes(32);
$_SESSION['webauthn_challenge'] = base64_encode($challenge);
$_SESSION['webauthn_teacher_id'] = $teacherId;
$_SESSION['webauthn_user_id'] = base64_encode($teacherId . '_' . time());

echo json_encode([
    'success' => true,
    'challenge' => base64_encode($challenge),
    'user_id' => $_SESSION['webauthn_user_id'],
    'user_name' => $teacher['name'],
    'user_display_name' => $teacher['name']
]);

