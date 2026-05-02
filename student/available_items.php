<?php
require_once '../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Student']);
$pageTitle = 'Available Items';
$userId = $_SESSION['user_id'];

$stmt = $connection->prepare('SELECT u.*, s.Course, s.EnrollmentStatus, s.HasLiability FROM `User` u JOIN Student s ON u.UserID=s.UserID WHERE u.UserID=?');
$stmt->bind_param('s', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$department = trim($_GET['department'] ?? '');
$qtySort = strtolower($_GET['qty_sort'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$departments = $connection->query('SELECT DepartmentID, DepartmentName FROM Department ORDER BY DepartmentName');
$where = ['i.QuantityAvailable > 0', "i.CurrentCondition <> 'Damaged'"];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(i.AssetNumber LIKE ? OR i.ItemName LIKE ? OR i.Category LIKE ? OR d.DepartmentName LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
if ($type !== '' && in_array($type, ['Returnable', 'Reusable', 'Consumable'], true)) {
    $where[] = 'i.ItemType = ?';
    $params[] = $type;
    $types .= 's';
}
if ($condition !== '' && in_array($condition, ['Good', 'Worn'], true)) {
    $where[] = 'i.CurrentCondition = ?';
    $params[] = $condition;
    $types .= 's';
}
if ($department !== '') {
    $where[] = 'i.DepartmentID = ?';
    $params[] = $department;
    $types .= 's';
}

$sql = 'SELECT i.*, d.DepartmentName FROM Inventory_item i JOIN Department d ON i.DepartmentID=d.DepartmentID WHERE ' . implode(' AND ', $where) . " ORDER BY i.QuantityAvailable {$qtySort}, i.ItemName ASC";
$stmt = $connection->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result();

$canBorrow = ($profile['EnrollmentStatus'] === 'Officially Enrolled' && (int)$profile['HasLiability'] === 0);
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <aside class="sidebar">
        <div class="profile-box">
            <div class="name"><?= h($profile['FirstName'].' '.$profile['LastName']) ?></div>
            <div class="role">Student</div>
        </div>
        <div class="side-links">
            <a href="<?= url('student/dashboard.php') ?>">My Borrow Transactions</a>
            <a class="active" href="<?= url('student/available_items.php') ?>">Available Items</a>
            <a href="<?= url('student/breakage_reports.php') ?>">Breakage Reports</a>
            <a href="<?= url('logout.php') ?>">Logout</a>
        </div>
    </aside>

    <section class="content">
        <div class="page-head">
            <div>
                <h1>Available Items</h1>
                <p>Search, filter, and borrow available laboratory items by type, condition, quantity, or department.</p>
            </div>
            <a class="btn btn-outline" href="<?= url('student/dashboard.php') ?>">Back to Dashboard</a>
        </div>

        <?php if (!$canBorrow): ?>
            <div class="flash flash-warning" style="width:100%;margin:0 0 18px 0;">
                You cannot borrow yet. Your account must be officially enrolled and must have no liability.
            </div>
        <?php endif; ?>

        <div class="panel filter-bar">
            <form method="get" class="form-grid">
                <div>
                    <label for="search">Search Item</label>
                    <input id="search" type="text" name="search" value="<?= h($search) ?>" placeholder="Oscilloscope, cable tester, microscope">
                </div>
                <div>
                    <label for="type">Filter by Type</label>
                    <select id="type" name="type">
                        <option value="">All Types</option>
                        <option value="Returnable" <?= $type === 'Returnable' ? 'selected' : '' ?>>Returnable</option>
                        <option value="Reusable" <?= $type === 'Reusable' ? 'selected' : '' ?>>Reusable</option>
                        <option value="Consumable" <?= $type === 'Consumable' ? 'selected' : '' ?>>Consumable</option>
                    </select>
                </div>
                <div>
                    <label for="condition">Filter by Condition</label>
                    <select id="condition" name="condition">
                        <option value="">All Borrowable Conditions</option>
                        <option value="Good" <?= $condition === 'Good' ? 'selected' : '' ?>>Good</option>
                        <option value="Worn" <?= $condition === 'Worn' ? 'selected' : '' ?>>Worn</option>
                    </select>
                </div>
                <div>
                    <label for="department">Filter by Department</label>
                    <select id="department" name="department">
                        <option value="">All Departments</option>
                        <?php while ($d = $departments->fetch_assoc()): ?>
                            <option value="<?= h($d['DepartmentID']) ?>" <?= $department === $d['DepartmentID'] ? 'selected' : '' ?>><?= h(department_code($d['DepartmentID']) . ' | ' . department_short_name($d['DepartmentID'])) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="qty_sort">Sort by Available Quantity</label>
                    <select id="qty_sort" name="qty_sort">
                        <option value="desc" <?= $qtySort === 'DESC' ? 'selected' : '' ?>>Highest Quantity First</option>
                        <option value="asc" <?= $qtySort === 'ASC' ? 'selected' : '' ?>>Lowest Quantity First</option>
                    </select>
                </div>
                <div class="form-actions" style="justify-content:flex-start;margin-top:20px;">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline" href="<?= url('student/available_items.php') ?>">Reset</a>
                </div>
            </form>
        </div>

        <div class="panel table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Asset Number</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Condition</th>
                        <th>Available Quantity</th>
                        <th>Department</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items->num_rows === 0): ?>
                        <tr><td class="empty" colspan="7">No available items found.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($row['AssetNumber']) ?></td>
                            <td>
                                <strong><?= h($row['ItemName']) ?></strong><br>
                                <span class="subtle"><?= h($row['Category']) ?></span>
                            </td>
                            <td><span class="badge badge-muted"><?= h($row['ItemType']) ?></span></td>
                            <td><span class="badge <?= $row['CurrentCondition'] === 'Good' ? 'badge-success' : 'badge-warning' ?>"><?= h($row['CurrentCondition']) ?></span></td>
                            <td><?= h((string)$row['QuantityAvailable']) ?></td>
                            <td><?= h(department_code($row['DepartmentID']) . ' | ' . department_short_name($row['DepartmentID'])) ?></td>
                            <td>
                                <?php if ($canBorrow): ?>
                                    <form method="post" action="<?= url('student/borrow.php') ?>" onsubmit="return confirm('Borrow <?= h($row['ItemName']) ?>?');">
                                        <input type="hidden" name="asset_number" value="<?= h($row['AssetNumber']) ?>">
                                        <button class="btn btn-small btn-gold" type="submit">Borrow</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-small btn-outline" type="button" disabled>Not Allowed</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
