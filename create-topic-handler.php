<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

if (!isLoggedIn()) {
    setFlashMessage('danger', 'Anda harus login terlebih dahulu');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $category_id = (int)$_POST['category_id'];
    $tags = sanitize($_POST['tags'] ?? '');
    $author_id = getCurrentUserId();
    
    if (empty($title) || empty($content)) {
        setFlashMessage('danger', 'Judul dan isi topik harus diisi');
        redirect('index.php');
    }
    
    if ($category_id === 0) {
        setFlashMessage('danger', 'Kategori harus dipilih');
        redirect('index.php');
    }
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Check file size (max 5MB)
            if ($_FILES['image']['size'] <= 5 * 1024 * 1024) {
                $new_filename = 'topic_' . time() . '_' . uniqid() . '.' . $ext;
                $upload_path = 'uploads/topics/' . $new_filename;
                
                // Create directory if not exists
                if (!is_dir('uploads/topics')) {
                    mkdir('uploads/topics', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_path = $new_filename;
                } else {
                    setFlashMessage('warning', 'Gagal mengupload gambar');
                }
            } else {
                setFlashMessage('warning', 'Ukuran gambar terlalu besar (maksimal 5MB)');
            }
        } else {
            setFlashMessage('warning', 'Format gambar tidak didukung');
        }
    }
    
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("INSERT INTO topics (title, content, author_id, category_id, tags, image_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiss", $title, $content, $author_id, $category_id, $tags, $image_path);
    
    if ($stmt->execute()) {
        $topic_id = $db->insert_id;
        
        // Update category topic count
        $db->query("UPDATE categories SET topic_count = topic_count + 1 WHERE category_id = $category_id");
        
        setFlashMessage('success', 'Topik berhasil dibuat!');
        redirect('topic-detail.php?id=' . $topic_id);
    } else {
        setFlashMessage('danger', 'Gagal membuat topik: ' . $stmt->error);
        redirect('index.php');
    }
} else {
    redirect('index.php');
}
?>
