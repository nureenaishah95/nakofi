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
    $svId = isset($_POST['svId']) ? trim($_POST['svId']) : '';
    $svPassword = isset($_POST['svPassword']) ? trim($_POST['svPassword']) : '';

    if (empty($svId) || empty($svPassword)) {
        echo json_encode(['success' => false, 'message' => 'Staff Leader ID and Password are required']);
        exit;
    }

    // Query to check supervisor credentials
    $stmt = $conn->prepare("
        SELECT users.user_id, users.password
        FROM users
        JOIN staff_leader ON users.user_id = staff_leader.slID
        WHERE users.user_id = :user_id AND staff_leader.roles_id = :role
    ");
    $stmt->bindParam(':user_id', $svId, PDO::PARAM_STR);
    $stmt->bindParam(':role', $role, PDO::PARAM_INT);
    $role = 2; // Staff Leader role
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($svPassword, $user['password'])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Staff Leader ID or Password']);
    }
} catch (PDOException $e) {
    error_log("Database error in auth.php: " . $e->getMessage(), 3, "error.log");
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in auth.php: " . $e->getMessage(), 3, "error.log");
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

$conn = null;
?>