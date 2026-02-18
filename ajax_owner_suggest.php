<?php
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim($_GET['term'] ?? '');
$type = $_GET['type'] ?? 'all'; // 'all', 'student', 'guest'

if ($term === '') {
    echo json_encode([]);
    exit;
}

$term_esc = $conn->real_escape_string($term) . '%';
$results = [];

// Helper to fetch distinct owner names from a table
function fetch_names($conn, $table, $term_esc, &$results) {
    $sql = "SELECT DISTINCT owner_name FROM `" . $conn->real_escape_string($table) . "` WHERE owner_name LIKE '" . $term_esc . "' ORDER BY owner_name LIMIT 10";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $results[] = $r['owner_name'];
        }
    }
}

if ($type === 'student') {
    fetch_names($conn, 'vehicles', $term_esc, $results);
} elseif ($type === 'guest') {
    // Check guests table exists
    $tbl = $conn->query("SHOW TABLES LIKE 'guests'");
    if ($tbl && $tbl->num_rows > 0) {
        fetch_names($conn, 'guests', $term_esc, $results);
    }
} else {
    fetch_names($conn, 'vehicles', $term_esc, $results);
    // include guests if table exists
    $tbl = $conn->query("SHOW TABLES LIKE 'guests'");
    if ($tbl && $tbl->num_rows > 0) {
        fetch_names($conn, 'guests', $term_esc, $results);
    }
}

// Deduplicate and limit
$results = array_values(array_unique($results));
if (count($results) > 10) $results = array_slice($results, 0, 10);

echo json_encode($results);
