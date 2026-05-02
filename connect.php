<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROOT_PATH', __DIR__);

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$parts = explode('/', trim($scriptName, '/'));
$baseSegment = $parts[0] ?? '';
$baseUrl = $baseSegment !== '' ? '/' . $baseSegment . '/' : '/';
define('BASE_URL', $baseUrl);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tekno_cube_db');

try {
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('Database connection failed. Please import database/tekno_cube.sql in phpMyAdmin first. Error: ' . htmlspecialchars($e->getMessage()));
}

function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function current_user(): ?array
{
    global $connection;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $connection->prepare('SELECT UserID, FirstName, LastName, UserType, Email FROM `User` WHERE UserID = ? LIMIT 1');
    $stmt->bind_param('s', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc() ?: null;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        set_flash('warning', 'Please login first.');
        redirect('login.php');
    }
}

function require_role(array $roles): void
{
    require_login();
    $userType = $_SESSION['user_type'] ?? '';
    if (!in_array($userType, $roles, true)) {
        set_flash('danger', 'You are not allowed to access that page.');
        redirect('dashboard.php');
    }
}

function app_id(string $prefix): string
{
    return strtoupper($prefix) . '-' . date('ymd') . '-' . random_int(1000, 9999);
}

function display_settlement_status(string $status): string
{
    return $status === 'Paid' ? 'Resolved' : $status;
}

function preview_text(?string $text, int $limit = 55): string
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '...' : $text;
    }
    return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
}

function refresh_future_reservation_conflicts(mysqli $connection): int
{
    // Recalculate actionable future reservation risk after stock changes, inspections, or replenishment.
    // This first clears stale risk flags, then re-flags only future reservations that
    // cannot be supported by the current usable inventory.
    $connection->query("UPDATE Reservation_batch
        SET ConflictStatus='Clear', ConflictNote=NULL
        WHERE ReservationStatus IN ('Reserved','Return Requested')");

    $sql = "SELECT rb.BatchID, rb.ScheduleDate, rb.StartTime, rb.EndTime,
            ri.AssetNumber, i.ItemName, i.QuantityAvailable,
            COALESCE(pending.PendingPenaltyUnits, 0) AS PendingPenaltyUnits,
            GREATEST(i.QuantityAvailable - COALESCE(pending.PendingPenaltyUnits, 0), 0) AS UsableStock,
            COALESCE(SUM(ri2.QuantityReserved), 0) AS ReservedDuringSlot
        FROM Reservation_batch rb
        JOIN Reserved_item ri ON rb.BatchID = ri.BatchID
        JOIN Inventory_item i ON ri.AssetNumber = i.AssetNumber
        LEFT JOIN (
            SELECT AssetNumber, SUM(QuantityMissing + QuantityDamaged) AS PendingPenaltyUnits
            FROM Reservation_breakage_report
            WHERE SettlementStatus = 'Pending'
            GROUP BY AssetNumber
        ) pending ON pending.AssetNumber = ri.AssetNumber
        JOIN Reservation_batch rb2 ON rb2.ScheduleDate = rb.ScheduleDate
            AND rb2.ReservationStatus IN ('Reserved','Return Requested')
            AND rb2.StartTime < rb.EndTime
            AND rb2.EndTime > rb.StartTime
        JOIN Reserved_item ri2 ON ri2.BatchID = rb2.BatchID
            AND ri2.AssetNumber = ri.AssetNumber
        WHERE rb.ReservationStatus IN ('Reserved','Return Requested')
          AND CONCAT(rb.ScheduleDate, ' ', rb.EndTime) >= NOW()
        GROUP BY rb.BatchID, rb.ScheduleDate, rb.StartTime, rb.EndTime,
                 ri.AssetNumber, i.ItemName, i.QuantityAvailable, pending.PendingPenaltyUnits
        HAVING ReservedDuringSlot > UsableStock";

    $result = $connection->query($sql);
    $notesByBatch = [];
    while ($row = $result->fetch_assoc()) {
        $shortage = max(0, (int)$row['ReservedDuringSlot'] - (int)$row['UsableStock']);
        $notesByBatch[$row['BatchID']][] = $row['ItemName'] . ' is short by ' . $shortage . ' unit(s) for ' . $row['ScheduleDate'] . ' ' . substr($row['StartTime'], 0, 5) . '-' . substr($row['EndTime'], 0, 5) . '. Needed during this slot: ' . $row['ReservedDuringSlot'] . ', total stock: ' . $row['QuantityAvailable'] . ', pending unresolved penalty units: ' . $row['PendingPenaltyUnits'] . ', usable stock: ' . $row['UsableStock'] . '.';
    }

    $update = $connection->prepare("UPDATE Reservation_batch SET ConflictStatus='At Risk', ConflictNote=? WHERE BatchID=?");
    $flagged = 0;
    foreach ($notesByBatch as $batchId => $notes) {
        $note = implode("\n", $notes);
        $update->bind_param('ss', $note, $batchId);
        $update->execute();
        $flagged++;
    }
    return $flagged;
}

?>
