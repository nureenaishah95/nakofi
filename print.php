<?php
session_start();
require_once 'config.php';

// Check connection
if (!$conn || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'));
}

// Get parameters
$order_date = $_GET['date'] ?? null;
$passed_invoice = $_GET['invoice'] ?? null;

if (!$order_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $order_date)) {
    die('<h2 style="text-align:center;margin-top:100px;color:red;">Invalid or missing order date.</h2>');
}

// Fetch current logged-in staff ID (user ID)
$staff_id = "Unknown";
if (isset($_SESSION['user_id'])) {
    // Directly use the session user_id as staff_id (most reliable)
    $staff_id = $_SESSION['user_id'];
    
    // Optional safety check: verify it exists in staff table
    $stmt = $conn->prepare("SELECT id FROM staff WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($fetched_id);
        if ($stmt->fetch()) {
            $staff_id = $fetched_id;
        }
        $stmt->close();
    }
}

// Generate or use invoice number (starts from #1001)
$counter_file = 'invoice_counter.txt';
if (!file_exists($counter_file)) {
    file_put_contents($counter_file, '1000');
}
$current_counter = (int)file_get_contents($counter_file);

if ($passed_invoice && substr($passed_invoice, 1) > $current_counter) {
    $invoice_number = $passed_invoice;
    $current_counter = (int)substr($passed_invoice, 1);
} else {
    $current_counter++;
    $invoice_number = '#' . $current_counter;
}
file_put_contents($counter_file, $current_counter);

// Fetch shipped items for this date
$query = "SELECT item_name, quantity, total 
          FROM supplier_orders 
          WHERE DATE(order_date) = ? AND status = 'Shipped'
          ORDER BY id ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Prepare Error: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("s", $order_date);
if (!$stmt->execute()) {
    die("Execute Error: " . htmlspecialchars($stmt->error));
}
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($items)) {
    die('<h2 style="text-align:center;margin-top:100px;color:#666;">No shipped items found for this date.</h2>');
}

$grand_total = array_sum(array_column($items, 'total'));
$formatted_date = date('d/m/Y', strtotime($order_date));
$base_img_url = "<?= BASE_URL ?>asset/img/";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice_number) ?> - Nakofi Cafe</title>
    <link rel="icon" href="<?= $base_img_url ?>logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{--white:#FFFFFF;--charcoal:#212529;--bg:#F9FAFB;--card:#FFFFFF;--border:#E5E7EB;--text:#111827;--text-muted:#6B7280;--shadow:0 12px 28px rgba(0,0,0,0.05),0 6px 12px rgba(0,0,0,0.03);--radius:20px;--trans:all 0.4s cubic-bezier(0.16,1,0.3,1);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);line-height:1.7;min-height:100vh;padding-top:100px;overflow-x:hidden;font-weight:700;}
        header{position:fixed;top:0;left:0;right:0;background:var(--white);padding:20px 48px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,0.03);z-index:1000;gap:16px;}
        header .logo{height:70px;width:auto;object-fit:contain;}
        header h1{font-size:32px;color:var(--charcoal);margin:0;letter-spacing:-1.2px;}
        .back-btn{background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);padding:10px 22px;border-radius:50px;font-weight:700;font-size:14px;cursor:pointer;transition:var(--trans);display:flex;align-items:center;gap:8px;}
        .back-btn:hover{background:var(--charcoal);color:var(--white);transform:translateY(-2px);}
        .container{max-width:900px;margin:0 auto;padding:30px 25px;}
        .title{text-align:center;font-size:36px;color:var(--charcoal);margin:0 0 50px;position:relative;}
        .title::after{content:'';width:80px;height:2px;background:var(--charcoal);opacity:0.2;display:block;margin:20px auto 0;}
        .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:48px 40px;box-shadow:var(--shadow);position:relative;overflow:hidden;}
        .invoice-details{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:48px;font-size:18px;}
        .invoice-details div{display:flex;flex-direction:column;gap:8px;}
        .invoice-details strong{color:var(--charcoal);font-size:20px;}
        .invoice-details span{color:var(--text-muted);font-size:15px;text-transform:uppercase;letter-spacing:1px;}
        table{width:100%;border-collapse:separate;border-spacing:0 16px;margin-bottom:40px;}
        thead th{text-align:left;padding:16px 20px;background:var(--white);color:var(--text-muted);font-size:14px;text-transform:uppercase;letter-spacing:1.2px;}
        tbody tr{background:var(--white);box-shadow:0 4px 12px rgba(0,0,0,0.03);}
        td{padding:20px;font-size:19px;}
        .unit-price,.line-total{text-align:right;}
        .total{text-align:right;font-size:28px;color:var(--charcoal);margin-top:20px;}
        .print-btn{margin:40px auto 0;padding:14px 40px;background:var(--charcoal);color:var(--white);border:none;border-radius:50px;font-weight:700;font-size:16px;cursor:pointer;transition:var(--trans);display:block;width:fit-content;}
        .print-btn:hover{background:#1a1d20;transform:translateY(-4px);}
        .paid-stamp{display:none;position:absolute;top:45%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:120px;color:rgba(220,53,69,0.15);font-weight:900;text-transform:uppercase;pointer-events:none;z-index:1;}
        footer{text-align:center;padding:60px 20px 40px;color:var(--text-muted);font-size:13px;}
        @media print{header,.print-btn,footer,.back-btn{display:none !important;}body{padding:0;background:#fff;}.container{padding:40px 20px;}.card{border:none;box-shadow:none;border-radius:0;}.paid-stamp{display:block !important;}}
        @media(max-width:768px){.container{padding:20px;}.card{padding:36px 24px;}.invoice-details{grid-template-columns:1fr;gap:24px;}
            table,thead,tbody,th,td,tr{display:block;}thead tr{position:absolute;top:-9999px;left:-9999px;}
            td:before{content:attr(data-label);font-weight:700;color:var(--text-muted);text-transform:uppercase;font-size:13px;display:block;margin-bottom:8px;}
            .unit-price,.line-total,.total{text-align:left;}}
    </style>
</head>
<body>

<header>
    <img src="<?= $base_img_url ?>logo.png" alt="Nakofi Cafe Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="back-btn" onclick="window.location.href='invoice.php'">
        <i class="fas fa-arrow-left"></i> Back
    </button>
</header>

<div class="container">
    <h2 class="title">Invoice</h2>

    <div class="card">
        <div class="invoice-details">
            <div>
                <span>Invoice #</span>
                <strong><?= htmlspecialchars($invoice_number) ?></strong>
            </div>
            <div>
                <span>Date</span>
                <strong><?= htmlspecialchars($formatted_date) ?></strong>
            </div>
            <div>
                <span>Billed To</span>
                <strong>Supplier</strong>
            </div>
            <div>
                <span>Processed By (Supplier Staff ID)</span>
                <strong><?= htmlspecialchars($staff_id) ?></strong>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align:center;">Quantity</th>
                    <th class="unit-price">Unit Price (RM)</th>
                    <th class="line-total">Total (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php 
                        $unit_price = $item['quantity'] > 0 ? $item['total'] / $item['quantity'] : 0;
                    ?>
                    <tr>
                        <td data-label="Item"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td data-label="Quantity" style="text-align:center;"><?= number_format($item['quantity']) ?></td>
                        <td data-label="Unit Price" class="unit-price"><?= number_format($unit_price, 2) ?></td>
                        <td data-label="Total" class="line-total"><?= number_format($item['total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total">Total: RM <?= number_format($grand_total, 2) ?></div>

        <button class="print-btn" onclick="handlePrint()">Print Invoice</button>
        <div class="paid-stamp">PAID</div>
    </div>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>

<script>
function handlePrint() {
    const stamp = document.querySelector('.paid-stamp');
    stamp.style.display = 'block';
    setTimeout(() => window.print(), 100);
}
</script>

</body>
</html>