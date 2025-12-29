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

// Fetch distinct order batches (using linked_order_id)
$batches = [];
$sql = "
    SELECT DISTINCT linked_order_id, MIN(order_date) AS order_date
    FROM supplier_orders 
    WHERE supplier_id = ? AND linked_order_id IS NOT NULL
    GROUP BY linked_order_id
    ORDER BY order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $supID);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $order_id = $row['linked_order_id'];

    $status_sql = "
        SELECT 
            COUNT(*) AS total_items,
            SUM(CASE WHEN status = 'Pending Ship' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status IN ('Shipped', 'In Transit', 'Received') THEN 1 ELSE 0 END) AS shipped_count
        FROM supplier_orders 
        WHERE linked_order_id = ? AND supplier_id = ?
    ";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("ii", $order_id, $supID);
    $status_stmt->execute();
    $status_row = $status_stmt->get_result()->fetch_assoc();
    $status_stmt->close();

    $total = $status_row['total_items'];
    $pending = $status_row['pending_count'];
    $shipped = $status_row['shipped_count'];

    if ($pending > 0) {
        $batch_status = 'pending';
    } elseif ($shipped == $total && $total > 0) {
        $batch_status = 'shipped';
    } else {
        $batch_status = 'partial';
    }

    $batches[] = [
        'order_id' => $order_id,
        'order_date' => date('d M Y', strtotime($row['order_date'])),
        'status' => $batch_status,
        'total_items' => $total,
        'pending_items' => $pending
    ];
}
$stmt->close();

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['linked_order_id'])) {
    $linked_order_id = (int)$_POST['linked_order_id'];

    $check_stmt = $conn->prepare("
        SELECT COUNT(*) AS pending_count 
        FROM supplier_orders 
        WHERE linked_order_id = ? AND supplier_id = ? AND status = 'Pending Ship'
    ");
    $check_stmt->bind_param("ii", $linked_order_id, $supID);
    $check_stmt->execute();
    $check = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($check['pending_count'] == 0) {
        $error = "This order has already been shipped or has no pending items.";
    } else {
        $receiver_name = trim($_POST['receiver_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $courier = trim($_POST['courier'] ?? '');
        $ship_date = $_POST['ship_date'] ?? '';

        if ($receiver_name && $address && $courier && $ship_date) {
            try {
                $conn->autocommit(false);

                $update_stmt = $conn->prepare("
                    UPDATE supplier_orders 
                    SET status = 'Shipped'
                    WHERE linked_order_id = ? AND supplier_id = ? AND status = 'Pending Ship'
                ");
                $update_stmt->bind_param("ii", $linked_order_id, $supID);
                $update_stmt->execute();

                if ($update_stmt->affected_rows > 0) {
                    $success = true;
                    $conn->commit();
                } else {
                    throw new Exception("No items were updated.");
                }
                $update_stmt->close();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to arrange delivery.";
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}

// Default selected batch
$selected_batch = null;
foreach ($batches as $batch) {
    if ($batch['status'] === 'pending') {
        $selected_batch = $batch;
        break;
    }
}
if (!$selected_batch && !empty($batches)) {
    $selected_batch = $batches[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Nakofi Cafe - Delivery Arrangement </title>
    <link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{
            --white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;
            --radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);
            --shadow:0 6px 20px rgba(0,0,0,.08);--shadow-hover:0 15px 35px rgba(0,0,0,.12);
            --success:#27ae60;--danger:#e74c3c;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);
            min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;
        }
        .sidebar{
            width:280px;background:var(--white);border-right:1px solid var(--border);
            position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);
            display:flex;flex-direction:column;z-index:10;
        }
        .logo{text-align:center;margin-bottom:40px;}
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}
        .menu-container{flex:1;overflow-y:auto;padding-right:8px;margin-bottom:20px;}
        .menu-item{
            display:flex;align-items:center;gap:16px;padding:14px 20px;color:var(--text);
            font-size:16px;font-weight:700;border-radius:16px;cursor:pointer;
            transition:var(--trans);margin-bottom:8px;
        }
        .menu-item i{font-size:22px;color:#D1D5DB;}
        .menu-item:hover{background:#f1f5f9;}
        .menu-item.active{background:var(--charcoal);color:var(--white);}
        .menu-item.active i{color:var(--white);}
        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}

        .main{
            margin-left:280px;width:calc(100% - 280px);padding:40px 60px;flex:1;
        }
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
        .form-group{margin-bottom:28px;}
        .form-group label{
            display:block;font-size:14px;color:var(--muted);text-transform:uppercase;
            letter-spacing:1.5px;margin-bottom:8px;font-weight:700;
        }
        .form-group select,.form-group input{
            width:100%;padding:16px 20px;border:1px solid var(--border);border-radius:12px;
            font-size:18px;background:var(--white);font-family:'Playfair Display',serif;
        }
        .form-group select:focus,.form-group input:focus{
            outline:none;border-color:var(--charcoal);box-shadow:0 0 0 4px rgba(33,37,41,0.1);
        }

        .status-badge{
            display:inline-block;padding:8px 20px;border-radius:50px;font-size:15px;font-weight:700;
        }
        .status-pending{background:#fee2e2;color:#991b1b;}
        .status-shipped{background:#d4edda;color:#155724;}

        .ship-btn{
            font-family: 'Playfair Display',serif;
            display:block;width:100%;padding:18px;background:var(--charcoal);color:white;
            border:none;border-radius:50px;font-size:20px;font-weight:700;cursor:pointer;
            transition:var(--trans);margin-top:20px;
        }
        .ship-btn:hover{background:#111;}
        .ship-btn:disabled{background:#999;cursor:not-allowed;}

        .success-msg,.error-msg{
            text-align:center;padding:30px;font-size:20px;font-weight:700;
        }
        .success-msg{color:var(--success);}
        .error-msg{color:var(--danger);}

        footer{
            text-align:center;padding:60px 0 40px;color:var(--muted);font-size:14px;
            margin-top:auto;background:var(--bg);margin-left:280px;
        }

        @media (max-width:768px){
            .sidebar{width:100%;height:auto;position:relative;padding:20px;flex-direction:row;justify-content:center;gap:30px;}
            .main{margin-left:0;width:100%;padding:30px 20px;}
            footer{margin-left:0;}
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
        <div class="menu-item active"><i class="fas fa-truck"></i><span>Delivery Arrangement</span></div>
        <div class="menu-item" onclick="location='report.php'"><i class="fas fa-receipt"></i><span>Report</span></div>
        <div class="menu-item" onclick="location='invoice.php'"><i class="fas fa-file-invoice"></i><span>Invoice</span></div>
    </div>
    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<div class="main">
    <div class="header">
        <div class="page-title">Delivery Arrangement</div>
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
        <?php if ($success): ?>
            <div class="success-msg">
                <i class="fas fa-check-circle" style="font-size:48px;display:block;margin-bottom:20px;"></i>
                Delivery arranged successfully!<br>
                All items in this order are now marked as <strong>Shipped</strong>.
            </div>
        <?php elseif ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($batches)): ?>
            <p style="text-align:center;font-size:20px;color:var(--muted);padding:60px 0;">
                No orders pending delivery at this time.
            </p>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label for="linked_order_id">SELECT ORDER BATCH</label>
                    <select name="linked_order_id" id="linked_order_id" onchange="this.form.submit()" required>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?= $batch['order_id'] ?>" <?= ($selected_batch && $batch['order_id'] == $selected_batch['order_id']) ? 'selected' : '' ?>>
                                #<?= $batch['order_id'] ?> — <?= $batch['order_date'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selected_batch): ?>
                    <div style="margin:20px 0;padding:16px;background:#f8f9fa;border-radius:12px;text-align:center;">
                        <strong>CURRENT STATUS:</strong>
                        <span class="status-badge status-<?= $selected_batch['status'] ?>">
                            <?= $selected_batch['status'] === 'pending' ? 'Pending Shipment' : 'Already Shipped' ?>
                        </span>
                    </div>

                    <?php if ($selected_batch['status'] === 'pending'): ?>
                        <div class="form-group">
                            <label for="receiver_name">RECEIVER NAME</label>
                            <input type="text" name="receiver_name" id="receiver_name" required>
                        </div>
                        <div class="form-group">
                            <label for="address">DELIVERY ADDRESS</label>
                            <input type="text" name="address" id="address" required>
                        </div>
                        <div class="form-group">
                            <label for="courier">COURIER SERVICE</label>
                            <input type="text" name="courier" id="courier" placeholder="e.g. J&T Express, Pos Laju" required>
                        </div>
                        <div class="form-group">
                            <label for="ship_date">PLANNED SHIP DATE & TIME</label>
                            <input type="datetime-local" name="ship_date" id="ship_date" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>

                        <button type="submit" class="ship-btn">
                            <i class="fas fa-truck"></i> Arrange Shipment
                        </button>
                    <?php else: ?>
                        <p style="text-align:center;color:var(--muted);font-size:18px;padding:40px 0;">
                            This order batch has already been shipped.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>

<script>
function logoutConfirm() {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
    setTimeout(() => location = 'login.php', 800);
}
</script>
</body>
</html>