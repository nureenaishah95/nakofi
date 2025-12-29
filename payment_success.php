<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get supplier ID from URL
$paid_supplier_id = $_GET['supplier'] ?? null;
if (!$paid_supplier_id || !is_numeric($paid_supplier_id)) {
    die("Error: Invalid supplier ID.");
}
$paid_supplier_id = intval($paid_supplier_id);

// Fetch user role
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$role = $user['role'] ?? 'staff';

// Calculate grand total (subtotal + 8% tax)
$sql_total = "
    SELECT 
        SUM(o.total) AS subtotal,
        ROUND(SUM(o.total) * 0.08, 2) AS tax,
        ROUND(SUM(o.total) + (SUM(o.total) * 0.08), 2) AS grand_total,
        COUNT(*) AS order_count
    FROM orders o
    JOIN supplier_orders so ON o.orders_id = so.linked_order_id
    WHERE so.supplier_id = ?
      AND o.payment_status = 'unpaid'
";

$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $paid_supplier_id);
$stmt_total->execute();
$total_result = $stmt_total->get_result();
$total_row = $total_result->fetch_assoc();
$stmt_total->close();

$grand_total = $total_row['grand_total'] ?? 0;
$order_count = $total_row['order_count'] ?? 0;

$message = "";

if ($grand_total == 0 || $order_count == 0) {
    $message = "No unpaid orders found for this supplier.<br>Possibly already paid or no pending orders.";
} else {
    // Update orders - NO comments inside the SQL string!
    $sql_update = "
        UPDATE orders o
        JOIN supplier_orders so ON o.orders_id = so.linked_order_id
        SET 
            o.payment_status = 'paid',
            o.status = 'PAYMENT SUCCESS',
            o.total_amount = ?
        WHERE so.supplier_id = ?
          AND o.payment_status = 'unpaid'
    ";

    $stmt_update = $conn->prepare($sql_update);
    if (!$stmt_update) {
        die("Database prepare error: " . $conn->error);
    }

    $stmt_update->bind_param("di", $grand_total, $paid_supplier_id);
    $stmt_update->execute();
    $affected = $stmt_update->affected_rows;
    $stmt_update->close();

    if ($affected > 0) {
        $message = "<strong>Payment Successful!</strong><br>
                    Amount Paid: <strong>RM " . number_format($grand_total, 2) . "</strong><br>
                    $affected order(s) marked as paid.";
    } else {
        $message = "No orders were updated.<br>They may have already been paid.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - Payment Success</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght=700&display=swap" rel="stylesheet">
    <style>
        body {font-family:'Playfair Display',serif;background:#f8fdf9;text-align:center;padding:100px 20px;color:#111; min-height:100vh;}
        .success-icon {font-size:100px;color:#28a745;margin-bottom:30px;}
        h1 {font-size:48px;color:#28a745;margin-bottom:20px;}
        p {font-size:20px;color:#666;margin-bottom:40px;line-height:1.6;}
        .btn {padding:16px 40px;background:#212529;color:white;border:none;border-radius:50px;font-size:18px;cursor:pointer;text-decoration:none;display:inline-block;transition:background .3s;margin:10px;}
        .btn:hover {background:#111;}
        .btn-secondary {background:#6c757d;}
        .btn-secondary:hover {background:#545b62;}
    </style>
</head>
<body>
    <div class="success-icon">
        <i class="fas fa-check-circle"></i>
    </div>
    <h1>Payment Successful!</h1>
    <div class="message">
        <?= $message ?>
    </div>

    <a href="payment.php" class="btn">Back to Payment Page</a>
    <br><br>
    <a href="<?= $role === 'staff_leader' ? 'staff_leader.php' : 'staff.php' ?>" class="btn btn-secondary">
        Go to Dashboard
    </a>

    <script>
        // Clean up sessionStorage
        sessionStorage.removeItem('paying_supplier_id');
        sessionStorage.removeItem('payment_amount');
    </script>
</body>
</html>