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
    // Verify WebAuthn credential (simplified)
    $credentialId = base64_encode($credential['rawId']);
    $clientDataJSON = base64_encode(pack('C*', ...$credential['response']['clientDataJSON']));
    
    $clientData = json_decode(base64_decode($clientDataJSON), true);
    if (!$clientData || $clientData['type'] !== 'webauthn.get') {
        throw new Exception('Invalid client data');
    }
    
    // Verify challenge
    $expectedChallenge = base64_encode(base64_decode($_SESSION['webauthn_challenge']));
    if (!hash_equals($expectedChallenge, $clientData['challenge'])) {
        throw new Exception('Challenge mismatch');
    }
    
    // Verify credential exists
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id FROM webauthn_credentials WHERE teacher_id = ? AND credential_id = ?");
    $stmt->execute([$teacherId, $credentialId]);
    if (!$stmt->fetch()) {
        throw new Exception('Credential not found');
    }
    
    // Update counter
    $stmt = $db->prepare("UPDATE webauthn_credentials SET counter = counter + 1, last_used = NOW() WHERE credential_id = ?");
    $stmt->execute([$credentialId]);
    
    // Get current date and time
    $now = new DateTime('now', new DateTimeZone('Africa/Kampala'));
    $today = $now->format('Y-m-d');
    
    // Find today's lesson with clock-in but no clock-out
    $stmt = $db->prepare("
        SELECT id, lesson_start_time, lesson_end_time, clock_in_time
        FROM attendance_logs 
        WHERE teacher_id = ? AND lesson_date = ? AND clock_in_time IS NOT NULL AND clock_out_time IS NULL
        ORDER BY lesson_start_time DESC
        LIMIT 1
    ");
    $stmt->execute([$teacherId, $today]);
    $attendance = $stmt->fetch();
    
    if (!$attendance) {
        throw new Exception('No clock-in found for today. Please clock in first.');
    }
    
    // Check if already clocked out
    if ($attendance['clock_out_time']) {
        throw new Exception('You have already clocked out for this lesson');
    }
    
    // Calculate clock-out status
    $lessonEndDateTime = new DateTime($today . ' ' . $attendance['lesson_end_time'], new DateTimeZone('Africa/Kampala'));
    $secondsDiff = $now->getTimestamp() - $lessonEndDateTime->getTimestamp();
    $minutesDiff = round($secondsDiff / 60);
    
    $clockOutStatus = $now <= $lessonEndDateTime ? 'on_time' : ($minutesDiff <= 30 ? 'late' : 'late');
    
    // Calculate final attendance status
    $status = calculateAttendanceStatus(
        $attendance['clock_in_time'],
        $now->format('Y-m-d H:i:s'),
        $attendance['lesson_start_time'],
        $attendance['lesson_end_time']
    );
    
    // Update attendance log
    $stmt = $db->prepare("
        UPDATE attendance_logs 
        SET clock_out_time = ?, clock_out_status = ?, 
            clock_in_status = ?, attendance_status = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $now->format('Y-m-d H:i:s'),
        $clockOutStatus,
        $status['clock_in_status'],
        $status['attendance_status'],
        $attendance['id']
    ]);
    
    logAudit('teacher', $teacherId, 'clock_out', "Clocked out at " . $now->format('Y-m-d H:i:s'));
    
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_teacher_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Clock-out successful! Status: ' . ucfirst(str_replace('_', ' ', $status['attendance_status']))
    ]);
    
} catch (Exception $e) {
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_teacher_id']);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

