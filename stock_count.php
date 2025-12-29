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

// Unit labels
$unit_labels = [
    'milk'              => 'carton(s)',
    'coffee'            => 'pack(s)',
    'syrup'             => 'bottle(s)',
    'monin_strawberry'  => 'bottle(s)',
    'caramel_syrup'     => 'bottle(s)',
    'matcha_niko_neko'  => 'pack(s)',
    'whipped_cream'     => 'bottle(s)',
    'default'           => 'unit(s)'
];

// Query
$sql = "
    SELECT 
        orders_id,
        GROUP_CONCAT(item_name ORDER BY item_name SEPARATOR ',') AS items,
        GROUP_CONCAT(quantity ORDER BY item_name SEPARATOR ',') AS quantities,
        SUM(total) AS subtotal,
        MIN(order_date) AS order_date,
        status
    FROM orders
    WHERE 1=1
";

$params = [];
$types = '';

$search_order_id = trim($_POST['order_id'] ?? '');

if ($search_order_id !== '') {
    $sql .= " AND orders_id = ?";
    $params[] = $search_order_id;
    $types .= "i";
}

$sql .= " GROUP BY orders_id ORDER BY order_date DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
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
    <title>Nakofi Cafe - Stock Count</title>
    <link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--border:#E5E7EB;--text:#111827;--muted:#6B7280;--radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);--shadow:0 6px 20px rgba(0,0,0,.08);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

        .sidebar{width:280px;background:var(--white);border-right:1px solid var(--border);position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);display:flex;flex-direction:column;z-index:10;transition:transform .4s ease;}
        .logo{text-align:center;margin-bottom:40px;}
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}

        .menu-container{flex:1;overflow-y:auto;padding-right:8px;margin-bottom:20px;}
        .menu-container::-webkit-scrollbar{width:6px;}
        .menu-container::-webkit-scrollbar-track{background:transparent;}
        .menu-container::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px;}
        .menu-container::-webkit-scrollbar-thumb:hover{background:#a0aec0;}

        .menu-item{display:flex;align-items:center;gap:16px;padding:14px 20px;color:var(--text);font-size:16px;font-weight:700;border-radius:16px;cursor:pointer;transition:var(--trans);margin-bottom:8px;}
        .menu-item i{font-size:22px;color:#D1D5DB;transition:var(--trans);}
        .menu-item:hover{background:#f1f5f9;}
        .menu-item:hover i{color:var(--charcoal);}
        .menu-item.active{background:var(--charcoal);color:var(--white);}
        .menu-item.active i{color:var(--white);}

        .logout-item{flex-shrink:0;padding-top:-10px;border-top:1px solid var(--border);}
        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}
        .logout-item .menu-item:hover{background:#fee2e2;color:#dc2626;}

        .main{margin-left:280px;width:calc(100% - 280px);padding:40px 60px;}
        .main.full{margin-left:0;padding:30px 20px;}

        .sidebar-toggle{position:fixed;top:20px;left:20px;background:var(--charcoal);color:white;width:50px;height:50px;border-radius:50%;display:none;align-items:center;justify-content:center;font-size:24px;cursor:pointer;z-index:100;box-shadow:var(--shadow);}
        .sidebar-toggle:hover{background:#111;}

        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
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

        .container{max-width:1400px;margin:0 auto;}

        .search-container{display:flex;align-items:center;gap:20px;margin-bottom:40px;flex-wrap:wrap;justify-content:center;}
        .search-wrapper{position:relative;flex:1;max-width:600px;}
        .search-input{font-family:'Playfair Display',serif;width:100%;padding:18px 20px 18px 56px;font-size:17px;border-radius:50px;border:none;background:#fff;box-shadow:var(--shadow);outline:none;}
        .search-input::placeholder{color:#9CA3AF;}
        .search-icon{position:absolute;left:20px;top:50%;transform:translateY(-50%);font-size:24px;color:#9CA3AF;pointer-events:none;}
        .search-btn, .clear-btn{font-family:'Playfair Display',serif;padding:16px 32px;background:var(--charcoal);color:white;border:none;border-radius:50px;font-size:16px;font-weight:700;cursor:pointer;box-shadow:var(--shadow);transition:var(--trans);}
        .search-btn:hover, .clear-btn:hover{background:#111;}

        table{width:100%;border-collapse:collapse;background:var(--white);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);margin-bottom:60px;}
        thead{background:var(--charcoal);color:var(--white);}
        th, td{padding:20px 24px;text-align:left;font-size:16px;}
        th{font-weight:700;}
        tbody tr{cursor:pointer;transition:var(--trans);}
        tbody tr:hover{background:#f1f5f9;}
        tbody tr:nth-child(even){background:#f9fafb;}

        /* Updated Status Styling */
        .status{
            display:inline-block;
            padding:8px 16px;
            border-radius:50px;
            font-size:14px;
            font-weight:700;
            text-transform:uppercase;
        }
        .status.pending{
            background:#FFF3CD;   /* Soft orange background */
            color:#856404;
        }
        .status.success{
            background:transparent;  /* No background */
            color:#111827;           /* Normal black text */
        }

        .hint{color:var(--muted);font-size:14px;font-style:italic;margin-bottom:20px;text-align:center;}
        .no-orders{text-align:center;font-size:24px;color:var(--muted);padding:100px 20px;font-style:italic;}

        .actions{display:flex;justify-content:center;gap:24px;margin:50px 0;}
        .btn{font-family:'Playfair Display',serif;padding:16px 52px;border:2px solid var(--charcoal);border-radius:50px;background:transparent;color:var(--charcoal);font-weight:700;font-size:16px;cursor:pointer;transition:var(--trans);min-width:240px;}
        .btn:hover{background:var(--charcoal);color:var(--white);}

        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:var(--trans);z-index:1000;}
        .modal-overlay.open{opacity:1;visibility:visible;}
        .modal-content{background:var(--white);border-radius:var(--radius);max-width:650px;width:90%;max-height:90vh;overflow-y:auto;padding:40px;box-shadow:var(--shadow);position:relative;transform:scale(0.9);transition:var(--trans);}
        .modal-overlay.open .modal-content{transform:scale(1);}
        .close-btn{position:absolute;top:15px;right:20px;background:none;border:none;font-size:32px;color:var(--muted);cursor:pointer;}
        .modal-title{font-size:28px;color:var(--charcoal);margin-bottom:10px;text-align:center;}
        .modal-date{text-align:center;color:var(--muted);font-size:18px;margin:15px 0 30px;}
        .order-items{list-style:none;margin:30px 0;padding:0;}
        .order-items li{font-size:18px;padding:12px 0;border-bottom:1px solid #f1f5f9;}
        .order-items li:last-child{border-bottom:none;}
        .price-breakdown{background:#f9fafb;padding:25px;border-radius:16px;margin:30px 0;box-shadow:0 4px 12px rgba(0,0,0,0.05);}
        .price-line{display:flex;justify-content:space-between;align-items:center;margin:12px 0;font-size:18px;}
        .price-line.tax{color:#856404;}
        .price-line.total{font-size:24px;font-weight:700;color:var(--charcoal);padding-top:15px;margin-top:15px;border-top:2px solid var(--border);}
        .modal-status{text-align:center;margin-top:20px;}

        @media(max-width:992px){
            .sidebar{transform:translateX(-280px);}
            .sidebar.open{transform:translateX(0);}
            .main{margin-left:0;padding:30px 20px;}
            .sidebar-toggle{display:flex;}
            .search-container{flex-direction:column;align-items:stretch;}
            .search-wrapper{max-width:100%;}
            th, td{padding:14px;}
        }
    </style>
</head>
<body>

<!-- Sidebar and header remain the same -->
<div class="sidebar-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>

<div class="sidebar" id="sidebar">
    <!-- Your sidebar code unchanged -->
    <div class="logo">
        <img src="http://localhost/nakofi/asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>

    <div class="menu-container">
        <!-- Menu items unchanged -->
        <div class="menu-item" onclick="location.href='<?php echo $is_leader ? 'staff_leader.php' : 'staff.php'; ?>'">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </div>
        <div class="menu-item" onclick="location.href='stock_take.php'">
            <i class="fas fa-clipboard-list"></i><span>Stock Take</span>
        </div>
        <div class="menu-item active">
            <i class="fas fa-boxes-stacked"></i><span>Stock Count</span>
        </div>
        <div class="menu-item" onclick="location.href='stock_drafts.php'">
            <i class="fas fa-file-circle-plus"></i><span>Stock Take Drafts</span>
        </div>
        <div class="menu-item" onclick="location.href='track.php'">
            <i class="fas fa-truck"></i><span>Delivery Tracking</span>
        </div>

        <?php if ($is_leader): ?>
        <div class="menu-item" onclick="location.href='payment.php'">
            <i class="fas fa-credit-card"></i><span>Payment</span>
        </div>
        <div class="menu-item" onclick="location.href='reporting.php'">
            <i class="fas fa-chart-line"></i><span>Inventory Audit Reports</span>
        </div>
        <div class="menu-item" onclick="location.href='register.php'">
            <i class="fas fa-users-cog"></i><span>User Register</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </div>
    </div>
</div>

<div class="main" id="mainContent">
    <div class="page-header">
        <div class="page-title">Stock Count</div>
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

    <div class="container">
        <div class="search-container">
            <form method="POST" style="display:flex;gap:20px;align-items:center;flex:1;max-width:800px;">
                <div class="search-wrapper">
                    <input type="text" name="order_id" class="search-input" placeholder="Search by Order ID" value="<?php echo htmlspecialchars($search_order_id); ?>">
                </div>
                <button type="submit" class="search-btn">Search</button>
                <?php if ($search_order_id !== ''): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="clear-btn">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <p class="hint">Double-click any row to view full order details</p>

        <?php if (empty($orders)): ?>
            <p class="no-orders">
                <i class="fas fa-search" style="font-size:60px;margin-bottom:20px;opacity:0.5;"></i><br>
                <?php echo $search_order_id ? "No order found with ID #$search_order_id" : "No auto-orders yet."; ?>
            </p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order):
                        $item_names = explode(',', $order['items']);
                        $quantities = explode(',', $order['quantities']);
                        $pretty_items = [];

                        for ($i = 0; $i < count($item_names); $i++) {
                            $name = trim($item_names[$i]);
                            $key = str_replace(' ', '_', strtolower($name));
                            $unit = $unit_labels[$key] ?? $unit_labels['default'];
                            $pretty_name = strtoupper(str_replace('_', ' ', $name));
                            $pretty_items[] = trim($quantities[$i]) . " $unit × $pretty_name";
                        }

                        $subtotal = (float)$order['subtotal'];
                        $tax = $subtotal * 0.08;
                        $total_incl_tax = $subtotal + $tax;

                        $order_id = $order['orders_id'];
                        $date_str = date('d F Y \a\t H:i', strtotime($order['order_date']));

                        // Status logic
                        $raw_status = strtolower(trim($order['status']));
                        if ($raw_status === 'pending_payment' || $raw_status === 'pending payment') {
                            $status_class = 'pending';
                            $status_text = 'PENDING PAYMENT';
                        } else {
                            $status_class = 'success';  // All other statuses (including payment_success) = normal black text
                            $status_text = 'PAYMENT SUCCESS';
                        }

                        $js_items = htmlspecialchars(json_encode($pretty_items), ENT_QUOTES, 'UTF-8');
                        $js_date = htmlspecialchars($date_str, ENT_QUOTES, 'UTF-8');
                        $js_subtotal = number_format($subtotal, 2);
                        $js_tax = number_format($tax, 2);
                        $js_total = number_format($total_incl_tax, 2);
                    ?>
                        <tr ondblclick="openModal(<?= $order_id ?>, '<?= $js_date ?>', <?= $js_items ?>, '<?= $js_subtotal ?>', '<?= $js_tax ?>', '<?= $js_total ?>', '<?= $status_text ?>', '<?= $status_class ?>')">
                            <td><strong>#<?= $order_id ?></strong></td>
                            <td><?= $date_str ?></td>
                            <td><span class="status <?= $status_class ?>"><?= $status_text ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="actions">
            <button class="btn" onclick="window.location.href='stock_take.php'">
                Perform New Stock Count
            </button>
        </div>
    </div>
</div>

<!-- Modal and JavaScript unchanged -->
<div class="modal-overlay" id="orderModal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <h3 class="modal-title">Order Details <span id="modalOrderId"></span></h3>
        <div class="modal-date" id="modalDate"></div>
        <ul class="order-items" id="modalItems"></ul>
        <div class="price-breakdown">
            <div class="price-line"><span>Subtotal</span><span>RM <span id="modalSubtotal"></span></span></div>
            <div class="price-line tax"><span>Tax (8%)</span><span>RM <span id="modalTax"></span></span></div>
            <div class="price-line total"><span>Total (incl. tax)</span><span>RM <span id="modalTotal"></span></span></div>
        </div>
        <div class="modal-status">
            Status: <span class="status" id="modalStatus"></span>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('mainContent').classList.toggle('full');
}

function logoutConfirm() {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
    setTimeout(() => location.href = 'login.php', 800);
}

function openModal(orderId, date, items, subtotal, tax, total, status, statusClass) {
    document.getElementById('modalOrderId').textContent = '# ' + orderId;
    document.getElementById('modalDate').textContent = date;
    document.getElementById('modalSubtotal').textContent = subtotal;
    document.getElementById('modalTax').textContent = tax;
    document.getElementById('modalTotal').textContent = total;

    const statusEl = document.getElementById('modalStatus');
    statusEl.textContent = status;
    statusEl.className = 'status ' + statusClass;

    const itemsList = document.getElementById('modalItems');
    itemsList.innerHTML = '';
    items.forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        itemsList.appendChild(li);
    });

    document.getElementById('orderModal').classList.add('open');
}

function closeModal() {
    document.getElementById('orderModal').classList.remove('open');
}

document.getElementById('orderModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

</body>
</html>