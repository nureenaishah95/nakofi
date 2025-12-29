<?php
// update_delivery.php - Fixed version (no tracking columns needed)

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: login.php');
    exit;
}

$user_role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$order_id = $_POST['order_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$order_id || !in_array($action, ['ship', 'confirm'])) {
    die("Invalid data.");
}

// Get current status
$stmt = $conn->prepare("SELECT status FROM supplier_orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found.");
}

$current_status = strtolower($order['status'] ?? 'pending');

if ($action === 'ship') {
    // Supplier marks as shipped
    if ($user_role !== 'supplier' || $current_status !== 'pending') {
        die("Not allowed.");
    }
    $update = $conn->prepare("UPDATE supplier_orders SET status = 'shipped' WHERE id = ?");
    $update->bind_param("i", $order_id);

} elseif ($action === 'confirm') {
    // Staff confirms receipt
    if (!in_array($user_role, ['staff', 'staff_leader']) || $current_status !== 'shipped') {
        die("Not allowed.");
    }
    $update = $conn->prepare("UPDATE supplier_orders SET status = 'completed' WHERE id = ?");
    $update->bind_param("i", $order_id);
}

$update->execute();

$referer = $_SERVER['HTTP_REFERER'] ?? 'flow.php';
header("Location: $referer");
exit;
?>