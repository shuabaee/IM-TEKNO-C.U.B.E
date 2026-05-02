<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Return Requests';
$active = 'returns';

$requests = $connection->query("SELECT bt.TransactionNumber, bt.BorrowDateTime, bt.DueDateTime, bt.TransactionStatus, u.UserID, u.FirstName, u.LastName, i.AssetNumber, i.ItemName, i.ItemType, i.CurrentCondition, i.ReplacementCost
    FROM Borrow_transaction bt
    JOIN `User` u ON bt.UserID = u.UserID
    JOIN Inventory_item i ON bt.AssetNumber = i.AssetNumber
    WHERE bt.TransactionStatus = 'Return Requested'
    ORDER BY bt.DueDateTime ASC, bt.BorrowDateTime ASC");

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Return Requests</h1>
                <p>Inspect returned items and close transactions. Damaged items automatically generate a breakage report.</p>
            </div>
        </div>
        <div class="panel table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Borrower</th>
                        <th>Item</th>
                        <th>Borrowed Date</th>
                        <th>Due Date</th>
                        <th>Current Condition</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests->num_rows === 0): ?>
                        <tr><td class="empty" colspan="7">No pending return requests.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $requests->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($row['TransactionNumber']) ?></td>
                            <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?><br><span class="subtle"><?= h($row['UserID']) ?></span></td>
                            <td><strong><?= h($row['ItemName']) ?></strong><br><span class="subtle"><?= h($row['AssetNumber']) ?> · <?= h($row['ItemType']) ?></span></td>
                            <td><?= h($row['BorrowDateTime']) ?></td>
                            <td><?= h($row['DueDateTime']) ?></td>
                            <td><span class="badge <?= $row['CurrentCondition'] === 'Good' ? 'badge-success' : ($row['CurrentCondition'] === 'Damaged' ? 'badge-danger' : 'badge-warning') ?>"><?= h($row['CurrentCondition']) ?></span></td>
                            <td><a class="btn btn-small btn-primary" href="<?= url('admin/returns/inspect.php?id=' . urlencode($row['TransactionNumber'])) ?>">Inspect Return</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
