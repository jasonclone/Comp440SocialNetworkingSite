-- schema.sql
CREATE DATABASE IF NOT EXISTS Comp440SocialNetworkWebsite CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

USE Comp440SocialNetworkWebsite;

-- Users (unchanged)
CREATE TABLE IF NOT EXISTS Users (
    username VARCHAR(50) PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

-- Blogs table (author, subject, description)
CREATE TABLE IF NOT EXISTS Blogs (
    blog_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL, -- references the authoring user
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (username) REFERENCES Users (username) ON DELETE CASCADE -- if user deleted, delete their blogs too
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

-- Tags
CREATE TABLE IF NOT EXISTS Tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    tag VARCHAR(100) NOT NULL UNIQUE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

-- BlogTags linking table (used to track which tags are associated with which blogs)
CREATE TABLE IF NOT EXISTS BlogTags (
    blog_id INT NOT NULL, -- references a blog
    tag_id INT NOT NULL, -- references a tag
    PRIMARY KEY (blog_id, tag_id), -- composite primary key to avoid duplicate links (ensures each tag linked once per blog)
    FOREIGN KEY (blog_id) REFERENCES Blogs (blog_id) ON DELETE CASCADE, -- if blog deleted, delete its blogtags too
    FOREIGN KEY (tag_id) REFERENCES Tags (tag_id) ON DELETE CASCADE -- if tag deleted, delete from blogtags too
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

-- Comments table (unchanged columns, but triggers will enforce limits)
CREATE TABLE IF NOT EXISTS Comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY, -- unique comment identifier
    blog_id INT NOT NULL, -- references the blog being commented on
    commenter VARCHAR(50) NOT NULL, -- references the user making the comment
    sentiment ENUM('positive', 'negative') NOT NULL,
    description TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES Blogs (blog_id) ON DELETE CASCADE, -- if blog deleted, delete its comments too
    FOREIGN KEY (commenter) REFERENCES Users (username) ON DELETE CASCADE -- if user deleted, delete their comments too
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

-- Followers table
CREATE TABLE IF NOT EXISTS Followers (
    follower_username VARCHAR(50) NOT NULL, -- who is following
    followed_username VARCHAR(50) NOT NULL, -- who is being followed
    PRIMARY KEY (
        follower_username,
        followed_username
    ), -- composite primary key to avoid duplicate follows
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- when the the follow happened
    FOREIGN KEY (follower_username) REFERENCES Users (username) ON DELETE CASCADE, -- if user deleted, delete their followers too
    FOREIGN KEY (followed_username) REFERENCES Users (username) ON DELETE CASCADE -- if user deleted, delete their follows too
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

-- Index for quickly finding who a user follows
CREATE INDEX idx_follower ON Followers (follower_username);
-- Index for quickly finding who follows a given user
CREATE INDEX idx_followed ON Followers (followed_username);

-- ========== TRIGGERS to enforce per-day limits and constraints ==========

-- Trigger: limit 2 blogs per user per day
DELIMITER $$

CREATE TRIGGER trg_before_insert_blog
BEFORE INSERT ON Blogs -- this trigger fires before a new blog is inserted
FOR EACH ROW
BEGIN
  DECLARE blog_count INT DEFAULT 0; -- variable to hold count of today's blogs by user
  SELECT COUNT(*) INTO blog_count -- query to count blogs by this user today
    FROM Blogs
    WHERE username = NEW.username
      AND DATE(created_at) = CURDATE();
  IF blog_count >= 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Daily blog limit reached (2 per day).'; -- raise error
  END IF;
END$$

DELIMITER;

-- Trigger: comments constraints
DELIMITER $$

CREATE TRIGGER trg_before_insert_comment
BEFORE INSERT ON Comments
FOR EACH ROW
BEGIN
  DECLARE owner VARCHAR(50);
  DECLARE comment_count INT DEFAULT 0;
  DECLARE already_commented INT DEFAULT 0;

  -- Fetch the blog owner
  SELECT username INTO owner FROM Blogs WHERE blog_id = NEW.blog_id;

  -- No self-review: commenter != owner
  IF owner = NEW.commenter THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot comment on your own blog.';
  END IF;

  -- At most one comment per user per blog
  SELECT COUNT(*) INTO already_commented
    FROM Comments
    WHERE blog_id = NEW.blog_id AND commenter = NEW.commenter;
  IF already_commented > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You have already commented on this blog.';
  END IF;

  -- At most 3 comments per user per day
  SELECT COUNT(*) INTO comment_count
    FROM Comments
    WHERE commenter = NEW.commenter
      AND DATE(created_at) = CURDATE();
  IF comment_count >= 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Daily comment limit reached (3 per day).';
  END IF;
END$$

DELIMITER;
-- ========================================================================