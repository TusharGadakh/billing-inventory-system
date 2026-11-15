<?php
$page_title = 'Add New Product';
require_once __DIR__ . '/includes/header.php';

$suggestedSku = generate_sku($pdo, 'PRD');
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $initialStock = (int)($_POST['initial_stock'] ?? 0);
    $minStockAlert = (int)($_POST['min_stock_alert'] ?? 5);
    $unit = trim($_POST['unit'] ?? 'pcs') ?: 'pcs';
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($sku)) {
        $errors[] = 'SKU is required.';
    } else {
        // Check for duplicate SKU
        $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $skuCheck->execute([$sku]);
        if ($skuCheck->fetch()) {
            $errors[] = 'A product with this SKU already exists.';
        }
    }

    if (empty($name)) {
        $errors[] = 'Product name is required.';
    }

    if ($sellingPrice < 0) {
        $errors[] = 'Selling price cannot be negative.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare("
                INSERT INTO products (sku, name, category_id, cost_price, selling_price, current_stock, min_stock_alert, unit, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $sku, $name, $categoryId, $costPrice, $sellingPrice, $initialStock, $minStockAlert, $unit, $status
            ]);
            $productId = $pdo->lastInsertId();

            // If initial stock was given, record movement log
            if ($initialStock > 0) {
                $mvStmt = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, type, quantity, reference_id, reference_type, remarks)
                    VALUES (?, 'IN', ?, NULL, 'OPENING_STOCK', 'Initial opening stock intake')
                ");
                $mvStmt->execute([$productId, $initialStock]);
            }

            $pdo->commit();
            set_flash('success', "Product '{$name}' created successfully with SKU {$sku}.");
            header('Location: products.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add New Product</h1>
        <p>Define product specifications, cost/sales pricing, and opening inventory.</p>
    </div>
    <div class="page-actions">
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
        <h3><i class="fa-solid fa-cube"></i> Product Specifications</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="product_add.php">
            <div class="form-grid">
                <div class="form-group">
                    <label>SKU / Product Code <span class="required">*</span></label>
                    <input type="text" name="sku" class="form-control" value="<?= e($_POST['sku'] ?? $suggestedSku) ?>" required>
                    <small class="form-text">Unique barcode or stock keeping unit.</small>
                </div>

                <div class="form-group">
                    <label>Product Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Wireless Mouse" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">-- Select Category (Optional) --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Unit of Measurement</label>
                    <input type="text" name="unit" class="form-control" value="<?= e($_POST['unit'] ?? 'pcs') ?>" placeholder="pcs, kg, box, pack">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Cost Price ($)</label>
                    <input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="<?= e($_POST['cost_price'] ?? '0.00') ?>">
                    <small class="form-text">Purchase or acquisition price per unit.</small>
                </div>

                <div class="form-group">
                    <label>Selling Price ($) <span class="required">*</span></label>
                    <input type="number" step="0.01" min="0" name="selling_price" class="form-control" value="<?= e($_POST['selling_price'] ?? '0.00') ?>" required>
                    <small class="form-text">Base retail billing price.</small>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Opening / Initial Stock</label>
                    <input type="number" min="0" name="initial_stock" class="form-control" value="<?= e($_POST['initial_stock'] ?? '0') ?>">
                    <small class="form-text">Recorded in stock movement audit log.</small>
                </div>

                <div class="form-group">
                    <label>Low Stock Warning Threshold</label>
                    <input type="number" min="0" name="min_stock_alert" class="form-control" value="<?= e($_POST['min_stock_alert'] ?? '5') ?>">
                    <small class="form-text">Triggers dashboard warning when stock drops to or below this level.</small>
                </div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" style="max-width: 250px;">
                    <option value="active" <?= (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : '' ?>>Active (Available for billing)</option>
                    <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : '' ?>>Inactive (Hidden from POS)</option>
                </select>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Save Product
                </button>
                <a href="products.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
