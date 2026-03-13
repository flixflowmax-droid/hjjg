<?php
session_start();

// ======== DATABASE CONFIGURATION ========
$host = '127.0.0.1';
$dbname = 'test_db'; // Update with your actual database name
$user = 'root';      // Update with your database user
$pass = '';          // Update with your database password
// ========================================

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Get POST payload
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (!isset($input['firebase_uid']) || !isset($input['email'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing essential user data']);
    exit;
}

$firebase_uid = $input['firebase_uid'];
$first_name = $input['first_name'] ?? '';
$last_name = $input['last_name'] ?? '';
$email = $input['email'];
$auth_provider = $input['auth_provider'] ?? 'Email';
$is_verified = !empty($input['is_verified']) ? 1 : 0;

// Connect to Database
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

try {
    // Check if user already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE firebase_uid = ? LIMIT 1');
    $stmt->execute([$firebase_uid]);
    $userRow = $stmt->fetch();

    if ($userRow) {
        $local_user_id = $userRow['id'];
        
        // Update last login & verification status
        $updateStmt = $pdo->prepare('UPDATE users SET last_login = NOW(), is_verified = ? WHERE id = ?');
        $updateStmt->execute([$is_verified, $local_user_id]);
    } else {
        // Insert new user
        $insertStmt = $pdo->prepare('INSERT INTO users (firebase_uid, first_name, last_name, email, auth_provider, is_verified) VALUES (?, ?, ?, ?, ?, ?)');
        $insertStmt->execute([$firebase_uid, $first_name, $last_name, $email, $auth_provider, $is_verified]);
        
        $local_user_id = $pdo->lastInsertId();
    }

    // Initialize session for recognized logged-in user
    $_SESSION['user_id'] = $local_user_id;
    $_SESSION['firebase_uid'] = $firebase_uid;
    $_SESSION['email'] = $email;
    $_SESSION['first_name'] = $first_name;

    echo json_encode(['status' => 'success', 'message' => 'Session created successfully', 'user_id' => $local_user_id]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database query failed: ' . $e->getMessage()]);
}
?>
