<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Update all pending orders to "success" + add "to ship"
$sql = "UPDATE orders 
        SET status = 'success',
            notes = CONCAT(IFNULL(notes, ''), ' to ship'),
            payment_method = 'Touch n Go eWallet',
            paid_at = NOW()
        WHERE user_id = ? AND status = 'pending'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No pending orders or update failed']);
}

$stmt->close();
$conn->close();
?>