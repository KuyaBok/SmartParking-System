<?php
require 'db.php';

// Support deletion for both registered vehicles and guest vehicles
if (isset($_GET['guest_id'])) {
    $guest_id = intval($_GET['guest_id']);

    // remove qr image if exists
    $stmt = $conn->prepare("SELECT qr_image FROM guests WHERE id = ?");
    $stmt->bind_param("i", $guest_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['qr_image']) && file_exists($row['qr_image'])) {
            @unlink($row['qr_image']);
        }
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
    $stmt = $conn->prepare("SELECT qr_image FROM vehicles WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['qr_image']) && file_exists($row['qr_image'])) {
            @unlink($row['qr_image']);
        }
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
