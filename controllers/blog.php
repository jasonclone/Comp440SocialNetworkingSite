<?php
// controllers/blog.php
//* functions:
// post new blog (with tag processing and daily limit enforcement)
// search blogs by tag (with tags and comments fetching)
// post comment on blog (with daily limit enforcement and one comment per blog)

session_start();
require '../db_connect.php';

//* keep login check in case backend accessed directly
if (!isset($_SESSION['username'])) { // not logged in
    header("Location: ../controllers/index.php?action=login");
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

//* POST A NEW BLOG
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // if post request

    if (isset($_POST['post_blog'])) { // if post blog button clicked
        // Retrieve and trim blog inputs
        $subject = trim($_POST['subject']);
        $description = trim($_POST['description']);
        $tags_raw = trim($_POST['tags']); //trim does whitespace removal from ends

        //* get today's blog count for user (not entire user's blog count)
        $stmt = $UserDBConnect->prepare("SELECT COUNT(*) FROM Blogs WHERE username = ? AND DATE(created_at) = CURDATE()"); //curdate() gets today's date only not exact time
        $stmt->bind_param("s", $username);
        $stmt->execute(); // execute the statement that gets the count of today's blogs by user
        $stmt->bind_result($blog_count); // move result into $blog_count
        $stmt->fetch();
        $stmt->close();

        // this check is also done by the DB trigger, but we do it here first for better UX
        //* if user has already posted 2 blogs today
        if ($blog_count >= 2) {

            //* Get the 2 most recent blog created_at times EVER (**dates returned can be from previous day)
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
                $blogs[] = $row['created_at']; // add created_at times from result to blogs array
            }
            $stmt->close();

            //* should always be true (defensive check)
            if (isset($blogs[1])) { //
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
                    header("Location: ../controllers/index.php?action=home");
                    exit;
                }
            }

            // less than 2 blogs today, proceed to insert
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

        // refresh to update results
        header("Location: ../controllers/index.php?action=home");
        exit;
    }


    //* SEARCH BLOGS BY TAG
    if (isset($_POST['search_tag'])) {
        $tag_input = trim($_POST['tag']);
        if ($tag_input !== '') { // if tag input provided
            $tag_norm = mb_strtolower($tag_input); // make tag input lowercase

            /*
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

            $search_results = []; //* holds each blog with their tags and comments as elements of the array

            // go through each searched blog and process tags and comments
            while ($row = $result->fetch_assoc()) { // for each groupconcat string from each row

                // Fetch comments for each blog searched with the tag. this is separated from the main blog query to keep things simpler
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
                // store comments from this specific blog into comments array
                $comments = [];
                while ($commentRow = $resultComments->fetch_assoc()) {
                    $comments[] = $commentRow;
                }
                $stmtComments->close();

                // row[comments] is an array of comment rows for this blog
                $row['comments'] = $comments; // add comments (as array) as a new field in the current row (this row should now contain blog info + comments)

                // row[tags] is the groupconcat string of all tags for this blog
                $row['tags'] = $row['tags'] ? explode(',', $row['tags']) : []; // a string like "tag1,tag2,tag3" → array ["tag1","tag2","tag3"]
                $search_results[] = $row; // add processed row to results
            }
            $stmt->close();
        } else {
            // if no tag input provided, return empty results for index.php to handle
            $search_results = [];
        }

        // Save results to session so the view can display them
        $_SESSION['search_results'] = $search_results;
        $_SESSION['search_tag'] = $tag_input; // optional (to show what was searched)

        // Redirect back to home.php to update results
        header("Location: ../controllers/index.php?action=home");
        exit;
    }


}
?>