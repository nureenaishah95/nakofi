<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $user_id          = trim($_POST['user_id'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role_id          = $_POST['role'] ?? '';

    // Preserve values on error (for your new form)
    $preserve = http_build_query([
        'name'    => $name,
        'email'   => $email,
        'user_id' => $user_id,
        'role'    => $role_id
    ]);

    // Validation
    if (empty($name) || empty($email) || empty($user_id) || empty($password) || empty($confirm_password) || empty($role_id)) {
        header("Location: register.php?error=" . urlencode("All fields are required") . "&" . $preserve);
        exit;
    }

    if ($password !== $confirm_password) {
        header("Location: register.php?error=" . urlencode("Passwords do not match") . "&" . $preserve);
        exit;
    }

    if (strlen($password) < 8) {
        header("Location: register.php?error=" . urlencode("Password must be at least 8 characters") . "&" . $preserve);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=" . urlencode("Invalid email format") . "&" . $preserve);
        exit;
    }

    if (!ctype_digit($user_id) || (int)$user_id <= 0) {
        header("Location: register.php?error=" . urlencode("User ID must be a positive number") . "&" . $preserve);
        exit;
    }
    $user_id = (int)$user_id;

    // Map role_id to role string
    $roles = ['1' => 'staff', '2' => 'staff_leader', '3' => 'supplier'];
    if (!isset($roles[$role_id])) {
        header("Location: register.php?error=" . urlencode("Invalid role") . "&" . $preserve);
        exit;
    }
    $role_string = $roles[$role_id];

    mysqli_begin_transaction($conn);

    try {
        // Check if user_id exists
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM users WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if ($count > 0) {
            throw new Exception("User ID already exists");
        }

        // Check if email exists
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $email_count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if ($email_count > 0) {
            throw new Exception("Email already registered");
        }

        // Insert into users table (plain text password)
        $stmt = mysqli_prepare($conn, "INSERT INTO users (user_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $name, $email, $password, $role_string);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Insert into role-specific table
        if ($role_id == '1') { // staff
            $stmt = mysqli_prepare($conn, "INSERT INTO staff (sID, roles_id, name, email, password) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisss", $user_id, $role_id, $name, $email, $password);
        } elseif ($role_id == '2') { // staff_leader
            $stmt = mysqli_prepare($conn, "INSERT INTO staff_leader (slID, roles_id, name, email, password) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisss", $user_id, $role_id, $name, $email, $password);
        } elseif ($role_id == '3') { // supplier
            $stmt = mysqli_prepare($conn, "INSERT INTO supplier (supID, roles_id, name, email, password) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisss", $user_id, $role_id, $name, $email, $password);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);

        $_SESSION['register_success'] = "Welcome, $name! Your account has been created successfully.";
        header("Location: register.php");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: register.php?error=" . urlencode($e->getMessage()) . "&" . $preserve);
        exit;
    }
} else {
    header("Location: register.php");
    exit;
}
?>