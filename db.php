<?php
// Prevent caching globally for all PHP pages that include this file
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$conn = new mysqli("localhost", "root", "", "users_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
