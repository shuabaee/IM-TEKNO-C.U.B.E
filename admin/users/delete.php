<?php
require_once '../../connect.php';
require_role(['Admin']);
$userId = $_GET['id'] ?? '';
if ($userId === ($_SESSION['user_id'] ?? '')) {
    set_flash('danger', 'You cannot delete your own logged-in admin account.');
    redirect('admin/users/index.php');
}
try {
    $stmt = $connection->prepare('DELETE FROM `User` WHERE UserID = ?');
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    set_flash('success', 'User deleted successfully.');
} catch (mysqli_sql_exception $e) {
    set_flash('danger', 'User cannot be deleted because related records still exist.');
}
redirect('admin/users/index.php');
?>
