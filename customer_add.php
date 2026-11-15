<?php
$page_title = 'Add Customer';
require_once __DIR__ . '/includes/header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name)) {
        $errors[] = 'Customer name is required.';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $address]);

            set_flash('success', "Customer '{$name}' created successfully.");
            header('Location: customers.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Register New Customer</h1>
        <p>Save customer contact details for automated invoice generation.</p>
    </div>
    <div class="page-actions">
        <a href="customers.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to Customers
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

<div class="card" style="max-width: 700px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-user-plus"></i> Customer Contact Profile</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="customer_add.php">
            <div class="form-group">
                <label>Customer / Business Name <span class="required">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Acme Corporation or John Doe" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+1-555-0100">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" placeholder="client@example.com">
                </div>
            </div>

            <div class="form-group">
                <label>Billing / Delivery Address</label>
                <textarea name="address" class="form-control" rows="3" placeholder="Street Address, City, State, ZIP code"><?= e($_POST['address'] ?? '') ?></textarea>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Save Customer
                </button>
                <a href="customers.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
