<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');
require_once '../../user/api/db_connect.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        try {
            $stmt = $pdo->prepare("SELECT id, section_name, is_visible, created_at FROM homepage_sections ORDER BY created_at ASC");
            $stmt->execute();
            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $sections]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'add') {
        $name = $data['section_name'] ?? '';
        try {
            $isVisible = $data['is_visible'] ?? true;
            $stmt = $pdo->prepare("INSERT INTO homepage_sections (section_name, is_visible) VALUES (?, ?)");
            $stmt->execute([$name, $isVisible ? 1 : 0]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'update') {
        $id = $data['id'] ?? '';
        $name = $data['section_name'] ?? '';
        try {
            $isVisible = $data['is_visible'] ?? true;
            $stmt = $pdo->prepare("UPDATE homepage_sections SET section_name = ?, is_visible = ? WHERE id = ?");
            $stmt->execute([$name, $isVisible ? 1 : 0, $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete') {
        $id = $data['id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM homepage_sections WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>
