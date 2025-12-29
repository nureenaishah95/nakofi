<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only allow staff_leader
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user['role'] !== 'staff_leader') {
    die("Access denied.");
}

$message = '';

// Session-based wizard
if (!isset($_SESSION['temp_product'])) {
    $_SESSION['temp_product'] = ['id' => null, 'name' => '', 'step' => 1];
}
$temp = &$_SESSION['temp_product'];

// Handle go back via ?step=
if (isset($_GET['step']) && in_array($_GET['step'], [1,2,3]) && $temp['step'] >= $_GET['step']) {
    $temp['step'] = (int)$_GET['step'];
}

// Step 1
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1'])) {
    $name = trim($_POST['name']);
    if (empty($name)) {
        $message = "<div class='alert error'>Product name is required.</div>";
    } else {
        $slug = strtolower(str_replace(' ', '_', $name));
        $image_path = 'asset/img/default.jpg';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $filename = $slug . '.' . $ext;
                $target = 'asset/img/' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $image_path = 'asset/img/' . $filename;
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO products (product_name, image_path) VALUES (?, ?)");
        $stmt->bind_param("ss", $slug, $image_path);
        if ($stmt->execute()) {
            $temp['id'] = $stmt->insert_id;
            $temp['name'] = $name;
            $temp['step'] = 2;

            $stmt2 = $conn->prepare("INSERT IGNORE INTO inventory (item_name, stock_quantity) VALUES (?, 0)");
            $stmt2->bind_param("s", $slug);
            $stmt2->execute();
            $stmt2->close();

            $message = "<div class='alert success'>Product '<strong>$name</strong>' added! Now add suppliers.</div>";
        } else {
            $message = "<div class='alert error'>Product already exists.</div>";
        }
        $stmt->close();
    }
}

// Step 2
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2']) && $temp['step'] === 2 && $temp['id']) {
    $supplier_ids = $_POST['supplier_id'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $lead_times = $_POST['lead_time_days'] ?? [];
    $is_primary = $_POST['is_primary'] ?? [];

    $inserted = 0;
    $has_primary = false;

    foreach ($supplier_ids as $i => $sid) {
        if (empty($sid) || empty($unit_prices[$i])) continue;
        $price = floatval($unit_prices[$i]);
        $lead = intval($lead_times[$i] ?? 3);
        $primary = isset($is_primary[$i]) ? 1 : 0;
        if ($primary) $has_primary = true;

        $stmt = $conn->prepare("INSERT INTO product_suppliers (product_id, supplier_id, unit_price, is_primary, lead_time_days)
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE unit_price=VALUES(unit_price), is_primary=VALUES(is_primary), lead_time_days=VALUES(lead_time_days)");
        $stmt->bind_param("iidii", $temp['id'], $sid, $price, $primary, $lead);
        $stmt->execute();
        $inserted++;
        $stmt->close();
    }

    if (!$has_primary && $inserted > 0) {
        $conn->query("UPDATE product_suppliers SET is_primary = 1 WHERE product_id = {$temp['id']} ORDER BY id LIMIT 1");
    }

    if ($inserted > 0) {
        $temp['step'] = 3;
        $message = "<div class='alert success'>$inserted supplier(s) saved! Now set auto-order rules.</div>";
    } else {
        $message = "<div class='alert error'>Add at least one supplier.</div>";
    }
}

// Step 3 - Auto-fetch unit_price from primary supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step3']) && $temp['step'] === 3 && $temp['id']) {
    $threshold = intval($_POST['threshold'] ?? 3);
    $target = intval($_POST['target_quantity'] ?? 10);
    $enabled = isset($_POST['enabled']) ? 1 : 0;

    // Get unit_price from primary supplier
    $primary_price = 0.00;
    $stmt_price = $conn->prepare("SELECT unit_price FROM product_suppliers WHERE product_id = ? AND is_primary = 1 LIMIT 1");
    $stmt_price->bind_param("i", $temp['id']);
    $stmt_price->execute();
    $result_price = $stmt_price->get_result();
    if ($row = $result_price->fetch_assoc()) {
        $primary_price = floatval($row['unit_price']);
    }
    $stmt_price->close();

    $sql = "INSERT INTO auto_order_rules (product_id, threshold, target_quantity, unit_price, enabled)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                threshold = VALUES(threshold), 
                target_quantity = VALUES(target_quantity), 
                unit_price = VALUES(unit_price), 
                enabled = VALUES(enabled)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $message = "<div class='alert error'>Database error: " . htmlspecialchars($conn->error) . "</div>";
    } else {
        $stmt->bind_param("iiidi", $temp['id'], $threshold, $target, $primary_price, $enabled);
        if ($stmt->execute()) {
            $message = "<div class='alert success'>
                            <strong>Complete!</strong><br>
                            Auto-order rules saved using primary supplier price: <strong>RM " . number_format($primary_price, 2) . "</strong><br>
                            '<strong>{$temp['name']}</strong>' is fully set up!
                        </div>";
            unset($_SESSION['temp_product']);
            $temp = ['id' => null, 'name' => '', 'step' => 1];
        } else {
            $message = "<div class='alert error'>Failed: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    }
}

// Fetch suppliers
$suppliers = [];
$result = $conn->query("SELECT supID, name FROM supplier ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product - Nakofi Cafe</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;--radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;padding-top:100px;font-weight:700;overflow-x:hidden;}
        header{position:fixed;top:0;left:0;right:0;background:var(--white);padding:20px 48px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,.03);z-index:1000;gap:16px;}
        header .logo{height:70px;width:auto;object-fit:contain;}
        header h1{font-size:32px;color:var(--charcoal);letter-spacing:-1.2px;}
        .logout{background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);padding:10px 22px;border-radius:50px;font-weight:700;font-size:14px;cursor:pointer;transition:var(--trans);}
        .logout:hover{background:var(--charcoal);color:var(--white);}
        .container{max-width:1200px;margin:40px auto;padding:0 40px;}
        .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:60px 80px;box-shadow:0 20px 40px rgba(0,0,0,.08);text-align:center;}
        .card h1{font-size:38px;color:var(--charcoal);margin-bottom:40px;}
        .progress{display:flex;justify-content:center;align-items:center;gap:50px;margin:50px 0;}
        .step{position:relative;width:70px;height:70px;border-radius:50%;background:#ddd;color:#999;font-size:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:var(--trans);}
        .step.active{background:var(--charcoal);color:white;transform:scale(1.15);box-shadow:0 8px 20px rgba(33,37,41,.2);}
        .step.done{background:#28a745;color:white;}
        .step:hover:not(.active):not(.done){background:#ccc;}
        .line{flex:1;height:5px;background:#ddd;transition:var(--trans);}
        .line.active{background:var(--charcoal);}
        .form-section{display:none;opacity:0;transition:opacity .6s var(--trans), transform .6s var(--trans);transform:translateY(20px);}
        .form-section.active{display:block;opacity:1;transform:translateY(0);}
        .form-group{margin-bottom:35px;text-align:left;}
        .form-group label{display:block;margin-bottom:12px;font-size:18px;color:var(--charcoal);}
        .form-group input,.form-group select{
            font-family:'Playfair Display',serif;width:100%;padding:18px 26px;border:1px solid var(--border);border-radius:50px;font-size:17px;background:var(--white);transition:var(--trans);
        }
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--charcoal);box-shadow:0 0 0 4px rgba(33,37,41,.1);}
        .checkbox-group{display:flex;align-items:center;gap:18px;font-size:18px;margin-top:16px;height:40px;}
        .checkbox-group input{width:26px;height:26px;cursor:pointer;accent-color:#28a745;}
        .submit-btn{font-family:'Playfair Display',serif;width:100%;padding:20px;background:var(--charcoal);color:white;border:none;border-radius:50px;font-size:19px;cursor:pointer;margin-top:40px;transition:var(--trans);}
        .submit-btn:hover{background:#111;transform:translateY(-3px);box-shadow:0 10px 25px rgba(0,0,0,.15);}
        .alert{padding:24px;border-radius:var(--radius);margin:30px 0;text-align:center;font-size:19px;}
        .alert.success{background:#d4edda;color:#155724;}
        .alert.error{background:#f8d7da;color:#721c24;}
        .supplier-row{display:grid;grid-template-columns:3fr 2fr 1.2fr 1.8fr 160px;gap:30px;align-items:end;padding:25px 0;border-bottom:1px dashed var(--border);}
        .add-supplier{font-family:'Playfair Display',serif;background:#28a745;color:white;padding:16px 36px;border:none;border-radius:50px;cursor:pointer;margin:40px 0;font-size:18px;display:inline-flex;align-items:center;gap:12px;transition:var(--trans);}
        .add-supplier:hover{background:#218838;transform:translateY(-3px);}
        .remove-row{font-family:'Playfair Display',serif;background:#dc3545;color:white;padding:16px 24px;border:none;border-radius:50px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;height:58px;font-size:17px;transition:var(--trans);}
        .remove-row:hover{background:#c82333;}
        @media(max-width:1100px){.supplier-row{grid-template-columns:2.5fr 1.8fr 1fr 1.6fr 150px;gap:20px;}}
        @media(max-width:992px){.container{max-width:900px;padding:0 30px;}.supplier-row{grid-template-columns:1fr;}}
        @media(max-width:768px){.card{padding:40px 30px;}.container{padding:0 20px;}.progress{flex-wrap:wrap;gap:20px;}.line{display:none;}.step{width:60px;height:60px;font-size:24px;}}
    </style>
</head>
<body>

<header>
    <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="logout" onclick="location='stock_take.php'"><i class="fas fa-arrow-left"></i> Back</button>
</header>

<div class="container">
    <div class="card">
        <h1>Add New Product</h1>

        <div class="progress">
            <div class="step <?= $temp['step'] >= 1 ? 'active' : '' ?> <?= $temp['step'] > 1 ? 'done' : '' ?>" onclick="location.href='?step=1'">1</div>
            <div class="line <?= $temp['step'] > 1 ? 'active' : '' ?>"></div>
            <div class="step <?= $temp['step'] >= 2 ? 'active' : '' ?> <?= $temp['step'] > 2 ? 'done' : '' ?>" onclick="location.href='?step=2'">2</div>
            <div class="line <?= $temp['step'] > 2 ? 'active' : '' ?>"></div>
            <div class="step <?= $temp['step'] >= 3 ? 'active' : '' ?>" onclick="location.href='?step=3'">3</div>
        </div>

        <?php if (!empty($message)): ?>
            <?= $message ?>
        <?php endif; ?>

        <!-- Step 1 -->
        <div class="form-section <?= $temp['step'] == 1 ? 'active' : '' ?>">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="step1" value="1">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" required placeholder="e.g., Vanilla Syrup">
                </div>
                <div class="form-group">
                    <label>Product Image (Optional)</label>
                    <input type="file" name="image" accept="image/*">
                    <small style="display:block;margin-top:10px;color:var(--muted);font-size:16px;">JPG, PNG, GIF, WebP supported</small>
                </div>
                <button type="submit" class="submit-btn">Next → Suppliers</button>
            </form>
        </div>

        <!-- Step 2 -->
        <div class="form-section <?= $temp['step'] == 2 ? 'active' : '' ?>">
            <h2 style="font-size:30px;margin:50px 0 40px;">Add Suppliers for "<?= htmlspecialchars($temp['name']) ?>"</h2>
            <form method="POST">
                <input type="hidden" name="step2" value="1">
                <div id="suppliers-container">
                    <div class="supplier-row">
                        <div class="form-group">
                            <label>Supplier</label>
                            <select name="supplier_id[]" required>
                                <option value="">Select supplier...</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['supID'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Unit Price</label>
                            <input type="number" step="0.01" name="unit_price[]" required placeholder="17.90">
                        </div>
                        <div class="form-group">
                            <label>Lead Time (days)</label>
                            <input type="number" name="lead_time_days[]" value="3" min="1">
                        </div>
                        <div class="form-group">
                            <label>Primary?</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_primary[0]" value="1">
                                <span>Yes, set as primary</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="button" class="remove-row"><i class="fas fa-trash-alt"></i> Remove</button>
                        </div>
                    </div>
                </div>
                <button type="button" id="add-supplier" class="add-supplier"><i class="fas fa-plus"></i> Add Another Supplier</button>
                <button type="submit" class="submit-btn">Next → Auto-Order Rules</button>
            </form>
        </div>

        <!-- Step 3 - Auto-filled unit_price from primary supplier -->
        <div class="form-section <?= $temp['step'] == 3 ? 'active' : '' ?>">
            <h2 style="font-size:30px;margin:50px 0 40px;">Auto-Order Rules for "<?= htmlspecialchars($temp['name']) ?>"</h2>
            <form method="POST">
                <input type="hidden" name="step3" value="1">
                <div class="form-group">
                    <label>Threshold (Order when stock ≤)</label>
                    <input type="number" name="threshold" value="3" min="0" required>
                </div>
                <div class="form-group">
                    <label>Target Quantity (Restock to)</label>
                    <input type="number" name="target_quantity" value="10" min="1" required>
                </div>
                <div class="form-group">
                    <label>Unit Price (Auto-filled from Primary Supplier)</label>
                    <?php
                    $display_price = 0.00;
                    $stmt_disp = $conn->prepare("SELECT unit_price FROM product_suppliers WHERE product_id = ? AND is_primary = 1 LIMIT 1");
                    $stmt_disp->bind_param("i", $temp['id']);
                    $stmt_disp->execute();
                    $res_disp = $stmt_disp->get_result();
                    if ($row_disp = $res_disp->fetch_assoc()) {
                        $display_price = floatval($row_disp['unit_price']);
                    }
                    $stmt_disp->close();
                    ?>
                    <input type="text" value="RM <?= number_format($display_price, 2) ?>" disabled style="background:#f0f0f0;">
                    <small style="display:block;margin-top:8px;color:#28a745;font-weight:600;">This price is automatically used for auto-orders</small>
                </div>
                <div class="form-group">
                    <label>Enable Auto-Order?</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="enabled" value="1" checked>
                        <span>Yes, enable automatic reordering</span>
                    </div>
                </div>
                <button type="submit" class="submit-btn">Complete Setup</button>
            </form>
        </div>

        <?php if ($temp['step'] > 3): ?>
        <div style="text-align:center;margin-top:80px;">
            <p style="font-size:24px;margin-bottom:30px;"><strong>Product added successfully!</strong></p>
            <button onclick="location.reload()" class="submit-btn" style="width:auto;padding:20px 60px;">Add Another Product</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if ($temp['step'] == 2): ?>
document.getElementById('add-supplier').addEventListener('click', function() {
    const container = document.getElementById('suppliers-container');
    const rows = container.querySelectorAll('.supplier-row');
    const idx = rows.length;

    const row = document.createElement('div');
    row.className = 'supplier-row';
    row.innerHTML = `
        <div class="form-group">
            <label>Supplier</label>
            <select name="supplier_id[]" required>
                <option value="">Select supplier...</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['supID'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Unit Price</label>
            <input type="number" step="0.01" name="unit_price[]" required placeholder="17.90">
        </div>
        <div class="form-group">
            <label>Lead Time (days)</label>
            <input type="number" name="lead_time_days[]" value="3" min="1">
        </div>
        <div class="form-group">
            <label>Primary?</label>
            <div class="checkbox-group">
                <input type="checkbox" name="is_primary[${idx}]" value="1">
                <span>Yes, set as primary</span>
            </div>
        </div>
        <div class="form-group">
            <button type="button" class="remove-row"><i class="fas fa-trash-alt"></i> Remove</button>
        </div>
    `;
    container.appendChild(row);

    row.querySelector('.remove-row').addEventListener('click', function() {
        if (container.children.length > 1) {
            container.removeChild(row);
        } else {
            alert("Keep at least one supplier.");
        }
    });
});

document.querySelectorAll('.remove-row').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('.supplier-row');
        const container = document.getElementById('suppliers-container');
        if (container.children.length > 1) {
            container.removeChild(row);
        } else {
            alert("Keep at least one supplier.");
        }
    });
});
<?php endif; ?>
</script>

</body>
</html>