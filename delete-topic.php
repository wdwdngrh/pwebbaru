<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

if (!isLoggedIn()) {
    setFlashMessage('danger', 'Anda harus login terlebih dahulu');
    redirect('index.php');
}

$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($topic_id === 0) {
    setFlashMessage('danger', 'ID topik tidak valid');
    redirect('diskusi.php');
}

$db = Database::getInstance()->getConnection();
$user_id = getCurrentUserId();

// Check ownership
$stmt = $db->prepare("SELECT author_id, category_id, image_path FROM topics WHERE topic_id = ?");
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Topik tidak ditemukan');
    redirect('diskusi.php');
}

$topic = $result->fetch_assoc();

if ($topic['author_id'] != $user_id && !isAdmin()) {
    setFlashMessage('danger', 'Anda tidak memiliki izin untuk menghapus topik ini');
    redirect('topic-detail.php?id=' . $topic_id);
}

// Delete topic image if exists
if (!empty($topic['image_path']) && file_exists('uploads/topics/' . $topic['image_path'])) {
    unlink('uploads/topics/' . $topic['image_path']);
}

// Delete topic (will cascade delete comments and likes)
$stmt = $db->prepare("DELETE FROM topics WHERE topic_id = ?");
$stmt->bind_param("i", $topic_id);

if ($stmt->execute()) {
    // Update category count
    if ($topic['category_id']) {
        $db->query("UPDATE categories SET topic_count = topic_count - 1 WHERE category_id = " . $topic['category_id']);
    }
    
    setFlashMessage('success', 'Topik berhasil dihapus');
    redirect('my-topics.php');
} else {
    setFlashMessage('danger', 'Gagal menghapus topik');
    redirect('topic-detail.php?id=' . $topic_id);
}
?>
