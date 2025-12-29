<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT name, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$display_name = strtoupper($user['name'] ?? 'User');
$user_role = $user['role'] ?? 'staff';

// Fetch individual successful orders
$sql = "
    SELECT 
        orders_id,
        order_date,
        total_amount,
        payment_status,
        (SELECT COUNT(*) FROM orders WHERE orders_id = o.orders_id) as item_count
    FROM orders o
    WHERE status = 'PAYMENT SUCCESS'
    GROUP BY orders_id
    ORDER BY orders_id DESC
";

$reports = [];
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            'orders_id'    => $row['orders_id'],
            'date'         => date('d M Y', strtotime($row['order_date'])),
            'time'         => date('h:i A', strtotime($row['order_date'])),
            'raw_date'     => $row['order_date'],
            'item_count'   => $row['item_count'],
            'total_amount' => number_format($row['total_amount'], 2),
            'payment_status' => $row['payment_status']
        ];
    }
}

// This month's total sales (optional - kept for overview)
$monthly_sql = "
    SELECT SUM(total_amount) as month_total 
    FROM orders 
    WHERE status = 'PAYMENT SUCCESS' 
      AND MONTH(order_date) = MONTH(CURDATE()) 
      AND YEAR(order_date) = YEAR(CURDATE())
";
$month_result = $conn->query($monthly_sql);
$monthly_total = number_format($month_result->fetch_assoc()['month_total'] ?? 0, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - Inventory Audit Reports </title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{
            --white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;
            --radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);
            --shadow:0 6px 20px rgba(0,0,0,.08);--shadow-hover:0 15px 35px rgba(0,0,0,.12);
            --success:#28a745;--danger:#e74c3c;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

        .sidebar{width:280px;background:var(--white);border-right:1px solid var(--border);position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);display:flex;flex-direction:column;z-index:10;}
        .logo{text-align:center;margin-bottom:40px;flex-shrink:0;}
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}

        .menu-container{flex:1;overflow-y:auto;padding-right:8px;margin-bottom:20px;}
        .menu-item{display:flex;align-items:center;gap:16px;padding:14px 20px;color:var(--text);font-size:16px;font-weight:700;border-radius:16px;cursor:pointer;transition:var(--trans);margin-bottom:8px;}
        .menu-item i{font-size:22px;color:#D1D5DB;transition:var(--trans);}
        .menu-item:hover{background:#f1f5f9;}
        .menu-item:hover i{color:var(--charcoal);}
        .menu-item.active{background:var(--charcoal);color:var(--white);}
        .menu-item.active i{color:var(--white);}

        .logout-item{flex-shrink:0;padding-top:20px;border-top:1px solid var(--border);}
        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}
        .logout-item .menu-item:hover{background:#fee2e2;}

        .main{margin-left:280px;width:calc(100% - 280px);padding:40px 60px;min-height:100vh;display:flex;flex-direction:column;}

        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
        .page-title{font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;}
        .user-profile{position:relative;}
        .user-btn{display:flex;align-items:center;gap:10px;background:var(--white);border:2px solid var(--charcoal);padding:10px 24px;border-radius:50px;font-weight:700;cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{position:absolute;top:100%;right:0;background:var(--white);min-width:260px;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;opacity:0;visibility:hidden;transform:translateY(10px);transition:var(--trans);margin-top:12px;z-index:20;border:1px solid var(--border);}
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}

        .report-content{flex:1;max-width:1000px;width:100%;margin:0 auto;}

        .monthly-box{background:var(--success);color:white;padding:20px;border-radius:var(--radius);text-align:center;font-size:22px;margin-bottom:30px;box-shadow:var(--shadow);font-weight:700;}

        .report-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}

        .report-list{max-height:70vh;overflow-y:auto;}
        .report-list::-webkit-scrollbar{width:8px;}
        .report-list::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:4px;}

        .report-item{display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid var(--border);transition:var(--trans);}
        .report-item:hover{background:#f8fdf9;}

        .report-left .order-id{font-size:18px;color:var(--muted);margin-bottom:4px;}
        .report-left .report-date{font-size:22px;color:var(--charcoal);font-weight:700;}
        .report-left .report-details{font-size:14px;color:var(--muted);margin-top:4px;}
        .paid{color:var(--success);font-weight:700;}
        .unpaid{color:var(--danger);font-weight:700;}

        .view-link{color:var(--charcoal);text-decoration:none;font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;white-space:nowrap;}
        .view-link:hover{color:var(--success);}

        .no-reports{text-align:center;padding:80px 20px;color:var(--muted);font-size:18px;}
        .no-reports i{font-size:60px;color:#ddd;margin-bottom:20px;display:block;}

        footer{text-align:center;padding:30px 20px;color:var(--muted);font-size:14px;margin-top:auto;}

        @media (max-width:992px){
            .main{margin-left:0;padding:30px 20px;}
            .report-item{flex-direction:column;align-items:flex-start;gap:10px;}
            .view-link{align-self:flex-end;}
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
        <div class="menu-item active"><i class="fas fa-chart-line"></i><span>Inventory Audit Report</span></div>
        <div class="menu-item" onclick="location='register.php'"><i class="fas fa-users-cog"></i><span>User Register</span></div>
    </div>

    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="page-header">
        <div class="page-title">Inventory Audit Reports</div>
        <div class="user-profile">
            <button class="user-btn"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($display_name); ?></button>
            <div class="user-tooltip">
                <div class="detail"><strong>ID:</strong> <?php echo htmlspecialchars($user_id); ?></div>
                <div class="detail"><strong>Name:</strong> <?php echo htmlspecialchars($display_name); ?></div>
                <div class="detail"><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <div class="report-content">
        <div class="monthly-box">
            Total Inventory Purchases: <strong>RM<?php echo $monthly_total; ?></strong>
        </div>

        <div class="report-card">
            <?php if (!empty($reports)): ?>
                <div class="report-list">
                    <?php foreach ($reports as $report): ?>
                        <div class="report-item">
                            <div class="report-left">
                                <div class="order-id">Order #<?php echo $report['orders_id']; ?></div>
                                <div class="report-date"><?php echo $report['date']; ?> <span style="font-size:16px;color:var(--muted);">(<?php echo $report['time']; ?>)</span></div>
                                <div class="report-details">
                                    <?php echo $report['item_count']; ?> item<?php echo $report['item_count'] > 1 ? 's' : ''; ?> • 
                                    Total: <strong>RM<?php echo $report['total_amount']; ?></strong> • 
                                    <span class="<?php echo strtolower($report['payment_status']) === 'paid' ? 'paid' : 'unpaid'; ?>">
                                        <?php echo ucwords($report['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <a href="order_details.php?id=<?php echo $report['orders_id']; ?>" class="view-link">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-reports">
                    <i class="fas fa-chart-line"></i>
                    <p>No successful orders recorded yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
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