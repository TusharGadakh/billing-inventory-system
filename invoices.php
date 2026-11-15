<?php
$page_title = 'Sales Invoices';
require_once __DIR__ . '/includes/header.php';

// Handle invoice cancellation or deletion
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $invId = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();

        // Check if invoice exists and not already cancelled
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invId]);
        $invoice = $stmt->fetch();

        if ($invoice && $invoice['payment_status'] !== 'cancelled') {
            // Restore inventory for all items in this invoice
            $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
            $itemsStmt->execute([$invId]);
            $items = $itemsStmt->fetchAll();

            $restockStmt = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
            $logStmt = $pdo->prepare("
                INSERT INTO stock_movements (product_id, type, quantity, reference_id, reference_type, remarks)
                VALUES (?, 'IN', ?, ?, 'INVOICE_CANCELLED', ?)
            ");

            foreach ($items as $item) {
                $restockStmt->execute([$item['quantity'], $item['product_id']]);
                $logStmt->execute([
                    $item['product_id'],
                    $item['quantity'],
                    $invId,
                    "Stock restored from cancelled invoice {$invoice['invoice_number']}"
                ]);
            }

            // Update status to cancelled
            $cancelStmt = $pdo->prepare("UPDATE invoices SET payment_status = 'cancelled' WHERE id = ?");
            $cancelStmt->execute([$invId]);

            $pdo->commit();
            set_flash('success', "Invoice {$invoice['invoice_number']} cancelled and stock restored to inventory.");
        } else {
            $pdo->rollBack();
            set_flash('warning', 'Invoice not found or already cancelled.');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', 'Error cancelling invoice: ' . $e->getMessage());
    }
    header('Location: invoices.php');
    exit;
}

// Fetch invoices
$invoices = $pdo->query("
    SELECT i.*, c.email AS customer_email 
    FROM invoices i 
    LEFT JOIN customers c ON i.customer_id = c.id 
    ORDER BY i.created_at DESC
")->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h1>Sales Invoices & Billing Records</h1>
        <p>Comprehensive transaction log with real-time stock-backed billing records.</p>
    </div>
    <div class="page-actions">
        <a href="invoice_create.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Sales Invoice
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <input type="text" id="tableSearchInput" class="form-control" placeholder="Search by invoice number, customer name..." style="max-width: 320px;">
        <span class="badge badge-info"><?= count($invoices) ?> Total Invoices</span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer Name</th>
                    <th>Invoice Date</th>
                    <th>Items</th>
                    <th>Subtotal</th>
                    <th>Tax / Disc</th>
                    <th>Grand Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 32px;">
                            No invoices generated yet. Click "New Sales Invoice" to start.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <?php 
                        // Count items in this invoice
                        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?");
                        $cStmt->execute([$inv['id']]);
                        $itemCount = $cStmt->fetchColumn();
                        ?>
                        <tr>
                            <td>
                                <a href="invoice_view.php?id=<?= (int)$inv['id'] ?>" style="font-weight: 700; color: var(--primary); text-decoration: none;">
                                    <?= e($inv['invoice_number']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= e($inv['customer_name']) ?></strong>
                                <?php if ($inv['customer_phone']): ?>
                                    <small style="display: block; color: var(--text-muted); font-size: 11px;"><?= e($inv['customer_phone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= format_date($inv['invoice_date']) ?></td>
                            <td><span class="badge badge-gray"><?= (int)$itemCount ?> items</span></td>
                            <td><?= format_currency($inv['subtotal']) ?></td>
                            <td style="font-size: 12px; color: var(--text-secondary);">
                                +<?= format_currency($inv['tax_amount']) ?> / -<?= format_currency($inv['discount_amount']) ?>
                            </td>
                            <td>
                                <strong style="font-size: 14px; color: var(--text-primary);"><?= format_currency($inv['grand_total']) ?></strong>
                            </td>
                            <td>
                                <span style="text-transform: uppercase; font-size: 11.5px; font-weight: 600; color: var(--text-secondary);">
                                    <?= e(str_replace('_', ' ', $inv['payment_method'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($inv['payment_status'] === 'paid'): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check"></i> Paid</span>
                                <?php elseif ($inv['payment_status'] === 'pending'): ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fa-solid fa-ban"></i> Cancelled</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="invoice_view.php?id=<?= (int)$inv['id'] ?>" class="btn-action" title="View & Print">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <?php if ($inv['payment_status'] !== 'cancelled'): ?>
                                        <a href="javascript:void(0)" onclick="confirmAction('Are you sure you want to cancel invoice <?= e($inv['invoice_number']) ?>? This will restore deducted stock back to inventory.', 'invoices.php?action=cancel&id=<?= (int)$inv['id'] ?>')" class="btn-action delete" title="Cancel Invoice & Restore Stock">
                                            <i class="fa-solid fa-ban"></i>
                                        </a>
                                    <?php endif; ?>
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
