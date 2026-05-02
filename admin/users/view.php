<?php
require_once '../../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Admin']);
$pageTitle = 'View User';
$active = 'users';
$userId = $_GET['id'] ?? '';
$stmt = $connection->prepare('SELECT * FROM `User` WHERE UserID = ? LIMIT 1');
$stmt->bind_param('s', $userId);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
if (!$record) { set_flash('danger', 'User not found.'); redirect('admin/users/index.php'); }
$extra = null;
if ($record['UserType'] === 'Student') {
    $stmt = $connection->prepare('SELECT * FROM Student WHERE UserID=?'); $stmt->bind_param('s', $userId); $stmt->execute(); $extra = $stmt->get_result()->fetch_assoc();
} elseif ($record['UserType'] === 'Instructor') {
    $stmt = $connection->prepare('SELECT * FROM Instructor WHERE UserID=?'); $stmt->bind_param('s', $userId); $stmt->execute(); $extra = $stmt->get_result()->fetch_assoc();
}
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head"><div><h1>User Details</h1><p><?= h($record['UserID']) ?></p></div><a class="btn btn-primary" href="<?= url('admin/users/edit.php?id=' . urlencode($record['UserID'])) ?>">Edit</a></div>
        <div class="panel">
            <div class="form-grid">
                <div><label>User ID</label><p><?= h($record['UserID']) ?></p></div>
                <div><label>Role</label><p><span class="badge badge-warning"><?= h($record['UserType']) ?></span></p></div>
                <div><label>Name</label><p><?= h($record['FirstName'] . ' ' . $record['LastName']) ?></p></div>
                <div><label>Email</label><p><?= h($record['Email']) ?></p></div>
                <?php if ($record['UserType'] === 'Student' && $extra): ?>
                    <div><label>Course</label><p><?= h($extra['Course']) ?></p></div>
                    <div><label>Enrollment Status</label><p><?= h($extra['EnrollmentStatus']) ?></p></div>
                    <div><label>Has Liability</label><p><?= $extra['HasLiability'] ? 'Yes' : 'No' ?></p></div>
                <?php elseif ($record['UserType'] === 'Instructor' && $extra): ?>
                    <div><label>Department</label><p><?= h(department_name($extra['Department'])) ?></p></div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
