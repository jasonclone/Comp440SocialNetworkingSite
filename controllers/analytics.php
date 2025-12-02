<?php
// controllers/analytics.php
session_start();
require '../db_connect.php';

// redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../controllers/index.php?action=login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    //* Dual-Tag Search
    if (isset($_POST['action']) && $_POST['action'] === 'dual_tag_search') {
        $tag1 = trim($_POST['tag1'] ?? '');
        $tag2 = trim($_POST['tag2'] ?? '');

        $_SESSION['dual_tag_searched'] = true; // mark that user searched

        if ($tag1 === '' || $tag2 === '') {
            $_SESSION['dual_tag_users'] = [];
        } else {
            $sql = "
            SELECT DISTINCT u.username
            FROM Users u
            JOIN Blogs b1 ON u.username = b1.username
            JOIN BlogTags bt1 ON b1.blog_id = bt1.blog_id
            JOIN Tags t1 ON bt1.tag_id = t1.tag_id
            JOIN Blogs b2 ON u.username = b2.username AND b1.blog_id != b2.blog_id AND DATE(b1.created_at) = DATE(b2.created_at)
            JOIN BlogTags bt2 ON b2.blog_id = bt2.blog_id
            JOIN Tags t2 ON bt2.tag_id = t2.tag_id
            WHERE t1.tag = ? AND t2.tag = ?
            ";
            $stmt = $UserDBConnect->prepare($sql);
            $stmt->bind_param("ss", $tag1, $tag2);
            $stmt->execute();
            $result = $stmt->get_result();

            $dual_tag_users = [];
            while ($row = $result->fetch_assoc()) {
                $dual_tag_users[] = $row;
            }
            $stmt->close();

            $_SESSION['dual_tag_users'] = $dual_tag_users;
        }

        header("Location: ../views/home.php");
        exit;
    }

    //* Most Blogs on Specific Date
    if (isset($_POST['action']) && $_POST['action'] === 'most_blogs_date') {
        $blog_date = trim($_POST['blog_date'] ?? '');
        $_SESSION['most_blogs_searched'] = true;

        if ($blog_date === '') {
            $_SESSION['most_blogs_users'] = [];
            $_SESSION['selected_date'] = $blog_date;
        } else {
            $sql = "
            SELECT u.username, COUNT(b.blog_id) AS blog_count
            FROM Users u
            JOIN Blogs b ON u.username = b.username
            WHERE DATE(b.created_at) = ?
            GROUP BY u.username
            ORDER BY blog_count DESC
            ";
            $stmt = $UserDBConnect->prepare($sql);
            $stmt->bind_param("s", $blog_date);
            $stmt->execute();
            $result = $stmt->get_result();

            $users = [];
            $max_count = 0;
            while ($row = $result->fetch_assoc()) {
                if ($max_count === 0)
                    $max_count = (int) $row['blog_count'];
                if ((int) $row['blog_count'] === $max_count)
                    $users[] = $row;
                else
                    break;
            }
            $stmt->close();

            $_SESSION['most_blogs_users'] = $users;
            $_SESSION['selected_date'] = $blog_date;
        }

        header("Location: ../views/home.php");
        exit;
    }

    //* Users Followed by Both X and Y
    if (isset($_POST['action']) && $_POST['action'] === 'followed_by_both') {
        $userX = trim($_POST['user_x'] ?? '');
        $userY = trim($_POST['user_y'] ?? '');
        $_SESSION['followed_by_both_searched'] = true;

        if ($userX === '' || $userY === '') {
            $_SESSION['followed_by_both'] = [];
            $_SESSION['user_x_input'] = $userX;
            $_SESSION['user_y_input'] = $userY;
        } else {
            $sql = "
            SELECT u.username
            FROM Users u
            WHERE u.username IN (
                SELECT f1.followed_username
                FROM Followers f1
                JOIN Followers f2 ON f1.followed_username = f2.followed_username
                WHERE f1.follower_username = ? AND f2.follower_username = ?
            )
            ";
            $stmt = $UserDBConnect->prepare($sql);
            $stmt->bind_param("ss", $userX, $userY);
            $stmt->execute();
            $result = $stmt->get_result();

            $followed_by_both = [];
            while ($row = $result->fetch_assoc()) {
                $followed_by_both[] = $row;
            }
            $stmt->close();

            $_SESSION['followed_by_both'] = $followed_by_both;
            $_SESSION['user_x_input'] = $userX;
            $_SESSION['user_y_input'] = $userY;
        }

        header("Location: ../views/home.php");
        exit;
    }

    //* Users Who Never Posted a Blog
    if (isset($_POST['action']) && $_POST['action'] === 'never_posted') {
        $_SESSION['never_posted_searched'] = true;

        $sql = "
        SELECT u.username
        FROM Users u
        LEFT JOIN Blogs b ON u.username = b.username
        WHERE b.blog_id IS NULL
        ";
        $result = $UserDBConnect->query($sql);

        $never_posted_users = [];
        while ($row = $result->fetch_assoc()) {
            $never_posted_users[] = $row;
        }

        $_SESSION['never_posted_users'] = $never_posted_users;

        header("Location: ../views/home.php");
        exit;
    }

    //* Blogs of User X with All Positive Comments
    if (isset($_POST['action']) && $_POST['action'] === 'all_positive_comments') {
        $userX = trim($_POST['user_x_all_positive'] ?? '');
        $_SESSION['all_positive_searched'] = true;

        if ($userX === '') {
            $_SESSION['all_positive_blogs'] = [];
            $_SESSION['all_positive_user'] = $userX;
        } else {
            $sql = "
        SELECT b.blog_id, b.subject, b.description, b.username, b.created_at,
               GROUP_CONCAT(DISTINCT t.tag ORDER BY t.tag ASC) AS tags
        FROM Blogs b
        LEFT JOIN BlogTags bt ON b.blog_id = bt.blog_id
        LEFT JOIN Tags t ON bt.tag_id = t.tag_id
        LEFT JOIN Comments c ON b.blog_id = c.blog_id
        WHERE b.username = ?
        GROUP BY b.blog_id
        HAVING COUNT(c.comment_id) > 0
           AND SUM(CASE WHEN c.sentiment != 'positive' THEN 1 ELSE 0 END) = 0
        ";
            $stmt = $UserDBConnect->prepare($sql);
            $stmt->bind_param("s", $userX);
            $stmt->execute();
            $result = $stmt->get_result();
            $all_positive_blogs = [];
            while ($row = $result->fetch_assoc()) {
                $row['tags'] = $row['tags'] ? explode(",", $row['tags']) : [];
                $all_positive_blogs[] = $row;
            }
            $stmt->close();
            $_SESSION['all_positive_blogs'] = $all_positive_blogs;
            $_SESSION['all_positive_user'] = $userX;
        }

        header("Location: ../views/home.php");
        exit;
    }


    //* Users Who Posted Only Negative Comments
    if (isset($_POST['action']) && $_POST['action'] === 'only_negative_comments') {
        $_SESSION['only_negative_searched'] = true;

        $sql = "
        SELECT u.username
        FROM Users u
        JOIN Comments c ON u.username = c.commenter
        GROUP BY u.username
        HAVING SUM(CASE WHEN c.sentiment != 'negative' THEN 1 ELSE 0 END) = 0
        ";
        $result = $UserDBConnect->query($sql);

        $only_negative_users = [];
        while ($row = $result->fetch_assoc()) {
            $only_negative_users[] = $row;
        }

        $_SESSION['only_negative_users'] = $only_negative_users;

        header("Location: ../views/home.php");
        exit;
    }

    //* Users Whose Blogs Never Received Negative Comments
    if (isset($_POST['action']) && $_POST['action'] === 'blogs_no_negative') {
        $_SESSION['blogs_no_negative_searched'] = true;

        $sql = "
        SELECT u.username
        FROM Users u
        JOIN Blogs b ON u.username = b.username
        LEFT JOIN Comments c ON b.blog_id = c.blog_id AND c.sentiment = 'negative'
        GROUP BY u.username
        HAVING COUNT(b.blog_id) > 0 AND COUNT(c.comment_id) = 0
        ";
        $result = $UserDBConnect->query($sql);

        $blogs_no_negative_users = [];
        while ($row = $result->fetch_assoc()) {
            $blogs_no_negative_users[] = $row;
        }

        $_SESSION['blogs_no_negative_users'] = $blogs_no_negative_users;

        header("Location: ../views/home.php");
        exit;
    }
}
?>