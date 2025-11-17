<?php
// views/home.php
session_start();
require '../db_connect.php';

// redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../controllers/index.php?action=login");
    exit;
}

$username = $_SESSION['username'];

// Grab blog/comment statuses directly
$blog_submit_status = $_SESSION['blog_submit_status'] ?? null;
$comment_submit_status = $_SESSION['comment_submit_status'] ?? null;

// keep search results and tag in session for persistence across page loads/refreshes
$search_results = $_SESSION['search_results'] ?? [];
$search_tag = $_SESSION['search_tag'] ?? ""; // default: empty string means not a user search

// results for dual-tag search + flag
$dual_tag_users = $_SESSION['dual_tag_users'] ?? [];
$dual_tag_searched = $_SESSION['dual_tag_searched'] ?? false;

// results for most blogs on specific date + flag
$most_blogs_users = $_SESSION['most_blogs_users'] ?? [];
$most_blogs_searched = $_SESSION['most_blogs_searched'] ?? false;
$selected_date = $_SESSION['selected_date'] ?? '';

// results for users followed by both X and Y + flag
$followed_by_both = $_SESSION['followed_by_both'] ?? [];
$followed_by_both_searched = $_SESSION['followed_by_both_searched'] ?? false;
$user_x_input = $_SESSION['user_x_input'] ?? '';
$user_y_input = $_SESSION['user_y_input'] ?? '';

// results for users who never posted a blog + flag
$never_posted_users = $_SESSION['never_posted_users'] ?? [];
$never_posted_searched = $_SESSION['never_posted_searched'] ?? false;

// results for blogs of User X with all positive comments + flag
$all_positive_blogs = $_SESSION['all_positive_blogs'] ?? [];
$all_positive_user = $_SESSION['all_positive_user'] ?? '';
$all_positive_searched = $_SESSION['all_positive_searched'] ?? false;

// results for users who posted only negative comments + flag
$only_negative_users = $_SESSION['only_negative_users'] ?? [];
$only_negative_searched = $_SESSION['only_negative_searched'] ?? false;

// results for users whose blogs never received negative comments + flag
$blogs_no_negative_users = $_SESSION['blogs_no_negative_users'] ?? [];
$blogs_no_negative_searched = $_SESSION['blogs_no_negative_searched'] ?? false;

/* ---- clear session values so they are one-shot (disappear after page reload) ---- */
unset(
    $_SESSION['blog_submit_status'],
    $_SESSION['comment_submit_status'],
    $_SESSION['dual_tag_users'],
    $_SESSION['dual_tag_searched'],
    $_SESSION['most_blogs_users'],
    $_SESSION['most_blogs_searched'],
    $_SESSION['selected_date'],
    $_SESSION['followed_by_both'],
    $_SESSION['followed_by_both_searched'],
    $_SESSION['user_x_input'],
    $_SESSION['user_y_input'],
    $_SESSION['never_posted_users'],
    $_SESSION['never_posted_searched'],
    $_SESSION['all_positive_blogs'],
    $_SESSION['all_positive_user'],
    $_SESSION['all_positive_searched'],
    $_SESSION['only_negative_users'],
    $_SESSION['only_negative_searched'],
    $_SESSION['blogs_no_negative_users'],
    $_SESSION['blogs_no_negative_searched']
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Blogs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .section {
            margin-bottom: 30px;
        }

        .blog-card {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }

        .meta {
            font-size: 0.9em;
            color: #555;
        }

        .comments {
            display: none;
            margin-top: 10px;
            border-top: 1px dashed #aaa;
            padding-top: 10px;
        }

        .comment {
            margin-bottom: 5px;
        }

        .toggle-btn {
            margin-top: 10px;
            cursor: pointer;
            background-color: #eee;
            border: 1px solid #ccc;
            padding: 5px 10px;
        }
    </style>
</head>

<body>
    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
    <form method="post" action="../controllers/auth.php">
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
        <form method="post" action="../controllers/blog.php">
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
        <form method="post" action="../controllers/blog.php">
            <input type="text" name="tag" placeholder="Enter a tag (optional)">
            <input type="submit" name="search_tag" value="Search">
        </form>
    </div>

    <!-- Dual-Tag User Search -->
    <div class="section">
        <h3>Find users who posted at least two blogs on same day (two given tags)</h3>
        <form method="post" action="../controllers/analytics.php" name="dual_tag_form">
            <label>Tag 1:</label>
            <input type="text" name="tag1" required>
            <label>Tag 2:</label>
            <input type="text" name="tag2" required>
            <button type="submit" name="action" value="dual_tag_search">Search</button>
        </form>

        <?php if ($dual_tag_searched): ?>
            <?php if (!empty($dual_tag_users)): ?>
                <h4>Users Found:</h4>
                <ul>
                    <?php foreach ($dual_tag_users as $user): ?>
                        <li><?php echo htmlspecialchars($user['username']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No users found matching the criteria.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Most Blogs on Specific Date -->
    <div class="section">
        <h3>Find users who posted the most blogs on a specific date</h3>
        <form method="post" action="../controllers/analytics.php">
            <label>Date:</label>
            <input type="date" name="blog_date" required>
            <button type="submit" name="action" value="most_blogs_date">Search</button>
        </form>

        <?php if ($most_blogs_searched): ?>
            <?php if (!empty($most_blogs_users)): ?>
                <h4>Users with Most Blogs on <?php echo htmlspecialchars($selected_date); ?>:</h4>
                <ul>
                    <?php foreach ($most_blogs_users as $user): ?>
                        <li><?php echo htmlspecialchars($user['username']); ?> (<?php echo (int) $user['blog_count']; ?> blogs)</li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No blogs found for this date.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Followed by both X and Y -->
    <div class="section">
        <h3>Find users followed by both users X and Y</h3>
        <form method="post" action="../controllers/analytics.php" name="followed_form">
            <label>User X:</label>
            <input type="text" name="user_x" required>
            <label>User Y:</label>
            <input type="text" name="user_y" required>
            <button type="submit" name="action" value="followed_by_both">Search</button>
        </form>

        <?php if ($followed_by_both_searched): ?>
            <?php if (!empty($followed_by_both)): ?>
                <h4>Users followed by both <?php echo htmlspecialchars($user_x_input); ?> and
                    <?php echo htmlspecialchars($user_y_input); ?>:</h4>
                <ul>
                    <?php foreach ($followed_by_both as $user): ?>
                        <li><?php echo htmlspecialchars($user['username']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No users are followed by both <?php echo htmlspecialchars($user_x_input); ?> and
                    <?php echo htmlspecialchars($user_y_input); ?>.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Users Who Never Posted a Blog -->
    <div class="section">
        <h3>Users Who Never Posted a Blog</h3>
        <form method="post" action="../controllers/analytics.php" id="never_posted_form">
            <button type="submit" name="action" value="never_posted">Show Users</button>
        </form>

        <?php if ($never_posted_searched): ?>
            <?php if (!empty($never_posted_users)): ?>
                <ul>
                    <?php foreach ($never_posted_users as $user): ?>
                        <li><?php echo htmlspecialchars($user['username']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No users found who never posted a blog.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Blogs of User X with All Positive Comments -->
    <div class="section">
        <h3>Display Blogs of a User where All Comments are Positive</h3>
        <form method="post" action="../controllers/analytics.php">
            <label>User X:</label><input type="text" name="user_x_all_positive" required>
            <button type="submit" name="action" value="all_positive_comments">Show Blogs</button>
        </form>

        <?php if ($all_positive_searched): ?>
            <?php if (!empty($all_positive_blogs)): ?>
                <h4>Blogs of <?php echo htmlspecialchars($all_positive_user); ?> (With All Comments Positive):</h4>
                <?php foreach ($all_positive_blogs as $blog): ?>
                    <div class="blog-card">
                        <strong><?php echo htmlspecialchars($blog['subject']); ?></strong>
                        <div class="meta">By <?php echo htmlspecialchars($blog['username']); ?> on
                            <?php echo htmlspecialchars($blog['created_at']); ?></div>
                        <p><?php echo nl2br(htmlspecialchars($blog['description'])); ?></p>
                        <p><strong>Tags:</strong> <?php echo implode(', ', array_map('htmlspecialchars', $blog['tags'])); ?></p>
                        <p><strong>Comments:</strong> All Positive</p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No blogs found for this user where all comments are positive.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Users Who Posted Only Negative Comments -->
    <div class="section">
        <h3>Users Who Posted Comments, but Each Comment is Negative</h3>
        <form method="post" action="../controllers/analytics.php">
            <button type="submit" name="action" value="only_negative_comments">Show Users</button>
        </form>

        <?php if ($only_negative_searched): ?>
            <?php if (!empty($only_negative_users)): ?>
                <h4>Users:</h4>
                <ul>
                    <?php foreach ($only_negative_users as $user): ?>
                        <li><?php echo htmlspecialchars($user['username']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No users found who posted only negative comments.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Users Whose Blogs Never Received Negative Comments -->
    <div class="section">
        <h3>Users Whose every blog never received negative comments</h3>
        <form method="post" action="../controllers/analytics.php">
            <button type="submit" name="action" value="blogs_no_negative">Show Users</button>
        </form>

        <?php if ($blogs_no_negative_searched): ?>
            <?php if (!empty($blogs_no_negative_users)): ?>
                <h4>Users:</h4>
                <ul>
                    <?php foreach ($blogs_no_negative_users as $user): ?>
                        <li><?php echo htmlspecialchars($user['username']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No users found whose blogs never received negative comments.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Display Search Results (blog listing) -->
    <?php if (!empty($search_results)): ?>
        <h3><?php echo $search_tag === "" ? "All Blog Posts" : 'Search Results for "' . htmlspecialchars($search_tag) . '"'; ?>
        </h3>

        <?php if ($comment_submit_status): ?>
            <p class="<?php echo htmlspecialchars($comment_submit_status['type']); ?>">
                <?php echo htmlspecialchars($comment_submit_status['message']); ?>
            </p>
        <?php endif; ?>

        <?php foreach ($search_results as $blog): ?>
            <div class="blog-card">
                <strong><?php echo htmlspecialchars($blog['subject']); ?></strong><br>
                <div class="meta">
                    By <a href="../controllers/index.php?action=profile&user=<?php echo urlencode($blog['username']); ?>">
                        <?php echo htmlspecialchars($blog['username']); ?></a>
                    on <?php echo htmlspecialchars($blog['created_at']); ?>
                </div>
                <p><?php echo nl2br(htmlspecialchars($blog['description'])); ?></p>

                <?php $tagList = array_map('htmlspecialchars', $blog['tags']); ?>
                <p><strong>Tags:</strong> <?php echo implode(", ", $tagList); ?></p>

                <div class="toggle-btn" onclick="toggleComments(<?php echo (int) $blog['blog_id']; ?>)"
                    id="toggle-btn-<?php echo (int) $blog['blog_id']; ?>">
                    View Comments
                </div>

                <div class="comments" id="comments-<?php echo (int) $blog['blog_id']; ?>">
                    <?php if (!empty($blog['comments'])): ?>
                        <?php foreach ($blog['comments'] as $comment): ?>
                            <div class="comment">
                                <strong>
                                    <a href="../controllers/index.php?action=profile&user=<?php echo urlencode($comment['commenter']); ?>">
                                        <?php echo htmlspecialchars($comment['commenter']); ?></a>
                                    (<?php echo htmlspecialchars($comment['sentiment']); ?>):
                                </strong>
                                <?php echo nl2br(htmlspecialchars($comment['description'])); ?>
                                <div class="meta"><?php echo htmlspecialchars($comment['created_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No comments yet.</p>
                    <?php endif; ?>
                </div>

                <!-- add comment form -->
                <form method="post" action="../controllers/user.php" style="margin-top:10px;">
                    <input type="hidden" name="blog_id" value="<?php echo (int) $blog['blog_id']; ?>">
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

    <?php elseif ($search_tag !== ""): ?>
        <p>No blogs found for "<?php echo htmlspecialchars($search_tag); ?>".</p>
    <?php else: ?>
        <p>No blogs exist yet!</p>
    <?php endif; ?>

    <script src="../public/blog/toggleComments.js"></script>
    <script src="../public/home/verifyDualInputs.js"></script>
</body>

</html>