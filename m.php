<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$servername = "localhost";
$username = "root"; // Update with your DB username
$password = ""; // Update with your DB password
$dbname = "nakofi_cafe";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get POST data
    $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($user_id) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'ID and Password are required']);
        exit;
    }

    // Query to check user credentials and fetch role
    $stmt = $conn->prepare("
        SELECT users.user_id, users.password, COALESCE(staff.roles_id, staff_leader.roles_id, supplier.roles_id) AS role
        FROM users
        LEFT JOIN staff ON users.user_id = staff.sID
        LEFT JOIN staff_leader ON users.user_id = staff_leader.slID
        LEFT JOIN supplier ON users.user_id = supplier.supID
        WHERE users.user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['role'] === null || !in_array($user['role'], [1, 2, 3])) {
            error_log("Invalid role for user_id: $user_id, role: " . $user['role'], 3, "error.log");
            echo json_encode(['success' => false, 'message' => 'Invalid role assigned']);
            exit;
        }
        // Start session and store user data
        session_start();
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode(['success' => true, 'role' => (int)$user['role']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID or Password']);
    }
} catch (PDOException $e) {
    error_log("Database error in m.php: " . $e->getMessage(), 3, "error.log");
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in m.php: " . $e->getMessage(), 3, "error.log");
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

$conn = null;
?>