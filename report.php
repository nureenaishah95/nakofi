<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$supID = $_SESSION['user_id'];

// Fetch supplier details
$query = "SELECT name, email FROM supplier WHERE supID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $supID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$supplier = $result->fetch_assoc();
$supplier_name = $supplier['name'] ?? 'Unknown Supplier';
$supplier_email = $supplier['email'] ?? 'No email';
$display_name = strtoupper($supplier_name);
$stmt->close();

// Filters
$filter_date = !empty($_GET['filter_date']) ? $_GET['filter_date'] : '';
$search_order_id = !empty($_GET['search_order_id']) ? trim($_GET['search_order_id']) : '';

$whereClauses = ["supplier_id = ?"];
$params = [$supID];
$types = "i";

if ($filter_date) {
    $whereClauses[] = "DATE(order_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($search_order_id !== '') {
    $whereClauses[] = "linked_order_id LIKE ?";
    $params[] = "%$search_order_id%";
    $types .= "s";
}

$where = "WHERE " . implode(" AND ", $whereClauses);

$excluded_statuses = ['refunded', 'cancelled'];
$excluded_list = "'" . implode("', '", $excluded_statuses) . "'";

$query = "SELECT 
                linked_order_id,
                DATE(MIN(order_date)) AS order_day,
                SUM(CASE WHEN TRIM(LOWER(status)) NOT IN ($excluded_list) THEN quantity ELSE 0 END) AS total_quantity,
                SUM(CASE WHEN TRIM(LOWER(status)) NOT IN ($excluded_list) THEN total ELSE 0 END) AS subtotal_amount,
                (SUM(CASE WHEN TRIM(LOWER(status)) NOT IN ($excluded_list) THEN total ELSE 0 END) * 1.08) AS grand_total,
                GROUP_CONCAT(
                    CASE WHEN TRIM(LOWER(status)) NOT IN ($excluded_list)
                        THEN CONCAT(item_name, ' (Qty: ', quantity, ', RM ', FORMAT(total, 2), ')')
                    END SEPARATOR '\n'
                ) AS items_list,
                IF(
                    SUM(CASE WHEN TRIM(LOWER(status)) = 'completed' THEN 1 ELSE 0 END) =
                    SUM(CASE WHEN TRIM(LOWER(status)) NOT IN ($excluded_list) THEN 1 ELSE 0 END),
                    'Completed',
                    'Pending'
                ) AS status
          FROM supplier_orders 
          $where
          GROUP BY linked_order_id
          HAVING total_quantity > 0
          ORDER BY order_day DESC, linked_order_id DESC";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die("SQL Prepare Error: " . $conn->error . "<br><br>Full Query: <pre>$query</pre>");
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Nakofi Cafe - Report </title>
    <link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{
            --white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;
            --radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);
            --shadow:0 6px 20px rgba(0,0,0,.08);--shadow-hover:0 15px 35px rgba(0,0,0,.12);
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;}

        .sidebar{width:280px;background:var(--white);border-right:1px solid var(--border);position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);display:flex;flex-direction:column;z-index:10;}
        .logo{text-align:center;margin-bottom:40px;}
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}
        .menu-container{flex:1;overflow-y:auto;padding-right:8px;margin-bottom:20px;}
        .menu-item{display:flex;align-items:center;gap:16px;padding:14px 20px;color:var(--text);font-size:16px;font-weight:700;border-radius:16px;cursor:pointer;transition:var(--trans);margin-bottom:8px;}
        .menu-item i{font-size:22px;color:#D1D5DB;transition:var(--trans);}
        .menu-item:hover{background:#f1f5f9;}
        .menu-item:hover i{color:var(--charcoal);}
        .menu-item.active{background:var(--charcoal);color:var(--white);}
        .menu-item.active i{color:var(--white);}
        .logout-item{border-top:1px solid var(--border);padding-top:10px;}
        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}
        .logout-item .menu-item:hover{background:#fee2e2;color:#dc2626;}

        .main{margin-left:280px;width:calc(100% - 280px);padding:40px 60px;flex:1;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
        .page-title{font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;}

        .user-profile{position:relative;}
        .user-btn{display:flex;align-items:center;gap:10px;background:var(--white);border:2px solid var(--charcoal);padding:10px 24px;border-radius:50px;font-weight:700;cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{position:absolute;top:100%;right:0;background:var(--white);min-width:260px;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;opacity:0;visibility:hidden;transform:translateY(10px);transition:var(--trans);margin-top:12px;text-align:left;z-index:20;border:1px solid var(--border);}
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .name{font-size:18px;font-weight:700;color:var(--charcoal);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}

        .card{background:var(--card);border-radius:var(--radius);padding:40px;box-shadow:var(--shadow);}
        .filter-bar{ text-align:center;margin-bottom:40px;display:flex;justify-content:center;gap:20px;flex-wrap:wrap;align-items:center;}
        .filter-bar form{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;font-family:'Playfair Display';}
        .filter-bar input[type="date"], .filter-bar input[type="text"]{font-family: 'Playfair Display',serif;padding:12px 16px;border:2px solid var(--border);border-radius:12px;background:var(--white);font-weight:700;font-size:15px;color:var(--charcoal);}
        .filter-bar button{padding:12px 24px;background:var(--charcoal);color:white;border:none;border-radius:12px;cursor:pointer;font-weight:700;font-size:15px;}
        .clear-filter{display:block;margin-top:12px;color:var(--charcoal);text-decoration:underline;font-size:14px;cursor:pointer;}

        .report-list{display:grid;gap:20px;}
        .report-item{background:var(--white);padding:28px 32px;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.03);display:flex;justify-content:space-between;align-items:center;transition:var(--trans);cursor:pointer;}
        .report-item:hover{transform:translateY(-6px);box-shadow:0 12px 24px rgba(0,0,0,0.08);}
        .report-item .details{font-size:20px;color:var(--charcoal);}
        .report-item .order-id{font-weight:700;color:var(--charcoal);font-size:22px;}
        .report-item .date{color:var(--muted);font-size:15px;}
        .report-item .status{padding:8px 20px;border-radius:50px;font-size:14px;font-weight:700;}
        .status-Pending{background:#fff3cd;color:#856404;}
        .status-Completed{background:#d4edda;color:#155724;}

        .no-results{text-align:center;color:var(--muted);font-size:18px;margin:40px 0;}

       .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--white);
            padding: 40px 32px; /* Reduced horizontal padding */
            border-radius: var(--radius);
            box-shadow: var(--shadow-hover);
            text-align: left; /* Better for alignment */
            z-index: 1001;
            max-width: 90%; /* Never exceed screen width */
            width: 500px;
            max-height: 90vh; /* Prevent vertical overflow */
            overflow-y: auto; /* Scroll if content is too tall */
            font-size: 16px;
            line-height: 1.6;
        }

        .popup h2 {
            font-size: 26px;
            margin-bottom: 24px;
            color: var(--charcoal);
            text-align: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }

        .popup p {
            font-size: 16px;
            line-height: 1.8;
            color: var(--text);
            margin-bottom: 20px;
            white-space: pre-wrap;
            word-wrap: break-word; /* Crucial: breaks long words */
            overflow-wrap: break-word;
        }

        /* Make amount lines align nicely */
        .popup strong {
            display: inline-block;
            width: 140px; /* Fixed width for labels */
        }
        .popup-overlay{
            display:none;
            position:fixed;
            top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.5);
            backdrop-filter:blur(4px);
            z-index:1000;
        }
        .popup .warning{background:#fff3cd;color:#856404;padding:16px;border-radius:12px;margin:20px 0;font-size:16px;border-left:4px solid #ffc107;}
        .popup .buttons{display:flex;gap:16px;justify-content:center;}
        .popup button{padding:12px 32px;background:var(--charcoal);color:var(--white);border:none;border-radius:50px;font-weight:700;font-size:15px;cursor:pointer;transition:var(--trans);}
        .popup button:hover{background:#1a1d20;transform:translateY(-2px);}
        .popup button:disabled{background:#aaa;color:#666;cursor:not-allowed;opacity:0.6;}
        .popup button:nth-child(2){background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);}
        .popup button:nth-child(2):hover{background:var(--charcoal);color:var(--white);}

        footer{text-align:center;padding:60px 0 20px;color:var(--muted);font-size:14px;margin-left:250px;}

        @media (max-width:768px){
            .sidebar{width:100%;height:auto;position:relative;padding:20px;flex-direction:row;justify-content:center;gap:30px;}
            .main{margin-left:0;padding:30px 20px;}
            .header{flex-direction:column;gap:20px;align-items:stretch;text-align:center;}
            .page-title{font-size:30px;}
            .report-item{flex-direction:column;text-align:center;gap:16px;}
            .filter-bar{flex-direction:column;}
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <img src="http://localhost/nakofi/asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>
    <div class="menu-container">
        <div class="menu-item" onclick="location='supplier.php'"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div>
        <div class="menu-item" onclick="location='orders.php'"><i class="fas fa-clipboard-check"></i><span>List Orders</span></div>
        <div class="menu-item" onclick="location='delivery.php'"><i class="fas fa-truck"></i><span>Delivery Arrangement</span></div>
        <div class="menu-item active"><i class="fas fa-receipt"></i><span>Report</span></div>
        <div class="menu-item" onclick="location='invoice.php'"><i class="fas fa-file-invoice"></i><span>Invoice</span></div>
    </div>
    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </div>
    </div>
</div>

<div class="main">
    <div class="header">
        <div class="page-title">Supplier Orders Report</div>
        <div class="user-profile">
            <button class="user-btn">
                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($display_name) ?>
            </button>
            <div class="user-tooltip">
                <div class="name">ID: <?= htmlspecialchars($supID) ?></div>
                <div class="detail"><?= htmlspecialchars($display_name) ?></div>
                <div class="detail"><?= htmlspecialchars($supplier_email) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="filter-bar">
            <form method="GET">
                <input type="text" name="search_order_id" placeholder="Search by Order ID" value="<?= htmlspecialchars($search_order_id)?>">
                <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>">
                <button type="submit">Search & Filter</button>
            </form>
            <?php if ($filter_date || $search_order_id !== ''): ?>
                <div class="clear-filter" onclick="window.location.href='report.php'">Clear All Filters</div>
            <?php endif; ?>
        </div>

        <div class="report-list">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $index => $order): ?>
                    <?php
                        $orderId = $order['linked_order_id'] ?? 'N/A';
                        $formattedDate = $order['order_day'] ? date('d/m/Y', strtotime($order['order_day'])) : 'N/A';

                        $subtotal = $order['subtotal_amount'] ?? 0;
                        $eight_percent = $subtotal * 0.08;
                        $grand_total = $order['grand_total'] ?? 0;

                        $popupText = "Linked Order ID: " . htmlspecialchars($orderId) . "\n" .
                                    "Order Date: {$formattedDate}\n\n" .
                                    "Items:\n" . ($order['items_list'] ?? 'No items') . "\n\n" .
                                    "Total Quantity: " . ($order['total_quantity'] ?? 0) . "\n\n" .
                                    "Subtotal:          RM " . number_format($subtotal, 2) . "\n" .
                                    "+ 8% Amount:     RM " . number_format($eight_percent, 2) . "\n" .
                                    "─────────────────────────────────\n" .
                                    "Grand Total:       RM " . number_format($grand_total, 2) . "\n\n" .
                                    "Status: " . ($order['status'] ?? 'Pending');

                        $isCompleted = strtolower($order['status'] ?? '') === 'completed';
                    ?>
                    <div class="report-item" 
                         ondblclick="showPopup(<?= htmlspecialchars(json_encode($popupText), ENT_QUOTES) ?>, <?= $isCompleted ? 'true' : 'false' ?>, '<?= htmlspecialchars($orderId) ?>')">
                        <div class="details">
                            <div class="order-id">Order ID: <?= htmlspecialchars($orderId) ?></div>
                            <div class="date">Date: <?= $formattedDate ?></div>
                            <small style="display:block;font-size:15px;margin-top:8px;color:#555;">
                                <?= $order['total_quantity'] ?? 0 ?> items • RM <?= number_format($order['grand_total'] ?? 0, 2) ?>
                            </small>
                        </div>
                        <div class="status status-<?= htmlspecialchars($order['status'] ?? 'Pending') ?>">
                            <?= htmlspecialchars($order['status'] ?? 'Pending') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <?php if ($search_order_id || $filter_date): ?>
                        No orders found matching your search.
                    <?php else: ?>
                        No supplier orders recorded yet.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>

<!-- Popup -->
<div id="popup-overlay" class="popup-overlay" onclick="hidePopup()"></div>
<div id="popup" class="popup">
    <h2>Supplier Order Details</h2>
    <p id="popupText"></p>
    <div id="warningMessage" class="warning" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i> 
        This order is still Pending. Printing is only allowed when the status is <strong>Completed</strong>.
    </div>
    <div class="buttons">
        <button id="printBtn" onclick="printReport()">Print Report</button>
        <button onclick="hidePopup()">Close</button>
    </div>
</div>

<script>
    let currentLinkedOrderId = null;

    function showPopup(text, isCompleted, linkedOrderId) {
        document.getElementById('popupText').textContent = text;
        currentLinkedOrderId = linkedOrderId;

        const printBtn = document.getElementById('printBtn');
        const warning = document.getElementById('warningMessage');

        if (isCompleted) {
            printBtn.disabled = false;
            warning.style.display = 'none';
        } else {
            printBtn.disabled = true;
            warning.style.display = 'block';
        }

        document.getElementById('popup').style.display = 'block';
        document.getElementById('popup-overlay').style.display = 'block';
    }

    function hidePopup() {
        document.getElementById('popup').style.display = 'none';
        document.getElementById('popup-overlay').style.display = 'none';
        currentLinkedOrderId = null;
    }

    function printReport() {
        if (currentLinkedOrderId) {
            window.location.href = 'complete.php?linked_order_id=' + encodeURIComponent(currentLinkedOrderId);
        }
    }

    document.addEventListener('keydown', e => { 
        if (e.key === 'Escape') hidePopup(); 
    });

    function logoutConfirm() {
        document.body.style.pointerEvents = 'none';
        document.querySelector('.main').style.opacity = '0.6';
        setTimeout(() => location = 'login.php', 800);
    }
</script>
</body>
</html>