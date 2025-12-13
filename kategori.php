<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'config/helpers.php';
startSession();

$db = Database::getInstance()->getConnection();

// Get all categories with stats
$categories = $db->query("
    SELECT c.*, 
           COUNT(DISTINCT t.topic_id) as topic_count,
           COUNT(DISTINCT cm.user_id) as member_count
    FROM categories c
    LEFT JOIN topics t ON c.category_id = t.category_id
    LEFT JOIN community_members cm ON cm.community_id IN (
        SELECT community_id FROM communities WHERE category_id = c.category_id
    )
    GROUP BY c.category_id
    ORDER BY c.category_name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi UTBK – Kategori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section" id="kategori">
        <div class="container text-center">
            <h1><i class="bi bi-grid-fill"></i> Mata Pelajaran dan Topik</h1>
            <p>Pilih mata pelajaran atau topik yang kalian butuhkan</p>
        </div>
    </section>
    
    <!-- Kategori Section -->
    <section class="container mb-5" id="kategori">
        <h2 class="mb-4 fw-bold">Kategori Diskusi</h2>
        <div class="row">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-4 mb-4">
                    <div class="card category-card" onclick="window.location.href='diskusi.php?category_id=<?php echo $category['category_id']; ?>'">
                        <div class="card-body text-center">
                            <div class="category-icon bg-<?php echo $category['color']; ?> bg-opacity-10 text-<?php echo $category['color']; ?> mx-auto">
                                <i class="bi <?php echo $category['icon']; ?>"></i>
                            </div>
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($category['category_name']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($category['description']); ?></p>
                            <div class="d-flex justify-content-between mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-chat"></i> <?php echo number_format($category['topic_count']); ?> Topik
                                </small>
                                <small class="text-muted">
                                    <i class="bi bi-people"></i> <?php echo number_format($category['member_count']); ?> Anggota
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
