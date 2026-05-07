<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'User CRUD';
$active = 'users';

$sort = trim($_GET['sort'] ?? '') ?: 'created';
$dir = strtolower(trim($_GET['dir'] ?? '')) === 'asc' ? 'ASC' : 'DESC';
$sortMap = [
    'name' => 'FirstName, LastName',
    'role' => 'UserType',
    'created' => 'CreatedAt'
];
$orderBy = $sortMap[$sort] ?? $sortMap['created'];

$users = $connection->query("SELECT UserID, FirstName, LastName, UserType, Email, CreatedAt FROM `User` ORDER BY {$orderBy} {$dir}");

function sort_link_users(string $key, string $label, string $currentSort, string $currentDir): string {
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
            <div><h1>User Management</h1><p>Create, update, review, and remove user accounts for all system roles.</p></div>
            <a class="btn btn-gold" href="<?= url('admin/users/add.php') ?>">+ Add User</a>
        </div>
        <div class="panel">
            <form class="form-actions" data-live-search style="justify-content:flex-start;margin-top:0;margin-bottom:14px">
                <input name="q" placeholder="Search User ID, name, or email" style="max-width:500px; width: 100%;">
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>User ID</th>
                        <th><?= sort_link_users('name', 'Name', $sort, $dir) ?></th>
                        <th><?= sort_link_users('role', 'Role', $sort, $dir) ?></th>
                        <th>Email</th>
                        <th><?= sort_link_users('created', 'Created', $sort, $dir) ?></th>
                        <th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php if ($users->num_rows === 0): ?>
                        <tr class="empty" data-live-search-empty><td class="empty" colspan="6">No user records found.</td></tr>
                    <?php else: ?>
                        <tr class="empty" data-live-search-empty style="display:none;"><td class="empty" colspan="6">No matching user records found.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($row['UserID']) ?></td>
                            <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                            <td><span class="badge <?= $row['UserType'] === 'Admin' ? 'badge-danger' : ($row['UserType'] === 'Student' ? 'badge-info' : 'badge-warning') ?>"><?= h($row['UserType']) ?></span></td>
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
