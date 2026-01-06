<?php
require_once '../config/config.php';

header('Content-Type: application/json');

$teacherId = (int)($_GET['teacher_id'] ?? 0);

if (!$teacherId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Teacher ID required']);
    exit;
}

$db = getDBConnection();
$today = date('Y-m-d');

$stmt = $db->prepare("
    SELECT 
        clock_in_time,
        clock_out_time,
        clock_in_status,
        clock_out_status,
        attendance_status,
        lesson_start_time,
        lesson_end_time
    FROM attendance_logs 
    WHERE teacher_id = ? AND lesson_date = ?
    ORDER BY lesson_start_time
");
$stmt->execute([$teacherId, $today]);
$records = $stmt->fetchAll();

$attendance = [];
foreach ($records as $record) {
    if ($record['clock_in_time']) {
        $attendance[] = [
            'time' => date('h:i A', strtotime($record['clock_in_time'])),
            'action' => 'Clock In',
            'status' => getStatusBadge($record['clock_in_status'])
        ];
    }
    if ($record['clock_out_time']) {
        $attendance[] = [
            'time' => date('h:i A', strtotime($record['clock_out_time'])),
            'action' => 'Clock Out',
            'status' => getStatusBadge($record['clock_out_status'])
        ];
    }
}

echo json_encode([
    'success' => true,
    'attendance' => $attendance
]);

