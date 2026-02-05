<?php
session_start();
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
$vehicleSql = "
    SELECT owner_name, action, scanned_at
    FROM parking_logs
    WHERE COALESCE(vehicle_id, 0) > 0
    ORDER BY scanned_at DESC
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
    SELECT owner_name, action, scanned_at
    FROM parking_logs
    WHERE COALESCE(vehicle_id, 0) = 0
    ORDER BY scanned_at DESC
    LIMIT 10
";
$guestResult = $conn->query($guestSql);
$guestActivities = [];
if ($guestResult) {
    while ($row = $guestResult->fetch_assoc()) {
        $guestActivities[] = $row;
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
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <header class="main-header">
            <h1>Welcome, <span><?= htmlspecialchars($_SESSION['username']) ?></span></h1>
        </header>

        <div class="content-grid">

            <div class="card">
                <h3>Total Registered Vehicles</h3>
                <p><?= number_format($totalRegistered) ?></p>
            </div>
            <div class="full-width-card">
                <h3>Recent Parking Activity</h3>

                <div class="activity-table-wrapper">
                    <h4 class="section-title">Student/Employee</h4>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vehicleActivities)): ?>
                                <tr>
                                    <td colspan="3">No recent activity</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vehicleActivities as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['owner_name']) ?></td>
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
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guestActivities)): ?>
                                <tr>
                                    <td colspan="3">No recent activity</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($guestActivities as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['owner_name']) ?></td>
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
