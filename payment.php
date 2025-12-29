<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ================= USER INFO =================
$stmt = $conn->prepare("SELECT name, role, email FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$name = strtoupper($user['name'] ?? 'User');

// ================= FETCH UNPAID ORDERS GROUPED BY SUPPLIER =================
$suppliers_data = [];

$sql = "
    SELECT 
        s.supID,
        s.name AS supplier_name,
        o.orders_id,
        o.order_date,
        o.item_name,
        o.quantity,
        o.total AS line_total
    FROM orders o
    JOIN supplier_orders so ON o.orders_id = so.linked_order_id
    JOIN supplier s ON so.supplier_id = s.supID
    WHERE o.payment_status = 'unpaid'
    ORDER BY s.name ASC, o.orders_id DESC, o.order_date DESC
";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sup_id = $row['supID'];

        if (!isset($suppliers_data[$sup_id])) {
            $suppliers_data[$sup_id] = [
                'supplier_name' => $row['supplier_name'],
                'subtotal'      => 0,
                'orders'        => []
            ];
        }

        $order_id = $row['orders_id'];

        if (!isset($suppliers_data[$sup_id]['orders'][$order_id])) {
            $suppliers_data[$sup_id]['orders'][$order_id] = [
                'order_id' => $order_id,
                'date'     => date('d M Y, h:i A', strtotime($row['order_date'])),
                'items'    => []
            ];
        }

        $price_per_unit = $row['quantity'] > 0 ? $row['line_total'] / $row['quantity'] : 0;

        $suppliers_data[$sup_id]['orders'][$order_id]['items'][] = [
            'item_name'      => $row['item_name'],
            'quantity'       => $row['quantity'],
            'price_per_unit' => number_format($price_per_unit, 2),
            'total'          => $row['line_total']
        ];

        $suppliers_data[$sup_id]['subtotal'] += $row['line_total'];
    }
}

// Calculate tax (8%) and grand total for each supplier
foreach ($suppliers_data as $sup_id => &$data) {
    $data['tax'] = round($data['subtotal'] * 0.08, 2);
    $data['grand_total'] = $data['subtotal'] + $data['tax'];
    // Format for display and JS with exactly 2 decimals
    $data['grand_total_formatted'] = number_format($data['grand_total'], 2);
}
unset($data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - Payment</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{
            --white:#fff;--charcoal:#212529;--bg:#F9FAFB;--border:#E5E7EB;--text:#111827;--muted:#6B7280;
            --radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);
            --shadow:0 6px 20px rgba(0,0,0,.08);--shadow-hover:0 15px 35px rgba(0,0,0,.12);
            --success:#28a745;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

        .sidebar{width:280px;background:var(--white);border-right:1px solid var(--border);position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);display:flex;flex-direction:column;z-index:10;}
        .logo{text-align:center;margin-bottom:40px;}
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}
        .menu-container{flex:1;overflow-y:auto;padding-right:8px;margin-bottom:20px;}
        .menu-item{display:flex;align-items:center;gap:16px;padding:14px 20px;color:var(--text);font-size:16px;font-weight:700;border-radius:16px;cursor:pointer;transition:var(--trans);margin-bottom:8px;}
        .menu-item i{font-size:22px;color:#D1D5DB;}
        .menu-item:hover{background:#f1f5f9;}
        .menu-item.active{background:var(--charcoal);color:var(--white);}
        .menu-item.active i{color:var(--white);}
        .logout-item{flex-shrink:0;padding-top:20px;border-top:1px solid var(--border);}
        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}

        .main{margin-left:280px;width:calc(100% - 280px);padding:40px 60px;min-height:100vh;}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
        .page-title{font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;}
        .user-profile{position:relative;}
        .user-btn{display:flex;align-items:center;gap:10px;background:var(--white);border:2px solid var(--charcoal);padding:10px 24px;border-radius:50px;font-weight:700;cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{position:absolute;top:100%;right:0;background:var(--white);min-width:260px;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;opacity:0;visibility:hidden;transform:translateY(10px);transition:var(--trans);margin-top:12px;text-align:left;z-index:20;border:1px solid var(--border);}
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}

        .payment-content{max-width:1400px;margin:0 auto;}
        .supplier-section{margin-bottom:80px;padding-bottom:60px;border-bottom:3px dashed #ddd;}
        .supplier-section:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0;}
        .payment-grid{display:grid;grid-template-columns:1fr 420px;gap:50px;}
        .summary-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:40px;box-shadow:var(--shadow);}
        .item-row{display:flex;justify-content:space-between;align-items:center;padding:16px 0;border-bottom:1px solid #eee;}
        .item-name{font-size:20px;font-weight:700;}
        .item-qty{color:var(--muted);font-size:15px;}
        .item-price{font-size:19px;font-weight:700;}
        .summary-row{display:flex;justify-content:space-between;padding:14px 0;font-size:18px;border-bottom:1px solid #eee;}
        .summary-row.total{font-size:24px;font-weight:900;border:none;padding-top:24px;margin-top:20px;border-top:3px double #ddd;}

        .methods-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        .method{background:var(--white);border:2px solid var(--border);border-radius:var(--radius);padding:32px;text-align:center;cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
        .method.selected{border-color:var(--success);background:#f0fdf4;box-shadow:0 0 0 4px rgba(40,167,69,0.15);}
        .method:hover{transform:translateY(-10px);box-shadow:var(--shadow-hover);}
        .method i{font-size:48px;color:var(--success);margin-bottom:16px;}
        .method h3{font-size:20px;color:var(--charcoal);}

        .pay-btn{font-family:'Playfair Display',serif;width:100%;margin-top:40px;padding:18px;background:var(--charcoal);color:var(--white);border:none;border-radius:var(--radius);font-size:20px;font-weight:700;cursor:pointer;transition:var(--trans);}
        .pay-btn:hover{background:#111;}
        .pay-btn:disabled{background:#6c757d;cursor:not-allowed;opacity:0.7;}

        .no-order{text-align:center;padding:100px;font-size:20px;color:var(--muted);}

        .popup-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:2000;align-items:center;justify-content:center;}
        .popup{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--white);width:90%;max-width:480px;border-radius:var(--radius);box-shadow:0 20px 60px rgba(0,0,0,0.3);z-index:2001;padding:40px 32px;text-align:center;}
        .popup h2{font-size:26px;margin-bottom:20px;color:var(--charcoal);}
        .popup-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin:30px 0;}
        .popup-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px 12px;background:var(--white);border:2px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:var(--trans);font-weight:700;font-size:15px;height:90px;box-shadow:var(--shadow);}
        .popup-btn:hover{transform:translateY(-8px);box-shadow:var(--shadow-hover);border-color:var(--success);}
        .popup-btn img{height:36px;object-fit:contain;margin-bottom:8px;}
        .popup-actions{display:flex;gap:16px;justify-content:center;margin-top:30px;}
        .popup-close, .confirm-btn{padding:14px 32px;background:var(--charcoal);color:white;border:none;border-radius:50px;cursor:pointer;font-weight:700;font-size:16px;}
        .confirm-btn:hover, .popup-close:hover{background:#495057;}

        @media (max-width:1100px){.payment-grid{grid-template-columns:1fr;gap:40px;}}
        @media (max-width:992px){.main{margin-left:0;padding:30px 20px;}.methods-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>

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
        <div class="menu-item" onclick="location='track.php'"><i class="fas fa-truck"></i><span>Delivery Tracking</span></div>
        <div class="menu-item active"><i class="fas fa-credit-card"></i><span>Payment</span></div>
        <div class="menu-item" onclick="location='reporting.php'"><i class="fas fa-chart-line"></i><span>Inventory Audit Reports</span></div>
        <div class="menu-item" onclick="location='register.php'"><i class="fas fa-users-cog"></i><span>User Register</span></div>
    </div>
    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<div class="main">
    <div class="page-header">
        <div class="page-title">Payment</div>
        <div class="user-profile">
            <button class="user-btn">
                <i class="fas fa-user-circle"></i> <?= $name ?>
            </button>
            <div class="user-tooltip">
                <div class="detail"><strong>ID:</strong> <?= htmlspecialchars($user_id) ?></div>
                <div class="detail"><?= $name ?></div>
                <div class="detail"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
        </div>
    </div>

    <div class="payment-content">
        <?php if (!empty($suppliers_data)): ?>
            <?php foreach ($suppliers_data as $sup_id => $supplier): ?>
                <div class="supplier-section">
                    <div class="payment-grid">
                        <div class="summary-card">
                            <h2 style="font-size:28px;margin-bottom:30px;text-align:center;">
                                PAYMENT TO: <?= htmlspecialchars($supplier['supplier_name']) ?>
                            </h2>
                            <p style="text-align:center;color:var(--muted);margin-bottom:25px;">
                                <?= count($supplier['orders']) ?> order<?= count($supplier['orders']) > 1 ? 's' : '' ?> pending payment
                            </p>

                            <?php foreach ($supplier['orders'] as $order): ?>
                                <div style="font-weight:700;margin:24px 0 10px;color:var(--charcoal);border-bottom:2px solid #eee;padding-bottom:8px;">
                                    Order #<?= $order['order_id'] ?> — <?= $order['date'] ?>
                                </div>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="item-row">
                                        <div>
                                            <div class="item-name"><?= ucwords(str_replace('_', ' ', $item['item_name'])) ?></div>
                                            <div class="item-qty"><?= $item['quantity'] ?> × RM <?= $item['price_per_unit'] ?></div>
                                        </div>
                                        <div class="item-price">RM <?= number_format($item['total'], 2) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>

                            <div class="summary-row"><span>Subtotal</span><strong>RM <?= number_format($supplier['subtotal'], 2) ?></strong></div>
                            <div class="summary-row"><span>Service Tax (8%)</span><strong>RM <?= number_format($supplier['tax'], 2) ?></strong></div>
                            <div class="summary-row total"><span>Grand Total</span><strong>RM <?= $supplier['grand_total_formatted'] ?></strong></div>
                        </div>

                        <div>
                            <h2 style="margin-bottom:30px;font-size:26px;text-align:center;">Choose Payment Method</h2>
                            <div class="methods-grid" data-supplier="<?= $sup_id ?>">
                                <div class="method" onclick="selectMethod(this, 'card', <?= $sup_id ?>)"><i class="fas fa-credit-card"></i><h3>Credit / Debit Card</h3></div>
                                <div class="method" onclick="selectMethod(this, 'ewallet', <?= $sup_id ?>)"><i class="fas fa-mobile-alt"></i><h3>E-Wallet</h3></div>
                                <div class="method" onclick="selectMethod(this, 'bank', <?= $sup_id ?>)"><i class="fas fa-university"></i><h3>Online Banking</h3></div>
                                <div class="method" onclick="selectMethod(this, 'cash', <?= $sup_id ?>)"><i class="fas fa-money-bill-wave"></i><h3>Cash / COD</h3></div>
                            </div>
                            <button class="pay-btn" id="payBtn_<?= $sup_id ?>" disabled 
                                onclick="proceedPayment(<?= $sup_id ?>, '<?= $supplier['grand_total_formatted'] ?>')">
                                PAY <?= htmlspecialchars($supplier['supplier_name']) ?> • RM <?= $supplier['grand_total_formatted'] ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-order">
                <i class="fas fa-receipt" style="font-size:60px;margin-bottom:20px;opacity:0.4;"></i><br>
                No unpaid auto-orders found.<br>
                Perform a stock take to generate new orders.
            </div>
        <?php endif; ?>
    </div>

    <!-- Popups -->
    <div class="popup-overlay" id="ewalletOverlay" onclick="closePopup('ewallet')"></div>
    <div class="popup" id="ewalletPopup">
        <h2>E-Wallet Payment</h2>
        <div class="popup-grid">
            <div class="popup-btn" onclick="confirmAndProceed()"><img src="<?= BASE_URL ?>asset/img/tng.avif" alt="TNG"> Touch 'n Go</div>
            <div class="popup-btn" onclick="confirmAndProceed()"><img src="<?= BASE_URL ?>asset/img/grab.jpg" alt="Grab"> GrabPay</div>
            <div class="popup-btn" onclick="confirmAndProceed()"><img src="<?= BASE_URL ?>asset/img/shoppe.png" alt="Shopee"> ShopeePay</div>
        </div>
        <div class="popup-actions">
            <button class="popup-close" onclick="closePopup('ewallet')">Close</button>
        </div>
    </div>

    <div class="popup-overlay" id="bankOverlay" onclick="closePopup('bank')"></div>
    <div class="popup" id="bankPopup">
        <h2>Select Your Bank</h2>
        <div class="popup-grid">
            <div class="popup-btn" onclick="openBank('https://www.maybank2u.com.my')"><img src="<?= BASE_URL ?>asset/img/maybank.png" alt="Maybank"> Maybank2u</div>
            <div class="popup-btn" onclick="openBank('https://www.cimbclicks.com.my')"><img src="<?= BASE_URL ?>asset/img/cimb.png" alt="CIMB"> CIMB Clicks</div>
            <div class="popup-btn" onclick="openBank('https://www.rhbgroup.com')"><img src="<?= BASE_URL ?>asset/img/rhb.png" alt="RHB"> RHB Now</div>
            <div class="popup-btn" onclick="openBank('https://www.hlb.com.my')"><img src="<?= BASE_URL ?>asset/img/HLB.jpg" alt="Hong Leong"> Hong Leong</div>
            <div class="popup-btn" onclick="openBank('https://www.bankislam.biz')"><img src="<?= BASE_URL ?>asset/img/bimb.png" alt="Bank Islam"> Bank Islam</div>
            <div class="popup-btn" onclick="openBank('https://www.bsn.com.my')"><img src="<?= BASE_URL ?>asset/img/bsn.jpg" alt="BSN"> BSN Online</div>
        </div>
        <div class="popup-actions">
            <button class="popup-close" onclick="closePopup('bank')">Close</button>
        </div>
    </div>

    <div class="popup-overlay" id="cashOverlay" onclick="closePopup('cash')"></div>
    <div class="popup" id="cashPopup">
        <h2>Cash / COD Payment</h2>
        <p>Please prepare the exact amount when the delivery arrives.</p>
        <div class="popup-actions">
            <button class="popup-close" onclick="closePopup('cash')">Close</button>
            <button class="confirm-btn" onclick="confirmAndProceed()">Confirm</button>
        </div>
    </div>
</div>

<script>
let selectedMethods = {};

function selectMethod(el, method, sup_id) {
    // Remove selected from siblings
    el.closest('.methods-grid').querySelectorAll('.method').forEach(m => m.classList.remove('selected'));
    el.classList.add('selected');
    
    selectedMethods[sup_id] = method;
    document.getElementById('payBtn_' + sup_id).disabled = false;
}

function proceedPayment(sup_id, amount_str) {
    const method = selectedMethods[sup_id];
    if (!method) {
        alert('Please select a payment method.');
        return;
    }

    // Store as string to preserve exact decimal places (e.g., 108.80)
    sessionStorage.setItem('paying_supplier_id', sup_id);
    sessionStorage.setItem('payment_amount', amount_str);  // Now guaranteed correct like "108.80"

    if (method === 'card') {
        location.href = 'card.php';
    } else if (method === 'ewallet') {
        document.getElementById('ewalletOverlay').style.display = 'block';
        document.getElementById('ewalletPopup').style.display = 'block';
    } else if (method === 'bank') {
        document.getElementById('bankOverlay').style.display = 'block';
        document.getElementById('bankPopup').style.display = 'block';
    } else if (method === 'cash') {
        document.getElementById('cashOverlay').style.display = 'block';
        document.getElementById('cashPopup').style.display = 'block';
    }
}

function closePopup(type) {
    document.getElementById(type + 'Popup').style.display = 'none';
    document.getElementById(type + 'Overlay').style.display = 'none';
}

function openBank(url) {
    window.open(url, '_blank');
    closePopup('bank');
    setTimeout(confirmAndProceed, 1500);
}

function confirmAndProceed() {
    const sup_id = sessionStorage.getItem('paying_supplier_id');
    if (sup_id) {
        location.href = 'payment_success.php?supplier=' + sup_id;
    }
}

function logoutConfirm() {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
    setTimeout(() => location.href = 'login.php', 800);
}
</script>

</body>
</html>