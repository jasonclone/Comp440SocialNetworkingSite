<?php
// controllers/user.php
//* functions:
// follow/unfollow user
// post comment on blog

session_start();
require '../db_connect.php';

if (!isset($_SESSION['username'])) { // not logged in
    header("Location: ../controllers/index.php?action=login");
    exit;
}

$username = $_SESSION['username']; // logged-in user

// Check if this is a POST request to follow a user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // follow user
    if (isset($_POST['follow_user'])) {
        $followUser = $_POST['follow_user'];

        // Prevent following self
        if ($followUser !== $username) {
            // Check if already following
            $stmt = $UserDBConnect->prepare("
            SELECT 1
            FROM Followers
            WHERE follower_username = ? AND followed_username = ?
        ");
            $stmt->bind_param("ss", $username, $followUser);
            $stmt->execute();
            $result = $stmt->get_result();
            $alreadyFollowing = ($result->num_rows > 0);
            $stmt->close();

            if (!$alreadyFollowing) {
                // Insert new follower
                $stmt = $UserDBConnect->prepare("
                INSERT INTO Followers (follower_username, followed_username)
                VALUES (?, ?)
            ");
                $stmt->bind_param("ss", $username, $followUser);
                $stmt->execute();
                $stmt->close();
            }
        }

        // After follow, redirect to the profile page of that user through user.php because profile.php requires session data
        header("Location: ../controllers/index.php?action=profile&user=" . urlencode($followUser));
        exit;

    }

    //* UNFOLLOW USER
    if (isset($_POST['unfollow_user'])) {
        $targetUser = $_POST['unfollow_user'];
        if ($targetUser !== $username) {
            $stmt = $UserDBConnect->prepare("
                DELETE FROM Followers
                WHERE follower_username = ? AND followed_username = ?
            ");
            $stmt->bind_param("ss", $username, $targetUser);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: ../controllers/index.php?action=profile&user=" . urlencode($targetUser));
        exit;

    }

    //* POST COMMENT
    if (isset($_POST['comment_blog'])) {
        $blog_id = (int) $_POST['blog_id']; // get blog id being commented on from hidden input
        $sentiment = $_POST['sentiment'] ?? ''; // get sentiment
        $comment_desc = trim($_POST['comment_description'] ?? ''); // get comment description

        // Basic PHP checks for UX
        // 1) Can't comment on own blog
        $stmt = $UserDBConnect->prepare("SELECT username FROM Blogs WHERE blog_id = ?"); // get blog owner
        $stmt->bind_param("i", $blog_id);
        $stmt->execute();
        $stmt->bind_result($owner); // get owner username

        if (!$stmt->fetch()) { // blog not found
            $stmt->close();
            $_SESSION['comment_submit_status'] = [
                'message' => "Blog does not exist. Cannot comment.",
                'type' => 'error'
            ];
        } else { // blog found
            $stmt->close();
            if ($owner === $username) { // can't comment on own blog (username from session)
                $_SESSION['comment_submit_status'] = [
                    'message' => "You cannot comment on your own blog.",
                    'type' => 'error'
                ];
            } else { // not own blog, proceed to other checks
                // check if already commented on this blog (one comment from user per blog)
                $stmt = $UserDBConnect->prepare("SELECT COUNT(*) FROM Comments WHERE blog_id = ? AND commenter = ?"); // get count of comments by user on this blog
                $stmt->bind_param("is", $blog_id, $username);
                $stmt->execute();
                $stmt->bind_result($already_commented); // get count of comments by user on this blog
                $stmt->fetch();
                $stmt->close();

                // count today's comments
                $stmt = $UserDBConnect->prepare("SELECT COUNT(*) FROM Comments WHERE commenter = ? AND DATE(created_at) = CURDATE()");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->bind_result($comment_count); // get total comments user has done today
                $stmt->fetch();
                $stmt->close();

                if ($already_commented > 0) { // if already commented on this blog
                    $_SESSION['comment_submit_status'] = [
                        'message' => "You already commented on this blog.",
                        'type' => 'error'
                    ];
                } elseif ($comment_count >= 3) {
                    // Get the most recent comment today by this user
                    $stmt = $UserDBConnect->prepare("
                    SELECT created_at
                    FROM Comments
                    WHERE commenter = ? AND DATE(created_at) = CURDATE()
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $stmt->bind_result($latest_comment_time_str);
                    $stmt->fetch();
                    $stmt->close();

                    if ($latest_comment_time_str) {
                        $pacific = new DateTimeZone('America/Los_Angeles');

                        // Parse the latest comment time
                        $latest_comment_time = DateTime::createFromFormat('Y-m-d H:i:s', $latest_comment_time_str, $pacific);

                        // Limit is 24 hours after the latest comment
                        $limit_time = clone $latest_comment_time;
                        $limit_time->modify('+24 hours');

                        $now = new DateTime('now', $pacific);

                        if ($now < $limit_time) {
                            $diff = $now->diff($limit_time);
                            $_SESSION['comment_submit_status'] = [
                                'message' => "Daily comment limit reached. You can post again in {$diff->h}h {$diff->i}m {$diff->s}s.",
                                'type' => 'error'
                            ];
                            header("Location: ../controllers/index.php?action=home");
                            exit;
                        }
                    }

                } else {
                    // Insert comment (triggers will also check and will raise an exception on violation)
                    try {
                        $stmt = $UserDBConnect->prepare("INSERT INTO Comments (blog_id, commenter, sentiment, description) VALUES (?, ?, ?, ?)"); // insert new comment
                        $stmt->bind_param("isss", $blog_id, $username, $sentiment, $comment_desc); // only username from session, rest from form
                        $stmt->execute();
                        $stmt->close();
                        $_SESSION['comment_submit_status'] = [
                            'message' => "Comment added successfully!",
                            'type' => 'success'
                        ];
                    } catch (mysqli_sql_exception $e) {
                        $_SESSION['comment_submit_status'] = [
                            'message' => "Error adding comment: " . htmlspecialchars($e->getMessage()),
                            'type' => 'error'
                        ];
                        error_log($comment_submit_status);
                    }
                }
            }
        }
        // refresh to render results
        header("Location: ../controllers/index.php?action=home");
        exit;
    }
}


