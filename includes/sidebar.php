<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <div class="brand-text">
            <h3>BillTrack</h3>
            <span class="subtext">Inventory & Invoicing</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Core Operations</div>
        
        <a href="index.php" class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>

        <div class="nav-section-title">Billing & Sales</div>

        <a href="invoice_create.php" class="nav-item <?= $currentPage === 'invoice_create.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-cash-register"></i>
            <span>Point of Sale (Billing)</span>
        </a>

        <a href="invoices.php" class="nav-item <?= in_array($currentPage, ['invoices.php', 'invoice_view.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Invoices History</span>
        </a>

        <div class="nav-section-title">Inventory Management</div>

        <a href="products.php" class="nav-item <?= in_array($currentPage, ['products.php', 'product_edit.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-packing"></i>
            <span>Product Catalog</span>
        </a>

        <a href="product_add.php" class="nav-item <?= $currentPage === 'product_add.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-circle-plus"></i>
            <span>Add Product</span>
        </a>

        <a href="stock_adjust.php" class="nav-item <?= $currentPage === 'stock_adjust.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-ramp-box"></i>
            <span>Stock Adjustment</span>
        </a>

        <div class="nav-section-title">CRM</div>

        <a href="customers.php" class="nav-item <?= in_array($currentPage, ['customers.php', 'customer_add.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i>
            <span>Customers</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="system-status">
            <span class="status-indicator online"></span>
            <span>MySQL Real-time Sync</span>
        </div>
        <small class="version-label">v1.0.0 &bull; Open Source</small>
    </div>
</aside>
