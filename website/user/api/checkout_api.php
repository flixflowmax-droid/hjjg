<?php
// user/api/checkout_api.php
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once '../../vendor/mailer_core.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$orderId = $input['id'] ?? uniqid('LX-');
$customer = $input['customer'] ?? [];
$items = $input['items'] ?? [];
$subtotal = $input['subtotal'] ?? 0;
$deliveryCharge = $input['deliveryCharge'] ?? 0;
$total = $input['total'] ?? 0;

// Save to DB
if ($pdo) {
    try {
        $firstItemSize = count($items) > 0 ? ($items[0]['size'] ?? '') : '';
        $stmt = $pdo->prepare("
            INSERT INTO orders (id, customer_name, customer_phone, division, district, city, zip, subtotal, delivery_charge, total, items_json, size)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderId,
            $customer['name'] ?? '',
            $customer['phone'] ?? '',
            $customer['division'] ?? '',
            $customer['district'] ?? '',
            $customer['city'] ?? '',
            $customer['zip'] ?? '',
            $subtotal,
            $deliveryCharge,
            $total,
            json_encode($items),
            $firstItemSize
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No database connection']);
    exit;
}

// Prepare items HTML for email
$itemsHtml = '';
foreach ($items as $item) {
    // If image is relative, try to get absolute URL
    $imgSrc = $item['image1'];
    if (strpos($imgSrc, 'http') === false) {
        // Fallback to absolute URL logic based on current server if necessary
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $base = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/');
        $imgSrc = $scheme . "://" . $host . $base . "/" . ltrim($imgSrc, '/');
    }

    $priceFormatted = number_format($item['price'], 2);
    $itemTotal = number_format($item['price'] * $item['quantity'], 2);
    
    $itemsHtml .= "
        <tr>
            <td style='padding: 10px; border-bottom: 1px solid #eee;'>
                <img src='{$imgSrc}' alt='{$item['name']}' style='width: 60px; height: 80px; object-fit: cover; border-radius: 4px;'>
            </td>
            <td style='padding: 10px; border-bottom: 1px solid #eee;'>
                <p style='margin: 0; font-weight: bold;'>{$item['name']}</p>
                <p style='margin: 0; font-size: 12px; color: #666;'>Size: {$item['size']}</p>
            </td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$item['quantity']}</td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>৳{$priceFormatted}</td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;'>৳{$itemTotal}</td>
        </tr>
    ";
}

$subtotalF = number_format($subtotal, 2);
$deliveryF = number_format($deliveryCharge, 2);
$totalF = number_format($total, 2);

$cName = $customer['name'] ?? '';
$cPhone = $customer['phone'] ?? '';
$cCity = $customer['city'] ?? '';
$cZip = $customer['zip'] ?? '';
$cDist = $customer['district'] ?? '';
$cDiv = $customer['division'] ?? '';

// Try sending email
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT sender_email, smtp_password, receiver_email FROM smtp_settings LIMIT 1");
        $stmt->execute();
        $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($smtpSettings && !empty($smtpSettings['sender_email']) && !empty($smtpSettings['receiver_email'])) {
            $mail = new PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpSettings['sender_email'];
            $mail->Password   = $smtpSettings['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom($smtpSettings['sender_email'], 'Luxe Fashion Orders');
            $mail->addAddress($smtpSettings['receiver_email']);

            $mail->isHTML(true);
            $mail->Subject = "New Order Received - {$orderId}";

            $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333; max-width: 700px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px; overflow: hidden;'>
                <div style='background-color: #0f172a; padding: 20px; text-align: center;'>
                    <h2 style='color: #fff; margin: 0;'>New Order Received</h2>
                    <p style='color: #a1a1aa; margin: 5px 0 0 0;'>Order ID: {$orderId}</p>
                </div>
                
                <div style='padding: 20px;'>
                    <h3 style='border-bottom: 2px solid #eee; padding-bottom: 10px;'>Customer Details</h3>
                    <p><strong>Name:</strong> {$cName}</p>
                    <p><strong>Phone:</strong> {$cPhone}</p>
                    <p><strong>Address:</strong> {$cCity}</p>
                    <p><strong>District & Division:</strong> {$cDist}, {$cDiv} - {$cZip}</p>
                </div>

                <div style='padding: 20px;'>
                    <h3 style='border-bottom: 2px solid #eee; padding-bottom: 10px;'>Order Items</h3>
                    <table style='width: 100%; border-collapse: collapse; text-align: left;'>
                        <thead>
                            <tr style='background-color: #f8fafc;'>
                                <th style='padding: 10px;'>Image</th>
                                <th style='padding: 10px;'>Product</th>
                                <th style='padding: 10px; text-align: center;'>Qty</th>
                                <th style='padding: 10px; text-align: right;'>Price</th>
                                <th style='padding: 10px; text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan='4' style='padding: 10px; text-align: right;'>Subtotal:</td>
                                <td style='padding: 10px; text-align: right;'>৳{$subtotalF}</td>
                            </tr>
                            <tr>
                                <td colspan='4' style='padding: 10px; text-align: right;'>Delivery:</td>
                                <td style='padding: 10px; text-align: right;'>৳{$deliveryF}</td>
                            </tr>
                            <tr style='font-size: 18px; font-weight: bold;'>
                                <td colspan='4' style='padding: 10px; text-align: right;'>Net Total:</td>
                                <td style='padding: 10px; text-align: right; color: var(--primary-color);'>৳{$totalF}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>";

            $mail->send();
        }
    } catch (Exception $e) {
        // Ignore mail errors to complete checkout
    }
}

echo json_encode(['success' => true, 'message' => 'Order processed', 'data' => ['order_id' => $orderId]]);
?>
