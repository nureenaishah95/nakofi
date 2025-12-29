<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get total from session or DB (reuse logic from payment.php)
$grand_total = $_SESSION['grand_total'] ?? 0;
if (!$grand_total) {
    $result = $conn->query("SELECT SUM(total)*1.06 AS grand_total FROM orders WHERE status='pending'");
    $row = $result->fetch_assoc();
    $grand_total = $row['grand_total'] ?? 0;
    $_SESSION['grand_total'] = $grand_total;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Nakofi Cafe</title>
<link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
    :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;--radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);--shadow:0 4px 20px rgba(0,0,0,0.04);--shadow-hover:0 20px 40px rgba(0,0,0,0.1);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;padding-top:100px;}
    header{position:fixed;top:0;left:0;right:0;background:var(--white);padding:20px 48px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,.03);z-index:1000;}
    header .logo{height:70px;width:auto;}
    header h1{font-size:32px;color:var(--charcoal);letter-spacing:-1.2px;margin-left:32px;}
    .back-btn{background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);padding:10px 28px;border-radius:50px;font-weight:700;font-size:14px;cursor:pointer;transition:var(--trans);}
    .back-btn:hover{background:var(--charcoal);color:var(--white);}
    .container{max-width:600px;margin:40px auto;padding:40px;background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);border:1px solid var(--border);}
    .page-title{text-align:center;font-size:38px;margin-bottom:10px;color:var(--charcoal);}
    .subtitle{text-align:center;color:var(--muted);margin-bottom:40px;}
    .form-group{margin-bottom:24px;}
    label{display:block;margin-bottom:8px;font-size:17px;color:var(--charcoal);}
    input[type=text], input[type=tel]{width:100%;padding:16px;border:2px solid var(--border);border-radius:12px;font-size:16px;transition:var(--trans);}
    input:focus{border-color:#28a745;outline:none;box-shadow:0 0 0 4px rgba(34,197,94,0.1);}
    .card-icons{text-align:right;margin-top:8px;}
    .card-icons i{font-size:28px;margin-left:12px;color:#555;}
    .pay-btn{display:block;width:100%;padding:18px;background:var(--charcoal);color:var(--white);border:none;border-radius:var(--radius);font-family:'Playfair Display',serif;font-size:22px;font-weight:700;cursor:pointer;transition:var(--trans);margin-top:30px;}
    .pay-btn:hover{background:#111;}
    footer{text-align:center;padding:60px 20px;color:var(--muted);font-size:13px;}
    @media(max-width:768px){header{flex-direction:column;gap:16px;padding:20px;text-align:center;} header .logo{height:50px;} header h1{font-size:28px;margin-left:0;}}
</style>
</head>
<body>

<header>
    <img src="http://localhost/nakofi/asset/img/logo.png" alt="Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="back-btn" onclick="history.back()">Back</button>
</header>

<div class="container">
    <h1 class="page-title">Credit / Debit Card</h1>
    <p class="subtitle">Secure payment powered by Stripe (demo)</p>

    <form action="payment_success.php" method="POST">
        <div class="form-group">
            <label>Card Number</label>
            <input type="tel" placeholder="1234 5678 9012 3456" maxlength="19" required>
            <div class="card-icons">
                <i class="fab fa-cc-visa"></i>
                <i class="fab fa-cc-mastercard"></i>
                <i class="fab fa-cc-amex"></i>
            </div>
        </div>

        <div class="form-group">
            <label>Cardholder Name</label>
            <input type="text" placeholder="Ahmad Bin Ali" required>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="text" placeholder="MM / YY" maxlength="5" required>
            </div>
            <div class="form-group">
                <label>CVC</label>
                <input type="tel" placeholder="123" maxlength="4" required>
            </div>
        </div>

        <button type="submit" class="pay-btn">Pay RM <?php echo number_format($grand_total, 2); ?></button>
    </form>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</body>
</html>