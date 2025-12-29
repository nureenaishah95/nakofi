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
        .print-button {
            display: block;
            width: 100px;
            margin: 20px auto;
            padding: 10px;
            background-color:rgb(255, 255, 255);
            color: #000;
            border: 1px solid #000;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
        }
        .print-button:hover {
            background-color: #212529;
            color: #fff;
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
        <button class="logout" onclick="window.location.href='orders.php'">Back</button>
    </div>
    <div class="container">
        <div class="title">Purchase Order</div>
        <table class="order-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><img src="<?= BASE_URL ?>asset/img/syrup.jpg" alt="Syrup">Syrup</td>
                    <td>3</td>
                    <td>RM 39.90</td>
                </tr>
                <tr>
                    <td><img src="<?= BASE_URL ?>asset/img/coffee.jpg" alt="Coffee Bean">Coffee Bean</td>
                    <td>5</td>
                    <td>RM 68.70</td>
                </tr>
                <tr>
                    <td><img src="<?= BASE_URL ?>asset/img/milk.jpg" alt="Milk">Milk</td>
                    <td>4</td>
                    <td>RM 277.00</td>
                </tr>
            </tbody>
        </table>
        <div class="subtotal">
            Subtotal: RM 385.60
        </div>
        <button class="print-button" onclick="printOrder()">Print</button>
    </div>
    <div class="footer">
        <p>© 2025 Nakofi Cafe. All rights reserved.</p>
    </div>

    <script>
        function printOrder() {
            const printContent = `
                <html>
                <head>
                    <title>Purchase Order</title>
                    <style>
                        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                        .container { width: 90%; max-width: 900px; margin: 20px auto; padding: 15px; }
                        .title { text-align: center; font-size: 24px; margin-bottom: 20px; color: #2c3e50; font-weight: 600; }
                        .order-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        .order-table th, .order-table td { padding: 5px; text-align: left; border-bottom: 1px solid #eee; }
                        .order-table th { background-color: #f8f9fa; font-weight: 600; color: #2c3e50; }
                        .order-table td img { width: 50px; height: 50px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px; margin-right: 10px; vertical-align: middle; }
                        .subtotal { text-align: right; padding: 10px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="title">Purchase Order</div>
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><img src="<?= BASE_URL ?>asset/img/syrup.jpg" alt="Syrup">Syrup</td>
                                    <td>3</td>
                                    <td>RM 39.90</td>
                                </tr>
                                <tr>
                                    <td><img src="<?= BASE_URL ?>asset/img/coffee.jpg" alt="Coffee Bean">Coffee Bean</td>
                                    <td>5</td>
                                    <td>RM 68.70</td>
                                </tr>
                                <tr>
                                    <td><img src="<?= BASE_URL ?>asset/img/milk.jpg" alt="Milk">Milk</td>
                                    <td>4</td>
                                    <td>RM 277.00</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="subtotal">Subtotal: RM 385.60</div>
                    </div>
                </body>
                </html>
            `;
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>