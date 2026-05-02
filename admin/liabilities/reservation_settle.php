<?php
require_once '../../connect.php';
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/liabilities/index.php');
}

$reportNumber = $_POST['report_number'] ?? '';

try {
    if ($reportNumber === '') {
        throw new Exception('No reservation breakage report was selected.');
    }

    $connection->begin_transaction();

    $stmt = $connection->prepare("SELECT ReportNumber, SettlementStatus FROM Reservation_breakage_report WHERE ReportNumber = ? FOR UPDATE");
    $stmt->bind_param('s', $reportNumber);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();

    if (!$report) {
        throw new Exception('Reservation breakage report not found.');
    }

    if ($report['SettlementStatus'] === 'Paid') {
        throw new Exception('This reservation breakage report is already resolved.');
    }

    $settlementStatus = 'Paid';
    $stmt = $connection->prepare('UPDATE Reservation_breakage_report SET SettlementStatus = ? WHERE ReportNumber = ?');
    $stmt->bind_param('ss', $settlementStatus, $reportNumber);
    $stmt->execute();

    refresh_future_reservation_conflicts($connection);

    $connection->commit();
    set_flash('success', 'Instructor reservation breakage report marked as resolved. Reservation availability was recalculated.');
} catch (Throwable $e) {
    try { $connection->rollback(); } catch (Throwable $ignored) {}
    set_flash('danger', $e->getMessage());
}

redirect('admin/liabilities/index.php');
