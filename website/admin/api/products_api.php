<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');

$json_file = '../../user/products.json';
$assets_dir = '../../user/'; // Base for deleting images
require_once '../../user/api/db_connect.php';
$assets_dir = '../../user/'; // Base for deleting images

// Ensure directory exists for uploads
$upload_dir = '../../user/assets/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Allowed image types
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];

// Read products
function getProducts() {
    global $json_file;
    if (file_exists($json_file)) {
        $json = file_get_contents($json_file);
        return json_decode($json, true) ?: [];
    }
    return [];
}

function saveProducts($products) {
    global $json_file;
    file_put_contents($json_file, json_encode(array_values($products), JSON_PRETTY_PRINT));
}

// Delete product image
function deleteImage($relativePath) {
    global $assets_dir;
    if ($relativePath && strpos($relativePath, 'assets/') === 0 && $relativePath !== 'assets/placeholder.jpg') {
        $fullPath = $assets_dir . $relativePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}

// Handle Image Uploads
function uploadImage($fileKey) {
    global $upload_dir, $allowed_types;
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES[$fileKey]['tmp_name'];
        $type = mime_content_type($tmp);
        $size = $_FILES[$fileKey]['size'];
        
        if (!in_array($type, $allowed_types)) {
            throw new Exception("Invalid file type: $type");
        }
        if ($size > 10 * 1024 * 1024) { // 10MB limit
            throw new Exception("File too large");
        }
        
        $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
        $filename = uniqid('prod_') . '.' . $ext;
        $dest = $upload_dir . $filename;
        
        if (move_uploaded_file($tmp, $dest)) {
            return 'assets/' . $filename; 
        }
    }
    return null;
}

$action = $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $products = getProducts();
        
        // Fetch sections for all products
        $mapped_sections = [];
        try {
            if ($pdo) {
                $stmt = $pdo->query("SELECT product_id, section_id FROM product_section_mapping");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $mapped_sections[$row['product_id']][] = $row['section_id'];
                }
            }
        } catch(Exception $e) {}
        
        foreach ($products as &$p) {
            // If local JSON already has it, merge or keep it. DB overrides if present.
            if (isset($mapped_sections[$p['id']])) {
                $p['sections'] = $mapped_sections[$p['id']];
            } else if (!isset($p['sections'])) {
                $p['sections'] = [];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $products]);
        exit;
    }
    
    // Add or Update Product
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'update')) {
        $products = getProducts();
        
        $id = $_POST['id'] ?? ('p' . time());
        $name = $_POST['name'] ?? 'New Product';
        $price = (float)($_POST['price'] ?? 0);
        $originalPrice = isset($_POST['originalPrice']) && $_POST['originalPrice'] !== '' ? (float)$_POST['originalPrice'] : null;
        $category = $_POST['category'] ?? '';
        $feature = $_POST['feature'] ?? '';
        // In FormData, checkboxes are 'on' if checked
        $isNew = isset($_POST['isNew']) && ($_POST['isNew'] === 'on' || $_POST['isNew'] === 'true');
        $isFlashSale = isset($_POST['isFlashSale']) && ($_POST['isFlashSale'] === 'on' || $_POST['isFlashSale'] === 'true');
        $isSoldOut = isset($_POST['isSoldOut']) && ($_POST['isSoldOut'] === 'on' || $_POST['isSoldOut'] === 'true');
        $isVisible = !isset($_POST['isVisible']) || ($_POST['isVisible'] === 'on' || $_POST['isVisible'] === 'true');
        
        // Parse sizes (sent as JSON string or array)
        $sizes = [];
        if (isset($_POST['sizes'])) {
            $rawSizes = $_POST['sizes'];
            if (is_string($rawSizes)) {
                $decoded = json_decode($rawSizes, true);
                $sizes = is_array($decoded) ? array_filter($decoded, fn($s) => trim($s) !== '') : array_filter(array_map('trim', explode(',', $rawSizes)), fn($s) => $s !== '');
            } elseif (is_array($rawSizes)) {
                $sizes = array_filter($rawSizes, fn($s) => trim($s) !== '');
            }
        }
        $sizes = array_values($sizes);
        
        // Handle image (it's named 'image' in the form, but let's be safe)
        $image1 = null;
        if (isset($_FILES['image'])) {
            $image1 = uploadImage('image');
        } elseif (isset($_FILES['image1'])) {
            $image1 = uploadImage('image1');
        }
        
        $existingIndex = -1;
        foreach ($products as $index => $prod) {
            if ($prod['id'] === $id) {
                $existingIndex = $index;
                break;
            }
        }
        
        // Extract section mappings from request
        $sections = isset($_POST['sections']) && is_array($_POST['sections']) ? $_POST['sections'] : [];
        if (isset($_POST['sections']) && is_string($_POST['sections'])) {
            $sections = explode(',', $_POST['sections']);
        }

        if ($action === 'update' && $existingIndex >= 0) {
            // Update Existing
            if ($image1) {
                deleteImage($products[$existingIndex]['image1']);
                $products[$existingIndex]['image1'] = $image1;
                $products[$existingIndex]['image2'] = $image1; 
            }
            
            $products[$existingIndex]['name'] = $name;
            $products[$existingIndex]['price'] = $price;
            $products[$existingIndex]['originalPrice'] = $originalPrice;
            $products[$existingIndex]['category'] = $category;
            $products[$existingIndex]['feature'] = $feature;
            $products[$existingIndex]['isNew'] = $isNew;
            $products[$existingIndex]['isFlashSale'] = $isFlashSale;
            $products[$existingIndex]['isSoldOut'] = $isSoldOut;
            $products[$existingIndex]['isVisible'] = $isVisible;
            $products[$existingIndex]['sizes'] = $sizes; // Save sizes
            $products[$existingIndex]['sections'] = $sections; // Save to JSON
            
        } else {
            // Add new
            if (!$image1) {
                $image1 = 'assets/placeholder.jpg'; 
            }
            $newProduct = [
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'originalPrice' => $originalPrice,
                'category' => $category,
                'feature' => $feature,
                'isNew' => $isNew,
                'isFlashSale' => $isFlashSale,
                'isSoldOut' => $isSoldOut,
                'isVisible' => $isVisible,
                'image1' => $image1,
                'image2' => $image1,
                'sizes' => !empty($sizes) ? $sizes : [],
                'colors' => ['#000000', '#ffffff'],
                'sections' => $sections // Save to JSON
            ];
            array_unshift($products, $newProduct); // New products first
        }
        
        saveProducts($products);
        
        try {
            if ($pdo) {
                $pdo->prepare("DELETE FROM product_section_mapping WHERE product_id = ?")->execute([$id]);
                if (!empty($sections)) {
                    $stmt = $pdo->prepare("INSERT INTO product_section_mapping (product_id, section_id) VALUES (?, ?)");
                    foreach ($sections as $secId) {
                        if(!empty($secId)) {
                            $stmt->execute([$id, $secId]);
                        }
                    }
                }
            }
        } catch(Exception $e) {}
        
        echo json_encode(['success' => true, 'message' => 'Product saved successfully!', 'id' => $id]);
        exit;
    }
    
    // Delete Product
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete')) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? ($_POST['id'] ?? '');
        
        if (!$id) {
            $id = $_GET['id'] ?? '';
        }

        $products = getProducts();
        $filteredProducts = [];
        $deleted = false;
        
        foreach ($products as $prod) {
            if ($prod['id'] === $id) {
                deleteImage($prod['image1']);
                if (isset($prod['image2']) && $prod['image2'] !== $prod['image1']) {
                    deleteImage($prod['image2']);
                }
                // Also delete all images from product_images table
                if ($pdo) {
                    try {
                        // Get all images for this product to delete files too
                        $simg = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
                        $simg->execute([$id]);
                        foreach ($simg->fetchAll(PDO::FETCH_COLUMN) as $imgP) {
                            if ($imgP && strpos($imgP, 'assets/') === 0 && $imgP !== 'assets/placeholder.jpg') {
                                $fp = '../../user/' . $imgP;
                                if (file_exists($fp)) unlink($fp);
                            }
                        }
                        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
                    } catch(PDOException $e) {}
                }
                $deleted = true;
            } else {
                $filteredProducts[] = $prod;
            }
        }
        
        if ($deleted) {
            saveProducts($filteredProducts);
            try {
                if ($pdo) {
                    $pdo->prepare("DELETE FROM product_section_mapping WHERE product_id = ?")->execute([$id]);
                }
            } catch(Exception $e) {}
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
