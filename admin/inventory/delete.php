<?php
require_once '../../connect.php';
require_role(['Admin']);
$id=$_GET['id']??'';
try { $stmt=$connection->prepare('DELETE FROM Inventory_item WHERE AssetNumber=?'); $stmt->bind_param('s',$id); $stmt->execute(); set_flash('success','Inventory item deleted.'); }
catch(mysqli_sql_exception $e){ set_flash('danger','Item cannot be deleted because it is used in transactions or reservations.'); }
redirect('admin/inventory/index.php');
?>
