<?php
require 'db.php';

$id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_number = $_POST['vehicle_number'];
    $owner_name = $_POST['owner_name'];
    $owner_id = $_POST['owner_id'];
    $contact = $_POST['contact_number'];
    $type = $_POST['vehicle_type'];

    $stmt = $conn->prepare("
        UPDATE vehicles 
        SET vehicle_number=?, owner_name=?, owner_id=?, contact_number=?, vehicle_type=?
        WHERE id=?
    ");
    $stmt->bind_param("sssssi", $vehicle_number, $owner_name, $owner_id, $contact, $type, $id);
    $stmt->execute();

    header("Location: view_vehicles.php");
    exit();
}

$result = $conn->query("SELECT * FROM vehicles WHERE id=$id");
$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Vehicle</title>
    <link rel="stylesheet" href="edit_vehicle.css">
    <script src="assets/js/reload_on_nav.js"></script>
</head>
<body>

<div class="login-container">
    <div class="form-box">
        <h2>Edit Vehicle</h2>

        <form method="post">
            <label>Vehicle Number</label>
            <input type="text" name="vehicle_number"
                   value="<?= htmlspecialchars($row['vehicle_number']) ?>" required>

            <label>Owner Name</label>
            <input type="text" name="owner_name"
                   value="<?= htmlspecialchars($row['owner_name']) ?>" required>

            <label>Owner ID</label>
            <input type="text" name="owner_id"
                   value="<?= htmlspecialchars($row['owner_id']) ?>" required>

            <label>Contact Number</label>
            <input type="text" name="contact_number"
                   value="<?= htmlspecialchars($row['contact_number']) ?>" required>

            <label>Vehicle Type</label>
            <input type="text" name="vehicle_type"
                   value="<?= htmlspecialchars($row['vehicle_type']) ?>" required>

            <div class="btn-group">
                <button type="submit" class="btn save-btn">Update</button>
                <a href="view_vehicles.php" class="btn cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>