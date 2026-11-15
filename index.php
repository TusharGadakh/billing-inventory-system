<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Fetch Dashboard Metrics
try {
    // 1. Total Revenue
    $revStmt = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) AS total_revenue FROM invoices WHERE payment_status = 'paid'");
    $totalRevenue = (float)$revStmt->fetch()['total_revenue'];

    // 2. Total Invoices Count
    $invStmt = $pdo->query("SELECT COUNT(*) AS total_invoices FROM invoices");
    $totalInvoices = (int)$invStmt->fetch()['total_invoices'];

    // 3. Total Products Count
    $prdStmt = $pdo->query("SELECT COUNT(*) AS total_products FROM products WHERE status = 'active'");
    $totalProducts = (int)$prdStmt->fetch()['total_products'];

    // 4. Low Stock Alerts Count
    $lowStmt = $pdo->query("SELECT COUNT(*) AS low_stock_count FROM products WHERE current_stock <= min_stock_alert AND status = 'active'");
    $lowStockCount = (int)$lowStmt->fetch()['low_stock_count'];

    // 5. Fetch Low Stock Items for Warning Table
    $lowStockProducts = $pdo->query("
        SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.current_stock <= p.min_stock_alert AND p.status = 'active'
        ORDER BY p.current_stock ASC 
        LIMIT 5
    ")->fetchAll();

    // 6. Recent Invoices
    $recentInvoices = $pdo->query("
        SELECT i.*, c.name AS registered_customer_name 
        FROM invoices i 
        LEFT JOIN customers c ON i.customer_id = c.id 
        ORDER BY i.created_at DESC 
        LIMIT 6
    ")->fetchAll();

    // 7. Recent Stock Movements
    $recentMovements = $pdo->query("
        SELECT sm.*, p.name AS product_name, p.sku 
        FROM stock_movements sm 
        JOIN products p ON sm.product_id = p.id 
        ORDER BY sm.created_at DESC 
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    die("Query Error: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Overview & Metrics</h1>
        <p>Real-time analytics for sales revenue, inventory health, and billing operations.</p>
    </div>
    <div class="page-actions">
        <a href="stock_adjust.php" class="btn btn-outline">
            <i class="fa-solid fa-truck-ramp-box"></i> Adjust Stock
        </a>
        <a href="invoice_create.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Create Invoice
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value"><?= format_currency($totalRevenue) ?></div>
            <div class="stat-sub">From paid customer invoices</div>
        </div>
        <div class="stat-icon success">
            <i class="fa-solid fa-dollar-sign"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">Invoices Prepared</div>
            <div class="stat-value"><?= number_format($totalInvoices) ?></div>
            <div class="stat-sub">Automated billing transactions</div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">Active Products</div>
            <div class="stat-value"><?= number_format($totalProducts) ?></div>
            <div class="stat-sub">Tracked catalog inventory</div>
        </div>
        <div class="stat-icon secondary">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <div class="stat-label">Low Stock Alerts</div>
            <div class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--danger)' : 'var(--text-primary)' ?>;">
                <?= number_format($lowStockCount) ?>
            </div>
            <div class="stat-sub"><?= $lowStockCount > 0 ? 'Items need replenishment' : 'All stocks optimal' ?></div>
        </div>
        <div class="stat-icon <?= $lowStockCount > 0 ? 'danger' : 'success' ?>">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
    </div>
</div>

<?php if ($lowStockCount > 0): ?>
<!-- Low Stock Warning Banner -->
<div class="card" style="border-left: 4px solid var(--danger);">
    <div class="card-header" style="background: var(--danger-light);">
        <h3 style="color: #991b1b;">
            <i class="fa-solid fa-triangle-exclamation"></i> Critical Stock Replenishment Warning
        </h3>
        <a href="stock_adjust.php" class="btn btn-sm btn-danger">
            <i class="fa-solid fa-boxes-packing"></i> Restock Now
        </a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Current Stock</th>
                    <th>Min Alert Level</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lowStockProducts as $lp): ?>
                    <tr>
                        <td><code><?= e($lp['sku']) ?></code></td>
                        <td><strong><?= e($lp['name']) ?></strong></td>
                        <td><?= e($lp['category_name'] ?: 'Uncategorized') ?></td>
                        <td>
                            <strong style="color: <?= $lp['current_stock'] == 0 ? 'var(--danger)' : 'var(--warning)' ?>;">
                                <?= (int)$lp['current_stock'] ?> <?= e($lp['unit']) ?>
                            </strong>
                        </td>
                        <td><?= (int)$lp['min_stock_alert'] ?> <?= e($lp['unit']) ?></td>
                        <td><?= get_stock_badge($lp['current_stock'], $lp['min_stock_alert']) ?></td>
                        <td>
                            <a href="stock_adjust.php?product_id=<?= (int)$lp['id'] ?>" class="btn btn-sm btn-outline">
                                <i class="fa-solid fa-arrow-up"></i> Restock
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 24px;">
    <!-- Recent Invoices Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Sales Invoices</h3>
            <a href="invoices.php" class="btn btn-sm btn-outline">View All Invoices</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentInvoices)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                No invoices generated yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td>
                                    <a href="invoice_view.php?id=<?= (int)$inv['id'] ?>" style="font-weight: 600; color: var(--primary); text-decoration: none;">
                                        <?= e($inv['invoice_number']) ?>
                                    </a>
                                </td>
                                <td><?= e($inv['customer_name']) ?></td>
                                <td><?= format_date($inv['invoice_date']) ?></td>
                                <td><strong><?= format_currency($inv['grand_total']) ?></strong></td>
                                <td>
                                    <?php if ($inv['payment_status'] === 'paid'): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Paid</span>
                                    <?php elseif ($inv['payment_status'] === 'pending'): ?>
                                        <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Cancelled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="invoice_view.php?id=<?= (int)$inv['id'] ?>" class="btn-action" title="View & Print">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stock Movement Audit Log -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-shoe-prints"></i> Real-time Stock Movements</h3>
        </div>
        <div class="card-body" style="padding: 12px 18px;">
            <?php if (empty($recentMovements)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px;">No movements logged yet.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($recentMovements as $mv): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                            <div>
                                <strong style="font-size: 13px; color: var(--text-primary); display: block;">
                                    <?= e($mv['product_name']) ?>
                                </strong>
                                <small style="color: var(--text-muted);">
                                    <?= e($mv['remarks'] ?: $mv['reference_type']) ?> &bull; <?= date('M d, H:i', strtotime($mv['created_at'])) ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($mv['type'] === 'IN'): ?>
                                    <span class="badge badge-success">+<?= (int)$mv['quantity'] ?> IN</span>
                                <?php elseif ($mv['type'] === 'OUT'): ?>
                                    <span class="badge badge-danger">-<?= (int)$mv['quantity'] ?> OUT</span>
                                <?php else: ?>
                                    <span class="badge badge-info">&plusmn;<?= (int)$mv['quantity'] ?> ADJ</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
