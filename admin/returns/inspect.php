<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Inspect Return';
$active = 'returns';
$transactionNumber = $_GET['id'] ?? $_POST['transaction_number'] ?? '';

$stmt = $connection->prepare("SELECT bt.*, u.FirstName, u.LastName, u.UserID, i.ItemName, i.ItemType, i.CurrentCondition, i.ReplacementCost, i.AssetNumber
    FROM Borrow_transaction bt
    JOIN `User` u ON bt.UserID = u.UserID
    JOIN Inventory_item i ON bt.AssetNumber = i.AssetNumber
    WHERE bt.TransactionNumber = ? LIMIT 1");
$stmt->bind_param('s', $transactionNumber);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    set_flash('danger', 'Return request not found.');
    redirect('admin/returns/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $condition = $_POST['condition'] ?? 'Good';
    $inspectorComment = trim($_POST['inspector_comment'] ?? '');

    try {
        if (!in_array($condition, ['Good', 'Worn', 'Damaged'], true)) {
            throw new Exception('Please select a valid item condition.');
        }
        if (in_array($condition, ['Worn', 'Damaged'], true) && $inspectorComment === '') {
            throw new Exception('Please enter an inspector comment for worn or damaged items.');
        }

        $connection->begin_transaction();

        $stmt = $connection->prepare("SELECT bt.TransactionNumber, bt.UserID, bt.AssetNumber, bt.TransactionStatus, i.ReplacementCost
            FROM Borrow_transaction bt
            JOIN Inventory_item i ON bt.AssetNumber = i.AssetNumber
            WHERE bt.TransactionNumber=? FOR UPDATE");
        $stmt->bind_param('s', $transactionNumber);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        if (!$transaction || $transaction['TransactionStatus'] !== 'Return Requested') {
            throw new Exception('This return request is no longer pending.');
        }

        $actualReturnDateTime = date('Y-m-d H:i:s');
        $status = 'Returned';
        $returnCondition = $condition;
        $stmt = $connection->prepare('UPDATE Borrow_transaction SET ActualReturnDateTime=?, TransactionStatus=?, ReturnCondition=?, InspectorComment=? WHERE TransactionNumber=?');
        $stmt->bind_param('sssss', $actualReturnDateTime, $status, $returnCondition, $inspectorComment, $transactionNumber);
        $stmt->execute();

        $stmt = $connection->prepare('UPDATE Inventory_item SET CurrentCondition=? WHERE AssetNumber=?');
        $stmt->bind_param('ss', $condition, $transaction['AssetNumber']);
        $stmt->execute();

        if ($condition === 'Damaged') {
            $reportNumber = app_id('BRK');
            $dateGenerated = date('Y-m-d');
            $penalty = (float)$transaction['ReplacementCost'];
            $settlementStatus = 'Pending';
            $stmt = $connection->prepare('INSERT INTO Breakage_report (ReportNumber, DateGenerated, PenaltyFeeAmount, DamageDescription, SettlementStatus, TransactionNumber) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssdsss', $reportNumber, $dateGenerated, $penalty, $inspectorComment, $settlementStatus, $transactionNumber);
            $stmt->execute();

            $stmt = $connection->prepare('UPDATE Student SET HasLiability=1 WHERE UserID=?');
            $stmt->bind_param('s', $transaction['UserID']);
            $stmt->execute();
        } else {
            $stmt = $connection->prepare('UPDATE Inventory_item SET QuantityAvailable = QuantityAvailable + 1 WHERE AssetNumber=?');
            $stmt->bind_param('s', $transaction['AssetNumber']);
            $stmt->execute();
        }

        $connection->commit();
        $message = 'Return inspected and closed successfully.';
        if ($condition === 'Worn') {
            $message = 'Return inspected and closed. The borrower can now see the warning and inspector comment.';
        } elseif ($condition === 'Damaged') {
            $message = 'Return closed. Breakage report generated, borrower warning recorded, and liability was applied.';
        }
        set_flash('success', $message);
        redirect('admin/returns/index.php');
    } catch (Throwable $e) {
        try { $connection->rollback(); } catch (Throwable $ignored) {}
        set_flash('danger', $e->getMessage());
    }
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Inspect Returned Item</h1>
                <p>Admin/Lab Staff must inspect the item before closing the return. Comments entered here will be visible to the student.</p>
            </div>
            <a class="btn btn-outline" href="<?= url('admin/returns/index.php') ?>">Back to Student Returns</a>
        </div>

        <?php if ($record['TransactionStatus'] !== 'Return Requested'): ?>
            <div class="flash flash-warning" style="width:100%;margin:0 0 18px 0;">This transaction is not pending return inspection.</div>
        <?php endif; ?>

        <form class="panel" method="post">
            <input type="hidden" name="transaction_number" value="<?= h($record['TransactionNumber']) ?>">
            <div class="grid-2" style="margin-bottom:20px;">
                <div class="notice-card"><strong>Transaction</strong><br><?= h($record['TransactionNumber']) ?><br><span class="subtle">Borrowed: <?= h($record['BorrowDateTime']) ?></span></div>
                <div class="notice-card"><strong>Borrower</strong><br><?= h($record['FirstName'] . ' ' . $record['LastName']) ?><br><span class="subtle"><?= h($record['UserID']) ?></span></div>
                <div class="notice-card"><strong>Item</strong><br><?= h($record['ItemName']) ?><br><span class="subtle"><?= h($record['AssetNumber']) ?> · Replacement Cost: PHP <?= h(number_format((float)$record['ReplacementCost'], 2)) ?></span></div>
                <div class="notice-card"><strong>Current System Condition</strong><br><?= h($record['CurrentCondition']) ?></div>
            </div>

            <div class="form-grid">
                <div>
                    <label for="condition">Final Inspected Condition</label>
                    <select id="condition" name="condition">
                        <option value="Good">Good</option>
                        <option value="Worn">Worn</option>
                        <option value="Damaged">Damaged</option>
                    </select>
                </div>
                <div class="form-full">
                    <label for="inspector_comment">Inspector Comment</label>
                    <textarea id="inspector_comment" name="inspector_comment" placeholder="Example: Cable sheath was torn near the connector, or unit is functional but worn after inspection."></textarea>
                    <p class="subtle">Recommendation: enter a comment whenever the final condition is worn or damaged so the borrower can see what issue was found.</p>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn btn-outline" href="<?= url('admin/returns/index.php') ?>">Cancel</a>
                <button class="btn btn-primary" type="submit" <?= $record['TransactionStatus'] !== 'Return Requested' ? 'disabled' : '' ?>>Confirm Inspection</button>
            </div>
        </form>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
