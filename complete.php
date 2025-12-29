<?php
session_start();
require_once 'config.php';

// Optional: Require login (uncomment if only suppliers should access)
// if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$linked_order_id = $_GET['linked_order_id'] ?? null;

if (!$linked_order_id) {
    die('<h2 style="text-align:center;margin-top:100px;color:red;">Invalid or missing Order ID.</h2>');
}

// Fetch all items for this linked_order_id, excluding refunded/cancelled
$query = "SELECT item_name, quantity, total, order_date, status
          FROM supplier_orders 
          WHERE linked_order_id = ? 
            AND TRIM(LOWER(status)) NOT IN ('refunded', 'cancelled')
          ORDER BY id ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $linked_order_id);  // linked_order_id is likely string/varchar
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($items)) {
    die('<h2 style="text-align:center;margin-top:100px;color:#666;">No items found for Order ID: ' . htmlspecialchars($linked_order_id) . '</h2>');
}

// Get the earliest order date for display
$order_date = $items[0]['order_date'];
$formatted_date = date('d/m/Y', strtotime($order_date));

// Calculate subtotal (only non-refunded items)
$subtotal = array_sum(array_column($items, 'total'));

// 8% tax
$tax_rate = 0.08;
$tax_amount = $subtotal * $tax_rate;
$grand_total = $subtotal + $tax_amount;

$base_img_url = "http://localhost/nakofi/asset/img/";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - Purchase Order Report #<?= htmlspecialchars($linked_order_id) ?></title>
    <link rel="icon" href="<?= $base_img_url ?>logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{
            --white: #FFFFFF;--charcoal: #212529;--bg: #F9FAFB;--card: #FFFFFF;--border: #E5E7EB;
            --text: #111827;--text-muted: #6B7280;--shadow: 0 12px 28px rgba(0,0,0,0.05), 0 6px 12px rgba(0,0,0,0.03);
            --radius: 20px;--trans: all 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);
            line-height:1.7;min-height:100vh;padding-top:100px;overflow-x:hidden;font-weight:700;
        }
        header{
            position:fixed;top:0;left:0;right:0;background:var(--white);padding:20px 48px;
            display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);
            box-shadow:0 2px 10px rgba(0,0,0,0.03);z-index:1000;gap:16px;
        }
        header .logo{height:70px;width:auto;object-fit:contain;}
        header h1{font-size:32px;color:var(--charcoal);letter-spacing:-1.2px;}
        .back-btn{
            background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);
            padding:10px 22px;border-radius:50px;font-weight:700;font-size:14px;cursor:pointer;
            transition:var(--trans);display:flex;gap:8px;align-items:center;
        }
        .back-btn:hover{background:var(--charcoal);color:var(--white);transform:translateY(-2px);}

        .container{max-width:900px;margin:0 auto;padding:30px 25px;}
        .title{text-align:center;font-size:36px;color:var(--charcoal);margin-bottom:20px;position:relative;}
        .title::after{content:'';width:80px;height:2px;background:var(--charcoal);opacity:0.2;display:block;margin:20px auto 0;}
        .order-info{text-align:center;font-size:20px;color:#555;margin-bottom:40px;}
        .order-id{font-weight:900;font-size:24px;color:var(--charcoal);display:block;margin-bottom:8px;}

        .card{
            background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
            padding:48px 40px;box-shadow:var(--shadow);position:relative;overflow:hidden;
        }
        table{
            width:100%;border-collapse:separate;border-spacing:0 20px;margin-bottom:40px;
        }
        thead th{
            text-align:left;padding:16px 20px;background:var(--white);color:var(--text-muted);
            font-size:14px;text-transform:uppercase;letter-spacing:1.2px;
        }
        tbody tr{background:var(--white);box-shadow:0 4px 12px rgba(0,0,0,0.03);}
        td{padding:20px;font-size:19px;vertical-align:middle;}
        td img{
            width:80px;height:80px;object-fit:cover;border-radius:12px;margin-right:20px;
            border:1px solid var(--border);vertical-align:middle;
        }
        .item-name{font-size:20px;color:var(--charcoal);}

        .totals {
            text-align:right;font-size:20px;margin-top:30px;
        }
        .totals div {
            margin-bottom:12px;
        }
        .subtotal-line { font-size:24px; color:#555; }
        .tax-line { font-size:22px; color:#666; }
        .grand-total-line { 
            font-size:30px; 
            color:var(--charcoal); 
            border-top:2px solid var(--border); 
            padding-top:12px; 
            margin-top:20px;
        }

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

        footer{text-align:center;padding:60px 20px 40px;color:var(--text-muted);font-size:13px;}

        @media print{
            header, .print-btn, footer, .back-btn{display:none !important;}
            body{padding:0;background:#fff;}
            .container{padding:40px 20px;}
            .card{border:none;box-shadow:none;border-radius:0;}
            .complete-stamp{display:block !important;}
            .totals { font-size:18px; }
            .grand-total-line { font-size:28px; }
        }

        @media(max-width:768px){
            .container{padding:20px;}.card{padding:36px 24px;}
            td img{width:60px;height:60px;margin-right:12px;}
            table, thead, tbody, th, td, tr{display:block;}
            thead tr{position:absolute;top:-9999px;left:-9999px;}
            td:before{
                content:attr(data-label);font-weight:700;color:var(--text-muted);
                text-transform:uppercase;font-size:13px;display:block;margin-bottom:8px;
            }
            .totals { font-size:18px; text-align:center; }
            .grand-total-line { font-size:26px; }
        }
    </style>
</head>
<body>

<header>
    <img src="<?= $base_img_url ?>logo.png" alt="Nakofi Cafe Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="back-btn" onclick="goBack()"><i class="fas fa-arrow-left"></i> Back</button>
</header>

<div class="container">
    <h2 class="title">Purchase Order Report</h2>
    <div class="order-info">
        <span class="order-id">Order ID: <?= htmlspecialchars($linked_order_id) ?></span>
        <div>Order Date: <?= htmlspecialchars($formatted_date) ?></div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                        $item_lower = strtolower($item['item_name']);

                        if (strpos($item_lower, 'monin strawberry') !== false || strpos($item_lower, 'strawberry') !== false) {
                            $img_src = $base_img_url . "syrup.jpg";
                        } elseif (strpos($item_lower, 'whipped cream') !== false || strpos($item_lower, 'cream') !== false) {
                            $img_src = $base_img_url . "cream.png";
                        } elseif (strpos($item_lower, 'matcha') !== false || strpos($item_lower, 'niko neko') !== false) {
                            $img_src = $base_img_url . "niko.jpg";
                        } elseif (strpos($item_lower, 'coffee bean') !== false || strpos($item_lower, 'coffee') !== false) {
                            $img_src = $base_img_url . "coffee.jpg";
                        } elseif (strpos($item_lower, 'milk') !== false) {
                            $img_src = $base_img_url . "milk.jpg";
                        } else {
                            $img_src = $base_img_url . "coffee.jpg";
                        }
                    ?>
                    <tr>
                        <td data-label="Item">
                            <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                            <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                        </td>
                        <td data-label="Quantity"><?= number_format($item['quantity']) ?></td>
                        <td data-label="Total">RM <?= number_format($item['total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="subtotal-line">Subtotal: RM <?= number_format($subtotal, 2) ?></div>
            <div class="tax-line">Tax (8%): RM <?= number_format($tax_amount, 2) ?></div>
            <div class="grand-total-line">Grand Total: RM <?= number_format($grand_total, 2) ?></div>
        </div>

        <button class="print-btn" onclick="handlePrint()">Print Report</button>
        <div class="complete-stamp">COMPLETED</div>
    </div>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>

<script>
function handlePrint() {
    const stamp = document.querySelector('.complete-stamp');
    stamp.style.display = 'block';
    setTimeout(() => window.print(), 100);
}

// Auto-print if accessed with ?print=1
if (new URLSearchParams(window.location.search).get('print') === '1') {
    setTimeout(handlePrint, 800);
}

function goBack() {
    document.body.style.opacity = '0.5';
    setTimeout(() => {
        window.location.href = 'report.php';
    }, 400);
}
</script>

</body>
</html>