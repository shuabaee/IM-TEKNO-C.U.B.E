<?php
require_once '../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Instructor']);
$pageTitle = 'Breakage Reports';
$userId = $_SESSION['user_id'];
$selectedBatch = trim($_GET['batch'] ?? '');

$stmt = $connection->prepare('SELECT u.*, i.Department FROM `User` u JOIN Instructor i ON u.UserID=i.UserID WHERE u.UserID=?');
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

$where = 'rb.UserID = ?';
$params = [$userId];
$types = 's';
if ($selectedBatch !== '') {
    $where .= ' AND rb.BatchID = ?';
    $params[] = $selectedBatch;
    $types .= 's';
}

$sql = "SELECT rbr.*, rb.BatchID, rb.ScheduleDate, rb.StartTime, rb.EndTime, rb.Purpose,
        i.AssetNumber, i.ItemName, i.Category, ri.QuantityReserved, ri.QuantityReturned, ri.QuantityMissing, ri.QuantityDamaged
    FROM Reservation_breakage_report rbr
    JOIN Reservation_batch rb ON rbr.BatchID = rb.BatchID
    JOIN Reserved_item ri ON rbr.BatchID = ri.BatchID AND rbr.AssetNumber = ri.AssetNumber
    JOIN Inventory_item i ON rbr.AssetNumber = i.AssetNumber
    WHERE {$where}
    ORDER BY FIELD(rbr.SettlementStatus, 'Pending', 'Paid'), rbr.DateGenerated DESC, rbr.ReportNumber DESC";
$stmt = $connection->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$reports = $stmt->get_result();

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <aside class="sidebar">
        <div class="profile-box">
            <div class="name"><?= h($profile['FirstName'].' '.$profile['LastName']) ?></div>
            <div class="role">Instructor · <?= h(department_code($profile['Department'])) ?></div>
        </div>
        <div class="side-links">
            <a href="<?= url('instructor/dashboard.php') ?>">Reservation Batches</a>
            <a href="<?= url('instructor/reservation_add.php') ?>">Create Reservation</a>
            <a class="active" href="<?= url('instructor/breakage_reports.php') ?>">Breakage Reports</a>
            <a href="<?= url('logout.php') ?>">Logout</a>
        </div>
    </aside>

    <section class="content">
        <div class="page-head">
            <div>
                <h1>Breakage Reports</h1>
                <p>View full inspector comments and settlement status for reservation items marked as damaged after batch return inspection.</p>
            </div>
            <a class="btn btn-outline" href="<?= url('instructor/dashboard.php') ?>">Back to Reservations</a>
        </div>

        <div class="panel table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Batch</th>
                        <th>Item</th>
                        <th>Date Generated</th>
                        <th>Penalty Basis</th>
                        <th>Penalty</th>
                        <th>Status</th>
                        <th>Inspector Comment</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($reports->num_rows === 0): ?>
                    <tr><td class="empty" colspan="8">No breakage reports found.</td></tr>
                <?php endif; ?>
                <?php while ($row = $reports->fetch_assoc()): ?>
                    <?php
                        $statusText = display_settlement_status($row['SettlementStatus']);
                        $statusBadge = $row['SettlementStatus'] === 'Paid' ? 'badge-success' : 'badge-warning';
                    ?>
                    <tr>
                        <td><strong><?= h($row['ReportNumber']) ?></strong></td>
                        <td>
                            <strong><?= h($row['BatchID']) ?></strong><br>
                            <span class="subtle"><?= h($row['ScheduleDate']) ?> · <?= h(substr($row['StartTime'], 0, 5).' - '.substr($row['EndTime'], 0, 5)) ?></span><br>
                            <span class="subtle"><?= h($row['Purpose']) ?></span>
                        </td>
                        <td>
                            <strong><?= h($row['ItemName']) ?></strong><br>
                            <span class="subtle"><?= h($row['AssetNumber']) ?> · <?= h($row['Category']) ?></span><br>
                            <span class="subtle">Reserved: <?= h((string)$row['QuantityReserved']) ?> · Returned: <?= h((string)$row['QuantityReturned']) ?></span>
                        </td>
                        <td><?= h($row['DateGenerated']) ?></td>
                        <td>
                            <span class="badge <?= (int)$row['QuantityMissing'] > 0 ? 'badge-danger' : 'badge-muted' ?>">Missing: <?= h((string)$row['QuantityMissing']) ?></span><br>
                            <span class="badge <?= (int)$row['QuantityDamaged'] > 0 ? 'badge-danger' : 'badge-muted' ?>" style="margin-top:6px;">Damaged: <?= h((string)$row['QuantityDamaged']) ?></span>
                        </td>
                        <td>PHP <?= h(number_format((float)$row['PenaltyFeeAmount'], 2)) ?></td>
                        <td><span class="badge <?= $statusBadge ?>"><?= h($statusText) ?></span></td>
                        <td style="min-width:280px;white-space:normal;"><?= nl2br(h($row['DamageDescription'] ?: 'No inspector comment recorded.')) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
