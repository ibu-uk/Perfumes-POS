<?php
require_once 'config.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
if (!$id) exit;

$rProd = $conn->query("SELECT * FROM products WHERE id=$id LIMIT 1");
$prod = $rProd ? $rProd->fetch_assoc() : null;
if (!$prod) exit;

$rSizes = $conn->query("SELECT * FROM product_sizes WHERE product_id=$id ORDER BY sort_order");
$sizes = $rSizes ? $rSizes->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Barcode — <?= htmlspecialchars($prod['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<style>
  @page { size: 58mm auto; margin: 2mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', sans-serif; background: #fff; padding: 10px; }
  .label {
    width: 54mm;
    border: 1px solid #ccc;
    padding: 3mm 2mm;
    margin: 4px auto;
    text-align: center;
    page-break-inside: avoid;
    break-inside: avoid;
  }
  .label .p-name { font-size: 9px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .label .p-size { font-size: 8px; color: #555; }
  .label .p-price { font-size: 10px; font-weight: 800; margin: 2px 0; }
  .label svg { max-width: 100%; height: 28mm; }
  .label .p-code { font-size: 7px; color: #777; font-family: monospace; }
  .controls { text-align: center; margin: 12px 0; }
  @media print { .controls { display: none; } }
  .label-grid { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; }
</style>
</head>
<body>
<div class="controls">
  <button onclick="window.print()" style="padding:8px 20px;font-size:13px;cursor:pointer;">🖨 Print Labels</button>
  <a href="products.php" style="margin-left:12px;font-size:13px;">← Back</a>
</div>
<div class="label-grid">
<?php
$labelsData = [];

// Main product barcode
if ($prod['barcode']) {
    $labelsData[] = ['barcode' => $prod['barcode'], 'name' => $prod['name'], 'name_ar' => $prod['name_ar'], 'size' => '', 'price' => $prod['base_price']];
}

// Size barcodes
foreach ($sizes as $sz) {
    $bc = $sz['barcode'] ?: $prod['barcode'] . '-' . $sz['size_label'];
    $labelsData[] = ['barcode' => $bc, 'name' => $prod['name'], 'name_ar' => $prod['name_ar'], 'size' => $sz['size_label'], 'price' => $sz['price']];
}

if (empty($labelsData) && !$prod['barcode']) {
    $labelsData[] = ['barcode' => 'P' . str_pad($id, 6, '0', STR_PAD_LEFT), 'name' => $prod['name'], 'name_ar' => $prod['name_ar'], 'size' => '', 'price' => $prod['base_price']];
}

$qty = (int)($_GET['qty'] ?? 1);
foreach ($labelsData as $i => $lbl):
?>
<?php for ($q = 0; $q < $qty; $q++): ?>
<div class="label">
  <div class="p-name"><?= htmlspecialchars($lbl['name']) ?></div>
  <div class="p-name" style="font-family:'Noto Sans Arabic',sans-serif;direction:rtl;"><?= htmlspecialchars($lbl['name_ar']) ?></div>
  <?php if ($lbl['size']): ?><div class="p-size"><?= htmlspecialchars($lbl['size']) ?></div><?php endif; ?>
  <svg id="bc_<?= $i ?>_<?= $q ?>"></svg>
  <div class="p-price"><?= number_format($lbl['price'],3) ?> KD</div>
  <div class="p-code"><?= htmlspecialchars($lbl['barcode']) ?></div>
</div>
<script>
JsBarcode("#bc_<?= $i ?>_<?= $q ?>", "<?= addslashes($lbl['barcode']) ?>", {
  format: "CODE128", width: 1.2, height: 35, displayValue: false, margin: 2
});
</script>
<?php endfor; ?>
<?php endforeach; ?>
</div>
<div class="controls" style="margin-top:10px;">
  <label style="font-size:13px;">Copies per label: 
    <input type="number" id="qtyInput" value="<?= $qty ?>" min="1" max="50" style="width:60px;font-size:13px;padding:4px;">
  </label>
  <button onclick="location.href='barcode_print.php?id=<?= $id ?>&qty='+document.getElementById('qtyInput').value" style="padding:6px 14px;margin-left:8px;cursor:pointer;">Update</button>
</div>
</body>
</html>
