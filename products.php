<?php
$page_title = 'Product Inventory';
require_once __DIR__ . '/includes/header.php';

// Handle product deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = (int)$_GET['id'];
    try {
        // Check if product is referenced in invoice items
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE product_id = ?");
        $checkStmt->execute([$deleteId]);
        if ($checkStmt->fetchColumn() > 0) {
            set_flash('danger', 'Cannot delete this product because it is linked to existing sales invoices. You can set its status to Inactive instead.');
        } else {
            $delStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $delStmt->execute([$deleteId]);
            set_flash('success', 'Product removed successfully.');
        }
    } catch (PDOException $e) {
        set_flash('danger', 'Error deleting product: ' . $e->getMessage());
    }
    header('Location: products.php');
    exit;
}

// Fetch categories for filtering
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Category filter
$catFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$query = "
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
";

$params = [];
if ($catFilter > 0) {
    $query .= " WHERE p.category_id = ? ";
    $params[] = $catFilter;
}
$query .= " ORDER BY p.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h1>Product Catalog & Stock</h1>
        <p>Manage product master data, selling prices, alert thresholds, and live inventory.</p>
    </div>
    <div class="page-actions">
        <a href="stock_adjust.php" class="btn btn-outline">
            <i class="fa-solid fa-truck-ramp-box"></i> Adjust / Restock
        </a>
        <a href="product_add.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add New Product
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 280px;">
            <input type="text" id="tableSearchInput" class="form-control" placeholder="Search by SKU, Product Name..." style="max-width: 320px;">
            <form method="GET" action="products.php" style="display: flex; align-items: center; gap: 8px;">
                <select name="category" class="form-control" onchange="this.form.submit()" style="max-width: 200px;">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($catFilter > 0): ?>
                    <a href="products.php" class="btn btn-sm btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div>
            <span class="badge badge-info"><?= count($products) ?> Total Items</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Cost</th>
                    <th>Selling Price</th>
                    <th>Current Stock</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 32px;">
                            No products found in this view.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><code><?= e($p['sku']) ?></code></td>
                            <td>
                                <strong><?= e($p['name']) ?></strong>
                            </td>
                            <td><?= e($p['category_name'] ?: 'Uncategorized') ?></td>
                            <td style="color: var(--text-secondary);"><?= format_currency($p['cost_price']) ?></td>
                            <td><strong style="color: var(--text-primary);"><?= format_currency($p['selling_price']) ?></strong></td>
                            <td>
                                <?= get_stock_badge($p['current_stock'], $p['min_stock_alert']) ?>
                                <small style="display: block; color: var(--text-muted); font-size: 11px; margin-top: 2px;">
                                    Min alert: <?= (int)$p['min_stock_alert'] ?> <?= e($p['unit']) ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($p['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="stock_adjust.php?product_id=<?= (int)$p['id'] ?>" class="btn-action" title="Stock Adjustment">
                                        <i class="fa-solid fa-boxes-packing"></i>
                                    </a>
                                    <a href="product_edit.php?id=<?= (int)$p['id'] ?>" class="btn-action" title="Edit Product">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="javascript:void(0)" onclick="confirmAction('Are you sure you want to delete product <?= e($p['name']) ?>?', 'products.php?action=delete&id=<?= (int)$p['id'] ?>')" class="btn-action delete" title="Delete Product">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
