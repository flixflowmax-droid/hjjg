<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

// Load products from JSON (always available)
$json_file = '../products.json';
$all_products = [];
if (file_exists($json_file)) {
    $all_products = json_decode(file_get_contents($json_file), true) ?: [];
}

// Only visible products
$all_products = array_filter($all_products, function($p) {
    return isset($p['isVisible']) ? $p['isVisible'] !== false : true;
});
$all_products = array_values($all_products);

// Convert product array to associative by id
$productDict = [];
foreach ($all_products as $p) {
    $productDict[$p['id']] = $p;
}

// If no DB connection, build sections from products.json sections array
if (!$pdo) {
    // Collect all unique section IDs from products
    $sectionMap = [];
    foreach ($all_products as $p) {
        if (!empty($p['sections']) && is_array($p['sections'])) {
            foreach ($p['sections'] as $secId) {
                if (!isset($sectionMap[$secId])) {
                    $sectionMap[$secId] = [];
                }
                $sectionMap[$secId][] = $p;
            }
        }
    }

    // Default section names
    $defaultNames = [
        '1' => 'Curated Shop',
        '2' => 'New Arrivals',
        '3' => 'Trending',
        '4' => 'Recommended',
    ];

    $result = [];
    foreach ($sectionMap as $secId => $prods) {
        if (count($prods) > 0) {
            $result[] = [
                'id' => $secId,
                'name' => $defaultNames[$secId] ?? 'Section ' . $secId,
                'is_visible' => 1,
                'products' => array_slice($prods, 0, 7)
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

// Fetch all visible sections from DB
$sections = [];
try {
    $stmt = $pdo->query("SELECT id, section_name, is_visible FROM homepage_sections WHERE is_visible = 1 ORDER BY id ASC");
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->query("SELECT id, section_name FROM homepage_sections ORDER BY id ASC");
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

// Fetch section product mappings
$mappings = [];
try {
    $stmt = $pdo->query("SELECT product_id, section_id FROM product_section_mapping");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mappings[$row['section_id']][] = $row['product_id'];
    }
} catch (PDOException $e) {
    // Also check JSON sections as fallback per product
    foreach ($all_products as $p) {
        if (!empty($p['sections']) && is_array($p['sections'])) {
            foreach ($p['sections'] as $secId) {
                $mappings[$secId][] = $p['id'];
            }
        }
    }
}

// If no DB sections exist but products have section assignments, use JSON fallback
if (empty($sections)) {
    $sectionMap = [];
    foreach ($all_products as $p) {
        if (!empty($p['sections']) && is_array($p['sections'])) {
            foreach ($p['sections'] as $secId) {
                $sectionMap[$secId][] = $p;
            }
        }
    }
    $defaultNames = ['1' => 'Curated Shop', '2' => 'New Arrivals', '3' => 'Trending', '4' => 'Recommended'];
    $result = [];
    foreach ($sectionMap as $secId => $prods) {
        if (count($prods) > 0) {
            $result[] = ['id' => $secId, 'name' => $defaultNames[$secId] ?? 'Section '.$secId, 'is_visible' => 1, 'products' => array_slice($prods, 0, 7)];
        }
    }
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

// Assemble data per DB section
$result = [];
foreach ($sections as $sec) {
    $secId = $sec['id'];
    $mappedIds = $mappings[$secId] ?? [];
    
    // Also include products that have this section in their JSON 'sections' array
    foreach ($all_products as $p) {
        if (!empty($p['sections']) && in_array((string)$secId, array_map('strval', $p['sections']))) {
            if (!in_array($p['id'], $mappedIds)) {
                $mappedIds[] = $p['id'];
            }
        }
    }

    $sectionProducts = [];
    foreach ($mappedIds as $pid) {
        if (isset($productDict[$pid])) {
            $sectionProducts[] = $productDict[$pid];
        }
    }

    $result[] = [
        'id' => $secId,
        'name' => $sec['section_name'],
        'is_visible' => $sec['is_visible'] ?? 1,
        'products' => array_slice($sectionProducts, 0, 7)
    ];
}

echo json_encode(['success' => true, 'data' => $result]);
?>
