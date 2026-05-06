<?php
require_once 'config.php';
$r = $conn->query("SELECT id, name, name_ar, type, base_price, weight_unit, stock, low_stock_threshold FROM products WHERE type='weight' ORDER BY name");
if ($r && $r->num_rows) {
    echo "ID | Name | Type | Base Price | Unit | Stock | Low Stock\n";
    echo "-----------------------------------------------------------\n";
    while($row = $r->fetch_assoc()) {
        echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['type'] . ' | ' . $row['base_price'] . ' | ' . $row['weight_unit'] . ' | ' . $row['stock'] . ' | ' . $row['low_stock_threshold'] . "\n";
    }
} else {
    echo "No weight-type products found.\n";
}
