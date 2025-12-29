<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ================= USER =================
$stmt = $conn->prepare("SELECT name, role, email FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$name = strtoupper($user['name'] ?? 'User');
$is_leader = ($user['role'] ?? 'staff') === 'staff_leader';

// Query: Group by linked_order_id from supplier_orders
$sql = "SELECT 
            linked_order_id AS order_group_id,
            MIN(order_date) AS order_date,
            COUNT(*) AS total_count,
            SUM(CASE WHEN LOWER(status) = 'pending ship' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN LOWER(status) IN ('shipped', 'in transit') THEN 1 ELSE 0 END) AS shipped_count,
            SUM(CASE WHEN LOWER(status) IN ('success', 'delivered', 'completed') THEN 1 ELSE 0 END) AS completed_count
        FROM supplier_orders 
        WHERE linked_order_id IS NOT NULL AND linked_order_id != ''
        GROUP BY linked_order_id 
        ORDER BY order_date DESC, linked_order_id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) die("SQL Error: " . $conn->error);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nakofi Cafe - Orders Tracking</title>
    <link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;--radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);--shadow:0 6px 20px rgba(0,0,0,.08);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

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

        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}
        .logout-item .menu-item:hover{background:#fee2e2;}

        .main{margin-left:280px;width:calc(100% - 280px);padding:60px 60px 40px;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
        .page-title{font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;}

        /* User Profile - Exact style from staff dashboard */
        .user-profile{position:relative;}
        .user-btn{
            display:flex;align-items:center;gap:10px;background:var(--white);border:2px solid var(--charcoal);
            padding:10px 24px;border-radius:50px;font-weight:700;cursor:pointer;transition:var(--trans);
            box-shadow:var(--shadow);
        }
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{
            position:absolute;top:100%;right:0;background:var(--white);min-width:260px;border-radius:var(--radius);
            box-shadow:var(--shadow);padding:20px;opacity:0;visibility:hidden;transform:translateY(10px);
            transition:var(--trans);margin-top:12px;text-align:left;z-index:20;border:1px solid var(--border);
        }
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .name{font-size:18px;font-weight:700;color:var(--charcoal);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}
        .order-list{list-style:none;max-width:1000px;margin:0 auto;}
        .order-item{margin-bottom:30px;}
        .order-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:36px;box-shadow:0 12px 28px rgba(0,0,0,.05);transition:var(--trans);text-align:center;cursor:pointer;}
        .order-card:hover{transform:translateY(-12px);box-shadow:0 20px 40px rgba(0,0,0,.1);}
        .order-display{font-size:30px;color:var(--charcoal);margin-bottom:16px;}
        .order-date{font-size:20px;color:var(--muted);margin-bottom:20px;}
        .status-summary{display:flex;justify-content:center;gap:30px;flex-wrap:wrap;margin-top:20px;}
        .status-count{font-size:18px;}
        .status-count strong{color:var(--charcoal);}
        .pending strong{color:#856404;}
        .shipped strong{color:#0C5460;}
        .completed strong{color:#155724;}
        .no-orders{text-align:center;font-size:22px;color:var(--muted);padding:100px;font-style:italic;}

        @media(max-width:992px){
            .main{margin-left:0;padding:40px 20px;}
            .sidebar{position:fixed;left:-280px;}
        }
        @media(max-width:768px){
            .status-summary{gap:20px;flex-direction:column;}
            .order-display{font-size:26px;}
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
        <div class="menu-item" onclick="location.href='<?php echo $is_leader ? 'staff_leader.php' : 'staff.php'; ?>'">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </div>
        <div class="menu-item" onclick="location.href='stock_take.php'">
            <i class="fas fa-clipboard-list"></i><span>Stock Take</span>
        </div>
        <div class="menu-item" onclick="location.href='stock_count.php'">
            <i class="fas fa-boxes-stacked"></i><span>Stock Count</span>
        </div>
        <div class="menu-item" onclick="location.href='stock_drafts.php'">
            <i class="fas fa-file-circle-plus"></i><span>Stock Take Drafts</span>
        </div>
        <div class="menu-item active">
            <i class="fas fa-truck"></i><span>Delivery Tracking</span>
        </div>

        <?php if ($is_leader): ?>
        <div class="menu-item" onclick="location.href='payment.php'">
            <i class="fas fa-credit-card"></i><span>Payment</span>
        </div>
        <div class="menu-item" onclick="location.href='reporting.php'">
            <i class="fas fa-chart-line"></i><span>Reports</span>
        </div>
        <div class="menu-item" onclick="location.href='register.php'">
            <i class="fas fa-users-cog"></i><span>User Register</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<div class="main">
    <div class="header">
        <div class="page-title">Delivery Tracking</div>
        <div class="user-profile">
            <button class="user-btn">
                <i class="fas fa-user-circle"></i> <?= $name ?>
            </button>
            <div class="user-tooltip">
                <div class="detail"><strong>ID:</strong> <?= htmlspecialchars($user_id) ?></div>
                <div class="detail"><strong></strong> <?= $name ?></div>
                <div class="detail"><strong></strong> <?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <p class="no-orders">No supplier orders found.</p>
    <?php else: ?>
        <ul class="order-list">
            <?php foreach ($orders as $o): ?>
                <li class="order-item">
                    <div class="order-card" onclick="window.location='flow.php?order=<?= $o['order_group_id'] ?>'">
                        <div class="order-display">
                            Order #<?= $o['order_group_id'] ?>
                        </div>
                        <div class="order-date">
                            <?= date('l, j F Y', strtotime($o['order_date'])) ?>
                        </div>
                        <div class="status-summary">
                            <div class="status-count pending">
                                <strong><?= $o['pending_count'] ?></strong> Pending
                            </div>
                            <div class="status-count shipped">
                                <strong><?= $o['shipped_count'] ?></strong> Shipped
                            </div>
                            <div class="status-count completed">
                                <strong><?= $o['completed_count'] ?></strong> Completed
                            </div>
                        </div>
                        <div style="margin-top:20px;font-size:18px;color:var(--muted);">
                            Total: <?= $o['total_count'] ?> item<?= $o['total_count'] > 1 ? 's' : '' ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<script>
    function logoutConfirm() {
    document.body.style.pointerEvents = 'none';
    document.querySelector('.main').style.opacity = '0.6';
    setTimeout(() => location = 'login.php', 800);
}
</script>
</body>
</html>