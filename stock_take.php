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

// ================= PRODUCTS + AUTO RULES =================
$products = [];
$sql = "
    SELECT p.id, p.product_name, p.image_path,
           COALESCE(r.threshold, 5) AS threshold,
           COALESCE(r.target_quantity, 10) AS target_qty
    FROM products p
    LEFT JOIN auto_order_rules r ON p.id = r.product_id
    ORDER BY p.product_name
";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) {
    $display_name = str_replace('_', ' ', ucwords($r['product_name'], '_'));
    $key = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $display_name)));
    $products[$key] = [
        'id' => $r['id'],
        'product_name' => $r['product_name'],
        'display_name' => $display_name,
        'image_path' => $r['image_path'],
        'threshold' => (int)$r['threshold'],
        'target_qty' => (int)$r['target_qty']
    ];
}

// ================= SUPPLIERS PER PRODUCT =================
$product_suppliers = [];
$sql = "
    SELECT ps.product_id, s.supID, s.name AS supplier_name,
           ps.unit_price, ps.is_primary
    FROM product_suppliers ps
    JOIN supplier s ON ps.supplier_id = s.supID
    ORDER BY ps.product_id, ps.is_primary DESC
";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) {
    $product_suppliers[$r['product_id']][] = $r;
}

// ================= LOAD DRAFT (ALWAYS) =================
$draft_counts = [];
$stmt = $conn->prepare("SELECT draft_data FROM stock_drafts WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $draft_counts = json_decode($row['draft_data'], true) ?: [];
    }
    $stmt->close();
}

// ================= FORM HANDLING =================
$items_to_order = [];
$show_supplier_modal = false;
$order_success = false;
$no_order_needed = false;

// Save Draft - Redirect to stock_drafts.php
if (isset($_POST['save_draft'])) {
    $counts = [];
    foreach ($products as $key => $p) {
        $counts[$key] = max(0, (int)($_POST[$key] ?? ($draft_counts[$key] ?? 0)));
    }
    $json = json_encode($counts);
    $stmt = $conn->prepare("REPLACE INTO stock_drafts (user_id, draft_data) VALUES (?, ?)");
    $stmt->bind_param("ss", $user_id, $json);
    $stmt->execute();
    $stmt->close();

    // Success message + redirect to drafts page
    echo "<script>
        window.location.href = 'stock_drafts.php';
    </script>";
    exit;
}

// Confirm Stock Count - Updates Inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $conn->autocommit(false);
    try {
        foreach ($products as $key => $p) {
            $qty = max(0, (int)($_POST[$key] ?? ($draft_counts[$key] ?? 0)));

            // UPDATE OR INSERT INTO inventory TABLE
            $stmt = $conn->prepare("
                INSERT INTO inventory 
                    (item_name, stock_quantity, unit_price, last_updated, product_id)
                VALUES 
                    (?, ?, 
                     (SELECT unit_price FROM product_suppliers WHERE product_id = ? AND is_primary = 1 LIMIT 1), 
                     NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    stock_quantity = VALUES(stock_quantity),
                    unit_price = VALUES(unit_price),
                    last_updated = NOW()
            ");
            $stmt->bind_param("siii", $p['product_name'], $qty, $p['id'], $p['id']);
            $stmt->execute();
            $stmt->close();

            // Check if below threshold → suggest reorder
            if ($qty < $p['threshold']) {
                $need = $p['target_qty'] - $qty;
                if ($need > 0) {
                    $items_to_order[] = [
                        'product_id' => $p['id'],
                        'item_name' => $p['product_name'],
                        'display_name' => $p['display_name'],
                        'quantity' => $need
                    ];
                }
            }
        }

        // Delete draft after confirm
        $stmt = $conn->prepare("DELETE FROM stock_drafts WHERE user_id = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        if (!empty($items_to_order)) {
            $show_supplier_modal = true;
        } else {
            $no_order_needed = true;
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Failed to confirm stock count: " . addslashes($e->getMessage()) . "');</script>";
    }
    $conn->autocommit(true);
}

// ================= PLACE ORDER (GROUPED BY SUPPLIER) - FIXED =================
if (isset($_POST['place_order'])) {
    $conn->autocommit(false);
    try {
        $orders_by_supplier = [];
        foreach ($_POST['supplier'] as $pid => $supplier_id) {
            $item_name = $_POST['item'][$pid];
            $quantity = (int)$_POST['qty'][$pid];
            $price = (float)$_POST['price'][$pid];
            $total = $quantity * $price;

            if (!isset($orders_by_supplier[$supplier_id])) {
                $orders_by_supplier[$supplier_id] = [
                    'items' => [],
                    'total_amount' => 0,
                    'item_count' => 0
                ];
            }

            $orders_by_supplier[$supplier_id]['items'][] = [
                'product_id' => $pid,
                'item_name' => $item_name,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $total
            ];
            $orders_by_supplier[$supplier_id]['total_amount'] += $total;
            $orders_by_supplier[$supplier_id]['item_count']++;
        }

        foreach ($orders_by_supplier as $supplier_id => $order_data) {
            // Insert into auto_order_headers (internal tracking)
            $stmt = $conn->prepare("
                INSERT INTO auto_order_headers (created_by, total_items, total_amount)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("sid", $user_id, $order_data['item_count'], $order_data['total_amount']);
            $stmt->execute();
            $order_group_id = $conn->insert_id; // This is the linked_order_id
            $stmt->close();

            // Insert each item into 'orders' (internal restock log)
            foreach ($order_data['items'] as $item) {
                $stmt = $conn->prepare("
                    INSERT INTO orders 
                    (orders_id, item_name, quantity, total, order_date, status)
                    VALUES (?, ?, ?, ?, NOW(), 'PENDING PAYMENT')
                ");
                $stmt->bind_param("isid", $order_group_id, $item['item_name'], $item['quantity'], $item['total']);
                $stmt->execute();
                $stmt->close();
            }

            // FIXED: Insert into supplier_orders correctly
            foreach ($order_data['items'] as $item) {
                $stmt = $conn->prepare("
                    INSERT INTO supplier_orders 
                    (item_name, quantity, total, order_date, status, linked_order_id, supplier_id)
                    VALUES (?, ?, ?, NOW(), 'Pending Ship', ?, ?)
                ");
                $stmt->bind_param("sidii", $item['item_name'], $item['quantity'], $item['total'], $order_group_id, $supplier_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        $order_success = true;
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Order failed: " . addslashes($e->getMessage()) . "');</script>";
    }
    $conn->autocommit(true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - Stock Take</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;--radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);--shadow:0 6px 20px rgba(0,0,0,.08);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

        .sidebar{width:280px;background:var(--white);border-right:1px solid var(--border);position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);display:flex;flex-direction:column;z-index:10;transition:transform .4s ease;}
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

        .main{margin-left:280px;width:calc(100% - 280px);padding:40px 60px;}

        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
        .page-title{font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;}
        .user-profile{position:relative;}
        .user-btn{display:flex;align-items:center;gap:10px;background:var(--white);border:2px solid var(--charcoal);padding:10px 24px;border-radius:50px;font-weight:700;cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{position:absolute;top:100%;right:0;background:var(--white);min-width:260px;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;opacity:0;visibility:hidden;transform:translateY(10px);transition:var(--trans);margin-top:12px;z-index:20;border:1px solid var(--border);}
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}

        .top-controls{display:flex;gap:20px;align-items:center;margin-bottom:40px;flex-wrap:wrap;}
        .search-wrapper input{font-family:'Playfair Display',serif;}
        .search-wrapper{flex:1;min-width:280px;}
        .search-box{width:100%;padding:16px 24px;border:1px solid var(--border);border-radius:50px;font-size:16px;background:var(--white);}
        .add-product-btn{background:var(--charcoal);color:white;padding:16px 32px;border-radius:50px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:10px;}
        .add-product-btn:hover{background:#111;}

        .cards-row{display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:24px;margin-bottom:60px;}

        .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;text-align:center;box-shadow:var(--shadow);transition:var(--trans);}
        .card:hover{transform:translateY(-10px);box-shadow:0 12px 30px rgba(0,0,0,.12);}
        .card img{width:100px;height:130px;object-fit:cover;border-radius:12px;margin-bottom:16px;}
        .card h3{font-size:19px;color:var(--charcoal);margin:12px 0;}
        .target-info{font-size:14px;color:var(--muted);margin-bottom:16px;}
        .controls{display:flex;align-items:center;justify-content:center;gap:16px;}
        .controls button{width:44px;height:44px;border:2px solid var(--charcoal);border-radius:50%;background:transparent;color:var(--charcoal);font-size:22px;cursor:pointer;transition:var(--trans);}
        .controls button:hover{background:var(--charcoal);color:white;}
        .count{font-size:30px;font-weight:700;color:var(--charcoal);min-width:70px;}

        .actions{display:flex;justify-content:center;gap:30px;margin-top:40px;}
        .draft-btn,.confirm-btn{font-family:'Playfair Display',serif;padding:16px 50px;border:2px solid var(--charcoal);border-radius:50px;font-size:17px;font-weight:700;cursor:pointer;transition:var(--trans);}
        .draft-btn{background:transparent;color:var(--charcoal);}
        .draft-btn:hover{background:var(--charcoal);color:white;}
        .confirm-btn{background:var(--charcoal);color:white;}
        .confirm-btn:hover{background:#111;}

        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;z-index:1000;overflow-y:auto;padding:20px 0;}
        .modal.active{display:flex;}
        .modal-content{background:var(--white);border-radius:var(--radius);max-width:660px;width:95%;max-height:90vh;box-shadow:var(--shadow);display:flex;flex-direction:column;}
        .modal-header{padding:30px 40px 20px;text-align:center;border-bottom:1px solid var(--border);}
        .modal-header h3{font-size:26px;color:var(--charcoal);}
        .modal-body{flex:1;overflow-y:auto;padding:20px 40px;}
        .modal-footer{padding:20px 40px 30px;display:flex;justify-content:center;gap:20px;border-top:1px solid var(--border);}
        footer{text-align:center;padding:30px 0 20px;color:var(--muted);font-size:14px;}

        .supplier-item{font-family:'Playfair Display',serif;background:#f9fafb;border-radius:16px;padding:24px;margin-bottom:24px;box-shadow:0 4px 12px rgba(0,0,0,0.05);transition:var(--trans);}
        .supplier-item:hover{transform:translateY(-4px);box-shadow:0 8px 20px rgba(0,0,0,0.1);}
        .supplier-item strong{font-size:19px;color:var(--charcoal);display:block;margin-bottom:12px;}
        .supplier-item label{font-size:15px;color:var(--muted);margin-bottom:8px;display:block;}
        .supplier-item select{width:100%;padding:14px 16px;border:1px solid var(--border);border-radius:12px;background:white;font-size:16px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
        .supplier-item select:focus{outline:none;border-color:#111827;box-shadow:0 0 0 3px rgba(17,24,39,0.1);}
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>

    <div class="menu-container">
        <div class="menu-item" onclick="location.href='<?php echo $is_leader ? 'staff_leader.php' : 'staff.php'; ?>'">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </div>
        <div class="menu-item active">
            <i class="fas fa-clipboard-list"></i><span>Stock Take</span>
        </div>
        <div class="menu-item" onclick="location.href='stock_count.php'">
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
            <i class="fas fa-users-cog"></i><span>Staff Register</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </div>
    </div>
</div>

<div class="main">
    <div class="page-header">
        <div class="page-title">Stock Take</div>
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

    <div class="top-controls">
        <div class="search-wrapper">
            <input type="text" id="searchInput" class="search-box" placeholder="Search Product Name" autocomplete="off">
        </div>
        <a href="add_product.php" class="add-product-btn">
            <i class="fas fa-plus"></i> Add Product
        </a>
    </div>

    <div class="container">
        <form method="POST" id="mainForm">
            <div class="cards-row" id="cardsRow">
                <?php foreach ($products as $key => $p): 
                    $current_count = $draft_counts[$key] ?? 0;
                ?>
                <div class="card">
                    <img src="<?= BASE_URL ?><?= htmlspecialchars($p['image_path']) ?>" 
                         alt="<?= htmlspecialchars($p['display_name']) ?>"
                         onerror="this.src='<?= BASE_URL ?>asset/img/placeholder.jpg';">
                    <h3><?= strtoupper(htmlspecialchars($p['display_name'])) ?></h3>
                    <div class="target-info">Target Quantity Stock: <strong><?= $p['target_qty'] ?></strong></div>
                    <div class="controls">
                        <button type="button" onclick="change('<?= $key ?>', -1)">−</button>
                        <span class="count" id="<?= $key ?>-count"><?= $current_count ?></span>
                        <button type="button" onclick="change('<?= $key ?>', 1)">+</button>
                    </div>
                    <input type="hidden" name="<?= $key ?>" id="<?= $key ?>" value="<?= $current_count ?>">
                </div>
                <?php endforeach; ?>
            </div>

            <div class="actions">
                <button type="submit" name="save_draft" class="draft-btn">Save Draft</button>
                <button type="submit" name="confirm" class="confirm-btn">Confirm Stock Count</button>
            </div>
        </form>
    </div>

    <!-- Supplier Selection Modal -->
    <?php if ($show_supplier_modal): ?>
    <div class="modal active" id="supplierModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Please choose a supplier for each item below threshold</h3>
            </div>
            <div class="modal-body">
                <form method="POST" id="supplierForm">
                    <?php foreach ($items_to_order as $i): 
                        $pid = $i['product_id'];
                        $suppliers = $product_suppliers[$pid] ?? [];
                    ?>
                    <div class="supplier-item">
                        <strong><?= strtoupper(htmlspecialchars($i['display_name'])) ?> × <?= $i['quantity'] ?></strong>
                        <label>Select Supplier:</label>
                        <select name="supplier[<?= $pid ?>]" required>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['supID'] ?>" data-price="<?= $s['unit_price'] ?>" <?= $s['is_primary'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['supplier_name']) ?> - RM <?= number_format($s['unit_price'], 2) ?>
                                <?= $s['is_primary'] ? ' (Primary)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="item[<?= $pid ?>]" value="<?= htmlspecialchars($i['item_name']) ?>">
                        <input type="hidden" name="qty[<?= $pid ?>]" value="<?= $i['quantity'] ?>">
                        <input type="hidden" name="price[<?= $pid ?>]" id="price-<?= $pid ?>" value="<?= $suppliers[0]['unit_price'] ?? 0 ?>">
                    </div>
                    <?php endforeach; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="draft-btn" id="cancelBtn">Cancel</button>
                <button type="submit" name="place_order" form="supplierForm" class="confirm-btn">Place Order</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Success Popup (Order Placed) -->
    <?php if ($order_success): ?>
    <div class="modal active">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Success!</h3>
            </div>
            <div class="modal-body" style="text-align:center;padding:50px;">
                <i class="fas fa-check-circle" style="font-size:80px;color:#10b981;margin-bottom:20px;"></i>
                <p style="font-size:20px;font-weight:600;">
                    Your auto-order has been created successfully.
                </p>
                <p style="font-size:18px;color:#6b7280;margin-top:10px;">
                    Waiting for staff leader to review and make payment.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="confirm-btn" onclick="window.location.href='stock_count.php'">
                    OK
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- No Order Needed Popup -->
    <?php if ($no_order_needed): ?>
    <div class="modal active" id="noOrderModal">
        <div class="modal-content" style="max-width:520px;text-align:center;">
            <div class="modal-header">
                <h3>All Stock Sufficient!</h3>
            </div>
            <div class="modal-body" style="padding:60px 40px;">
                <i class="fas fa-check-circle" style="font-size:80px;color:#10b981;margin-bottom:24px;display:block;"></i>
                <p style="font-size:20px;font-weight:600;color:#374151;">
                    No items are below the threshold.<br>
                    Great job keeping everything in stock! ✓
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="confirm-btn" onclick="closeNoOrderModal()">OK</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</div>

<script>
function change(key, delta) {
    const countEl = document.getElementById(key + '-count');
    const inputEl = document.getElementById(key);
    let value = parseInt(countEl.textContent) + delta;
    if (value < 0) value = 0;
    countEl.textContent = value;
    inputEl.value = value;
}

// Update price when supplier changes
document.querySelectorAll('select[name^="supplier["]').forEach(select => {
    select.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        const pid = this.name.match(/\[(.*?)\]/)[1];
        document.getElementById('price-' + pid).value = price;
    });
});

// Cancel button in supplier modal
document.getElementById('cancelBtn')?.addEventListener('click', function() {
    document.getElementById('supplierModal')?.classList.remove('active');
});

// Close No Order Modal
function closeNoOrderModal() {
    document.getElementById('noOrderModal')?.classList.remove('active');
}

// Auto-close "No Order Needed" modal after 4 seconds
<?php if ($no_order_needed): ?>
setTimeout(() => {
    closeNoOrderModal();
}, 4000);
<?php endif; ?>

// Live Search
document.getElementById('searchInput').addEventListener('input', function() {
    const term = this.value.trim().toLowerCase();
    const cards = document.querySelectorAll('.card');
    let visibleCount = 0;

    cards.forEach(card => {
        const name = card.querySelector('h3').textContent.toLowerCase();
        if (name.includes(term)) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Get or create the "no results" message element
    let noResultsMsg = document.getElementById('noResultsMessage');
    if (!noResultsMsg) {
        noResultsMsg = document.createElement('div');
        noResultsMsg.id = 'noResultsMessage';
        noResultsMsg.style.cssText = `
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            font-size: 22px;
            color: #6B7280;
            font-weight: 600;
        `;
        document.getElementById('cardsRow').appendChild(noResultsMsg);
    }

    if (visibleCount === 0 && term !== '') {
        noResultsMsg.innerHTML = `
            <i class="fas fa-search" style="font-size:48px; color:#9CA3AF; margin-bottom:20px; display:block;"></i>
            <div>No products found for "<strong>${this.value.trim()}</strong>"</div>
            <div style="font-size:18px; margin-top:12px; color:#9CA3AF;">
                Try searching with words like:<br>
                <strong>coffee</strong>, <strong>milk</strong>, <strong>sugar</strong>, <strong>syrup</strong>, <strong>cup</strong>, <strong>tea</strong>, <strong>bean</strong>
            </div>
        `;
        noResultsMsg.style.display = 'block';
    } else {
        noResultsMsg.style.display = 'none';
    }
});

function logoutConfirm() {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
    setTimeout(() => location.href = 'login.php', 800);
}
</script>

</body>
</html>