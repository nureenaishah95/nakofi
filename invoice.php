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

// Build WHERE conditions
$whereClauses = ["so.supplier_id = ?", "LOWER(so.status) = 'completed'"];
$params = [$supID];
$types = "i";

if ($filter_date) {
    $whereClauses[] = "DATE(so.order_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($search_order_id) {
    $whereClauses[] = "so.linked_order_id LIKE ?";
    $params[] = "%" . $search_order_id . "%";
    $types .= "s";
}

$where = implode(" AND ", $whereClauses);

$query = "SELECT 
                so.linked_order_id,
                so.order_date,
                SUM(so.quantity) AS total_quantity,
                SUM(so.total) AS subtotal,
                s.name AS supplier_name
          FROM supplier_orders so
          JOIN supplier s ON so.supplier_id = s.supID
          WHERE $where
          GROUP BY so.linked_order_id
          ORDER BY so.order_date DESC";

// Prepare the statement with error handling
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$invoices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$base_img_url = "http://localhost/nakofi/asset/img/";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Nakofi Cafe - Invoice </title>
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
        body{
            font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);
            min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;
        }

        .sidebar{width:280px;background:var(--white);border-right:1px solid var(--border);position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);display:flex;flex-direction:column;z-index:10;}
        .logo{text-align:center;margin-bottom:40px;}
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}
        .menu-container{flex:1;overflow-y:auto;padding-right:8px;margin-bottom:20px;}
        .menu-container::-webkit-scrollbar{width:6px;}
        .menu-container::-webkit-scrollbar-track{background:transparent;}
        .menu-container::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px;}
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
        .page-title{font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;text-align:center;}

        .user-profile{position:relative;}
        .user-btn{display:flex;align-items:center;gap:10px;background:var(--white);border:2px solid var(--charcoal);padding:10px 24px;border-radius:50px;font-weight:700;cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{position:absolute;top:100%;right:0;background:var(--white);min-width:260px;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;opacity:0;visibility:hidden;transform:translateY(10px);transition:var(--trans);margin-top:12px;text-align:left;z-index:20;border:1px solid var(--border);}
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .name{font-size:18px;font-weight:700;color:var(--charcoal);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}

        .card{background:var(--card);border-radius:var(--radius);padding:48px 40px;box-shadow:var(--shadow);}
        .filter-bar{font-family: 'Playfair Display',serif;text-align:center;margin-bottom:40px;display:flex;justify-content:center;align-items:center;gap:16px;flex-wrap:wrap;}
        .filter-bar input[type="date"], .filter-bar input[type="text"]{ font-family: 'Playfair Display',serif;
            padding:12px 20px;border:2px solid var(--border);border-radius:12px;background:var(--white);font-weight:700;font-size:16px;color:var(--charcoal);
        }
        .filter-bar button{padding:12px 24px;background:var(--charcoal);color:var(--white);border:none;border-radius:12px;cursor:pointer;font-weight:700;font-size:16px;}
        .clear-filter{display:block;margin-top:12px;color:var(--charcoal);text-decoration:underline;font-size:14px;cursor:pointer;}

        .invoice-list{gap:24px;display:flex;flex-direction:column;}
        .invoice-item{background:var(--white);padding:32px;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.03);cursor:pointer;transition:var(--trans);display:flex;justify-content:space-between;align-items:center;}
        .invoice-item:hover{transform:translateY(-6px);box-shadow:0 12px 24px rgba(0,0,0,0.08);}
        .invoice-details .order-id {
            font-size: 22px;
            font-weight: 700;
            color: var(--charcoal);
            margin-bottom: 8px;
            font-family: 'Playfair Display',serif;
            padding: 8px 12px;
            border-radius: 12px;
            display: inline-block;
        }

        .invoice-details .date {
            font-size: 20px;
            color: #555;
            margin-bottom: 8px;
        }

        .invoice-details .supplier {
            font-size: 18px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .invoice-details .info {
            font-size: 17px;
            color: var(--muted);
        }
                .invoice-amount{text-align:right;}
        .invoice-amount .total{font-size:27px;color:var(--charcoal);}
        .invoice-amount .tax-note{font-size:15px;color:#28a745;margin-top:6px;}

        .no-results{text-align:center;color:var(--muted);font-size:20px;padding:60px 0;}

        footer{text-align:center;padding:60px 0 20px;color:var(--muted);font-size:14px;margin-left: 250px}

        @media (max-width:768px){
            .sidebar{width:100%;height:auto;position:relative;padding:20px;flex-direction:row;justify-content:center;gap:30px;}
            .main{margin-left:0;padding:30px 20px;}
            .header{flex-direction:column;gap:20px;align-items:stretch;text-align:center;}
            .page-title{font-size:30px;}
            .card{padding:36px 24px;}
            .filter-bar{flex-direction:column;}
            .invoice-item{flex-direction:column;text-align:center;gap:20px;}
            .invoice-amount{text-align:center;}
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <img src="http://localhost/nakofi/asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>
    <div class="menu-container">
        <div class="menu-item" onclick="location='supplier.php'"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div>
        <div class="menu-item" onclick="location='orders.php'"><i class="fas fa-clipboard-check"></i><span>List Orders</span></div>
        <div class="menu-item" onclick="location='delivery.php'"><i class="fas fa-truck"></i><span>Delivery Arrangement</span></div>
        <div class="menu-item" onclick="location='report.php'"><i class="fas fa-receipt"></i><span>Report</span></div>
        <div class="menu-item active"><i class="fas fa-file-invoice"></i><span>Invoice</span></div>
    </div>
    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="header">
        <div class="page-title">Supplier Invoices</div>
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
                <input type="text" name="search_order_id" placeholder="Search by Order ID" value="<?= htmlspecialchars($search_order_id) ?>">
                <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>">                
                <button type="submit">Search / Filter</button>
            </form>
            <?php if ($filter_date || $search_order_id): ?>
                <div class="clear-filter" onclick="window.location.href='invoice.php'">Clear All Filters</div>
            <?php endif; ?>
        </div>

        <div class="invoice-list">
            <?php if (count($invoices) > 0): ?>
                <?php foreach ($invoices as $index => $inv): ?>
                    <?php
                        $formattedDate = date('d/m/Y', strtotime($inv['order_date']));
                        $tax_amount = $inv['subtotal'] * 0.08;
                        $grand_total = $inv['subtotal'] + $tax_amount;
                    ?>
                    <div class="invoice-item" ondblclick="window.location.href='print_invoice.php?linked_order_id=<?= htmlspecialchars($inv['linked_order_id']) ?>'">
                    <div class="invoice-details">
                        <div class="order-id">#Order ID:<?= htmlspecialchars($inv['linked_order_id']) ?></div>
                        <div class="date"><?= $formattedDate ?></div>
                        <div class="supplier"><?= htmlspecialchars($inv['supplier_name']) ?></div>
                        <div class="info"><?= (int)$inv['total_quantity'] ?> items • Double-click to view invoice</div>
                    </div>
                    <div class="invoice-amount">
                        <div class="total">RM <?= number_format($grand_total, 2) ?></div>
                        <div class="tax-note">inclusive of 8% tax</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <?php if ($filter_date || $search_order_id): ?>
                        No completed orders found matching your search/filter.
                    <?php else: ?>
                        No completed supplier orders yet.<br>
                        <small>Orders marked as "completed" will appear here automatically.</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>

<script>
function logoutConfirm() {
    document.body.style.pointerEvents = 'none';
    document.querySelector('.main').style.opacity = '0.6';
    setTimeout(() => location = 'login.php', 800);
}
</script>
</body>
</html>