<?php
require_once '../connect.php';
require_once ROOT_PATH . '/includes/options.php';
require_role(['Instructor']);
$pageTitle = 'Create Reservation';
$userId = $_SESSION['user_id'];
$departmentFilter = trim($_GET['department'] ?? '');

$departmentOptions = $connection->query('SELECT DepartmentID, DepartmentName FROM Department ORDER BY DepartmentName');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batchId = app_id('BAT');
    $scheduleDate = $_POST['schedule_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');
    $quantities = $_POST['quantities'] ?? [];

    try {
        if ($scheduleDate === '' || $startTime === '' || $endTime === '' || $purpose === '') {
            throw new Exception('Please complete the reservation date, time, and purpose.');
        }
        if ($endTime <= $startTime) {
            throw new Exception('End time must be later than start time.');
        }

        $selectedItems = [];
        foreach ($quantities as $assetNumber => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity > 0) {
                $selectedItems[$assetNumber] = $quantity;
            }
        }
        if (!$selectedItems) {
            throw new Exception('Please enter a quantity for at least one item.');
        }

        $connection->begin_transaction();

        $itemStmt = $connection->prepare("SELECT i.AssetNumber, i.ItemName, i.QuantityAvailable,
                COALESCE(pending.PendingPenaltyUnits, 0) AS PendingPenaltyUnits,
                GREATEST(i.QuantityAvailable - COALESCE(pending.PendingPenaltyUnits, 0), 0) AS UsableStock
            FROM Inventory_item i
            LEFT JOIN (
                SELECT AssetNumber, SUM(QuantityMissing + QuantityDamaged) AS PendingPenaltyUnits
                FROM Reservation_breakage_report
                WHERE SettlementStatus = 'Pending'
                GROUP BY AssetNumber
            ) pending ON pending.AssetNumber = i.AssetNumber
            WHERE i.AssetNumber=? FOR UPDATE");
        $conflictStmt = $connection->prepare("
            SELECT COALESCE(SUM(ri.QuantityReserved), 0) AS ReservedDuringSlot
            FROM Reserved_item ri
            JOIN Reservation_batch rb ON ri.BatchID = rb.BatchID
            WHERE ri.AssetNumber = ?
              AND rb.ScheduleDate = ?
              AND rb.ReservationStatus IN ('Reserved', 'Return Requested')
              AND rb.StartTime < ?
              AND rb.EndTime > ?
        ");

        $validatedItems = [];
        foreach ($selectedItems as $assetNumber => $quantity) {
            $itemStmt->bind_param('s', $assetNumber);
            $itemStmt->execute();
            $item = $itemStmt->get_result()->fetch_assoc();

            if (!$item) {
                throw new Exception('One selected item was not found.');
            }

            $conflictStmt->bind_param('ssss', $assetNumber, $scheduleDate, $endTime, $startTime);
            $conflictStmt->execute();
            $reservedDuringSlot = (int)$conflictStmt->get_result()->fetch_assoc()['ReservedDuringSlot'];

            $usableStock = (int)$item['UsableStock'];
            $availableForSlot = $usableStock - $reservedDuringSlot;
            if ($quantity > $availableForSlot) {
                throw new Exception($item['ItemName'] . ' cannot be reserved for the selected schedule. Total stock: ' . $item['QuantityAvailable'] . ', pending unresolved penalty units: ' . $item['PendingPenaltyUnits'] . ', usable stock: ' . $usableStock . ', already reserved during that time: ' . $reservedDuringSlot . ', remaining for that slot: ' . max(0, $availableForSlot) . '.');
            }

            $validatedItems[$assetNumber] = $quantity;
        }

        $stmt = $connection->prepare('INSERT INTO Reservation_batch (BatchID, ScheduleDate, StartTime, EndTime, Purpose, UserID) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $batchId, $scheduleDate, $startTime, $endTime, $purpose, $userId);
        $stmt->execute();

        $reserveStmt = $connection->prepare('INSERT INTO Reserved_item (BatchID, AssetNumber, QuantityReserved) VALUES (?, ?, ?)');
        foreach ($validatedItems as $assetNumber => $quantity) {
            $reserveStmt->bind_param('ssi', $batchId, $assetNumber, $quantity);
            $reserveStmt->execute();
        }

        refresh_future_reservation_conflicts($connection);

        $connection->commit();
        set_flash('success', 'Reservation batch created. The system checked schedule conflicts before approving the requested quantities. Batch ID: ' . $batchId);
        redirect('instructor/dashboard.php');
    } catch (Throwable $e) {
        try { $connection->rollback(); } catch (Throwable $ignored) {}
        set_flash('danger', $e->getMessage());
    }
}

$where = ['i.QuantityAvailable > 0', "i.CurrentCondition <> 'Damaged'"];
$params = [];
$types = '';
if ($departmentFilter !== '') {
    $where[] = 'i.DepartmentID = ?';
    $params[] = $departmentFilter;
    $types = 's';
}
$sql = "SELECT i.*, d.DepartmentName,
        COALESCE(pending.PendingPenaltyUnits, 0) AS PendingPenaltyUnits,
        GREATEST(i.QuantityAvailable - COALESCE(pending.PendingPenaltyUnits, 0), 0) AS UsableStock
    FROM Inventory_item i
    JOIN Department d ON i.DepartmentID=d.DepartmentID
    LEFT JOIN (
        SELECT AssetNumber, SUM(QuantityMissing + QuantityDamaged) AS PendingPenaltyUnits
        FROM Reservation_breakage_report
        WHERE SettlementStatus = 'Pending'
        GROUP BY AssetNumber
    ) pending ON pending.AssetNumber = i.AssetNumber
    WHERE " . implode(' AND ', $where) . " ORDER BY d.DepartmentName, i.ItemName";
$stmt = $connection->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$items = $stmt->get_result();

$itemsByDepartment = [];
while ($row = $items->fetch_assoc()) {
    $itemsByDepartment[$row['DepartmentName']][] = $row;
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="layout">
    <aside class="sidebar">
        <div class="profile-box">
            <div class="name">Create Reservation</div>
            <div class="role">Instructor Portal</div>
        </div>
        <div class="side-links">
            <a href="<?= url('instructor/dashboard.php') ?>">Reservation Batches</a>
            <a class="active" href="<?= url('instructor/reservation_add.php') ?>">Create Reservation</a>
            <a href="<?= url('instructor/breakage_reports.php') ?>">Breakage Reports</a>
            <a href="<?= url('logout.php') ?>">Logout</a>
        </div>
    </aside>
    <section class="content">
        <div class="page-head">
            <div>
                <h1>Create Reservation Batch</h1>
                <p>Select items by college department and enter the quantity to reserve. Approval is based on schedule availability.</p>
            </div>
            <a class="btn btn-outline" href="<?= url('instructor/dashboard.php') ?>">Back</a>
        </div>

        <div class="panel filter-bar">
            <form method="get" class="form-grid">
                <div>
                    <label for="department">Filter Items by Department</label>
                    <select id="department" name="department" onchange="this.form.submit()">
                        <option value="">All College Departments</option>
                        <?php while ($d = $departmentOptions->fetch_assoc()): ?>
                            <option value="<?= h($d['DepartmentID']) ?>" <?= $departmentFilter === $d['DepartmentID'] ? 'selected' : '' ?>><?= h(department_code($d['DepartmentID']) . ' | ' . department_short_name($d['DepartmentID'])) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>

        <form class="panel" method="post">
            <div class="form-grid">
                <div>
                    <label for="schedule_date">Scheduled Date</label>
                    <input id="schedule_date" type="date" name="schedule_date" required>
                </div>
                <div>
                    <label for="purpose">Purpose</label>
                    <input id="purpose" name="purpose" placeholder="Example: CPE 401 laboratory experiment" required>
                </div>
                <div>
                    <label for="start_time">Start Time</label>
                    <input id="start_time" type="time" name="start_time" required>
                </div>
                <div>
                    <label for="end_time">End Time</label>
                    <input id="end_time" type="time" name="end_time" required>
                </div>
            </div>

            <div style="margin-top:22px;" class="stack">
                <div class="notice-card">
                    Enter a quantity only for items you want to reserve. The system now checks overlapping reservations for the same date and time first. 
                    Example: if there are 40 microscopes, a Monday 40-piece reservation will not block a Tuesday 40-piece reservation, but another overlapping Monday reservation will be rejected if the remaining quantity cannot cover it.
                </div>

                <?php if (!$itemsByDepartment): ?>
                    <div class="empty">No available items found for the selected department.</div>
                <?php endif; ?>

                <?php foreach ($itemsByDepartment as $departmentName => $rows): ?>
                    <div>
                        <h2 style="font-size:20px;margin:8px 0 12px;"><?= h($departmentName) ?></h2>
                        <div class="selectable-grid">
                            <?php foreach ($rows as $item): ?>
                                <div class="item-select-card">
                                    <header>
                                        <div>
                                            <strong><?= h($item['ItemName']) ?></strong><br>
                                            <small><?= h($item['AssetNumber']) ?> · <?= h($item['Category']) ?></small>
                                        </div>
                                        <span class="badge <?= $item['CurrentCondition'] === 'Good' ? 'badge-success' : 'badge-warning' ?>"><?= h($item['CurrentCondition']) ?></span>
                                    </header>
                                    <div class="quantity-row">
                                        <div>
                                            <small class="subtle">Type: <?= h($item['ItemType']) ?> · Total Stock: <?= h((string)$item['QuantityAvailable']) ?><?php if ((int)($item['PendingPenaltyUnits'] ?? 0) > 0): ?> · Pending Issue Units: <?= h((string)$item['PendingPenaltyUnits']) ?> · Usable Stock: <?= h((string)$item['UsableStock']) ?><?php endif; ?></small>
                                        </div>
                                        <div>
                                            <label for="qty_<?= h($item['AssetNumber']) ?>">Quantity</label>
                                            <input id="qty_<?= h($item['AssetNumber']) ?>" type="number" name="quantities[<?= h($item['AssetNumber']) ?>]" min="0" max="<?= h((string)$item['QuantityAvailable']) ?>" value="0">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-actions">
                <a class="btn btn-outline" href="<?= url('instructor/dashboard.php') ?>">Cancel</a>
                <button class="btn btn-primary" type="submit">Submit Reservation Batch</button>
            </div>
        </form>
    </section>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
