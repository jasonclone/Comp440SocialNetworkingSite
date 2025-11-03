<?php
// php/blog.php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['username'])) { // not logged in
    header("Location: login.php");
    exit;
}
$username = $_SESSION['username'];

// Helper: normalize tags string -> array of trimmed lowercase tags (unique)
/* Input examples and expected outputs:
"php, MySQL, security" → ["php","mysql","security"]
" tag , , TAG ,Other " → ["tag","other"]
"" or " , , " → []
"Café, CAFÉ" → ["café"]
*/
function normalize_tags_array($tags_string)
{
    $parts = preg_split('/[,]+/', $tags_string);
    $clean = [];
    foreach ($parts as $p) {
        $t = trim(mb_strtolower($p));
        if ($t !== '')
            $clean[$t] = true;
    }
    return array_keys($clean);
}

// POST A NEW BLOG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_blog'])) {

    // Retrieve and trim blog inputs
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $tags_raw = trim($_POST['tags']); //trim does whitespace removal from ends

    // PHP-side count check (good UX) - still triggers enforce in DB
    $stmt = $UserDBConnect->prepare("SELECT COUNT(*) FROM Blogs WHERE username = ? AND DATE(created_at) = CURDATE()"); //curdate() gets today's date only not exact time
    $stmt->bind_param("s", $username);
    $stmt->execute(); // execute the statement that gets the count of today's blogs by user
    $stmt->bind_result($blog_count); // move result into $blog_count
    $stmt->fetch();
    $stmt->close();

    if ($blog_count >= 2) { // limit reached

        // Get the 2 most recent blogs
        $stmt = $UserDBConnect->prepare("
        SELECT created_at
        FROM Blogs
        WHERE username = ?
        ORDER BY created_at DESC
        LIMIT 2
    ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        $blogs = [];
        while ($row = $result->fetch_assoc()) {
            $blogs[] = $row['created_at'];
        }
        $stmt->close();

        if (isset($blogs[1])) { // get time remaining until next allowed blog
            // Pacific TimeZone
            $pacific = new DateTimeZone('America/Los_Angeles'); // our timezone

            // Parse second most recent blog as Pacific time
            $second_blog_time = DateTime::createFromFormat('Y-m-d H:i:s', $blogs[1], $pacific);

            // Limit is 24 hours after second blog
            $limit_time = clone $second_blog_time;
            $limit_time->modify('+24 hours');

            // Current time in Pacific
            $now = new DateTime('now', $pacific);

            if ($now < $limit_time) {
                $diff = $now->diff($limit_time);
                $_SESSION['blog_submit_status'] = [
                    'message' => "Daily blog limit reached. You can post again in {$diff->h}h {$diff->i}m {$diff->s}s.",
                    'type' => 'error'
                ];
                header("Location: ../views/blog.php");
                exit;
            }
        }


    } else {
        // Insert blog row inside try/catch to capture trigger errors
        try {
            $UserDBConnect->begin_transaction(); // Begins a transaction: subsequent DB operations until commit or rollback are treated as one atomic unit; on failure calling rollback() undoes all changes made since begin_transaction().

            $stmt = $UserDBConnect->prepare("INSERT INTO Blogs (username, subject, description) VALUES (?, ?, ?)"); // insert new blog
            $stmt->bind_param("sss", $username, $subject, $description);
            $stmt->execute(); // schema trigger may raise error here if limit exceeded
            $blog_id = $stmt->insert_id; // get the auto-generated blog_id of the newly inserted blog
            $stmt->close();

            // process tags: create tags if needed and insert into BlogTags
            $tags = normalize_tags_array($tags_raw); // get array of unique normalized tags
            if (!empty($tags)) { // if there are tags
                // prepare statements once
                $selectTagStmt = $UserDBConnect->prepare("SELECT tag_id FROM Tags WHERE tag = ?"); // check if tag input from tags array exists in Tags table db (returns tag_id)
                $insertTagStmt = $UserDBConnect->prepare("INSERT INTO Tags (tag) VALUES (?)"); // insert new tag
                $insertBlogTagStmt = $UserDBConnect->prepare("INSERT INTO BlogTags (blog_id, tag_id) VALUES (?, ?)"); // insert blog-tag link (to ensure no duplicate tags per blog)

                foreach ($tags as $t) { // for each tag that user provided (parsed into array), check if tag already exists in db. if not, insert. then link blog to tag using BlogTags db table
                    // check if existing tag_id in Tags table db
                    $selectTagStmt->bind_param("s", $t); // bind tag from tags array
                    $selectTagStmt->execute();
                    $selectTagStmt->bind_result($tag_id); // get tag_id result in $tag_id
                    if ($selectTagStmt->fetch()) { // returns true if tag exists (tag id found in db table). false otherwise
                        $selectTagStmt->free_result();
                    } else {
                        // insert new tag
                        $selectTagStmt->free_result();
                        $insertTagStmt->bind_param("s", $t); // bind tag from tags array
                        $insertTagStmt->execute(); // insert new tag
                        $tag_id = $insertTagStmt->insert_id; // get new tag_id
                    }
                    // link blog to tag
                    $insertBlogTagStmt->bind_param("ii", $blog_id, $tag_id);

                    //
                    try {
                        $insertBlogTagStmt->execute();
                    } catch (mysqli_sql_exception $e) { // check if error caused by duplicate key or other - ignore linking duplicates but log it
                        if ($e->getCode() !== 1062) {
                            error_log("Unexpected error when inserting into BlogTags for blog_id $blog_id and tag_id $tag_id: " . $e->getMessage());
                            throw $e; // rethrow unexpected errors (non duplicate blog-tag error). execution will jump to outer catch
                        }
                        error_log("BlogTags duplicate detected for blog_id $blog_id and tag_id $tag_id. This duplicate pair wont be added to blogtags table, and blog posting code execution will continue: " . $e->getMessage());
                    }
                }
                $selectTagStmt->close();
                $insertTagStmt->close();
                $insertBlogTagStmt->close();
            }

            $UserDBConnect->commit(); // commit transaction
            $_SESSION['blog_submit_status'] = [
                'message' => "Blog posted successfully!",
                'type' => 'success'
            ];
        } catch (mysqli_sql_exception $e) {
            $UserDBConnect->rollback();
            // If the trigger raised a SIGNAL, it will become an SQL error with message we can show
            $_SESSION['blog_submit_status'] = [
                'message' => "Error posting blog: " . htmlspecialchars($e->getMessage()),
                'type' => 'error'
            ];
            error_log($blog_submit_status);
        }
    }

    // refresh to render results
    header("Location: ../views/blog.php");
    exit;
}

// SEARCH BLOGS BY TAG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_tag'])) {
    $tag_input = trim($_POST['tag']);
    if ($tag_input !== '') { // if tag input provided
        $tag_norm = mb_strtolower($tag_input); // normalize tag input to lowercase

        /*
        Query explanation:
        This query returns all blogs that have the searched tag, even if they have multiple tags.
         */
        $stmt = $UserDBConnect->prepare("
            SELECT B.blog_id, B.username, B.subject, B.description, B.created_at,
                   GROUP_CONCAT(DISTINCT T.tag) AS tags -- concatenate all tags for each blog into a single comma-separated string (e.g., aa,ab,ac); this is done after grouping by blog_id, so each blog row in the result has all its tags in one string.

            -- Blogs table gets joined with BlogTags, where each row is a blog id and one of its tags with the other metadata. There are multiple rows per blog if it has multiple tags.
            FROM Blogs B
            JOIN BlogTags BT ON B.blog_id = BT.blog_id
            JOIN Tags T ON BT.tag_id = T.tag_id

            -- outer query results like: WHERE B.blog_id IN (1,4,7), where 1,4,7 are blog IDs that have the searched tag
            WHERE B.blog_id IN (
                -- inner subquery to find blog_ids with the searched tag
                SELECT BT2.blog_id
                FROM BlogTags BT2
                JOIN Tags T2 ON BT2.tag_id = T2.tag_id
                WHERE T2.tag = ?
            )
            -- Groups all rows by blog_id → one row per blog. This allows us to use GROUP_CONCAT to get all tags for each blog in one row.
            GROUP BY B.blog_id
            ORDER BY B.created_at DESC -- just orders results by creation date (newest first)
        ");
        $stmt->bind_param("s", $tag_norm);
        $stmt->execute();
        $result = $stmt->get_result(); // blogs matching the searched tag

        $search_results = [];

        // loop through rows of result (blogs)
        while ($row = $result->fetch_assoc()) { // for each groupconcat string from each row

            // Fetch comments for each blog (row). this is separate from the main blog query to keep things simpler.
            $stmtComments = $UserDBConnect->prepare("
                -- gets comments for this blog
                SELECT commenter, sentiment, description, created_at
                FROM Comments
                WHERE blog_id = ?
                ORDER BY created_at ASC
            ");
            $stmtComments->bind_param("i", $row['blog_id']); // bind blog_id of current row (row represents a blog)
            $stmtComments->execute();
            $resultComments = $stmtComments->get_result();
            $comments = [];
            while ($commentRow = $resultComments->fetch_assoc()) {
                $comments[] = $commentRow;
            }
            $stmtComments->close();

            $row['comments'] = $comments; // add comments (as array) as a new field in the current row (this row should now contain blog info + comments)

            // row[tags] is the groupconcat string of all tags for this blog
            $row['tags'] = $row['tags'] ? explode(',', $row['tags']) : []; // a string like "tag1,tag2,tag3" → array ["tag1","tag2","tag3"]
            $search_results[] = $row; // add processed row to results
        }
        $stmt->close();
    } else {  // if no tag input, return all blogs
        $stmt = $UserDBConnect->prepare("
            -- gets all blogs
            SELECT B.blog_id, B.username, B.subject, B.description, B.created_at,
                GROUP_CONCAT(DISTINCT T.tag) AS tags
            FROM Blogs B
            LEFT JOIN BlogTags BT ON B.blog_id = BT.blog_id
            LEFT JOIN Tags T ON BT.tag_id = T.tag_id
            GROUP BY B.blog_id
            ORDER BY B.created_at DESC
    ");
        $stmt->execute();
        $result = $stmt->get_result();

        $search_results = [];
        while ($row = $result->fetch_assoc()) { // for each blog row
            // Convert tags string to array
            $row['tags'] = $row['tags'] ? explode(',', $row['tags']) : [];

            // Fetch comments for this blog
            $stmtComments = $UserDBConnect->prepare("
            -- gets comments for this blog
            SELECT commenter, sentiment, description, created_at
            FROM Comments
            WHERE blog_id = ?
            ORDER BY created_at ASC
        ");
            $stmtComments->bind_param("i", $row['blog_id']);
            $stmtComments->execute();
            $resultComments = $stmtComments->get_result();
            $comments = [];
            while ($commentRow = $resultComments->fetch_assoc()) { // for each comment row
                $comments[] = $commentRow; // add comment to comments array
            }
            $stmtComments->close();

            $row['comments'] = $comments;

            $search_results[] = $row;
        }
        $stmt->close();
    }

    // Save results to session so the view can render them
    $_SESSION['search_results'] = $search_results;
    $_SESSION['search_tag'] = $tag_input; // optional (to show what was searched)

    // Redirect back to blog.php to render results
    header("Location: ../views/blog.php");
    exit;
}


// POST COMMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_blog'])) {
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
                        header("Location: ../views/blog.php");
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
    header("Location: ../views/blog.php");
    exit;
}

// LOGOUT
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: ../views/login.php");
    exit;
}
?>