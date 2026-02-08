<?php
session_start();
require 'db.php';

if (isset($_POST['register_vehicle'])) {
    $plate_number = trim($_POST['plate_number']);
    $owner_name = trim($_POST['owner_name']);
    $contact_number = trim($_POST['contact_number']);
    $vehicle_description = trim($_POST['vehicle_description']);
    $vehicle_type = trim($_POST['vehicle_type']);


    // Improved duplicate checks: primarily check plate/vehicle number uniqueness and contact number.
    // 1) Check plate number against guests and vehicles
    if (!empty($plate_number)) {
        $plateGuest = $conn->prepare("SELECT id FROM guests WHERE plate_number = ?");
        $plateGuest->bind_param("s", $plate_number);
        $plateGuest->execute();
        $pg = $plateGuest->get_result();

        $plateVeh = $conn->prepare("SELECT id FROM vehicles WHERE vehicle_number = ?");
        $plateVeh->bind_param("s", $plate_number);
        $plateVeh->execute();
        $pv = $plateVeh->get_result();

        if (($pg && $pg->num_rows > 0) || ($pv && $pv->num_rows > 0)) {
            $_SESSION['error'] = "Registration denied. Plate number already registered.";
            header("Location: register_guest.php");
            exit();
        }
    }

    // 2) Check contact number uniqueness (optional but helpful)
    if (!empty($contact_number)) {
        $c1 = $conn->prepare("SELECT id FROM guests WHERE contact_number = ?");
        $c1->bind_param("s", $contact_number);
        $c1->execute();
        $r1 = $c1->get_result();

        $c2 = $conn->prepare("SELECT id FROM vehicles WHERE contact_number = ?");
        $c2->bind_param("s", $contact_number);
        $c2->execute();
        $r2 = $c2->get_result();

        if (($r1 && $r1->num_rows > 0) || ($r2 && $r2->num_rows > 0)) {
            $_SESSION['error'] = "Registration denied. Contact number is already registered.";
            header("Location: register_guest.php");
            exit();
        }
    }

    // Note: we no longer block solely on owner_name or vehicle_type matches as they can be common.

    // Ensure 'vehicle_description' column exists in guests table
    $colCheckDesc = $conn->query("SHOW COLUMNS FROM guests LIKE 'vehicle_description'");
    if (!$colCheckDesc || $colCheckDesc->num_rows === 0) {
        $conn->query("ALTER TABLE guests ADD COLUMN vehicle_description VARCHAR(255) DEFAULT NULL");
    }

    $insertStmt = $conn->prepare("
        INSERT INTO guests 
        (plate_number, owner_name, contact_number, vehicle_description, vehicle_type)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertStmt->bind_param(
        "sssss",
        $plate_number,
        $owner_name,
        $contact_number,
        $vehicle_description,
        $vehicle_type
    );

    if ($insertStmt->execute()) {
        $_SESSION['success'] = "Guest vehicle registered successfully.";
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }

    header("Location: register_guest.php");
    exit();
}
