<?php
// auth.php
//* functions:
// signup
// login (with throttling)
// logout

session_start();
require '../db_connect.php';

//* Initialize login throttling
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_login_attempt'])) {
    $_SESSION['last_login_attempt'] = 0;
}

$lockout_time = 10; // seconds
$max_attempts = 5;

// Calculate time since last attempt
$timeSinceLastAttempt = time() - $_SESSION['last_login_attempt']; // seconds since last attempt. current time in seconds minus last attempt time in seconds (e.g., 1697049600 - 1697049590 = 10 seconds)

// check if still locked out
$stillLockedOut = $_SESSION['login_attempts'] >= $max_attempts && $timeSinceLastAttempt < $lockout_time; // if attempts exceed max and within lockout time

// If lockout expired, reset attempts
if ($timeSinceLastAttempt >= $lockout_time) {
    $_SESSION['login_attempts'] = 0;
}

// Enforce lockout
if ($stillLockedOut) {
    $remaining = $lockout_time - $timeSinceLastAttempt;
    $_SESSION['loginStatus'] = [
        'message' => "Too many failed attempts. Try again in $remaining seconds.",
        'type' => 'error'
    ];
    header("Location: ../controllers/index.php?action=login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // if post request

    //* SIGNUP
    if (isset($_POST['signUp'])) { // if sign up button clicked
        $username = trim($_POST['signup_username']);
        $password = $_POST['signup_password'];
        $confirm_password = $_POST['signup_confirm_password'];
        $firstName = trim($_POST['firstName']);
        $lastName = trim($_POST['lastName']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        $errors = ['username' => '', 'email' => '', 'phone' => '']; // associative array to hold validation errors

        if ($password !== $confirm_password)
            $errors['username'] = 'Passwords do not match.'; // password input mismatch
        elseif (!preg_match("/^[a-zA-Z0-9_]{3,50}$/", $username))
            $errors['username'] = 'Invalid username.'; // username format error
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Invalid email.'; // email format error
        elseif (!preg_match("/^[0-9+\- ]{7,20}$/", $phone))
            $errors['phone'] = 'Invalid phone.'; // phone format error
        else { // check uniqueness
            $stmt = $UserDBConnect->prepare("SELECT username, email, phone FROM Users WHERE username=? OR email=? OR phone=?"); // get existing users with same username/email/phone
            $stmt->bind_param("sss", $username, $email, $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            // if any existing user found, set errors
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) { // check for account data that are already present in database
                    if ($row['username'] === $username)
                        $errors['username'] = "Username already exists!"; // set username error in errors associative array
                    if ($row['email'] === $email)
                        $errors['email'] = "Email already exists!";
                    if ($row['phone'] === $phone)
                        $errors['phone'] = "Phone number already exists!";
                }
            } else { // no duplicates, proceed to insert
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $UserDBConnect->prepare(
                    "INSERT INTO Users (username, password, firstName, lastName, email, phone) VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("ssssss", $username, $hashedPassword, $firstName, $lastName, $email, $phone);
                if ($stmt->execute()) {
                    $_SESSION['signupStatus'] = ['message' => "Signup successful! You can now log in.", 'type' => 'success'];
                } else {
                    $_SESSION['signupStatus'] = ['message' => "Error signing up.", 'type' => 'error'];
                }
            }
            $stmt->close();
        }

        // If validation errors exist, store them in session
        $_SESSION['usernameError'] = $errors['username'];
        $_SESSION['emailError'] = $errors['email'];
        $_SESSION['phoneNumError'] = $errors['phone'];

        header("Location: ../controllers/index.php?action=login");
        exit;
    }

    //* LOGOUT
    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: ../controllers/index.php?action=login");
        exit;
    }


    //* LOGIN
    if (isset($_POST['login'])) {
        $username = trim($_POST['login_username']);
        $password = $_POST['login_password'];

        $stmt = $UserDBConnect->prepare("SELECT password FROM Users WHERE username=?"); // fetch hashed password for given username
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        // Verify credentials
        if ($row = $result->fetch_assoc()) { // username found
            if (password_verify($password, $row['password'])) { // successful login
                $_SESSION['username'] = $username; // save username of logged in user in session
                $_SESSION['login_attempts'] = 0; //* reset login attempts on successful login for login throttling
                // redirect to central index.php to load home page
                header("Location: ../controllers/index.php?action=home");
                exit;
            } else { // invalid password
                $_SESSION['login_attempts']++; //* increment login attempts for login throttling
                $_SESSION['last_login_attempt'] = time(); // update last attempt time
                $_SESSION['loginStatus'] = ['message' => "Invalid password.", 'type' => 'error'];
            }
        } else { // invalid username
            $_SESSION['login_attempts']++; //* increment login attempts for login throttling
            $_SESSION['last_login_attempt'] = time(); // update last attempt time
            $_SESSION['loginStatus'] = ['message' => "Invalid username.", 'type' => 'error'];
        }
        $stmt->close();
        header("Location: ../controllers/index.php?action=login");
        exit;
    }

}
?>