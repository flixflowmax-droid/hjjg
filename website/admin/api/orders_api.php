<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');

require_once '../../user/api/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT * FROM orders ORDER BY created_at DESC");
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Decode the items_json if available so the frontend doesn't struggle
            foreach ($orders as &$o) {
                $o['items'] = !empty($o['items_json']) ? json_decode($o['items_json'], true) : [];
            }
            echo json_encode(['success' => true, 'data' => $orders]);
        } else {
            echo json_encode(['success' => true, 'data' => []]);
        }
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
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    if ($action === 'status') {
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? 'Pending'; // Pending, Processing, Delivered
        
        if ($pdo) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No database connection']);
        }
        exit;
    }
}
?>
