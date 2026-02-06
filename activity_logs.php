<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require 'db.php';

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$type = $_GET['type'] ?? '';

$where = [];

if (!empty($from)) {
    $where[] = "DATE(scanned_at) >= '$from'";
}
if (!empty($to)) {
    $where[] = "DATE(scanned_at) <= '$to'";
}
if (!empty($type)) {
    $where[] = "action = '$type'";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch logs for vehicles (students/employees) — treat vehicle_id > 0 as vehicle logs
$vehicleSql = "
    SELECT owner_name, action, scanned_at
    FROM parking_logs
    WHERE COALESCE(vehicle_id, 0) > 0 ". ($where ? ' AND '.implode(' AND ', $where) : '') ."
    ORDER BY scanned_at DESC
";
$vehicleResult = $conn->query($vehicleSql);

// Fetch logs for guests (vehicle_id NULL or 0)
$guestSql = "
    SELECT owner_name, action, scanned_at
    FROM parking_logs
    WHERE COALESCE(vehicle_id, 0) = 0 ". ($where ? ' AND '.implode(' AND ', $where) : '') ."
    ORDER BY scanned_at DESC
";
$guestResult = $conn->query($guestSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parking Activity Logs</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="assets/js/reload_on_nav.js"></script>
</head>
<body>

<div class="dashboard-wrapper">

    <aside class="sidebar">
        <div class="logo-container">Admin Panel</div>
        <nav class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="register.php">Register</a>
            <a href="view_vehicles.php">Registered</a>
            <a href="activity_logs.php" class="active">Activity Logs</a>
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <header class="main-header">
            <h1>Parking Activity Logs</h1>
        </header>

        <a href="dashboard.php" class="back-btn">
            <svg viewBox="0 0 24 24"><path d="M15.5 19l-7-7 7-7"/></svg>
            Back</a>

        <form method="GET" class="filter-form">
            <label>
                From Date
                <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
            </label>

            <label>
                To Date
                <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
            </label>

            <label>
                Status
                <select name="type">
                    <option value="">All</option>
                    <option value="IN" <?= $type === 'IN' ? 'selected' : '' ?>>IN</option>
                    <option value="OUT" <?= $type === 'OUT' ? 'selected' : '' ?>>OUT</option>
                </select>
            </label>

            <button type="submit">Apply</button>
            <a href="activity_logs.php" class="reset-btn">Reset</a>
        </form>

        <div class="table-container">
            <h2 class="table-title">Student Parking Logs</h2>
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Owner Name</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($vehicleResult->num_rows === 0): ?>
                        <tr>
                            <td colspan="4">No records found</td>
                        </tr>
                    <?php else: ?>
                        <?php $count = 1; ?>
                        <?php while ($row = $vehicleResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= $count++ ?></td>
                                <td><?= htmlspecialchars($row['owner_name']) ?></td>
                                <td class="<?= $row['action'] === 'IN' ? 'in' : 'out' ?>">
                                    <?= $row['action'] ?>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($row['scanned_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container mt-32">
            <h2 class="table-title">Guest Parking Logs</h2>
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Owner Name</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($guestResult->num_rows === 0): ?>
                        <tr>
                            <td colspan="4">No records found</td>
                        </tr>
                    <?php else: ?>
                        <?php $gcount = 1; ?>
                        <?php while ($row = $guestResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= $gcount++ ?></td>
                                <td><?= htmlspecialchars($row['owner_name']) ?></td>
                                <td class="<?= $row['action'] === 'IN' ? 'in' : 'out' ?>">
                                    <?= $row['action'] ?>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($row['scanned_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

</body>
</html>
