<?php
require_once '../connect.php';
require_role(['Student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/dashboard.php');
}

$userId = $_SESSION['user_id'];
$transactionNumber = trim($_POST['transaction_number'] ?? '');

if ($transactionNumber === '') {
    set_flash('danger', 'No transaction was selected.');
    redirect('student/dashboard.php');
}

try {
    $stmt = $connection->prepare("UPDATE Borrow_transaction SET TransactionStatus='Return Requested' WHERE TransactionNumber=? AND UserID=? AND TransactionStatus IN ('Active','Overdue','Late')");
    $stmt->bind_param('ss', $transactionNumber, $userId);
    $stmt->execute();

    if ($stmt->affected_rows <= 0) {
        throw new Exception('This transaction cannot be requested for return, or it is already pending inspection.');
    }

    set_flash('success', 'Return request submitted. Please bring the item to the Admin/Lab Staff for inspection.');
} catch (Throwable $e) {
    set_flash('danger', $e->getMessage());
}

redirect('student/dashboard.php');
