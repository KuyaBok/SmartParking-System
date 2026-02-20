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

    // Collect all duplicate field errors
    $duplicates = [];

    // Check vehicle number
    if (!empty($vehicle_number)) {
        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE vehicle_number = ?");
        $stmt->bind_param("s", $vehicle_number);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $duplicates[] = "Plate number";
        }
        $stmt->close();
    }

    // Check owner name
    if (!empty($owner_name)) {
        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE owner_name = ?");
        $stmt->bind_param("s", $owner_name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $duplicates[] = "Name";
        }
        $stmt->close();
    }

    // Check owner ID
    if (!empty($owner_id)) {
        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE owner_id = ?");
        $stmt->bind_param("s", $owner_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $duplicates[] = "Owner ID";
        }
        $stmt->close();
    }

    // Check contact number
    if (!empty($contact_number)) {
        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE contact_number = ?");
        $stmt->bind_param("s", $contact_number);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $duplicates[] = "Contact number";
        }
        $stmt->close();
    }

    // Check owner email
    if (!empty($owner_email)) {
        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE owner_email = ?");
        $stmt->bind_param("s", $owner_email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $duplicates[] = "Email";
        }
        $stmt->close();
    }

    // If any duplicates found, show comprehensive error
    if (!empty($duplicates)) {
        $bulletList = "<br>" . implode("<br>", array_map(function($item) { return "• " . $item; }, $duplicates));
        $_SESSION['error'] = "Registration denied. The following are already registered:" . $bulletList;
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

        // Ensure 'vehicle_image' column exists to store a primary photo (backwards compatibility)
        $colCheckVehImg = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_image'");
        if (!$colCheckVehImg || $colCheckVehImg->num_rows === 0) {
            $conn->query("ALTER TABLE vehicles ADD COLUMN vehicle_image VARCHAR(255) DEFAULT NULL");
        }

        // Handle multiple uploaded vehicle images (optional) with server-side resizing (max 800x600)
        // Requires a `vehicle_images` table (one row per image). If the table is missing, fallback to single-column behavior.
        $hasVehicleImagesTable = ($conn->query("SHOW TABLES LIKE 'vehicle_images'")->num_rows > 0);
        if (!$hasVehicleImagesTable) {
            // Try to create the table automatically so multi-image uploads work even if user didn't run SQL
            $createSql = "CREATE TABLE IF NOT EXISTS vehicle_images (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                INDEX (vehicle_id),
                CONSTRAINT vehicle_images_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $conn->query($createSql);
            $hasVehicleImagesTable = ($conn->query("SHOW TABLES LIKE 'vehicle_images'")->num_rows > 0);
        }
        $maxSize = 16 * 1024 * 1024; // 16MB
        $maxVehicleFiles = 4;
        $maxLicenseFiles = 2;
        $firstSavedRelative = null;

        $vehicleStoredCount = 0;
        $vehicleSelected = 0;
        $licenseStoredCount = 0;
        $licenseSelected = 0;
        $skipped = [];

        // --- Vehicle images processing (up to $maxVehicleFiles) ---
        if (isset($_FILES['vehicle_images']) && is_array($_FILES['vehicle_images']) && !empty($_FILES['vehicle_images']['name'])) {
            $files = $_FILES['vehicle_images'];
            $count = is_array($files['name']) ? count($files['name']) : 0;
            $vehicleSelected = $count;
            $uploadDir = __DIR__ . '/uploads/vehicle_images/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // If table exists, determine how many images already stored (should be 0 for new vehicle)
            $existingCount = 0;
            if ($hasVehicleImagesTable) {
                $stmtCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM vehicle_images WHERE vehicle_id = ?");
                $stmtCount->bind_param('i', $vehicle_id);
                $stmtCount->execute();
                $resCnt = $stmtCount->get_result();
                if ($rc = $resCnt->fetch_assoc()) $existingCount = intval($rc['cnt']);
                $stmtCount->close();
            }

            $allowedToStore = max(0, $maxVehicleFiles - $existingCount);
            for ($i = 0; $i < $count && $allowedToStore > 0; $i++) {
                if (!isset($files['error'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) { $skipped[] = "vehicle file #".($i+1)." upload error"; continue; }
                if (!isset($files['size'][$i]) || $files['size'][$i] > $maxSize) { $skipped[] = "vehicle file #".($i+1)." too large"; continue; }
                if (!is_uploaded_file($files['tmp_name'][$i])) { $skipped[] = "vehicle file #".($i+1)." not uploaded"; continue; }

                $imageInfo = @getimagesize($files['tmp_name'][$i]);
                if ($imageInfo === false) continue;
                $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
                if (!isset($allowedTypes[$imageInfo[2]])) continue;

                $ext = $allowedTypes[$imageInfo[2]];
                $safeName = 'vehicle_' . $vehicle_id . '_' . time() . '_' . $i . '.' . $ext;
                $destPath = $uploadDir . $safeName;

                // Resize image to max 800x600 keeping aspect ratio
                $maxW = 800; $maxH = 600;
                list($width, $height, $type) = $imageInfo;
                $ratio = $width / $height;
                $newW = $width; $newH = $height;
                if ($width > $maxW || $height > $maxH) {
                    if ($ratio > 1) { $newW = $maxW; $newH = intval($maxW / $ratio); } else { $newH = $maxH; $newW = intval($maxH * $ratio); }
                }

                $srcImg = null;
                switch ($type) {
                    case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($files['tmp_name'][$i]); break;
                    case IMAGETYPE_PNG: $srcImg = imagecreatefrompng($files['tmp_name'][$i]); break;
                    case IMAGETYPE_GIF: $srcImg = imagecreatefromgif($files['tmp_name'][$i]); break;
                }

                if ($srcImg) {
                    $dstImg = imagecreatetruecolor($newW, $newH);
                    if ($type === IMAGETYPE_PNG) { imagealphablending($dstImg, false); imagesavealpha($dstImg, true); $transparent = imagecolorallocatealpha($dstImg,255,255,255,127); imagefilledrectangle($dstImg,0,0,$newW,$newH,$transparent); }
                    imagecopyresampled($dstImg, $srcImg, 0,0,0,0, $newW, $newH, $width, $height);
                    $saved = false;
                    switch ($type) { case IMAGETYPE_JPEG: $saved = imagejpeg($dstImg, $destPath, 85); break; case IMAGETYPE_PNG: $saved = imagepng($dstImg, $destPath); break; case IMAGETYPE_GIF: $saved = imagegif($dstImg, $destPath); break; }
                    imagedestroy($srcImg); imagedestroy($dstImg);

                    if ($saved) {
                        $relativePath = 'uploads/vehicle_images/' . $safeName;
                        // Insert into vehicle_images table if available
                        if ($hasVehicleImagesTable) {
                            $ins = $conn->prepare("INSERT INTO vehicle_images (vehicle_id, image_path) VALUES (?, ?)");
                            $ins->bind_param('is', $vehicle_id, $relativePath);
                            $ins->execute();
                            $ins->close();
                        }
                        // For backward compatibility, set the vehicles.vehicle_image to the first uploaded image
                        if ($firstSavedRelative === null) {
                            $firstSavedRelative = $relativePath;
                            $upd = $conn->prepare("UPDATE vehicles SET vehicle_image = ? WHERE id = ?");
                            $upd->bind_param('si', $firstSavedRelative, $vehicle_id);
                            $upd->execute();
                            $upd->close();
                        }
                        $allowedToStore--;
                        $vehicleStoredCount++;
                    } else {
                        $skipped[] = "vehicle file #".($i+1)." failed to save";
                    }
                }
            }
        }

        // --- License images processing (up to $maxLicenseFiles) ---
        if (isset($_FILES['license_images']) && is_array($_FILES['license_images']) && !empty($_FILES['license_images']['name'])) {
            $files = $_FILES['license_images'];
            $count = is_array($files['name']) ? count($files['name']) : 0;
            $licenseSelected = $count;
            $uploadDir = __DIR__ . '/uploads/license_images/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $allowedToStore = $maxLicenseFiles;
            for ($i = 0; $i < $count && $allowedToStore > 0; $i++) {
                if (!isset($files['error'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) { $skipped[] = "license file #".($i+1)." upload error"; continue; }
                if (!isset($files['size'][$i]) || $files['size'][$i] > $maxSize) { $skipped[] = "license file #".($i+1)." too large"; continue; }
                if (!is_uploaded_file($files['tmp_name'][$i])) { $skipped[] = "license file #".($i+1)." not uploaded"; continue; }

                $imageInfo = @getimagesize($files['tmp_name'][$i]);
                if ($imageInfo === false) continue;
                $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
                if (!isset($allowedTypes[$imageInfo[2]])) continue;

                $ext = $allowedTypes[$imageInfo[2]];
                $safeName = 'license_' . $vehicle_id . '_' . time() . '_' . $i . '.' . $ext;
                $destPath = $uploadDir . $safeName;

                // Resize image to max 800x600 keeping aspect ratio
                $maxW = 800; $maxH = 600;
                list($width, $height, $type) = $imageInfo;
                $ratio = $width / $height;
                $newW = $width; $newH = $height;
                if ($width > $maxW || $height > $maxH) {
                    if ($ratio > 1) { $newW = $maxW; $newH = intval($maxW / $ratio); } else { $newH = $maxH; $newW = intval($maxH * $ratio); }
                }

                $srcImg = null;
                switch ($type) {
                    case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($files['tmp_name'][$i]); break;
                    case IMAGETYPE_PNG: $srcImg = imagecreatefrompng($files['tmp_name'][$i]); break;
                    case IMAGETYPE_GIF: $srcImg = imagecreatefromgif($files['tmp_name'][$i]); break;
                }

                if ($srcImg) {
                    $dstImg = imagecreatetruecolor($newW, $newH);
                    if ($type === IMAGETYPE_PNG) { imagealphablending($dstImg, false); imagesavealpha($dstImg, true); $transparent = imagecolorallocatealpha($dstImg,255,255,255,127); imagefilledrectangle($dstImg,0,0,$newW,$newH,$transparent); }
                    imagecopyresampled($dstImg, $srcImg, 0,0,0,0, $newW, $newH, $width, $height);
                    $saved = false;
                    switch ($type) { case IMAGETYPE_JPEG: $saved = imagejpeg($dstImg, $destPath, 85); break; case IMAGETYPE_PNG: $saved = imagepng($dstImg, $destPath); break; case IMAGETYPE_GIF: $saved = imagegif($dstImg, $destPath); break; }
                    imagedestroy($srcImg); imagedestroy($dstImg);

                    if ($saved) {
                        $relativePath = 'uploads/license_images/' . $safeName;
                        // Insert into vehicle_images table if available so gallery shows license images too
                        if ($hasVehicleImagesTable) {
                            $ins = $conn->prepare("INSERT INTO vehicle_images (vehicle_id, image_path) VALUES (?, ?)");
                            $ins->bind_param('is', $vehicle_id, $relativePath);
                            $ins->execute();
                            $ins->close();
                        }
                        $allowedToStore--;
                        $licenseStoredCount++;
                    } else {
                        $skipped[] = "license file #".($i+1)." failed to save";
                    }
                }
            }
        }
        // Simplified success message for UI modal
        $_SESSION['success'] = "Vehicle registered successfully.";
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }

    header("Location: register.php");
    exit();
}
