<?php
require_once '../../connect.php';
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/liabilities/index.php');
}

$reportNumber = $_POST['report_number'] ?? '';

try {
    if ($reportNumber === '') {
        throw new Exception('No breakage report was selected.');
    }

    $connection->begin_transaction();

    $stmt = $connection->prepare("SELECT br.ReportNumber, br.SettlementStatus, bt.UserID
        FROM Breakage_report br
        JOIN Borrow_transaction bt ON br.TransactionNumber = bt.TransactionNumber
        WHERE br.ReportNumber = ?
        FOR UPDATE");
    $stmt->bind_param('s', $reportNumber);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();

    if (!$report) {
        throw new Exception('Breakage report not found.');
    }

    if ($report['SettlementStatus'] === 'Paid') {
        throw new Exception('This breakage report is already resolved.');
    }

    $settlementStatus = 'Paid';
    $stmt = $connection->prepare('UPDATE Breakage_report SET SettlementStatus = ? WHERE ReportNumber = ?');
    $stmt->bind_param('ss', $settlementStatus, $reportNumber);
    $stmt->execute();

    $userId = $report['UserID'];
    $stmt = $connection->prepare("SELECT COUNT(*) AS total
        FROM Breakage_report br
        JOIN Borrow_transaction bt ON br.TransactionNumber = bt.TransactionNumber
        WHERE bt.UserID = ? AND br.SettlementStatus = 'Pending'");
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $pendingCount = (int)$stmt->get_result()->fetch_assoc()['total'];

    $isStudentStmt = $connection->prepare('SELECT COUNT(*) AS total FROM Student WHERE UserID = ?');
    $isStudentStmt->bind_param('s', $userId);
    $isStudentStmt->execute();
    $isStudent = (int)$isStudentStmt->get_result()->fetch_assoc()['total'] > 0;

    if ($isStudent) {
        $liabilityValue = $pendingCount > 0 ? 1 : 0;
        $stmt = $connection->prepare('UPDATE Student SET HasLiability = ? WHERE UserID = ?');
        $stmt->bind_param('is', $liabilityValue, $userId);
        $stmt->execute();
    }

    $connection->commit();
    if ($isStudent && $pendingCount === 0) {
        set_flash('success', 'Report marked as resolved. The student account has been unblocked.');
    } else {
        set_flash('success', 'Report marked as resolved successfully.');
    }
} catch (Throwable $e) {
    try {
        $connection->rollback();
    } catch (Throwable $ignored) {
    }
    set_flash('danger', $e->getMessage());
}

redirect('admin/liabilities/index.php');
