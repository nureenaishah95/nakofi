<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$user_role = $user['role'];

// Get the linked_order_id from URL
$linked_order_id = $_GET['order'] ?? null;

if (!$linked_order_id) {
    die("No order specified.");
}

// Show success message after refund request
$msg = $_GET['msg'] ?? '';
$success_message = '';
if ($msg === 'refund_requested') {
    $success_message = '
        <div style="background:#d4edda; color:#155724; padding:18px; margin:25px 0; border-radius:12px; text-align:center; font-size:18px; font-weight:700; border:1px solid #c3e6cb;">
            ✅ Refund/Return request submitted successfully!<br>
            <span style="font-size:16px; color:#0f5132;">Waiting for supplier review and approval.</span>
        </div>';
}

// Dynamically build SELECT fields
$base_fields = "id AS row_id, item_name, quantity, total, order_date, status, action, supplier_id, refund_amount, refund_receipt, refund_status, refund_reason, refund_proof";
$extra_fields = [];

$check = $conn->query("SHOW COLUMNS FROM supplier_orders LIKE 'tracking_number'");
if ($check && $check->num_rows > 0) {
    $extra_fields[] = "tracking_number";
}

$check = $conn->query("SHOW COLUMNS FROM supplier_orders LIKE 'estimated_delivery'");
if ($check && $check->num_rows > 0) {
    $extra_fields[] = "estimated_delivery";
}

$select_fields = $base_fields . (empty($extra_fields) ? '' : ', ' . implode(', ', $extra_fields));

// Fetch ALL items for this linked_order_id
$sql = "SELECT $select_fields FROM supplier_orders WHERE linked_order_id = ? ORDER BY item_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $linked_order_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

if (empty($orders)) {
    die("No items found for Order #$linked_order_id.");
}

$page_title = "Order #$linked_order_id";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nakofi Cafe - <?= $page_title ?></title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;--radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;padding-top:100px;font-weight:700;}
        header{position:fixed;top:0;left:0;right:0;background:var(--white);padding:20px 48px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,.03);z-index:1000;}
        header .logo{height:70px;width:auto;}
        header h1{font-size:32px;color:var(--charcoal);letter-spacing:-1.2px;}
        .back-btn{background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);padding:10px 22px;border-radius:50px;font-weight:700;font-size:14px;cursor:pointer;transition:var(--trans);}
        .back-btn:hover{background:var(--charcoal);color:var(--white);}
        .container{max-width:1200px;margin:60px auto;padding:0 30px;}
        .title{text-align:center;font-size:36px;color:var(--charcoal);margin-bottom:40px;}
        .title::after{content:'';width:100px;height:3px;background:var(--charcoal);opacity:.15;display:block;margin:20px auto 0;}
        .order-list{margin-bottom:60px;}
        .order-item{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:36px;margin-bottom:40px;box-shadow:0 12px 28px rgba(0,0,0,.05);}
        .order-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;}
        .order-id{font-size:28px;color:var(--charcoal);}
        .status-badge{padding:10px 28px;border-radius:50px;font-size:16px;font-weight:700;text-transform:uppercase;}
        .status-badge.pending{background:#FFF3CD;color:#856404;}
        .status-badge.shipped,.status-badge.in-transit{background:#D1ECF1;color:#0C5460;}
        .status-badge.delivered,.status-badge.completed,.status-badge.success{background:#D4EDDA;color:#155724;}
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;margin:30px 0;}
        .info-item strong{display:block;font-size:18px;color:var(--muted);margin-bottom:8px;}
        .info-item span{font-size:26px;color:var(--charcoal);}
        .timeline{display:flex;justify-content:space-between;position:relative;margin:60px 0;}
        .timeline::before{content:'';position:absolute;top:25px;left:0;right:0;height:6px;background:#e0e0e0;z-index:0;border-radius:3px;}
        .step{flex:1;text-align:center;position:relative;z-index:1;}
        .circle{width:50px;height:50px;border-radius:50%;background:#ccc;color:white;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-weight:bold;font-size:18px;}
        .completed .circle{background:#198754;}
        .active .circle{background:#0d6efd;}
        .label{font-weight:600;color:#444;font-size:18px;}
        .date{font-size:14px;color:#666;margin-top:6px;}
        .action-area{text-align:center;margin-top:30px;}
        .action-btn{padding:16px 40px;border:none;border-radius:50px;color:white;font-weight:700;font-size:18px;cursor:pointer;margin:5px;font-family:'Playfair Display',serif;}
        .btn-ship{background:#0d6efd;}
        .btn-confirm{background:#198754;font-family:'Playfair Display',serif;}
        footer{text-align:center;padding:40px;color:var(--muted);font-size:14px;}

        /* Modal styles */
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:none;align-items:center;justify-content:center;z-index:2000;}
        .modal-content{background:var(--card);padding:30px;border-radius:var(--radius);width:90%;max-width:800px;position:relative;box-shadow:0 20px 40px rgba(0,0,0,0.2);max-height:95vh;overflow-y:auto;}

        /* Enhanced Close Button */
        .modal-close {
            position: absolute;
            top: 12px;
            right: 20px;
            font-size: 36px;
            font-weight: 300;
            color: #888;
            cursor: pointer;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
            z-index: 10;
        }
        .modal-close:hover {
            background: #f1f1f1;
            color: #333;
        }

        .preview-imgs{margin-top:10px;display:flex;flex-wrap:wrap;gap:10px;}
        .preview-imgs img{max-width:150px;max-height:150px;border:1px solid var(--border);border-radius:8px;}

        @media(max-width:768px){
            header{flex-direction:column;gap:12px;padding:16px;}
            .order-header{flex-direction:column;text-align:center;}
            .info-grid{grid-template-columns:1fr;}
            .timeline{flex-direction:column;gap:30px;}
            .timeline::before{display:none;}
            .step{margin:20px 0;}
            .modal-content{width:95%;padding:20px;}
        }
    </style>
</head>
<body>

<header>
    <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="back-btn" onclick="location.href='track.php'">
        <i class="fas fa-arrow-left"></i> Back
    </button>
</header>

<div class="container">
    <h2 class="title"><?= $page_title ?></h2>

    <?= $success_message ?>

    <div class="order-list">
        <?php foreach ($orders as $order): 
            $status = strtolower($order['status'] ?? 'pending');
            $display_status = $status;
            if ($status === 'in transit') $display_status = 'shipped';
            if (in_array($status, ['success', 'delivered', 'completed'])) $display_status = 'completed';
            $item_name = ucwords($order['item_name']);
            $refund_status = strtolower($order['refund_status'] ?? 'none');

            // Refund status display configuration
            $refund_display = [
                'none'       => ['','transparent','transparent'],
                'requested'  => ['Waiting for Supplier Approval', '#856404', '#FFF3CD'],
                'approved'   => ['Refund Approved – Processing', '#0c5460', '#D1ECF1'],
                'completed'  => ['Refund Completed', '#155724', '#D4EDDA'],
                'rejected'   => ['Refund Rejected', '#721c24', '#F8D7DA']
            ];
            $status_info = $refund_display[$refund_status] ?? ['', '#6c757d', '#e9ecef'];
        ?>
            <div class="order-item">
                <div class="order-header">
                    <div class="order-id">#<?= $linked_order_id ?> — <?= $item_name ?></div>
                    <div class="status-badge <?= $display_status ?>">
                        <?= strtoupper($display_status) ?>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <strong>Quantity</strong>
                        <span><?= $order['quantity'] ?> unit(s)</span>
                    </div>
                    <div class="info-item">
                        <strong>Total Cost</strong>
                        <span>RM <?= number_format($order['total'], 2) ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Ordered</strong>
                        <span><?= date('d M Y H:i', strtotime($order['order_date'])) ?></span>
                    </div>
                    <?php if (!empty($order['tracking_number'] ?? '')): ?>
                        <div class="info-item">
                            <strong>Tracking</strong>
                            <span><?= htmlspecialchars($order['tracking_number']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($order['estimated_delivery'] ?? '')): ?>
                        <div class="info-item">
                            <strong>Est. Arrival</strong>
                            <span><?= date('d M Y', strtotime($order['estimated_delivery'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 style="font-size:24px;text-align:center;margin:40px 0 20px;">Order Progress</h3>
                <div class="timeline">
                    <div class="step completed">
                        <div class="circle">✓</div>
                        <div class="label">Order Placed</div>
                        <div class="date"><?= date('d M Y', strtotime($order['order_date'])) ?></div>
                    </div>
                    <div class="step <?= in_array($status, ['shipped', 'in transit', 'completed', 'success']) ? 'completed' : 'active' ?>">
                        <div class="circle"><?= in_array($status, ['shipped', 'in transit', 'completed', 'success']) ? '✓' : '●' ?></div>
                        <div class="label">Supplier Preparing</div>
                        <div class="date"><?= $status !== 'pending' ? 'Prepared' : 'Pending' ?></div>
                    </div>
                    <div class="step <?= in_array($status, ['shipped', 'in transit']) ? 'active' : (in_array($status, ['completed', 'success']) ? 'completed' : '') ?>">
                        <div class="circle"><?= in_array($status, ['completed', 'success']) ? '✓' : (in_array($status, ['shipped', 'in transit']) ? '●' : '') ?></div>
                        <div class="label">In Transit</div>
                        <div class="date"><?= in_array($status, ['shipped', 'in transit']) ? 'Shipped' : '—' ?></div>
                    </div>
                    <div class="step <?= in_array($status, ['completed', 'success']) ? 'completed' : '' ?>">
                        <div class="circle"><?= in_array($status, ['completed', 'success']) ? '✓' : '' ?></div>
                        <div class="label">Delivered & Confirmed</div>
                        <div class="date"><?= in_array($status, ['completed', 'success']) ? date('d M Y') : '—' ?></div>
                    </div>
                </div>

                <div class="action-area">
                    <?php if ($user_role === 'supplier' && $status === 'pending'): ?>
                        <form action="update_delivery.php" method="POST" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?= $order['row_id'] ?>">
                            <input type="hidden" name="table" value="supplier_orders">
                            <input type="hidden" name="action" value="ship">
                            <button type="submit" class="action-btn btn-ship">Mark as Shipped</button>
                        </form>

                        <?php if ($refund_status === 'none' || $refund_status === 'requested'): ?>
                            <button type="button" class="action-btn" style="background:#dc3545;" onclick="openModal('refund-receipt-modal-<?= $order['row_id'] ?>')">
                                Upload Refund Receipt
                            </button>
                        <?php endif; ?>

                    <?php elseif (in_array($user_role, ['staff', 'staff_leader']) && in_array($status, ['shipped', 'in transit'])): ?>
                        <form action="update_delivery.php" method="POST" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?= $order['row_id'] ?>">
                            <input type="hidden" name="table" value="supplier_orders">
                            <input type="hidden" name="action" value="confirm">
                            <button type="submit" class="action-btn btn-confirm">Order Received</button>
                        </form>
                    <?php endif; ?>

                    <?php if (in_array($user_role, ['staff', 'staff_leader']) && $refund_status === 'none' && !in_array($status, ['completed', 'success'])): ?>
                        <button type="button" class="action-btn" style="background:#FF8C00;" onclick="openModal('return-request-modal-<?= $order['row_id'] ?>')">
                            Request Return/Refund
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($order['refund_receipt'])): ?>
                        <button type="button" class="action-btn" style="background:#17a2b8;" onclick="openModal('view-receipt-modal-<?= $order['row_id'] ?>')">
                            View Refund Receipt
                        </button>
                    <?php endif; ?>

                    <!-- Improved refund status badge -->
                    <?php if ($refund_status !== 'none'): ?>
                        <div style="
                            margin: 25px 0 15px;
                            padding: 14px 30px;
                            background: <?= $status_info[2] ?>;
                            color: <?= $status_info[1] ?>;
                            border-radius: 50px;
                            font-size: 17px;
                            font-weight: bold;
                            display: inline-block;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                        ">
                            Refund Status: <strong><?= $status_info[0] ?></strong>
                            <?php if ($order['refund_amount'] > 0): ?>
                                 • RM <?= number_format($order['refund_amount'], 2) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Request Refund Modal (Staff) -->
                <div id="return-request-modal-<?= $order['row_id'] ?>" class="modal-overlay">
                    <div class="modal-content">
                        <span class="modal-close" onclick="closeModal('return-request-modal-<?= $order['row_id'] ?>')">&times;</span>
                        <h3>Request Return/Refund</h3>
                        <p><strong>Item:</strong> <?= htmlspecialchars($item_name) ?> | <strong>Total:</strong> RM <?= number_format($order['total'], 2) ?></p>
                        <form action="process_refund.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="order_id" value="<?= $order['row_id'] ?>">
                            <input type="hidden" name="action" value="request_refund">
                            <input type="hidden" name="linked_order_id" value="<?= $linked_order_id ?>">
                            <input type="hidden" name="refund_amount" value="<?= $order['total'] ?>">
                            <label><strong>Refund Reason:</strong></label><br>
                            <textarea name="reason" rows="5" style="width:100%; padding:10px; margin:10px 0; border-radius:8px; border:1px solid var(--border);" required></textarea><br>
                            <label><strong>Upload Proof:</strong></label><br>
                            <input type="file" name="proof_images[]" id="proof-<?= $order['row_id'] ?>" multiple accept="image/*" onchange="previewFiles('proof-<?= $order['row_id'] ?>', 'preview-proof-<?= $order['row_id'] ?>')"><br>
                            <div class="preview-imgs preview-proof-<?= $order['row_id'] ?>"></div><br>
                            <button type="submit" class="action-btn btn-confirm">Submit Request</button>
                        </form>
                    </div>
                </div>

                <!-- Upload Refund Receipt Modal (Supplier) -->
                <div id="refund-receipt-modal-<?= $order['row_id'] ?>" class="modal-overlay">
                    <div class="modal-content">
                        <span class="modal-close" onclick="closeModal('refund-receipt-modal-<?= $order['row_id'] ?>')">&times;</span>
                        <h3>Upload Bukti Refund</h3>
                        <form action="process_refund.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="order_id" value="<?= $order['row_id'] ?>">
                            <input type="hidden" name="action" value="process_refund">
                            <input type="hidden" name="linked_order_id" value="<?= $linked_order_id ?>">
                            <input type="hidden" name="refund_amount" value="<?= $order['total'] ?>">
                            <label><strong>Jumlah Refund:</strong> RM <?= number_format($order['total'], 2) ?></label><br><br>
                            <label><strong>Upload Resit (gambar/PDF):</strong></label><br>
                            <input type="file" name="receipt" accept="image/*,application/pdf" required><br><br>
                            <button type="submit" class="action-btn" style="background:#28a745;">Upload & Process</button>
                        </form>
                    </div>
                </div>

                <!-- IMPROVED View Receipt Modal -->
                <?php if (!empty($order['refund_receipt'])): ?>
                <div id="view-receipt-modal-<?= $order['row_id'] ?>" class="modal-overlay">
                    <div class="modal-content">
                        <span class="modal-close" onclick="closeModal('view-receipt-modal-<?= $order['row_id'] ?>')">&times;</span>

                        <h3 style="text-align:center; margin-bottom:20px;">Refund Receipt</h3>
                        <p style="text-align:center; font-size:18px; margin-bottom:30px;">
                            Total Refund: <strong>RM <?= number_format($order['refund_amount'], 2) ?></strong>
                        </p>

                        <div style="background:#f8f9fa; border-radius:12px; padding:20px; text-align:center; max-height:70vh; overflow:auto;">
                            <?php 
                            $ext = strtolower(pathinfo($order['refund_receipt'], PATHINFO_EXTENSION));
                            if ($ext === 'pdf'): ?>
                                <embed src="<?= htmlspecialchars($order['refund_receipt']) ?>" 
                                       style="width:100%; height:70vh; border:none; border-radius:8px;" 
                                       type="application/pdf">
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($order['refund_receipt']) ?>" 
                                     alt="Refund Receipt" 
                                     style="max-width:100%; max-height:70vh; height:auto; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1);">
                            <?php endif; ?>
                        </div>

                        <div style="text-align:center; margin-top:20px; color:#6c757d; font-size:14px;">
                            Note: This receipt is computer-generated and no signature is required.
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    </div>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>

<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.querySelectorAll('#' + modalId + ' .preview-imgs').forEach(el => el.innerHTML = '');
}

function previewFiles(inputId, previewClass) {
    const files = document.getElementById(inputId).files;
    const preview = document.querySelector('.' + previewClass);
    preview.innerHTML = '';
    for (let file of files) {
        if (file.type.match('image.*')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = document.createElement('img');
                img.src = e.target.result;
                preview.appendChild(img);
            }
            reader.readAsDataURL(file);
        }
    }
}
</script>

</body>
</html>