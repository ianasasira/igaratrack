<?php
require_once '../config/config.php';
requireAdminLogin();

$db = getDBConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name'] ?? '');
                $holidayDate = sanitize($_POST['holiday_date'] ?? '');
                $endDate = sanitize($_POST['end_date'] ?? '');
                $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
                
                if (empty($name) || empty($holidayDate)) {
                    $error = 'Holiday name and date are required';
                } else {
                    try {
                        $stmt = $db->prepare("INSERT INTO public_holidays (name, holiday_date, end_date, is_recurring) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $holidayDate, $endDate ?: null, $isRecurring]);
                        logAudit('admin', $_SESSION['admin_id'], 'add_holiday', "Added holiday: $name");
                        $message = 'Holiday added successfully';
                    } catch (PDOException $e) {
                        $error = 'Error adding holiday: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $db->prepare("DELETE FROM public_holidays WHERE id = ?");
                    $stmt->execute([$id]);
                    logAudit('admin', $_SESSION['admin_id'], 'delete_holiday', "Deleted holiday ID: $id");
                    $message = 'Holiday deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Error deleting holiday: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all holidays
$stmt = $db->query("SELECT * FROM public_holidays ORDER BY holiday_date DESC");
$holidays = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Management - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <h1>Public Holiday Management</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Add Form -->
        <div class="card">
            <h2>Add Public Holiday</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Holiday Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="holiday_date">Holiday Date *</label>
                        <input type="date" id="holiday_date" name="holiday_date" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="end_date">End Date (for date ranges)</label>
                        <input type="date" id="end_date" name="end_date">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_recurring" value="1">
                            Recurring Yearly
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Holiday</button>
                </div>
            </form>
        </div>
        
        <!-- Holidays List -->
        <div class="card">
            <h2>All Public Holidays</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Recurring</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($holidays)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No holidays found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($holidays as $holiday): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($holiday['name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($holiday['holiday_date'])); ?></td>
                                <td><?php echo $holiday['end_date'] ? date('M d, Y', strtotime($holiday['end_date'])) : '-'; ?></td>
                                <td><?php echo $holiday['is_recurring'] ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this holiday?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $holiday['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

