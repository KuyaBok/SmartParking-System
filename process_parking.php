<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$action = $_POST['action'] ?? '';
$entity_type = $_POST['entity_type'] ?? '';

if (!in_array($action, ['IN','OUT']) || !in_array($entity_type, ['vehicle','guest'])) {
    die("Invalid data.");
}

// Prepare status values for the UI
$status_title = '';
$status_message = '';
$status_kind = 'info'; // info|success|danger
$auto_redirect = true; // send back to scanner after a few seconds
$owner_name = '';
$vehicle_number = '';

if ($entity_type === 'vehicle') {
    $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
    if (!$vehicle_id) die("Invalid vehicle.");

    $getOwner = $conn->prepare("SELECT owner_name, vehicle_number, qr_image FROM vehicles WHERE id = ?");
    $getOwner->bind_param("i", $vehicle_id);
    $getOwner->execute();
    $result = $getOwner->get_result();
    if ($result->num_rows === 0) die("Vehicle not found.");
    $ownerRow = $result->fetch_assoc();
    $owner_name = $ownerRow['owner_name'];
    $vehicle_number = $ownerRow['vehicle_number'];

    $stmt = $conn->prepare("INSERT INTO parking_logs (vehicle_id, owner_name, action) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $vehicle_id, $owner_name, $action);
    $stmt->execute();

    $status_title = "Parking $action recorded";
    $status_message = "Owner: " . htmlspecialchars($owner_name) . "<br>Vehicle: " . htmlspecialchars($vehicle_number);
    $status_kind = ($action === 'IN') ? 'success' : 'danger';
} else {
    $guest_id = intval($_POST['guest_id'] ?? 0);
    if (!$guest_id) die("Invalid guest.");

    $getGuest = $conn->prepare("SELECT owner_name FROM guests WHERE id = ?");
    $getGuest->bind_param("i", $guest_id);
    $getGuest->execute();
    $result = $getGuest->get_result();
    if ($result->num_rows === 0) die("Guest not found.");
    $guest = $result->fetch_assoc();
    $owner_name = $guest['owner_name'];

    // Insert guest log. Some installations have vehicle_id as NOT NULL which causes errors when
    // trying to insert a NULL vehicle_id. Detect column nullability and adapt.
    $col = $conn->query("SHOW COLUMNS FROM parking_logs LIKE 'vehicle_id'");
    $colInfo = $col ? $col->fetch_assoc() : null;
    $vehicleCanBeNull = $colInfo && isset($colInfo['Null']) && strtoupper($colInfo['Null']) === 'YES';

    if ($vehicleCanBeNull) {
        $stmt = $conn->prepare("INSERT INTO parking_logs (vehicle_id, owner_name, action) VALUES (NULL, ?, ?)");
        $stmt->bind_param("ss", $owner_name, $action);
    } else {
        // Fallback: insert without vehicle_id if the column does not accept NULL
        $stmt = $conn->prepare("INSERT INTO parking_logs (owner_name, action) VALUES (?, ?)");
        $stmt->bind_param("ss", $owner_name, $action);
    }

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    $stmt->execute();

    // Recommended fix (run once in your DB via phpMyAdmin):
    // ALTER TABLE parking_logs MODIFY vehicle_id INT NULL;
    // This ensures guest logs can store NULL vehicle_id properly.

    if ($action === 'OUT') {
        // Delete guest record on time out
        $del = $conn->prepare("DELETE FROM guests WHERE id = ?");
        $del->bind_param("i", $guest_id);
        $del->execute();
        $status_title = "Guest timed out and removed";
        $status_kind = 'danger';
    } else {
        $status_title = "Guest timed in";
        $status_kind = 'success';
    }

    $status_message = "Owner: " . htmlspecialchars($owner_name);
}

// Render a clean status page with actions and optional auto-redirect
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($status_title); ?></title>
    <script src="assets/js/reload_on_nav.js"></script>
    <link rel="stylesheet" href="assets/css/process_parking.css">
</head>
<body>
    <div class="status-card <?= $status_kind === 'success' ? 'success' : ($status_kind === 'danger' ? 'danger' : '') ?>">
        <h1><span class="status-dot"></span> <?= htmlspecialchars($status_title); ?></h1>
        <div class="message"><?= $status_message; ?></div>
        <div class="meta">Time: <?= date('Y-m-d H:i:s'); ?></div>

        <a href="scan.php" class="btn btn-primary">Back to Scanner</a>
        <a href="view_vehicles.php" class="btn btn-secondary">View Vehicles</a>
        <a href="activity_logs.php" class="btn btn-secondary">View Logs</a>

        <?php if ($auto_redirect): ?>
            <div class="auto-note">You will be redirected to the scanner in <span id="count">4</span> seconds...</div>
            <script>
                (function(){
                    var c = 4;
                    var el = document.getElementById('count');
                    var t = setInterval(function(){ c--; if(c<=0){ clearInterval(t); window.location.href='scan.php'; } el.textContent = c; }, 1000);
                })();
            </script>
        <?php endif; ?>
    </div>
</body>
</html>