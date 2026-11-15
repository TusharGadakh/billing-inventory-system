<?php
$page_title = 'Create Sales Invoice';
require_once __DIR__ . '/includes/header.php';

// Generate default sequential invoice number
$nextInvoiceNo = generate_invoice_number($pdo);

// Fetch customers
$customers = $pdo->query("SELECT id, name, phone, email FROM customers ORDER BY name ASC")->fetchAll();

// Fetch active products that have available stock or active status
$products = $pdo->query("
    SELECT id, sku, name, selling_price, current_stock, unit 
    FROM products 
    WHERE status = 'active' 
    ORDER BY name ASC
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
    $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $invoiceDate = !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d');
    $taxRate = (float)($_POST['tax_rate'] ?? 0);
    $discountAmount = (float)($_POST['discount_amount'] ?? 0);
    $paymentStatus = in_array($_POST['payment_status'] ?? '', ['paid', 'pending', 'cancelled']) ? $_POST['payment_status'] : 'paid';
    $paymentMethod = in_array($_POST['payment_method'] ?? '', ['cash', 'card', 'upi', 'bank_transfer', 'other']) ? $_POST['payment_method'] : 'cash';
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    // If customer selected from dropdown, fill name/phone if empty
    if ($customerId) {
        $cStmt = $pdo->prepare("SELECT name, phone FROM customers WHERE id = ?");
        $cStmt->execute([$customerId]);
        $cust = $cStmt->fetch();
        if ($cust) {
            $customerName = $cust['name'];
            $customerPhone = $cust['phone'];
        }
    }

    if (empty($customerName)) {
        $customerName = 'Walk-in Customer';
    }

    if (empty($invoiceNumber)) {
        $errors[] = 'Invoice number is required.';
    }

    if (empty($items) || !is_array($items)) {
        $errors[] = 'Please add at least one line item to the invoice.';
    }

    if (empty($errors)) {
        try {
            // Start strict transaction for atomic stock deduction and billing
            $pdo->beginTransaction();

            // Check if invoice number is duplicate
            $invCheck = $pdo->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
            $invCheck->execute([$invoiceNumber]);
            if ($invCheck->fetch()) {
                throw new Exception("Invoice number '{$invoiceNumber}' is already in use. Please regenerate.");
            }

            $subtotal = 0.00;
            $validatedItems = [];

            // 1. Validate all line items and check real-time stock locks
            foreach ($items as $item) {
                $prodId = (int)($item['product_id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 0);

                if ($prodId <= 0 || $qty <= 0) {
                    continue; // skip blank rows
                }

                // Row-level lock to prevent race conditions during concurrent billing
                $pStmt = $pdo->prepare("
                    SELECT id, name, sku, selling_price, current_stock, unit 
                    FROM products 
                    WHERE id = ? FOR UPDATE
                ");
                $pStmt->execute([$prodId]);
                $product = $pStmt->fetch();

                if (!$product) {
                    throw new Exception("Product ID {$prodId} not found in catalog.");
                }

                if ($product['current_stock'] < $qty) {
                    throw new Exception("Insufficient stock for '{$product['name']}'. Requested: {$qty}, Available: {$product['current_stock']} {$product['unit']}.");
                }

                $unitPrice = (float)$product['selling_price'];
                $lineTotal = $unitPrice * $qty;
                $subtotal += $lineTotal;

                $validatedItems[] = [
                    'product_id' => $prodId,
                    'product_name' => $product['name'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal
                ];
            }

            if (empty($validatedItems)) {
                throw new Exception("Invoice must contain at least one valid item with quantity greater than zero.");
            }

            // Calculate taxes and totals
            $taxableBase = max(0, $subtotal - $discountAmount);
            $taxAmount = ($taxableBase * $taxRate) / 100;
            $grandTotal = $taxableBase + $taxAmount;

            // 2. Insert Invoice Record
            $insInv = $pdo->prepare("
                INSERT INTO invoices (
                    invoice_number, customer_id, customer_name, customer_phone, 
                    invoice_date, subtotal, tax_rate, tax_amount, discount_amount, 
                    grand_total, payment_status, payment_method, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insInv->execute([
                $invoiceNumber, $customerId, $customerName, $customerPhone,
                $invoiceDate, $subtotal, $taxRate, $taxAmount, $discountAmount,
                $grandTotal, $paymentStatus, $paymentMethod, $notes
            ]);
            $invoiceId = $pdo->lastInsertId();

            // 3. Insert Line Items & Deduct Product Stock in Real Time
            $insItem = $pdo->prepare("
                INSERT INTO invoice_items (invoice_id, product_id, product_name, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $deductStock = $pdo->prepare("
                UPDATE products 
                SET current_stock = current_stock - ? 
                WHERE id = ?
            ");

            $logStock = $pdo->prepare("
                INSERT INTO stock_movements (product_id, type, quantity, reference_id, reference_type, remarks)
                VALUES (?, 'OUT', ?, ?, 'INVOICE', ?)
            ");

            foreach ($validatedItems as $item) {
                // Insert line item
                $insItem->execute([
                    $invoiceId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                ]);

                // Deduct stock in real time
                $deductStock->execute([
                    $item['quantity'],
                    $item['product_id']
                ]);

                // Create movement audit trail
                $logStock->execute([
                    $item['product_id'],
                    $item['quantity'],
                    $invoiceId,
                    "Sales deduction for invoice {$invoiceNumber}"
                ]);
            }

            // Commit atomic transaction
            $pdo->commit();

            set_flash('success', "Invoice {$invoiceNumber} generated successfully. Stock updated in real time.");
            header("Location: invoice_view.php?id={$invoiceId}");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Point of Sale & Billing Terminal</h1>
        <p>Automate sales bill generation with instant real-time stock deduction and tax computation.</p>
    </div>
    <div class="page-actions">
        <a href="invoices.php" class="btn btn-outline">
            <i class="fa-solid fa-list"></i> Invoices History
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

<form id="billingForm" method="POST" action="invoice_create.php">
    <div class="billing-split">
        <!-- Main Form & Items Table -->
        <div class="left-section">
            <!-- Invoice Meta Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-file-invoice"></i> Invoice Details & Customer</h3>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Invoice Number <span class="required">*</span></label>
                            <input type="text" name="invoice_number" class="form-control" value="<?= e($_POST['invoice_number'] ?? $nextInvoiceNo) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Invoice Date <span class="required">*</span></label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= e($_POST['invoice_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select Existing Customer</label>
                            <select name="customer_id" id="customerSelect" class="form-control">
                                <option value="">-- Walk-in / Direct Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>" data-phone="<?= e($c['phone']) ?>">
                                        <?= e($c['name']) ?> (<?= e($c['phone'] ?: 'No Phone') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Customer Name</label>
                            <input type="text" name="customer_name" id="customerNameInput" class="form-control" value="<?= e($_POST['customer_name'] ?? 'Walk-in Customer') ?>" placeholder="e.g. John Doe">
                        </div>

                        <div class="form-group">
                            <label>Customer Phone</label>
                            <input type="text" name="customer_phone" id="customerPhoneInput" class="form-control" value="<?= e($_POST['customer_phone'] ?? '') ?>" placeholder="+1-555-0100">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-cart-shopping"></i> Billable Items</h3>
                    <button type="button" id="addItemBtn" class="btn btn-sm btn-outline">
                        <i class="fa-solid fa-plus"></i> Add Line Item
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 45%;">Product / Service</th>
                                <th style="width: 15%;">Quantity</th>
                                <th style="width: 20%;">Price ($)</th>
                                <th style="width: 15%;">Total ($)</th>
                                <th style="width: 5%; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic Rows Added by billing.js -->
                        </tbody>
                    </table>
                </div>
                <div class="card-body" style="padding-top: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Notes / Payment Instructions</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Thank you for your business! Net 15 days or Cash on delivery."><?= e($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Summary & Payment Card -->
        <div class="right-section">
            <div class="summary-card">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                    Payment Summary
                </h3>

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>$<span id="displaySubtotal">0.00</span></span>
                </div>

                <div class="form-group" style="margin: 12px 0 8px;">
                    <label style="font-size: 12.5px;">Discount ($)</label>
                    <input type="number" step="0.01" min="0" name="discount_amount" id="discountAmount" class="form-control" value="0.00">
                </div>

                <div class="form-group" style="margin: 8px 0 12px;">
                    <label style="font-size: 12.5px;">Tax Rate (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="tax_rate" id="taxRate" class="form-control" value="8.0">
                </div>

                <div class="summary-row">
                    <span>Tax Amount:</span>
                    <span>$<span id="displayTaxAmount">0.00</span></span>
                </div>

                <div class="summary-row total-row">
                    <span>Grand Total:</span>
                    <span class="total-amount">$<span id="displayGrandTotal">0.00</span></span>
                </div>

                <div style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <div class="form-group">
                        <label style="font-size: 12.5px;">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="card">Credit / Debit Card</option>
                            <option value="upi">UPI / Instant Pay</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12.5px;">Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="paid">Paid Immediately</option>
                            <option value="pending">Pending / On Credit</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 12px;">
                        <i class="fa-solid fa-receipt"></i> Generate & Print Bill
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Expose available products to JavaScript -->
<script>
    window.availableProducts = <?= json_encode($products) ?>;
    
    // Auto-fill customer details when dropdown changes
    document.getElementById('customerSelect').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (this.value) {
            document.getElementById('customerNameInput').value = selected.dataset.name || '';
            document.getElementById('customerPhoneInput').value = selected.dataset.phone || '';
        }
    });
</script>

<?php 
$extra_js = 'assets/js/billing.js';
require_once __DIR__ . '/includes/footer.php'; 
?>
