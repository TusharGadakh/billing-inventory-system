<?php
$page_title = 'Stock Adjustment & Restock';
require_once __DIR__ . '/includes/header.php';

$preselectedProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$products = $pdo->query("SELECT id, sku, name, current_stock, unit FROM products ORDER BY name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $type = $_POST['type'] ?? 'IN';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($productId <= 0) {
        $errors[] = 'Please select a valid product.';
    }

    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than zero.';
    }

    if (!in_array($type, ['IN', 'OUT', 'ADJUSTMENT'])) {
        $errors[] = 'Invalid adjustment type.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Fetch current stock with row lock
            $stmt = $pdo->prepare("SELECT id, name, current_stock, unit FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$productId]);
            $prod = $stmt->fetch();

            if (!$prod) {
                throw new Exception("Product does not exist.");
            }

            $currentStock = (int)$prod['current_stock'];
            $newStock = $currentStock;

            if ($type === 'IN') {
                $newStock = $currentStock + $quantity;
            } elseif ($type === 'OUT') {
                if ($quantity > $currentStock) {
                    throw new Exception("Cannot deduct {$quantity} items. Only {$currentStock} available in stock.");
                }
                $newStock = $currentStock - $quantity;
            } elseif ($type === 'ADJUSTMENT') {
                // In adjustment, quantity is the new target count or absolute change
                // Let's treat it as the new absolute verified physical stock
                $diff = $quantity - $currentStock;
                $newStock = $quantity;
                $remarks = ($remarks ? $remarks . ' ' : '') . "(Audited from {$currentStock} to {$newStock})";
            }

            // Update product stock
            $upd = $pdo->prepare("UPDATE products SET current_stock = ? WHERE id = ?");
            $upd->execute([$newStock, $productId]);

            // Log movement
            $log = $pdo->prepare("
                INSERT INTO stock_movements (product_id, type, quantity, reference_id, reference_type, remarks)
                VALUES (?, ?, ?, NULL, 'MANUAL_ADJUSTMENT', ?)
            ");
            $log->execute([$productId, $type, $quantity, $remarks]);

            $pdo->commit();
            set_flash('success', "Stock for '{$prod['name']}' successfully updated from {$currentStock} to {$newStock} {$prod['unit']}.");
            header('Location: stock_adjust.php?product_id=' . $productId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Fetch movement history for display
$historyQuery = "
    SELECT sm.*, p.name AS product_name, p.sku, p.unit 
    FROM stock_movements sm 
    JOIN products p ON sm.product_id = p.id 
";
if ($preselectedProductId > 0) {
    $historyQuery .= " WHERE sm.product_id = {$preselectedProductId} ";
}
$historyQuery .= " ORDER BY sm.created_at DESC LIMIT 15";
$movements = $pdo->query($historyQuery)->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h1>Stock Adjustment & Inventory Inflow</h1>
        <p>Restock supplies, record damaged goods, or perform physical inventory audits.</p>
    </div>
    <div class="page-actions">
        <a href="products.php" class="btn btn-outline">
            <i class="fa-solid fa-boxes-packing"></i> View All Products
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

<div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px;">
    <!-- Adjustment Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-dolly"></i> Record Inventory Adjustment</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="stock_adjust.php">
                <div class="form-group">
                    <label>Select Product <span class="required">*</span></label>
                    <select name="product_id" class="form-control" required id="adjustProductSelect">
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" 
                                data-stock="<?= (int)$p['current_stock'] ?>" 
                                data-unit="<?= e($p['unit']) ?>"
                                <?= $preselectedProductId === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> (<?= e($p['sku']) ?>) - Current: <?= (int)$p['current_stock'] ?> <?= e($p['unit']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Adjustment Type <span class="required">*</span></label>
                    <select name="type" class="form-control" required>
                        <option value="IN">Stock In / Purchase Restock (+)</option>
                        <option value="OUT">Stock Out / Damage / Loss (-)</option>
                        <option value="ADJUSTMENT">Physical Audit Count (= Target)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" class="form-control" min="1" required placeholder="Number of units">
                </div>

                <div class="form-group">
                    <label>Remarks / Reference Reason</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="e.g. Shipment PO-8849 received from vendor, or Damaged during transit"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fa-solid fa-check"></i> Apply Stock Update
                </button>
            </form>
        </div>
    </div>

    <!-- Movement Audit Trail -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-list-check"></i> Audit History (Latest 15)</h3>
            <?php if ($preselectedProductId > 0): ?>
                <a href="stock_adjust.php" class="btn btn-sm btn-outline">Show All Products</a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                No stock history records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 12px; color: var(--text-secondary);">
                                    <?= date('M d, Y H:i', strtotime($m['created_at'])) ?>
                                </td>
                                <td>
                                    <strong><?= e($m['product_name']) ?></strong>
                                    <small style="display: block; color: var(--text-muted); font-size: 11px;"><?= e($m['sku']) ?></small>
                                </td>
                                <td>
                                    <?php if ($m['type'] === 'IN'): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-arrow-down"></i> IN</span>
                                    <?php elseif ($m['type'] === 'OUT'): ?>
                                        <span class="badge badge-danger"><i class="fa-solid fa-arrow-up"></i> OUT</span>
                                    <?php else: ?>
                                        <span class="badge badge-info"><i class="fa-solid fa-sliders"></i> AUDIT</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= (int)$m['quantity'] ?> <?= e($m['unit']) ?></strong></td>
                                <td style="font-size: 12.5px; color: var(--text-secondary);"><?= e($m['remarks'] ?: $m['reference_type']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
