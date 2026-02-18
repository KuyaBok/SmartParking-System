<?php
require 'db.php';

// Support deletion for both registered vehicles and guest vehicles
if (isset($_GET['guest_id'])) {
    $guest_id = intval($_GET['guest_id']);

    // remove qr image if exists
    $stmt = $conn->prepare("SELECT qr_image" . ( ($conn->query("SHOW COLUMNS FROM guests LIKE 'vehicle_image'")->num_rows>0) ? ", vehicle_image" : "" ) . " FROM guests WHERE id = ?");
    $stmt->bind_param("i", $guest_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // helper to unlink either absolute or relative paths safely
        $unlinkIfExists = function($p) {
            if (empty($p)) return;
            if (file_exists($p)) { @unlink($p); return; }
            $alt = __DIR__ . '/' . ltrim($p, '/\\');
            if (file_exists($alt)) @unlink($alt);
        };
        $unlinkIfExists($row['qr_image'] ?? '');
        if (isset($row['vehicle_image'])) $unlinkIfExists($row['vehicle_image']);
    }

    $del = $conn->prepare("DELETE FROM guests WHERE id = ?");
    $del->bind_param("i", $guest_id);
    $del->execute();

    header("Location: view_vehicles.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // remove qr image if exists
    // select qr_image and vehicle_image if available
    $hasVehImg = ($conn->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_image'")->num_rows>0);
    $selectCols = 'qr_image' . ($hasVehImg ? ', vehicle_image' : '');
    $stmt = $conn->prepare("SELECT $selectCols FROM vehicles WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $unlinkIfExists = function($p) {
            if (empty($p)) return;
            if (file_exists($p)) { @unlink($p); return; }
            $alt = __DIR__ . '/' . ltrim($p, '/\\');
            if (file_exists($alt)) @unlink($alt);
        };
        $unlinkIfExists($row['qr_image'] ?? '');
        if ($hasVehImg) $unlinkIfExists($row['vehicle_image'] ?? '');
    }

    // Also remove all images stored in vehicle_images table (if present)
    if ($conn->query("SHOW TABLES LIKE 'vehicle_images'")->num_rows > 0) {
        $si = $conn->prepare("SELECT image_path FROM vehicle_images WHERE vehicle_id = ?");
        $si->bind_param('i', $id);
        $si->execute();
        $rimgs = $si->get_result();
        while ($ir = $rimgs->fetch_assoc()) {
            $p = $ir['image_path'];
            if (empty($p)) continue;
            if (file_exists($p)) { @unlink($p); continue; }
            $alt = __DIR__ . '/' . ltrim($p, '/\\');
            if (file_exists($alt)) @unlink($alt);
        }
        $si->close();
    }

    $stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: view_vehicles.php");
    exit();
}

// If no valid id provided, just redirect back
header("Location: view_vehicles.php");
exit();
