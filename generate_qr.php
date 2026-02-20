<?php
session_start();
require 'db.php';
require 'phpqrcode/qrlib.php';

$email_status = null; // message to show after sending email

// Load optional SMTP config
require_once 'config.php';

// Use reusable email helper
require_once __DIR__ . '/qr_email.php';

// Handle email send POST (when user clicks Send to Email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $to = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $file = $_POST['file'] ?? '';
    $owner = $_POST['owner'] ?? 'Owner';

    $email_status = sendQrEmail($to, $owner, $file);
}

// Support both vehicles and guests (GET flow)
if (isset($_GET['id'])) {
    $vehicle_id = intval($_GET['id']);

    // Check if 'owner_email' exists in vehicles
    $colCheck = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'owner_email'");
    $hasEmail = ($colCheck && $colCheck->num_rows > 0);

    if ($hasEmail) {
        $stmt = $conn->prepare("SELECT owner_name, qr_token, owner_email, qr_image FROM vehicles WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT owner_name, qr_token, qr_image FROM vehicles WHERE id = ?");
    }
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("Vehicle not found.");
    }
    $vehicle = $result->fetch_assoc();

    $owner_email = $hasEmail ? ($vehicle['owner_email'] ?? '') : '';


    // Build base URL for link display (use config or auto-detect)
    if (!empty($app_url)) {
        $baseUrl = rtrim($app_url, '/');
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    }

    if (!empty($vehicle['qr_token'])) {
        // already generated - use existing image
        $file = $vehicle['qr_image'];
        $already = true;
    } else {
        $ownerName = strtolower($vehicle['owner_name']);
        $ownerName = preg_replace('/[^a-z0-9]+/', '_', $ownerName);
        $ownerName = trim($ownerName, '_');
        // signature payload: prefix with V for vehicle
        $sig = hash_hmac('sha256', (string)$vehicle_id, $qr_secret);
        $token = $sig; // store signature as qr_token for record
        $qrData = 'V' . $vehicle_id . ':' . $sig;
        $folder = "qrcodes/";
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
        $file = $folder . $ownerName . "_" . $vehicle_id . ".png";
        QRcode::png($qrData, $file, QR_ECLEVEL_H, 6);
        $update = $conn->prepare("UPDATE vehicles SET qr_token = ?, qr_image = ? WHERE id = ?");
        $update->bind_param("ssi", $token, $file, $vehicle_id);
        $update->execute();
        $already = false;

        // Auto-send is disabled. If ?send=1 is present, show a message instructing manual send via the form below
        if (isset($_GET['send']) && $_GET['send'] == '1') {
            $email_status = ['ok' => false, 'msg' => 'Auto-send is disabled. Please use the "Send QR" button on this page to send the QR manually.'];
        }
    }

    // If QR already existed but send was requested (Resend)
    if ($already && isset($_GET['send']) && $_GET['send'] == '1') {
        $email_status = ['ok' => false, 'msg' => 'Auto-send is disabled. Please use the "Send QR" button on this page to resend the QR manually.'];
    }
} elseif (isset($_GET['guest_id'])) {
    $guest_id = intval($_GET['guest_id']);

    // Check if 'owner_email' exists in guests
    $colCheckG = $conn->query("SHOW COLUMNS FROM guests LIKE 'owner_email'");
    $hasEmailG = ($colCheckG && $colCheckG->num_rows > 0);

    if ($hasEmailG) {
        $stmt = $conn->prepare("SELECT owner_name, qr_token, owner_email, qr_image FROM guests WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT owner_name, qr_token, qr_image FROM guests WHERE id = ?");
    }
    $stmt->bind_param("i", $guest_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("Guest not found.");
    }
    $guest = $result->fetch_assoc();

    $owner_email = $hasEmailG ? ($guest['owner_email'] ?? '') : '';

    if (!empty($guest['qr_token'])) {
        $file = $guest['qr_image'];
        $already = true;
    } else {
        $ownerName = strtolower($guest['owner_name']);
        $ownerName = preg_replace('/[^a-z0-9]+/', '_', $ownerName);
        $ownerName = trim($ownerName, '_');
        // signature payload: prefix with G for guest
        $sig = hash_hmac('sha256', 'G' . (string)$guest_id, $qr_secret);
        $token = $sig; // store signature
        $qrData = 'G' . $guest_id . ':' . $sig;

        $folder = "qrcodes/";
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
        $file = $folder . $ownerName . "_guest_" . $guest_id . ".png";
        QRcode::png($qrData, $file, QR_ECLEVEL_H, 6);
        $update = $conn->prepare("UPDATE guests SET qr_token = ?, qr_image = ? WHERE id = ?");
        $update->bind_param("ssi", $token, $file, $guest_id);
        $update->execute();
        $already = false;

        // Auto-send is disabled. If ?send=1 is present, show a message instructing manual send via the form below
        if (isset($_GET['send']) && $_GET['send'] == '1') {
            $email_status = ['ok' => false, 'msg' => 'Auto-send is disabled. Please use the "Send QR" button on this page to send the QR manually.'];
        }
    }

    // If QR already existed but send was requested (Resend)
    if ($already && isset($_GET['send']) && $_GET['send'] == '1') {
        $email_status = ['ok' => false, 'msg' => 'Auto-send is disabled. Please use the "Send QR" button on this page to resend the QR manually.'];
    }
} else {
    die("Invalid request.");
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generated</title>
    <script src="assets/js/reload_on_nav.js"></script>
    <link rel="stylesheet" href="assets/css/generate_qr.css">
</head>
<body>
    <div class="qr-card">
        <h2>QR Code Generated</h2>
        <img src="<?= htmlspecialchars($file); ?>" alt="QR Code" class="qr-img" width="220" height="220">
        <div class="filename">File: <?= htmlspecialchars(basename($file)); ?></div>

        <a href="<?= htmlspecialchars($file); ?>" download class="btn">Download QR Code</a>
        <a href="view_vehicles.php" class="btn small secondary">Back to Vehicles</a>

        <?php
            // owner name for emails
            $ownerName = $vehicle['owner_name'] ?? $guest['owner_name'] ?? 'Owner';
        ?>
        <!-- Regenerate removed: QR is managed from Registered view -->

        <?php if (!empty($owner_email)): ?>
            <form method="post" class="send-form">
                <input type="hidden" name="file" value="<?= htmlspecialchars($file); ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($owner_email); ?>">
                <input type="hidden" name="owner" value="<?= htmlspecialchars($ownerName); ?>">
                <button type="submit" name="send_email" class="btn success">Send QR to <?= htmlspecialchars($owner_email); ?></button>
            </form>
            <div class="note">Note: Emails use PHP mail(); ensure your server is configured for sending mail.</div>
        <?php else: ?>
            <div class="note">No owner email available. Add an email to the vehicle record to enable sending.</div>
        <?php endif; ?>

        <?php if ($email_status !== null): ?>
            <div class="status <?= $email_status['ok'] ? 'success' : 'error' ?>"><?= htmlspecialchars($email_status['msg']); ?></div>
        <?php endif; ?>

    </div>
</body>
</html>
