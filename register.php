<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'staff_leader') {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'] ?? '';
$user_name = $_SESSION['user_name'] ?? 'Staff Leader';
$email     = $_SESSION['email'] ?? '';
$display_name = strtoupper($user_name);

// Handle preserved values and error
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$selected_role = isset($_GET['role']) ? htmlspecialchars($_GET['role']) : '';
$name_value = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '';
$email_value = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
$user_id_value = isset($_GET['user_id']) ? htmlspecialchars($_GET['user_id']) : '';

// Success message
$success_message = '';
if (isset($_SESSION['register_success'])) {
    $success_message = htmlspecialchars($_SESSION['register_success']);
    unset($_SESSION['register_success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe - User Register</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root{
            --white:#fff;--charcoal:#212529;--bg:#F9FAFB;--card:#fff;--border:#E5E7EB;--text:#111827;--muted:#6B7280;
            --radius:20px;--trans:all .4s cubic-bezier(.16,1,.3,1);
            --shadow:0 6px 20px rgba(0,0,0,.08);--shadow-hover:0 15px 35px rgba(0,0,0,.12);
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Playfair Display',serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}

        /* Sidebar - Same as staff_leader.php */
        .sidebar{
            width:280px;
            background:var(--white);
            border-right:1px solid var(--border);
            position:fixed;
            left:0;top:0;bottom:0;
            padding:40px 20px;
            box-shadow:var(--shadow);
            display:flex;
            flex-direction:column;
            z-index:10;
        }
        .logo{
            text-align:center;
            margin-bottom:40px;
            flex-shrink:0;
        }
        .logo img{height:80px;filter:grayscale(100%) opacity(0.8);}
        .logo h1{font-size:28px;color:var(--charcoal);margin-top:16px;letter-spacing:-1px;}

        .menu-container{
            flex:1;
            overflow-y:auto;
            padding-right:8px;
            margin-bottom:20px;
        }
        .menu-container::-webkit-scrollbar{width:6px;}
        .menu-container::-webkit-scrollbar-track{background:transparent;}
        .menu-container::-webkit-scrollbar-thumb{background:#cbd5e0;border-radius:3px;}

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
            padding-top:20px;
            border-top:1px solid var(--border);
        }
        .logout-item .menu-item{color:#dc2626;}
        .logout-item .menu-item i{color:#dc2626;}
        .logout-item .menu-item:hover{background:#fee2e2;color:#dc2626;}

        /* Main Content Area */
        .main{
            margin-left:280px;
            width:calc(100% - 280px);
            padding:40px 60px;
            min-height:100vh;
            display:flex;
            flex-direction:column;
        }

        .page-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:40px;
        }
        .page-title{
            font-size:36px;
            color:var(--charcoal);
            letter-spacing:-1.2px;
        }
        .user-profile{position:relative;}
        .user-btn{
            display:flex;
            align-items:center;
            gap:10px;
            background:var(--white);
            border:2px solid var(--charcoal);
            padding:10px 24px;
            border-radius:50px;
            font-weight:700;
            cursor:pointer;
            transition:var(--trans);
            box-shadow:var(--shadow);
        }
        .user-btn i{font-size:23px;color:var(--charcoal);}
        .user-btn:hover{background:var(--charcoal);color:var(--white);}
        .user-btn:hover i{color:var(--white);}
        .user-tooltip{
            position:absolute;
            top:100%;right:0;
            background:var(--white);
            min-width:260px;
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:20px;
            opacity:0;visibility:hidden;
            transform:translateY(10px);
            transition:var(--trans);
            margin-top:12px;
            z-index:20;
            border:1px solid var(--border);
        }
        .user-profile:hover .user-tooltip{opacity:1;visibility:visible;transform:translateY(0);}
        .user-tooltip .detail{font-size:15px;color:var(--muted);margin-top:6px;}

        /* Register Card - Centered */
        .register-card{
            background:var(--white);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            overflow:hidden;
            border:1px solid var(--border);
            display:flex;
            max-width:1000px;
            width:100%;
            margin:0 auto;
            flex:1;
        }

        .hero-side{
            flex:1;
            background:var(--white);
            padding:60px 40px;
            display:flex;
            flex-direction:column;
            justify-content:center;
            align-items:center;
            text-align:center;
        }
        .hero-logo{
            width:250px;
            height:250px;
            margin-bottom:32px;
            object-fit:contain;
            border-radius:50%;
        }
        .hero-title{
            font-size:38px;
            font-weight:700;
            letter-spacing:-1.5px;
            margin-bottom:16px;
            color:var(--charcoal);
        }
        .hero-subtitle{
            font-size:18px;
            color:var(--muted);
            line-height:1.6;
            max-width:380px;
        }

        .form-side{
            flex:1;
            background:var(--white);
            padding:60px 50px;
            display:flex;
            flex-direction:column;
            justify-content:center;
        }
        .form-title{
            font-size:30px;
            color:var(--charcoal);
            text-align:center;
            margin-bottom:8px;
            letter-spacing:-1px;
        }

        .form-group{margin-bottom:22px;}
        .form-label{
            display:block;
            margin-bottom:10px;
            font-size:16px;
            color:var(--charcoal);
            font-weight:700;
        }
        .input-wrapper{position:relative;}
        .form-control{
            width:100%;
            padding:16px 20px 16px 52px;
            border:2px solid var(--border);
            border-radius:16px;
            font-size:16px;
            background:var(--white);
            transition:var(--trans);
            height:54px;
        }
        .form-control:focus{
            outline:none;
            border-color:var(--charcoal);
            box-shadow:0 0 0 4px rgba(33,37,41,0.1);
        }
        .input-icon{
            position:absolute;
            left:18px;
            top:50%;
            transform:translateY(-50%);
            color:var(--muted);
            font-size:20px;
        }
        .password-toggle{
            position:absolute;
            right:18px;
            top:50%;
            transform:translateY(-50%);
            background:none;
            border:none;
            color:var(--muted);
            cursor:pointer;
            font-size:20px;
            padding:4px;
            border-radius:50%;
            transition:var(--trans);
        }
        .password-toggle:hover{
            color:var(--charcoal);
            background:rgba(33,37,41,0.05);
        }

        .role-group{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:14px;
            margin-top:12px;
        }
        .role-option{
            padding:18px 10px;
            border:2px solid var(--border);
            border-radius:16px;
            text-align:center;
            cursor:pointer;
            transition:var(--trans);
            background:var(--white);
        }
        .role-option:hover{
            border-color:var(--charcoal);
            transform:translateY(-4px);
            box-shadow:var(--shadow);
        }
        .role-option.selected{
            border-color:var(--charcoal);
            background:var(--charcoal);
            color:var(--white);
        }
        .role-option.selected .role-icon{color:var(--white);}
        .role-icon{font-size:30px;margin-bottom:10px;color:var(--charcoal);}
        .role-label{font-size:15px;font-weight:700;}

        .submit-btn{
            width:100%;
            padding:18px;
            background:var(--charcoal);
            color:var(--white);
            border:none;
            border-radius:16px;
            font-size:18px;
            font-weight:700;
            cursor:pointer;
            transition:var(--trans);
            margin-top:20px;
        }
        .submit-btn:hover{
            background:#1a1d21;
            transform:translateY(-2px);
            box-shadow:var(--shadow-hover);
        }

        .alert{
            border-radius:12px;
            padding:16px;
            margin-bottom:24px;
            font-size:15px;
            border-left:4px solid #fca5a5;
            background:#fee2e2;
            color:#dc2626;
        }

        footer{
            text-align:center;
            padding:30px 20px;
            color:var(--muted);
            font-size:14px;
            margin-top:auto;
        }

        @media (max-width:992px){
            .sidebar{transform:translateX(-280px);transition:transform .4s ease;}
            .sidebar.open{transform:translateX(0);}
            .main{margin-left:0;padding:30px 20px;}
            .register-card{flex-direction:column;max-width:600px;}
            .hero-side{padding:50px 30px;border-radius:var(--radius) var(--radius) 0 0;}
            .hero-logo{width:160px;height:160px;}
            .form-side{padding:50px 40px;}
        }

        @media (max-width:576px){
            .hero-side{padding:40px 20px;}
            .form-side{padding:40px 30px;}
            .role-group{grid-template-columns:1fr;}
            .hero-title{font-size:28px;}
            .form-title{font-size:28px;}
        }
    </style>
</head>
<body>

<!-- Sidebar (same as staff_leader.php) -->
<div class="sidebar">
    <div class="logo">
        <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe">
        <h1>Nakofi Cafe</h1>
    </div>

    <div class="menu-container">
        <div class="menu-item" onclick="location='staff_leader.php'"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></div>
        <div class="menu-item" onclick="location='stock_take.php'"><i class="fas fa-clipboard-list"></i><span>Stock Take</span></div>
        <div class="menu-item" onclick="location='stock_count.php'"><i class="fas fa-boxes-stacked"></i><span>Stock Count</span></div>
        <div class="menu-item" onclick="location='stock_drafts.php'"><i class="fas fa-file-circle-plus"></i><span>Stock Take Drafts</span></div>
        <div class="menu-item" onclick="location='payment.php'"><i class="fas fa-credit-card"></i><span>Payment</span></div>
        <div class="menu-item" onclick="location='track.php'"><i class="fas fa-truck"></i><span>Delivery Tracking</span></div>
        <div class="menu-item" onclick="location='reporting.php'"><i class="fas fa-chart-line"></i><span>Inventory Audit Reports</span></div>
        <div class="menu-item active"><i class="fas fa-users-cog"></i><span>User Register</span></div>
    </div>

    <div class="logout-item">
        <div class="menu-item" onclick="logoutConfirm()"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="page-header">
        <div class="page-title">User Register</div>
        <div class="user-profile">
            <button class="user-btn"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($display_name); ?></button>
            <div class="user-tooltip">
                <div class="detail"><strong>ID:</strong> <?php echo htmlspecialchars($user_id); ?></div>
                <div class="detail"><strong></strong> <?php echo htmlspecialchars($display_name); ?></div>
                <div class="detail"><strong></strong> <?php echo htmlspecialchars($email); ?></div>
            </div>
        </div>
    </div>

    <div class="register-card">
        <div class="hero-side">
            <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" alt="Nakofi Cafe" class="hero-logo">
            <h1 class="hero-title">Nakofi Cafe</h1>
            <p class="hero-subtitle">Join our dedicated team and become part of a warm, passionate family committed to excellence.</p>
        </div>

        <div class="form-side">
            <?php if ($error): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>

            <h2 class="form-title">Create Account</h2>

            <form action="register_process.php" method="POST" id="registerForm">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="name" class="form-control" placeholder="Enter full name" required value="<?php echo $name_value; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control" placeholder="Enter email" required value="<?php echo $email_value; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">ID Number</label>
                    <div class="input-wrapper">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="user_id" class="form-control" placeholder="Staff/Supplier ID" required value="<?php echo $user_id_value; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Role</label>
                    <div class="role-group">
                        <div class="role-option <?php echo $selected_role === '1' ? 'selected' : ''; ?>" data-role="1">
                            <i class="fas fa-user-tie role-icon"></i>
                            <div class="role-label">Staff</div>
                        </div>
                        <div class="role-option <?php echo $selected_role === '2' ? 'selected' : ''; ?>" data-role="2">
                            <i class="fas fa-user-shield role-icon"></i>
                            <div class="role-label">Staff Leader</div>
                        </div>
                        <div class="role-option <?php echo $selected_role === '3' ? 'selected' : ''; ?>" data-role="3">
                            <i class="fas fa-truck role-icon"></i>
                            <div class="role-label">Supplier</div>
                        </div>
                    </div>
                    <input type="hidden" name="role" id="roleInput" value="<?php echo $selected_role; ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Create password" required minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword(this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword(this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Create Account</button>
            </form>
        </div>
    </div>

    <footer>© 2025 Nakofi Cafe. All rights reserved.</footer>
</div>

<?php if ($success_message): ?>
<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <div style="background:white;padding:40px;border-radius:20px;text-align:center;max-width:500px;box-shadow:var(--shadow-hover);">
        <i class="fas fa-check-circle" style="font-size:70px;color:#28a745;margin-bottom:24px;"></i>
        <h4 style="font-size:28px;margin-bottom:16px;">Registration Successful!</h4>
        <p style="color:#6c757d;font-size:17px;margin-bottom:30px;"><?php echo $success_message; ?></p>
        <a href="staff_leader.php" style="background:#212529;color:white;padding:14px 40px;border-radius:50px;text-decoration:none;font-weight:700;font-size:17px;">Go to Dashboard</a>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.role-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        document.getElementById('roleInput').value = this.dataset.role;
    });
});

function togglePassword(button) {
    const wrapper = button.closest('.input-wrapper');
    const field = wrapper.querySelector('input');
    const icon = button.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

document.getElementById('registerForm').addEventListener('submit', function(e) {
    const pwd = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    if (pwd !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
});

function logoutConfirm() {
    document.body.style.opacity = '0.6';
    document.body.style.pointerEvents = 'none';
    setTimeout(() => location.href = 'login.php', 800);
}
</script>

</body>
</html>