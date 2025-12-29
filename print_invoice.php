<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$supID = $_SESSION['user_id'];

// Validate linked_order_id
$linked_order_id = $_GET['linked_order_id'] ?? '';
if (empty($linked_order_id)) {
    die("Invalid invoice request. Missing order ID.");
}

// Fetch supplier details (supID in supplier table)
$stmt = $conn->prepare("SELECT name, email FROM supplier WHERE supID = ?");
if ($stmt === false) {
    die("Database error (supplier): " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $supID);
$stmt->execute();
$supplier_result = $stmt->get_result();
$supplier = $supplier_result->fetch_assoc();
$stmt->close();

if (!$supplier) {
    die("Supplier not found.");
}

$supplier_name = $supplier['name'] ?? 'Unknown Supplier';
$supplier_email = $supplier['email'] ?? 'No email provided';

// Fetch completed order items for this linked_order_id
$sql = "
    SELECT 
        item_name,
        quantity,
        total AS subtotal,
        order_date
    FROM supplier_orders
    WHERE linked_order_id = ?
      AND supplier_id = ?
      AND LOWER(status) = 'completed'
    ORDER BY item_name
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Database prepare error: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("si", $linked_order_id, $supID);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$subtotal = 0.00;
$order_date = '';

while ($row = $result->fetch_assoc()) {
    $row['unit_price'] = $row['quantity'] > 0 ? $row['subtotal'] / $row['quantity'] : 0;
    $items[] = $row;
    $subtotal += $row['subtotal'];
    $order_date = $row['order_date'];
}

$stmt->close();

if (empty($items)) {
    die("No completed order found for Invoice #$linked_order_id.<br>
         Possible reasons:<br>
         • The linked_order_id does not exist<br>
         • No items have status = 'completed'<br>
         • Supplier ID mismatch");
}

$formatted_date = date('d M Y', strtotime($order_date));
$tax_amount = $subtotal * 0.08;
$grand_total = $subtotal + $tax_amount;

$subtotal_fmt = number_format($subtotal, 2);
$tax_fmt = number_format($tax_amount, 2);
$grand_total_fmt = number_format($grand_total, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title> Nakofi Cafe - Invoice #<?= htmlspecialchars($linked_order_id) ?> </title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{
            --white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;
            --success:#28a745;--info:#17a2b8;--radius:20px;--trans:all .4s ease;--shadow:0 4px 20px rgba(0,0,0,0.04);
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;padding-top:100px;font-weight:700;}
        header{position:fixed;top:0;left:0;right:0;background:var(--white);padding:20px 48px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,.03);z-index:1000;}
        header .logo{height:70px;}
        header h1{font-size:32px;color:var(--charcoal);margin-left:32px;}
        .logout{background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);padding:10px 22px;border-radius:50px;font-weight:700;font-size:14px;cursor:pointer;transition:var(--trans);}
        .logout:hover{background:var(--charcoal);color:var(--white);}
        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 30px 5px 30px;  /* ← Added 80px bottom padding */
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .invoice-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:40px;padding-top:40px;}
        .company-info h1{font-size:42px;color:var(--charcoal);}
        .company-logo {
            height: 100px;        /* ← Changed to 150px as requested (adjust if needed) */
            width: auto;          /* Keeps aspect ratio */
            margin-bottom: 20px;
            filter: drop-shadow(0 6px 16px rgba(0,0,0,0.15));
            border-radius: 12px;  /* Optional: slight rounding for modern look */
        }
        .company-info p{margin:8px 0;color:var(--muted);font-size:16px;}
        .invoice-info{text-align:right;}
        .invoice-id{font-size:36px;font-weight:900;color:var(--charcoal);margin-bottom:12px;}
        .invoice-date{font-size:20px;color:var(--muted);}
        .supplier-details{background:#f8f9fa;padding:24px;border-radius:16px;margin-bottom:40px;}
        .supplier-details h3{font-size:24px;margin-bottom:12px;color:var(--charcoal);}
        .supplier-details p{margin:6px 0;font-size:17px;}
        table{width:100%;border-collapse:collapse;background:var(--card);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);margin-bottom:40px;}
        th{background:var(--charcoal);color:white;padding:18px;text-align:left;font-size:18px;}
        td{padding:18px;border-bottom:1px solid var(--border);font-size:17px;}
        tr:hover{background:#f8fdf9;}
        .text-right{text-align:right;}
        .total-row{font-weight:900;font-size:20px;background:#f0fdf4;}
        .amount{font-weight:700;color:var(--charcoal);}
        .print-btn {
            margin: 40px auto 60px;  /* ← Increased bottom margin from 60px to 100px */
            padding: 14px 40px;
            background: var(--charcoal);
            color: var(--white);
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            transition: var(--trans);
            display: block;
            width: fit-content;
            font-family:'Playfair Display',serif;
        }

        .print-btn:hover {
            background: #1a1d20;
            transform: translateY(-4px);
        }
        footer{text-align:center;padding:-10px;color:var(--muted);font-size:13px; margin-bottom:20px;}
        

        @media print {
            body * { visibility: hidden; }
            header, .back-btn, .print-btn, footer { display: none !important; }
            .container, .container * { visibility: visible; }
            .container { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; margin: 0; padding: 40px; }
            body { padding: 0; background: white; }
        }
    </style>
</head>
<body>

<header>
    <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="logout" onclick="handleLogout()"><i class="fas fa-arrow-left"></i> Back</button>
</header>

<div class="container">
    <div class="invoice-header">
        <div class="company-info">
            <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe Logo" class="company-logo">
            <h1>Nakofi Cafe</h1>
            <p>Premium Coffee & Supplies</p>
            <p>Kampung Bukit Changgang , Selangor</p>
            <p>support@nakofi.my</p>
        </div>
        <div class="invoice-info">
            <div class="invoice-id">#<?= htmlspecialchars($linked_order_id) ?></div>
            <div class="invoice-date">Date: <?= $formatted_date ?></div>
        </div>
    </div>

    <div class="supplier-details">
        <h3>Bill To:</h3>
        <p><strong><?= htmlspecialchars($supplier_name) ?></strong></p>
        <?php if (!empty($supplier_email)): ?>
            <p><?= htmlspecialchars($supplier_email) ?></p>
        <?php endif; ?>
        <p>Supplier ID: <?= htmlspecialchars($supID) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Item Description</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price (RM)</th>
                <th class="text-right">Subtotal (RM)</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($items as $item): ?>
                <tr>
                    <td data-label="No"><?= $no++ ?></td>
                    <td data-label="Item"><?= htmlspecialchars(ucwords($item['item_name'])) ?></td>
                    <td data-label="Qty" class="text-right"><?= (int)$item['quantity'] ?></td>
                    <td data-label="Unit Price" class="text-right">RM<?= number_format($item['unit_price'], 2) ?></td>
                    <td data-label="Subtotal" class="text-right amount">RM<?= number_format($item['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" class="text-right">Subtotal</td>
                <td class="text-right amount">RM<?= $subtotal_fmt ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="4" class="text-right">Tax (8% SST)</td>
                <td class="text-right amount">RM<?= $tax_fmt ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="4" class="text-right"><strong>Grand Total</strong></td>
                <td class="text-right amount"><strong>RM<?= $grand_total_fmt ?></strong></td>
            </tr>
        </tbody>
    </table>

    <div style="text-align:center;margin-top:40px;color:var(--muted);">
        <p>Thank you for your continued supply to Nakofi Cafe!</p>
        <p>This is a computer-generated invoice.</p>
    </div>

    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
<script>
function handleLogout() {
    document.body.style.opacity = '0.5';
    setTimeout(() => {
        location = 'invoice.php';
    }, 500);
}
</script>
</body>
</html>