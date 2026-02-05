<?php
session_start();

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active-form'] ?? 'login';

session_unset();

function showForm($error){
    return !empty($error) ? "<p class='error-message'>$error</p>" : "";
}

function isActive($formName, $activeForm){
    return $formName === $activeForm ? 'active' : '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="Login Css.css">    <script src="assets/js/reload_on_nav.js"></script></head>
<body>
    <div class="login-container">
        <div class="form-box <?= isActive('login', $activeForm); ?>" id="login-form">

        <h2>Welcome Admin!</h2>
        
       <form action="process_login.php" method="post">
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required> 
    <button type="submit" name="login">Log In</button>
    <p>Register Admin Account <br>
        <a href="#" onclick="showForm('register-form')">Register</a>
    </p>
</form>
        </div>
        <div class="form-box <?= isActive('register', $activeForm); ?>" id="register-form">
        <form action="process_login.php" method="post">
    <h2>Register</h2>
    <?= showForm($errors['register']); ?>
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>

    <button type="submit" name="register">Register</button>
    <p>Already have an account? <br>
        <a href="#" onclick="showForm('login-form')">Login</a>
    </p>
</form>
    </div>
    </div>
    <script src="script.js"></script>
    
</body>
</html>