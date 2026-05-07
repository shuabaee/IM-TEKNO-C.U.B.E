<?php $active = $active ?? ''; ?>
<aside class="sidebar">
    <?php $user = current_user(); ?>
    <div class="profile-box">
        <div class="name"><?= h(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? '')) ?></div>
        <div class="role">Admin / Laboratory Staff Portal</div>
    </div>
    <nav class="side-links">
        <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
        <a class="<?= $active === 'users' ? 'active' : '' ?>" href="<?= url('admin/users/index.php') ?>">Users</a>
        <a class="<?= $active === 'departments' ? 'active' : '' ?>" href="<?= url('admin/departments/index.php') ?>">Departments</a>
        <a class="<?= $active === 'inventory' ? 'active' : '' ?>" href="<?= url('admin/inventory/index.php') ?>">Inventory</a>
        <a class="<?= $active === 'returns' ? 'active' : '' ?>" href="<?= url('admin/returns/index.php') ?>">Student Returns</a>
        <a class="<?= $active === 'reservation_returns' ? 'active' : '' ?>" href="<?= url('admin/reservations/index.php') ?>">Instructor Returns</a>
        <a class="<?= $active === 'liabilities' ? 'active' : '' ?>" href="<?= url('admin/liabilities/index.php') ?>">Settlement and Unblocking</a>
        <a href="<?= url('logout.php') ?>">Logout</a>
    </nav>
</aside>
