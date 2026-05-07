<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Department CRUD';
$active = 'departments';

$sort = trim($_GET['sort'] ?? '') ?: 'name';
$dir = strtolower(trim($_GET['dir'] ?? '')) === 'desc' ? 'DESC' : 'ASC';
$sortMap = [
    'name' => 'DepartmentName',
    'location' => 'Location'
];
$orderBy = $sortMap[$sort] ?? $sortMap['name'];

$departments = $connection->query("SELECT * FROM Department ORDER BY {$orderBy} {$dir}");

function sort_link_depts(string $key, string $label, string $currentSort, string $currentDir): string {
    $nextDir = ($currentSort === $key && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
    $indicator = $currentSort === $key ? (strtoupper($currentDir) === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a href="?sort=' . urlencode($key) . '&dir=' . urlencode($nextDir) . '">' . h($label . $indicator) . '</a>';
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head"><div><h1>Department Management</h1><p>Manage academic departments, locations, and the options available to users across the system.</p></div><a class="btn btn-gold" href="<?= url('admin/departments/add.php') ?>">+ Add Department</a></div>
        <div class="panel">
            <form class="form-actions" data-live-search style="justify-content:flex-start;margin-top:0;margin-bottom:14px">
                <input name="q" placeholder="Search department ID, name, or location" style="max-width:500px; width: 100%;">
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>ID</th>
                        <th><?= sort_link_depts('name', 'Name', $sort, $dir) ?></th>
                        <th><?= sort_link_depts('location', 'Location', $sort, $dir) ?></th>
                        <th>Actions</th>
                    </tr></thead>
                    <tbody>
                <?php if ($departments->num_rows === 0): ?>
                    <tr class="empty" data-live-search-empty><td class="empty" colspan="4">No department records found.</td></tr>
                <?php else: ?>
                    <tr class="empty" data-live-search-empty style="display:none;"><td class="empty" colspan="4">No matching department records found.</td></tr>
                <?php endif; ?>
                <?php while($row=$departments->fetch_assoc()): ?><tr><td><?= h($row['DepartmentID']) ?></td><td><?= h($row['DepartmentName']) ?></td><td><?= h($row['Location']) ?></td><td><div class="actions"><a class="btn btn-small btn-primary" href="<?= url('admin/departments/edit.php?id='.urlencode($row['DepartmentID'])) ?>">Edit</a><a class="btn btn-small btn-danger" data-confirm="Delete this department?" href="<?= url('admin/departments/delete.php?id='.urlencode($row['DepartmentID'])) ?>">Delete</a></div></td></tr><?php endwhile; ?>
            </tbody></table></div>
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
