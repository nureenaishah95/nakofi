<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$grand_total = $_SESSION['grand_total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Nakofi Cafe</title>
<link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
    :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);--shadow:0 4px 20px rgba(0,0,0,0.04);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Playfair Display',serif;background:var(--bg);color:#111827;min-height:100vh;padding-top:100px;}
    header{position:fixed;top:0;left:0;right:0;background:var(--white);padding:20px 48px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,.03);z-index:1000;}
    header .logo{height:70px;} header h1{font-size:32px;color:var(--charcoal);margin-left:32px;}
    .back-btn{background:transparent;color:var(--charcoal);border:2px solid var(--charcoal);padding:10px 28px;border-radius:50px;font-weight:700;cursor:pointer;}
    .back-btn:hover{background:var(--charcoal);color:var(--white);}
    .container{max-width:500px;margin:100px auto;text-align:center;}
    .wallet-btn{display:block;width:100%;padding:24px;margin:20px 0;background:var(--card);border:2px solid var(--border);border-radius:var(--radius);font-size:22px;font-weight:700;cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
    .wallet-btn:hover{transform:translateY(-8px);box-shadow:0 20px 40px rgba(0,0,0,0.1);}
    .wallet-btn i{font-size:36px;margin-bottom:12px;display:block;}
    .tng{background:#0055ff;color:white;}.tng:hover{background:#0044cc;}
    .grab{background:#00c853;color:white;}.grab:hover{background:#00b140;}
    .shopee{background:#ee4d2d;color:white;}.shopee:hover{background:#e03a1a;}
    footer{text-align:center;padding:60px 20px;color:#6B7280;font-size:13px;}
</style>
</head>
<body>

<header>
    <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="back-btn" onclick="history.back()">Back</button>
</header>

<div class="container">
    <h1 style="font-size:38px;margin-bottom:20px;color:#212529;">Choose E-Wallet</h1>
    <p style="color:#6B7280;margin-bottom:40px;">Pay securely with your favorite e-wallet</p>

    <button onclick="location.href='payment_success.php'" class="wallet-btn tng">
        <i class="fas fa-mobile-alt"></i>
        Touch 'n Go eWallet
    </button>

    <button onclick="location.href='payment_success.php'" class="wallet-btn grab">
        <i class="fas fa-car"></i>
        GrabPay
    </button>

    <button onclick="location.href='payment_success.php'" class="wallet-btn shopee">
        <i class="fas fa-shopping-bag"></i>
        ShopeePay
    </button>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</body>
</html>