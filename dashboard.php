<?php
require_once 'connect.php';
require_login();
$type = $_SESSION['user_type'] ?? '';
if ($type === 'Admin') {
    redirect('admin/dashboard.php');
}
if ($type === 'Instructor') {
    redirect('instructor/dashboard.php');
}
redirect('student/dashboard.php');
?>
