<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Unauthorized.");
}

$supplier_id = $_SESSION['user_id'];
$item_id     = isset($_POST['item_id'])     ? (int)$_POST['item_id']     : 0;
$refund_amount = isset($_POST['refund_amount']) ? (float)$_POST['refund_amount'] : 0;

if ($item_id <= 0 || $refund_amount <= 0) {
    $_SESSION['error'] = "Invalid item or refund amount.";
    header("Location: orders.php");
    exit;
}

// ──────────────────────────────────────────────────────────────
// 1. Verify ownership & get linked_order_id
// ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT supplier_id, linked_order_id 
    FROM supplier_orders 
    WHERE id = ?
");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Item not found.";
    header("Location: orders.php");
    exit;
}

$data = $result->fetch_assoc();
if ($data['supplier_id'] !== $supplier_id) {
    $_SESSION['error'] = "You are not authorized for this item.";
    header("Location: orders.php");
    exit;
}

$linked_order_id = $data['linked_order_id'];
$stmt->close();

// ──────────────────────────────────────────────────────────────
// 2. Handle receipt upload
// ──────────────────────────────────────────────────────────────
if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Refund receipt is required.";
    header("Location: orders.php");
    exit;
}

$upload_dir = 'uploads/receipts/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'pdf'];

if (!in_array($ext, $allowed)) {
    $_SESSION['error'] = "Only jpg, jpeg, png, pdf allowed.";
    header("Location: orders.php");
    exit;
}

$filename = "refund_item_{$item_id}_" . time() . "." . $ext;
$upload_path = $upload_dir . $filename;

if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
    $_SESSION['error'] = "Failed to save receipt file.";
    header("Location: orders.php");
    exit;
}

// ──────────────────────────────────────────────────────────────
// 3. Mark item as fully refunded
// ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    UPDATE supplier_orders 
    SET 
        refund_status       = 'completed',
        refund_amount       = ?,
        refund_receipt      = ?,
        refund_processed_at = NOW(),
        status              = 'refunded'           -- important for consistency
    WHERE id = ?
");
$stmt->bind_param("dsi", $refund_amount, $upload_path, $item_id);

if (!$stmt->execute()) {
    $_SESSION['error'] = "Failed to update refund status.";
    header("Location: orders.php");
    exit;
}
$stmt->close();

// ──────────────────────────────────────────────────────────────
// 4. Check if ALL items in this order are now refunded
// ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE 
            WHEN refund_status IN ('paid','completed') 
                 OR status = 'refunded' 
            THEN 1 ELSE 0 
        END) AS refunded_count
    FROM supplier_orders
    WHERE linked_order_id = ?
");
$stmt->bind_param("i", $linked_order_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row['total'] > 0 && $row['refunded_count'] === $row['total']) {
    // Whole order is fully refunded → update main order status
    $stmt = $conn->prepare("
        UPDATE orders 
        SET 
            status = 'refund',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("i", $linked_order_id);
    $stmt->execute();
    $stmt->close();
}

// ──────────────────────────────────────────────────────────────
// Success
// ──────────────────────────────────────────────────────────────
$_SESSION['success'] = "Refund processed successfully!";
header("Location: orders.php");
exit;