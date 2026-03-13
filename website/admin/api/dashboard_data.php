<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');

require_once '../../user/api/db_connect.php';

$response = [
    'success' => true,
    'counts' => [
        'products' => 0,
        'orders' => 0,
        'users' => 0
    ],
    'latest_orders' => [],
    'latest_users' => [],
    'site_name' => 'LUXE FASHION', 
    'hero_image' => 'assets/hero-1.jpg',
    'support_email' => 'Email Address',
    'footer_text' => '',
    'theme_color' => 'var(--primary-color)'
];

// 1. Fetch counts from DB
if ($pdo) {
    try {
        $response['counts']['orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $response['counts']['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        
        // Latest 10 orders
        $stmt = $pdo->prepare("SELECT id, customer_name, total, status, created_at FROM orders ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
        $response['latest_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Latest 10 users
        $stmt = $pdo->prepare("SELECT id, name, email, phone, created_at FROM users ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
        $response['latest_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback for missing tables
    }
}

// 2. Read Products from JSON for count
$products_file = '../../user/products.json';
if (file_exists($products_file)) {
    $products_data = json_decode(file_get_contents($products_file), true);
    if (is_array($products_data)) {
        $response['counts']['products'] = count($products_data);
    }
}

// 3. Read static info from home.html
$index_file = '../../user/home.html';
if (file_exists($index_file)) {
    $html = file_get_contents($index_file);
    if (preg_match('/<span class="group-hover:text-black transition-colors">(.*?)<\/span>/', $html, $matches)) {
        $response['site_name'] = trim($matches[1]);
    }
    if (preg_match('/<img src="(.*?)" alt="Fashion Hero"/', $html, $matches)) {
        $response['hero_image'] = trim($matches[1]);
    }
    if (preg_match('/<img src="(.*?)" alt="Logo" class="site-logo.*?">/', $html, $matches)) {
        $response['site_logo'] = trim($matches[1]);
    }
    if (preg_match('/<p class="text-gray-400 text-sm mb-6 leading-relaxed">(.*?)<\/p>/', $html, $matches)) {
        $response['footer_text'] = trim($matches[1]);
    }
}

// 4. Read Theme Color
$style_file = '../../user/style.css';
if (file_exists($style_file)) {
    $css = file_get_contents($style_file);
    if (preg_match('/--primary-color:\s*(#?[a-zA-Z0-9]+);/', $css, $matches)) {
        $response['theme_color'] = trim($matches[1]);
    }
}

echo json_encode($response);
?>
