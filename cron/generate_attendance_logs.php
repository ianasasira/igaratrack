<?php
/**
 * Cron Job: Generate Attendance Logs
 * 
 * This script should be run daily (e.g., via cron) to pre-generate
 * attendance log entries for scheduled lessons.
 * 
 * Usage: php cron/generate_attendance_logs.php
 */

require_once __DIR__ . '/../config/config.php';

$db = getDBConnection();
$today = date('Y-m-d');
$dayOfWeek = getDayOfWeek($today);

// Check if today is a public holiday
if (isPublicHoliday($today)) {
    echo "Today is a public holiday. No attendance logs generated.\n";
    exit;
}

// Get all active teachers with timetables for today
$stmt = $db->prepare("
    SELECT DISTINCT t.id as teacher_id, lt.lesson_start, lt.lesson_end
    FROM teachers t
    INNER JOIN lesson_timetables lt ON t.id = lt.teacher_id
    WHERE t.status = 'active' AND lt.day_of_week = ?
");
$stmt->execute([$dayOfWeek]);
$lessons = $stmt->fetchAll();

$created = 0;
$skipped = 0;

foreach ($lessons as $lesson) {
    // Check if attendance log already exists
    $checkStmt = $db->prepare("
        SELECT id FROM attendance_logs 
        WHERE teacher_id = ? AND lesson_date = ? AND lesson_start_time = ?
    ");
    $checkStmt->execute([$lesson['teacher_id'], $today, $lesson['lesson_start']]);
    
    if ($checkStmt->fetch()) {
        $skipped++;
        continue;
    }
    
    // Create attendance log entry
    $insertStmt = $db->prepare("
        INSERT INTO attendance_logs 
        (teacher_id, lesson_date, lesson_start_time, lesson_end_time, attendance_status)
        VALUES (?, ?, ?, ?, 'absent')
    ");
    $insertStmt->execute([
        $lesson['teacher_id'],
        $today,
        $lesson['lesson_start'],
        $lesson['lesson_end']
    ]);
    
    $created++;
}

echo "Attendance logs generated: $created created, $skipped skipped.\n";

