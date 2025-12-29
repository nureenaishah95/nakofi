<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid order ID.");
}

$orders_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT name, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$display_name = strtoupper($user['name'] ?? 'User');

// Fetch order details
$sql = "
    SELECT 
        o.orders_id,
        o.order_date,
        o.total_amount,
        o.payment_status,
        o.item_name,
        o.quantity,
        o.total AS item_total
    FROM orders o
    WHERE o.orders_id = ?
    ORDER BY o.Id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $orders_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Order not found or no items in this order.");
}

$order_items = [];
$order_date = '';
$subtotal = 0;
$payment_status = '';

while ($row = $result->fetch_assoc()) {
    $order_items[] = $row;
    $order_date = $row['order_date'];
    $subtotal += $row['item_total'];
    $payment_status = $row['payment_status'];
}

$tax = round($subtotal * 0.08, 2);
$grand_total = $subtotal + $tax;

$formatted_date = date('d M Y', strtotime($order_date));
$formatted_time = date('h:i A', strtotime($order_date));
$formatted_datetime = $formatted_date . ' at ' . $formatted_time;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - Order #<?php echo $orders_id; ?></title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --white:#fff; --charcoal:#212529; --bg:#F9FAFB; --card:#fff; --border:#E5E7EB;
            --text:#111827; --muted:#6B7280; --radius:20px; --success:#28a745;
            --shadow:0 6px 20px rgba(0,0,0,.08); --trans:all .4s cubic-bezier(.16,1,.3,1);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Playfair Display',serif;
            background:var(--bg);
            color:var(--text);
            min-height:100vh;
            display:flex;
            overflow-x:hidden;
        }

        /* Sidebar */
        .sidebar {
            width:280px;
            background:var(--white);
            border-right:1px solid var(--border);
            position:fixed;
            left:0; top:0; bottom:0;
            padding:40px 20px;
            box-shadow:var(--shadow);
            display:flex;
            flex-direction:column;
            z-index:10;
        }
        .logo { text-align:center; margin-bottom:40px; }
        .logo img { height:80px; filter:grayscale(100%) opacity(0.8); }
        .logo h1 { font-size:28px; color:var(--charcoal); margin-top:16px; }

        .menu-container { flex:1; overflow-y:auto; padding-right:8px; margin-bottom:20px; }
        .menu-item {
            display:flex; align-items:center; gap:16px;
            padding:14px 20px; color:var(--text);
            font-size:16px; font-weight:700;
            border-radius:16px; cursor:pointer;
            transition:var(--trans); margin-bottom:8px;
        }
        .menu-item i { font-size:22px; color:#D1D5DB; }
        .menu-item:hover { background:#f1f5f9; }
        .menu-item.active { background:var(--charcoal); color:var(--white); }
        .menu-item.active i { color:var(--white); }

        .logout-item { flex-shrink:0; padding-top:20px; border-top:1px solid var(--border); }
        .logout-item .menu-item { color:#dc2626; }
        .logout-item .menu-item i { color:#dc2626; }
        .logout-item .menu-item:hover { background:#fee2e2; }

        /* Main */
        .main {
            margin-left:280px;
            width:calc(100% - 280px);
            padding:40px 60px;
            display:flex;
            flex-direction:column;
            min-height:100vh;
        }

        .page-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:30px;
        }
        .page-title {
            font-size:36px;
            color:var(--charcoal);
            letter-spacing:-1.2px;
        }

        .user-profile { position:relative; }
        .user-btn {
            display:flex; align-items:center; gap:10px;
            background:var(--white); border:2px solid var(--charcoal);
            padding:10px 24px; border-radius:50px;
            font-weight:700; cursor:pointer;
            transition:var(--trans); box-shadow:var(--shadow);
        }
        .user-btn i { font-size:23px; color:var(--charcoal); }
        .user-btn:hover { background:var(--charcoal); color:var(--white); }
        .user-btn:hover i { color:var(--white); }

        .detail-content {
            max-width:1000px;
            width:100%;
            margin:0 auto;
            flex:1;
        }

        /* Actions */
        .actions {
            margin:40px 0;
            display:flex;
            justify-content:center;
            gap:20px;
            flex-wrap:wrap;
        }
        .print-btn {
            background:var(--success);
            color:white;
            padding:12px 30px;
            border:none;
            border-radius:50px;
            font-size:16px; font-weight:700;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            gap:10px;
            transition:var(--trans);
        }
        .print-btn:hover { background:#218838; }

        .back-btn {
            background:var(--charcoal);
            color:var(--white);
            padding:12px 30px;
            border:none;
            border-radius:50px;
            font-size:16px; font-weight:700;
            display:inline-flex;
            align-items:center;
            gap:10px;
            text-decoration:none;
            transition:var(--trans);
        }
        .back-btn:hover { background:#6B7280; }

        /* On-screen receipt preview */
        .receipt-preview {
            background:white;
            max-width:400px;
            margin:20px auto;
            padding:30px;
            border:1px dashed #ccc;
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            font-family:'Roboto Mono',monospace;
            font-size:14px;
            line-height:1.6;
        }

        /* Shared receipt styles */
        .receipt { width:100%; text-align:center; }
        .receipt-header { margin-bottom:30px; }
        .receipt-header img { max-width:100px; height:auto; margin-bottom:15px; filter:grayscale(100%) opacity(0.8); }
        .receipt-header h1 { font-size:24px; font-weight:bold; margin:10px 0; font-family:'Playfair Display',serif; }
        .receipt-header p { font-size:14px; margin:5px 0; color:var(--muted); }

        .divider { border-top:1px dashed #999; margin:25px 0; }

        .item-row { display:flex; justify-content:space-between; margin:12px 0; font-size:15px; }
        .item-name { flex:1; text-align:left; }
        .qty-price { text-align:right; }

        .summary-row { display:flex; justify-content:space-between; margin:8px 0; font-size:15px; }
        .summary-row.total { font-weight:bold; font-size:18px; margin:25px 0; }

        .thank-you { margin-top:30px; font-size:13px; color:var(--muted); }

        footer {
            text-align:center;
            padding:30px 20px;
            color:var(--muted);
            font-size:14px;
            margin-top:auto;
        }

        /* Hide printable version on screen */
        #printable-receipt { display: none; }

        /* ────────────────────────────────────────────────
           PRINT STYLES - Smaller & more compact version
           ──────────────────────────────────────────────── */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            body, html {
                background: white !important;
                margin: 0;
                padding: 0;
                height: 297mm;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .sidebar, .page-header, .user-profile, .actions, .receipt-preview, footer, .main {
                display: none !important;
            }

            #printable-receipt {
                display: block !important;
                width: 160mm;
                max-width: 120mm;
                min-height: 220mm;
                margin: 0 auto;
                padding: 28mm 22mm 35mm 22mm;
                background: #f9f9f9;
                box-shadow: 0 0 18px rgba(0,0,0,0.08);
                font-family: 'Roboto Mono', monospace;
                font-size: 15.5px;
                line-height: 1.55;
                color: black;
                text-align: center;
                margin-top: 30px;
            }

            #printable-receipt .receipt-header img {
                max-width: 110px;
                margin-bottom: 16px;
            }

            #printable-receipt .receipt-header h1 {
                font-size: 28px;
                margin: 12px 0 8px 0;
            }

            #printable-receipt .receipt-header p {
                font-size: 15px;
                margin: 4px 0;
                color: #444;
            }

            #printable-receipt .divider {
                border-top: 1px dashed #777;
                margin: 24px 0;
            }

            #printable-receipt .item-row {
                font-size: 15.5px;
                margin: 10px 0;
                line-height: 1.4;
            }

            #printable-receipt .summary-row {
                font-size: 15.5px;
                margin: 9px 0;
            }

            #printable-receipt .summary-row.total {
                font-size: 19px;
                font-weight: bold;
                margin: 28px 0 32px 0;
                border-top: 1px solid #444;
                padding-top: 10px;
            }

            #printable-receipt .thank-you {
                font-size: 14px;
                color: #555;
                margin-top: 45px;
                line-height: 1.45;
            }

            #printable-receipt .thank-you p {
                margin: 5px 0;
            }
        }

        @media (max-width:992px) {
            .main { margin-left:0; padding:30px 20px; }
            .receipt-preview { max-width:100%; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>
    <div class="menu-container">
        <div class="menu-item" onclick="location='staff_leader.php'"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div>
        <div class="menu-item" onclick="location='stock_take.php'"><i class="fas fa-clipboard-list"></i><span>Stock Take</span></div>
        <div class="menu-item" onclick="location='stock_count.php'"><i class="fas fa-boxes-stacked"></i><span>Stock Count</span></div>
        <div class="menu-item" onclick="location='stock_drafts.php'"><i class="fas fa-file-circle-plus"></i><span>Stock Take Drafts</span></div>
        <div class="menu-item" onclick="location='payment.php'"><i class="fas fa-credit-card"></i><span>Payment</span></div>
        <div class="menu-item" onclick="location='track.php'"><i class="fas fa-truck"></i><span>Delivery Tracking</span></div>
        <div class="menu-item" onclick="location='reporting.php'"><i class="fas fa-chart-line"></i><span>Inventory Audit Report</span></div>
        <div class="menu-item" onclick="location='register.php'"><i class="fas fa-users-cog"></i><span>User Register</span></div>
    </div>
    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="page-header">
        <div class="page-title">Order Details</div>
        <div class="user-profile">
            <button class="user-btn"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($display_name); ?></button>
        </div>
    </div>

    <div class="detail-content">
        <!-- On-screen preview -->
        <div class="receipt-preview">
            <div class="receipt">
                <div class="receipt-header">
                    <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe">
                    <h1>Nakofi Cafe</h1>
                    <p>Kampung Bukit Changgang, 42700 Banting Selangor</p>
                    <p>Tel: 03-1234 5678</p>
                    <p>Order #: <?php echo $orders_id; ?></p>
                    <p>Date: <?php echo $formatted_datetime; ?></p>
                </div>
                <div class="divider"></div>

                <?php foreach ($order_items as $item): ?>
                <div class="item-row">
                    <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                    <div class="qty-price">
                        <?php echo $item['quantity']; ?> x RM<?php echo number_format($item['item_total'] / $item['quantity'], 2); ?><br>
                        <strong>RM<?php echo number_format($item['item_total'], 2); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="divider"></div>
                <div class="summary-row">
                    <div>Subtotal</div>
                    <div>RM<?php echo number_format($subtotal, 2); ?></div>
                </div>
                <div class="summary-row">
                    <div>SST (8%)</div>
                    <div>RM<?php echo number_format($tax, 2); ?></div>
                </div>
                <div class="summary-row total">
                    <div>TOTAL</div>
                    <div>RM<?php echo number_format($grand_total, 2); ?></div>
                </div>
                <div class="summary-row">
                    <div>Payment Status</div>
                    <div><?php echo strtoupper($payment_status); ?></div>
                </div>

                <div class="thank-you">
                    <p>Thank you for your purchase!</p>
                    <p>Come again soon ♡</p>
                </div>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="actions">
            <a href="reporting.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
        </div>
    </div>

    <footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</div>

<!-- Printable Receipt (Smaller version) -->
<div class="receipt" id="printable-receipt">
    <div class="receipt-header">
        <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
        <p>Kampung Bukit Changgang, 42700 Banting Selangor</p>
        <p>Tel: 03-1234 5678</p>
        <p>Order #: <?php echo $orders_id; ?></p>
        <p>Date: <?php echo $formatted_datetime; ?></p>
    </div>
    <div class="divider"></div>

    <?php foreach ($order_items as $item): ?>
    <div class="item-row">
        <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
        <div class="qty-price">
            <?php echo $item['quantity']; ?> x RM<?php echo number_format($item['item_total'] / $item['quantity'], 2); ?><br>
            <strong>RM<?php echo number_format($item['item_total'], 2); ?></strong>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="divider"></div>
    <div class="summary-row">
        <div>Subtotal</div>
        <div>RM<?php echo number_format($subtotal, 2); ?></div>
    </div>
    <div class="summary-row">
        <div>SST (8%)</div>
        <div>RM<?php echo number_format($tax, 2); ?></div>
    </div>
    <div class="summary-row total">
        <div>TOTAL</div>
        <div>RM<?php echo number_format($grand_total, 2); ?></div>
    </div>
    <div class="summary-row">
        <div>Payment Status</div>
        <div><?php echo strtoupper($payment_status); ?></div>
    </div>

    <div class="thank-you">
        <p>Thank you for your purchase!</p>
        <p>Come again soon ♡</p>
    </div>
</div>

<script>
function logoutConfirm() {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
    setTimeout(() => location.href = 'login.php', 800);
}
</script>

</body>
</html>