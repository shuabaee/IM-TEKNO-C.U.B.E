<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Add Department';
$active = 'departments';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $stmt=$connection->prepare('INSERT INTO Department (DepartmentID, DepartmentName, Location) VALUES (?,?,?)');
    $stmt->bind_param('sss', $_POST['department_id'], $_POST['department_name'], $_POST['location']);
    try { $stmt->execute(); set_flash('success','Department added.'); redirect('admin/departments/index.php'); } catch(mysqli_sql_exception $e){ set_flash('danger','Unable to add department. Check duplicate ID/name.'); }
}
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout"><?php require ROOT_PATH . '/admin/sidebar.php'; ?><section class="content"><div class="page-head"><h1>Add Department</h1></div><form class="panel" method="post"><div class="form-grid"><div><label>Department ID</label><input name="department_id" required placeholder="DEPT-CCS"></div><div><label>Department Name</label><input name="department_name" required></div><div class="form-full"><label>Location</label><input name="location" required></div></div><div class="form-actions"><a class="btn btn-outline" href="<?= url('admin/departments/index.php') ?>">Cancel</a><button class="btn btn-primary">Save Department</button></div></form></section></div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
