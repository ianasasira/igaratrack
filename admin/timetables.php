<?php
require_once '../config/config.php';
requireAdminLogin();

$db = getDBConnection();
$message = '';
$error = '';
$selectedTeacherId = (int)($_GET['teacher_id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $teacherId = (int)$_POST['teacher_id'];
                $dayOfWeek = (int)$_POST['day_of_week'];
                $lessonStart = sanitize($_POST['lesson_start'] ?? '');
                $lessonEnd = sanitize($_POST['lesson_end'] ?? '');
                $subject = sanitize($_POST['subject'] ?? '');
                
                if (empty($lessonStart) || empty($lessonEnd)) {
                    $error = 'Lesson start and end times are required';
                } else {
                    try {
                        $stmt = $db->prepare("INSERT INTO lesson_timetables (teacher_id, day_of_week, lesson_start, lesson_end, subject) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$teacherId, $dayOfWeek, $lessonStart, $lessonEnd, $subject]);
                        logAudit('admin', $_SESSION['admin_id'], 'add_timetable', "Added timetable for teacher ID: $teacherId");
                        $message = 'Timetable entry added successfully';
                        $selectedTeacherId = $teacherId;
                    } catch (PDOException $e) {
                        $error = 'Error adding timetable: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $db->prepare("DELETE FROM lesson_timetables WHERE id = ?");
                    $stmt->execute([$id]);
                    logAudit('admin', $_SESSION['admin_id'], 'delete_timetable', "Deleted timetable ID: $id");
                    $message = 'Timetable entry deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Error deleting timetable: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all teachers
$stmt = $db->query("SELECT id, name FROM teachers WHERE status = 'active' ORDER BY name");
$teachers = $stmt->fetchAll();

// Get timetables
$timetables = [];
if ($selectedTeacherId) {
    $stmt = $db->prepare("SELECT * FROM lesson_timetables WHERE teacher_id = ? ORDER BY day_of_week, lesson_start");
    $stmt->execute([$selectedTeacherId]);
    $timetables = $stmt->fetchAll();
}

$daysOfWeek = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <h1>Lesson Timetable Management</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Add Form -->
        <div class="card">
            <h2>Add Timetable Entry</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="teacher_id">Teacher *</label>
                        <select id="teacher_id" name="teacher_id" required onchange="this.form.submit()">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                        <?php echo $selectedTeacherId == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="day_of_week">Day of Week *</label>
                        <select id="day_of_week" name="day_of_week" required>
                            <?php foreach ($daysOfWeek as $num => $day): ?>
                                <option value="<?php echo $num; ?>"><?php echo $day; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="lesson_start">Lesson Start Time *</label>
                        <input type="time" id="lesson_start" name="lesson_start" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lesson_end">Lesson End Time *</label>
                        <input type="time" id="lesson_end" name="lesson_end" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" placeholder="Optional">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Timetable Entry</button>
                </div>
            </form>
        </div>
        
        <!-- Timetables List -->
        <?php if ($selectedTeacherId): ?>
            <div class="card">
                <h2>Timetable for Selected Teacher</h2>
                <?php if (empty($timetables)): ?>
                    <p>No timetable entries found for this teacher.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Subject</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetables as $timetable): ?>
                                <tr>
                                    <td><?php echo $daysOfWeek[$timetable['day_of_week']]; ?></td>
                                    <td><?php echo date('h:i A', strtotime($timetable['lesson_start'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($timetable['lesson_end'])); ?></td>
                                    <td><?php echo htmlspecialchars($timetable['subject'] ?? '-'); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this timetable entry?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $timetable['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

