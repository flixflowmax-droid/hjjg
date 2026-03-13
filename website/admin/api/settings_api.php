<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');

$user_dir = '../../user/';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'get_admin_user') {
        $creds = get_creds();
        echo json_encode(['success' => true, 'username' => $creds['username']]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';

    if ($action === 'update_admin') {
        $new_user = $data['username'] ?? '';
        $new_pass = $data['password'] ?? '';
        
        if (empty($new_user)) {
             echo json_encode(['success' => false, 'message' => 'Username cannot be empty']);
             exit;
        }

        $creds = get_creds();
        $creds['username'] = $new_user;
        if (!empty($new_pass)) {
            $creds['password_plain'] = $new_pass;
        }
        
        file_put_contents($creds_file, json_encode($creds, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'message' => 'Admin credentials updated']);
        exit;
    }
    
    // We update index.html specifically, could do all html files
    $htmlFiles = glob($user_dir . '*.html');
    
    foreach ($htmlFiles as $file) {
        $content = file_get_contents($file);
        
        // 1. Footer description text
        if (isset($data['footer_text']) && !empty(trim($data['footer_text']))) {
            $content = preg_replace('/(<p class="text-gray-400 text-sm mb-6 leading-relaxed">)(.*?)(<\/p>)/s', "৳1" . trim($data['footer_text']) . "৳3", $content);
        }
        
        // 2. Support Email (placeholder in newsletter form)
        if (isset($data['support_email']) && !empty(trim($data['support_email']))) {
            $content = preg_replace('/placeholder="(.*?@.*?|Email Address)"/i', 'placeholder="' . trim($data['support_email']) . '"', $content);
        }
        
        file_put_contents($file, $content);
    }
    
    echo json_encode(['success' => true]);
    exit;
}
?>
