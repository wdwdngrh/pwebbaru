<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

$db = Database::getInstance()->getConnection();

// Get topic ID
$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($topic_id === 0) {
    redirect('diskusi.php');
}

// Update view count
$db->query("UPDATE topics SET view_count = view_count + 1 WHERE topic_id = $topic_id");

// Get topic details
$stmt = $db->prepare("
    SELECT t.*, u.username, u.full_name, u.avatar, c.category_name, c.color as category_color
    FROM topics t
    JOIN users u ON t.author_id = u.user_id
    LEFT JOIN categories c ON t.category_id = c.category_id
    WHERE t.topic_id = ?
");
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$topic = $stmt->get_result()->fetch_assoc();

if (!$topic) {
    setFlashMessage('danger', 'Topik tidak ditemukan');
    redirect('diskusi.php');
}

// Check if current user has liked this topic
$isLiked = false;
if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    $stmt = $db->prepare("SELECT like_id FROM likes WHERE user_id = ? AND likeable_type = 'topic' AND likeable_id = ?");
    $stmt->bind_param("ii", $user_id, $topic_id);
    $stmt->execute();
    $isLiked = $stmt->get_result()->num_rows > 0;
}

// Get comments
$stmt = $db->prepare("
    SELECT c.*, u.username, u.full_name, u.avatar
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.topic_id = ? AND c.parent_comment_id IS NULL
    ORDER BY c.created_at ASC
");
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if (!isLoggedIn()) {
        setFlashMessage('danger', 'Anda harus login terlebih dahulu');
        redirect('topic-detail.php?id=' . $topic_id);
    }
    
    $content = sanitize($_POST['content']);
    $user_id = getCurrentUserId();
    
    if (!empty($content)) {
        $stmt = $db->prepare("INSERT INTO comments (topic_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $topic_id, $user_id, $content);
        
        if ($stmt->execute()) {
            // Update comment count
            $db->query("UPDATE topics SET comment_count = comment_count + 1 WHERE topic_id = $topic_id");
            setFlashMessage('success', 'Komentar berhasil ditambahkan');
            redirect('topic-detail.php?id=' . $topic_id);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($topic['title']); ?> - Forum UTBK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Flash Messages -->
    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
        <div class="container mt-3">
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Topic Content -->
    <section class="container mt-4 mb-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                <li class="breadcrumb-item"><a href="diskusi.php">Diskusi</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars(truncate($topic['title'], 50)); ?></li>
            </ol>
        </nav>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="mb-2"><?php echo htmlspecialchars($topic['title']); ?></h2>
                        <span class="badge bg-<?php echo $topic['category_color']; ?> badge-custom">
                            <?php echo htmlspecialchars($topic['category_name']); ?>
                        </span>
                        <?php if (!empty($topic['tags'])): ?>
                            <?php foreach (explode(',', $topic['tags']) as $tag): ?>
                                <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars(trim($tag)); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (isLoggedIn() && (getCurrentUserId() == $topic['author_id'] || isAdmin())): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item text-danger" href="delete-topic.php?id=<?php echo $topic_id; ?>" onclick="return confirm('Yakin ingin menghapus topik ini?')"><i class="bi bi-trash"></i> Hapus</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex mb-4">
                    <div class="user-avatar me-3"><?php echo getUserInitials($topic['full_name'] ?: $topic['username']); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo htmlspecialchars($topic['full_name'] ?: $topic['username']); ?></h6>
                        <small class="text-muted"><?php echo timeAgo($topic['created_at']); ?></small>
                    </div>
                </div>

                <div class="topic-content mb-4" style="white-space: pre-wrap;">
                    <?php echo nl2br(htmlspecialchars($topic['content'])); ?>
                </div>

                <!-- Display image if exists -->
                <?php if (!empty($topic['image_path']) && file_exists('uploads/topics/' . $topic['image_path'])): ?>
                    <div class="mb-4">
                        <img src="uploads/topics/<?php echo htmlspecialchars($topic['image_path']); ?>" 
                             class="img-fluid rounded" 
                             style="max-width: 100%; max-height: 500px; object-fit: contain;"
                             alt="Gambar topik">
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-3 border-top pt-3">
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" action="api/like-handler.php" class="d-inline">
                            <input type="hidden" name="type" value="topic">
                            <input type="hidden" name="id" value="<?php echo $topic_id; ?>">
                            <button type="submit" class="btn btn-sm <?php echo $isLiked ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="bi bi-hand-thumbs-up<?php echo $isLiked ? '-fill' : ''; ?>"></i> 
                                Like (<?php echo $topic['like_count']; ?>)
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="bi bi-hand-thumbs-up"></i> Like (<?php echo $topic['like_count']; ?>)
                        </button>
                    <?php endif; ?>
                    <span class="text-muted"><i class="bi bi-eye"></i> <?php echo $topic['view_count']; ?> Views</span>
                    <span class="text-muted"><i class="bi bi-chat-left-text"></i> <?php echo $topic['comment_count']; ?> Komentar</span>
                </div>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="comment-section">
            <h5 class="fw-bold mb-3"><?php echo count($comments); ?> Komentar</h5>
            
            <?php foreach ($comments as $comment): ?>
                <div class="comment-item">
                    <div class="d-flex">
                        <div class="user-avatar me-3" style="width: 40px; height: 40px; font-size: 0.9rem;">
                            <?php echo getUserInitials($comment['full_name'] ?: $comment['username']); ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-0"><?php echo htmlspecialchars($comment['full_name'] ?: $comment['username']); ?></h6>
                                <div>
                                    <small class="text-muted me-2"><?php echo timeAgo($comment['created_at']); ?></small>
                                    <?php if (isLoggedIn() && (getCurrentUserId() == $comment['user_id'] || isAdmin())): ?>
                                        <div class="dropdown d-inline">
                                            <button class="btn btn-sm btn-link" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item text-danger" href="delete-comment.php?id=<?php echo $comment['comment_id']; ?>&topic=<?php echo $topic_id; ?>" onclick="return confirm('Yakin ingin menghapus komentar ini?')"><i class="bi bi-trash"></i> Hapus</a></li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mt-2 mb-2" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Add Comment Form -->
            <?php if (isLoggedIn()): ?>
                <div class="mt-4">
                    <h6 class="fw-bold mb-3">Tambah Komentar</h6>
                    <form method="POST">
                        <div class="mb-3">
                            <textarea name="content" class="form-control" rows="3" placeholder="Tulis komentar Anda..." required></textarea>
                        </div>
                        <button type="submit" name="submit_comment" class="btn btn-primary rounded-pill px-4">Kirim Komentar</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="mt-4 text-center py-4 bg-light rounded">
                    <p class="mb-2">Anda harus login untuk berkomentar</p>
                    <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Modals -->
    <?php include 'includes/modals.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/auth-dynamic.js"></script>
</body>
</html>
