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
    // Verify WebAuthn credential (simplified - in production use proper verification)
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
    $currentTime = $now->format('H:i:s');
    
    // Get today's lessons for this teacher
    $dayOfWeek = getDayOfWeek($today);
    
    // Check if today is a holiday
    if (isPublicHoliday($today)) {
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_teacher_id']);
        echo json_encode([
            'success' => true,
            'message' => 'Clock-in recorded. Note: Today is a public holiday.',
            'is_holiday' => true
        ]);
        exit;
    }
    
    // Find matching lesson
    $stmt = $db->prepare("
        SELECT id, lesson_start, lesson_end 
        FROM lesson_timetables 
        WHERE teacher_id = ? AND day_of_week = ?
        ORDER BY lesson_start
    ");
    $stmt->execute([$teacherId, $dayOfWeek]);
    $lessons = $stmt->fetchAll();
    
    if (empty($lessons)) {
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_teacher_id']);
        echo json_encode([
            'success' => true,
            'message' => 'Clock-in recorded. No lessons scheduled for today.',
            'no_lessons' => true
        ]);
        exit;
    }
    
    // Find the lesson that matches current time (within 2 hours before or after start)
    $matchedLesson = null;
    foreach ($lessons as $lesson) {
        $lessonStart = new DateTime($today . ' ' . $lesson['lesson_start'], new DateTimeZone('Africa/Kampala'));
        $secondsDiff = $now->getTimestamp() - $lessonStart->getTimestamp();
        $minutesDiff = round($secondsDiff / 60);
        
        // Allow clock-in up to 2 hours before or 1 hour after lesson start
        if ($minutesDiff >= -120 && $minutesDiff <= 60) {
            $matchedLesson = $lesson;
            break;
        }
    }
    
    if (!$matchedLesson) {
        // Use the next upcoming lesson or the first lesson of the day
        $matchedLesson = $lessons[0];
    }
    
    // Check if clock-in already exists for this lesson today
    $stmt = $db->prepare("
        SELECT id, clock_in_time FROM attendance_logs 
        WHERE teacher_id = ? AND lesson_date = ? AND lesson_start_time = ?
    ");
    $stmt->execute([$teacherId, $today, $matchedLesson['lesson_start']]);
    $existing = $stmt->fetch();
    
    if ($existing && $existing['clock_in_time']) {
        throw new Exception('You have already clocked in for this lesson');
    }
    
    // Calculate clock-in status
    $lessonStartDateTime = new DateTime($today . ' ' . $matchedLesson['lesson_start'], new DateTimeZone('Africa/Kampala'));
    $secondsDiff = $now->getTimestamp() - $lessonStartDateTime->getTimestamp();
    $minutesDiff = round($secondsDiff / 60);
    
    if ($now < $lessonStartDateTime) {
        $hoursBefore = abs($minutesDiff) / 60;
        $clockInStatus = $hoursBefore >= 1 ? 'early' : 'on_time';
    } else {
        $clockInStatus = $minutesDiff <= 30 ? 'late' : 'very_late';
    }
    
    // Insert or update attendance log
    if ($existing) {
        $stmt = $db->prepare("
            UPDATE attendance_logs 
            SET clock_in_time = ?, clock_in_status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$now->format('Y-m-d H:i:s'), $clockInStatus, $existing['id']]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO attendance_logs 
            (teacher_id, lesson_date, lesson_start_time, lesson_end_time, clock_in_time, clock_in_status, attendance_status)
            VALUES (?, ?, ?, ?, ?, ?, 'absent')
        ");
        $stmt->execute([
            $teacherId,
            $today,
            $matchedLesson['lesson_start'],
            $matchedLesson['lesson_end'],
            $now->format('Y-m-d H:i:s'),
            $clockInStatus
        ]);
    }
    
    logAudit('teacher', $teacherId, 'clock_in', "Clocked in at " . $now->format('Y-m-d H:i:s'));
    
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_teacher_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Clock-in successful! Status: ' . ucfirst(str_replace('_', ' ', $clockInStatus))
    ]);
    
} catch (Exception $e) {
    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_teacher_id']);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

