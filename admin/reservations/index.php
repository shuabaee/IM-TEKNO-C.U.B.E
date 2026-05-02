<?php
require_once '../../connect.php';
require_role(['Admin']);
$pageTitle = 'Instructor Returns';
$active = 'reservation_returns';
$statusFilter = $_GET['status'] ?? 'Return Requested';
$allowed = ['Reserved', 'Return Requested', 'Returned', 'At Risk', 'All'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'Return Requested';
}

refresh_future_reservation_conflicts($connection);

$where = '';
if ($statusFilter === 'At Risk') {
    $where = "WHERE rb.ConflictStatus = 'At Risk' AND rb.ReservationStatus IN ('Reserved','Return Requested')";
} elseif ($statusFilter !== 'All') {
    $where = "WHERE rb.ReservationStatus = '" . $connection->real_escape_string($statusFilter) . "'";
}

$sql = "SELECT rb.BatchID, rb.ScheduleDate, rb.StartTime, rb.EndTime, rb.Purpose, rb.ReservationStatus, rb.ConflictStatus, rb.ConflictNote, rb.ActualReturnDateTime,
        u.UserID, u.FirstName, u.LastName,
        GROUP_CONCAT(CONCAT(ri.QuantityReserved, ' x ', ii.ItemName) ORDER BY ii.ItemName SEPARATOR ', ') AS ReservedItems
    FROM Reservation_batch rb
    JOIN `User` u ON rb.UserID = u.UserID
    LEFT JOIN Reserved_item ri ON rb.BatchID = ri.BatchID
    LEFT JOIN Inventory_item ii ON ri.AssetNumber = ii.AssetNumber
    {$where}
    GROUP BY rb.BatchID
    ORDER BY FIELD(rb.ReservationStatus, 'Return Requested', 'Reserved', 'Returned', 'Cancelled'), rb.ScheduleDate DESC, rb.StartTime DESC";
$reservations = $connection->query($sql);
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <?php require ROOT_PATH . '/admin/sidebar.php'; ?>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Instructor Reservation Returns</h1>
                <p>Track instructor batches, review return requests, and confirm the actual returned quantities after inspection.</p>
            </div>
        </div>
        <div class="panel filter-bar">
            <form method="get" class="form-grid">
                <div>
                    <label for="status">Filter by Reservation Status</label>
                    <select id="status" name="status">
                        <?php foreach ($allowed as $status): ?>
                            <option value="<?= h($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions" style="justify-content:flex-start;margin-top:20px;">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="btn btn-outline" href="<?= url('admin/reservations/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>
        <div class="panel table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Instructor</th>
                        <th>Schedule</th>
                        <th>Reserved Items</th>
                        <th>Status</th>
                        <th>Availability</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reservations->num_rows === 0): ?>
                        <tr><td class="empty" colspan="7">No instructor reservation batches found.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $reservations->fetch_assoc()): ?>
                        <?php
                            $badge = 'badge-muted';
                            if ($row['ReservationStatus'] === 'Return Requested') $badge = 'badge-info';
                            elseif ($row['ReservationStatus'] === 'Reserved') $badge = 'badge-warning';
                            elseif ($row['ReservationStatus'] === 'Returned') $badge = 'badge-success';
                        ?>
                        <tr>
                            <td><strong><?= h($row['BatchID']) ?></strong><br><span class="subtle"><?= h($row['Purpose']) ?></span></td>
                            <td><?= h($row['FirstName'] . ' ' . $row['LastName']) ?><br><span class="subtle"><?= h($row['UserID']) ?></span></td>
                            <td><?= h($row['ScheduleDate']) ?><br><span class="subtle"><?= h(substr($row['StartTime'],0,5) . ' - ' . substr($row['EndTime'],0,5)) ?></span></td>
                            <td><?= h($row['ReservedItems'] ?: 'No items listed') ?></td>
                            <td>
                                <span class="badge <?= $badge ?>"><?= h($row['ReservationStatus']) ?></span>
                                <?php if (!empty($row['ActualReturnDateTime'])): ?>
                                    <div class="subtle" style="margin-top:6px;">Returned: <?= h($row['ActualReturnDateTime']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($row['ConflictStatus'] ?? 'Clear') === 'At Risk'): ?>
                                    <span class="badge badge-danger">At Risk</span>
                                    <div class="subtle" style="margin-top:8px;white-space:normal;"><?= h(preview_text($row['ConflictNote'] ?? '', 120)) ?></div>
                                <?php else: ?>
                                    <span class="badge badge-success">Clear</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['ReservationStatus'] === 'Return Requested'): ?>
                                    <a class="btn btn-small btn-primary" href="<?= url('admin/reservations/inspect.php?id=' . urlencode($row['BatchID'])) ?>">Inspect Batch</a>
                                <?php else: ?>
                                    <a class="btn btn-small btn-outline" href="<?= url('admin/reservations/inspect.php?id=' . urlencode($row['BatchID'])) ?>">View Batch</a>
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
