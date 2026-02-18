<?php
session_start();
require 'db.php';

$id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_number = $_POST['vehicle_number'];
    $owner_name = $_POST['owner_name'];
    $owner_id = $_POST['owner_id'];
    $contact = $_POST['contact_number'];
    $type = $_POST['vehicle_type'];
    // fetch current values to detect changes
    $curStmt = $conn->prepare("SELECT vehicle_number, owner_name, owner_id, contact_number, vehicle_type FROM vehicles WHERE id = ?");
    $curStmt->bind_param('i', $id);
    $curStmt->execute();
    $curRes = $curStmt->get_result();
    $curRow = $curRes->fetch_assoc();
    $curStmt->close();

    $changed = false;
    if (!$curRow) $changed = true; else {
        if (trim($vehicle_number) !== trim($curRow['vehicle_number'])) $changed = true;
        if (trim($owner_name) !== trim($curRow['owner_name'])) $changed = true;
        if (trim($owner_id) !== trim($curRow['owner_id'])) $changed = true;
        if (trim($contact) !== trim($curRow['contact_number'])) $changed = true;
        if (trim($type) !== trim($curRow['vehicle_type'])) $changed = true;
    }

    // track whether any new images were successfully saved
    $savedAny = false;

    // Handle multiple optional vehicle and license images upload on edit (resize to max 800x600)
    $maxSize = 16 * 1024 * 1024;
    $maxVehicleFiles = 4; // max vehicle photos
    $maxLicenseFiles = 2; // max license photos
    $hasVehicleImagesTable = ($conn->query("SHOW TABLES LIKE 'vehicle_images' ")->num_rows > 0);
    if (!$hasVehicleImagesTable) {
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

    if (isset($_FILES['vehicle_images']) && is_array($_FILES['vehicle_images']) && !empty($_FILES['vehicle_images']['name'])) {
        $files = $_FILES['vehicle_images'];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        $uploadDir = __DIR__ . '/uploads/vehicle_images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // determine how many vehicle images already stored for this vehicle (exclude license images)
        $existingVehicleCount = 0;
        if ($hasVehicleImagesTable) {
            $sc = $conn->prepare("SELECT COUNT(*) AS cnt FROM vehicle_images WHERE vehicle_id = ? AND image_path NOT LIKE '%license_images/%'");
            $sc->bind_param('i', $id);
            $sc->execute();
            $rc = $sc->get_result();
            if ($r = $rc->fetch_assoc()) $existingVehicleCount = intval($r['cnt']);
            $sc->close();
        }

        $allowedToStore = max(0, $maxVehicleFiles - $existingVehicleCount);
        $firstSavedRelative = null;

        for ($i = 0; $i < $count && $allowedToStore > 0; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > $maxSize) continue;
            if (!is_uploaded_file($files['tmp_name'][$i])) continue;

            $imageInfo = @getimagesize($files['tmp_name'][$i]);
            if ($imageInfo === false) continue;
            $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
            if (!isset($allowedTypes[$imageInfo[2]])) continue;

            $ext = $allowedTypes[$imageInfo[2]];
            $safeName = 'vehicle_' . $id . '_' . time() . '_' . $i . '.' . $ext;
            $destPath = $uploadDir . $safeName;

            // Resize
            $maxW = 800; $maxH = 600;
            list($width, $height, $typeImg) = $imageInfo;
            $ratio = $width / $height;
            $newW = $width; $newH = $height;
            if ($width > $maxW || $height > $maxH) {
                if ($ratio > 1) { $newW = $maxW; $newH = intval($maxW / $ratio); } else { $newH = $maxH; $newW = intval($maxH * $ratio); }
            }

            $srcImg = null;
            switch ($typeImg) {
                case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($files['tmp_name'][$i]); break;
                case IMAGETYPE_PNG: $srcImg = imagecreatefrompng($files['tmp_name'][$i]); break;
                case IMAGETYPE_GIF: $srcImg = imagecreatefromgif($files['tmp_name'][$i]); break;
            }
            if ($srcImg) {
                $dstImg = imagecreatetruecolor($newW, $newH);
                if ($typeImg === IMAGETYPE_PNG) { imagealphablending($dstImg, false); imagesavealpha($dstImg, true); $transparent = imagecolorallocatealpha($dstImg,255,255,255,127); imagefilledrectangle($dstImg,0,0,$newW,$newH,$transparent); }
                imagecopyresampled($dstImg, $srcImg, 0,0,0,0, $newW, $newH, $width, $height);
                $saved = false;
                switch ($typeImg) { case IMAGETYPE_JPEG: $saved = imagejpeg($dstImg, $destPath, 85); break; case IMAGETYPE_PNG: $saved = imagepng($dstImg, $destPath); break; case IMAGETYPE_GIF: $saved = imagegif($dstImg, $destPath); break; }
                imagedestroy($srcImg); imagedestroy($dstImg);

                if ($saved) {
                    $relativePath = 'uploads/vehicle_images/' . $safeName;
                    if ($hasVehicleImagesTable) {
                        $ins = $conn->prepare("INSERT INTO vehicle_images (vehicle_id, image_path) VALUES (?, ?)");
                        $ins->bind_param('is', $id, $relativePath);
                        $ins->execute();
                        $ins->close();
                    }
                    if ($firstSavedRelative === null) {
                        $firstSavedRelative = $relativePath;
                        // if vehicles.vehicle_image is empty, set it for backward compatibility
                        $qv = $conn->prepare("SELECT vehicle_image FROM vehicles WHERE id = ?");
                        $qv->bind_param('i', $id);
                        $qv->execute();
                        $rv = $qv->get_result();
                        $existingMain = ($r = $rv->fetch_assoc()) ? ($r['vehicle_image'] ?? '') : '';
                        $qv->close();
                        if (empty($existingMain)) {
                            $upd = $conn->prepare("UPDATE vehicles SET vehicle_image = ? WHERE id = ?");
                            $upd->bind_param('si', $firstSavedRelative, $id);
                            $upd->execute();
                            $upd->close();
                        }
                    }
                    $savedAny = true;
                    $allowedToStore--;
                }
            }
        }

    // Process license images (optional)
    if (isset($_FILES['license_images']) && is_array($_FILES['license_images']) && !empty($_FILES['license_images']['name'])) {
        $lfiles = $_FILES['license_images'];
        $lcount = is_array($lfiles['name']) ? count($lfiles['name']) : 0;
        $licUploadDir = __DIR__ . '/uploads/license_images/';
        if (!is_dir($licUploadDir)) mkdir($licUploadDir, 0755, true);

        // determine how many license images already stored for this vehicle
        $existingLicenseCount = 0;
        if ($hasVehicleImagesTable) {
            $sc2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM vehicle_images WHERE vehicle_id = ? AND image_path LIKE '%license_images/%'");
            $sc2->bind_param('i', $id);
            $sc2->execute();
            $rc2 = $sc2->get_result();
            if ($r2 = $rc2->fetch_assoc()) $existingLicenseCount = intval($r2['cnt']);
            $sc2->close();
        }

        $allowedLicense = max(0, $maxLicenseFiles - $existingLicenseCount);

        for ($i = 0; $i < $lcount && $allowedLicense > 0; $i++) {
            if ($lfiles['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($lfiles['size'][$i] > $maxSize) continue;
            if (!is_uploaded_file($lfiles['tmp_name'][$i])) continue;

            $imageInfo = @getimagesize($lfiles['tmp_name'][$i]);
            if ($imageInfo === false) continue;
            $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
            if (!isset($allowedTypes[$imageInfo[2]])) continue;

            $ext = $allowedTypes[$imageInfo[2]];
            $safeName = 'license_' . $id . '_' . time() . '_' . $i . '.' . $ext;
            $destPath = $licUploadDir . $safeName;

            // Resize
            $maxW = 800; $maxH = 600;
            list($width, $height, $typeImg) = $imageInfo;
            $ratio = $width / $height;
            $newW = $width; $newH = $height;
            if ($width > $maxW || $height > $maxH) {
                if ($ratio > 1) { $newW = $maxW; $newH = intval($maxW / $ratio); } else { $newH = $maxH; $newW = intval($maxH * $ratio); }
            }

            $srcImg = null;
            switch ($typeImg) {
                case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($lfiles['tmp_name'][$i]); break;
                case IMAGETYPE_PNG: $srcImg = imagecreatefrompng($lfiles['tmp_name'][$i]); break;
                case IMAGETYPE_GIF: $srcImg = imagecreatefromgif($lfiles['tmp_name'][$i]); break;
            }
            if ($srcImg) {
                $dstImg = imagecreatetruecolor($newW, $newH);
                if ($typeImg === IMAGETYPE_PNG) { imagealphablending($dstImg, false); imagesavealpha($dstImg, true); $transparent = imagecolorallocatealpha($dstImg,255,255,255,127); imagefilledrectangle($dstImg,0,0,$newW,$newH,$transparent); }
                imagecopyresampled($dstImg, $srcImg, 0,0,0,0, $newW, $newH, $width, $height);
                $saved = false;
                switch ($typeImg) { case IMAGETYPE_JPEG: $saved = imagejpeg($dstImg, $destPath, 85); break; case IMAGETYPE_PNG: $saved = imagepng($dstImg, $destPath); break; case IMAGETYPE_GIF: $saved = imagegif($dstImg, $destPath); break; }
                imagedestroy($srcImg); imagedestroy($dstImg);

                if ($saved) {
                    $relativePath = 'uploads/license_images/' . $safeName;
                    if ($hasVehicleImagesTable) {
                        $ins2 = $conn->prepare("INSERT INTO vehicle_images (vehicle_id, image_path) VALUES (?, ?)");
                        $ins2->bind_param('is', $id, $relativePath);
                        $ins2->execute();
                        $ins2->close();
                    }
                    $savedAny = true;
                    $allowedLicense--;
                }
            }
        }
    }
    }
    // only update vehicle fields when something changed
    if ($changed) {
        $stmt = $conn->prepare("UPDATE vehicles SET vehicle_number=?, owner_name=?, owner_id=?, contact_number=?, vehicle_type=? WHERE id=?");
        $stmt->bind_param("sssssi", $vehicle_number, $owner_name, $owner_id, $contact, $type, $id);
        $stmt->execute();
    }

    // set appropriate flash message
    if ($changed || $savedAny) {
        $_SESSION['success'] = 'Vehicle updated successfully.';
    } else {
        $_SESSION['success'] = 'No Changes Made.';
    }
    header("Location: edit_vehicle.php?id=" . intval($id));
    exit();
}

// flash message handling
$successMsg = null;
if (isset($_SESSION['success'])) { $successMsg = $_SESSION['success']; unset($_SESSION['success']); }

$result = $conn->query("SELECT * FROM vehicles WHERE id=$id");
$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Vehicle</title>
    <link rel="stylesheet" href="edit_vehicle.css">
    <link rel="stylesheet" href="assets/css/image_gallery.css">
    <link rel="stylesheet" href="assets/css/success_modal.css">
    <script src="assets/js/reload_on_nav.js"></script>
    <script src="assets/js/success_modal.js"></script>
</head>
<body>

    <div class="gallery-top-actions">
        <a href="view_vehicles.php" class="back-btn">
            <svg viewBox="0 0 24 24"><path d="M15.5 19l-7-7 7-7"/></svg>
            Back
        </a>
        <a href="dashboard.php" class="back-btn dashboard-link">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Dashboard
        </a>
    </div>

<div class="login-container">
    <div class="form-box">
        <h2>Edit Vehicle</h2>

        <form method="post" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>Vehicle Number</label>
                    <input type="text" name="vehicle_number" value="<?= htmlspecialchars($row['vehicle_number']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Owner Name</label>
                    <input type="text" name="owner_name" value="<?= htmlspecialchars($row['owner_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Owner ID</label>
                    <input type="text" name="owner_id" value="<?= htmlspecialchars($row['owner_id']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" value="<?= htmlspecialchars($row['contact_number']) ?>" required>
                </div>
                <div class="form-group full">
                    <label>Vehicle Type</label>
                    <select name="vehicle_type" required>
                        <option value="">-- Select Vehicle Type --</option>
                        <option value="car" <?= ($row['vehicle_type'] === 'car') ? 'selected' : '' ?>>Four-Wheels</option>
                        <option value="motor" <?= ($row['vehicle_type'] === 'motor') ? 'selected' : '' ?>>Motorcycle</option>
                        <option value="bike" <?= ($row['vehicle_type'] === 'bike') ? 'selected' : '' ?>>Bicycle</option>
                        <option value="evehicle" <?= ($row['vehicle_type'] === 'evehicle') ? 'selected' : '' ?>>Electric Vehicle</option>
                    </select>
                </div>
            </div>

            <?php
            // Fetch images and separate vehicle vs license images
            $vehicle_imgs = [];
            $license_imgs = [];
            $hasTable = ($conn->query("SHOW TABLES LIKE 'vehicle_images'")->num_rows > 0);
            if ($hasTable) {
                $qi = $conn->prepare("SELECT id, image_path FROM vehicle_images WHERE vehicle_id = ? ORDER BY id ASC");
                $qi->bind_param('i', $id);
                $qi->execute();
                $r = $qi->get_result();
                while ($ir = $r->fetch_assoc()) {
                    if (strpos($ir['image_path'], 'license_images/') !== false) $license_imgs[] = $ir; else $vehicle_imgs[] = $ir;
                }
                $qi->close();
            } else {
                if (!empty($row['vehicle_image'])) $vehicle_imgs[] = ['id' => 0, 'image_path' => $row['vehicle_image']];
            }
            ?>

            <label>Current Vehicle Images</label>
            <div class="current-images" style="margin-bottom:8px;">
                <?php if (empty($vehicle_imgs)): ?>
                    <div style="width:200px;height:120px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;">No Image</div>
                <?php else: ?>
                    <?php foreach ($vehicle_imgs as $img):
                        $path = htmlspecialchars($img['image_path']);
                        $iid = intval($img['id']);
                    ?>
                        <div class="gallery-item">
                            <a href="<?= $path ?>" class="thumb-wrapper" target="_blank"><img src="<?= $path ?>" class="vehicle-thumb" alt="vehicle"></a>
                            <?php if ($iid > 0): ?>
                                <a href="delete_vehicle_image.php?id=<?= $iid ?>&vehicle_id=<?= $id ?>" class="preview-remove current-remove" onclick="return confirm('Remove this image?');">×</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <label for="vehicle_images">Add/Replace Vehicle Images (optional, up to 4 total)</label>
            <input type="file" name="vehicle_images[]" id="vehicle_images" class="vehicle-image-input" accept="image/*" multiple>
            <div id="vehicle_images-preview" class="preview-row" style="margin-top:8px;"></div>

            <label>Current License Images</label>
            <div class="current-images" style="margin-bottom:8px;">
                <?php if (empty($license_imgs)): ?>
                    <div style="width:200px;height:120px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;">No License Images</div>
                <?php else: ?>
                    <?php foreach ($license_imgs as $img):
                        $path = htmlspecialchars($img['image_path']);
                        $iid = intval($img['id']);
                    ?>
                        <div class="gallery-item">
                            <a href="<?= $path ?>" class="thumb-wrapper" target="_blank"><img src="<?= $path ?>" class="vehicle-thumb" alt="license"></a>
                            <?php if ($iid > 0): ?>
                                <a href="delete_vehicle_image.php?id=<?= $iid ?>&vehicle_id=<?= $id ?>" class="preview-remove current-remove" onclick="return confirm('Remove this image?');">×</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <label for="license_images">Add/Replace License Images (optional, up to 2)</label>
            <input type="file" name="license_images[]" id="license_images" class="license-image-input" accept="image/*" multiple>
            <div id="license_images-preview" class="preview-row" style="margin-top:8px;"></div>

            <div class="btn-group">
                <button type="submit" class="btn save-btn">Update</button>
                <a href="view_vehicles.php" class="btn cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>

<script src="assets/js/file_accumulator.js"></script>
<script src="assets/js/image_validate.js"></script>
<script src="assets/js/image_lightbox.js"></script>
    <?php if (!empty($successMsg)): ?>
        <div id="modal-overlay" class="modal-overlay">
            <div class="modal-card">
                <div class="modal-check">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="#22c55e" d="M9 16.2l-3.5-3.5L4 14.2 9 19 20 8l-1.5-1.5z"></path></svg>
                </div>
                <div class="modal-title">Successful!</div>
                <div class="modal-body"><?= htmlspecialchars($successMsg) ?></div>
                <button id="modal-ok-btn" class="modal-ok">OK</button>
            </div>
        </div>
    <?php endif; ?>