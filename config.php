<?php

$host = 'localhost';
$user = 'root';
$password = "";
$database = "users_db";

$app_url = 'http://localhost/SmartPark';


$smtp_host = '';
$smtp_port = 587; 
$smtp_user = '';
$smtp_pass = '';
$smtp_secure = 'tls'; 
$smtp_from_email = 'no-reply@localhost';
$smtp_from_name = 'Parking Admin';

$conn = new mysqli($host, $user, $password, $database); 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>