<?php
session_start();
require_once 'config.php'; // Your mysqli connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$supID = $_SESSION['user_id'];

// Fetch supplier details from 'supplier' table
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

// === Supplier-Specific Stats ===

// Total Orders Shipped (only this supplier)
$total_shipped = 0;
$sql = "SELECT COUNT(*) AS total 
        FROM supplier_orders 
        WHERE supplier_id = ? 
          AND status IN ('shipped', 'completed', 'Completed', 'Shipped', 'Received')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $supID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_shipped = (int)$row['total'];
}
$stmt->close();

// Pending Orders (only this supplier)
$total_pending = 0;
$sql = "SELECT COUNT(*) AS total 
        FROM supplier_orders 
        WHERE supplier_id = ? 
          AND status = 'pending ship'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $supID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_pending = (int)$row['total'];
}
$stmt->close();

// Total Earnings (only shipped/completed items for this supplier)
$total_earnings = 0.00;
$sql = "SELECT COALESCE(SUM(total), 0) AS earnings 
        FROM supplier_orders 
        WHERE supplier_id = ? 
          AND status IN ('shipped', 'completed', 'Completed', 'Shipped', 'Received')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $supID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_earnings = (float)$row['earnings'];
}
$stmt->close();

$sst = $total_earnings * 0.08;
$grand_total = $total_earnings + $sst;

// === Recent Orders (this supplier only) ===
$recent_orders = [];
$sql = "SELECT order_date, item_name, quantity, total AS subtotal, status 
        FROM supplier_orders 
        WHERE supplier_id = ?
        ORDER BY order_date DESC, id DESC 
        LIMIT 15";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $supID);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_orders[] = $row;
}
$stmt->close();

// === Pie Chart Data (this supplier only) ===
$selected_date = $_GET['filter_date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$selected_ts = strtotime($selected_date);
$week_day = date('N', $selected_ts);
$week_start_ts = strtotime("-" . ($week_day - 1) . " days", $selected_ts);
$week_end_ts = strtotime('+6 days', $week_start_ts);

$week_start = date('Y-m-d', $week_start_ts);
$week_end = date('Y-m-d', $week_end_ts);

$colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED', '#C9CBCF', '#7BC225', '#F7464A'];

$sql = "SELECT item_name, SUM(quantity) AS total_qty 
        FROM supplier_orders 
        WHERE supplier_id = ?
          AND order_date BETWEEN ? AND ? 
          AND status IN ('shipped', 'completed', 'Completed', 'Shipped', 'Received')
        GROUP BY item_name 
        ORDER BY total_qty DESC 
        LIMIT 10";

$chart_data = ['names' => [], 'quantities' => [], 'colors' => []];
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $supID, $week_start, $week_end);
$stmt->execute();
$result = $stmt->get_result();

$index = 0;
while ($row = $result->fetch_assoc()) {
    $chart_data['names'][] = $row['item_name'];
    $chart_data['quantities'][] = (int)$row['total_qty'];
    $chart_data['colors'][] = $colors[$index % count($colors)];
    $index++;
}

if (empty($chart_data['names'])) {
    $chart_data['names'][] = 'No shipments this week';
    $chart_data['quantities'][] = 1;
    $chart_data['colors'][] = '#ccc';
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Nakofi Cafe - Supplier Dashboard </title>
    <link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        :root{
            --white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;
            --radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);
            --shadow:0 6px 20px rgba(0,0,0,.08);--shadow-hover:0 15px 35px rgba(0,0,0,.12);
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

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

        .main{margin-left:280px;width:calc(100% - 280px);padding:40px 60px;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
        .page-title{font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;}

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

        .cards-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:32px;margin-top:20px;}
        .stat-card{background:var(--card);border-radius:var(--radius);padding:28px 24px;text-align:center;box-shadow:var(--shadow);transition:var(--trans);}
        .stat-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-hover);}
        .stat-card i{font-size:42px;color:var(--charcoal);margin-bottom:16px;}
        .stat-card .value{font-size:28px;font-weight:700;color:var(--charcoal);margin:8px 0;}
        .stat-card .label{font-size:15px;color:var(--muted);}

        .orders-row{grid-column:1/-1;display:grid;grid-template-columns:2fr 1fr;gap:32px;}
        .orders-card,.chart-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;}
        .orders-header,.chart-title{padding:24px 32px 16px;border-bottom:1px solid var(--border);font-size:22px;font-weight:700;color:var(--charcoal);display:flex;align-items:center;gap:10px;}
        .orders-search-bar{display:flex;gap:12px;margin-top:12px;}
        .orders-search-bar input{flex-grow:1;padding:10px 16px;border:1px solid var(--border);border-radius:8px;font-size:15px;}
        .orders-search-bar button{padding:10px 20px;background:var(--charcoal);color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;}
        .date-filter-group{display:flex;gap:12px;margin-bottom:20px;}
        .date-input-wrapper{position:relative;flex:1;}
        .date-input{width:100%;padding:12px 40px 12px 16px;border:1px solid var(--border);border-radius:12px;font-size:15px;background:white;}
        .date-input-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none;}
        .filter-btn{padding:12px 24px;background:var(--charcoal);color:white;border:none;border-radius:12px;font-weight:600;cursor:pointer;}

        table{width:100%;border-collapse:collapse;}
        thead{background:#f8f9fa;position:sticky;top:0;}
        th,td{padding:14px 12px;text-align:left;font-size:15px;}
        th{color:var(--muted);text-transform:uppercase;font-size:13px;letter-spacing:1px;}
        tbody tr:nth-child(even){background:#f9fafb;}
        tbody tr:hover{background:#f1f5f9;}
        .status-pending{color:#dc2626;}
        .status-shipped{color:#f59e0b;}
        .status-completed{color:#28a745;}
        .status-received{color:#28a745;}

        footer{text-align:center;padding:60px 0 20px;color:var(--muted);font-size:14px;}

        @media (max-width:1200px){.cards-grid{grid-template-columns:repeat(2,1fr);}}
        @media (max-width:768px){
            .sidebar{width:100%;height:auto;position:relative;padding:20px;flex-direction:row;justify-content:center;gap:30px;}
            .main{margin-left:0;padding:30px 20px;}
            .header{flex-direction:column;gap:20px;align-items:stretch;text-align:center;}
            .cards-grid{grid-template-columns:1fr;}
            .orders-row{grid-template-columns:1fr;}
            .date-filter-group{flex-direction:column;}
            .filter-btn{width:100%;}
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
        <div class="menu-item active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div>
        <div class="menu-item" onclick="location='orders.php'"><i class="fas fa-clipboard-check"></i><span>List Orders</span></div>
        <div class="menu-item" onclick="location='delivery.php'"><i class="fas fa-truck"></i><span>Delivery Arrangement</span></div>
        <div class="menu-item" onclick="location='report.php'"><i class="fas fa-receipt"></i><span>Report</span></div>
        <div class="menu-item" onclick="location='invoice.php'"><i class="fas fa-file-invoice"></i><span>Invoice</span></div>
    </div>
    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="header">
        <div class="page-title">Supplier Dashboard</div>
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

    <div class="cards-grid">
        <div class="stat-card">
            <i class="fas fa-truck"></i>
            <div class="value"><?= $total_shipped ?></div>
            <div class="label">Total Orders Shipped</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <div class="value"><?= $total_pending ?></div>
            <div class="label">Pending Orders</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-dollar-sign"></i>
            <div class="value">RM <?= number_format($total_earnings, 2) ?></div>
            <div class="label">Total Earnings (excl. SST)</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-receipt"></i>
            <div class="value">RM <?= number_format($grand_total, 2) ?></div>
            <div class="label">Grand Total (incl. 8% SST)</div>
        </div>

        <div class="orders-row">
            <div class="orders-card">
                <div class="orders-header"><i class="fas fa-list-alt"></i> Recent Orders</div>
                <div class="orders-search-bar">
                    <input type="text" placeholder="Search orders..." id="orderSearch">
                    <button onclick="searchOrders()">Search</button>
                </div>
                <div style="flex-grow:1;overflow-y:auto;max-height:300px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th><th>Item</th><th>Qty</th><th>Subtotal</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php if (empty($recent_orders)): ?>
                                <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted);">No recent orders</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($order['order_date'])) ?></td>
                                    <td><?= htmlspecialchars($order['item_name']) ?></td>
                                    <td><?= $order['quantity'] ?></td>
                                    <td>RM <?= number_format($order['subtotal'], 2) ?></td>
                                    <td><span class="status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-pie"></i> Top Items Shipped This Week</div>
                <div class="date-filter-group">
                    <div class="date-input-wrapper">
                        <input type="text" id="datePicker" class="date-input" value="<?= date('d/m/Y', strtotime($selected_date)) ?>" readonly>
                        <i class="fas fa-calendar-alt date-input-icon"></i>
                    </div>
                    <button class="filter-btn" onclick="applyDateFilter()">Filter Week</button>
                </div>
                <canvas id="pieChart" style="max-height:280px;"></canvas>
            </div>
        </div>
    </div>

    <footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</div>

<script>
function searchOrders() {
    const input = document.getElementById('orderSearch').value.trim().toLowerCase();
    document.querySelectorAll('#ordersTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
    });
}
document.getElementById('orderSearch').addEventListener('input', searchOrders);

flatpickr("#datePicker", {
    dateFormat: "d/m/Y",
    defaultDate: "<?= date('d/m/Y', strtotime($selected_date)) ?>"
});

function applyDateFilter() {
    const val = document.getElementById('datePicker').value;
    if (val) {
        const [d, m, y] = val.split('/');
        location = '?filter_date=' + y + '-' + m.padStart(2,'0') + '-' + d.padStart(2,'0');
    }
}

function logoutConfirm() {
    document.body.style.pointerEvents = 'none';
    document.querySelector('.main').style.opacity = '0.6';
    setTimeout(() => location = 'login.php', 800);
}

const chartData = <?= json_encode($chart_data) ?>;
let pieChart;
function initChart() {
    const ctx = document.getElementById('pieChart').getContext('2d');
    if (pieChart) pieChart.destroy();
    pieChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: chartData.names,
            datasets: [{
                data: chartData.quantities,
                backgroundColor: chartData.colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { font: { size: 14 }, padding: 20 } },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const total = ctx.dataset.data.reduce((a,b) => a + b, 0);
                            const percent = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                            return `${ctx.label}: ${ctx.raw} units (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
}
document.addEventListener('DOMContentLoaded', initChart);
</script>
</body>
</html>