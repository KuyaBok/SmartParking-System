<?php
session_start();
require 'db.php';

if (!isset($_GET['id'])) {
    header('Location: view_vehicles.php');
    exit();
}

$id = intval($_GET['id']);
$vehicle_id = intval($_GET['vehicle_id'] ?? 0);

// fetch image row
$stmt = $conn->prepare("SELECT image_path, vehicle_id FROM vehicle_images WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $path = $row['image_path'];
    // unlink safely
    if (!empty($path)) {
        if (file_exists($path)) {
            @unlink($path);
        } else {
            $alt = __DIR__ . '/' . ltrim($path, '/\\');
            if (file_exists($alt)) @unlink($alt);
        }
    }
    // delete DB row
    $del = $conn->prepare("DELETE FROM vehicle_images WHERE id = ?");
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();
    $vehicle_id = intval($row['vehicle_id']);
}
$stmt->close();
// set a flash message to inform the user
$_SESSION['success'] = 'Image removed successfully.';
if ($vehicle_id) {
    header('Location: edit_vehicle.php?id=' . $vehicle_id);
} else {
    header('Location: view_vehicles.php');
}
exit();
?>