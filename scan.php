<?php
require 'db.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    die("Invalid QR code.");
}

$stmt = $conn->prepare("SELECT *, 'vehicle' AS type FROM vehicles WHERE qr_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("SELECT *, 'guest' AS type FROM guests WHERE qr_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("QR code not recognized.");
    }
}

$data = $result->fetch_assoc();

$canTimeIn  = true;
$canTimeOut = false;

if ($data['type'] === 'vehicle') {
    $logStmt = $conn->prepare("
        SELECT action 
        FROM parking_logs 
        WHERE vehicle_id = ? 
        ORDER BY scanned_at DESC 
        LIMIT 1
    ");
    $logStmt->bind_param("i", $data['id']);
} else {
    $logStmt = $conn->prepare("
        SELECT action 
        FROM parking_logs 
        WHERE owner_name = ? 
        ORDER BY scanned_at DESC 
        LIMIT 1
    ");
    $logStmt->bind_param("s", $data['owner_name']);
}

$logStmt->execute();
$logRes = $logStmt->get_result();

if ($logRes && $logRes->num_rows > 0) {
    $lastAction = $logRes->fetch_assoc()['action'];
    if ($lastAction === 'IN') {
        $canTimeIn  = false;
        $canTimeOut = true;
    }
}

$is_admin = isset($_SESSION['username']) && !empty($_SESSION['username']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Parking Access</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="assets/js/reload_on_nav.js"></script>
    <link rel="stylesheet" href="assets/css/scan.css">
</head>
<body>

<a href="dashboard.php" class="back-btn">
    <svg viewBox="0 0 24 24"><path d="M15.5 19l-7-7 7-7"/></svg>
    Back
</a>
<div class="minimal-container">
    <h2>Parking Access</h2>
    <table>
        <tbody>
            <tr>
                <th>Owner</th>
                <td><?= htmlspecialchars($data['owner_name']) ?></td>
            </tr>
            <?php if ($data['type'] === 'vehicle'): ?>
            <tr>
                <th>Vehicle</th>
                <td><?= htmlspecialchars($data['vehicle_number']) ?></td>
            </tr>
            <?php else: ?>
            <tr>
                <th>Plate Number</th>
                <td><?= htmlspecialchars($data['plate_number']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Type</th>
                <td><?= htmlspecialchars($data['vehicle_type']) ?></td>
            </tr>
        </tbody>
    </table>


    <form method="post" action="process_parking.php">
        <?php if ($data['type'] === 'vehicle'): ?>
            <input type="hidden" name="vehicle_id" value="<?= $data['id'] ?>">
            <input type="hidden" name="entity_type" value="vehicle">
        <?php else: ?>
            <input type="hidden" name="guest_id" value="<?= $data['id'] ?>">
            <input type="hidden" name="entity_type" value="guest">
        <?php endif; ?>

        <button type="submit" name="action" value="IN" class="minimal-btn in" <?= !$canTimeIn ? 'disabled' : '' ?>>Time In</button>
        <button type="submit" name="action" value="OUT" class="minimal-btn out" <?= !$canTimeOut ? 'disabled' : '' ?>>Time Out</button>
    </form>

    <?php if (!$canTimeIn): ?>
        <div class="minimal-msg">
            Already timed in. Please time out before timing in again.
        </div>
    <?php endif; ?>
</div>

</body>
</html>
