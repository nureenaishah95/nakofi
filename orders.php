<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$supID = $_SESSION['user_id'];

// Force refresh once if redirected back from an action
if (isset($_GET['refreshed'])) {
    echo '<script>if (history.replaceState) history.replaceState(null, null, "orders.php");</script>';
}

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

// MAIN QUERY
$sql = "
    SELECT 
        id,
        item_name,
        quantity,
        total AS line_total,
        order_date,
        status,
        action,
        linked_order_id,
        refund_status,
        refund_amount,
        (
            SELECT COUNT(*) 
            FROM supplier_orders si 
            WHERE si.linked_order_id = so.linked_order_id 
              AND (si.refund_status NOT IN ('completed', 'paid') OR si.refund_status IS NULL)
              AND si.status != 'refunded'
        ) AS non_refunded_items_count,
        (
            SELECT COUNT(*) 
            FROM supplier_orders si2 
            WHERE si2.linked_order_id = so.linked_order_id
        ) AS total_items_in_order
    FROM supplier_orders so
    WHERE supplier_id = ?
    ORDER BY order_date DESC, id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $supID);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $display_order_id = $row['linked_order_id'] ?? $row['id'];

    if (!isset($orders[$display_order_id])) {
        $orders[$display_order_id] = [
            'header' => [
                'display_id'         => $display_order_id,
                'order_date'         => $row['order_date'],
                'db_status'          => trim($row['status'] ?? 'Pending'),
                'db_action'          => trim($row['action'] ?? 'Ship'),
                'item_count'         => 0,
                'total_amount'       => 0,
                'non_refunded_count' => (int)$row['non_refunded_items_count'],
                'total_items'        => (int)$row['total_items_in_order']
            ],
            'items' => []
        ];
    }

    $orders[$display_order_id]['header']['item_count']++;
    $orders[$display_order_id]['header']['total_amount'] += $row['line_total'];

    // Update header status/action (take the most recent)
    $orders[$display_order_id]['header']['db_status'] = trim($row['status'] ?? $orders[$display_order_id]['header']['db_status']);
    $orders[$display_order_id]['header']['db_action'] = trim($row['action'] ?? $orders[$display_order_id]['header']['db_action']);

    $orders[$display_order_id]['items'][] = [
        'id'            => $row['id'],
        'item_name'     => $row['item_name'] ?: 'Unknown Item',
        'quantity'      => $row['quantity'],
        'line_total'    => $row['line_total'],
        'refund_status' => $row['refund_status'] ?? 'none',
        'refund_amount' => $row['refund_amount'] ?? 0
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - List Orders</title>
    <link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        :root {
            --white:#fff; --charcoal:#212529; --bg:#F9FAFB; --card:#fff; --border:#E5E7EB;
            --text:#111827; --muted:#6B7280; --radius:20px; --trans:all .4s cubic-bezier(.16,1,.3,1);
            --shadow:0 6px 20px rgba(0,0,0,.08); --shadow-hover:0 15px 35px rgba(0,0,0,.12);
            --red:#dc2626; --green:#28a745; --blue:#007bff; --darkred:#991b1b; --orange:#fd7e14;
        }
        * {margin:0;padding:0;box-sizing:border-box;}
        body {font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;}
        .sidebar {width:280px;background:var(--white);border-right:1px solid var(--border);position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);display:flex;flex-direction:column;z-index:10;}
        .logo {text-align:center;margin-bottom:40px;}
        .logo img {height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1 {font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}
        .menu-container {flex:1;overflow-y:auto;padding-right:8px;margin-bottom:20px;}
        .menu-item {display:flex;align-items:center;gap:16px;padding:14px 20px;color:var(--text);font-size:16px;font-weight:700;border-radius:16px;cursor:pointer;transition:var(--trans);margin-bottom:8px;}
        .menu-item i {font-size:22px;color:#D1D5DB;}
        .menu-item:hover {background:#f1f5f9;}
        .menu-item.active {background:var(--charcoal);color:var(--white);}
        .menu-item.active i {color:var(--white);}
        .logout-item .menu-item {color:var(--red);}
        .logout-item .menu-item i {color:var(--red);}
        .main {margin-left:280px;width:calc(100% - 280px);padding:40px 60px;flex:1;}
        .header {display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
        .page-title {font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;}
        .user-profile{position:relative;}
        .user-btn{display:flex;align-items:center;gap:10px;background:var(--white);border:2px solid var(--charcoal);padding:10px 24px;border-radius:50px;font-weight:700;cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{position:absolute;top:100%;right:0;background:var(--white);min-width:260px;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;opacity:0;visibility:hidden;transform:translateY(10px);transition:var(--trans);margin-top:12px;text-align:left;z-index:20;border:1px solid var(--border);}
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .name{font-size:18px;font-weight:700;color:var(--charcoal);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}
        .card {background:var(--card);border-radius:var(--radius);padding:40px;box-shadow:var(--shadow);}
        .filter-bar {display:flex;justify-content:center;gap:16px;margin-bottom:32px;flex-wrap:wrap;align-items:center;}
        .filter-bar input[type="text"], .filter-bar select, .filter-bar button {font-family: 'Playfair Display',serif;padding:12px 20px;border-radius:12px;font-weight:700;font-size:15px;}
        .filter-bar input[type="text"], .filter-bar select {border:2px solid var(--border);background:var(--white);min-width:200px;}
        .filter-bar button {background:var(--charcoal);color:var(--white);border:none;cursor:pointer;}
        table {width:100%;border-collapse:separate;border-spacing:0 12px;}
        thead th {padding:16px 20px;background:var(--white);color:var(--muted);font-size:14px;text-transform:uppercase;letter-spacing:1.2px;}
        tbody tr {background:var(--white);box-shadow:0 4px 12px rgba(0,0,0,0.03);transition:var(--trans);cursor:pointer;border-radius:16px;}
        tbody tr:hover {transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,0.08);}
        td {padding:20px;font-size:18px;}
        .status-pending {color:var(--red);font-weight:700;}
        .status-transit {color:var(--blue);font-weight:700;}
        .status-received {color:var(--green);font-weight:700;}
        .status-refunded {color:var(--darkred);font-weight:800;text-transform:uppercase;letter-spacing:0.8px;}
        .btn-ship {
            background:#800000;color:#fff;padding:12px 24px;border-radius:50px;text-decoration:none;
            font-weight:500;display:inline-block;transition:var(--trans);box-shadow:0 4px 10px rgba(220,38,38,0.3);
        }
        .btn-ship:hover {background:#fff3cd;color:#800000;transform:translateY(-2px);}
        .btn-no-stock, .btn-process-request {
            padding:8px 16px;border-radius:50px;font-weight:700;font-size:14px;
            cursor:pointer;border:none;transition:var(--trans);box-shadow:0 3px 8px rgba(0,0,0,0.2);
        }
        .btn-no-stock {background:#dc3545;color:#fff;}
        .btn-no-stock:hover {background:#c82333;transform:translateY(-1px);}
        .btn-process-request {background:#fd7e14;color:#fff;}
        .btn-process-request:hover {background:#e06b00;transform:translateY(-1px);}
        .details-row td {padding:0;background:transparent;}
        .details-content {padding:20px;background:#f8fafc;border-top:1px solid var(--border);border-radius:0 0 16px 16px;}
        .details-table {width:100%;border-collapse:collapse;}
        .details-table th {background:#e2e8f0;padding:14px;font-size:14px;text-transform:uppercase;color:var(--muted);}
        .details-table td {padding:14px;border-bottom:1px solid #eee;text-align:center;}
        .details-table td:first-child {text-align:left;font-weight:700;}
        .expand-icon {transition:transform 0.3s ease;color:var(--muted);}
        .expanded .expand-icon {transform:rotate(90deg);}
        footer {text-align:center;padding:60px 0 40px;color:var(--muted);font-size:14px;margin-top:auto;background:var(--bg);margin-left:280px;}

        .modal-overlay {position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:none;align-items:center;justify-content:center;z-index:1000;}
        .modal-content {background:var(--card);padding:30px;border-radius:var(--radius);width:90%;max-width:600px;box-shadow:var(--shadow-hover);position:relative;}
        .modal-close {position:absolute;top:10px;right:20px;font-size:28px;cursor:pointer;color:var(--muted);}
        .qr-container {text-align:center;margin:25px 0;}
        .qr-container img {width:220px;height:220px;border:10px solid white;box-shadow:0 6px 20px rgba(0,0,0,0.15);border-radius:12px;}
        
        /* Success/Error messages */
        .alert {padding:15px 20px;margin-bottom:20px;border-radius:12px;font-weight:700;}
        .alert-success {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
        .alert-error {background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
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
        <div class="menu-item active"><i class="fas fa-clipboard-check"></i><span>List Orders</span></div>
        <div class="menu-item" onclick="location='delivery.php'"><i class="fas fa-truck"></i><span>Delivery Arrangement</span></div>
        <div class="menu-item" onclick="location='report.php'"><i class="fas fa-receipt"></i><span>Report</span></div>
        <div class="menu-item" onclick="location='invoice.php'"><i class="fas fa-file-invoice"></i><span>Invoice</span></div>
    </div>
    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<div class="main">
    <div class="header">
        <div class="page-title">List Orders</div>
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

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="filter-bar">
            <input type="text" id="orderIdSearch" placeholder="Search by Order ID">
            <select id="statusFilter">
                <option value="">All Status</option>
                <option value="PENDING SHIP">Pending Ship</option>
                <option value="IN TRANSIT">In Transit</option>
                <option value="RECEIVED">Received</option>
                <option value="FULLY REFUNDED">Fully Refunded</option>
            </select>
            <button type="button" onclick="applyFilters()">Apply Filters</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>ORDER ID</th>
                    <th>ORDER DATE</th>
                    <th>ITEMS</th>
                    <th>TOTAL AMOUNT</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:60px;font-size:18px;color:var(--muted);">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $h = $order['header'];

                        // 1. Is order fully refunded?
                        $is_fully_refunded = ($h['non_refunded_count'] === 0 && $h['total_items'] > 0);

                        // 2. Has the order been shipped?
                        $db_status_upper = strtoupper(trim($h['db_status']));
                        $has_been_shipped = ($db_status_upper === 'SHIPPED' || $db_status_upper === 'RECEIVED' || $db_status_upper === 'COMPLETED');

                        // 3. Count eligible (non-refunded) items for "No Stock" button
                        $eligible_items_count = 0;
                        foreach ($order['items'] as $item) {
                            $rs = strtolower($item['refund_status']);
                            if ($rs === 'none' || $rs === null) {
                                $eligible_items_count++;
                            }
                        }

                        // 4. Determine display status and action
                        if ($is_fully_refunded) {
                            $display_status = 'FULLY REFUNDED';
                            $class = 'status-refunded';
                            $action = '<span style="color:var(--darkred);font-weight:800;">ORDER FULLY REFUNDED</span>';

                        } elseif ($has_been_shipped) {
                            if ($db_status_upper === 'RECEIVED' || $db_status_upper === 'COMPLETED') {
                                $display_status = 'RECEIVED';
                                $class = 'status-received';
                                $action = '<span style="color:var(--green);font-weight:700;">ORDER RECEIVED</span>';
                            } else {
                                $display_status = 'IN TRANSIT';
                                $class = 'status-transit';
                                $action = '<span style="color:var(--blue);font-weight:700;">IN TRANSIT</span>';
                            }

                        } elseif ($eligible_items_count > 0) {
                            // Still has at least one item that can be shipped or refunded
                            $display_status = 'PENDING SHIP';
                            $class = 'status-pending';
                            $date_param = date('Y-m-d', strtotime($h['order_date']));
                            $ship_link = "delivery.php?date=" . $date_param . "&order_id=" . $h['display_id'];
                            $action = '<a href="' . $ship_link . '" class="btn-ship">Ship Order</a>';

                        } else {
                            // Safety fallback (shouldn't happen)
                            $display_status = 'PARTIALLY REFUNDED';
                            $class = 'status-transit';
                            $action = '<span style="color:var(--orange);font-weight:700;">PARTIALLY PROCESSED</span>';
                        }
                        ?>

                        <tr class="order-row" data-order-id="<?= $h['display_id'] ?>" data-status="<?= $display_status ?>" onclick="toggleDetails(this)">
                            <td><i class="fas fa-chevron-right expand-icon"></i></td>
                            <td>#<?= $h['display_id'] ?></td>
                            <td><?= date('d-m-Y', strtotime($h['order_date'])) ?></td>
                            <td><?= $h['item_count'] ?></td>
                            <td>RM <?= number_format($h['total_amount'], 2) ?></td>
                            <td><span class="<?= $class ?>"><?= $display_status ?></span></td>
                            <td><?= $action ?></td>
                        </tr>

                        <tr class="details-row" style="display:none;">
                            <td colspan="7">
                                <div class="details-content">
                                    <table class="details-table">
                                        <thead>
                                            <tr>
                                                <th>ITEM NAME</th>
                                                <th>QUANTITY</th>
                                                <th>SUBTOTAL</th>
                                                <th>ACTION</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order['items'] as $item): 
                                                $rs = strtolower($item['refund_status'] ?? 'none');
                                                $is_requested = ($rs === 'requested');
                                                $can_no_stock_refund = ($rs === 'none') && ($display_status === 'PENDING SHIP');
                                                $is_refunded = in_array($rs, ['completed', 'paid']);
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                    <td><?= $item['quantity'] ?></td>
                                                    <td>RM <?= number_format($item['line_total'], 2) ?></td>
                                                    <td>
                                                        <?php if ($is_requested): ?>
                                                            <button type="button" class="btn-process-request"
                                                                onclick="openRefundModal(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['item_name'])) ?>', <?= $item['line_total'] ?>, 'Process Refund Request')">
                                                                Process Refund Request
                                                            </button>
                                                        <?php elseif ($can_no_stock_refund): ?>
                                                            <button type="button" class="btn-no-stock"
                                                                onclick="openRefundModal(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['item_name'])) ?>', <?= $item['line_total'] ?>, 'No Stock – Process Refund')">
                                                                No Stock → Refund
                                                            </button>
                                                        <?php elseif ($is_refunded): ?>
                                                            <span style="color:var(--green);font-weight:700;">Refunded</span>
                                                        <?php else: ?>
                                                            <span style="color:var(--muted);">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Refund Modal -->
        <div id="refund-modal" class="modal-overlay">
            <div class="modal-content">
                <span class="modal-close" onclick="closeRefundModal()">×</span>
                <h3 id="modal-title" style="text-align:center;margin-bottom:20px;">No Stock – Process Refund</h3>
                <p style="text-align:center;font-size:18px;">
                    <strong>Item:</strong> <span id="modal-item-name"></span><br>
                    <strong>Refund Amount:</strong> RM <span id="modal-amount"></span>
                </p>
                <div class="qr-container">
                    <p><strong>Scan This QR for Refund:</strong></p>
                    <img src="http://localhost/nakofi/asset/img/qr.jpeg" alt="QR Code">
                </div>
                <form action="process_no_stock_refund.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="item_id" id="form-item-id">
                    <input type="hidden" name="refund_amount" id="form-refund-amount">
                    <label style="font-weight:700;display:block;margin:20px 0 10px;">Upload Refund Receipt (Image/PDF):</label>
                    <input type="file" name="receipt" accept="image/*,application/pdf" required style="width:100%;padding:12px;border:1px solid var(--border);border-radius:12px;">
                    <div style="text-align:center;margin-top:30px;">
                        <button type="submit" style="background:var(--green);color:white;padding:14px 40px;border:none;border-radius:50px;font-weight:700;font-size:18px;cursor:pointer;">
                            Confirm Refund Processed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>

<script>
function toggleDetails(row) {
    const detailsRow = row.nextElementSibling;
    if (detailsRow.style.display === 'table-row') {
        detailsRow.style.display = 'none';
        row.classList.remove('expanded');
    } else {
        detailsRow.style.display = 'table-row';
        row.classList.add('expanded');
    }
}

function applyFilters() {
    const idSearch = document.getElementById('orderIdSearch').value.trim().toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;

    document.querySelectorAll('.order-row').forEach(row => {
        const orderId = row.getAttribute('data-order-id').toLowerCase();
        const status = row.getAttribute('data-status');

        const matchesId = idSearch === '' || orderId.includes(idSearch);
        const matchesStatus = statusFilter === '' || status === statusFilter;

        row.style.display = (matchesId && matchesStatus) ? '' : 'none';
        if (!matchesId || !matchesStatus) {
            row.nextElementSibling.style.display = 'none';
            row.classList.remove('expanded');
        }
    });
}

document.getElementById('orderIdSearch').addEventListener('input', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);

function openRefundModal(itemId, itemName, amount, title) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-item-name').textContent = itemName;
    document.getElementById('modal-amount').textContent = parseFloat(amount).toFixed(2);
    document.getElementById('form-item-id').value = itemId;
    document.getElementById('form-refund-amount').value = amount;
    document.getElementById('refund-modal').style.display = 'flex';
}

function closeRefundModal() {
    document.getElementById('refund-modal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('refund-modal');
    if (event.target === modal) closeRefundModal();
}

function logoutConfirm() {
    document.body.style.pointerEvents = 'none';
    document.querySelector('.main').style.opacity = '0.6';
    setTimeout(() => location = 'login.php', 800);
}
</script>
</body>
</html>