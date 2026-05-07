<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Student Returns';
$active = 'returns';

$statusFilter = $_GET['status'] ?? 'Return Requested';
$allowed = ['Active', 'Return Requested', 'Returned', 'Overdue', 'Late', 'All'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'Return Requested';
}

$sort = trim($_GET['sort'] ?? '') ?: 'due';
$dir = strtolower(trim($_GET['dir'] ?? '')) === 'desc' ? 'DESC' : 'ASC';
$sortMap = [
    'borrowed' => 'bt.BorrowDateTime',
    'due' => 'bt.DueDateTime'
];
$orderBy = $sortMap[$sort] ?? $sortMap['due'];

$where = '';
if ($statusFilter !== 'All') {
    $where = "WHERE bt.TransactionStatus = '" . $connection->real_escape_string($statusFilter) . "'";
}

$requests = $connection->query("SELECT bt.TransactionNumber, bt.BorrowDateTime, bt.DueDateTime, bt.TransactionStatus, u.UserID, u.FirstName, u.LastName, i.AssetNumber, i.ItemName, i.ItemType, i.CurrentCondition, i.ReplacementCost
    FROM Borrow_transaction bt
    JOIN `User` u ON bt.UserID = u.UserID
    JOIN Inventory_item i ON bt.AssetNumber = i.AssetNumber
    {$where}
    ORDER BY {$orderBy} {$dir}, FIELD(bt.TransactionStatus, 'Return Requested', 'Active', 'Overdue', 'Late', 'Returned')");

function sort_link_student_returns(string $key, string $label, string $currentSort, string $currentDir, string $statusFilter): string {
    $nextDir = ($currentSort === $key && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
    $indicator = $currentSort === $key ? (strtoupper($currentDir) === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a href="?status=' . urlencode($statusFilter) . '&sort=' . urlencode($key) . '&dir=' . urlencode($nextDir) . '">' . h($label . $indicator) . '</a>';
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Student Returns</h1>
                <p>Inspect returned items and close transactions. Damaged items automatically generate a breakage report.</p>
            </div>
        </div>
        <div class="panel filter-bar">
            <form method="get" class="form-grid">
                <div>
                    <label for="status">Filter by Transaction Status</label>
                    <select id="status" name="status" onchange="this.form.submit()">
                        <?php foreach ($allowed as $status): ?>
                            <option value="<?= h($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="panel table-wrap">
            <form class="form-actions" data-live-search style="justify-content:flex-start;margin-top:0;margin-bottom:14px;padding: 16px 16px 0;">
                <input name="q" placeholder="Search transaction number, borrower, or item" style="max-width:500px; width: 100%;">
            </form>
            <table>
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Borrower</th>
                        <th>Item</th>
                        <th><?= sort_link_student_returns('borrowed', 'Borrowed Date', $sort, $dir, $statusFilter) ?></th>
                        <th><?= sort_link_student_returns('due', 'Due Date', $sort, $dir, $statusFilter) ?></th>
                        <th>Current Condition</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests->num_rows === 0): ?>
                        <tr><td class="empty" colspan="7">No pending return requests.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $requests->fetch_assoc()): ?>
                        <?php
                            $badge = 'badge-muted';
                            if ($row['TransactionStatus'] === 'Return Requested') $badge = 'badge-info';
                            elseif ($row['TransactionStatus'] === 'Active') $badge = 'badge-primary';
                            elseif ($row['TransactionStatus'] === 'Overdue' || $row['TransactionStatus'] === 'Late') $badge = 'badge-danger';
                            elseif ($row['TransactionStatus'] === 'Returned') $badge = 'badge-success';
                        ?>
                        <tr>
                            <td><?= h($row['TransactionNumber']) ?></td>
                            <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?><br><span class="subtle"><?= h($row['UserID']) ?></span></td>
                            <td><strong><?= h($row['ItemName']) ?></strong><br><span class="subtle"><?= h($row['AssetNumber']) ?> · <?= h($row['ItemType']) ?></span></td>
                            <td><?= h($row['BorrowDateTime']) ?></td>
                            <td><?= h($row['DueDateTime']) ?></td>
                            <td>
                                <span class="badge <?= $badge ?>"><?= h($row['TransactionStatus']) ?></span><br>
                                <span class="badge <?= $row['CurrentCondition'] === 'Good' ? 'badge-success' : ($row['CurrentCondition'] === 'Damaged' ? 'badge-danger' : 'badge-warning') ?>" style="margin-top:4px"><?= h($row['CurrentCondition']) ?> condition</span>
                            </td>
                            <td>
                                <?php if ($row['TransactionStatus'] === 'Return Requested'): ?>
                                    <a class="btn btn-small btn-primary" href="<?= url('admin/returns/inspect.php?id=' . urlencode($row['TransactionNumber'])) ?>">Inspect Return</a>
                                <?php else: ?>
                                    <a class="btn btn-small btn-outline" href="<?= url('admin/returns/inspect.php?id=' . urlencode($row['TransactionNumber'])) ?>">View Transaction</a>
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
