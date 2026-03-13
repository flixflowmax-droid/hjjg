<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');

// Include user's DB connection
require_once '../../user/api/db_connect.php'; 
// Assuming $pdo is available from db_connect.php

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'details') {
        $id = $_GET['id'] ?? null;
        try {
            // Fetch User
            $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }
            
            // Fetch Orders for this user
            // Note: Since users log in with email, we can match orders by email OR name/phone if available. 
            // In a better system, orders would have a user_id. Let's try matching by customer_phone or customer_name or email.
            // Assuming we added user_id to orders during checkout, but let's check what's available.
            // For now, let's look for orders matching this user's email if possible, or just mock if we don't have user_id in orders.
            // Actually, let's try matching by email if it exists in orders table (need to check columns).
            // Checking users table columns from previous auth.php: id, name, email, password_hash, phone.
            // Checking orders table columns from previous checkout_api.php: id, customer_name, customer_phone, items_json, etc.
            
            $stmt = $pdo->prepare("SELECT id, total, status, created_at FROM orders WHERE customer_phone = ? OR customer_name = ? ORDER BY created_at DESC");
            $stmt->execute([$user['phone'], $user['name']]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $user['order_count'] = count($orders);
            $user['orders'] = $orders;
            
            echo json_encode(['success' => true, 'data' => $user]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    try {
        // We will try to fetch users. If the table doesn't exist yet, we handle gracefully.
        $stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == '42S02') { // Table not found
            // Create dummy user response if DB isn't strictly set up yet by user
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Role/Status or Delete
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    if ($action === 'status') {
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? 'Active'; // Active, Banned
        
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}
?>
