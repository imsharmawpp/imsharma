<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();
$pageTitle = 'Products';

// Delete
if (!empty($_GET['delete'])) {
    Database::exec("UPDATE products SET is_active = 0 WHERE id = ?", [intval($_GET['delete'])]);
    header('Location: products.php?deleted=1');
    exit;
}

// Add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => clean($_POST['title']),
        'slug' => slugify($_POST['title']),
        'category' => clean($_POST['category']),
        'price' => floatval($_POST['price']),
        'original_price' => floatval($_POST['original_price'] ?: 0),
        'short_description' => clean($_POST['short_description']),
        'description' => clean($_POST['description']),
        'inventory' => intval($_POST['inventory'] ?: 100),
        'icon' => clean($_POST['icon'] ?: 'gem'),
        'badge' => clean($_POST['badge']),
        'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
        'is_active' => 1,
        'rating' => floatval($_POST['rating'] ?: 4.5),
    ];

    if (!empty($_POST['id'])) {
        Database::update('products', intval($_POST['id']), $data);
    } else {
        Database::insert('products', $data);
    }
    header('Location: products.php?saved=1');
    exit;
}

$products = Database::all("SELECT * FROM products WHERE is_active = 1 ORDER BY id DESC");
$editing = null;
if (!empty($_GET['edit'])) {
    $editing = Database::row("SELECT * FROM products WHERE id = ?", [intval($_GET['edit'])]);
}

include '_header.php';
?>

<?php if (!empty($_GET['saved'])): ?>
    <div style="background: #10B981; color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 16px;">
        <i class="fas fa-check-circle"></i> Product saved!
    </div>
<?php endif; ?>

<div class="admin-card">
    <h2 style="margin-bottom: 16px;"><?= $editing ? 'Edit Product' : 'Add New Product' ?></h2>
    <form method="POST">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label>Product Title *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($editing['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category" required>
                    <?php foreach (['pyramid','crystal','brass','copper','yantra','rudraksha','lamp','plant'] as $c): ?>
                        <option value="<?= $c ?>" <?= ($editing['category'] ?? '') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Price (₹) *</label>
                <input type="number" name="price" required step="0.01" value="<?= $editing['price'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Original Price (₹)</label>
                <input type="number" name="original_price" step="0.01" value="<?= $editing['original_price'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Inventory</label>
                <input type="number" name="inventory" value="<?= $editing['inventory'] ?? 100 ?>">
            </div>
            <div class="form-group">
                <label>Icon (Font Awesome)</label>
                <input type="text" name="icon" value="<?= htmlspecialchars($editing['icon'] ?? 'gem') ?>" placeholder="gem, dharmachakra, lightbulb...">
            </div>
            <div class="form-group">
                <label>Badge (e.g. Bestseller)</label>
                <input type="text" name="badge" value="<?= htmlspecialchars($editing['badge'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Rating (1-5)</label>
                <input type="number" name="rating" min="1" max="5" step="0.1" value="<?= $editing['rating'] ?? 4.5 ?>">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Short Description</label>
                <input type="text" name="short_description" value="<?= htmlspecialchars($editing['short_description'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Full Description</label>
                <textarea name="description" rows="4"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_featured" <?= !empty($editing['is_featured']) ? 'checked' : '' ?>> Featured Product</label>
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> <?= $editing ? 'Update' : 'Add' ?> Product</button>
        <?php if ($editing): ?><a href="products.php" class="btn btn-outline">Cancel</a><?php endif; ?>
    </form>
</div>

<div class="admin-card">
    <h2 style="margin-bottom: 16px;">All Products (<?= count($products) ?>)</h2>
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Price</th><th>Stock</th><th>Featured</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>#<?= $p['id'] ?></td>
                    <td>
                        <i class="fas fa-<?= htmlspecialchars($p['icon']) ?>" style="color: #D4AF37;"></i>
                        <strong><?= htmlspecialchars($p['title']) ?></strong>
                    </td>
                    <td><?= ucfirst($p['category']) ?></td>
                    <td>
                        <strong>₹<?= $p['price'] ?></strong>
                        <?php if ($p['original_price']): ?><br><small><s>₹<?= $p['original_price'] ?></s></small><?php endif; ?>
                    </td>
                    <td><?= $p['inventory'] ?></td>
                    <td><?= !empty($p['is_featured']) ? '★ Featured' : '-' ?></td>
                    <td class="actions">
                        <a href="?edit=<?= $p['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i></a>
                        <a href="?delete=<?= $p['id'] ?>" onclick="return confirmDelete('Delete this product?')" class="action-btn delete"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>
