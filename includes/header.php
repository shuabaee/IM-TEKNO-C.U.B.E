<?php
$user = current_user();
$flash = get_flash();
$pageTitle = $pageTitle ?? 'TEKNO C.U.B.E.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | TEKNO C.U.B.E.</title>
    <link rel="stylesheet" href="<?= url('css/site.css?v=' . time()) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= url('index.php') ?>">
        <img src="<?= url('images/TEKNO C.U.B.E 1.png') ?>" alt="TEKNO C.U.B.E. logo">
        <span>TEKNO C.U.B.E.</span>
    </a>
    <button class="nav-toggle" type="button" data-menu-toggle>Menu</button>
    <nav class="nav" data-menu>
        <a href="<?= url('index.php') ?>">Home</a>
        <a href="<?= url('index.php#rules') ?>">Rules</a>
        <?php if ($user): ?>
            <a href="<?= url('dashboard.php') ?>">Dashboard</a>
            <?php if (($user['UserType'] ?? '') === 'Admin'): ?>
                <a href="<?= url('admin/users/index.php') ?>">Users</a>
                <a href="<?= url('admin/departments/index.php') ?>">Departments</a>
                <a href="<?= url('admin/inventory/index.php') ?>">Inventory</a>
                <a href="<?= url('admin/returns/index.php') ?>">Student Returns</a>
                <a href="<?= url('admin/reservations/index.php') ?>">Instructor Returns</a>
                <a href="<?= url('admin/liabilities/index.php') ?>">Settlement and Unblocking</a>
            <?php elseif (($user['UserType'] ?? '') === 'Student'): ?>
                <a href="<?= url('student/available_items.php') ?>">Available Items</a>
                <a href="<?= url('student/breakage_reports.php') ?>">Breakage Reports</a>
            <?php elseif (($user['UserType'] ?? '') === 'Instructor'): ?>
                <a href="<?= url('instructor/reservation_add.php') ?>">Reserve Items</a>
                <a href="<?= url('instructor/breakage_reports.php') ?>">Breakage Reports</a>
            <?php endif; ?>
            <a class="btn btn-small btn-gold" href="<?= url('logout.php') ?>">Logout</a>
        <?php else: ?>
            <a href="<?= url('login.php') ?>">Login</a>
            <a class="btn btn-small btn-gold" href="<?= url('register.php') ?>">Register</a>
        <?php endif; ?>
    </nav>
</header>
<?php if ($flash): ?>
    <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>
<main>
