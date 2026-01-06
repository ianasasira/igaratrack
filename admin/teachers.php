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
                $email = sanitize($_POST['email'] ?? '');
                $phone = sanitize($_POST['phone'] ?? '');
                $employee_id = sanitize($_POST['employee_id'] ?? '');
                
                if (empty($name)) {
                    $error = 'Teacher name is required';
                } else {
                    try {
                        $stmt = $db->prepare("INSERT INTO teachers (name, email, phone, employee_id) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $phone, $employee_id]);
                        logAudit('admin', $_SESSION['admin_id'], 'add_teacher', "Added teacher: $name");
                        $message = 'Teacher added successfully';
                    } catch (PDOException $e) {
                        $error = 'Error adding teacher: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name'] ?? '');
                $email = sanitize($_POST['email'] ?? '');
                $phone = sanitize($_POST['phone'] ?? '');
                $employee_id = sanitize($_POST['employee_id'] ?? '');
                $status = sanitize($_POST['status'] ?? 'active');
                
                if (empty($name)) {
                    $error = 'Teacher name is required';
                } else {
                    try {
                        $stmt = $db->prepare("UPDATE teachers SET name = ?, email = ?, phone = ?, employee_id = ?, status = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $phone, $employee_id, $status, $id]);
                        logAudit('admin', $_SESSION['admin_id'], 'edit_teacher', "Updated teacher ID: $id");
                        $message = 'Teacher updated successfully';
                    } catch (PDOException $e) {
                        $error = 'Error updating teacher: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $db->prepare("SELECT name FROM teachers WHERE id = ?");
                    $stmt->execute([$id]);
                    $teacher = $stmt->fetch();
                    
                    $stmt = $db->prepare("DELETE FROM teachers WHERE id = ?");
                    $stmt->execute([$id]);
                    logAudit('admin', $_SESSION['admin_id'], 'delete_teacher', "Deleted teacher: " . ($teacher['name'] ?? ''));
                    $message = 'Teacher deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Error deleting teacher: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all teachers
$stmt = $db->query("SELECT t.*, 
    (SELECT COUNT(*) FROM webauthn_credentials WHERE teacher_id = t.id) as has_biometric,
    (SELECT COUNT(*) FROM lesson_timetables WHERE teacher_id = t.id) as timetable_count
    FROM teachers t ORDER BY t.name");
$teachers = $stmt->fetchAll();

// Get teacher for editing
$editTeacher = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$id]);
    $editTeacher = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container">
        <h1>Teacher Management</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <div class="card">
            <h2><?php echo $editTeacher ? 'Edit Teacher' : 'Add New Teacher'; ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editTeacher ? 'edit' : 'add'; ?>">
                <?php if ($editTeacher): ?>
                    <input type="hidden" name="id" value="<?php echo $editTeacher['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo htmlspecialchars($editTeacher['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="employee_id">Employee ID</label>
                        <input type="text" id="employee_id" name="employee_id" 
                               value="<?php echo htmlspecialchars($editTeacher['employee_id'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($editTeacher['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($editTeacher['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <?php if ($editTeacher): ?>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo ($editTeacher['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editTeacher['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editTeacher ? 'Update Teacher' : 'Add Teacher'; ?>
                    </button>
                    <?php if ($editTeacher): ?>
                        <a href="teachers.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Teachers List -->
        <div class="card">
            <h2>All Teachers</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Employee ID</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Biometric</th>
                        <th>Timetable</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No teachers found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?php echo $teacher['id']; ?></td>
                                <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['employee_id'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['phone'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($teacher['has_biometric'] > 0): ?>
                                        <span class="badge badge-success">Registered</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Not Registered</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="timetables.php?teacher_id=<?php echo $teacher['id']; ?>">
                                        <?php echo $teacher['timetable_count']; ?> lessons
                                    </a>
                                </td>
                                <td><?php echo getStatusBadge($teacher['status']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <a href="biometric.php?teacher_id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-info">Register Biometric</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this teacher?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $teacher['id']; ?>">
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

