<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['cart'])) {
    echo json_encode(['success' => false, 'error' => 'No cart data']);
    exit;
}

$conn->begin_transaction();
try {
    $invoiceNo = generateInvoiceNo();
    $userId    = (int)$_SESSION['user_id'];
    $branchId  = (int)($_SESSION['branch_id'] ?? 1);
    $subtotal  = (float)$input['subtotal'];
    $discount  = (float)$input['discount'];
    $discType  = in_array($input['discount_type'], ['fixed','percent']) ? $input['discount_type'] : 'fixed';
    $total     = (float)$input['total'];
    $paid      = (float)$input['paid_amount'];
    // (change recalculated after paid correction below)
    $method    = in_array($input['payment_method'], ['cash','knet','wamt']) ? $input['payment_method'] : 'cash';
    $custName  = $conn->real_escape_string($input['customer_name'] ?? '');
    // For non-mixed payments, if paid < total it's a data entry error — treat as fully paid
    if ($method !== 'mixed' && $paid < $total) $paid = $total;
    $change    = max(0, $paid - $total);
    $status    = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

    $invEsc = $conn->real_escape_string($invoiceNo);
    $promoDisc = (float)($input['promo_discount'] ?? 0);
    $conn->query("INSERT INTO sales (invoice_no, user_id, branch_id, subtotal, discount, discount_type, total, paid_amount, change_amount, payment_method, status, customer_name, promo_discount) VALUES ('$invEsc', $userId, $branchId, $subtotal, $discount, '$discType', $total, $paid, $change, '$method', '$status', '$custName', $promoDisc)");
    $saleId = $conn->insert_id;

    foreach ($input['cart'] as $item) {
        $prodId    = (int)$item['id'];
        $sizeId    = !empty($item['sizeId']) ? (int)$item['sizeId'] : 'NULL';
        $pName     = $conn->real_escape_string($item['name'] ?? '');
        $pNameAr   = $conn->real_escape_string($item['name_ar'] ?? '');
        $sizeLabel = $conn->real_escape_string($item['sizeLabel'] ?? '');
        $qty       = (float)$item['qty'];
        $price     = (float)$item['price'];
        $lineTotal = round($qty * $price, 3);

        $conn->query("INSERT INTO sale_items (sale_id, product_id, product_size_id, product_name, product_name_ar, size_label, qty, unit_price, total) VALUES ($saleId, $prodId, $sizeId, '$pName', '$pNameAr', '$sizeLabel', $qty, $price, $lineTotal)");

        // Update stock
        if ($item['type'] === 'weight') {
            $conn->query("UPDATE products SET stock = stock - $qty WHERE id = $prodId AND stock >= $qty");
        } else {
            if ($sizeId !== 'NULL') {
                $qtyInt = (int)round($qty);
                $conn->query("UPDATE product_sizes SET stock = stock - $qtyInt WHERE id = $sizeId AND stock >= $qtyInt");
            } else {
                $conn->query("UPDATE products SET stock = stock - $qty WHERE id = $prodId AND stock >= $qty");
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'sale_id' => $saleId, 'invoice_no' => $invoiceNo]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
