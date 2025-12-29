<?php
// Database connection
$conn = new mysqli("localhost", "username", "password", "nakofi_cafe");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check milk stock
$sql = "SELECT stock_quantity FROM inventory WHERE item_name = 'Milk'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $milk_stock = $row['stock_quantity'];

    if ($milk_stock < 2) {
        $quantity_needed = 4 - $milk_stock; // Example: order enough to bring stock to 4
        $item_price = 27.00; // Price per carton from the image
        $total = $quantity_needed * $item_price;

        // Insert into orders table
        $order_sql = "INSERT INTO orders (item_name, quantity, total) VALUES ('Milk', $quantity_needed, $total)";
        $conn->query($order_sql);

        // Update supplier order list
        $supplier_sql = "INSERT INTO supplier_orders (item_name, quantity, total) VALUES ('Milk', $quantity_needed, $total)";
        $conn->query($supplier_sql);

        // Update orders.php (example update, adjust as per your file structure)
        $order_content = file_get_contents('orders.php');
        $new_order = "<tr><td>Milk</td><td>$quantity_needed</td><td>RM $total</td></tr>";
        $order_content = str_replace('</tbody>', $new_order . '</tbody>', $order_content);
        file_put_contents('orders.php', $order_content);

        // Update supplier.php (example update, adjust as per your file structure)
        $supplier_content = file_get_contents('supplier.php');
        $new_supplier_order = "<tr><td>Milk</td><td>$quantity_needed</td><td>RM $total</td></tr>";
        $supplier_content = str_replace('</tbody>', $new_supplier_order . '</tbody>', $supplier_content);
        file_put_contents('supplier.php', $supplier_content);
    }
}

$conn->close();
?>