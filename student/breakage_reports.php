<?php
require_once '../connect.php';
require_role(['Student']);
$pageTitle = 'Breakage Reports';
$userId = $_SESSION['user_id'];
$selectedReport = trim($_GET['report'] ?? '');

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

$where = 'bt.UserID = ?';
$params = [$userId];
$types = 's';
if ($selectedReport !== '') {
    $where .= ' AND br.ReportNumber = ?';
    $params[] = $selectedReport;
    $types .= 's';
}

$sql = "SELECT br.*, bt.TransactionNumber, bt.BorrowDateTime, bt.ActualReturnDateTime, i.AssetNumber, i.ItemName, i.Category
    FROM Breakage_report br
    JOIN Borrow_transaction bt ON br.TransactionNumber = bt.TransactionNumber
    JOIN Inventory_item i ON bt.AssetNumber = i.AssetNumber
    WHERE {$where}
    ORDER BY FIELD(br.SettlementStatus, 'Pending', 'Paid'), br.DateGenerated DESC, br.ReportNumber DESC";
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
            <div class="role">Student</div>
        </div>
        <div class="panel" style="box-shadow:none;margin-bottom:16px;padding:16px;">
            <p style="margin:0 0 8px;color:var(--muted);font-size:13px;">User ID: <strong style="color:var(--ink);"><?= h($profile['UserID']) ?></strong></p>
            <p style="margin:0;color:var(--muted);font-size:13px;">Liability: <span class="badge <?= (int)$profile['HasLiability'] === 0 ? 'badge-success' : 'badge-danger' ?>"><?= (int)$profile['HasLiability'] === 0 ? 'No' : 'Yes' ?></span></p>
        </div>
        <div class="side-links">
            <a href="<?= url('student/dashboard.php') ?>">My Borrow Transactions</a>
            <a href="<?= url('student/available_items.php') ?>">Available Items</a>
            <a class="active" href="<?= url('student/breakage_reports.php') ?>">Breakage Reports</a>
            <a href="<?= url('logout.php') ?>">Logout</a>
        </div>
    </aside>

    <section class="content">
        <div class="page-head">
            <div>
                <h1>Breakage Reports</h1>
                <p>View full inspector comments and settlement status for items marked as damaged after return inspection.</p>
            </div>
            <a class="btn btn-outline" href="<?= url('student/dashboard.php') ?>">Back to Transactions</a>
        </div>

        <div class="panel table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Item</th>
                        <th>Date Generated</th>
                        <th>Penalty</th>
                        <th>Status</th>
                        <th>Inspector Comment</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($reports->num_rows === 0): ?>
                    <tr><td class="empty" colspan="6">No breakage reports found.</td></tr>
                <?php endif; ?>
                <?php while ($row = $reports->fetch_assoc()): ?>
                    <?php
                        $statusText = display_settlement_status($row['SettlementStatus']);
                        $statusBadge = $row['SettlementStatus'] === 'Paid' ? 'badge-success' : 'badge-warning';
                    ?>
                    <tr>
                        <td><strong><?= h($row['ReportNumber']) ?></strong><br><span class="subtle">Transaction: <?= h($row['TransactionNumber']) ?></span></td>
                        <td><strong><?= h($row['ItemName']) ?></strong><br><span class="subtle"><?= h($row['AssetNumber']) ?> · <?= h($row['Category']) ?></span></td>
                        <td><?= h($row['DateGenerated']) ?></td>
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
