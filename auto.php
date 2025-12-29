<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user_id and role from users table
$user_id = $_SESSION['user_id'];
$query = "SELECT user_id, role FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database query preparation failed: " . $conn->error);
}
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// If user_id not found, redirect to login
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch stock data from database
$stock = [];
$items = ['syrup', 'coffee', 'milk'];
$prices = ['syrup' => 13.30, 'coffee' => 13.74, 'milk' => 27.00];
foreach ($items as $item) {
    $sql = "SELECT stock_quantity FROM inventory WHERE item_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock[$item] = ['count' => $result->num_rows > 0 ? $result->fetch_assoc()['stock_quantity'] : 0, 'image' => $item . '.jpg'];
    $stmt->close();
}

// Handle search form submission (if any)
$selected_date = isset($_POST['date']) ? $_POST['date'] : '';
$orders = [];
if ($selected_date) {
    $sql = "SELECT item_name, quantity, total, order_date FROM orders WHERE DATE(order_date) = ? ORDER BY order_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
} else {
    $sql = "SELECT item_name, quantity, total, order_date FROM orders ORDER BY order_date DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Sofia">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #fff;
            color: #343a40;
            line-height: 1.6;
            overflow-x: hidden;
        }
        .header {
            background-color: #212529;
            padding: 15px 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        .header h1 {
            font-size: 35px;
            font-style: italic;
            font-family: "Sofia", sans-serif;
            color: #ecf0f1;
            margin-left: 565px;
        }
        .logout {
            background-color: rgba(92, 92, 92, 0.54);
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            width: 75px;
            height: 35px;
            font-weight: bold;
        }
        .logout:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        .container {
            width: 90%;
            max-width: 900px;
            margin: 105px auto 20px;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .title {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #2c3e50;
            font-weight: 600;
            font-style: italic;
            font-family: "Sofia", sans-serif;
        }
        .order-date {
            text-align: center;
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .order-table th, .order-table td {
            padding: 5px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .order-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .order-table td img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
            vertical-align: middle;
        }
        .subtotal {
            text-align: right;
            padding: 10px;
            font-weight: bold;
        }
        .pay-button {
            display: block;
            width: 130px;
            margin: 20px auto;
            padding: 10px;
            background-color: rgb(54, 54, 54);
            color: #fff;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
        }
        .pay-button:hover {
            background-color: rgb(20, 20, 20);
        }
        .footer {
            background-color: #212529;
            padding: 10px 20px;
            text-align: center;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
            position: fixed;
            bottom: 0;
            width: 100%;
            color: #ecf0f1;
            font-size: 16px;
        }
        @media (max-width: 768px) {
            .container {
                width: 95%;
            }
            .header h1 {
                margin-left: 0;
                font-size: 28px;
            }
            .order-table {
                display: block;
                overflow-x: auto;
            }
            .order-table th, .order-table td {
                min-width: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>nakofi cafe</h1>
        <button class="logout" onclick="handleLogout()">Back</button>
    </div>
    <div class="container">
        <div class="title">Purchase Order</div>
        <div class="order-date">Order Date: <?php echo date('Y-m-d H:i:s', strtotime('2025-06-24 21:46:00 +08:00')); ?></div>
        <table class="order-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $reorder_thresholds = ['syrup' => 3, 'coffee' => 7, 'milk' => 2];
                $reorder_quantities = ['syrup' => 5, 'coffee' => 10, 'milk' => 4];
                $total_subtotal = 0;
                foreach ($orders as $order) {
                    $item = $order['item_name'];
                    if ($stock[$item]['count'] < $reorder_thresholds[$item]) {
                        echo "<tr>";
                        echo "<td><img src='<?= BASE_URL ?>asset/img/" . $stock[$item]['image'] . "' alt='" . ucfirst($item) . "'>" . ucfirst($item) . "</td>";
                        echo "<td>" . $order['quantity'] . "</td>";
                        echo "<td>RM " . number_format($order['total'], 2) . "</td>";
                        echo "</tr>";
                        $total_subtotal += $order['total'];
                    }
                }
                ?>
            </tbody>
        </table>
        <div class="subtotal">
            Subtotal: RM <?php echo number_format($total_subtotal, 2); ?>
        </div>
        <button class="pay-button" onclick="confirmOrder()">Confirm Orders</button>
    </div>
    <div class="footer">
        <p>© 2025 Nakofi Cafe. All rights reserved.</p>
    </div>

    <script>
        function changeCount(item, delta) {
            let countElement = document.getElementById(item + '-count');
            let inputElement = document.getElementById(item + '-input');
            let count = parseInt(countElement.textContent);
            count = Math.max(0, count + delta);
            countElement.textContent = count;
            inputElement.value = count;
        }

        function handleLogout() {
            <?php if ($user['role'] == "staff_leader"): ?>
                window.location.href = 'staff_leader.php';
            <?php else: ?>
                window.location.href = 'staff.php';
            <?php endif; ?>
        }

        function confirmOrder() {
            if (confirm('Are you sure you want to confirm the orders?')) {
                alert('Orders confirmed successfully!');
                window.location.href = 'staff.php';
            }
        }
    </script>
</body>
</html>