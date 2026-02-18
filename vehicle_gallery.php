<?php
require 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: view_vehicles.php');
    exit();
}

// Fetch vehicle basic info
$sv = $conn->prepare("SELECT id, vehicle_number, owner_name FROM vehicles WHERE id = ?");
$sv->bind_param('i', $id);
$sv->execute();
$rv = $sv->get_result();
$vehicle = $rv->fetch_assoc();
$sv->close();
if (!$vehicle) {
    header('Location: view_vehicles.php');
    exit();
}

// Fetch images
$images = [];
if ($conn->query("SHOW TABLES LIKE 'vehicle_images'")->num_rows > 0) {
    $qi = $conn->prepare("SELECT id, image_path, created_at FROM vehicle_images WHERE vehicle_id = ? ORDER BY id ASC");
    $qi->bind_param('i', $id);
    $qi->execute();
    $r = $qi->get_result();
    while ($row = $r->fetch_assoc()) $images[] = $row;
    $qi->close();
}

// Separate vehicle images vs license images by path
$vehicle_images = [];
$license_images = [];
foreach ($images as $img) {
    if (strpos($img['image_path'], 'license_images/') !== false) {
        $license_images[] = $img;
    } else {
        $vehicle_images[] = $img;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Gallery - <?= htmlspecialchars($vehicle['owner_name']) ?></title>
    <link rel="stylesheet" href="assets/css/image_gallery.css">
    <link rel="stylesheet" href="assets/css/view_vehicles.css">
    <script src="assets/js/reload_on_nav.js"></script>
</head>
<body>
    <div class="main-container">
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
        <h2>Images for <?= htmlspecialchars($vehicle['owner_name']) ?> — <?= htmlspecialchars($vehicle['vehicle_number']) ?></h2>

        <?php if (empty($vehicle_images) && empty($license_images)): ?>
            <div class="gallery-empty">No images uploaded for this vehicle.</div>
        <?php else: ?>

            <?php if (!empty($vehicle_images)): ?>
                <div class="gallery-section">
                    <div class="section-title">Vehicle Photos</div>
                    <div class="gallery-box">
                        <div class="gallery-wrap">
                        <?php foreach ($vehicle_images as $img): ?>
                            <div class="gallery-item">
                                <a class="thumb-wrapper" href="<?= htmlspecialchars($img['image_path']) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="vehicle image">
                                </a>
                                <div class="gallery-caption">
                                    <div class="gallery-meta">Uploaded: <?= htmlspecialchars($img['created_at']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($license_images)): ?>
                <div class="gallery-section">
                    <div class="section-title">License Images</div>
                    <div class="gallery-box">
                        <div class="gallery-wrap">
                        <?php foreach ($license_images as $img): ?>
                            <div class="gallery-item">
                                <a class="thumb-wrapper" href="<?= htmlspecialchars($img['image_path']) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="license image">
                                </a>
                                <div class="gallery-caption">
                                    <div class="gallery-meta">Uploaded: <?= htmlspecialchars($img['created_at']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <script src="assets/js/image_lightbox.js"></script>
</body>
</html>
