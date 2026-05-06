<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');
$barcode = trim($_GET['barcode'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if ($barcode) {
    $b = $conn->real_escape_string($barcode);
    $r = $conn->query("SELECT p.*, c.name as cat_name, c.name_ar as cat_name_ar FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.barcode='$b' AND p.is_active=1 LIMIT 1");
    if (!$r || !$r->num_rows) {
        // Check product sizes
        $r2 = $conn->query("SELECT ps.*, p.name, p.name_ar, p.type, p.weight_unit FROM product_sizes ps JOIN products p ON p.id=ps.product_id WHERE ps.barcode='$b' LIMIT 1");
        if ($r2 && $r2->num_rows) {
            $s = $r2->fetch_assoc();
            echo json_encode(['found' => true, 'type' => 'size', 'data' => $s]);
        } else {
            echo json_encode(['found' => false]);
        }
        exit;
    }
    $p = $r->fetch_assoc();
    $r3 = $conn->query("SELECT * FROM product_sizes WHERE product_id={$p['id']} ORDER BY sort_order");
    $p['sizes'] = $r3 ? $r3->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode(['found' => true, 'type' => 'product', 'data' => $p]);
} elseif ($id) {
    $r = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=$id AND p.is_active=1 LIMIT 1");
    $p = $r ? $r->fetch_assoc() : null;
    if ($p) {
        $r3 = $conn->query("SELECT * FROM product_sizes WHERE product_id=$id ORDER BY sort_order");
        $p['sizes'] = $r3 ? $r3->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['found' => true, 'data' => $p]);
    } else {
        echo json_encode(['found' => false]);
    }
} else {
    echo json_encode(['error' => 'No query']);
}
