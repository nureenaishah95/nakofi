<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'staff_leader') {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'] ?? '';
$user_name = $_SESSION['user_name'] ?? 'Staff';
$email     = $_SESSION['email'] ?? '';
$display_name = strtoupper($user_name);

// === Basic Stats ===
$total_products = 0;
$sql = "SELECT COUNT(*) AS total FROM products";
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $total_products = (int)$row['total'];
    $stmt->close();
}

$low_stock_count = 0;
$sql = "SELECT COUNT(*) AS low FROM inventory WHERE stock_quantity <= 5";
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $low_stock_count = (int)$row['low'];
    $stmt->close();
}

$last_update = 'Never';
$sql = "SELECT MAX(last_updated) AS latest FROM inventory";
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $latest = $row['latest'] ?? null;
        if ($latest) $last_update = date('d M Y, H:i', strtotime($latest));
    }
    $stmt->close();
}

// === Total Staff Count (from staff table) ===
$total_staff = 0;
$sql = "SELECT COUNT(*) AS total FROM staff";
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $total_staff = (int)$row['total'];
    $stmt->close();
}

// === Products List ===
$products = [];
$sql = "SELECT id, product_name, image_path, created_at FROM products ORDER BY id ASC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $products[] = $row;
    $stmt->close();
}

// === Date Filter & Chart Data ===
$selected_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$selected_ts = strtotime($selected_date);
$week_day = date('N', $selected_ts);
$days_to_monday = $week_day - 1;
$week_start_ts = strtotime("-$days_to_monday days", $selected_ts);
$week_end_ts = strtotime('+6 days', $week_start_ts);

$week_start = date('Y-m-d', $week_start_ts);
$week_end = date('Y-m-d', $week_end_ts);

$colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED', '#C9CBCF', '#7BC225', '#F7464A'];

$sql = "SELECT item_name, SUM(quantity) AS total_qty 
        FROM orders 
        WHERE order_date BETWEEN ? AND ? 
        GROUP BY item_name 
        ORDER BY total_qty DESC 
        LIMIT 10";

$chart_data = ['names' => [], 'quantities' => [], 'colors' => []];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $week_start, $week_end);
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
        $chart_data['names'][] = 'No orders this week';
        $chart_data['quantities'][] = 1;
        $chart_data['colors'][] = '#ccc';
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - Staff Leader Dashboard</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
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

        .sidebar{
            width:280px;
            background:var(--white);
            border-right:1px solid var(--border);
            position:fixed;
            left:0;top:0;bottom:0;
            padding:40px 20px;
            box-shadow:var(--shadow);
            display:flex;
            flex-direction:column;
            z-index:10;
        }
        .logo{
            text-align:center;
            margin-bottom:40px;
            flex-shrink:0;
        }
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}

        /* Scrollable menu area */
        .menu-container{
            flex:1;
            overflow-y:auto;
            padding-right:8px;
            margin-bottom:20px;
        }
        .menu-container::-webkit-scrollbar{width:6px;}
        .menu-container::-webkit-scrollbar-track{background:transparent;}
        .menu-container::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px;}
        .menu-container::-webkit-scrollbar-thumb:hover{background:#a0aec0;}

        .menu-item{
            display:flex;
            align-items:center;
            gap:16px;
            padding:14px 20px;
            color:var(--text);
            font-size:16px;
            font-weight:700;
            border-radius:16px;
            cursor:pointer;
            transition:var(--trans);
            margin-bottom:8px;
        }
        .menu-item i{font-size:22px;color:#D1D5DB;transition:var(--trans);}
        .menu-item:hover{background:#f1f5f9;}
        .menu-item:hover i{color:var(--charcoal);}
        .menu-item.active{background:var(--charcoal);color:var(--white);}
        .menu-item.active i{color:var(--white);}

        .logout-item{
            flex-shrink:0;
            padding-top:-10px;
            border-top:1px solid var(--border);
        }
        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}
        .logout-item .menu-item:hover{background:#fee2e2;color:#dc2626;}

        .main{margin-left:280px;width:calc(100% - 280px);padding:40px 60px;}
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

        /* 4 cards in one row */
        .cards-grid{
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-top: 20px;
        }

        .stat-card{
            background:var(--card);
            border-radius:var(--radius);
            padding: 20px 20px;
            text-align:center;
            box-shadow:var(--shadow);
            transition:var(--trans);
        }
        .stat-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-hover);}
        .stat-card i{font-size: 40px;color:var(--charcoal);margin-bottom: 16px;}
        .stat-card .value{font-size: 25px;font-weight: 700;color: var(--charcoal);}
        .stat-card .label{font-size: 15px;color: var(--muted);margin-top: 8px;}

        .products-row {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        .products-card {
            background:var(--card);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .products-header {padding: 24px 32px 16px;border-bottom: 1px solid var(--border);}
        .products-title {font-size: 22px;font-weight: 700;color: var(--charcoal);margin-bottom: 16px;display: flex;align-items: center;gap: 10px;}
        .products-search-bar {display: flex;gap: 12px;}
        .products-search-bar input {font-family:'Playfair Display',serif;flex-grow: 1;padding: 10px 16px;border: 1px solid var(--border);border-radius: 8px;font-size: 15px;}
        .products-search-bar button {font-family:'Playfair Display',serif;padding: 10px 20px;background: var(--charcoal);color: white;border: none;border-radius: 8px;cursor: pointer;font-weight: 600;}

        .chart-card {
            background:var(--card);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .chart-title {font-size: 20px;font-weight: 700;color: var(--charcoal);margin-bottom: 10px;display: flex;align-items: center;gap: 10px;}
        .date-filter-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: nowrap;
        }
        .date-input-wrapper {
            position: relative;
            flex: 1 1 220px;
        }
        .date-input {
            width: 100%;
            padding: 12px 40px 12px 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            background: white;
        }
        .date-input-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
        }
        .filter-btn {
            padding: 12px 24px;
            background: var(--charcoal);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            font-family:'Playfair Display',serif;
        }

        #clock-date{font-size: 16px;color: var(--muted);margin-bottom: 6px;}
        #clock-time{font-size: 25px;margin: 0;line-height: 1.2;}
        footer{text-align:center;padding:30px 0 20px;color:var(--muted);font-size:14px;}

        @media (max-width: 768px) {
            .sidebar{width:100%;height:auto;position:relative;padding:20px;flex-direction:row;justify-content:center;gap:30px;}
            .main{margin-left:0;padding:30px 20px;}
            .cards-grid {grid-template-columns: 1fr;}
            .products-row {grid-template-columns: 1fr;}
            .date-filter-group {flex-direction: column; align-items: stretch; gap: 12px;}
            .date-input-wrapper {flex: none;}
            .filter-btn {width: 100%;}
        }
    </style>
</head>
<body>

<!-- Sidebar with Scrollable Menu -->
<div class="sidebar">
    <div class="logo">
        <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>

    <div class="menu-container">
        <div class="menu-item active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div>
        <div class="menu-item" onclick="location='stock_take.php'"><i class="fas fa-clipboard-list"></i><span>Stock Take</span></div>
        <div class="menu-item" onclick="location='stock_count.php'"><i class="fas fa-boxes-stacked"></i><span>Stock Count</span></div>
        <div class="menu-item" onclick="location='stock_drafts.php'"><i class="fas fa-file-circle-plus"></i><span>Stock Take Drafts</span></div>
        <div class="menu-item" onclick="location='track.php'"><i class="fas fa-truck"></i><span>Delivery Tracking</span></div>        
        <div class="menu-item" onclick="location='payment.php'"><i class="fas fa-credit-card"></i><span>Payment</span></div>
        <div class="menu-item" onclick="location='reporting.php'"><i class="fas fa-chart-line"></i><span>Inventory Audit Reports</span></div>
        <div class="menu-item" onclick="location='register.php'"><i class="fas fa-users-cog"></i><span>User Register</span></div>
    </div>

    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="header">
        <div class="page-title">Dashboard Overview</div>
        <div class="user-profile">
            <button class="user-btn"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($display_name); ?></button>
            <div class="user-tooltip">
                <div class="name">ID: <?php echo htmlspecialchars($user_id); ?></div>
                <div class="detail"><?php echo htmlspecialchars($display_name); ?></div>
                <div class="detail"><?php echo htmlspecialchars($email); ?></div>
            </div>
        </div>
    </div>

    <div class="cards-grid">
        <!-- Live Clock -->
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <div id="clock-date"></div>
            <div class="value" id="clock-time"></div>
            <div class="label">Current Time</div>
        </div>

        <!-- Last Inventory Update -->
        <div class="stat-card">
            <i class="fas fa-sync-alt"></i>
            <div class="value"><?php echo htmlspecialchars($last_update); ?></div>
            <div class="label">Last Inventory Update</div>
        </div>

        <!-- Total Products -->
        <div class="stat-card">
            <i class="fas fa-boxes-stacked"></i>
            <div class="value"><?php echo $total_products; ?></div>
            <div class="label">Total Products</div>
        </div>

        <!-- Total Staff -->
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div class="value"><?php echo $total_staff; ?></div>
            <div class="label">Total Staff</div>
        </div>

        <!-- Products List + Chart -->
        <div class="products-row">
            <div class="products-card">
                <div class="products-header">
                    <div class="products-title"><i class="fas fa-th-list"></i> Products List</div>
                    <div class="products-search-bar">
                        <input type="text" placeholder="Search products..." id="productSearch">
                        <button onclick="searchProducts()">Search</button>
                        <button class="clear" onclick="clearSearch()">Clear Search</button>
                    </div>
                </div>
                <div style="flex-grow:1;overflow-y:auto;max-height:190px;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead style="background:#f8f9fa;position:sticky;top:0;">
                            <tr>
                                <th style="padding:12px;text-align:left;">Product ID</th>
                                <th style="padding:12px;text-align:left;">Image</th>
                                <th style="padding:12px;text-align:left;">Product Name</th>
                                <th style="padding:12px;text-align:left;">Added Date</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td style="padding:12px;"><?php echo htmlspecialchars($product['id']); ?></td>
                                <td style="padding:12px;">
                                    <img src="<?= BASE_URL ?><?php echo htmlspecialchars($product['image_path']); ?>" 
                                         style="width:40px;height:40px;object-fit:cover;border-radius:6px;"
                                         onerror="this.src='<?= BASE_URL ?>asset/img/placeholder.jpg';">
                                </td>
                                <td style="padding:12px;"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td style="padding:12px;color:#6B7280;"><?php echo date('d-m-Y', strtotime($product['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-pie"></i> Top Buying Products </div>
                <div class="date-filter-group">
                    <div class="date-input-wrapper">
                        <input type="text" id="datePicker" class="date-input" value="<?php echo date('d/m/Y', strtotime($selected_date)); ?>" readonly>
                        <i class="fas fa-calendar-alt date-input-icon"></i>
                    </div>
                    <button class="filter-btn" onclick="applyDateFilter()">Filter Date</button>
                </div>
                <canvas id="demandChart" style="max-height: 150px; margin-top: 20px;"></canvas>
            </div>
        </div>
    </div>

    <footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</div>

<script>
// Live Clock
function updateClock() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('clock-date').textContent = now.toLocaleDateString('en-GB', options);
    document.getElementById('clock-time').textContent = now.toTimeString().slice(0, 8);
}
updateClock();
setInterval(updateClock, 1000);

// Flatpickr
flatpickr("#datePicker", {
    dateFormat: "d/m/Y",
    defaultDate: "<?php echo date('d/m/Y', strtotime($selected_date)); ?>",
});

// Apply filter
function applyDateFilter() {
    const displayDate = document.getElementById('datePicker').value;
    if (!displayDate) return;
    const parts = displayDate.split('/');
    const isoDate = parts[2] + '-' + parts[1].padStart(2, '0') + '-' + parts[0].padStart(2, '0');
    window.location = '?filter_date=' + isoDate;
}

// Product search
function searchProducts() {
    const input = document.getElementById('productSearch').value.trim().toLowerCase();
    const rows = document.querySelectorAll('#productsTableBody tr');
    rows.forEach(row => {
        const name = row.cells[2].textContent.toLowerCase();
        row.style.display = name.includes(input) ? '' : 'none';
    });
}
function clearSearch() {
    document.getElementById('productSearch').value = '';
    document.querySelectorAll('#productsTableBody tr').forEach(row => row.style.display = '');
}
document.getElementById('productSearch').addEventListener('input', searchProducts);

// Pie Chart
const chartData = <?php echo json_encode($chart_data); ?>;

let demandChart;
function initChart() {
    const ctx = document.getElementById('demandChart').getContext('2d');
    if (demandChart) demandChart.destroy();
    
    demandChart = new Chart(ctx, {
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
                legend: { position: 'right', labels: { font: { size: 14 } } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const qty = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percent = total > 0 ? ((qty / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ${qty} units (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', initChart);

// Logout
function logoutConfirm() {
        document.body.style.pointerEvents = 'none';
        document.querySelector('.main').style.opacity = '0.6';
        setTimeout(() => location = 'login.php', 800);
}
</script>

</body>
</html>