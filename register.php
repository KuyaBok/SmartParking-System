<?php
session_start();
if (isset($_SESSION['success'])) {
    echo "<p class='success-message'>" . $_SESSION['success'] . "</p>";
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo "<p class='error-message'>" . $_SESSION['error'] . "</p>";
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Student Vehicle</title>
    <link rel="stylesheet" href="register.css">
    <script src="assets/js/reload_on_nav.js"></script> 
</head>
<body>
    <a href="register_choice.php" class="back-btn">
        <svg viewBox="0 0 24 24"><path d="M15.5 19l-7-7 7-7"/></svg>
        Back
    </a>

<div class="login-container">
    <div class="form-box active">
        <h2>Register Student Vehicle</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form action="process_register.php" method="post">

            <input type="text" name="vehicle_number" placeholder="Plate Number" >

            <input type="text" name="owner_name" placeholder="Owner Name" required>

            <input type="text" name="owner_id" placeholder="Owner ID" required>

            <input type="text" name="contact_number" placeholder="Contact Number" required>

            <input type="email" name="owner_email" placeholder="Student Email" required>

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

</body>
</html>

