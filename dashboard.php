<?php
session_start();
date_default_timezone_set('America/Los_Angeles'); // Set timezone to Pacific (UTC-8)
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require 'db.php';

$totalRegistered = 0;
$totalSql = "SELECT COUNT(*) AS total FROM vehicles";
$totalResult = $conn->query($totalSql);
if ($totalResult && $row = $totalResult->fetch_assoc()) {
    $totalRegistered = $row['total'];
}
// Total registered guests (if guests table exists)
$totalGuests = 0;
$tbl = $conn->query("SHOW TABLES LIKE 'guests'");
if ($tbl && $tbl->num_rows > 0) {
    $gCountRes = $conn->query("SELECT COUNT(*) AS total FROM guests");
    if ($gCountRes && $gr = $gCountRes->fetch_assoc()) {
        $totalGuests = $gr['total'];
    }
}
$vehicleSql = "
    SELECT pl.owner_name, pl.action, pl.scanned_at, v.contact_number, v.vehicle_description
    FROM parking_logs pl
    LEFT JOIN vehicles v ON pl.vehicle_id = v.id
    WHERE COALESCE(pl.vehicle_id, 0) > 0
    ORDER BY pl.scanned_at DESC
    LIMIT 10
";
$vehicleResult = $conn->query($vehicleSql);
$vehicleActivities = [];
if ($vehicleResult) {
    while ($row = $vehicleResult->fetch_assoc()) {
        $vehicleActivities[] = $row;
    }
}
$guestSql = "
    SELECT pl.owner_name, pl.action, pl.scanned_at, g.contact_number, g.vehicle_description
    FROM parking_logs pl
    LEFT JOIN guests g ON pl.owner_name COLLATE utf8mb4_general_ci = g.owner_name COLLATE utf8mb4_general_ci
    WHERE COALESCE(pl.vehicle_id, 0) = 0
    ORDER BY pl.scanned_at DESC
    LIMIT 10
";
$guestResult = $conn->query($guestSql);
$guestActivities = [];
if ($guestResult) {
    while ($row = $guestResult->fetch_assoc()) {
        $guestActivities[] = $row;
    }
}

// Get currently timed-in vehicles (vehicles still parked) - only check at 9pm or later
$currentHour = date('H'); // Get current hour (0-23)
$timedInVehicles = [];

// Show alert from 9:01pm (hour >= 21) through 5:59am (hour < 6)
// After 6:00am, continue showing if vehicle is still timed in
if ($currentHour >= 21 || $currentHour < 6) {
    // Get the latest log entry for each owner, filtered to only IN status
    $timedInSql = "
        SELECT 
            pl.id,
            pl.vehicle_id,
            pl.owner_name,
            pl.scanned_at,
            pl.action
        FROM parking_logs pl
        WHERE pl.action = 'IN'
        AND pl.id = (
            SELECT MAX(id) FROM parking_logs WHERE owner_name = pl.owner_name COLLATE utf8mb4_general_ci
        )
        ORDER BY pl.scanned_at DESC
    ";

    $timedInResult = $conn->query($timedInSql);
    if ($timedInResult && $timedInResult->num_rows > 0) {
        while ($row = $timedInResult->fetch_assoc()) {
            // Get vehicle details separately to avoid collation issues
            $vehicle_id = $row['vehicle_id'] ?? 0;
            $owner_name = $row['owner_name'];
            
            $plate_number = 'N/A';
            $vehicle_type = 'Guest';
            $owner_email = '';
            $contact_number = 'N/A';
            
            $category_type = '';
            $vehicleExists = false;
            
            if ($vehicle_id > 0) {
                // Student/Employee vehicle
                $vehicle_type = 'Student/Employee';
                $vSql = "SELECT vehicle_number, owner_email, contact_number, vehicle_type FROM vehicles WHERE id = " . intval($vehicle_id);
                $vResult = $conn->query($vSql);
                if ($vResult && $vRow = $vResult->fetch_assoc()) {
                    $plate_number = $vRow['vehicle_number'];
                    $owner_email = $vRow['owner_email'];
                    $contact_number = $vRow['contact_number'];
                    $category_type = $vRow['vehicle_type'];
                    $vehicleExists = true;
                }
            } else {
                // Guest vehicle
                $gSql = "SELECT plate_number, owner_email, contact_number, vehicle_type FROM guests WHERE owner_name = '" . $conn->real_escape_string($owner_name) . "'";
                $gResult = $conn->query($gSql);
                if ($gResult && $gRow = $gResult->fetch_assoc()) {
                    $plate_number = $gRow['plate_number'];
                    $owner_email = $gRow['owner_email'];
                    $contact_number = $gRow['contact_number'];
                    $category_type = $gRow['vehicle_type'];
                    $vehicleExists = true;
                }
            }
            
            // Only add to alert if vehicle/owner still exists
            if ($vehicleExists) {
                $timedInVehicles[] = array(
                    'id' => $row['id'],
                    'vehicle_id' => $row['vehicle_id'],
                    'owner_name' => $row['owner_name'],
                    'scanned_at' => $row['scanned_at'],
                    'action' => $row['action'],
                    'vehicle_type' => $vehicle_type,
                    'plate_number' => $plate_number,
                    'owner_email' => $owner_email,
                    'contact_number' => $contact_number,
                    'category_type' => $category_type
                );
            }
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="assets/js/reload_on_nav.js"></script>
</head>
<body>

<div class="dashboard-wrapper">

    <aside class="sidebar">
        <div class="logo-container">Admin Panel</div>
        <nav class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="register_choice.php">Register</a>
            <a href="view_vehicles.php">Registered</a>
            <a href="activity_logs.php">Activity Logs</a>
            <a href="guard_scan.php">Guard Scan</a>
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <header class="main-header">
            <h1>Welcome, <span><?= htmlspecialchars($_SESSION['username']) ?></span></h1>
        </header>

        <?php if (!empty($timedInVehicles)): ?>
            <div class="alert-notification" style="background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin-bottom: 30px; margin-top: 20px;">
                <div style="display: flex; align-items: flex-start; gap: 15px;">
                    <div style="font-size: 28px; color: #ff9800;">⚠️</div>
                    <div style="flex: 1;">
                        <h2 style="margin: 0 0 15px 0; color: #856404; font-size: 20px;">
                            <?= count($timedInVehicles) ?> Vehicle<?= count($timedInVehicles) !== 1 ? 's' : '' ?> Still Parked Inside
                        </h2>
                        <p style="margin: 0 0 15px 0; color: #856404;">
                            The following vehicle<?= count($timedInVehicles) !== 1 ? 's are' : ' is' ?> still timed in the system. Please remind the owner<?= count($timedInVehicles) !== 1 ? 's' : '' ?> to check out.
                        </p>

                        <table style="width: 100%; border-collapse: collapse; background: white; margin-top: 15px;">
                            <thead>
                                <tr style="background-color: #f5f5f5; border-bottom: 2px solid #ffc107;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Owner Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Plate Number</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Category</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Vehicle Type</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Time In</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timedInVehicles as $vehicle): ?>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 12px; color: #333;"><?= htmlspecialchars($vehicle['owner_name']) ?></td>
                                        <td style="padding: 12px; color: #333; font-weight: 500;"><?= htmlspecialchars($vehicle['plate_number']) ?></td>
                                        <td style="padding: 12px; color: #666;">
                                            <span style="background-color: <?= $vehicle['vehicle_type'] === 'Student/Employee' ? '#d1ecf1' : '#d4edda' ?>; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                <?= htmlspecialchars($vehicle['vehicle_type']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; color: #333; text-transform: capitalize;"><?= htmlspecialchars($vehicle['category_type']) ?></td>
                                        <td style="padding: 12px; color: #333;"><?= date('M d, Y h:i A', strtotime($vehicle['scanned_at'])) ?></td>
                                        <td style="padding: 12px; color: #333;"><?= htmlspecialchars($vehicle['contact_number']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="content-grid">

            <div class="card">
                <h3>Total Registered Vehicles</h3>
                <p><?= number_format($totalRegistered) ?></p>
            </div>
            <div class="card">
                <h3>Total Registered Guests</h3>
                <p><?= number_format($totalGuests) ?></p>
            </div>
            <div class="full-width-card">
                <h3>Recent Parking Activity</h3>

                <div class="activity-table-wrapper">
                    <h4 class="section-title">Student/Employee</h4>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Owner</th>
                                <th>Contact</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vehicleActivities)): ?>
                                <tr>
                                    <td colspan="6">No recent activity</td>
                                </tr>
                            <?php else: ?>
                                <?php $vcount = 1; ?>
                                <?php foreach ($vehicleActivities as $log): ?>
                                    <tr>
                                        <td><?= $vcount++ ?></td>
                                        <td><?= htmlspecialchars($log['owner_name']) ?></td>
                                        <td><?= htmlspecialchars($log['contact_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['vehicle_description'] ?? 'N/A') ?></td>
                                        <td class="<?= $log['action'] === 'IN' ? 'in' : 'out' ?>">
                                            <?= $log['action'] ?>
                                        </td>
                                        <td><?= date('M d, Y h:i A', strtotime($log['scanned_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="activity-table-wrapper mt-24">
                    <h4 class="section-title">Guests</h4> 
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Owner</th>
                                <th>Contact</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guestActivities)): ?>
                                <tr>
                                    <td colspan="6">No recent activity</td>
                                </tr>
                            <?php else: ?>
                                <?php $gcount = 1; ?>
                                <?php foreach ($guestActivities as $log): ?>
                                    <tr>
                                        <td><?= $gcount++ ?></td>
                                        <td><?= htmlspecialchars($log['owner_name']) ?></td>
                                        <td><?= htmlspecialchars($log['contact_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['vehicle_description'] ?? 'N/A') ?></td>
                                        <td class="<?= $log['action'] === 'IN' ? 'in' : 'out' ?>">
                                            <?= $log['action'] ?>
                                        </td>
                                        <td><?= date('M d, Y h:i A', strtotime($log['scanned_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </main>
</div>

</body>
</html>
