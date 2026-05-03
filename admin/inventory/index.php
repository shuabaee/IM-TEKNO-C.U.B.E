<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Inventory CRUD';
$active = 'inventory';

$sort = trim($_GET['sort'] ?? '') ?: 'name';
$dir = strtolower(trim($_GET['dir'] ?? '')) === 'desc' ? 'DESC' : 'ASC';
$sortMap = [
    'name' => 'i.ItemName',
    'type' => 'i.ItemType',
    'condition' => 'i.CurrentCondition',
    'qty' => 'i.QuantityAvailable'
];
$orderBy = $sortMap[$sort] ?? $sortMap['name'];

$items = $connection->query("SELECT i.*, d.DepartmentName FROM Inventory_item i LEFT JOIN Department d ON i.DepartmentID=d.DepartmentID ORDER BY {$orderBy} {$dir}, i.AssetNumber ASC");

function sort_link_inventory(string $key, string $label, string $currentSort, string $currentDir): string {
    $nextDir = ($currentSort === $key && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
    $indicator = $currentSort === $key ? (strtoupper($currentDir) === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a href="?sort=' . urlencode($key) . '&dir=' . urlencode($nextDir) . '">' . h($label . $indicator) . '</a>';
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div><h1>Inventory Management</h1><p>Create, update, and maintain inventory item records, including their condition, quantity, and department placement.</p></div>
            <a class="btn btn-gold" href="<?= url('admin/inventory/add.php') ?>">Add Item</a>
        </div>
        <div class="panel">
            <form class="form-actions" data-live-search style="justify-content:flex-start;margin-top:0;margin-bottom:14px">
                <input name="q" placeholder="Search asset number, item name, category, or department" style="max-width:500px; width: 100%;">
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Asset No.</th>
                        <th><?= sort_link_inventory('name', 'Item Name', $sort, $dir) ?></th>
                        <th>Category</th>
                        <th><?= sort_link_inventory('type', 'Type', $sort, $dir) ?></th>
                        <th><?= sort_link_inventory('condition', 'Condition', $sort, $dir) ?></th>
                        <th><?= sort_link_inventory('qty', 'Quantity Available', $sort, $dir) ?></th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php if ($items->num_rows === 0): ?>
                            <tr class="empty" data-live-search-empty><td class="empty" colspan="8">No inventory items found.</td></tr>
                        <?php else: ?>
                            <tr class="empty" data-live-search-empty style="display:none;"><td class="empty" colspan="8">No matching inventory items found.</td></tr>
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
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
