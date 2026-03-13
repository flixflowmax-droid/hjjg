<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');

$user_dir = '../../user/';
$assets_dir = '../../user/assets/';
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    require_once '../../user/api/db_connect.php';

    // 1. Ensure Table
    if ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT NOT NULL
        )");
    }
    
    // helper fn 
    function saveSetting($pdo, $key, $val) {
        if (!$pdo) return;
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $val, $val]);
    }

    // 2. Update Site Name in DB
    if (isset($_POST['site_name']) && !empty(trim($_POST['site_name']))) {
        saveSetting($pdo, 'site_name', trim($_POST['site_name']));
    }
    
    // 3. Update Site Logo in DB
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['site_logo']['tmp_name'];
        $type = mime_content_type($tmp);
        if (in_array($type, $allowed_types)) {
            $ext = pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $ext;
            
            if (move_uploaded_file($tmp, $assets_dir . $filename)) {
                saveSetting($pdo, 'site_logo', 'assets/' . $filename);
            }
        }
    }
    
    // 4. Upload and replace Hero Image in DB
    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['hero_image']['tmp_name'];
        $type = mime_content_type($tmp);
        if (in_array($type, $allowed_types)) {
            $ext = pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION);
            $filename = 'hero_' . time() . '.' . $ext;
            
            if (move_uploaded_file($tmp, $assets_dir . $filename)) {
                saveSetting($pdo, 'hero_image', 'assets/' . $filename);
            }
        }
    }

    // 5. Update Theme Color in style.css
    if (isset($_POST['theme_color']) && !empty(trim($_POST['theme_color']))) {
        $newColor = trim($_POST['theme_color']);
        saveSetting($pdo, 'primary_theme_color', $newColor);
        $style_file = $user_dir . 'style.css';
        
        if (file_exists($style_file)) {
            $css = file_get_contents($style_file);
            
            // Helper to adjust color brightness
            function adjustBrightness($hex, $steps) {
                $steps = max(-255, min(255, $steps));
                $hex = ltrim($hex, '#');
                if (strlen($hex) == 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                
                $r = max(0, min(255, $r + $steps));
                $g = max(0, min(255, $g + $steps));
                $b = max(0, min(255, $b + $steps));
                
                return "#" . str_pad(dechex($r), 2, "0", STR_PAD_LEFT) . str_pad(dechex($g), 2, "0", STR_PAD_LEFT) . str_pad(dechex($b), 2, "0", STR_PAD_LEFT);
            }

            // Helper for Smart Contrast
            function getContrast($hex) {
                $hex = ltrim($hex, '#');
                if (strlen($hex) == 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
                return ($yiq >= 128) ? '#111111' : '#ffffff';
            }
            
            // Calculate additional colors
            $hoverColor = adjustBrightness($newColor, -20); // 20% darker for hover
            $softColor = adjustBrightness($newColor, 40); // 40% lighter for soft backgrounds
            $glowColor = $newColor . '44'; // 25% opacity for glow effects
            $contrastColor = getContrast($newColor);
            
            // Replace CSS variables with robust regex
            $css = preg_replace('/--primary-color:\s*[^;]+;/', "--primary-color: " . $newColor . ";", $css);
            $css = preg_replace('/--primary-hover:\s*[^;]+;/', "--primary-hover: " . $hoverColor . ";", $css);
            $css = preg_replace('/--primary-contrast:\s*[^;]+;/', "--primary-contrast: " . $contrastColor . ";", $css);
            
            // Add new calculated variables if they don't exist, or replace if they do
            if (strpos($css, '--primary-soft:') !== false) {
                $css = preg_replace('/--primary-soft:\s*[^;]+;/', "--primary-soft: " . $softColor . ";", $css);
            } else {
                // Insert before --primary-contrast if it exists, otherwise at the end of :root
                $css = preg_replace('/(--primary-contrast:\s*[^;]+;)/', "--primary-soft: " . $softColor . ";\n    $1", $css, 1, $count);
                if ($count === 0) { // If --primary-contrast not found, append to :root
                    $css = preg_replace('/(:root\s*{[^}]*)/', "$1\n    --primary-soft: " . $softColor . ";", $css);
                }
            }

            if (strpos($css, '--primary-glow:') !== false) {
                $css = preg_replace('/--primary-glow:\s*[^;]+;/', "--primary-glow: " . $glowColor . ";", $css);
            } else {
                // Insert before --primary-contrast if it exists, otherwise at the end of :root
                $css = preg_replace('/(--primary-contrast:\s*[^;]+;)/', "--primary-glow: " . $glowColor . ";\n    $1", $css, 1, $count);
                if ($count === 0) { // If --primary-contrast not found, append to :root
                    $css = preg_replace('/(:root\s*{[^}]*)/', "$1\n    --primary-glow: " . $glowColor . ";", $css);
                }
            }
            
            file_put_contents($style_file, $css);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Branding updated successfully!']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request']);
