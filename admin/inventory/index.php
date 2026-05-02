<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Inventory CRUD';
$active = 'inventory';
$items = $connection->query('SELECT i.*, d.DepartmentName FROM Inventory_item i LEFT JOIN Department d ON i.DepartmentID=d.DepartmentID ORDER BY d.DepartmentName, i.ItemName');
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div><h1>Inventory CRUD</h1><p>Manage inventory item records based on the ERD.</p></div>
            <a class="btn btn-gold" href="<?= url('admin/inventory/add.php') ?>">Add Item</a>
        </div>
        <div class="panel table-wrap">
            <table>
                <thead><tr><th>Asset Number</th><th>Item Name</th><th>Category</th><th>Item Type</th><th>Current Condition</th><th>Quantity Available</th><th>Department</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($items->num_rows === 0): ?>
                        <tr><td class="empty" colspan="8">No inventory items found.</td></tr>
                    <?php endif; ?>
                    <?php while($row=$items->fetch_assoc()): ?>
                        <?php $badge = $row['CurrentCondition']==='Damaged' ? 'badge-danger' : ($row['CurrentCondition']==='Worn' ? 'badge-warning' : 'badge-success'); ?>
                        <tr>
                            <td><?= h($row['AssetNumber']) ?></td>
                            <td><?= h($row['ItemName']) ?></td>
                            <td><?= h($row['Category']) ?></td>
                            <td><?= h($row['ItemType']) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= h($row['CurrentCondition']) ?></span></td>
                            <td><?= h((string)$row['QuantityAvailable']) ?></td>
                            <td><?= h($row['DepartmentName']) ?></td>
                            <td><div class="actions"><a class="btn btn-small btn-primary" href="<?= url('admin/inventory/edit.php?id='.urlencode($row['AssetNumber'])) ?>">Edit</a><a class="btn btn-small btn-danger" data-confirm="Delete this inventory item?" href="<?= url('admin/inventory/delete.php?id='.urlencode($row['AssetNumber'])) ?>">Delete</a></div></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
