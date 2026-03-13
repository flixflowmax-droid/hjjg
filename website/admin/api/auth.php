<?php
// api/auth.php
session_start();
header('Content-Type: application/json');

$creds_file = __DIR__ . '/admin_credentials.json';

function get_creds() {
    global $creds_file;
    return json_decode(file_get_contents($creds_file), true);
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    $creds = get_creds();

    if ($username === $creds['username'] && $password === $creds['password_plain']) {
        $_SESSION['admin_auth'] = true;
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
    exit;
}

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Check session API
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false]);
    }
    exit;
}

// Validate any required script inclusion
function checkAdminSession() {
    if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}
?>
