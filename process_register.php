<?php
session_start();
require 'db.php';

if (isset($_POST['register_vehicle'])) {

    $vehicle_number = trim($_POST['vehicle_number']);
    $owner_name = trim($_POST['owner_name']);
    $owner_id = trim($_POST['owner_id']);
    $contact_number = trim($_POST['contact_number']);
    $owner_email = trim($_POST['owner_email']);
    $vehicle_description = trim($_POST['vehicle_description']);
    $vehicle_type = trim($_POST['vehicle_type']);

    // Ensure 'owner_email' column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'owner_email'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE vehicles ADD COLUMN owner_email VARCHAR(255) DEFAULT NULL");
    }

    // Ensure 'vehicle_description' column exists
    $colCheckDesc = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_description'");
    if (!$colCheckDesc || $colCheckDesc->num_rows === 0) {
        $conn->query("ALTER TABLE vehicles ADD COLUMN vehicle_description VARCHAR(255) DEFAULT NULL");
    }

    if (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please provide a valid email address.";
        header("Location: register.php");
        exit();
    }

    $checkStmt = $conn->prepare("
        SELECT id FROM vehicles
        WHERE vehicle_number = ?
           OR owner_name = ?
           OR owner_id = ?
           OR contact_number = ?
           OR owner_email = ?
    ");
    $checkStmt->bind_param(
        "sssss",
        $vehicle_number,
        $owner_name,
        $owner_id,
        $contact_number,
        $owner_email
    );
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Registration denied. One or more details are already registered.";
        header("Location: register.php");
        exit();
    }

    $insertStmt = $conn->prepare("
        INSERT INTO vehicles 
        (vehicle_number, owner_name, owner_id, contact_number, owner_email, vehicle_description, vehicle_type)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->bind_param(
        "sssssss",
        $vehicle_number,
        $owner_name,
        $owner_id,
        $contact_number,
        $owner_email,
        $vehicle_description,
        $vehicle_type
    );

    if ($insertStmt->execute()) {
        $vehicle_id = $conn->insert_id;

        // Ensure 'qr_token' and 'qr_image' columns exist so Generate QR can work later
        $colCheckToken = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'qr_token'");
        if (!$colCheckToken || $colCheckToken->num_rows === 0) {
            $conn->query("ALTER TABLE vehicles ADD COLUMN qr_token VARCHAR(255) DEFAULT NULL");
        }
        $colCheckImage = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'qr_image'");
        if (!$colCheckImage || $colCheckImage->num_rows === 0) {
            $conn->query("ALTER TABLE vehicles ADD COLUMN qr_image VARCHAR(255) DEFAULT NULL");
        }

        // Do NOT auto-generate QR upon registration. Admin should generate and optionally send via the Generate QR page.
        $_SESSION['success'] = "Vehicle registered successfully. You can generate and send the QR from the vehicle's Generate QR page.";
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }

    header("Location: register.php");
    exit();
}
