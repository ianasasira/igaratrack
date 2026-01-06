<?php
require_once '../config/config.php';
requireAdminLogin();

$db = getDBConnection();

// Get statistics
$stats = [];

// Total teachers
$stmt = $db->query("SELECT COUNT(*) as count FROM teachers WHERE status = 'active'");
$stats['total_teachers'] = $stmt->fetch()['count'];

// Total lessons today
$today = date('Y-m-d');
$dayOfWeek = getDayOfWeek($today);
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lesson_timetables 
    WHERE day_of_week = ? AND teacher_id IN (SELECT id FROM teachers WHERE status = 'active')
");
$stmt->execute([$dayOfWeek]);
$stats['lessons_today'] = $stmt->fetch()['count'];

// Attendance today
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM attendance_logs 
    WHERE lesson_date = ? AND attendance_status = 'present'
");
$stmt->execute([$today]);
$stats['attendance_today'] = $stmt->fetch()['count'];

// This week's attendance
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) as present
    FROM attendance_logs 
    WHERE lesson_date BETWEEN ? AND ?
");
$stmt->execute([$weekStart, $weekEnd]);
$weekData = $stmt->fetch();
$stats['week_total'] = $weekData['total'];
$stats['week_present'] = $weekData['present'];
$stats['week_percentage'] = $stats['week_total'] > 0 ? round(($stats['week_present'] / $stats['week_total']) * 100, 1) : 0;

// Get top teachers (this month)
$monthStart = date('Y-m-01');
$stmt = $db->prepare("
    SELECT t.name, COUNT(*) as lessons_taught
    FROM attendance_logs al
    JOIN teachers t ON al.teacher_id = t.id
    WHERE al.lesson_date >= ? AND al.attendance_status = 'present'
    GROUP BY t.id, t.name
    ORDER BY lessons_taught DESC
    LIMIT 5
");
$stmt->execute([$monthStart]);
$topTeachers = $stmt->fetchAll();

// Get attendance trends (last 7 days)
$stmt = $db->prepare("
    SELECT 
        lesson_date,
        COUNT(*) as total,
        SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) as present
    FROM attendance_logs
    WHERE lesson_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY lesson_date
    ORDER BY lesson_date ASC
");
$stmt->execute();
$trends = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Teacher Attendance System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <h1>Dashboard</h1>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_teachers']; ?></h3>
                    <p>Active Teachers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-content">
                    <h3><?php echo $stats['lessons_today']; ?></h3>
                    <p>Lessons Today</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $stats['attendance_today']; ?></h3>
                    <p>Attendance Today</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3><?php echo $stats['week_percentage']; ?>%</h3>
                    <p>Week Attendance Rate</p>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <h2>Attendance Trends (Last 7 Days)</h2>
                <canvas id="trendsChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h2>Top Teachers This Month</h2>
                <canvas id="topTeachersChart"></canvas>
            </div>
        </div>
        
        <!-- Top Teachers Table -->
        <div class="card">
            <h2>Top Performing Teachers</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Teacher Name</th>
                        <th>Lessons Taught</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topTeachers)): ?>
                        <tr>
                            <td colspan="3" class="text-center">No data available</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topTeachers as $index => $teacher): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                <td><?php echo $teacher['lessons_taught']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Trends Chart
        const trendsData = <?php echo json_encode($trends); ?>;
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(d => d.lesson_date),
                datasets: [{
                    label: 'Total Lessons',
                    data: trendsData.map(d => parseInt(d.total)),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }, {
                    label: 'Present',
                    data: trendsData.map(d => parseInt(d.present)),
                    borderColor: 'rgb(54, 162, 235)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Top Teachers Chart
        const topTeachersData = <?php echo json_encode($topTeachers); ?>;
        const topTeachersCtx = document.getElementById('topTeachersChart').getContext('2d');
        new Chart(topTeachersCtx, {
            type: 'bar',
            data: {
                labels: topTeachersData.map(t => t.name),
                datasets: [{
                    label: 'Lessons Taught',
                    data: topTeachersData.map(t => parseInt(t.lessons_taught)),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>

