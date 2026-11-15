<?php
$page_title = 'Invoice Details';
$extra_css = 'assets/css/print.css';
require_once __DIR__ . '/includes/header.php';

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Invoice
$stmt = $pdo->prepare("
    SELECT i.*, c.email AS customer_email, c.address AS customer_address 
    FROM invoices i 
    LEFT JOIN customers c ON i.customer_id = c.id 
    WHERE i.id = ?
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    set_flash('danger', 'Invoice not found.');
    header('Location: invoices.php');
    exit;
}

// Fetch line items
$itemsStmt = $pdo->prepare("
    SELECT ii.*, p.sku, p.unit 
    FROM invoice_items ii 
    LEFT JOIN products p ON ii.product_id = p.id 
    WHERE ii.invoice_id = ?
");
$itemsStmt->execute([$invoiceId]);
$items = $itemsStmt->fetchAll();
?>

<div class="page-header no-print">
    <div class="page-title">
        <h1>Invoice: <?= e($invoice['invoice_number']) ?></h1>
        <p>Issued on <?= format_date($invoice['invoice_date']) ?> &bull; Status: <?= strtoupper($invoice['payment_status']) ?></p>
    </div>
    <div class="page-actions">
        <a href="invoices.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to Invoices
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa-solid fa-print"></i> Print / Save as PDF
        </button>
    </div>
</div>

<div class="card invoice-card" style="max-width: 850px; margin: 0 auto 32px; background: #fff; padding: 40px; border-radius: var(--radius-md);">
    <!-- Invoice Header -->
    <div class="invoice-header" style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--border-color); padding-bottom: 24px; margin-bottom: 28px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <div style="width: 34px; height: 34px; background: var(--primary); color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <h2 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.5px;">BillTrack Enterprises</h2>
            </div>
            <p style="color: var(--text-secondary); font-size: 13px; line-height: 1.5; margin: 0;">
                742 Evergreen Business Park, Suite 500<br>
                San Jose, CA 95110<br>
                Email: billing@billtrack.local &bull; Tel: +1 (800) 555-0199
            </p>
        </div>

        <div style="text-align: right;">
            <h1 style="font-size: 26px; font-weight: 800; color: var(--primary); margin: 0; text-transform: uppercase; letter-spacing: 1px;">TAX INVOICE</h1>
            <div style="margin-top: 8px; font-size: 14px;">
                <strong style="color: var(--text-primary); font-size: 16px;"><?= e($invoice['invoice_number']) ?></strong>
            </div>
            <div style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">
                Date: <strong><?= format_date($invoice['invoice_date']) ?></strong>
            </div>
            <div style="margin-top: 8px;">
                <?php if ($invoice['payment_status'] === 'paid'): ?>
                    <span class="badge badge-success" style="font-size: 12px; padding: 6px 14px;"><i class="fa-solid fa-circle-check"></i> PAID IN FULL</span>
                <?php elseif ($invoice['payment_status'] === 'pending'): ?>
                    <span class="badge badge-warning" style="font-size: 12px; padding: 6px 14px;"><i class="fa-solid fa-clock"></i> PAYMENT PENDING</span>
                <?php else: ?>
                    <span class="badge badge-danger" style="font-size: 12px; padding: 6px 14px;"><i class="fa-solid fa-ban"></i> CANCELLED</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bill To Details -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">
        <div style="background: var(--bg-main); padding: 18px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); letter-spacing: 0.5px; margin-bottom: 6px;">Billed To:</div>
            <div style="font-size: 15px; font-weight: 700; color: var(--text-primary);"><?= e($invoice['customer_name']) ?></div>
            <?php if ($invoice['customer_phone']): ?>
                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 3px;">
                    <i class="fa-solid fa-phone" style="font-size: 11px; width: 14px;"></i> <?= e($invoice['customer_phone']) ?>
                </div>
            <?php endif; ?>
            <?php if ($invoice['customer_email']): ?>
                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 3px;">
                    <i class="fa-solid fa-envelope" style="font-size: 11px; width: 14px;"></i> <?= e($invoice['customer_email']) ?>
                </div>
            <?php endif; ?>
            <?php if ($invoice['customer_address']): ?>
                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 3px;">
                    <i class="fa-solid fa-location-dot" style="font-size: 11px; width: 14px;"></i> <?= nl2br(e($invoice['customer_address'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="background: var(--bg-main); padding: 18px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); letter-spacing: 0.5px; margin-bottom: 6px;">Payment Information:</div>
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                <span style="color: var(--text-secondary);">Payment Method:</span>
                <strong style="text-transform: uppercase;"><?= e(str_replace('_', ' ', $invoice['payment_method'])) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                <span style="color: var(--text-secondary);">Payment Status:</span>
                <strong><?= strtoupper($invoice['payment_status']) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                <span style="color: var(--text-secondary);">Currency:</span>
                <strong>USD ($)</strong>
            </div>
        </div>
    </div>

    <!-- Itemized Table -->
    <div style="margin-bottom: 28px;">
        <table class="data-table invoice-table" style="width: 100%; border: 1px solid var(--border-color);">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="width: 8%;">#</th>
                    <th>Item Description</th>
                    <th style="width: 15%; text-align: center;">SKU</th>
                    <th style="width: 12%; text-align: right;">Qty</th>
                    <th style="width: 18%; text-align: right;">Unit Price</th>
                    <th style="width: 18%; text-align: right;">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $idx = 1; foreach ($items as $item): ?>
                    <tr>
                        <td><?= $idx++ ?></td>
                        <td>
                            <strong><?= e($item['product_name']) ?></strong>
                        </td>
                        <td style="text-align: center;"><code><?= e($item['sku'] ?: '-') ?></code></td>
                        <td style="text-align: right;"><?= (int)$item['quantity'] ?> <?= e($item['unit'] ?: '') ?></td>
                        <td style="text-align: right;"><?= format_currency($item['unit_price']) ?></td>
                        <td style="text-align: right; font-weight: 600;"><?= format_currency($item['total_price']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary Box -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 28px;">
        <div style="width: 320px;">
            <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 13.5px; color: var(--text-secondary);">
                <span>Subtotal:</span>
                <span style="font-weight: 600; color: var(--text-primary);"><?= format_currency($invoice['subtotal']) ?></span>
            </div>

            <?php if ((float)$invoice['discount_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 13.5px; color: var(--success);">
                    <span>Discount:</span>
                    <span>-<?= format_currency($invoice['discount_amount']) ?></span>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 13.5px; color: var(--text-secondary);">
                <span>Tax (<?= number_format($invoice['tax_rate'], 1) ?>%):</span>
                <span style="font-weight: 600; color: var(--text-primary);"><?= format_currency($invoice['tax_amount']) ?></span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 12px 0 6px; font-size: 18px; font-weight: 800; border-top: 2px solid var(--border-color); color: var(--text-primary); margin-top: 8px;">
                <span>Total Amount:</span>
                <span style="color: var(--primary);"><?= format_currency($invoice['grand_total']) ?></span>
            </div>
        </div>
    </div>

    <!-- Footer Notes & Terms -->
    <div class="invoice-footer" style="border-top: 1px dashed var(--border-color); padding-top: 20px; font-size: 12.5px; color: var(--text-secondary);">
        <strong>Terms & Conditions:</strong>
        <p style="margin: 4px 0 10px;">
            <?= $invoice['notes'] ? nl2br(e($invoice['notes'])) : 'Payment is due within 15 days of invoice issue date. Thank you for your continued partnership!' ?>
        </p>
        <p style="font-size: 11.5px; color: var(--text-muted); text-align: center; margin: 16px 0 0;">
            This is a computer-generated tax invoice. Real-time inventory deduplication verified.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
