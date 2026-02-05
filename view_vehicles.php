<?php
session_start();
require 'db.php';

$result = $conn->query("SELECT * FROM vehicles ORDER BY created_at DESC");

// Check if 'guests' table exists before querying to avoid fatal errors
$tbl = $conn->query("SHOW TABLES LIKE 'guests'");
if ($tbl && $tbl->num_rows > 0) {
    $guests_result = $conn->query("SELECT * FROM guests ORDER BY created_at DESC");
} else {
    $guests_result = false; // table missing, treat as no guests
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Vehicles</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="assets/js/reload_on_nav.js"></script>
    <link rel="stylesheet" href="assets/css/view_vehicles.css">
</head>
<body>

<div class="main-container">
        <h2>Registered Vehicles</h2>

        <a href="dashboard.php" class="back-btn large">
            <svg viewBox="0 0 24 24"><path d="M15.5 19l-7-7 7-7"/></svg>
            Back
        </a>

        <div class="table-wrapper">
            
            <h3>Student Vehicles</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vehicle Number</th>
                        <th>Owner Name</th>
                        <th>Owner ID</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Date Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php $count = 1; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $count++; ?></td>
                            <td><?= htmlspecialchars($row['vehicle_number']); ?></td>
                            <td><?= htmlspecialchars($row['owner_name']); ?></td>
                            <td><?= htmlspecialchars($row['owner_id']); ?></td>
                            <td><?= htmlspecialchars($row['contact_number']); ?></td>
                            <td><?= htmlspecialchars($row['vehicle_type']); ?></td>
                            <td><?= htmlspecialchars($row['created_at']); ?></td>
                            <td class="actions">
                                <a href="edit_vehicle.php?id=<?= $row['id']; ?>" class="btn edit-btn"> Edit</a>
                                <?php if (empty($row['qr_image'])): ?>
                                    <a href="generate_qr.php?id=<?= $row['id']; ?>" class="btn qr-btn">Generate QR</a>
                                    <?php if (!empty($row['owner_email'])): ?>
                                        <a href="generate_qr.php?id=<?= $row['id']; ?>" class="btn success-btn">Generate QR &amp; Send</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($row['qr_image']); ?>" target="_blank" class="btn edit-btn">View QR</a>
                                    <?php if (!empty($row['owner_email'])): ?>
                                        <a href="generate_qr.php?id=<?= $row['id']; ?>" class="btn success-btn">Resend via Email</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="delete_vehicle.php?id=<?= $row['id']; ?>" class="btn delete-btn" onclick="return confirm('Are you sure you want to delete this vehicle?');">Remove</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No vehicles registered.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-wrapper">
            <h3>Guest Vehicles</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plate Number</th>
                        <th>Owner Name</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Date Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($guests_result && $guests_result->num_rows > 0): ?>
                    <?php $gcount = 1; ?>
                    <?php while ($guest = $guests_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $gcount++; ?></td>
                            <td><?= htmlspecialchars($guest['plate_number']); ?></td>
                            <td><?= htmlspecialchars($guest['owner_name']); ?></td>
                            <td><?= htmlspecialchars($guest['contact_number']); ?></td>
                            <td><?= htmlspecialchars($guest['vehicle_type']); ?></td>
                            <td><?= htmlspecialchars($guest['created_at']); ?></td>
                            <td class="actions">
                                <?php if (empty($guest['qr_image'])): ?>
                                    <a href="generate_qr.php?guest_id=<?= $guest['id']; ?>" class="btn qr-btn">Generate QR</a>
                                    <?php if (!empty($guest['owner_email'])): ?>
                                        <a href="generate_qr.php?guest_id=<?= $guest['id']; ?>" class="btn success-btn">Generate &amp; Send</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($guest['qr_image']); ?>" target="_blank" class="btn edit-btn">View QR</a>
                                    <?php if (!empty($guest['owner_email'])): ?>
                                        <a href="generate_qr.php?guest_id=<?= $guest['id']; ?>" class="btn success-btn">Resend via Email</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="delete_vehicle.php?guest_id=<?= $guest['id']; ?>" class="btn delete-btn" onclick="return confirm('Are you sure you want to delete this guest vehicle?');">Remove</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No guest vehicles registered.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
