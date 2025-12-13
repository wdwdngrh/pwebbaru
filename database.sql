-- ===============================
-- DATABASE UTBK FORUM
-- ===============================

CREATE DATABASE IF NOT EXISTS utbk_forum;
USE utbk_forum;

-- Drop tables if exist (untuk fresh install)
DROP TABLE IF EXISTS event_participants;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS likes;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS topics;
DROP TABLE IF EXISTS community_members;
DROP TABLE IF EXISTS communities;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ===============================
-- TABEL USERS
-- ===============================
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    bio TEXT,
    school VARCHAR(100),
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    role ENUM('user', 'admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email)
);

-- ===============================
-- TABEL CATEGORIES
-- ===============================
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'bi-grid',
    icon_image VARCHAR(255),
    color VARCHAR(20) DEFAULT 'primary',
    topic_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===============================
-- TABEL COMMUNITIES
-- ===============================
CREATE TABLE communities (
    community_id INT PRIMARY KEY AUTO_INCREMENT,
    community_name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    full_description TEXT,
    icon VARCHAR(50) DEFAULT 'bi-people',
    icon_image VARCHAR(255),
    icon_bg VARCHAR(20) DEFAULT '#007bff',
    category_id INT,
    creator_id INT,
    member_count INT DEFAULT 0,
    discussion_count INT DEFAULT 0,
    post_count INT DEFAULT 0,
    event_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (creator_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_slug (slug)
);

-- ===============================
-- TABEL COMMUNITY MEMBERS
-- ===============================
CREATE TABLE community_members (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'moderator', 'founder') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (community_id, user_id)
);

-- ===============================
-- TABEL TOPICS
-- ===============================
CREATE TABLE topics (
    topic_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    category_id INT,
    community_id INT,
    tags VARCHAR(255),
    image_path VARCHAR(255),
    view_count INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    like_count INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_locked BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_community (community_id),
    INDEX idx_created (created_at),
    FULLTEXT KEY ft_search (title, content, tags)
);

-- ===============================
-- TABEL COMMENTS
-- ===============================
CREATE TABLE comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_comment_id INT,
    content TEXT NOT NULL,
    like_count INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(topic_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES comments(comment_id) ON DELETE CASCADE,
    INDEX idx_topic (topic_id)
);

-- ===============================
-- TABEL LIKES
-- ===============================
CREATE TABLE likes (
    like_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    likeable_type ENUM('topic', 'comment') NOT NULL,
    likeable_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, likeable_type, likeable_id),
    INDEX idx_likeable (likeable_type, likeable_id)
);

-- ===============================
-- TABEL EVENTS
-- ===============================
CREATE TABLE events (
    event_id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    participant_count INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(community_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ===============================
-- TABEL EVENT PARTICIPANTS
-- ===============================
CREATE TABLE event_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (event_id, user_id)
);

-- ===============================
-- TABEL NOTIFICATIONS
-- ===============================
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('comment', 'like', 'mention', 'event', 'community') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
);

-- ===============================
-- TABEL REPORTS
-- ===============================
CREATE TABLE reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    reporter_id INT NOT NULL,
    reportable_type ENUM('topic', 'comment', 'user') NOT NULL,
    reportable_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    reviewed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ===============================
-- DATA DEFAULT
-- ===============================

-- Admin (password: password)
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@utbkforum.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- User demo (password: password)
INSERT INTO users (username, email, password, full_name) VALUES
('user1', 'user1@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User Demo 1'),
('user2', 'user2@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User Demo 2');

-- Categories
INSERT INTO categories (category_name, description, icon, color) VALUES
('Penalaran umum', 'Kemampuan bernalar yang logis', 'bi-book', 'success'),
('Pengetahuan dan Pemahaman Umum', 'Pengetahuan keterampilan berbahasa dan memahami informasi', 'bi-book', 'success'),
('Pemahaman Bacaan dan Menulis', 'Kemampuan menulis dan memahami bacaan', 'bi-book', 'success'),
('Pengetahuan Kuantitatif', 'Pengetahuan perhitungan dan pemecahan masalah', 'bi-book', 'success'),
('Literasi Bahasa Indonesia', 'Kemampuan memahami dan mengevaluasi teks bahasa Indonesia', 'bi-book', 'success'),
('Literasi Bahasa Inggris', 'Kemampuan memahami dan mengevaluasi teks bahasa Inggris', 'bi-book', 'success'),
('Penalaran Matematika', 'Kemampuan memahami literasi matematika', 'bi-book', 'success'),
('Umum', 'Semua bisa gabung', 'bi-book', 'success');

-- Communities
INSERT INTO communities (community_name, slug, description, full_description, icon, icon_bg, category_id, creator_id, member_count, discussion_count) VALUES
('Komunitas Surya Andyartha Last Chunk', 'surya-andyartha', 'Bagi yang mengikuti channel YouTube Surya Andyartha', 'Komunitas belajar Penalaran Matematika yang terpusat pada channel Youtube Surya Andyartha Last Chunk', 'bi-book', '#28a745', 7, 1, 0, 0),
('Belajar ala Kukang', 'sloth-life', 'Ayo kumpul yang belajarnya lemot kek kukang', 'Komunitas yang akan membantu kalian belajar semua subtes UTBK tanpa takut dijudge, kita semua di sini saling bantu', 'bi-book', '#28a745', 8, 1, 0, 0);

-- Sample Topics
INSERT INTO topics (title, content, author_id, category_id, tags) VALUES
('Cara cepat menghitung integral?', 'Saya kesulitan menghitung integral tentu dengan batas yang rumit. Ada tips atau trik khusus yang bisa dipake?', 2, 4, 'integral,kalkulus,pk'),
('Tips mengerjakan soal PM', 'Bagaimana strategi terbaik untuk mengerjakan soal PM? Gak pernah bisa paham maksud soalnya', 3, 7, 'pm,strategi,tips');

-- Update counts
UPDATE categories c SET topic_count = (SELECT COUNT(*) FROM topics t WHERE t.category_id = c.category_id);
