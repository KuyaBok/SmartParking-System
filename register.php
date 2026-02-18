<?php
session_start();
$successMsg = null;
$errorMsg = null;
if (isset($_SESSION['success'])) { $successMsg = $_SESSION['success']; unset($_SESSION['success']); }
if (isset($_SESSION['error'])) { $errorMsg = $_SESSION['error']; unset($_SESSION['error']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Student Vehicle</title>
    <link rel="stylesheet" href="register.css">
    <link rel="stylesheet" href="assets/css/image_gallery.css">
    <link rel="stylesheet" href="assets/css/success_modal.css">
    <script src="assets/js/reload_on_nav.js"></script> 
</head>
<body>
    <div class="top-actions">
        <a href="register_choice.php" class="back-btn">
            <svg viewBox="0 0 24 24"><path d="M15.5 19l-7-7 7-7"/></svg>
            Back
        </a>
        <a href="dashboard.php" class="back-btn dashboard-link">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Dashboard
        </a>
    </div>

<div class="login-container">
    <div class="form-box active">
        <h2>Register Student Vehicle</h2>

        <?php if ($errorMsg): ?>
            <div class="error-message">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <form action="process_register.php" method="post" enctype="multipart/form-data">

            <input type="text" name="vehicle_number" placeholder="Plate Number" >
            <label for="vehicle_images">Upload Vehicle Images (Max 16MB each, up to 4 images)</label>
            <input type="file" name="vehicle_images[]" id="vehicle_images" class="vehicle-image-input" accept="image/*" multiple>
            <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;" id="vehicle_images-preview"></div>

            <label for="license_images">Upload License Images (Front & Back) — Max 2 images (front, back)</label>
            <input type="file" name="license_images[]" id="license_images" class="license-image-input" accept="image/*" multiple>
            <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;" id="license_images-preview"></div>

            <input type="text" name="owner_name" placeholder="Owner Name" required>

            <input type="text" name="owner_id" placeholder="Owner ID" required>

            <input type="text" name="contact_number" placeholder="Contact Number" required>

            <input type="email" name="owner_email" placeholder="Student Email" required>
            <input type="text" name="vehicle_description" placeholder="Vehicle Description (e.g., Toyota Vios, Honda Civic)" required>
            <label for="vehicle_type">Type:</label>
            <select name="vehicle_type" id="vehicle_type" required>
                <option value="">-- Select Vehicle Type --</option>
                <option value="car">Four-Wheels</option>
                <option value="motor">Motorcycle</option>
                <option value="bike">Bicycle</option>
                <option value="evehicle">Electric Vehicle</option>
            </select>
            <button type="submit" name="register_vehicle">Register Vehicle</button>
        </form>
    </div>
</div>

    <script src="assets/js/file_accumulator.js"></script>
    <script src="assets/js/image_validate.js"></script>
    <script src="assets/js/image_lightbox.js"></script>
    <script src="assets/js/success_modal.js"></script>

    <?php if ($successMsg): ?>
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

    </body>
    </html>

