<?php
require_once '../../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Admin']);
$pageTitle = 'Add Inventory Item';
$active = 'inventory';
$departments = $connection->query('SELECT DepartmentID, DepartmentName FROM Department ORDER BY DepartmentName');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $connection->prepare('INSERT INTO Inventory_item (AssetNumber, ItemName, Category, ItemType, CurrentCondition, ReplacementCost, QuantityAvailable, DepartmentID) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->bind_param('sssssdis', $_POST['asset_number'], $_POST['item_name'], $_POST['category'], $_POST['item_type'], $_POST['current_condition'], $_POST['replacement_cost'], $_POST['quantity_available'], $_POST['department_id']);
    try {
        $stmt->execute();
        $flagged = refresh_future_reservation_conflicts($connection);
        $message = 'Inventory item added. Future reservation availability was recalculated.';
        if ($flagged > 0) {
            $message .= ' ' . $flagged . ' batch(es) are currently At Risk.';
        }
        set_flash('success', $message);
        redirect('admin/inventory/index.php');
    } catch(mysqli_sql_exception $e) {
        set_flash('danger', 'Unable to add item. Check duplicate Asset Number.');
    }
}
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head"><div><h1>Add Inventory Item</h1><p>Create a new laboratory item record.</p></div></div>
        <form class="panel" method="post">
            <div class="form-grid">
                <div><label for="asset_number">Asset Number</label><input id="asset_number" name="asset_number" required placeholder="CCS-IT-004"></div>
                <div><label for="item_name">Item Name</label><input id="item_name" name="item_name" required></div>
                <div><label for="category">Category</label><input id="category" name="category" value="Electronics" required></div>
                <div><label for="item_type">Item Type</label><select id="item_type" name="item_type"><option>Consumable</option><option>Reusable</option><option>Returnable</option></select></div>
                <div><label for="current_condition">Current Condition</label><select id="current_condition" name="current_condition"><option>Good</option><option>Worn</option><option>Damaged</option></select></div>
                <div><label for="replacement_cost">Replacement Cost</label><input id="replacement_cost" name="replacement_cost" type="number" min="0" step="0.01" value="0.00" required></div>
                <div><label for="quantity_available">Quantity Available</label><input id="quantity_available" name="quantity_available" type="number" min="0" value="1" required></div>
                <div><label for="department_id">Department</label><select id="department_id" name="department_id"><?php while($d=$departments->fetch_assoc()): ?><option value="<?= h($d['DepartmentID']) ?>"><?= h(department_code($d['DepartmentID']) . ' | ' . department_short_name($d['DepartmentID'])) ?></option><?php endwhile; ?></select></div>
            </div>
            <div class="form-actions"><a class="btn btn-outline" href="<?= url('admin/inventory/index.php') ?>">Cancel</a><button class="btn btn-primary">Save Item</button></div>
        </form>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
