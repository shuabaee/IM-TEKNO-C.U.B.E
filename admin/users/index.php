<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'User CRUD';
$active = 'users';
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $connection->prepare('SELECT UserID, FirstName, LastName, UserType, Email, CreatedAt FROM `User` WHERE UserID LIKE ? OR FirstName LIKE ? OR LastName LIKE ? OR Email LIKE ? ORDER BY CreatedAt DESC');
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $connection->query('SELECT UserID, FirstName, LastName, UserType, Email, CreatedAt FROM `User` ORDER BY CreatedAt DESC');
}
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div><h1>User CRUD</h1><p>Create, read, update, and delete Admin, Student, and Instructor users.</p></div>
            <a class="btn btn-gold" href="<?= url('admin/users/add.php') ?>">+ Add User</a>
        </div>
        <div class="panel">
            <form method="get" class="form-actions" style="justify-content:flex-start;margin-top:0;margin-bottom:14px">
                <input name="q" value="<?= h($q) ?>" placeholder="Search User ID, name, or email" style="max-width:360px">
                <button class="btn btn-outline" type="submit">Search</button>
                <a class="btn btn-outline" href="<?= url('admin/users/index.php') ?>">Reset</a>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>User ID</th><th>Name</th><th>Role</th><th>Email</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($users->num_rows === 0): ?>
                        <tr><td class="empty" colspan="6">No user records found.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($row['UserID']) ?></td>
                            <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                            <td><span class="badge <?= $row['UserType'] === 'Admin' ? 'badge-danger' : 'badge-warning' ?>"><?= h($row['UserType']) ?></span></td>
                            <td><?= h($row['Email']) ?></td>
                            <td><?= h(date('M d, Y', strtotime($row['CreatedAt']))) ?></td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-small btn-outline" href="<?= url('admin/users/view.php?id=' . urlencode($row['UserID'])) ?>">View</a>
                                    <a class="btn btn-small btn-primary" href="<?= url('admin/users/edit.php?id=' . urlencode($row['UserID'])) ?>">Edit</a>
                                    <?php if ($row['UserID'] !== ($_SESSION['user_id'] ?? '')): ?>
                                        <a class="btn btn-small btn-danger" data-confirm="Delete this user?" href="<?= url('admin/users/delete.php?id=' . urlencode($row['UserID'])) ?>">Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
