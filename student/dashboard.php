<?php
require_once '../connect.php';
require_role(['Student']);
$pageTitle = 'Student Dashboard';
$userId = $_SESSION['user_id'];

$stmt = $connection->prepare('SELECT u.*, s.Course, s.EnrollmentStatus, s.HasLiability FROM `User` u JOIN Student s ON u.UserID=s.UserID WHERE u.UserID=?');
$stmt->bind_param('s', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
if (!$profile) {
    session_unset();
    session_destroy();
    session_start();
    set_flash('warning', 'Your session was refreshed. Please login again.');
    redirect('login.php');
}

$status = trim($_GET['status'] ?? '');
$sort = $_GET['sort'] ?? 'borrowed';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$allowedStatuses = ['Active', 'Return Requested', 'Returned', 'Overdue', 'Late'];
$sortMap = [
    'borrowed' => 'bt.BorrowDateTime',
    'due' => 'bt.DueDateTime',
    'returned' => 'bt.ActualReturnDateTime',
];
$orderBy = $sortMap[$sort] ?? $sortMap['borrowed'];

$where = ['bt.UserID = ?'];
$params = [$userId];
$types = 's';
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where[] = 'bt.TransactionStatus = ?';
    $params[] = $status;
    $types .= 's';
}

$sql = 'SELECT bt.*, i.ItemName, i.ItemType, i.CurrentCondition, br.ReportNumber, br.SettlementStatus
        FROM Borrow_transaction bt
        JOIN Inventory_item i ON bt.AssetNumber=i.AssetNumber
        LEFT JOIN Breakage_report br ON bt.TransactionNumber=br.TransactionNumber
        WHERE ' . implode(' AND ', $where) . " ORDER BY {$orderBy} {$dir}";
$stmt = $connection->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result();

$canBorrow = ($profile['EnrollmentStatus'] === 'Officially Enrolled' && (int)$profile['HasLiability'] === 0);

function sort_link_student(string $key, string $label, string $currentSort, string $currentDir, string $status): string
{
    $nextDir = ($currentSort === $key && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
    $params = ['sort' => $key, 'dir' => $nextDir];
    if ($status !== '') { $params['status'] = $status; }
    $indicator = $currentSort === $key ? (strtoupper($currentDir) === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a href="' . url('student/dashboard.php?' . http_build_query($params)) . '">' . h($label . $indicator) . '</a>';
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <aside class="sidebar">
        <div class="profile-box">
            <div class="name"><?= h($profile['FirstName'].' '.$profile['LastName']) ?></div>
            <div class="role">Student</div>
        </div>
        <div class="panel" style="box-shadow:none;margin-bottom:16px;padding:16px;">
            <p style="margin:0 0 8px;color:var(--muted);font-size:13px;">User ID: <strong style="color:var(--ink);"><?= h($profile['UserID']) ?></strong></p>
            <p style="margin:0 0 8px;color:var(--muted);font-size:13px;">Course: <strong style="color:var(--ink);"><?= h($profile['Course']) ?></strong></p>
            <p style="margin:0 0 8px;color:var(--muted);font-size:13px;">Enrollment: <span class="badge <?= $profile['EnrollmentStatus'] === 'Officially Enrolled' ? 'badge-success' : 'badge-danger' ?>"><?= h($profile['EnrollmentStatus']) ?></span></p>
            <p style="margin:0;color:var(--muted);font-size:13px;">Liability: <span class="badge <?= (int)$profile['HasLiability'] === 0 ? 'badge-success' : 'badge-danger' ?>"><?= (int)$profile['HasLiability'] === 0 ? 'No' : 'Yes' ?></span></p>
        </div>
        <div class="side-links">
            <a class="active" href="<?= url('student/dashboard.php') ?>">My Borrow Transactions</a>
            <a href="<?= url('student/available_items.php') ?>">Available Items</a>
            <a href="<?= url('student/breakage_reports.php') ?>">Breakage Reports</a>
            <a href="<?= url('logout.php') ?>">Logout</a>
        </div>
    </aside>

    <section class="content">
        <div class="page-head">
            <div>
                <h1>My Borrow Transactions</h1>
                <p>View your borrowed items, due dates, return requests, and inspection status. Full damage comments are placed in Breakage Reports.</p>
            </div>
            <a class="btn <?= $canBorrow ? 'btn-primary' : 'btn-outline' ?>" href="<?= url('student/available_items.php') ?>">Borrow Available Item</a>
        </div>

        <?php if (!$canBorrow): ?>
            <div class="flash flash-warning" style="width:100%;margin:0 0 18px 0;">
                Borrowing is disabled because your account is either inactive or has liability.
            </div>
        <?php endif; ?>

        <div class="panel filter-bar">
            <form method="get" class="form-grid">
                <div>
                    <label for="status">Filter by Status</label>
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($allowedStatuses as $value): ?>
                            <option value="<?= h($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= h($value) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="sort">Sort Column</label>
                    <select id="sort" name="sort">
                        <option value="borrowed" <?= $sort === 'borrowed' ? 'selected' : '' ?>>Borrowed Date</option>
                        <option value="due" <?= $sort === 'due' ? 'selected' : '' ?>>Due Date</option>
                        <option value="returned" <?= $sort === 'returned' ? 'selected' : '' ?>>Return Date</option>
                    </select>
                </div>
                <div>
                    <label for="dir">Sort Direction</label>
                    <select id="dir" name="dir">
                        <option value="desc" <?= strtoupper($dir) === 'DESC' ? 'selected' : '' ?>>Newest / Highest First</option>
                        <option value="asc" <?= strtoupper($dir) === 'ASC' ? 'selected' : '' ?>>Oldest / Lowest First</option>
                    </select>
                </div>
                <div class="form-actions" style="justify-content:flex-start;margin-top:20px;">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline" href="<?= url('student/dashboard.php') ?>">Reset</a>
                </div>
            </form>
        </div>

        <div class="panel table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Item</th>
                        <th><?= sort_link_student('borrowed', 'Borrowed Date', $sort, $dir, $status) ?></th>
                        <th><?= sort_link_student('due', 'Due Date', $sort, $dir, $status) ?></th>
                        <th><?= sort_link_student('returned', 'Return Date', $sort, $dir, $status) ?></th>
                        <th>Status</th>
                        <th>Inspection Result</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows === 0): ?>
                        <tr><td class="empty" colspan="8">No borrow transactions found.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $transactions->fetch_assoc()): ?>
                        <?php
                            $badge = 'badge-warning';
                            if ($row['TransactionStatus'] === 'Returned') { $badge = 'badge-success'; }
                            if ($row['TransactionStatus'] === 'Return Requested') { $badge = 'badge-info'; }
                            if (in_array($row['TransactionStatus'], ['Overdue', 'Late'], true)) { $badge = 'badge-danger'; }

                            $inspectionBadge = 'badge-muted';
                            $inspectionText = 'No inspection yet';
                            if ($row['TransactionStatus'] === 'Return Requested') {
                                $inspectionText = 'Waiting for admin inspection';
                                $inspectionBadge = 'badge-info';
                            } elseif ($row['TransactionStatus'] === 'Returned') {
                                if ($row['ReturnCondition'] === 'Damaged') {
                                    $inspectionText = 'Damage Report Created';
                                    $inspectionBadge = 'badge-danger';
                                } elseif ($row['ReturnCondition'] === 'Worn') {
                                    $inspectionText = 'Returned with warning';
                                    $inspectionBadge = 'badge-warning';
                                } else {
                                    $inspectionText = 'Returned in good condition';
                                    $inspectionBadge = 'badge-success';
                                }
                            }
                        ?>
                        <tr>
                            <td><?= h($row['TransactionNumber']) ?></td>
                            <td>
                                <strong><?= h($row['ItemName']) ?></strong><br>
                                <span class="subtle"><?= h($row['AssetNumber']) ?> · <?= h($row['ItemType']) ?></span>
                            </td>
                            <td><?= h($row['BorrowDateTime']) ?></td>
                            <td><?= h($row['DueDateTime']) ?></td>
                            <td><?= h($row['ActualReturnDateTime'] ?: '-') ?></td>
                            <td><span class="badge <?= $badge ?>"><?= h($row['TransactionStatus']) ?></span></td>
                            <td>
                                <span class="badge <?= $inspectionBadge ?>"><?= h($inspectionText) ?></span>
                                <?php if ($row['ReturnCondition'] === 'Damaged' && !empty($row['ReportNumber'])): ?>
                                    <div style="margin-top:8px;"><a class="btn btn-small btn-outline" href="<?= url('student/breakage_reports.php?report=' . urlencode($row['ReportNumber'])) ?>">View Report</a></div>
                                <?php elseif ($row['ReturnCondition'] === 'Worn' && !empty($row['InspectorComment'])): ?>
                                    <div class="subtle" style="margin-top:8px;"><?= h(preview_text($row['InspectorComment'], 46)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (in_array($row['TransactionStatus'], ['Active', 'Overdue', 'Late'], true)): ?>
                                    <form method="post" action="<?= url('student/request_return.php') ?>" data-confirm="Request return for this item? Admin/Lab Staff will still inspect it before closing.">
                                        <input type="hidden" name="transaction_number" value="<?= h($row['TransactionNumber']) ?>">
                                        <button class="btn btn-small btn-outline" type="submit">Request Return</button>
                                    </form>
                                <?php elseif ($row['TransactionStatus'] === 'Return Requested'): ?>
                                    <span class="subtle">Waiting for admin inspection</span>
                                <?php else: ?>
                                    <span class="subtle">Closed</span>
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
