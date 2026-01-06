<?php
require_once '../config/config.php';

if (isAdminLoggedIn()) {
    logAudit('admin', $_SESSION['admin_id'], 'logout', 'Admin logged out');
}

session_destroy();
header('Location: login.php');
exit;

