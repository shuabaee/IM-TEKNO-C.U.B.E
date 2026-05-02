<?php
require_once '../connect.php';
require_role(['Instructor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('instructor/dashboard.php');
}

$userId = $_SESSION['user_id'];
$batchId = trim($_POST['batch_id'] ?? '');

if ($batchId === '') {
    set_flash('danger', 'No reservation batch was selected.');
    redirect('instructor/dashboard.php');
}

try {
    $stmt = $connection->prepare("SELECT ConflictStatus, ConflictNote FROM Reservation_batch WHERE BatchID=? AND UserID=? AND ReservationStatus='Reserved' LIMIT 1");
    $stmt->bind_param('ss', $batchId, $userId);
    $stmt->execute();
    $batch = $stmt->get_result()->fetch_assoc();

    if (!$batch) {
        throw new Exception('This reservation batch cannot be requested for return, or it is already closed.');
    }

    if (($batch['ConflictStatus'] ?? 'Clear') === 'At Risk') {
        throw new Exception('This reservation is currently marked At Risk because available stock may no longer cover it. Please coordinate with the Admin/Lab Staff before proceeding.');
    }

    $stmt = $connection->prepare("UPDATE Reservation_batch SET ReservationStatus='Return Requested' WHERE BatchID=? AND UserID=? AND ReservationStatus='Reserved' AND ConflictStatus='Clear'");
    $stmt->bind_param('ss', $batchId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows <= 0) {
        throw new Exception('This reservation batch cannot be requested for return, or it is already closed.');
    }

    set_flash('success', 'Return request submitted. Please bring the reserved items to the Admin/Lab Staff for inspection.');
} catch (Throwable $e) {
    set_flash('danger', $e->getMessage());
}

redirect('instructor/dashboard.php');
