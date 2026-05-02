<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Edit Department';
$active = 'departments';
$id=$_GET['id']??'';
$stmt=$connection->prepare('SELECT * FROM Department WHERE DepartmentID=?'); $stmt->bind_param('s',$id); $stmt->execute(); $dept=$stmt->get_result()->fetch_assoc();
if(!$dept){ set_flash('danger','Department not found.'); redirect('admin/departments/index.php'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $stmt=$connection->prepare('UPDATE Department SET DepartmentName=?, Location=? WHERE DepartmentID=?');
    $stmt->bind_param('sss', $_POST['department_name'], $_POST['location'], $id);
    try { $stmt->execute(); set_flash('success','Department updated.'); redirect('admin/departments/index.php'); } catch(mysqli_sql_exception $e){ set_flash('danger','Unable to update department.'); }
}
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout"><?php require ROOT_PATH . '/admin/sidebar.php'; ?><section class="content"><div class="page-head"><h1>Edit Department</h1></div><form class="panel" method="post"><div class="form-grid"><div><label>Department ID</label><input value="<?= h($dept['DepartmentID']) ?>" disabled></div><div><label>Department Name</label><input name="department_name" value="<?= h($dept['DepartmentName']) ?>" required></div><div class="form-full"><label>Location</label><input name="location" value="<?= h($dept['Location']) ?>" required></div></div><div class="form-actions"><a class="btn btn-outline" href="<?= url('admin/departments/index.php') ?>">Cancel</a><button class="btn btn-primary">Update Department</button></div></form></section></div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
