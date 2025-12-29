<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user role for back button
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get the date from URL (e.g., ?date=2025-12-13)
$selected_date = $_GET['date'] ?? date('Y-m-d');
$formatted_date = date('d M Y', strtotime($selected_date));

// Fetch all successful orders for this date
$sql = "
    SELECT 
        orders_id,
        item_name,
        quantity,
        total,
        order_date,
        total_amount
    FROM orders 
    WHERE DATE(order_date) = ? 
      AND status = 'success'
    ORDER BY order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
$grand_total_day = 0.00;

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
    $grand_total_day += $row['total_amount'];
}

$stmt->close();

// Strictly format to 2 decimal places
$display_grand_total = number_format($grand_total_day, 2);
$estimated_sst = number_format($grand_total_day * 0.08, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Orders History - <?= $formatted_date ?> - Nakofi Cafe</title>
<link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
    :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;--success:#28a745;--info:#17a2b8;--radius:20px;--trans:all .4s ease;--shadow:0 4px 20px rgba(0,0,0,0.04);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;padding-top:100px;font-weight:700;}
    header{position:fixed;top:0;left:0;right:0;background:var(--white);padding:20px 48px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,.03);z-index:1000;}
    header .logo{height:70px;}
    header h1{font-size:32px;color:var(--charcoal);margin-left:32px;}
    .back-btn{background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);padding:10px 28px;border-radius:50px;font-weight:700;cursor:pointer;transition:var(--trans);}
    .back-btn:hover{background:var(--charcoal);color:var(--white);}
    .container{max-width:1200px;margin:40px auto;padding:0 30px;}
    .page-title{text-align:center;font-size:42px;margin-bottom:10px;color:var(--charcoal);}
    .subtitle{text-align:center;color:var(--muted);margin-bottom:40px;font-size:18px;}
    .summary-container{display:flex;gap:20px;flex-wrap:wrap;justify-content:center;margin-bottom:30px;}
    .summary-box{padding:20px;border-radius:var(--radius);text-align:center;font-size:24px;color:white;flex:1;min-width:300px;}
    .total-sales{background:var(--success);}
    .sst-box{background:var(--info);}
    .sst-note{font-size:14px;margin-top:8px;opacity:0.9;}
    table{width:100%;border-collapse:collapse;background:var(--card);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
    th{background:var(--charcoal);color:white;padding:18px;text-align:left;font-size:16px;}
    td{padding:16px 18px;border-bottom:1px solid var(--border);font-size:17px;}
    tr:hover{background:#f8fdf9;}
    .item-name{font-weight:700;color:var(--charcoal);}
    .total-row{font-weight:900;font-size:20px;background:#f0fdf4;}
    .no-orders{text-align:center;padding:80px;color:var(--muted);font-size:18px;}
    .print-btn{
            margin:40px auto 0;padding:14px 40px;background:var(--charcoal);color:var(--white);
            border:none;border-radius:50px;font-weight:700;font-size:16px;cursor:pointer;
            transition:var(--trans);display:block;width:fit-content;font-family:'Playfair Display',serif;
        }
        .print-btn:hover{background:#1a1d20;transform:translateY(-4px);}

        .complete-stamp{
            display:none;position:absolute;top:45%;left:50%;
            transform:translate(-50%,-50%) rotate(-30deg);font-size:120px;
            color:rgba(40,167,69,0.15);font-weight:900;text-transform:uppercase;
            pointer-events:none;z-index:1;
        }
    footer{text-align:center;padding:60px;color:var(--muted);font-size:13px;}
    @media(max-width:768px){
        header{flex-direction:column;gap:16px;padding:20px;text-align:center;}
        header h1{margin-left:0;font-size:28px;}
        .summary-container{flex-direction:column;}
        table, thead, tbody, th, td, tr { display:block; }
        thead tr { position:absolute; top:-9999px; left:-9999px; }
        tr { margin-bottom:20px; border:1px solid var(--border); border-radius:12px; }
        td { position:relative; padding-left:50%; text-align:right; }
        td:before { content:attr(data-label); position:absolute; left:20px; width:45%; font-weight:700; text-align:left; color:var(--muted); }
    }
</style>
</head>
<body>

<header>
    <img src="http://localhost/nakofi/asset/img/logo.png" alt="Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="back-btn" onclick="goBack()"><i class="fas fa-arrow-left"></i> Back</button>
</header>

<div class="container">
    <h1 class="page-title">Orders History</h1>
    <p class="subtitle">All successful orders on <strong><?= $formatted_date ?></strong></p>

    <div class="summary-container">
        <div class="summary-box total-sales">
            Total Sales for This Day: <strong>RM<?= $display_grand_total ?></strong>
        </div>
        <div class="summary-box sst-box">
            Estimated SST Collected (8%): <strong>RM<?= $estimated_sst ?></strong>
            <div class="sst-note">Estimated 8% Sales & Service Tax collected from customers</div>
        </div>
    </div>

    <?php if (!empty($orders)): ?>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Unit Price (RM)</th>
                    <th>Subtotal (RM)</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($orders as $order): 
                    $unit_price = number_format($order['total'] / $order['quantity'], 2);
                    $subtotal = number_format($order['total'], 2);
                    $time = date('h:i A', strtotime($order['order_date']));
                ?>
                    <tr>
                        <td data-label="No"><?= $no++ ?></td>
                        <td data-label="Item" class="item-name"><?= ucwords($order['item_name']) ?></td>
                        <td data-label="Qty"><?= $order['quantity'] ?></td>
                        <td data-label="Unit Price">RM<?= $unit_price ?></td>
                        <td data-label="Subtotal">RM<?= $subtotal ?></td>
                        <td data-label="Time"><?= $time ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="5" style="text-align:right;">Grand Total</td>
                    <td>RM<?= $display_grand_total ?></td>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-orders">
            <i class="fas fa-receipt" style="font-size:60px;color:#ddd;margin-bottom:20px;"></i>
            <p>No orders found for <?= $formatted_date ?>.</p>
        </div>
    <?php endif; ?>

        <button class="print-btn" onclick="handlePrint()">Print Report</button>
        <div class="complete-stamp">COMPLETE</div>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>

<script>
    function handlePrint() {
    const stamp = document.querySelector('.complete-stamp');
    stamp.style.display = 'block';
    setTimeout(() => window.print(), 100);
}
    if (new URLSearchParams(window.location.search).get('print') === '1') {
    setTimeout(handlePrint, 500);
}

function goBack() {
    document.body.style.opacity = '0.5';
    setTimeout(() => {
        window.location.href = '<?= $user['role'] == "staff_leader" ? "staff_leader.php" : "staff.php" ?>';
    }, 400);
}
</script>

</body>
</html>