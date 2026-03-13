<?php
// user/api/profile_api.php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_profile') {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, phone, address_division, address_district, address_city, address_zip FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } 
    elseif ($action === 'get_orders') {
        try {
            $email = $_SESSION['user_email'];
            // Matches user email with customer_phone? No, customer_phone might be different. 
            // Better to match by email if we had it in orders, but orders has customer_phone.
            // Wait, does orders have email? Let's check checkout_api.php again.
            // It doesn't! We should probably add email to orders or match by phone.
            // However, sync_user uses email. 
            
            // For now, let's fetch by phone if available or we can add customer_email to orders.
            // Let's assume we can fetch by phone from user profile.
            $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch();
            $phone = $u['phone'] ?? '';

            if ($phone) {
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_phone = ? ORDER BY created_at DESC");
                $stmt->execute([$phone]);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($orders as &$o) {
                    $o['items'] = json_decode($o['items_json'], true);
                }
                echo json_encode(['success' => true, 'orders' => $orders]);
            } else {
                echo json_encode(['success' => true, 'orders' => []]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'update_profile') {
        $name = $data['name'] ?? '';
        $phone = $data['phone'] ?? '';
        $division = $data['address_division'] ?? '';
        $district = $data['address_district'] ?? '';
        $city = $data['address_city'] ?? '';
        $zip = $data['address_zip'] ?? '';

        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address_division = ?, address_district = ?, address_city = ?, address_zip = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $division, $district, $city, $zip, $userId]);
            
            $_SESSION['user_name'] = $name; // Update session
            echo json_encode(['success' => true, 'message' => 'Profile updated']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    elseif ($action === 'change_password') {
        $oldPass = $data['old_password'] ?? '';
        $newPass = $data['new_password'] ?? '';

        if (strlen($newPass) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password too short']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user && password_verify($oldPass, $user['password_hash'])) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $userId]);
                echo json_encode(['success' => true, 'message' => 'Password changed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Incorrect old password']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
?>
