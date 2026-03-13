<?php
require 'auth.php';
checkAdminSession();
header('Content-Type: application/json');

// Use a local JSON file as fallback (always works, no DB needed)
$smtp_file = '../../user/smtp_settings.json';

function getSmtpSettings($file) {
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) return $data;
    }
    return ['sender_email' => '', 'smtp_password' => '', 'receiver_email' => ''];
}

function saveSmtpSettings($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Try DB first, then JSON fallback
    $settings = null;
    try {
        require_once '../../user/api/db_connect.php';
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT sender_email, smtp_password, receiver_email FROM smtp_settings LIMIT 1");
            $stmt->execute();
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $settings = null;
    }

    if (!$settings) {
        $settings = getSmtpSettings($smtp_file);
    }

    echo json_encode(['success' => true, 'data' => $settings]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $sender_email   = $data['sender_email']   ?? '';
    $smtp_password  = $data['smtp_password']  ?? '';
    $receiver_email = $data['receiver_email'] ?? '';

    $payload = [
        'sender_email'   => $sender_email,
        'smtp_password'  => $smtp_password,
        'receiver_email' => $receiver_email
    ];

    // Save to JSON file (always reliable)
    $saved = saveSmtpSettings($smtp_file, $payload);

    // Also try DB if available (non-critical)
    try {
        require_once '../../user/api/db_connect.php';
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM smtp_settings");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $pdo->prepare("UPDATE smtp_settings SET sender_email=?, smtp_password=?, receiver_email=? LIMIT 1")
                    ->execute([$sender_email, $smtp_password, $receiver_email]);
            } else {
                $pdo->prepare("INSERT INTO smtp_settings (sender_email, smtp_password, receiver_email) VALUES (?, ?, ?)")
                    ->execute([$sender_email, $smtp_password, $receiver_email]);
            }
        }
    } catch (Exception $e) {
        // DB not available, JSON fallback was already saved above — that's fine
    }

    if ($saved) {
        echo json_encode(['success' => true, 'message' => 'SMTP Settings saved successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not write SMTP settings file. Check permissions.']);
    }
    exit;
}
?>
