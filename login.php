<?php
session_start();
include 'config.php'; // Your database connection file (with $conn)

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $error = '';

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        // Search across all three tables using UNION
        $query = "
            SELECT 'staff_leader' AS user_type, slID AS id, name, password, roles_id 
            FROM staff_leader 
            WHERE email = ?
            UNION
            SELECT 'staff' AS user_type, sID AS id, name, password, roles_id 
            FROM staff 
            WHERE email = ?
            UNION
            SELECT 'supplier' AS user_type, supID AS id, name AS name, password, roles_id 
            FROM supplier 
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $email, $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Since you're storing plain text passwords (for now)
            if ($password === $user['password']) {
                // Successful login
                $_SESSION['logged_in'] = true;
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['email'] = $email;
                $_SESSION['roles_id'] = $user['roles_id'];

                // Redirect based on user type
                switch ($user['user_type']) {
                    case 'staff_leader':
                        header("Location: staff_leader.php");
                        exit();
                    case 'staff':
                        header("Location: staff.php");
                        exit();
                    case 'supplier':
                        header("Location: supplier.php");
                        exit();
                    default:
                        header("Location: login.php");
                        exit();
                }
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No account found with that email.";
        }

        $stmt->close();
    }

    // If error, store in variable to display below
    $login_error = $error;
}
?>

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>styles.css">
    
    <!-- Playfair Display -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&display=swap" rel="stylesheet">

    <title>Nakofi Cafe</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">

    <style>
        body, p, label, input, button, .form-control, .btn, .modal-title, .form-label, .text-primary {
            font-family: 'Playfair Display', serif !important;
            font-weight: 400 !important;
        }
        
        h3 {
            font-family: 'Playfair Display', serif !important;
            font-weight: 800 !important;
        }

        h3 { font-size: 1.75rem; }
        .form-label { font-size: 0.9rem; }
        .form-control { font-size: 1rem; }
        .btn { font-size: 1rem; }
    </style>
</head>
<body>
    <!-- Background circles -->
    <div class="d-none d-md-block ball login bg-chocolate position-absolute rounded-circle"></div>
    <div class="d-none d-md-block ball login bg-chocolate position-absolute rounded-circle" style="top: 20%; left: 70%; width: 300px; height: 300px; opacity: 0.7;"></div>

    <!-- Login Section -->
    <div class="container login__form active">
        <div class="row vh-100 w-100 align-self-center">
            <div class="col-12 col-lg-6 col-xl-6 px-5">
                <div class="row vh-100">
                    <div class="col align-self-center p-5 w-100">
                        <h3>NAKOFI CAFE SYSTEM</h3>
                        
                        <!-- Login Form -->
                        <form method="POST" class="mt-5" id="loginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email"
                                    class="form-control rounded-pill fw-lighter fs-7 p-3"
                                    id="email"
                                    name="email"
                                    placeholder="Enter your registered email"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    required
                                    style="border: 1px solid #212529; background-color: transparent; color: #212529;">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="d-flex position-relative">
                                    <input type="password"
                                        class="form-control rounded-pill fw-lighter fs-7 p-3"
                                        id="password"
                                        name="password"
                                        placeholder="Password"
                                        required
                                        style="border: 1px solid #212529; background-color: transparent; color: #212529;">
                                    <span class="password__icon fs-4 bi bi-eye-slash" role="button" 
                                        onclick="togglePassword()"
                                        style="position: absolute; top: 50%; left: 109%; transform: translate(-50%, -50%); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; z-index: 3; color: #442711;"></span>
                                </div>

                                <!-- Error Message -->
                                <?php if (!empty($login_error)): ?>
                                    <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($login_error); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col text-center">
                                <button type="submit" class="btn btn-outline-dark btn-lg rounded-pill mt-4 w-100">Login</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right side image -->
            <div class="d-none d-lg-block col-lg-6 col-xl-6 p-5">
                <div class="row align-items-center justify-content-center h-100">
                    <div class="col text-center">
                        <img src="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" class="bounce right-image" alt="Nakofi Cafe Logo">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
    <script src="script.js"></script>
</body>
</html>