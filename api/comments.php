<?php
date_default_timezone_set('Asia/Jakarta');
// api/comments.php - Comments and Likes handler

require_once '../config/database.php';
require_once '../config/helpers.php';

header('Content-Type: application/json');
startSession();

$response = ['success' => false, 'message' => ''];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();

switch ($action) {
    case 'create':
        if ($method === 'POST') {
            if (!isLoggedIn()) {
                $response['message'] = 'Anda harus login terlebih dahulu';
                break;
            }
            
            $topic_id = intval($_POST['topic_id'] ?? 0);
            $content = sanitize($_POST['content'] ?? '');
            $user_id = getCurrentUserId();
            
            if (empty($content)) {
                $response['message'] = 'Komentar tidak boleh kosong';
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO comments (topic_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $topic_id, $user_id, $content);
            
            if ($stmt->execute()) {
                // Update topic comment count
                $db->query("UPDATE topics SET comment_count = comment_count + 1 WHERE topic_id = $topic_id");
                
                $response['success'] = true;
                $response['message'] = 'Komentar berhasil ditambahkan';
                $response['comment_id'] = $db->insert_id;
            } else {
                $response['message'] = 'Gagal menambahkan komentar';
            }
        }
        break;
        
    case 'update':
        if ($method === 'POST') {
            if (!isLoggedIn()) {
                $response['message'] = 'Anda harus login terlebih dahulu';
                break;
            }
            
            $comment_id = intval($_POST['comment_id'] ?? 0);
            $content = sanitize($_POST['content'] ?? '');
            $user_id = getCurrentUserId();
            
            // Check ownership
            $stmt = $db->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Komentar tidak ditemukan';
                break;
            }
            
            $comment = $result->fetch_assoc();
            if ($comment['user_id'] != $user_id && !isAdmin()) {
                $response['message'] = 'Anda tidak memiliki izin untuk mengedit komentar ini';
                break;
            }
            
            $stmt = $db->prepare("UPDATE comments SET content = ? WHERE comment_id = ?");
            $stmt->bind_param("si", $content, $comment_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Komentar berhasil diupdate';
            } else {
                $response['message'] = 'Gagal mengupdate komentar';
            }
        }
        break;
        
    case 'delete':
        if ($method === 'POST') {
            if (!isLoggedIn()) {
                $response['message'] = 'Anda harus login terlebih dahulu';
                break;
            }
            
            $comment_id = intval($_POST['comment_id'] ?? 0);
            $user_id = getCurrentUserId();
            
            // Check ownership and get topic_id
            $stmt = $db->prepare("SELECT user_id, topic_id FROM comments WHERE comment_id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Komentar tidak ditemukan';
                break;
            }
            
            $comment = $result->fetch_assoc();
            if ($comment['user_id'] != $user_id && !isAdmin()) {
                $response['message'] = 'Anda tidak memiliki izin untuk menghapus komentar ini';
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM comments WHERE comment_id = ?");
            $stmt->bind_param("i", $comment_id);
            
            if ($stmt->execute()) {
                // Update topic comment count
                $db->query("UPDATE topics SET comment_count = comment_count - 1 WHERE topic_id = " . $comment['topic_id']);
                
                $response['success'] = true;
                $response['message'] = 'Komentar berhasil dihapus';
            } else {
                $response['message'] = 'Gagal menghapus komentar';
            }
        }
        break;
        
    case 'like':
        if ($method === 'POST') {
            if (!isLoggedIn()) {
                $response['message'] = 'Anda harus login terlebih dahulu';
                break;
            }
            
            $type = $_POST['type'] ?? 'topic'; // topic or comment
            $id = intval($_POST['id'] ?? 0);
            $user_id = getCurrentUserId();
            
            // Check if already liked
            $stmt = $db->prepare("SELECT like_id FROM likes WHERE user_id = ? AND likeable_type = ? AND likeable_id = ?");
            $stmt->bind_param("isi", $user_id, $type, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Unlike
                $stmt = $db->prepare("DELETE FROM likes WHERE user_id = ? AND likeable_type = ? AND likeable_id = ?");
                $stmt->bind_param("isi", $user_id, $type, $id);
                $stmt->execute();
                
                // Update count
                if ($type === 'topic') {
                    $db->query("UPDATE topics SET like_count = like_count - 1 WHERE topic_id = $id");
                } else {
                    $db->query("UPDATE comments SET like_count = like_count - 1 WHERE comment_id = $id");
                }
                
                $response['success'] = true;
                $response['action'] = 'unliked';
                $response['message'] = 'Like dihapus';
            } else {
                // Like
                $stmt = $db->prepare("INSERT INTO likes (user_id, likeable_type, likeable_id) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $user_id, $type, $id);
                $stmt->execute();
                
                // Update count
                if ($type === 'topic') {
                    $db->query("UPDATE topics SET like_count = like_count + 1 WHERE topic_id = $id");
                } else {
                    $db->query("UPDATE comments SET like_count = like_count + 1 WHERE comment_id = $id");
                }
                
                $response['success'] = true;
                $response['action'] = 'liked';
                $response['message'] = 'Like ditambahkan';
            }
        }
        break;
        
    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);
?>
