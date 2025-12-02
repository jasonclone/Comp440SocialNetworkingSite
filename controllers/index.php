<?php
// controllers/index.php
// renders data to different views before any post/get requests are done by the user
//* functions:
// render profile data to profile.php


session_start();
require '../db_connect.php';

// go to login page directly if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../views/login.php");
    exit;
}

$username = $_SESSION['username'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    //* controller for profile page rendering
    if (($_GET['action'] ?? '') === 'profile') {

        $profileUser = $_GET['user'] ?? null;

        if (!$profileUser) {
            header("Location: ../controllers/index.php?action=home");
            exit;
        }

        // ------- GET FOLLOWERS COUNT -------
        $stmt = $UserDBConnect->prepare("
        SELECT COUNT(*) 
        FROM Followers 
        WHERE followed_username = ?
    ");
        $stmt->bind_param("s", $profileUser);
        $stmt->execute();
        $stmt->bind_result($followersCount);
        $stmt->fetch();
        $stmt->close();

        // ------- GET FOLLOWING COUNT -------
        $stmt = $UserDBConnect->prepare("
        SELECT COUNT(*)
        FROM Followers
        WHERE follower_username = ?
    ");
        $stmt->bind_param("s", $profileUser);
        $stmt->execute();
        $stmt->bind_result($followingCount);
        $stmt->fetch();
        $stmt->close();

        // ------- check if username is already following the user profile visited -------
        $isFollowing = false;
        $userData = [];
        if ($username !== $profileUser) {
            $stmt = $UserDBConnect->prepare("
            SELECT 1
            FROM Followers
            WHERE follower_username = ? AND followed_username = ?
        ");
            $stmt->bind_param("ss", $username, $profileUser);
            $stmt->execute();
            $result = $stmt->get_result();
            $isFollowing = ($result->num_rows > 0);
            $stmt->close();
        } else {
            $stmt = $UserDBConnect->prepare("
            SELECT username, firstName, lastName, email, phone, created_at
            FROM Users
            WHERE username = ?
        ");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            $stmt->close();
        }

        // Store in session for profile.php view
        $_SESSION['profile_data'] = [
            'user' => $profileUser,
            'followers' => $followersCount,
            'following' => $followingCount,
            'isFollowing' => $isFollowing,
            'user_data' => $userData
        ];

        // Redirect to profile view
        header("Location: ../views/profile.php");
        exit;
    }

    //* Home page controller
    if (($_GET['action'] ?? '') === 'home') {
        $search_tag = $_SESSION['search_tag'] ?? ""; // retain search tag if any

        // Clear previous search results
        if ($search_tag !== "") { // if search tag provided
            // Fetch blogs matching the search tag
            $stmt = $UserDBConnect->prepare("
        SELECT B.blog_id, B.username, B.subject, B.description, B.created_at,
               GROUP_CONCAT(DISTINCT T.tag) AS tags
        FROM Blogs B
        JOIN BlogTags BT_filter ON B.blog_id = BT_filter.blog_id
        JOIN Tags T_filter ON BT_filter.tag_id = T_filter.tag_id AND T_filter.tag = ?
        JOIN BlogTags BT ON B.blog_id = BT.blog_id
        JOIN Tags T ON BT.tag_id = T.tag_id
        GROUP BY B.blog_id
        ORDER BY B.created_at DESC
    ");
            $stmt->bind_param("s", $search_tag);
        } else { // if no search tag or search button clicked
            // Fetch all blogs
            $stmt = $UserDBConnect->prepare("
                    SELECT B.blog_id, B.username, B.subject, B.description, B.created_at,
                        GROUP_CONCAT(DISTINCT T.tag) AS tags
                    FROM Blogs B
                    LEFT JOIN BlogTags BT ON B.blog_id = BT.blog_id
                    LEFT JOIN Tags T ON BT.tag_id = T.tag_id
                    GROUP BY B.blog_id
                    ORDER BY B.created_at DESC
                ");
        }

        $stmt->execute();
        $result = $stmt->get_result();

        // Prepare comments query
        $stmtComments = $UserDBConnect->prepare("
                SELECT commenter, sentiment, description, created_at
                FROM Comments
                WHERE blog_id = ?
                ORDER BY created_at ASC
            ");

        while ($row = $result->fetch_assoc()) { // fetch blogs
            $row['tags'] = $row['tags'] ? explode(',', $row['tags']) : [];

            // Fetch comments for this blog
            $comments = [];
            $stmtComments->bind_param("i", $row['blog_id']);
            $stmtComments->execute();
            $resComments = $stmtComments->get_result();
            while ($c = $resComments->fetch_assoc()) {
                $comments[] = $c;
            }
            $row['comments'] = $comments;

            $search_results[] = $row;
        }

        $stmtComments->close();
        $stmt->close();

        $_SESSION['search_results'] = $search_results;
    }

    // Redirect to home view
    header("Location: ../views/home.php");
    exit;


    //* controller for login page rendering
    if (($_GET['action'] ?? '') === 'login') {
        header("Location: ../views/login.php");
        exit;
    }
}
