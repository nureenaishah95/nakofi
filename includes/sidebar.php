<?php
// includes/sidebar.php - Reusable sidebar for all pages

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get user role (staff or staff_leader)
$role = $_SESSION['user_type'] ?? 'staff';
$is_leader = ($role === 'staff_leader');

// Current page name (to highlight active menu)
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="logo">
        <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>

    <div class="menu-container">
        <!-- Dashboard -->
        <div class="menu-item <?php echo in_array($current_page, ['staff.php', 'staff_leader.php']) ? 'active' : ''; ?>"
             onclick="location.href='<?php echo $is_leader ? 'staff_leader.php' : 'staff.php'; ?>'">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </div>

        <!-- Stock Take -->
        <div class="menu-item <?php echo $current_page === 'stock_take.php' ? 'active' : ''; ?>"
             onclick="location.href='stock_take.php'">
            <i class="fas fa-clipboard-list"></i><span>Stock Take</span>
        </div>

        <!-- Stock Count -->
        <div class="menu-item <?php echo $current_page === 'stock_count.php' ? 'active' : ''; ?>"
             onclick="location.href='stock_count.php'">
            <i class="fas fa-boxes-stacked"></i><span>Stock Count</span>
        </div>

        <!-- Drafts -->
        <div class="menu-item <?php echo $current_page === 'stock_drafts.php' ? 'active' : ''; ?>"
             onclick="location.href='stock_drafts.php'">
            <i class="fas fa-file-circle-plus"></i><span>Stock Take Drafts</span>
        </div>

        <!-- Delivery Tracking -->
        <div class="menu-item <?php echo $current_page === 'track.php' ? 'active' : ''; ?>"
             onclick="location.href='track.php'">
            <i class="fas fa-truck"></i><span>Delivery Tracking</span>
        </div>

        <?php if ($is_leader): ?>
        <!-- Only staff_leader sees these -->
        <div class="menu-item <?php echo $current_page === 'payment.php' ? 'active' : ''; ?>"
             onclick="location.href='payment.php'">
            <i class="fas fa-credit-card"></i><span>Payment</span>
        </div>

        <div class="menu-item <?php echo $current_page === 'reporting.php' ? 'active' : ''; ?>"
             onclick="location.href='reporting.php'">
            <i class="fas fa-chart-line"></i><span>Inventory Audit Reports</span>
        </div>

        <div class="menu-item <?php echo $current_page === 'register.php' ? 'active' : ''; ?>"
             onclick="location.href='register.php'">
            <i class="fas fa-users-cog"></i><span>Staff Register</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </div>
    </div>
</div>

<style>
.sidebar{width:280px;background:#fff;border-right:1px solid #e5e7eb;position:fixed;left:0;top:0;bottom:0;padding:40px 20px;box-shadow:0 6px 20px rgba(0,0,0,.08);display:flex;flex-direction:column;z-index:10;transition:transform .4s ease;}
.sidebar.closed{transform:translateX(-280px);}
.logo{text-align:center;margin-bottom:40px;}
.logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
.logo h1{font-size:28px;color:#212529;margin-top:16px;letter-spacing:-1px;}
.menu-container{flex:1;overflow-y:auto;}
.menu-item{display:flex;align-items:center;gap:16px;padding:14px 20px;color:#111827;font-size:16px;font-weight:700;border-radius:16px;cursor:pointer;transition:all .4s;margin-bottom:8px;}
.menu-item i{font-size:22px;color:#D1D5DB;}
.menu-item:hover{background:#f1f5f9;}
.menu-item:hover i{color:#212529;}
.menu-item.active{background:#212529;color:#fff;}
.menu-item.active i{color:#fff;}
.logout-item{padding-top:20px;border-top:1px solid #e5e7eb;}
.logout-item .menu-item{color:#dc2626;}
.logout-item .menu-item i{color:#dc2626;}
.logout-item .menu-item:hover{background:#fee2e2;}
</style>

<script>
function logoutConfirm() {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
    setTimeout(() => location.href = 'login.php', 800);
}
</script>