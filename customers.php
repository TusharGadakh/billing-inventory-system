<?php
$page_title = 'Customers Directory';
require_once __DIR__ . '/includes/header.php';

// Fetch customers with purchase totals
$customers = $pdo->query("
    SELECT c.*, 
           COUNT(i.id) AS total_invoices, 
           COALESCE(SUM(i.grand_total), 0) AS total_spent 
    FROM customers c 
    LEFT JOIN invoices i ON c.id = i.customer_id 
    GROUP BY c.id 
    ORDER BY c.name ASC
")->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h1>Customers & Clients</h1>
        <p>Maintain customer profiles, contact directories, and historical purchase billing.</p>
    </div>
    <div class="page-actions">
        <a href="customer_add.php" class="btn btn-primary">
            <i class="fa-solid fa-user-plus"></i> Add New Customer
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <input type="text" id="tableSearchInput" class="form-control" placeholder="Search by customer name, phone, or email..." style="max-width: 350px;">
        <span class="badge badge-info"><?= count($customers) ?> Registered Customers</span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Billing Address</th>
                    <th>Invoices Count</th>
                    <th>Total Spent</th>
                    <th>Created On</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                            No customers registered yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <strong><?= e($c['name']) ?></strong>
                            </td>
                            <td><?= e($c['phone'] ?: '-') ?></td>
                            <td>
                                <?php if ($c['email']): ?>
                                    <a href="mailto:<?= e($c['email']) ?>" style="color: var(--primary); text-decoration: none;">
                                        <?= e($c['email']) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-secondary); max-width: 250px;"><?= e($c['address'] ?: '-') ?></td>
                            <td>
                                <span class="badge badge-gray"><?= (int)$c['total_invoices'] ?> invoices</span>
                            </td>
                            <td>
                                <strong><?= format_currency($c['total_spent']) ?></strong>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted);">
                                <?= format_date($c['created_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
