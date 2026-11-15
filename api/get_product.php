<?php
/**
 * Product Lookup API
 * Returns product details and real-time stock availability
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product ID'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, sku, name, selling_price, current_stock, min_stock_alert, unit, status 
        FROM products 
        WHERE id = ? AND status = 'active' 
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product) {
        echo json_encode([
            'success' => true,
            'product' => [
                'id' => (int)$product['id'],
                'sku' => $product['sku'],
                'name' => $product['name'],
                'selling_price' => (float)$product['selling_price'],
                'current_stock' => (int)$product['current_stock'],
                'unit' => $product['unit']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found or inactive'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
