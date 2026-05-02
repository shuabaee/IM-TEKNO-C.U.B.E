<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Inspect Instructor Batch';
$active = 'reservation_returns';
$batchId = $_GET['id'] ?? $_POST['batch_id'] ?? '';

$stmt = $connection->prepare("SELECT rb.*, u.UserID, u.FirstName, u.LastName
    FROM Reservation_batch rb
    JOIN `User` u ON rb.UserID = u.UserID
    WHERE rb.BatchID = ? LIMIT 1");
$stmt->bind_param('s', $batchId);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();

if (!$batch) {
    set_flash('danger', 'Reservation batch not found.');
    redirect('admin/reservations/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $batch['ReservationStatus'] === 'Return Requested') {
    $returnedQty = $_POST['returned_qty'] ?? [];
    $damagedQty = $_POST['damaged_qty'] ?? [];
    $conditions = $_POST['condition'] ?? [];
    $comments = $_POST['inspector_comment'] ?? [];

    try {
        $connection->begin_transaction();

        $stmt = $connection->prepare('SELECT ReservationStatus FROM Reservation_batch WHERE BatchID=? FOR UPDATE');
        $stmt->bind_param('s', $batchId);
        $stmt->execute();
        $statusRow = $stmt->get_result()->fetch_assoc();
        if (!$statusRow || $statusRow['ReservationStatus'] !== 'Return Requested') {
            throw new Exception('This batch is no longer pending return inspection.');
        }

        $itemsQuery = $connection->prepare('SELECT ri.BatchID, ri.AssetNumber, ri.QuantityReserved, i.ItemName, i.ReplacementCost FROM Reserved_item ri JOIN Inventory_item i ON ri.AssetNumber=i.AssetNumber WHERE ri.BatchID=?');
        $itemsQuery->bind_param('s', $batchId);
        $itemsQuery->execute();
        $items = $itemsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$items) {
            throw new Exception('No reserved items were found for this batch.');
        }

        $updateReserved = $connection->prepare('UPDATE Reserved_item SET QuantityReturned=?, QuantityMissing=?, QuantityDamaged=?, ReturnCondition=?, InspectorComment=? WHERE BatchID=? AND AssetNumber=?');
        $insertReservationReport = $connection->prepare('INSERT INTO Reservation_breakage_report (ReportNumber, DateGenerated, QuantityMissing, QuantityDamaged, PenaltyFeeAmount, DamageDescription, SettlementStatus, BatchID, AssetNumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE DateGenerated=VALUES(DateGenerated), QuantityMissing=VALUES(QuantityMissing), QuantityDamaged=VALUES(QuantityDamaged), PenaltyFeeAmount=VALUES(PenaltyFeeAmount), DamageDescription=VALUES(DamageDescription), SettlementStatus=VALUES(SettlementStatus)');

        foreach ($items as $item) {
            $asset = $item['AssetNumber'];
            $reserved = (int)$item['QuantityReserved'];
            $qty = isset($returnedQty[$asset]) ? (int)$returnedQty[$asset] : 0;
            $damaged = isset($damagedQty[$asset]) ? (int)$damagedQty[$asset] : 0;
            $condition = $conditions[$asset] ?? 'Good';
            $comment = trim($comments[$asset] ?? '');

            if ($qty < 0 || $qty > $reserved) {
                throw new Exception('Returned quantity for ' . $item['ItemName'] . ' must be between 0 and ' . $reserved . '.');
            }
            if ($damaged < 0 || $damaged > $qty) {
                throw new Exception('Damaged quantity for ' . $item['ItemName'] . ' must be between 0 and the returned quantity.');
            }
            if (!in_array($condition, ['Good', 'Worn', 'Damaged'], true)) {
                throw new Exception('Please select a valid condition for ' . $item['ItemName'] . '.');
            }

            $missing = $reserved - $qty;
            $penaltyUnits = $missing + $damaged;
            if ($penaltyUnits > 0) {
                $condition = 'Damaged';
            }
            if ($penaltyUnits > 0 && $comment === '') {
                throw new Exception('Please enter an inspector comment for ' . $item['ItemName'] . ' because there are missing or damaged units.');
            }
            if ($condition === 'Worn' && $comment === '') {
                throw new Exception('Please enter an inspector comment for ' . $item['ItemName'] . ' when the condition is worn.');
            }
            if ($condition === 'Damaged' && $penaltyUnits === 0) {
                $damaged = max(1, min($qty, 1));
                $penaltyUnits = $damaged;
                if ($comment === '') {
                    throw new Exception('Please enter an inspector comment for ' . $item['ItemName'] . ' when the condition is damaged.');
                }
            }

            $updateReserved->bind_param('iiissss', $qty, $missing, $damaged, $condition, $comment, $batchId, $asset);
            $updateReserved->execute();

            if ($penaltyUnits > 0) {
                $reportNumber = app_id('RBR');
                $dateGenerated = date('Y-m-d');
                $penalty = $penaltyUnits * (float)$item['ReplacementCost'];
                $settlementStatus = 'Pending';
                $autoDescription = 'Reserved: ' . $reserved . '. Returned: ' . $qty . '. Missing: ' . $missing . '. Damaged: ' . $damaged . '. Penalty units: ' . $penaltyUnits . '.';
                $damageDescription = $autoDescription . "\n\nInspector comment: " . $comment;

                $insertReservationReport->bind_param('ssiidssss', $reportNumber, $dateGenerated, $missing, $damaged, $penalty, $damageDescription, $settlementStatus, $batchId, $asset);
                $insertReservationReport->execute();
            }
        }

        $returnedAt = date('Y-m-d H:i:s');
        $returnedStatus = 'Returned';
        $updateBatch = $connection->prepare('UPDATE Reservation_batch SET ReservationStatus=?, ActualReturnDateTime=? WHERE BatchID=?');
        $updateBatch->bind_param('sss', $returnedStatus, $returnedAt, $batchId);
        $updateBatch->execute();

        $flaggedFutureReservations = refresh_future_reservation_conflicts($connection);

        $connection->commit();
        $message = 'Instructor batch inspected successfully. Missing or damaged quantities were recorded as scalable penalties. Total stock is kept unchanged, while unresolved penalty units reduce usable stock for future reservation checks.';
        if ($flaggedFutureReservations > 0) {
            $message .= ' ' . $flaggedFutureReservations . ' future reservation batch(es) were flagged as At Risk because current stock may no longer cover them.';
        }
        set_flash('success', $message);
        redirect('admin/reservations/index.php');
    } catch (Throwable $e) {
        try { $connection->rollback(); } catch (Throwable $ignored) {}
        set_flash('danger', $e->getMessage());
        redirect('admin/reservations/inspect.php?id=' . urlencode($batchId));
    }
}

$stmt = $connection->prepare("SELECT ri.*, i.ItemName, i.Category, i.ItemType, i.ReplacementCost
    FROM Reserved_item ri
    JOIN Inventory_item i ON ri.AssetNumber = i.AssetNumber
    WHERE ri.BatchID = ?
    ORDER BY i.ItemName ASC");
$stmt->bind_param('s', $batchId);
$stmt->execute();
$items = $stmt->get_result();

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Inspect Instructor Return Batch</h1>
                <p>Confirm returned, missing, and damaged quantities. Penalty is computed as missing or damaged units multiplied by replacement cost.</p>
            </div>
            <a class="btn btn-outline" href="<?= url('admin/reservations/index.php') ?>">Back to Instructor Returns</a>
        </div>

        <div class="grid-2" style="margin-bottom:20px;">
            <div class="notice-card"><strong>Batch</strong><br><?= h($batch['BatchID']) ?><br><span class="subtle">Status: <?= h($batch['ReservationStatus']) ?></span></div>
            <div class="notice-card"><strong>Instructor</strong><br><?= h($batch['FirstName'] . ' ' . $batch['LastName']) ?><br><span class="subtle"><?= h($batch['UserID']) ?></span></div>
            <div class="notice-card"><strong>Schedule</strong><br><?= h($batch['ScheduleDate']) ?><br><span class="subtle"><?= h(substr($batch['StartTime'], 0, 5) . ' - ' . substr($batch['EndTime'], 0, 5)) ?></span></div>
            <div class="notice-card"><strong>Purpose</strong><br><?= h($batch['Purpose']) ?></div>
        </div>

        <form class="panel stack" method="post">
            <input type="hidden" name="batch_id" value="<?= h($batch['BatchID']) ?>">
            <div class="notice-card">
                Example: if 10 microscopes were reserved at PHP 28,000 each and only 8 are returned, the missing quantity is 2 and the penalty becomes PHP 56,000. 
                If one of the returned units is also damaged, add that damaged quantity so the penalty scales correctly. After inspection, the system will also recheck future reservations and flag any batch that can no longer be supported by the remaining stock.
            </div>

            <?php while ($item = $items->fetch_assoc()): ?>
                <?php
                    $conditionBadge = 'badge-muted';
                    if ($item['ReturnCondition'] === 'Good') $conditionBadge = 'badge-success';
                    elseif ($item['ReturnCondition'] === 'Worn') $conditionBadge = 'badge-warning';
                    elseif ($item['ReturnCondition'] === 'Damaged') $conditionBadge = 'badge-danger';
                ?>
                <div class="item-select-card">
                    <header>
                        <div>
                            <strong><?= h($item['ItemName']) ?></strong><br>
                            <small><?= h($item['AssetNumber']) ?> · <?= h($item['Category']) ?> · <?= h($item['ItemType']) ?> · Replacement Cost: PHP <?= h(number_format((float)$item['ReplacementCost'], 2)) ?></small>
                        </div>
                        <span class="badge <?= $conditionBadge ?>"><?= h($item['ReturnCondition']) ?></span>
                    </header>
                    <div class="grid-2">
                        <div>
                            <label>Quantity Reserved</label>
                            <input type="number" value="<?= h((string)$item['QuantityReserved']) ?>" disabled>
                        </div>
                        <div>
                            <label for="qty_<?= h($item['AssetNumber']) ?>">Quantity Returned</label>
                            <input id="qty_<?= h($item['AssetNumber']) ?>" type="number" name="returned_qty[<?= h($item['AssetNumber']) ?>]" min="0" max="<?= h((string)$item['QuantityReserved']) ?>" value="<?= h((string)$item['QuantityReturned']) ?>" <?= $batch['ReservationStatus'] !== 'Return Requested' ? 'disabled' : '' ?>>
                        </div>
                        <div>
                            <label for="damaged_<?= h($item['AssetNumber']) ?>">Damaged Quantity</label>
                            <input id="damaged_<?= h($item['AssetNumber']) ?>" type="number" name="damaged_qty[<?= h($item['AssetNumber']) ?>]" min="0" max="<?= h((string)$item['QuantityReserved']) ?>" value="<?= h((string)($item['QuantityDamaged'] ?? 0)) ?>" <?= $batch['ReservationStatus'] !== 'Return Requested' ? 'disabled' : '' ?>>
                            <div class="help-text">This must not exceed the returned quantity.</div>
                        </div>
                        <div>
                            <label for="condition_<?= h($item['AssetNumber']) ?>">Final Condition</label>
                            <select id="condition_<?= h($item['AssetNumber']) ?>" name="condition[<?= h($item['AssetNumber']) ?>]" <?= $batch['ReservationStatus'] !== 'Return Requested' ? 'disabled' : '' ?>>
                                <?php foreach (['Good', 'Worn', 'Damaged'] as $option): ?>
                                    <option value="<?= h($option) ?>" <?= $item['ReturnCondition'] === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-full">
                            <label for="comment_<?= h($item['AssetNumber']) ?>">Inspector Comment</label>
                            <textarea id="comment_<?= h($item['AssetNumber']) ?>" name="inspector_comment[<?= h($item['AssetNumber']) ?>]" placeholder="Explain missing quantity, damaged units, or worn condition found during inspection." <?= $batch['ReservationStatus'] !== 'Return Requested' ? 'disabled' : '' ?>><?= h($item['InspectorComment'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            <div class="form-actions">
                <a class="btn btn-outline" href="<?= url('admin/reservations/index.php') ?>">Back</a>
                <?php if ($batch['ReservationStatus'] === 'Return Requested'): ?>
                    <button class="btn btn-primary" type="submit">Confirm Batch Inspection</button>
                <?php else: ?>
                    <span class="subtle">This batch is already closed or not yet requested for return.</span>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
