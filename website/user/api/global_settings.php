<?php
// user/api/global_settings.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = [
    'success' => true,
    'data' => [
        'site_name' => 'LUXE FASHION',
        'site_logo' => 'assets/logo.png',
        'hero_image' => 'assets/hero-1.jpg'
    ]
];

if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT NOT NULL
        )");

        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!empty($settings)) {
            if (isset($settings['site_name'])) $response['data']['site_name'] = $settings['site_name'];
            if (isset($settings['site_logo'])) $response['data']['site_logo'] = $settings['site_logo'];
            if (isset($settings['hero_image'])) $response['data']['hero_image'] = $settings['hero_image'];
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'DB Error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
