<?php
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once '../../vendor/mailer_core.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$email = $_POST['email'] ?? '';
$message_body = $_POST['message'] ?? '';

if (empty($first_name) || empty($last_name) || empty($email) || empty($message_body)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

try {
    // Fetch SMTP credentials
    $stmt = $pdo->prepare("SELECT sender_email, smtp_password, receiver_email FROM smtp_settings LIMIT 1");
    $stmt->execute();
    $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smtpSettings || empty($smtpSettings['sender_email']) || empty($smtpSettings['smtp_password']) || empty($smtpSettings['receiver_email'])) {
        echo json_encode(['success' => false, 'message' => 'SMTP settings are not configured. Please contact the administrator.']);
        exit;
    }

    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpSettings['sender_email'];
    $mail->Password   = $smtpSettings['smtp_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    // Recipients
    $mail->setFrom($smtpSettings['sender_email'], 'Luxe Fashion Contact');
    $mail->addAddress($smtpSettings['receiver_email']);
    $mail->addReplyTo($email, $first_name . ' ' . $last_name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'New Contact Us Message - ' . $first_name . ' ' . $last_name;
    
    // Beautiful HTML formatting
    $htmlContent = "
        <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
            <div style='background-color: #0f172a; padding: 20px; text-align: center;'>
                <h2 style='color: #fff; margin: 0; font-weight: bold;'>LUXE FASHION</h2>
                <p style='color: #a1a1aa; margin: 5px 0 0 0; font-size: 14px;'>New Customer Inquiry</p>
            </div>
            <div style='padding: 30px;'>
                <p style='margin: 0 0 10px 0;'><strong>From:</strong> {$first_name} {$last_name}</p>
                <p style='margin: 0 0 20px 0;'><strong>Email:</strong> {$email}</p>
                <div style='background-color: #f8fafc; padding: 20px; border-left: 4px solid #8b5cf6; border-radius: 4px;'>
                    <p style='margin: 0; white-space: pre-wrap; line-height: 1.6;'>{$message_body}</p>
                </div>
            </div>
            <div style='background-color: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b;'>
                This message was sent from the Luxe Fashion Contact form.
            </div>
        </div>
    ";
    
    $mail->Body    = $htmlContent;
    $mail->AltBody = "From: {$first_name} {$last_name}\nEmail: {$email}\n\nMessage:\n{$message_body}";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Thank you! Your message has been sent successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        echo json_encode(['success' => false, 'message' => 'Database table smtp_settings is not set up. Please run the SQL migration.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
