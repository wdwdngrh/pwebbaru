<?php
date_default_timezone_set('Asia/Jakarta');
require_once '../config/database.php';
require_once '../config/helpers.php';
startSession();

if (!isLoggedIn() || !isAdmin()) {
    setFlashMessage('danger', 'Anda tidak memiliki akses ke halaman ini');
    redirect('../index.php');
}

$db = Database::getInstance()->getConnection();

// Handle add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = sanitize($_POST['category_name']);
    $description = sanitize($_POST['description']);
    $icon = sanitize($_POST['icon']);
    $color = sanitize($_POST['color']);
    
    $icon_image = null;
    if (isset($_FILES['icon_image']) && $_FILES['icon_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        $filename = $_FILES['icon_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'category_' . time() . '.' . $ext;
            $upload_path = '../uploads/categories/';
            
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['icon_image']['tmp_name'], $upload_path . $new_filename)) {
                $icon_image = $new_filename;
            }
        }
    }
    
    $stmt = $db->prepare("INSERT INTO categories (category_name, description, icon, icon_image, color) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $description, $icon, $icon_image, $color);
    
    if ($stmt->execute()) {
        setFlashMessage('success', 'Kategori berhasil ditambahkan');
    } else {
        setFlashMessage('danger', 'Gagal menambahkan kategori: ' . $stmt->error);
    }
    redirect('manage-categories.php');
}

// Handle edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id = (int)$_POST['category_id'];
    $name = sanitize($_POST['category_name']);
    $description = sanitize($_POST['description']);
    $icon = sanitize($_POST['icon']);
    $color = sanitize($_POST['color']);
    
    // Get current icon_image
    $stmt = $db->prepare("SELECT icon_image FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $icon_image = $current['icon_image'];
    
    // Handle new icon image upload
    if (isset($_FILES['icon_image']) && $_FILES['icon_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        $filename = $_FILES['icon_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'category_' . time() . '.' . $ext;
            $upload_path = '../uploads/categories/';
            
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['icon_image']['tmp_name'], $upload_path . $new_filename)) {
                // Delete old image
                if ($icon_image && file_exists($upload_path . $icon_image)) {
                    unlink($upload_path . $icon_image);
                }
                $icon_image = $new_filename;
            }
        }
    }
    
    $stmt = $db->prepare("UPDATE categories SET category_name = ?, description = ?, icon = ?, icon_image = ?, color = ? WHERE category_id = ?");
    $stmt->bind_param("sssssi", $name, $description, $icon, $icon_image, $color, $id);
    
    if ($stmt->execute()) {
        setFlashMessage('success', 'Kategori berhasil diupdate');
    } else {
        setFlashMessage('danger', 'Gagal mengupdate kategori: ' . $stmt->error);
    }
    redirect('manage-categories.php');
}

// Handle delete category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $id = (int)$_POST['category_id'];
    
    // Get icon_image to delete
    $stmt = $db->prepare("SELECT icon_image FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    
    $stmt = $db->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Delete image file
        if ($category['icon_image'] && file_exists('../uploads/categories/' . $category['icon_image'])) {
            unlink('../uploads/categories/' . $category['icon_image']);
        }
        setFlashMessage('success', 'Kategori berhasil dihapus');
    } else {
        setFlashMessage('danger', 'Gagal menghapus kategori: ' . $stmt->error);
    }
    redirect('manage-categories.php');
}

// Get all categories with accurate topic count
$categories = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM topics t WHERE t.category_id = c.category_id) as topic_count
    FROM categories c 
    ORDER BY c.category_name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill"></i> UTBK Forum - Admin
            </a>
            <div class="ms-auto">
                <a class="btn btn-light btn-sm" href="../index.php">
                    <i class="bi bi-house"></i> Ke Forum
                </a>
            </div>
        </div>
    </nav>

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

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Kelola Kategori</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-circle"></i> Tambah Kategori
            </button>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Icon</th>
                                <th>Nama</th>
                                <th>Deskripsi</th>
                                <th>Warna</th>
                                <th>Topik</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?php echo $cat['category_id']; ?></td>
                                    <td>
                                        <?php if ($cat['icon_image'] && file_exists('../uploads/categories/' . $cat['icon_image'])): ?>
                                            <img src="../uploads/categories/<?php echo htmlspecialchars($cat['icon_image']); ?>" 
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="bi <?php echo $cat['icon']; ?> fs-3 text-<?php echo $cat['color']; ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars(truncate($cat['description'], 50)); ?></td>
                                    <td><span class="badge bg-<?php echo $cat['color']; ?>"><?php echo $cat['color']; ?></span></td>
                                    <td><?php echo $cat['topic_count']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus?')">
                                            <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                                            <button type="submit" name="delete_category" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Kategori</label>
                            <input type="text" name="category_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon Bootstrap (contoh: bi-calculator)</label>
                            <input type="text" name="icon" class="form-control" value="bi-grid" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Icon Gambar (opsional)</label>
                            <input type="file" name="icon_image" class="form-control" accept="image/*">
                            <small class="text-muted">Jika diisi, akan menggantikan icon Bootstrap</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Warna</label>
                            <select name="color" class="form-select" required>
                                <option value="primary">Primary (Biru)</option>
                                <option value="success">Success (Hijau)</option>
                                <option value="danger">Danger (Merah)</option>
                                <option value="warning">Warning (Kuning)</option>
                                <option value="info">Info (Cyan)</option>
                                <option value="secondary">Secondary (Abu)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_category" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Kategori</label>
                            <input type="text" name="category_name" id="edit_category_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon Bootstrap</label>
                            <input type="text" name="icon" id="edit_icon" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Icon Gambar Baru (opsional)</label>
                            <input type="file" name="icon_image" class="form-control" accept="image/*">
                            <div id="current_icon_preview" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Warna</label>
                            <select name="color" id="edit_color" class="form-select" required>
                                <option value="primary">Primary (Biru)</option>
                                <option value="success">Success (Hijau)</option>
                                <option value="danger">Danger (Merah)</option>
                                <option value="warning">Warning (Kuning)</option>
                                <option value="info">Info (Cyan)</option>
                                <option value="secondary">Secondary (Abu)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_category" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editCategory(cat) {
        document.getElementById('edit_category_id').value = cat.category_id;
        document.getElementById('edit_category_name').value = cat.category_name;
        document.getElementById('edit_description').value = cat.description;
        document.getElementById('edit_icon').value = cat.icon;
        document.getElementById('edit_color').value = cat.color;
        
        const preview = document.getElementById('current_icon_preview');
        if (cat.icon_image) {
            preview.innerHTML = '<small class="text-muted">Icon saat ini:</small><br><img src="../uploads/categories/' + cat.icon_image + '" style="width: 60px; height: 60px; object-fit: cover;" class="mt-2">';
        } else {
            preview.innerHTML = '';
        }
        
        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    }
    </script>
</body>
</html>
