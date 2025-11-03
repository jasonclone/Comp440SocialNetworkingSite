<?php
session_start();

// redirect if already logged in
if (isset($_SESSION['username'])) {
    header("Location: blog.php");
    exit;
}

// Retrieve messages from session
$signupStatus = $_SESSION['signupStatus'] ?? null;
$usernameError = $_SESSION['usernameError'] ?? '';
$emailError = $_SESSION['emailError'] ?? '';
$phoneNumError = $_SESSION['phoneNumError'] ?? '';
$loginStatus = $_SESSION['loginStatus'] ?? null;

unset($_SESSION['signupStatus'], $_SESSION['usernameError'], $_SESSION['emailError'], $_SESSION['phoneNumError'], $_SESSION['loginStatus']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login & Signup</title>
<style>
.error { color: red; margin: 5px 0; }
.success { color: green; margin: 5px 0; }
</style>
</head>
<body>
<div class="container">
    <h2><center>Login Page</center></h2>

    <h2>Sign Up</h2>
    <?php if ($signupStatus): ?>
        <p class="<?php echo htmlspecialchars($signupStatus['type']); ?>">
            <?php echo htmlspecialchars($signupStatus['message']); ?>
        </p>
    <?php endif; ?>
    <form action="../php/login.php" method="post">
        Username: <input type="text" name="signup_username" required><br>
        <div class="error"><?php echo htmlspecialchars($usernameError); ?></div>

        Password: <input type="password" name="signup_password" required><br>
        Confirm Password: <input type="password" name="signup_confirm_password" required><br>

        First Name: <input type="text" name="firstName" required><br>
        Last Name: <input type="text" name="lastName" required><br>

        Email: <input type="email" name="email" required><br>
        <div class="error"><?php echo htmlspecialchars($emailError); ?></div>

        Phone: <input type="text" name="phone" required><br>
        <div class="error"><?php echo htmlspecialchars($phoneNumError); ?></div>

        <input type="submit" name="signUp" value="Sign Up"><br><br>
    </form>

    <h2>Login</h2>
    <form action="../php/login.php" method="post">
        Username: <input type="text" name="login_username" required><br>
        Password: <input type="password" name="login_password" required><br>
        <input type="submit" name="login" value="Login"><br>
        <?php if ($loginStatus): ?>
            <div class="<?php echo htmlspecialchars($loginStatus['type']); ?>">
                <?php echo htmlspecialchars($loginStatus['message']); ?>
            </div>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
