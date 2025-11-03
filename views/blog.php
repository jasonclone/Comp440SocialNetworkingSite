<?php
// views/blog.php
session_start();
require '../db_connect.php';

// redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];

$blog_submit_status = $_SESSION['blog_submit_status'] ?? null;
unset($_SESSION['blog_submit_status']);

$comment_submit_status = $_SESSION['comment_submit_status'] ?? null;
unset($_SESSION['comment_submit_status']);

$search_results = $_SESSION['search_results'] ?? [];
$search_tag = $_SESSION['search_tag'] ?? " ";
unset($_SESSION['search_results'], $_SESSION['search_tag']); // unset after retrieving
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Blogs</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .section { margin-bottom: 30px; }
        .blog-card { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; }
        .success { color: green; }
        .error { color: red; }
        .meta { font-size: 0.9em; color: #555; } /* styling for metadata like username and timestamp */
        .comments { display: none; margin-top: 10px; border-top: 1px dashed #aaa; padding-top: 10px; }
        .comment { margin-bottom: 5px; }
        .toggle-btn { margin-top: 10px; cursor: pointer; background-color: #eee; border: 1px solid #ccc; padding: 5px 10px; }
    </style>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
    <form method="post" action="../php/blog.php">
        <button name="logout">Logout</button>
    </form>

    <!-- Post New Blog Section -->
    <div class="section">
        <h3>Post a New Blog</h3>
        <?php if ($blog_submit_status): ?>
            <p class="<?php echo htmlspecialchars($blog_submit_status['type']); ?>">
                <?php echo htmlspecialchars($blog_submit_status['message']); ?>
            </p>
        <?php endif; ?>
        <form method="post" action="../php/blog.php">
            <label>Subject:</label><br>
            <input type="text" name="subject" required><br>
            <label>Description:</label><br>
            <textarea name="description" required></textarea><br>
            <label>Tags (comma separated):</label><br>
            <input type="text" name="tags" required><br>
            <input type="submit" name="post_blog" value="Post Blog">
        </form>
    </div>

    <!-- Search Blogs by Tag Section -->
    <div class="section">
        <h3>Search Blogs by Tag</h3>
        <form method="post" action="../php/blog.php">
            <input type="text" name="tag" placeholder="Enter a tag (optional)">
            <input type="submit" name="search_tag" value="Search">
        </form>
    </div>


    <!-- Display Search Results -->
    <?php if (!empty($search_results)): ?> <!-- if there are search results -->
        <h3>Search Results for "<?php echo htmlspecialchars($search_tag); ?>"</h3>
        <?php foreach ($search_results as $blog): ?> <!-- Iterate through each blog in search results -->
            <div class="blog-card">
                <strong><?php echo htmlspecialchars($blog['subject']); ?></strong><br>
                <div class="meta">By <?php echo htmlspecialchars($blog['username']); ?> on <?php echo htmlspecialchars($blog['created_at']); ?></div>
                <p><?php echo nl2br(htmlspecialchars($blog['description'])); ?></p>

                <?php $tagList = array_map('htmlspecialchars', $blog['tags']); ?>
                <p><strong>Tags:</strong> <?php echo implode(", ", $tagList); ?></p>

                <!-- View/Hide Comments Button -->
                <div class="toggle-btn" onclick="toggleComments(<?php echo (int)$blog['blog_id']; ?>)" id="toggle-btn-<?php echo (int)$blog['blog_id']; ?>">
                    View Comments
                </div>

                <!-- Comments Section -->
                <div class="comments" id="comments-<?php echo (int)$blog['blog_id']; ?>">
                    <?php if (!empty($blog['comments'])): ?>
                        <?php foreach ($blog['comments'] as $comment): ?>
                            <div class="comment">
                                <strong><?php echo htmlspecialchars($comment['commenter']); ?> (<?php echo htmlspecialchars($comment['sentiment']); ?>):</strong>
                                <?php echo nl2br(htmlspecialchars($comment['description'])); ?>
                                <div class="meta"><?php echo htmlspecialchars($comment['created_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No comments yet.</p>
                    <?php endif; ?>
                </div>

                <!-- COMMENT FORM -->
                <form method="post" action="../php/blog.php" style="margin-top:10px;">
                    <input type="hidden" name="blog_id" value="<?php echo (int)$blog['blog_id']; ?>">
                    <select name="sentiment" required>
                        <option value="">--Select Sentiment--</option>
                        <option value="positive">Positive</option>
                        <option value="negative">Negative</option>
                    </select>
                    <input type="text" name="comment_description" placeholder="Enter comment..." required>
                    <input type="submit" name="comment_blog" value="Comment">
                </form>
            </div>
        <?php endforeach; ?>
    <?php elseif ($search_tag && $search_tag !== " "): ?> <!-- if search tag is set but no results -->
        <p>No blogs found for "<?php echo htmlspecialchars($search_tag); ?>".</p>
    <?php elseif (!$search_tag): ?> 
        <p>No blogs found for ""</p>
    <?php endif; ?>

    <?php if ($comment_submit_status): ?>
        <p class="<?php echo htmlspecialchars($comment_submit_status['type']); ?>">
            <?php echo htmlspecialchars($comment_submit_status['message']); ?>
        </p>
    <?php endif; ?>



<script src="../public/blog/toggleComments.js"></script> <!-- toggle comments button script -->

</body>
</html>
