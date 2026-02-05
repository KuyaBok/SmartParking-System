<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Registration Type</title>
    <link rel="stylesheet" href="register.css">
    <link rel="stylesheet" href="assets/css/register_choice.css">
    <script src="assets/js/reload_on_nav.js"></script> 
</head>
<body>
        <a href="dashboard.php" class="back-btn">
            <svg viewBox="0 0 24 24"><path d="M15.5 19l-7-7 7-7"/></svg>
            Back
        </a>

<div class="login-container">
    <div class="form-box active choice-box">
        <h2>Select Registration Type</h2>

        <div class="choice-buttons">
            <a href="register.php" class="choice-btn">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 8 1.34 8 4v2H4v-2c0-2.66 5.3-4 8-4zm0-2a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
                Register Student
            </a>
            <a href="register_guest.php" class="choice-btn">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 8 1.34 8 4v2H4v-2c0-2.66 5.3-4 8-4zm0-2a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><circle cx="18" cy="6" r="3"/></svg>
                Register Guest
            </a>
        </div>
    </div>
</div>

</body>
</html>
