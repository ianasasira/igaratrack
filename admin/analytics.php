<?php
require_once '../config/config.php';
requireAdminLogin();

$db = getDBConnection();

// Get filter parameters
$period = $_GET['period'] ?? 'week';
$teacherId = (int)($_GET['teacher_id'] ?? 0);
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Set date range based on period
$now = new DateTime('now', new DateTimeZone('Africa/Kampala'));
switch ($period) {
    case 'today':
        $startDate = $now->format('Y-m-d');
        $endDate = $now->format('Y-m-d');
        break;
    case 'week':
        $startDate = $now->format('Y-m-d');
        $endDate = $now->modify('+6 days')->format('Y-m-d');
        break;
    case 'month':
        $startDate = $now->format('Y-m-01');
        $endDate = $now->format('Y-m-t');
        break;
}

if (!$startDate) {
    $startDate = date('Y-m-d', strtotime('monday this week'));
}
if (!$endDate) {
    $endDate = date('Y-m-d', strtotime('sunday this week'));
}

// Get all teachers for filter
$stmt = $db->query("SELECT id, name FROM teachers WHERE status = 'active' ORDER BY name");
$teachers = $stmt->fetchAll();

// Build query
$query = "
    SELECT 
        t.id,
        t.name,
        COUNT(al.id) as total_lessons,
        SUM(CASE WHEN al.attendance_status = 'present' THEN 1 ELSE 0 END) as lessons_attended,
        SUM(CASE WHEN al.attendance_status = 'absent' OR al.attendance_status = 'missed' THEN 1 ELSE 0 END) as lessons_missed,
        SUM(CASE WHEN al.clock_in_status = 'late' THEN 1 ELSE 0 END) as late_arrivals,
        SUM(CASE WHEN al.clock_in_status = 'very_late' THEN 1 ELSE 0 END) as very_late_arrivals
    FROM teachers t
    LEFT JOIN attendance_logs al ON t.id = al.teacher_id 
        AND al.lesson_date BETWEEN ? AND ?
    WHERE t.status = 'active'
";

$params = [$startDate, $endDate];

if ($teacherId) {
    $query .= " AND t.id = ?";
    $params[] = $teacherId;
}

$query .= " GROUP BY t.id, t.name ORDER BY lessons_attended DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$analytics = $stmt->fetchAll();

// Calculate attendance scores
foreach ($analytics as &$row) {
    if ($row['total_lessons'] > 0) {
        $row['attendance_score'] = round(($row['lessons_attended'] / $row['total_lessons']) * 100, 1);
    } else {
        $row['attendance_score'] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <h1>Performance Analytics</h1>
        
        <!-- Filters -->
        <div class="card">
            <h2>Filters</h2>
            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="period">Period</label>
                        <select id="period" name="period" onchange="this.form.submit()">
                            <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="custom" <?php echo $period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="teacher_id">Teacher</label>
                        <select id="teacher_id" name="teacher_id" onchange="this.form.submit()">
                            <option value="">All Teachers</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                        <?php echo $teacherId == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($period == 'custom'): ?>
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="analytics.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Analytics Table -->
        <div class="card">
            <h2>Performance Metrics</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Total Lessons</th>
                        <th>Lessons Attended</th>
                        <th>Lessons Missed</th>
                        <th>Late Arrivals</th>
                        <th>Very Late</th>
                        <th>Attendance Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($analytics)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No data available for the selected period</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($analytics as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo $row['total_lessons']; ?></td>
                                <td><?php echo $row['lessons_attended']; ?></td>
                                <td><?php echo $row['lessons_missed']; ?></td>
                                <td><?php echo $row['late_arrivals']; ?></td>
                                <td><?php echo $row['very_late_arrivals']; ?></td>
                                <td>
                                    <strong><?php echo $row['attendance_score']; ?>%</strong>
                                    <?php if ($row['attendance_score'] >= 90): ?>
                                        <span class="badge badge-success">Excellent</span>
                                    <?php elseif ($row['attendance_score'] >= 75): ?>
                                        <span class="badge badge-primary">Good</span>
                                    <?php elseif ($row['attendance_score'] >= 60): ?>
                                        <span class="badge badge-warning">Fair</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Poor</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Charts -->
        <div class="charts-row">
            <div class="chart-card">
                <h2>Attendance Distribution</h2>
                <canvas id="attendanceChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h2>Top Performers</h2>
                <canvas id="performersChart"></canvas>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const analyticsData = <?php echo json_encode($analytics); ?>;
        
        // Attendance Distribution Chart
        if (analyticsData.length > 0) {
            const totalAttended = analyticsData.reduce((sum, row) => sum + parseInt(row.lessons_attended), 0);
            const totalMissed = analyticsData.reduce((sum, row) => sum + parseInt(row.lessons_missed), 0);
            
            const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
            new Chart(attendanceCtx, {
                type: 'pie',
                data: {
                    labels: ['Attended', 'Missed'],
                    datasets: [{
                        data: [totalAttended, totalMissed],
                        backgroundColor: ['#28a745', '#dc3545']
                    }]
                }
            });
            
            // Top Performers Chart
            const topPerformers = analyticsData.slice(0, 5);
            const performersCtx = document.getElementById('performersChart').getContext('2d');
            new Chart(performersCtx, {
                type: 'bar',
                data: {
                    labels: topPerformers.map(t => t.name),
                    datasets: [{
                        label: 'Attendance Score (%)',
                        data: topPerformers.map(t => parseFloat(t.attendance_score)),
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>

