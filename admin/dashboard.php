<?php
require_once '../connect.php';
require_role(['Admin']);
refresh_future_reservation_conflicts($connection);
$pageTitle = 'Admin Dashboard';
$active = 'dashboard';

$counts = [];
foreach ([
    'users' => 'SELECT COUNT(*) AS total FROM `User`',
    'students' => 'SELECT COUNT(*) AS total FROM Student',
    'items' => 'SELECT COUNT(*) AS total FROM Inventory_item',
    'student_returns' => "SELECT COUNT(*) AS total FROM Borrow_transaction WHERE TransactionStatus='Return Requested'",
    'instructor_returns' => "SELECT COUNT(*) AS total FROM Reservation_batch WHERE ReservationStatus='Return Requested'",
    'at_risk_reservations' => "SELECT COUNT(*) AS total FROM Reservation_batch WHERE ConflictStatus='At Risk' AND ReservationStatus IN ('Reserved','Return Requested')",
    'liabilities' => "SELECT (SELECT COUNT(*) FROM Breakage_report WHERE SettlementStatus='Pending') + (SELECT COUNT(*) FROM Reservation_breakage_report WHERE SettlementStatus='Pending') AS total",
] as $key => $sql) {
    $counts[$key] = (int)$connection->query($sql)->fetch_assoc()['total'];
}
$latest = $connection->query('SELECT UserID, FirstName, LastName, UserType, Email FROM `User` ORDER BY CreatedAt DESC LIMIT 5');
$returnRequests = $connection->query("SELECT bt.TransactionNumber, bt.DueDateTime, u.FirstName, u.LastName, i.ItemName
    FROM Borrow_transaction bt
    JOIN `User` u ON bt.UserID=u.UserID
    JOIN Inventory_item i ON bt.AssetNumber=i.AssetNumber
    WHERE bt.TransactionStatus='Return Requested'
    ORDER BY bt.DueDateTime ASC LIMIT 5");
$instructorRequests = $connection->query("SELECT rb.BatchID, rb.ScheduleDate, rb.StartTime, rb.EndTime, u.FirstName, u.LastName
    FROM Reservation_batch rb
    JOIN `User` u ON rb.UserID=u.UserID
    WHERE rb.ReservationStatus='Return Requested'
    ORDER BY rb.ScheduleDate ASC, rb.StartTime ASC LIMIT 5");
$liabilityPreview = $connection->query("SELECT * FROM (
        SELECT br.ReportNumber, br.DateGenerated, br.PenaltyFeeAmount, u.FirstName, u.LastName, i.ItemName, 'Student' AS ReportType
        FROM Breakage_report br
        JOIN Borrow_transaction bt ON br.TransactionNumber=bt.TransactionNumber
        JOIN `User` u ON bt.UserID=u.UserID
        JOIN Inventory_item i ON bt.AssetNumber=i.AssetNumber
        WHERE br.SettlementStatus='Pending'
        UNION ALL
        SELECT rbr.ReportNumber, rbr.DateGenerated, rbr.PenaltyFeeAmount, u.FirstName, u.LastName, i.ItemName, 'Instructor' AS ReportType
        FROM Reservation_breakage_report rbr
        JOIN Reservation_batch rb ON rbr.BatchID=rb.BatchID
        JOIN `User` u ON rb.UserID=u.UserID
        JOIN Inventory_item i ON rbr.AssetNumber=i.AssetNumber
        WHERE rbr.SettlementStatus='Pending'
    ) pending_reports
    ORDER BY DateGenerated DESC, ReportNumber DESC
    LIMIT 5");
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Admin Dashboard</h1>
                <p>Manage users, inventory, student and instructor return inspections, settlements, and liability clearing from one portal.</p>
            </div>
            <a class="btn btn-gold" href="<?= url('admin/users/add.php') ?>">Add User</a>
        </div>
        <div class="kpi-grid">
            <div class="kpi"><span>Total Users</span><strong><?= $counts['users'] ?></strong></div>
            <div class="kpi"><span>Students</span><strong><?= $counts['students'] ?></strong></div>
            <div class="kpi"><span>Inventory Items</span><strong><?= $counts['items'] ?></strong></div>
            <div class="kpi"><span>Student Returns</span><strong><?= $counts['student_returns'] ?></strong></div>
            <div class="kpi"><span>Instructor Returns</span><strong><?= $counts['instructor_returns'] ?></strong></div>
            <div class="kpi"><span>At Risk Reservations</span><strong><?= $counts['at_risk_reservations'] ?></strong></div>
            <div class="kpi"><span>Pending Settlements</span><strong><?= $counts['liabilities'] ?></strong></div>
        </div>
        <div class="grid-2">
            <div class="panel">
                <h2>Recent Users</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>User ID</th><th>Name</th><th>Role</th><th>Email</th></tr></thead>
                        <tbody>
                        <?php while ($row = $latest->fetch_assoc()): ?>
                            <tr>
                                <td><?= h($row['UserID']) ?></td>
                                <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                                <td><span class="badge <?= $row['UserType'] === 'Admin' ? 'badge-danger' : 'badge-warning' ?>"><?= h($row['UserType']) ?></span></td>
                                <td><?= h($row['Email']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel">
                <div class="page-head" style="margin-bottom:14px;">
                    <div>
                        <h2 style="margin:0;">Student Return Inspections</h2>
                        <p>Borrowed items that students already requested to return.</p>
                    </div>
                    <a class="btn btn-outline" href="<?= url('admin/returns/index.php') ?>">Open</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Transaction</th><th>Borrower</th><th>Item</th><th>Due Date</th></tr></thead>
                        <tbody>
                        <?php if ($returnRequests->num_rows === 0): ?>
                            <tr><td class="empty" colspan="4">No pending student return inspections.</td></tr>
                        <?php endif; ?>
                        <?php while ($row = $returnRequests->fetch_assoc()): ?>
                            <tr>
                                <td><a href="<?= url('admin/returns/inspect.php?id=' . urlencode($row['TransactionNumber'])) ?>"><?= h($row['TransactionNumber']) ?></a></td>
                                <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                                <td><?= h($row['ItemName']) ?></td>
                                <td><?= h($row['DueDateTime']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="grid-2" style="margin-top:18px;">
            <div class="panel">
                <div class="page-head" style="margin-bottom:14px;">
                    <div>
                        <h2 style="margin:0;">Instructor Return Requests</h2>
                        <p>Reservation batches waiting for actual return confirmation.</p>
                    </div>
                    <a class="btn btn-outline" href="<?= url('admin/reservations/index.php') ?>">Open</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Batch</th><th>Instructor</th><th>Schedule</th><th>Time</th></tr></thead>
                        <tbody>
                        <?php if ($instructorRequests->num_rows === 0): ?>
                            <tr><td class="empty" colspan="4">No pending instructor return requests.</td></tr>
                        <?php endif; ?>
                        <?php while ($row = $instructorRequests->fetch_assoc()): ?>
                            <tr>
                                <td><a href="<?= url('admin/reservations/inspect.php?id=' . urlencode($row['BatchID'])) ?>"><?= h($row['BatchID']) ?></a></td>
                                <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                                <td><?= h($row['ScheduleDate']) ?></td>
                                <td><?= h(substr($row['StartTime'],0,5) . ' - ' . substr($row['EndTime'],0,5)) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel">
                <div class="page-head" style="margin-bottom:14px;">
                    <div>
                        <h2 style="margin:0;">Pending Settlements</h2>
                        <p>Student and instructor breakage reports are shown here. Student accounts are unblocked when all student reports are resolved.</p>
                    </div>
                    <a class="btn btn-outline" href="<?= url('admin/liabilities/index.php') ?>">Open Settlement Page</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Report</th><th>Borrower</th><th>Item</th><th>Penalty</th></tr></thead>
                        <tbody>
                        <?php if ($liabilityPreview->num_rows === 0): ?>
                            <tr><td class="empty" colspan="4">No pending settlements.</td></tr>
                        <?php endif; ?>
                        <?php while ($row = $liabilityPreview->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= h($row['ReportNumber']) ?></strong><br>
                                    <span class="subtle"><?= h($row['ReportType']) ?> Report</span>
                                </td>
                                <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                                <td><?= h($row['ItemName']) ?></td>
                                <td>PHP <?= h(number_format((float)$row['PenaltyFeeAmount'], 2)) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
