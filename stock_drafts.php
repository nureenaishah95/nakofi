<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ================= USER =================
$stmt = $conn->prepare("SELECT name, role, email FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$name = strtoupper($user['name'] ?? 'User');
$is_leader = ($user['role'] ?? 'staff') === 'staff_leader';

// Fetch draft
$draft = null;
$last_saved = 'Never';

$stmt = $conn->prepare("SELECT draft_data, updated_at FROM stock_drafts WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $draft = json_decode($row['draft_data'], true);
    $last_saved = date('l, F j, Y \a\t g:i A', strtotime($row['updated_at']));
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - Stock Drafts</title>
    <link rel="icon" href="http://localhost/nakofi/asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{--white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;--radius:24px;--shadow:0 12px 32px rgba(0,0,0,.06);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

        .sidebar{width:280px;background:var(--white);border-right:1px solid var(--border);position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:var(--shadow);display:flex;flex-direction:column;z-index:10;transition:transform .4s ease;}
        .sidebar.closed{transform:translateX(-280px);}
        .logo{text-align:center;margin-bottom:40px;}
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}

        /* Scrollable menu area */
        .menu-container{
            flex:1;
            overflow-y:auto;
            padding-right:8px;
            margin-bottom:20px;
        }
        .menu-container::-webkit-scrollbar{width:6px;}
        .menu-container::-webkit-scrollbar-track{background:transparent;}
        .menu-container::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px;}
        .menu-container::-webkit-scrollbar-thumb:hover{background:#a0aec0;}

        .menu-item{
            display:flex;
            align-items:center;
            gap:16px;
            padding:14px 20px;
            color:var(--text);
            font-size:16px;
            font-weight:700;
            border-radius:16px;
            cursor:pointer;
            transition:var(--trans);
            margin-bottom:8px;
        }
        .menu-item i{font-size:22px;color:#D1D5DB;transition:var(--trans);}
        .menu-item:hover{background:#f1f5f9;}
        .menu-item:hover i{color:var(--charcoal);}
        .menu-item.active{background:var(--charcoal);color:var(--white);}
        .menu-item.active i{color:var(--white);}

        .logout-item{
            flex-shrink:0;
            padding-top:-10px;
            border-top:1px solid var(--border);
        }
        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}
        .logout-item .menu-item:hover{background:#fee2e2;color:#dc2626;}

        .main{margin-left:280px;width:calc(100% - 280px);padding:40px 60px;transition:margin-left .4s ease;}
        .main.full{margin-left:0;padding:30px 20px;}

        .sidebar-toggle{position:fixed;top:20px;left:20px;background:var(--charcoal);color:white;width:50px;height:50px;border-radius:50%;display:none;align-items:center;justify-content:center;font-size:24px;cursor:pointer;z-index:100;box-shadow:var(--shadow);}
        .sidebar-toggle:hover{background:#111;}

        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
        .page-title{font-size:36px;color:var(--charcoal);letter-spacing:-1.2px;}
        .user-profile{position:relative;}
        .user-btn{display:flex;align-items:center;gap:10px;background:var(--white);border:2px solid var(--charcoal);padding:10px 24px;border-radius:50px;font-weight:700;cursor:pointer;transition:all .4s;box-shadow:var(--shadow);}
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{position:absolute;top:100%;right:0;background:var(--white);min-width:260px;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;opacity:0;visibility:hidden;transform:translateY(10px);transition:all .4s;margin-top:12px;z-index:20;border:1px solid var(--border);}
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}

        .container{max-width:900px;margin:0 auto;padding:40px 20px;text-align:center;}
        .title{font-size:36px;color:var(--charcoal);margin-bottom:16px;letter-spacing:-1px;}
        .subtitle{font-size:14px;color:var(--muted);margin-bottom:40px;}
        .draft-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:48px 40px;box-shadow:var(--shadow);max-width:600px;margin:0 auto;}
        .draft-card h2{font-size:28px;margin-bottom:20px;color:var(--charcoal);}
        .saved-time{font-size:16px;color:var(--muted);margin:20px 0;}
        .items-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(200px,1fr));gap:20px;margin:30px 0;}
        .item{background:#f8f9fa;padding:20px;border-radius:16px;}
        .item-name{font-size:18px;color:var(--charcoal);margin-bottom:8px;}
        .item-count{font-size:32px;font-weight:700;color:var(--charcoal);}
        .btn-continue{font-family:'Playfair Display',serif;background:var(--charcoal);color:white;padding:16px 48px;border:none;border-radius:50px;font-size:18px;font-weight:700;cursor:pointer;margin-top:30px;box-shadow:0 8px 25px rgba(33,37,41,.2);transition:all .4s;}
        .btn-continue:hover{transform:translateY(-4px);box-shadow:0 16px 35px rgba(33,37,41,.3);}
        .no-draft{background:#f1f3f5;padding:60px;border-radius:var(--radius);color:var(--muted);}
        .no-draft i{font-size:60px;margin-bottom:20px;opacity:0.4;}
        footer{text-align:center;padding:40px 20px;color:var(--muted);font-size:13px;margin-top:60px;}

        @media(max-width:992px){
            .sidebar{transform:translateX(-280px);}
            .sidebar.open{transform:translateX(0);}
            .main{margin-left:0;padding:30px 20px;}
            .sidebar-toggle{display:flex;}
            .items-grid{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>

<div class="sidebar-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>

<div class="sidebar" id="sidebar">
    <div class="logo">
        <img src="http://localhost/nakofi/asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>
    <div class="menu-container">
        <div class="menu-item" onclick="location.href='<?php echo $is_leader ? 'staff_leader.php' : 'staff.php'; ?>'">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </div>
        <div class="menu-item" onclick="location.href='stock_take.php'">
            <i class="fas fa-clipboard-list"></i><span>Stock Take</span>
        </div>
        <div class="menu-item" onclick="location.href='stock_count.php'">
            <i class="fas fa-boxes-stacked"></i><span>Stock Count</span>
        </div>
        <div class="menu-item active">
            <i class="fas fa-file-circle-plus"></i><span>Stock Take Drafts</span>
        </div>
        <div class="menu-item" onclick="location.href='track.php'">
            <i class="fas fa-truck"></i><span>Delivery Tracking</span>
        </div>

        <?php if ($is_leader): ?>
        <div class="menu-item" onclick="location.href='payment.php'">
            <i class="fas fa-credit-card"></i><span>Payment</span>
        </div>
        <div class="menu-item" onclick="location.href='reporting.php'">
            <i class="fas fa-chart-line"></i><span>Inventoru Audit Reports</span>
        </div>
        <div class="menu-item" onclick="location.href='register.php'">
            <i class="fas fa-users-cog"></i><span>User Register</span>
        </div>
        <?php endif; ?>
    </div>
    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </div>
    </div>
</div>

<div class="main" id="mainContent">
    <div class="page-header">
        <div class="page-title">My Stock Count Draft</div>
        <div class="user-profile">
            <button class="user-btn">
                <i class="fas fa-user-circle"></i> <?= $name ?>
            </button>
            <div class="user-tooltip">
                <div class="detail"><strong>ID:</strong> <?= htmlspecialchars($user_id) ?></div>
                <div class="detail"><strong></strong> <?= $name ?></div>
                <div class="detail"><strong></strong> <?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
        </div>
    </div>

    <div class="container">
        <p class="subtitle">Your unfinished stock count is safely saved</p>

        <?php if ($draft && is_array($draft) && array_sum($draft) > 0): ?>
            <div class="draft-card">
                <h2>You have a saved draft</h2>
                <div class="saved-time">
                    <i class="fas fa-clock"></i> Last saved: <strong><?php echo $last_saved; ?></strong>
                </div>

                <div class="items-grid">
                    <?php 
                    $names = [
                        'syrup' => 'Syrup',
                        'coffee' => 'Coffee Bean',
                        'milk' => 'Milk',
                        'matcha_niko_neko' => 'Matcha Niko Neko',
                        'whipped_cream' => 'Whipped Cream',
                        'monin_strawberry' => 'Monin Strawberry',
                        'caramel_syrup' => 'Caramel Syrup'
                    ];
                    foreach ($names as $key => $name): 
                        if (isset($draft[$key]) && $draft[$key] > 0): ?>
                            <div class="item">
                                <div class="item-name"><?php echo $name; ?></div>
                                <div class="item-count"><?php echo $draft[$key]; ?></div>
                            </div>
                        <?php endif; 
                    endforeach; ?>
                </div>

                <button class="btn-continue" onclick="location='stock_take.php'">
                    Continue Counting
                </button>
            </div>
        <?php else: ?>
            <div class="no-draft">
                <i class="fas fa-clipboard-list"></i>
                <h2>No draft found</h2>
                <p>You haven't started or saved a stock count yet.</p>
                <button class="btn-continue" onclick="location='stock_take.php'" style="margin-top:20px;background:#6c757d;">
                    Start New Stock Count
                </button>
            </div>
        <?php endif; ?>
    </div>

    <footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('mainContent').classList.toggle('full');
}

function logoutConfirm() {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
    setTimeout(() => location.href = 'login.php', 800);
}
</script>
</body>
</html>