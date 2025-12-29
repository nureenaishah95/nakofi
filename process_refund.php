<?php
session_start();
require_once 'config.php';

// Check if user is logged in
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
    session_destroy();
    header('Location: login.php');
    exit;
}

$user_role = $user['role'];

// Only staff, staff_leader, and supplier can use this page
if (!in_array($user_role, ['staff', 'staff_leader', 'supplier'])) {
    die("Access denied. Insufficient permissions.");
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

// Get form data
$action          = $_POST['action'] ?? '';
$order_id        = (int)($_POST['order_id'] ?? 0);
$linked_order_id = (int)($_POST['linked_order_id'] ?? 0);
$refund_amount   = floatval($_POST['refund_amount'] ?? 0);

if ($order_id <= 0 || $linked_order_id <= 0 || $refund_amount <= 0) {
    die("Invalid order data.");
}

// Where to redirect after processing
$redirect_url = "flow.php?order=" . $linked_order_id;

// Folders for uploaded files
$proof_dir   = 'uploads/refund_proof/';
$receipt_dir = 'uploads/refund_receipt/';

// Create folders if they don't exist
if (!is_dir($proof_dir)) {
    mkdir($proof_dir, 0777, true);
}
if (!is_dir($receipt_dir)) {
    mkdir($receipt_dir, 0777, true);
}

// =============================================================
// STAFF / STAFF_LEADER → REQUEST REFUND
// =============================================================
if ($action === 'request_refund') {

    // Only staff can request refund
    if (!in_array($user_role, ['staff', 'staff_leader'])) {
        header("Location: $redirect_url&msg=error_permission");
        exit;
    }

    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') {
        header("Location: $redirect_url&msg=error_reason");
        exit;
    }

    // Upload proof images (multiple allowed)
    $proof_paths = [];
    if (isset($_FILES['proof_images']) && !empty($_FILES['proof_images']['name'][0])) {
        foreach ($_FILES['proof_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['proof_images']['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }

            $original_name = $_FILES['proof_images']['name'][$key];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed)) {
                continue; // skip invalid files
            }

            $filename = 'proof_' . $order_id . '_' . time() . '_' . $key . '.' . $ext;
            $target   = $proof_dir . $filename;

            if (move_uploaded_file($tmp_name, $target)) {
                $proof_paths[] = $target;
            }
        }
    }

    $proof_json = !empty($proof_paths) ? json_encode($proof_paths) : null;

    // Save to database
    $stmt = $conn->prepare("UPDATE supplier_orders 
                            SET refund_status = 'requested',
                                refund_reason = ?,
                                refund_proof = ?,
                                refund_amount = ?
                            WHERE id = ?");
    $stmt->bind_param("ssdi", $reason, $proof_json, $refund_amount, $order_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        header("Location: $redirect_url&msg=refund_requested");
    } else {
        header("Location: $redirect_url&msg=error_db");
    }
    $stmt->close();
    exit;
}

// =============================================================
// SUPPLIER → PROCESS REFUND (UPLOAD RECEIPT)
// =============================================================
else if ($action === 'process_refund') {

    // Only supplier can do this
    if ($user_role !== 'supplier') {
        header("Location: $redirect_url&msg=error_permission");
        exit;
    }

    // Must upload a receipt file
    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        header("Location: $redirect_url&msg=error_no_receipt");
        exit;
    }

    $file = $_FILES['receipt'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

    if (!in_array($ext, $allowed)) {
        header("Location: $redirect_url&msg=error_filetype");
        exit;
    }

    $filename = 'receipt_' . $order_id . '_' . time() . '.' . $ext;
    $target   = $receipt_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        $stmt = $conn->prepare("UPDATE supplier_orders 
                                SET refund_status = 'completed',
                                    refund_receipt = ?,
                                    refund_amount = ?
                                WHERE id = ?");
        $stmt->bind_param("sdi", $target, $refund_amount, $order_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            header("Location: $redirect_url&msg=refund_processed");
        } else {
            header("Location: $redirect_url&msg=error_db");
        }
        $stmt->close();
    } else {
        header("Location: $redirect_url&msg=error_upload");
    }
    exit;
}

// =============================================================
// INVALID ACTION
// =============================================================
else {
    header("Location: $redirect_url&msg=error_invalid_action");
    exit;
}