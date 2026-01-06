<?php
/**
 * Helper Functions
 */

/**
 * Check if user is logged in as admin
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

/**
 * Require admin login
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Check if user is logged in as teacher
 */
function isTeacherLoggedIn() {
    return isset($_SESSION['teacher_id']) && isset($_SESSION['teacher_name']);
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Log audit action
 */
function logAudit($userType, $userId, $action, $details = null) {
    $db = getDBConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $db->prepare("INSERT INTO audit_logs (user_type, user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userType, $userId, $action, $details, $ip]);
}

/**
 * Check if date is a public holiday
 */
function isPublicHoliday($date) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT id FROM public_holidays 
        WHERE holiday_date <= ? AND (end_date >= ? OR end_date IS NULL)
    ");
    $stmt->execute([$date, $date]);
    return $stmt->fetch() !== false;
}

/**
 * Get day of week number (1=Monday, 7=Sunday)
 */
function getDayOfWeek($date) {
    $day = date('N', strtotime($date));
    return (int)$day;
}

/**
 * Calculate attendance status
 */
function calculateAttendanceStatus($clockInTime, $clockOutTime, $lessonStart, $lessonEnd) {
    $status = [
        'clock_in_status' => 'absent',
        'clock_out_status' => 'absent',
        'attendance_status' => 'absent'
    ];
    
    // If no clock-in, mark as absent
    if (!$clockInTime) {
        return $status;
    }
    
    $timezone = new DateTimeZone('Africa/Kampala');
    
    // Calculate clock-in status
    $clockInDateTime = new DateTime($clockInTime, $timezone);
    $lessonStartDateTime = new DateTime($clockInDateTime->format('Y-m-d') . ' ' . $lessonStart, $timezone);
    $secondsDiff = $clockInDateTime->getTimestamp() - $lessonStartDateTime->getTimestamp();
    $minutesDiff = round($secondsDiff / 60);
    
    if ($clockInDateTime < $lessonStartDateTime) {
        $hoursBefore = abs($minutesDiff) / 60;
        if ($hoursBefore >= 1) {
            $status['clock_in_status'] = 'early';
        } else {
            $status['clock_in_status'] = 'on_time';
        }
    } else {
        if ($minutesDiff <= 30) {
            $status['clock_in_status'] = 'late';
        } else {
            $status['clock_in_status'] = 'very_late';
        }
    }
    
    // If no clock-out, mark as absent
    if (!$clockOutTime) {
        return $status;
    }
    
    // Calculate clock-out status
    $clockOutDateTime = new DateTime($clockOutTime, $timezone);
    $lessonEndDateTime = new DateTime($clockOutDateTime->format('Y-m-d') . ' ' . $lessonEnd, $timezone);
    $secondsDiff = $clockOutDateTime->getTimestamp() - $lessonEndDateTime->getTimestamp();
    $minutesDiff = round($secondsDiff / 60);
    
    if ($clockOutDateTime <= $lessonEndDateTime) {
        $status['clock_out_status'] = 'on_time';
    } else {
        $status['clock_out_status'] = 'late';
    }
    
    // If both clock-in and clock-out exist, mark as present
    if ($clockInTime && $clockOutTime) {
        $status['attendance_status'] = 'present';
    }
    
    return $status;
}

/**
 * Format time difference
 */
function formatTimeDiff($datetime1, $datetime2) {
    $date1 = new DateTime($datetime1);
    $date2 = new DateTime($datetime2);
    $diff = $date1->diff($date2);
    
    if ($diff->days > 0) {
        return $diff->days . ' day(s) ' . $diff->h . ' hour(s)';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour(s) ' . $diff->i . ' minute(s)';
    } else {
        return $diff->i . ' minute(s)';
    }
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badges = [
        'early' => '<span class="badge badge-success">Early</span>',
        'on_time' => '<span class="badge badge-primary">On Time</span>',
        'late' => '<span class="badge badge-warning">Late</span>',
        'very_late' => '<span class="badge badge-danger">Very Late</span>',
        'absent' => '<span class="badge badge-secondary">Absent</span>',
        'missed' => '<span class="badge badge-secondary">Missed</span>',
        'present' => '<span class="badge badge-success">Present</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

