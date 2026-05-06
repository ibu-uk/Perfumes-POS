<?php
require_once '../config.php';
requireLogin();
header('Content-Type: text/csv');

$reportType = $_GET['report_type'] ?? 'dashboard';
$period = $_GET['period'] ?? 'today';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$cashierFilter = (int)($_GET['cashier'] ?? 0);

// Resolve period
if ($period === 'all')       { $dateFrom = '2000-01-01'; $dateTo = '2099-12-31'; }
elseif ($period === 'today')     { $dateFrom = $dateTo = date('Y-m-d'); }
elseif ($period === 'yesterday') { $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day')); }
elseif ($period === 'week')  { $dateFrom = date('Y-m-d', strtotime('monday this week')); $dateTo = date('Y-m-d'); }
elseif ($period === 'month') { $dateFrom = date('Y-m-01'); $dateTo = date('Y-m-d'); }

$baseWhere = "WHERE DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo' AND s.status != 'void'" . ($cashierFilter ? " AND s.user_id=$cashierFilter" : '');

$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';

$output = fopen('php://output', 'w');

if ($reportType === 'dashboard') {
    header('Content-Disposition: attachment; filename="dashboard_report_' . date('Y-m-d') . '.csv"');

    $rSales = $conn->query("SELECT s.invoice_no, s.created_at, s.customer_name, s.total, s.payment_method, s.status, u.full_name as cashier
                            FROM sales s LEFT JOIN users u ON u.id=s.user_id $baseWhere ORDER BY s.created_at DESC");
    $sales = $rSales ? $rSales->fetch_all(MYSQLI_ASSOC) : [];

    $headers = [
        $isAr ? 'رقم الفاتورة' : 'Invoice No',
        $isAr ? 'التاريخ' : 'Date',
        $isAr ? 'الوقت' : 'Time',
        $isAr ? 'العميل' : 'Customer',
        $isAr ? 'الإجمالي' : 'Total',
        $isAr ? 'طريقة الدفع' : 'Payment Method',
        $isAr ? 'الحالة' : 'Status',
        $isAr ? 'الكاشير' : 'Cashier'
    ];

    fputcsv($output, $headers);

    foreach ($sales as $s) {
        fputcsv($output, [
            $s['invoice_no'],
            date('d/m/Y', strtotime($s['created_at'])),
            date('h:i A', strtotime($s['created_at'])),
            $s['customer_name'] ?: '',
            number_format($s['total'], 3),
            strtoupper($s['payment_method']),
            ucfirst($s['status']),
            $s['cashier'] ?? ''
        ]);
    }
}
elseif ($reportType === 'product') {
    header('Content-Disposition: attachment; filename="product_report_' . date('Y-m-d') . '.csv"');

    $rProdSales = $conn->query("SELECT si.product_name, si.product_name_ar, si.size_label, SUM(si.qty) as total_qty, SUM(si.total) as total_rev, COUNT(DISTINCT si.sale_id) as sales_count FROM sale_items si JOIN sales s ON s.id=si.sale_id $baseWhere GROUP BY si.product_name, si.size_label ORDER BY total_rev DESC");
    $productSales = $rProdSales ? $rProdSales->fetch_all(MYSQLI_ASSOC) : [];

    $headers = [
        $isAr ? 'المنتج' : 'Product',
        $isAr ? 'الحجم' : 'Size',
        $isAr ? 'الكمية' : 'Qty',
        $isAr ? 'عدد المبيعات' : 'Sales Count',
        $isAr ? 'الإيراد' : 'Revenue'
    ];

    fputcsv($output, $headers);

    foreach ($productSales as $ps) {
        fputcsv($output, [
            $isAr ? $ps['product_name_ar'] : $ps['product_name'],
            $ps['size_label'] ?: '',
            number_format($ps['total_qty'], 1),
            $ps['sales_count'],
            number_format($ps['total_rev'], 3)
        ]);
    }
}
elseif ($reportType === 'cashier') {
    header('Content-Disposition: attachment; filename="cashier_report_' . date('Y-m-d') . '.csv"');

    $rCashierPerf = $conn->query("SELECT u.full_name, COUNT(s.id) as invoice_count, COALESCE(SUM(s.total),0) as total_rev, COALESCE(AVG(s.total),0) as avg_rev FROM users u LEFT JOIN sales s ON s.user_id=u.id AND DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo' AND s.status!='void' WHERE u.role IN ('admin','cashier') GROUP BY u.id ORDER BY total_rev DESC");
    $cashierPerf = $rCashierPerf ? $rCashierPerf->fetch_all(MYSQLI_ASSOC) : [];

    $headers = [
        $isAr ? 'الكاشير' : 'Cashier',
        $isAr ? 'عدد الفواتير' : 'Invoice Count',
        $isAr ? 'الإيراد الإجمالي' : 'Total Revenue',
        $isAr ? 'متوسط الفاتورة' : 'Avg Invoice'
    ];

    fputcsv($output, $headers);

    foreach ($cashierPerf as $cp) {
        fputcsv($output, [
            $cp['full_name'],
            $cp['invoice_count'],
            number_format($cp['total_rev'], 3),
            number_format($cp['avg_rev'], 3)
        ]);
    }
}
elseif ($reportType === 'stock') {
    header('Content-Disposition: attachment; filename="stock_report_' . date('Y-m-d') . '.csv"');

    $stockReport = [];

    // Weight type products (bakhoor) - stock in products table
    $rWeight = $conn->query("SELECT p.name, p.name_ar, 'weight' as type, p.stock, p.low_stock_threshold, c.name as category_name, '' as size_label FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND p.type='weight' ORDER BY p.name");
    while ($row = $rWeight ? $rWeight->fetch_assoc() : null) {
        $stockReport[] = $row;
    }

    // Piece type products (perfumes) - stock in product_sizes table
    $rSizes = $conn->query("SELECT ps.id, p.name, p.name_ar, 'piece' as type, ps.stock, ps.low_stock_threshold, c.name as category_name, ps.size_label FROM product_sizes ps JOIN products p ON p.id=ps.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY p.name, ps.size_label");
    while ($row = $rSizes ? $rSizes->fetch_assoc() : null) {
        $stockReport[] = $row;
    }

    $headers = [
        $isAr ? 'المنتج' : 'Product',
        $isAr ? 'الحجم' : 'Size',
        $isAr ? 'المخزون' : 'Stock',
        $isAr ? 'الحد الأدنى' : 'Low Stock',
        $isAr ? 'الحالة' : 'Status'
    ];

    fputcsv($output, $headers);

    foreach ($stockReport as $sr) {
        fputcsv($output, [
            $isAr ? $sr['name_ar'] : $sr['name'],
            $sr['size_label'] ?: '',
            number_format($sr['stock'], 0),
            number_format($sr['low_stock_threshold'], 0),
            $sr['stock'] <= $sr['low_stock_threshold'] ? ($isAr ? 'منخفض' : 'Low') : ($isAr ? 'جيد' : 'OK')
        ]);
    }
}

fclose($output);
exit;
