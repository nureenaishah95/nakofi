<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
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
    .container{max-width:600px;margin:100px auto;}
    .bank-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
    .bank-btn{display:flex;align-items:center;padding:24px;background:var(--card);border:2px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:var(--trans);box-shadow:var(--shadow);}
    .bank-btn:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,0.1);border-color:#28a745;}
    .bank-btn img{height:40px;margin-right:16px;}
    .bank-btn span{font-size:20px;font-weight:700;}
    footer{text-align:center;padding:60px 20px;color:#6B7280;font-size:13px;}
    @media(max-width:640px){.bank-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<header>
    <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Logo" class="logo">
    <h1>Nakofi Cafe</h1>
    <button class="back-btn" onclick="history.back()">Back</button>
</header>

<div class="container">
    <h1 style="text-align:center;font-size:38px;margin-bottom:20px;color:#212529;">Online Banking</h1>
    <p style="text-align:center;color:#6B7280;margin-bottom:40px;">Select your bank to proceed</p>

    <div class="bank-grid">
        <a href="https://www.maybank2u.com.my" target="_blank" class="bank-btn">
            <img src="https://www.maybank2u.com.my/maybank2u/malaysia/images/logo-maybank.png" alt="Maybank">
            <span>Maybank2u</span>
        </a>
        <a href="https://www.cimbclicks.com.my" target="_blank" class="bank-btn">
            <img src="https://logowik.com/content/uploads/images/cimb-bank-new-20229383.logowik.com.webp" alt="CIMB" style="height:36px;">
            <span>CIMB Clicks</span>
        </a>
        <a href="https://www.rhbnow.com.my" target="_blank" class="bank-btn">
            <img src="https://www.rhbgroup.com/assets/images/rhb-logo.png" alt="RHB">
            <span>RHB Now</span>
        </a>
        <a href="https://www.pbebank.com" target="_blank" class="bank-btn">
            <img src="https://www.pbebank.com/assets/images/logo.png" alt="Public Bank">
            <span>Public Bank</span>
        </a>
        <a href="https://www.hlb.com.my" target="_blank" class="bank-btn">
            <img src="https://www.hongleongconnect.my/assets/images/hlb-logo.png" alt="Hong Leong">
            <span>Hong Leong</span>
        </a>
        <a href="https://www.bsn.com.my" target="_blank" class="bank-btn">
            <img src="https://www.bsn.com.my/sites/default/files/bsn-logo.png" alt="BSN">
            <span>BSN</span>
        </a>
    </div>

    <p style="text-align:center;margin-top:40px;color:#888;font-size:14px;">
        You will be redirected to your bank's secure login page.<br>
        After payment, return here to complete your order.
    </p>
</div>

<footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</body>
</html>