<?php
require_once '../connect.php';
require_role(['Student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/available_items.php');
}

$userId = $_SESSION['user_id'];
$assetNumber = trim($_POST['asset_number'] ?? '');

if ($assetNumber === '') {
    set_flash('danger', 'No item selected.');
    redirect('student/available_items.php');
}

try {
    $connection->begin_transaction();

    $stmt = $connection->prepare('SELECT EnrollmentStatus, HasLiability FROM Student WHERE UserID=? FOR UPDATE');
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student || $student['EnrollmentStatus'] !== 'Officially Enrolled') {
        throw new Exception('Only officially enrolled students can borrow items.');
    }

    if ((int)$student['HasLiability'] === 1) {
        throw new Exception('You cannot borrow while you still have a pending liability.');
    }

    $stmt = $connection->prepare("SELECT COUNT(*) AS ActiveCount FROM Borrow_transaction WHERE UserID=? AND TransactionStatus IN ('Active','Overdue','Late','Return Requested')");
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $activeCount = (int)$stmt->get_result()->fetch_assoc()['ActiveCount'];

    if ($activeCount >= 5) {
        throw new Exception('You already have five open borrow transactions. Please request a return first.');
    }

    $stmt = $connection->prepare('SELECT AssetNumber, ItemName, ItemType, CurrentCondition, QuantityAvailable FROM Inventory_item WHERE AssetNumber=? FOR UPDATE');
    $stmt->bind_param('s', $assetNumber);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();

    if (!$item) {
        throw new Exception('The selected item does not exist.');
    }

    if ((int)$item['QuantityAvailable'] <= 0) {
        throw new Exception('This item is no longer available.');
    }

    if ($item['CurrentCondition'] === 'Damaged') {
        throw new Exception('Damaged items cannot be borrowed.');
    }

    $transactionNumber = app_id('TR');
    $borrowDateTime = date('Y-m-d H:i:s');
    $dueDateTime = date('Y-m-d H:i:s', strtotime('+8 hours'));
    $actualReturnDateTime = null;
    $status = 'Active';

    if ($item['ItemType'] === 'Consumable') {
        $actualReturnDateTime = $borrowDateTime;
        $status = 'Returned';
    }

    $stmt = $connection->prepare('INSERT INTO Borrow_transaction (TransactionNumber, BorrowDateTime, DueDateTime, ActualReturnDateTime, TransactionStatus, UserID, AssetNumber) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sssssss', $transactionNumber, $borrowDateTime, $dueDateTime, $actualReturnDateTime, $status, $userId, $assetNumber);
    $stmt->execute();

    $stmt = $connection->prepare('UPDATE Inventory_item SET QuantityAvailable = QuantityAvailable - 1 WHERE AssetNumber=?');
    $stmt->bind_param('s', $assetNumber);
    $stmt->execute();

    $connection->commit();
    if ($item['ItemType'] === 'Consumable') {
        set_flash('success', 'Consumable item issued successfully. Transaction No. ' . $transactionNumber);
    } else {
        set_flash('success', 'Borrow transaction created successfully. Transaction No. ' . $transactionNumber);
    }
    redirect('student/dashboard.php');
} catch (Throwable $e) {
    try { $connection->rollback(); } catch (Throwable $ignored) {}
    set_flash('danger', $e->getMessage());
    redirect('student/available_items.php');
}
