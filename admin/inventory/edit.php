<?php
require_once '../../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Admin']);
$pageTitle = 'Edit Inventory Item';
$active = 'inventory';
$id = $_GET['id'] ?? '';
$stmt = $connection->prepare('SELECT * FROM Inventory_item WHERE AssetNumber=?');
$stmt->bind_param('s', $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
if (!$item) { set_flash('danger', 'Inventory item not found.'); redirect('admin/inventory/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $connection->prepare('UPDATE Inventory_item SET ItemName=?, Category=?, ItemType=?, CurrentCondition=?, ReplacementCost=?, QuantityAvailable=?, DepartmentID=? WHERE AssetNumber=?');
    $stmt->bind_param('ssssdiss', $_POST['item_name'], $_POST['category'], $_POST['item_type'], $_POST['current_condition'], $_POST['replacement_cost'], $_POST['quantity_available'], $_POST['department_id'], $id);
    try {
        $stmt->execute();
        $flagged = refresh_future_reservation_conflicts($connection);
        $message = 'Inventory item updated.';
        if ($flagged > 0) {
            $message .= ' Future reservation availability was recalculated and ' . $flagged . ' batch(es) are currently At Risk.';
        }
        set_flash('success', $message);
        redirect('admin/inventory/index.php');
    } catch(mysqli_sql_exception $e) {
        set_flash('danger', 'Unable to update item.');
    }
}
$departments = $connection->query('SELECT DepartmentID, DepartmentName FROM Department ORDER BY DepartmentName');
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head"><div><h1>Edit Inventory Item</h1><p>Update item details, condition, quantity, and department.</p></div></div>
        <form class="panel" method="post">
            <div class="form-grid">
                <div><label>Asset Number</label><input value="<?= h($item['AssetNumber']) ?>" disabled></div>
                <div><label for="item_name">Item Name</label><input id="item_name" name="item_name" value="<?= h($item['ItemName']) ?>" required></div>
                <div><label for="category">Category</label><input id="category" name="category" value="<?= h($item['Category']) ?>" required></div>
                <div><label for="item_type">Item Type</label><select id="item_type" name="item_type"><?php foreach(['Consumable','Reusable','Returnable'] as $v): ?><option <?= $item['ItemType']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                <div><label for="current_condition">Current Condition</label><select id="current_condition" name="current_condition"><?php foreach(['Good','Worn','Damaged'] as $v): ?><option <?= $item['CurrentCondition']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                <div><label for="replacement_cost">Replacement Cost</label><input id="replacement_cost" name="replacement_cost" type="number" min="0" step="0.01" value="<?= h($item['ReplacementCost']) ?>" required></div>
                <div><label for="quantity_available">Quantity Available</label><input id="quantity_available" name="quantity_available" type="number" min="0" value="<?= h($item['QuantityAvailable']) ?>" required></div>
                <div><label for="department_id">Department</label><select id="department_id" name="department_id"><?php while($d=$departments->fetch_assoc()): ?><option value="<?= h($d['DepartmentID']) ?>" <?= $item['DepartmentID']===$d['DepartmentID']?'selected':'' ?>><?= h(department_code($d['DepartmentID']) . ' | ' . department_short_name($d['DepartmentID'])) ?></option><?php endwhile; ?></select></div>
            </div>
            <div class="form-actions"><a class="btn btn-outline" href="<?= url('admin/inventory/index.php') ?>">Cancel</a><button class="btn btn-primary">Update Item</button></div>
        </form>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
