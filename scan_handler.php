<?php
require 'config.php';

header('Content-Type: application/json');

$code = trim($_REQUEST['code'] ?? '');
if (!$code) {
    echo json_encode(['success' => false, 'message' => 'Missing code parameter']);
    exit;
}

if (strpos($code, ':') === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid code format']);
    exit;
}

list($id_raw, $sig) = explode(':', $code, 2);
$entityType = null;
$id = 0;
if (preg_match('/^V(\d+)$/i', $id_raw, $m)) {
    $entityType = 'vehicle';
    $id = intval($m[1]);
} elseif (preg_match('/^G(\d+)$/i', $id_raw, $m)) {
    $entityType = 'guest';
    $id = intval($m[1]);
} elseif (ctype_digit($id_raw)) {
    $entityType = 'vehicle';
    $id = intval($id_raw);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid id format']);
    exit;
}

if ($id <= 0 || !$sig) {
    echo json_encode(['success' => false, 'message' => 'Invalid id or signature']);
    exit;
}

if ($entityType === 'vehicle') {
    $expected = hash_hmac('sha256', (string)$id, $qr_secret);
} else {
    $expected = hash_hmac('sha256', 'G' . (string)$id, $qr_secret);
}
if (!hash_equals($expected, $sig)) {
    echo json_encode(['success' => false, 'message' => 'Signature verification failed']);
    exit;
}

// fetch entity
if ($entityType === 'vehicle') {
    $stmt = $conn->prepare("SELECT id, owner_name FROM vehicles WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $entity = $res->fetch_assoc();
    if (!$entity) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
        exit;
    }
} else {
    $stmt = $conn->prepare("SELECT id, owner_name, plate_number, qr_image FROM guests WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $entity = $res->fetch_assoc();
    if (!$entity) {
        echo json_encode(['success' => false, 'message' => 'Guest not found']);
        exit;
    }
}

$conn->begin_transaction();
try {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $guardId = null;

    if ($entityType === 'vehicle') {
        $sel = $conn->prepare("SELECT id, in_time FROM parking_sessions WHERE vehicle_id = ? AND out_time IS NULL ORDER BY in_time DESC LIMIT 1");
        $sel->bind_param('i', $id);
        $sel->execute();
        $r = $sel->get_result();
        $open = $r->fetch_assoc();

        if ($open) {
            $upd = $conn->prepare("UPDATE parking_sessions SET out_time = NOW(), guard_id = ?, ip = ? WHERE id = ?");
            $upd->bind_param('isi', $guardId, $remoteIp, $open['id']);
            $upd->execute();

            // Insert into parking_logs, include contact/description if columns exist
            $hasContact = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'contact_number'")->num_rows > 0);
            $hasDesc = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'vehicle_description'")->num_rows > 0);
            $hasPlate = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'plate_number'")->num_rows > 0);

            if ($hasContact || $hasDesc || $hasPlate) {
                $cols = "vehicle_id, owner_name, action";
                $vals = "?, ?, 'OUT'";
                $params = ['is', $id, $entity['owner_name']];
                if ($hasContact) { $cols .= ", contact_number"; $vals .= ", ?"; $params[] = $entity['contact_number'] ?? null; $params[0] .= 's'; }
                if ($hasDesc)    { $cols .= ", vehicle_description"; $vals .= ", ?"; $params[] = $entity['vehicle_description'] ?? null; $params[0] .= 's'; }
                if ($hasPlate)   { $cols .= ", plate_number"; $vals .= ", ?"; $params[] = $entity['vehicle_number'] ?? null; $params[0] .= 's'; }

                $sql = "INSERT INTO parking_logs ($cols, scanned_at) VALUES ($vals, NOW())";
                $insLog = $conn->prepare($sql);
                // bind dynamically
                $types = array_shift($params);
                $bindParams = [];
                foreach ($params as $p) $bindParams[] = $p;
                $insLog->bind_param($types, ...$bindParams);
                $insLog->execute();
            } else {
                $insLog = $conn->prepare("INSERT INTO parking_logs (vehicle_id, owner_name, action, scanned_at) VALUES (?, ?, 'OUT', NOW())");
                $insLog->bind_param('is', $id, $entity['owner_name']);
                $insLog->execute();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'action' => 'OUT', 'message' => 'Checked OUT', 'vehicle_id' => $id]);
            exit;
        } else {
            $ins = $conn->prepare("INSERT INTO parking_sessions (vehicle_id, in_time, guard_id, ip) VALUES (?, NOW(), ?, ?)");
            $ins->bind_param('iis', $id, $guardId, $remoteIp);
            $ins->execute();

            // Insert IN log with optional contact/description/plate
            $hasContact = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'contact_number'")->num_rows > 0);
            $hasDesc = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'vehicle_description'")->num_rows > 0);
            $hasPlate = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'plate_number'")->num_rows > 0);

            if ($hasContact || $hasDesc || $hasPlate) {
                $cols = "vehicle_id, owner_name, action";
                $vals = "?, ?, 'IN'";
                $params = ['is', $id, $entity['owner_name']];
                if ($hasContact) { $cols .= ", contact_number"; $vals .= ", ?"; $params[] = $entity['contact_number'] ?? null; $params[0] .= 's'; }
                if ($hasDesc)    { $cols .= ", vehicle_description"; $vals .= ", ?"; $params[] = $entity['vehicle_description'] ?? null; $params[0] .= 's'; }
                if ($hasPlate)   { $cols .= ", plate_number"; $vals .= ", ?"; $params[] = $entity['vehicle_number'] ?? null; $params[0] .= 's'; }

                $sql = "INSERT INTO parking_logs ($cols, scanned_at) VALUES ($vals, NOW())";
                $insLog = $conn->prepare($sql);
                $types = array_shift($params);
                $insLog->bind_param($types, ...array_slice($params,0));
                $insLog->execute();
            } else {
                $insLog = $conn->prepare("INSERT INTO parking_logs (vehicle_id, owner_name, action, scanned_at) VALUES (?, ?, 'IN', NOW())");
                $insLog->bind_param('is', $id, $entity['owner_name']);
                $insLog->execute();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'action' => 'IN', 'message' => 'Checked IN', 'vehicle_id' => $id]);
            exit;
        }
    } else {
        // guest: toggle based on last parking_logs entry for owner_name
        $owner = $entity['owner_name'];
        $logSel = $conn->prepare("SELECT action FROM parking_logs WHERE owner_name = ? ORDER BY scanned_at DESC LIMIT 1");
        $logSel->bind_param('s', $owner);
        $logSel->execute();
        $lr = $logSel->get_result();
        $lastAction = $lr && $lr->num_rows ? $lr->fetch_assoc()['action'] : null;

        $action = ($lastAction === 'IN') ? 'OUT' : 'IN';

        // Insert log for guest, include contact/description/plate when parking_logs has those columns
        $hasContact = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'contact_number'")->num_rows > 0);
        $hasDesc = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'vehicle_description'")->num_rows > 0);
        $hasPlate = ($conn->query("SHOW COLUMNS FROM parking_logs LIKE 'plate_number'")->num_rows > 0);

        if ($hasContact || $hasDesc || $hasPlate) {
            $cols = "vehicle_id, owner_name, action";
            $vals = "NULL, ?, ?"; // vehicle_id literal NULL, owner_name, action
            $params = ['ss', $owner, $action];
            if ($hasContact) { $cols .= ", contact_number"; $vals .= ", ?"; $params[] = $entity['contact_number'] ?? null; $params[0] .= 's'; }
            if ($hasDesc)    { $cols .= ", vehicle_description"; $vals .= ", ?"; $params[] = $entity['vehicle_description'] ?? null; $params[0] .= 's'; }
            if ($hasPlate)   { $cols .= ", plate_number"; $vals .= ", ?"; $params[] = $entity['plate_number'] ?? null; $params[0] .= 's'; }

            $sql = "INSERT INTO parking_logs ($cols, scanned_at) VALUES ($vals, NOW())";
            $insLog = $conn->prepare($sql);
            $types = array_shift($params);
            $bindParams = [];
            foreach ($params as $p) $bindParams[] = $p;
            $insLog->bind_param($types, ...$bindParams);
            $insLog->execute();
        } else {
            $insLog = $conn->prepare("INSERT INTO parking_logs (vehicle_id, owner_name, action, scanned_at) VALUES (NULL, ?, ?, NOW())");
            $insLog->bind_param('ss', $owner, $action);
            $insLog->execute();
        }

        if ($action === 'OUT') {
            // remove guest record and QR image file if present
            $qrImage = $entity['qr_image'] ?? '';
            $del = $conn->prepare("DELETE FROM guests WHERE id = ?");
            $del->bind_param('i', $id);
            $del->execute();
            // attempt to unlink image file from disk (non-fatal)
            if (!empty($qrImage)) {
                $path = __DIR__ . DIRECTORY_SEPARATOR . $qrImage;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'action' => $action, 'message' => 'Checked ' . $action, 'guest_id' => $id]);
        exit;
    }

} catch (Exception $ex) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $ex->getMessage()]);
    exit;
}

?>
