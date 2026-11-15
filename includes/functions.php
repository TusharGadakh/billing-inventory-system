<?php
/**
 * Helper Functions & Business Logic Utilities
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape output for safe HTML rendering
 */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format monetary amount
 */
function format_currency($amount, $currency = '$') {
    return $currency . number_format((float)$amount, 2);
}

/**
 * Format date for UI display
 */
function format_date($dateString) {
    if (!$dateString) return '-';
    $timestamp = strtotime($dateString);
    return date('M d, Y', $timestamp);
}

/**
 * Set flash alert message in session
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash alert message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate Next Sequential Invoice Number
 * e.g., INV-2026-0003
 */
function generate_invoice_number($pdo) {
    $year = date('Y');
    $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM invoices");
    $row = $stmt->fetch();
    $nextId = ($row && $row['max_id']) ? ((int)$row['max_id'] + 1) : 1;
    return sprintf("INV-%s-%04d", $year, $nextId);
}

/**
 * Generate Unique Product SKU
 */
function generate_sku($pdo, $prefix = 'PRD') {
    $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM products");
    $row = $stmt->fetch();
    $nextId = ($row && $row['max_id']) ? ((int)$row['max_id'] + 1) : 1;
    return sprintf("%s-%04d", $prefix, $nextId);
}

/**
 * Render Stock Status Badge
 */
function get_stock_badge($stock, $minAlert) {
    if ($stock <= 0) {
        return '<span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Out of Stock (0)</span>';
    } elseif ($stock <= $minAlert) {
        return '<span class="badge badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock (' . (int)$stock . ')</span>';
    } else {
        return '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> In Stock (' . (int)$stock . ')</span>';
    }
}
