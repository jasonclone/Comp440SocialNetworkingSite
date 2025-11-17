<?php
// views/profile.php
session_start();

if (!isset($_SESSION['username'])) { // not logged in
    header("Location: login.php");
    exit;
}

$viewer = $_SESSION['username'];

if (!isset($_SESSION['profile_data'])) { // profile data missing
    die("Profile data missing. profile.php must be accessed through controllers/user.php.");
}

$data = $_SESSION['profile_data']; // your own account data

// user you are viewing's profile info
$profileUser = $data['user'];
$followers = $data['followers'];
$following = $data['following'];
$isFollowing = $data['isFollowing'];
$userData = $data['user_data'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($profileUser); ?>'s Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .follow-btn {
            padding: 5px 10px;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <h2>
        <?php
        if ($profileUser === $viewer) {
            echo htmlspecialchars($profileUser) . "'s Profile (You)";
        } else {
            echo htmlspecialchars($profileUser) . "'s Profile";
        }
        ?>
    </h2>

    <p>Followers: <?php echo $followers; ?> | Following: <?php echo $following; ?></p>

    <div class="follow-section">
        <?php if ($profileUser === $viewer && !empty($userData)): ?>
            <!-- display account data -->
            <p>First Name: <?php echo htmlspecialchars($userData['firstName']); ?></p>
            <p>Last Name: <?php echo htmlspecialchars($userData['lastName']); ?></p>
            <p>Email: <?php echo htmlspecialchars($userData['email']); ?></p>
            <p>Phone: <?php echo htmlspecialchars($userData['phone']); ?></p>
            <p>Member since: <?php echo htmlspecialchars($userData['created_at']); ?></p>

        <?php elseif ($isFollowing): ?>
            <form action="../controllers/user.php" method="POST">
                <input type="hidden" name="unfollow_user" value="<?php echo htmlspecialchars($profileUser); ?>">
                <button type="submit">Unfollow</button>
            </form>

        <?php else: ?>
            <form action="../controllers/user.php" method="POST">
                <input type="hidden" name="follow_user" value="<?php echo htmlspecialchars($profileUser); ?>">
                <button type="submit">Follow</button>
            </form>
        <?php endif; ?>
    </div>

</body>

</html>