<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

if (!isLoggedIn()) {
    setFlashMessage('danger', 'Anda harus login terlebih dahulu');
    redirect('index.php');
}

$comment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$topic_id = isset($_GET['topic']) ? (int)$_GET['topic'] : 0;

if ($comment_id === 0) {
    setFlashMessage('danger', 'ID komentar tidak valid');
    redirect('diskusi.php');
}

$db = Database::getInstance()->getConnection();
$user_id = getCurrentUserId();

// Check ownership
$stmt = $db->prepare("SELECT user_id, topic_id FROM comments WHERE comment_id = ?");
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Komentar tidak ditemukan');
    redirect('diskusi.php');
}

$comment = $result->fetch_assoc();
$topic_id = $comment['topic_id'];

if ($comment['user_id'] != $user_id && !isAdmin()) {
    setFlashMessage('danger', 'Anda tidak memiliki izin untuk menghapus komentar ini');
    redirect('topic-detail.php?id=' . $topic_id);
}

// Delete comment
$stmt = $db->prepare("DELETE FROM comments WHERE comment_id = ?");
$stmt->bind_param("i", $comment_id);

if ($stmt->execute()) {
    // Update topic comment count
    $db->query("UPDATE topics SET comment_count = comment_count - 1 WHERE topic_id = $topic_id");
    setFlashMessage('success', 'Komentar berhasil dihapus');
} else {
    setFlashMessage('danger', 'Gagal menghapus komentar');
}

redirect('topic-detail.php?id=' . $topic_id);
?>
