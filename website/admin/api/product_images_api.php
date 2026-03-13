<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');

require_once '../../user/api/db_connect.php';

$upload_dir = '../../user/assets/';
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$max_images = 10;

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$action = $_GET['action'] ?? '';

// ── CREATE TABLE IF NOT EXISTS ──────────────────────────────────────────────
function ensureTable($pdo) {
    if (!$pdo) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id VARCHAR(64) NOT NULL,
            image_path VARCHAR(512) NOT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_product_id (product_id)
        )");
    } catch(PDOException $e) {}
}

ensureTable($pdo);

// ── GET images for a product ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $productId = $_GET['product_id'] ?? '';
    if (!$productId) {
        echo json_encode(['success' => false, 'message' => 'No product_id']);
        exit;
    }

    $images = [];
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$productId]);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {}
    }

    // Fallback: also check products.json for legacy image1/image2
    if (empty($images)) {
        $json_file = '../../user/products.json';
        if (file_exists($json_file)) {
            $products = json_decode(file_get_contents($json_file), true) ?: [];
            foreach ($products as $p) {
                if ($p['id'] === $productId) {
                    $order = 0;
                    if (!empty($p['image1'])) {
                        $images[] = ['id' => 'json_1', 'product_id' => $productId, 'image_path' => $p['image1'], 'sort_order' => $order++];
                    }
                    if (!empty($p['image2']) && $p['image2'] !== $p['image1']) {
                        $images[] = ['id' => 'json_2', 'product_id' => $productId, 'image_path' => $p['image2'], 'sort_order' => $order++];
                    }
                    break;
                }
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $images]);
    exit;
}

// ── UPLOAD new images for a product ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    $productId = $_POST['product_id'] ?? '';
    if (!$productId) {
        echo json_encode(['success' => false, 'message' => 'No product_id']);
        exit;
    }

    // Count existing images
    $existingCount = 0;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
            $stmt->execute([$productId]);
            $existingCount = (int)$stmt->fetchColumn();
        } catch(PDOException $e) {}
    }

    $uploaded = [];
    $errors   = [];

    // Handle multiple file uploads: images[]
    $files = $_FILES['images'] ?? [];
    if (!empty($files['name']) && is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($existingCount + count($uploaded) >= $max_images) {
                $errors[] = "Max {$max_images} images allowed.";
                break;
            }

            $tmp  = $files['tmp_name'][$i];
            $type = mime_content_type($tmp);
            $size = $files['size'][$i];

            if (!in_array($type, $allowed_types)) { $errors[] = "Invalid type: $type"; continue; }
            if ($size > 10 * 1024 * 1024)          { $errors[] = "File too large ({$files['name'][$i]})"; continue; }

            $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = 'prod_' . uniqid() . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (move_uploaded_file($tmp, $dest)) {
                $imgPath = 'assets/' . $filename;

                // Save to DB
                if ($pdo) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
                        $stmt->execute([$productId, $imgPath, $existingCount + count($uploaded)]);
                        $newId = $pdo->lastInsertId();
                        $uploaded[] = ['id' => $newId, 'image_path' => $imgPath];
                    } catch(PDOException $e) {
                        $uploaded[] = ['id' => 'local_' . uniqid(), 'image_path' => $imgPath];
                    }
                } else {
                    $uploaded[] = ['id' => 'local_' . uniqid(), 'image_path' => $imgPath];
                }

                // Also update products.json image1 if this is the first image
                if ($existingCount === 0 && count($uploaded) === 1) {
                    updateProductJson($productId, $imgPath);
                }
            }
        }
    }

    echo json_encode(['success' => true, 'uploaded' => $uploaded, 'errors' => $errors]);
    exit;
}

// ── DELETE a specific image ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $input   = json_decode(file_get_contents('php://input'), true) ?? [];
    $imageId = $input['id'] ?? '';
    $imgPath = $input['image_path'] ?? '';

    if (!$imageId) {
        echo json_encode(['success' => false, 'message' => 'No image id']);
        exit;
    }

    // Remove from disk
    if ($imgPath && strpos($imgPath, 'assets/') === 0) {
        $fullPath = '../../user/' . $imgPath;
        if (file_exists($fullPath) && $imgPath !== 'assets/placeholder.jpg') {
            unlink($fullPath);
        }
    }

    // Remove from DB
    if ($pdo && is_numeric($imageId)) {
        try {
            $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imageId]);
        } catch(PDOException $e) {}
    }

    echo json_encode(['success' => true]);
    exit;
}

// ── Helper: update products.json primary image ────────────────────────────────
function updateProductJson($productId, $imagePath) {
    $json_file = '../../user/products.json';
    if (!file_exists($json_file)) return;
    $products = json_decode(file_get_contents($json_file), true) ?: [];
    foreach ($products as &$p) {
        if ($p['id'] === $productId) {
            $p['image1'] = $imagePath;
            $p['image2'] = $imagePath;
            break;
        }
    }
    file_put_contents($json_file, json_encode(array_values($products), JSON_PRETTY_PRINT));
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>
