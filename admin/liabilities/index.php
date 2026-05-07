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

$sortS = trim($_GET['sort_s'] ?? '') ?: 'date';
$dirS = strtolower(trim($_GET['dir_s'] ?? '')) === 'asc' ? 'ASC' : 'DESC';
$sortMapS = [
    'date' => 'br.DateGenerated',
    'status' => "FIELD(br.SettlementStatus, 'Pending', 'Paid')"
];
$orderByS = $sortMapS[$sortS] ?? $sortMapS['date'];

$sortI = trim($_GET['sort_i'] ?? '') ?: 'date';
$dirI = strtolower(trim($_GET['dir_i'] ?? '')) === 'asc' ? 'ASC' : 'DESC';
$sortMapI = [
    'date' => 'rbr.DateGenerated',
    'status' => "FIELD(rbr.SettlementStatus, 'Pending', 'Paid')"
];
$orderByI = $sortMapI[$sortI] ?? $sortMapI['date'];

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
    ORDER BY {$orderByS} {$dirS}, br.ReportNumber DESC");

$instructorReports = $connection->query("SELECT rbr.ReportNumber, rbr.DateGenerated, rbr.QuantityMissing, rbr.QuantityDamaged, rbr.PenaltyFeeAmount, rbr.DamageDescription, rbr.SettlementStatus,
        rb.BatchID, rb.UserID AS InstructorID,
        u.FirstName, u.LastName,
        i.ItemName, i.AssetNumber
    FROM Reservation_breakage_report rbr
    JOIN Reservation_batch rb ON rbr.BatchID = rb.BatchID
    JOIN `User` u ON rb.UserID = u.UserID
    JOIN Inventory_item i ON rbr.AssetNumber = i.AssetNumber
    {$instructorWhere}
    ORDER BY {$orderByI} {$dirI}, rbr.ReportNumber DESC");

function sort_link_liab(string $key, string $label, string $currentSort, string $currentDir, string $statusFilter, string $type, string $otherSort, string $otherDir): string {
    $nextDir = ($currentSort === $key && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
    $indicator = $currentSort === $key ? (strtoupper($currentDir) === 'ASC' ? ' ↑' : ' ↓') : '';
    $params = ['status' => $statusFilter];
    if ($type === 'student') {
        $params['sort_s'] = $key;
        $params['dir_s'] = $nextDir;
        $params['sort_i'] = $otherSort;
        $params['dir_i'] = $otherDir;
    } else {
        $params['sort_i'] = $key;
        $params['dir_i'] = $nextDir;
        $params['sort_s'] = $otherSort;
        $params['dir_s'] = $otherDir;
    }
    return '<a href="?'.http_build_query($params).'">' . h($label . $indicator) . '</a>';
}

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
                    <select id="status" name="status" onchange="this.form.submit()">
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
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
                        <th><?= sort_link_liab('date', 'Date Generated', $sortS, $dirS, $statusFilter, 'student', $sortI, $dirI) ?></th>
                        <th>Penalty</th>
                        <th><?= sort_link_liab('status', 'Report Status', $sortS, $dirS, $statusFilter, 'student', $sortI, $dirI) ?></th>
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
                        <th><?= sort_link_liab('date', 'Date Generated', $sortI, $dirI, $statusFilter, 'instructor', $sortS, $dirS) ?></th>
                        <th>Penalty Basis</th>
                        <th>Penalty</th>
                        <th><?= sort_link_liab('status', 'Report Status', $sortI, $dirI, $statusFilter, 'instructor', $sortS, $dirS) ?></th>
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
