<?php
require_once '../../connect.php';
require_role(['Admin']);
$id=$_GET['id']??'';
try { $stmt=$connection->prepare('DELETE FROM Department WHERE DepartmentID=?'); $stmt->bind_param('s',$id); $stmt->execute(); set_flash('success','Department deleted.'); }
catch(mysqli_sql_exception $e){ set_flash('danger','Department cannot be deleted because inventory items are assigned to it.'); }
redirect('admin/departments/index.php');
?>
