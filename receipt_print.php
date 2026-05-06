<?php
require_once 'config.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
if (!$id) exit;

$rSale = $conn->query("SELECT s.*, u.full_name as cashier_name FROM sales s LEFT JOIN users u ON u.id=s.user_id WHERE s.id=$id LIMIT 1");
$sale = $rSale ? $rSale->fetch_assoc() : null;
if (!$sale) exit;

$rItems = $conn->query("SELECT * FROM sale_items WHERE sale_id=$id ORDER BY id");
$items = $rItems ? $rItems->fetch_all(MYSQLI_ASSOC) : [];

$shopName    = getSetting('shop_name', 'Demo POS');
$shopNameAr  = getSetting('shop_name_ar', 'Demo POS');
$shopAddr    = getSetting('shop_address', 'UTC Building, Maliy, Kuwait City');
$shopAddrAr  = getSetting('shop_address_ar', 'مبنى يو تي سي، المالية، مدينة الكويت');
$shopPhone   = getSetting('shop_phone', '69989060');
$footerEn    = getSetting('receipt_footer', 'Thank you for your visit!');
$footerAr    = getSetting('receipt_footer_ar', 'شكراً لزيارتكم!');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<title>Receipt <?= htmlspecialchars($sale['invoice_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Noto+Sans+Arabic:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  @page { margin: 3mm; size: 80mm auto; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Inter', 'Noto Sans Arabic', sans-serif;
    font-size: 11px;
    color: #000;
    width: 80mm;
    background: #fff;
    padding: 3mm;
  }
  /* bilingual row: EN left, AR right on same line */
  .bi-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 4px;
    line-height: 1.5;
  }
  .bi-en  { font-family: 'Inter', sans-serif; }
  .bi-ar  { font-family: 'Noto Sans Arabic', sans-serif; direction: rtl; text-align: right; }
  .center { text-align: center; }
  .bold   { font-weight: 700; }
  .heavy  { font-weight: 800; }
  .sub    { font-size: 9.5px; color: #555; }
  .divider { border: none; border-top: 1px dashed #aaa; margin: 5px 0; }
  /* header shop name row */
  .shop-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 4px;
    border-bottom: 2px solid #000;
    padding-bottom: 5px;
    margin-bottom: 5px;
  }
  .shop-en { font-size: 13px; font-weight: 800; }
  .shop-ar { font-size: 12px; font-weight: 800; font-family: 'Noto Sans Arabic', sans-serif; direction: rtl; text-align: right; }
  /* invoice meta table */
  table { width: 100%; border-collapse: collapse; }
  td { font-size: 11px; padding: 2px 0; vertical-align: top; }
  td.val { text-align: right; font-weight: 600; white-space: nowrap; padding-left: 4px; }
  /* items table */
  .items-table th { font-size: 10px; font-weight: 700; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 3px 0; }
  .items-table td { padding: 4px 0; font-size: 11px; border-bottom: 1px dotted #ccc; }
  .items-table td.qty  { text-align: center; white-space: nowrap; }
  .items-table td.amt  { text-align: right; font-weight: 700; white-space: nowrap; padding-left: 4px; }
  .item-name-en { font-weight: 600; }
  .item-name-ar { font-family: 'Noto Sans Arabic', sans-serif; font-size: 10px; color: #333; direction: rtl; }
  .item-size    { font-size: 9.5px; color: #666; }
  /* totals */
  .tot-table td { padding: 2.5px 0; font-size: 11px; }
  .tot-table td.ar-lbl { font-family: 'Noto Sans Arabic', sans-serif; direction: rtl; text-align: right; color: #444; font-size: 10px; }
  .grand-row td { font-size: 13px; font-weight: 800; border-top: 2px solid #000; padding-top: 4px; }
  /* stamp */
  .stamp { display: inline-block; border: 2px solid #000; padding: 3px 10px; font-size: 13px; font-weight: 800; letter-spacing: .08em; margin-top: 6px; }
  @media screen {
    body { box-shadow: 0 2px 12px rgba(0,0,0,.18); margin: 16px auto; }
    .print-btn { display: block; text-align: center; margin: 8px 0; }
  }
  @media print { .print-btn { display: none !important; } }
</style>
</head>
<body>
<div class="print-btn">
  <button onclick="window.print()" style="padding:8px 22px;font-size:13px;cursor:pointer;border-radius:6px;border:1px solid #ccc;">🖨 Print Receipt</button>
</div>

<!-- ═══ HEADER: Shop name EN | AR same line ═══ -->
<div class="shop-header">
  <div class="shop-en"><?= htmlspecialchars($shopName) ?></div>
  <div class="shop-ar"><?= htmlspecialchars($shopNameAr) ?></div>
</div>

<!-- Address & phone: EN | AR same line -->
<div class="bi-row sub" style="margin-bottom:2px;">
  <span class="bi-en"><?= htmlspecialchars($shopAddr) ?></span>
  <span class="bi-ar"><?= htmlspecialchars($shopAddrAr) ?></span>
</div>
<div class="center sub" style="margin-bottom:4px;">📞 <?= htmlspecialchars($shopPhone) ?></div>
<hr class="divider">

<!-- ═══ INVOICE META ═══ -->
<table>
  <tr>
    <td><span class="bi-en bold">Invoice</span> <span class="bi-ar sub">/ فاتورة</span></td>
    <td class="val bold"><?= htmlspecialchars($sale['invoice_no']) ?></td>
  </tr>
  <tr>
    <td><span class="bi-en">Date</span> <span class="bi-ar sub">/ التاريخ</span></td>
    <td class="val"><?= date('d/m/Y', strtotime($sale['created_at'])) ?></td>
  </tr>
  <tr>
    <td><span class="bi-en">Time</span> <span class="bi-ar sub">/ الوقت</span></td>
    <td class="val"><?= date('h:i A', strtotime($sale['created_at'])) ?></td>
  </tr>
  <?php if ($sale['customer_name']): ?>
  <tr>
    <td><span class="bi-en">Mobile</span> <span class="bi-ar sub">/ الجوال</span></td>
    <td class="val"><?= htmlspecialchars($sale['customer_name']) ?></td>
  </tr>
  <?php endif; ?>
  <tr>
    <td><span class="bi-en">Cashier</span> <span class="bi-ar sub">/ الكاشير</span></td>
    <td class="val"><?= htmlspecialchars($sale['cashier_name'] ?? '—') ?></td>
  </tr>
</table>
<hr class="divider">

<!-- ═══ ITEMS ═══ -->
<table class="items-table">
  <thead>
    <tr>
      <th style="text-align:left;"><span class="bi-en">Item</span> <span class="bi-ar" style="font-size:9px;">/ المنتج</span></th>
      <th style="text-align:center;"><span class="bi-en">Qty</span> <span class="bi-ar" style="font-size:9px;">/الكمية</span></th>
      <th style="text-align:right;"><span class="bi-en">Total</span> <span class="bi-ar" style="font-size:9px;">/الإجمالي</span></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($items as $item): ?>
  <tr>
    <td>
      <!-- EN name | AR name on same line -->
      <div class="bi-row">
        <span class="item-name-en"><?= htmlspecialchars($item['product_name']) ?></span>
        <?php if ($item['product_name_ar']): ?>
        <span class="item-name-ar"><?= htmlspecialchars($item['product_name_ar']) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($item['size_label']): ?>
      <div class="item-size"><?= htmlspecialchars($item['size_label']) ?></div>
      <?php endif; ?>
      <div class="sub"><?= number_format($item['unit_price'],3) ?> KD / <span style="font-family:'Noto Sans Arabic',sans-serif;">الوحدة</span></div>
    </td>
    <td class="qty"><?= number_format($item['qty'], $item['qty'] == intval($item['qty']) ? 0 : 2) ?></td>
    <td class="amt"><?= number_format($item['total'],3) ?> KD</td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<hr class="divider">

<!-- ═══ TOTALS ═══ -->
<table class="tot-table">
  <tr>
    <td><span class="bi-en">Subtotal</span> <span class="bi-ar sub">/ المجموع الفرعي</span></td>
    <td class="val"><?= number_format($sale['subtotal'],3) ?> KD</td>
  </tr>
  <?php if ((float)$sale['discount'] > 0): ?>
  <tr>
    <td><span class="bi-en">Discount</span> <span class="bi-ar sub">/ الخصم</span></td>
    <td class="val" style="color:#c00;">- <?= number_format($sale['discount'],3) ?> KD</td>
  </tr>
  <?php endif; ?>
  <tr class="grand-row">
    <td><span class="bi-en">TOTAL</span> <span class="bi-ar">/ الإجمالي</span></td>
    <td class="val">
      <?= number_format($sale['total'],3) ?> KD
      <span style="font-size:10px;color:#666;margin-left:6px;"><?= strtoupper($sale['payment_method']) ?></span>
    </td>
  </tr>
</table>
<hr class="divider">

<!-- ═══ FOOTER ═══ -->
<div class="center" style="margin-top:5px;">
  <!-- Footer message EN | AR same line -->
  <div class="bi-row" style="justify-content:center;gap:8px;">
    <span class="bi-en sub"><?= htmlspecialchars($footerEn) ?></span>
    <span class="bi-ar sub"><?= htmlspecialchars($footerAr) ?></span>
  </div>
</div>
<script>window.onload = function(){ window.print(); }</script>
</body>
</html>
