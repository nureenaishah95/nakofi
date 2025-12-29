<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE orders SET status = ?, payment_method = ?, paid_at = NOW() WHERE id = ? AND status = 'pending'");
    $stmt->execute([$status, $_POST['method'], $order_id]);

    // Optional: reduce stock, send email, etc.
    echo "success";
}
?>