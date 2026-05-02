<?php
require_once '../../connect.php';
require_role(['Admin']);
refresh_future_reservation_conflicts($connection);
$pageTitle = 'Settlement and Unblocking';
$active = 'liabilities';

$statusFilter = $_GET['status'] ?? 'pending';
$allowedStatus = ['pending', 'resolved', 'all'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'pending';
}

$studentWhere = '';
$instructorWhere = '';
if ($statusFilter === 'pending') {
    $studentWhere = "WHERE br.SettlementStatus='Pending'";
    $instructorWhere = "WHERE rbr.SettlementStatus='Pending'";
} elseif ($statusFilter === 'resolved') {
    $studentWhere = "WHERE br.SettlementStatus='Paid'";
    $instructorWhere = "WHERE rbr.SettlementStatus='Paid'";
}

$studentReports = $connection->query("SELECT br.ReportNumber, br.DateGenerated, br.PenaltyFeeAmount, br.DamageDescription, br.SettlementStatus,
        bt.TransactionNumber, bt.UserID AS BorrowerID,
        u.FirstName, u.LastName,
        i.ItemName, i.AssetNumber,
        COALESCE(s.HasLiability, 0) AS HasLiability
    FROM Breakage_report br
    JOIN Borrow_transaction bt ON br.TransactionNumber = bt.TransactionNumber
    JOIN `User` u ON bt.UserID = u.UserID
    JOIN Inventory_item i ON bt.AssetNumber = i.AssetNumber
    LEFT JOIN Student s ON bt.UserID = s.UserID
    {$studentWhere}
    ORDER BY FIELD(br.SettlementStatus, 'Pending', 'Paid'), br.DateGenerated DESC, br.ReportNumber DESC");

$instructorReports = $connection->query("SELECT rbr.ReportNumber, rbr.DateGenerated, rbr.QuantityMissing, rbr.QuantityDamaged, rbr.PenaltyFeeAmount, rbr.DamageDescription, rbr.SettlementStatus,
        rb.BatchID, rb.UserID AS InstructorID,
        u.FirstName, u.LastName,
        i.ItemName, i.AssetNumber
    FROM Reservation_breakage_report rbr
    JOIN Reservation_batch rb ON rbr.BatchID = rb.BatchID
    JOIN `User` u ON rb.UserID = u.UserID
    JOIN Inventory_item i ON rbr.AssetNumber = i.AssetNumber
    {$instructorWhere}
    ORDER BY FIELD(rbr.SettlementStatus, 'Pending', 'Paid'), rbr.DateGenerated DESC, rbr.ReportNumber DESC");

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Settlement and Unblocking</h1>
                <p>Mark breakage reports as resolved. Student accounts are unblocked when all student reports are resolved.</p>
            </div>
        </div>

        <div class="panel filter-bar">
            <form method="get" class="form-grid">
                <div>
                    <label for="status">Filter by Report Status</label>
                    <select id="status" name="status">
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                </div>
                <div class="form-actions" style="justify-content:flex-start;margin-top:20px;">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline" href="<?= url('admin/liabilities/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>

        <div class="panel table-wrap" style="margin-bottom:20px;">
            <h2>Student Breakage Reports</h2>
            <table>
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Borrower</th>
                        <th>Item</th>
                        <th>Date Generated</th>
                        <th>Penalty</th>
                        <th>Report Status</th>
                        <th>Account Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($studentReports->num_rows === 0): ?>
                    <tr><td class="empty" colspan="8">No student breakage reports found for the selected filter.</td></tr>
                <?php endif; ?>
                <?php while ($row = $studentReports->fetch_assoc()): ?>
                    <?php $reportStatus = display_settlement_status($row['SettlementStatus']); ?>
                    <tr>
                        <td>
                            <strong><?= h($row['ReportNumber']) ?></strong><br>
                            <span class="subtle">Transaction: <?= h($row['TransactionNumber']) ?></span>
                        </td>
                        <td>
                            <?= h($row['FirstName'] . ' ' . $row['LastName']) ?><br>
                            <span class="subtle"><?= h($row['BorrowerID']) ?></span>
                        </td>
                        <td>
                            <strong><?= h($row['ItemName']) ?></strong><br>
                            <span class="subtle"><?= h($row['AssetNumber']) ?></span>
                            <?php if (!empty($row['DamageDescription'])): ?>
                                <div class="subtle" style="margin-top:6px;"><?= h(preview_text($row['DamageDescription'], 70)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= h($row['DateGenerated']) ?></td>
                        <td>PHP <?= h(number_format((float)$row['PenaltyFeeAmount'], 2)) ?></td>
                        <td><span class="badge <?= $row['SettlementStatus'] === 'Paid' ? 'badge-success' : 'badge-warning' ?>"><?= h($reportStatus) ?></span></td>
                        <td><span class="badge <?= (int)$row['HasLiability'] === 1 ? 'badge-danger' : 'badge-success' ?>"><?= (int)$row['HasLiability'] === 1 ? 'Blocked' : 'Active' ?></span></td>
                        <td>
                            <?php if ($row['SettlementStatus'] === 'Pending'): ?>
                                <form method="post" action="<?= url('admin/liabilities/settle.php') ?>" data-confirm="Mark this student breakage report as resolved and process account unblocking if applicable?">
                                    <input type="hidden" name="report_number" value="<?= h($row['ReportNumber']) ?>">
                                    <button class="btn btn-small btn-primary" type="submit">Mark Resolved</button>
                                </form>
                            <?php else: ?>
                                <span class="subtle">Resolved</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="panel table-wrap">
            <h2>Instructor Reservation Breakage Reports</h2>
            <table>
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Instructor</th>
                        <th>Batch</th>
                        <th>Item</th>
                        <th>Date Generated</th>
                        <th>Penalty Basis</th>
                        <th>Penalty</th>
                        <th>Report Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($instructorReports->num_rows === 0): ?>
                    <tr><td class="empty" colspan="9">No instructor breakage reports found for the selected filter.</td></tr>
                <?php endif; ?>
                <?php while ($row = $instructorReports->fetch_assoc()): ?>
                    <?php $reportStatus = display_settlement_status($row['SettlementStatus']); ?>
                    <tr>
                        <td><strong><?= h($row['ReportNumber']) ?></strong></td>
                        <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?><br><span class="subtle"><?= h($row['InstructorID']) ?></span></td>
                        <td><?= h($row['BatchID']) ?></td>
                        <td>
                            <strong><?= h($row['ItemName']) ?></strong><br>
                            <span class="subtle"><?= h($row['AssetNumber']) ?></span>
                            <?php if (!empty($row['DamageDescription'])): ?>
                                <div class="subtle" style="margin-top:6px;"><?= h(preview_text($row['DamageDescription'], 70)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= h($row['DateGenerated']) ?></td>
                        <td>
                            <span class="badge <?= (int)$row['QuantityMissing'] > 0 ? 'badge-danger' : 'badge-muted' ?>">Missing: <?= h((string)$row['QuantityMissing']) ?></span><br>
                            <span class="badge <?= (int)$row['QuantityDamaged'] > 0 ? 'badge-danger' : 'badge-muted' ?>" style="margin-top:6px;">Damaged: <?= h((string)$row['QuantityDamaged']) ?></span>
                        </td>
                        <td>PHP <?= h(number_format((float)$row['PenaltyFeeAmount'], 2)) ?></td>
                        <td><span class="badge <?= $row['SettlementStatus'] === 'Paid' ? 'badge-success' : 'badge-warning' ?>"><?= h($reportStatus) ?></span></td>
                        <td>
                            <?php if ($row['SettlementStatus'] === 'Pending'): ?>
                                <form method="post" action="<?= url('admin/liabilities/reservation_settle.php') ?>" data-confirm="Mark this instructor breakage report as resolved?">
                                    <input type="hidden" name="report_number" value="<?= h($row['ReportNumber']) ?>">
                                    <button class="btn btn-small btn-primary" type="submit">Mark Resolved</button>
                                </form>
                            <?php else: ?>
                                <span class="subtle">Resolved</span>
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
