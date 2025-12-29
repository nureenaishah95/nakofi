<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe</title>
    <link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
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
            background-color: rgb(255, 255, 255);
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
            width: 900px;
            margin: 105px auto 20px;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .container h1 {
            font-style: italic;
            font-family: "Sofia", sans-serif;
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        .invoice-details {
            margin-bottom: 20px;
        }
        .invoice-details p {
            margin: 5px 0;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .invoice-table th, .invoice-table td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        .invoice-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .invoice-table td {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .total {
            text-align: right;
            font-weight: bold;
            margin-top: 20px;
        }
        .print-btn {
            display: block;
            width: 100px;
            margin: 5px auto;
            padding: 10px;
            background-color: #fff;
            color: #000;
            border: 1px solid #000;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
        }
        .print-btn:hover {
            background-color: #212529;
            color: #fff;
        }
        .paid-stamp {
            display: none;
            position: absolute;
            top: 32%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(255, 0, 0, 0.19);
            font-weight: bold;
            text-transform: uppercase;
            z-index: 1;
            width: 100%;
            text-align: center;
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
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
        }
        @media print {
            .header, .footer, .print-btn {
                display: none;
            }
            .container {
                margin-top: 20px;
                border: none;
                box-shadow: none;
            }
            .paid-stamp {
                display: block;
            }
        }
    </style>
    <script>
        function handleBack() {
            window.location.href = 'reporting.php';
        }

        function handlePrint() {
            const stamp = document.querySelector('.paid-stamp');
            stamp.style.display = 'block';
            setTimeout(() => {
                window.print();
                stamp.style.display = 'none';
            }, 100);
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>nakofi cafe</h1>
        <button class="logout" onclick="handleBack()">Back</button>
    </div>
    <div class="container">
        <h1>Invoice</h1>
        <div class="invoice-details">
            <p><strong>Invoice #: 12345</strong></p>
            <p>Date: 28/06/2025</p>
            <p>Supplier: Nakofi Cafe</p>
            <p>Tracking Number: 678890</p>
        </div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Unit Price (RM)</th>
                    <th>Total (RM)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Coffee Beans</td>
                    <td>10</td>
                    <td>13.74</td>
                    <td>137.40</td>
                </tr>
                <tr>
                    <td>Milk</td>
                    <td>5</td>
                    <td>27.00</td>
                    <td>135.00</td>
                </tr>
            </tbody>
        </table>
        <div class="total">Total: RM325.00</div>
        <button class="print-btn" onclick="handlePrint()">Print</button>
        <div class="paid-stamp">PAID</div>
    </div>
    <div class="footer">
        <p>© 2025 Nakofi Cafe. All rights reserved.</p>
    </div>
</body>
</html>