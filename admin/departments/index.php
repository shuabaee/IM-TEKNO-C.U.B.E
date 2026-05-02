<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Department CRUD';
$active = 'departments';
$departments = $connection->query('SELECT * FROM Department ORDER BY DepartmentName');
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head"><div><h1>Department CRUD</h1><p>Manage Department table from the ERD.</p></div><a class="btn btn-gold" href="<?= url('admin/departments/add.php') ?>">+ Add Department</a></div>
        <div class="panel table-wrap"><table><thead><tr><th>ID</th><th>Name</th><th>Location</th><th>Actions</th></tr></thead><tbody>
            <?php while($row=$departments->fetch_assoc()): ?><tr><td><?= h($row['DepartmentID']) ?></td><td><?= h($row['DepartmentName']) ?></td><td><?= h($row['Location']) ?></td><td><div class="actions"><a class="btn btn-small btn-primary" href="<?= url('admin/departments/edit.php?id='.urlencode($row['DepartmentID'])) ?>">Edit</a><a class="btn btn-small btn-danger" data-confirm="Delete this department?" href="<?= url('admin/departments/delete.php?id='.urlencode($row['DepartmentID'])) ?>">Delete</a></div></td></tr><?php endwhile; ?>
        </tbody></table></div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
