<?php
require_once '../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Instructor']);
$pageTitle = 'Instructor Dashboard';
$userId = $_SESSION['user_id'];

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

refresh_future_reservation_conflicts($connection);

$sort = $_GET['sort'] ?? 'date';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$sortMap = [
    'date' => 'rb.ScheduleDate',
    'time' => 'rb.StartTime',
    'status' => 'rb.ReservationStatus',
];
$orderBy = $sortMap[$sort] ?? $sortMap['date'];

$stmt = $connection->prepare("SELECT rb.*, 
        GROUP_CONCAT(CONCAT(ri.QuantityReserved, ' x ', ii.ItemName) ORDER BY ii.ItemName SEPARATOR ', ') AS ReservedItems,
        GROUP_CONCAT(
            CASE 
                WHEN ri.ReturnCondition IN ('Worn','Damaged') THEN CONCAT(ii.ItemName, ': ', ri.ReturnCondition, IF(COALESCE(ri.InspectorComment,'') <> '', CONCAT(' - ', ri.InspectorComment), ''))
                ELSE NULL
            END
            ORDER BY ii.ItemName SEPARATOR ' | '
        ) AS InspectionNotes,
        MAX(CASE WHEN ri.ReturnCondition='Damaged' THEN 2 WHEN ri.ReturnCondition='Worn' THEN 1 ELSE 0 END) AS WarningLevel,
        GROUP_CONCAT(DISTINCT rbr.ReportNumber ORDER BY rbr.ReportNumber SEPARATOR ', ') AS ReportNumbers,
        MAX(CASE WHEN rbr.SettlementStatus='Pending' THEN 1 ELSE 0 END) AS HasPendingReport
    FROM Reservation_batch rb
    LEFT JOIN Reserved_item ri ON rb.BatchID = ri.BatchID
    LEFT JOIN Inventory_item ii ON ri.AssetNumber = ii.AssetNumber
    LEFT JOIN Reservation_breakage_report rbr ON ri.BatchID = rbr.BatchID AND ri.AssetNumber = rbr.AssetNumber
    WHERE rb.UserID=?
    GROUP BY rb.BatchID
    ORDER BY {$orderBy} {$dir}, rb.StartTime {$dir}");
$stmt->bind_param('s', $userId);
$stmt->execute();
$reservations = $stmt->get_result();

function sort_link_instructor(string $key, string $label, string $currentSort, string $currentDir): string
{
    $nextDir = ($currentSort === $key && strtoupper($currentDir) === 'ASC') ? 'desc' : 'asc';
    $indicator = $currentSort === $key ? (strtoupper($currentDir) === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a href="' . url('instructor/dashboard.php?' . http_build_query(['sort' => $key, 'dir' => $nextDir])) . '">' . h($label . $indicator) . '</a>';
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <aside class="sidebar">
        <div class="profile-box">
            <div class="name"><?= h($profile['FirstName'].' '.$profile['LastName']) ?></div>
            <div class="role">Instructor · <?= h(department_code($profile['Department'])) ?></div>
        </div>
        <div class="panel" style="box-shadow:none;margin-bottom:16px;padding:16px;">
            <p style="margin:0;color:var(--muted);font-size:13px;">Department:<br><strong style="color:var(--ink);"><?= h(department_name($profile['Department'])) ?></strong></p>
        </div>
        <div class="side-links">
            <a class="active" href="<?= url('instructor/dashboard.php') ?>">Reservation Batches</a>
            <a href="<?= url('instructor/reservation_add.php') ?>">Create Reservation</a>
            <a href="<?= url('instructor/breakage_reports.php') ?>">Breakage Reports</a>
            <a href="<?= url('logout.php') ?>">Logout</a>
        </div>
    </aside>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Instructor Dashboard</h1>
                <p>View reservation batches, request returns, and review inspection status. Full damage comments are placed in Breakage Reports.</p>
            </div>
            <a class="btn btn-gold" href="<?= url('instructor/reservation_add.php') ?>">New Reservation</a>
        </div>
        <div class="panel table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Batch ID</th>
                        <th><?= sort_link_instructor('date', 'Scheduled Date', $sort, $dir) ?></th>
                        <th><?= sort_link_instructor('time', 'Time', $sort, $dir) ?></th>
                        <th>Reserved Items</th>
                        <th>Purpose</th>
                        <th><?= sort_link_instructor('status', 'Status', $sort, $dir) ?></th>
                        <th>Availability</th>
                        <th>Inspection Result</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reservations->num_rows === 0): ?>
                        <tr><td class="empty" colspan="9">No reservation batches yet.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $reservations->fetch_assoc()): ?>
                        <?php
                            $statusBadge = 'badge-warning';
                            if ($row['ReservationStatus'] === 'Return Requested') $statusBadge = 'badge-info';
                            elseif ($row['ReservationStatus'] === 'Returned') $statusBadge = 'badge-success';
                            elseif ($row['ReservationStatus'] === 'Cancelled') $statusBadge = 'badge-danger';

                            $inspectionLabel = 'No inspection yet';
                            $inspectionBadge = 'badge-muted';
                            if ($row['ReservationStatus'] === 'Return Requested') {
                                $inspectionLabel = 'Waiting for admin inspection';
                                $inspectionBadge = 'badge-info';
                            } elseif ($row['ReservationStatus'] === 'Returned') {
                                if ((int)$row['WarningLevel'] === 2) {
                                    $inspectionLabel = 'Damage Report Created';
                                    $inspectionBadge = 'badge-danger';
                                } elseif ((int)$row['WarningLevel'] === 1) {
                                    $inspectionLabel = 'Returned with warning';
                                    $inspectionBadge = 'badge-warning';
                                } else {
                                    $inspectionLabel = 'Returned in good condition';
                                    $inspectionBadge = 'badge-success';
                                }
                            }
                        ?>
                        <tr>
                            <td><?= h($row['BatchID']) ?></td>
                            <td><?= h($row['ScheduleDate']) ?></td>
                            <td><?= h(substr($row['StartTime'], 0, 5).' - '.substr($row['EndTime'], 0, 5)) ?></td>
                            <td><?= h($row['ReservedItems'] ?: 'No items listed') ?></td>
                            <td><?= h($row['Purpose']) ?></td>
                            <td>
                                <span class="badge <?= $statusBadge ?>"><?= h($row['ReservationStatus']) ?></span>
                                <?php if (!empty($row['ActualReturnDateTime'])): ?>
                                    <div class="subtle" style="margin-top:6px;">Returned: <?= h($row['ActualReturnDateTime']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($row['ConflictStatus'] ?? 'Clear') === 'At Risk'): ?>
                                    <span class="badge badge-danger">At Risk</span>
                                    <div class="subtle" style="margin-top:8px;white-space:normal;"><?= h(preview_text($row['ConflictNote'] ?? '', 95)) ?></div>
                                <?php else: ?>
                                    <span class="badge badge-success">Clear</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $inspectionBadge ?>"><?= h($inspectionLabel) ?></span>
                                <?php if ((int)$row['WarningLevel'] === 2): ?>
                                    <div style="margin-top:8px;"><a class="btn btn-small btn-outline" href="<?= url('instructor/breakage_reports.php?batch=' . urlencode($row['BatchID'])) ?>">View Report</a></div>
                                <?php elseif ((int)$row['WarningLevel'] === 1 && !empty($row['InspectionNotes'])): ?>
                                    <div class="subtle" style="margin-top:8px;white-space:normal;"><?= h(preview_text($row['InspectionNotes'], 64)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['ReservationStatus'] === 'Reserved' && ($row['ConflictStatus'] ?? 'Clear') === 'Clear'): ?>
                                    <form method="post" action="<?= url('instructor/request_return.php') ?>" data-confirm="Request return for this reservation batch? Admin/Lab Staff will still inspect the returned items.">
                                        <input type="hidden" name="batch_id" value="<?= h($row['BatchID']) ?>">
                                        <button class="btn btn-small btn-outline" type="submit">Request Return</button>
                                    </form>
                                <?php elseif ($row['ReservationStatus'] === 'Reserved' && ($row['ConflictStatus'] ?? 'Clear') === 'At Risk'): ?>
                                    <span class="subtle">Coordinate with Admin/Lab Staff</span>
                                <?php elseif ($row['ReservationStatus'] === 'Return Requested'): ?>
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
