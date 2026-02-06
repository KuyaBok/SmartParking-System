<?php
// send_qr.php - send QR image to vehicle owner using bundled PHPMailer
require 'config.php'; // provides $conn and SMTP config

// Compatibility shim for legacy PHPMailer: some old PHPMailer versions call
// get_magic_quotes_runtime(), which was removed in newer PHP versions.
if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() {
        return false;
    }
}

require 'PHPMailer/_lib/class.phpmailer.php';

// Some legacy code may call set_magic_quotes_runtime(); provide a noop shim.
if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($newsetting) {
        return false;
    }
}

// Compatibility shim for split() removed in PHP 7.x — map to preg_split
if (!function_exists('split')) {
    function split($pattern, $string) {
        if ($pattern === '') return array($string);
        $p = $pattern;
        if ($p[0] !== '/') {
            $p = '/' . str_replace('/', '\/', $p) . '/';
        }
        return preg_split($p, $string);
    }
}

// Expect POST vehicle_id (students only). Guests are not required to have email.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['vehicle_id'])) {
    die('Invalid request.');
}

$vehicle_id = intval($_POST['vehicle_id']);
if ($vehicle_id <= 0) die('Invalid vehicle id.');

$stmt = $conn->prepare("SELECT owner_name, owner_email, qr_image FROM vehicles WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    die('Vehicle not found.');
}

$row = $result->fetch_assoc();
$owner = $row['owner_name'] ?? '';
$email = $row['owner_email'] ?? '';
$qrpath = $row['qr_image'] ?? '';

if (empty($email)) {
    die('Owner has no email address on record.');
}

$mail = new PHPMailer();
// Use SMTP if configured
if (!empty($smtp_host)) {
    $mail->IsSMTP();
    $mail->Host = $smtp_host;
    $mail->Port = !empty($smtp_port) ? $smtp_port : 587;
    if (!empty($smtp_user)) {
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
    }
    if (!empty($smtp_secure)) {
        $mail->SMTPSecure = $smtp_secure;
    }
}

$fromEmail = !empty($smtp_from_email) ? $smtp_from_email : 'no-reply@localhost';
$fromName = !empty($smtp_from_name) ? $smtp_from_name : 'Parking Admin';

$mail->From = $fromEmail;
$mail->FromName = $fromName;
$mail->AddAddress($email, $owner);
$mail->Subject = 'Your SmartPark QR Code';
$body = "Hello " . htmlspecialchars($owner) . ",<br><br>Please find your SmartPark QR code attached. Present this at the entrance when arriving.<br><br>Regards,<br>" . htmlspecialchars($fromName);
$mail->MsgHTML($body);
$mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));

// Attach QR if exists
if (!empty($qrpath) && file_exists($qrpath)) {
    $mail->AddAttachment($qrpath);
}

$sent = false;
if ($mail->Send()) {
    $sent = true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Send QR</title>
    <link rel="stylesheet" href="assets/css/view_vehicles.css">
</head>
<body>
    <div class="main-container">
        <h2><?= $sent ? 'Email Sent' : 'Failed to Send Email' ?></h2>
        <p>
            <?php if ($sent): ?>
                The QR code was sent to <?= htmlspecialchars($email) ?>.
            <?php else: ?>
                There was an error sending the email. Error: <?= htmlspecialchars($mail->ErrorInfo) ?>
            <?php endif; ?>
        </p>
        <p><a href="view_vehicles.php">Back to Vehicles</a></p>
    </div>
</body>
</html>
