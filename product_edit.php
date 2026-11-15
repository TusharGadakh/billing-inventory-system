<?php
$page_title = 'Edit Product';
require_once __DIR__ . '/includes/header.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Product not found.');
    header('Location: products.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $minStockAlert = (int)($_POST['min_stock_alert'] ?? 5);
    $unit = trim($_POST['unit'] ?? 'pcs') ?: 'pcs';
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($sku)) {
        $errors[] = 'SKU is required.';
    } else {
        $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $skuCheck->execute([$sku, $productId]);
        if ($skuCheck->fetch()) {
            $errors[] = 'Another product already uses this SKU.';
        }
    }

    if (empty($name)) {
        $errors[] = 'Product name is required.';
    }

    if (empty($errors)) {
        try {
            $updStmt = $pdo->prepare("
                UPDATE products 
                SET sku = ?, name = ?, category_id = ?, cost_price = ?, selling_price = ?, min_stock_alert = ?, unit = ?, status = ?
                WHERE id = ?
            ");
            $updStmt->execute([
                $sku, $name, $categoryId, $costPrice, $sellingPrice, $minStockAlert, $unit, $status, $productId
            ]);

            set_flash('success', "Product '{$name}' updated successfully.");
            header('Location: products.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Edit Product: <?= e($product['name']) ?></h1>
        <p>Update product master catalog parameters.</p>
    </div>
    <div class="page-actions">
        <a href="stock_adjust.php?product_id=<?= (int)$product['id'] ?>" class="btn btn-outline">
            <i class="fa-solid fa-truck-ramp-box"></i> Adjust Stock Level
        </a>
        <a href="products.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to Products
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash-alert alert-danger" style="margin: 0 0 24px 0;">
        <div class="alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="alert-content">
            <ul style="margin-left: 18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 850px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-pen-to-square"></i> Modify Product Details</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="product_edit.php?id=<?= (int)$product['id'] ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>SKU / Product Code <span class="required">*</span></label>
                    <input type="text" name="sku" class="form-control" value="<?= e($_POST['sku'] ?? $product['sku']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Product Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $product['name']) ?>" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">-- Select Category (Optional) --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= ((isset($_POST['category_id']) ? (int)$_POST['category_id'] : (int)$product['category_id']) === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Unit of Measurement</label>
                    <input type="text" name="unit" class="form-control" value="<?= e($_POST['unit'] ?? $product['unit']) ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Cost Price ($)</label>
                    <input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="<?= e($_POST['cost_price'] ?? $product['cost_price']) ?>">
                </div>

                <div class="form-group">
                    <label>Selling Price ($) <span class="required">*</span></label>
                    <input type="number" step="0.01" min="0" name="selling_price" class="form-control" value="<?= e($_POST['selling_price'] ?? $product['selling_price']) ?>" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Current Stock (Read-Only)</label>
                    <input type="text" class="form-control" value="<?= (int)$product['current_stock'] ?> <?= e($product['unit']) ?>" readonly>
                    <small class="form-text">To restock or adjust physical counts, use <a href="stock_adjust.php?product_id=<?= (int)$product['id'] ?>">Stock Adjustment</a>.</small>
                </div>

                <div class="form-group">
                    <label>Low Stock Warning Threshold</label>
                    <input type="number" min="0" name="min_stock_alert" class="form-control" value="<?= e($_POST['min_stock_alert'] ?? $product['min_stock_alert']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" style="max-width: 250px;">
                    <option value="active" <?= ((isset($_POST['status']) ? $_POST['status'] : $product['status']) === 'active') ? 'selected' : '' ?>>Active (Available for billing)</option>
                    <option value="inactive" <?= ((isset($_POST['status']) ? $_POST['status'] : $product['status']) === 'inactive') ? 'selected' : '' ?>>Inactive (Hidden from POS)</option>
                </select>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Update Product
                </button>
                <a href="products.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
