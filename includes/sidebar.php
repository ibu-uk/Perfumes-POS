<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
$shopName = getSetting('shop_name', 'Demo POS');
$shopNameAr = getSetting('shop_name_ar', 'Demo POS');
$shopLogo = getSetting('shop_logo', '');
$logoExists = $shopLogo && file_exists(__DIR__ . '/../' . $shopLogo);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php if ($logoExists): ?>
        <img src="../<?= htmlspecialchars($shopLogo) ?>?v=<?= filemtime(__DIR__ . '/../' . $shopLogo) ?>" alt="Logo" style="height:36px;width:36px;object-fit:contain;border-radius:6px;flex-shrink:0;">
        <?php else: ?>
        <div class="brand-icon">🌸</div>
        <?php endif; ?>
        <div>
            <div class="brand-name"><?= $isAr ? htmlspecialchars($shopNameAr) : htmlspecialchars($shopName) ?></div>
            <div class="brand-sub"><?= $isAr ? 'عطور وبخور' : 'Perfume & Bakhoor' ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label"><?= $isAr ? 'المتجر' : 'STORE' ?></div>
        <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span><?= $isAr ? 'لوحة التحكم' : 'Dashboard' ?></span>
        </a>
        <a href="new_sale.php" class="nav-item <?= $currentPage === 'new_sale.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span><?= $isAr ? 'بيع جديد' : 'New Sale' ?></span>
        </a>
        <a href="invoices.php" class="nav-item <?= $currentPage === 'invoices.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span><?= $isAr ? 'الفواتير' : 'Invoices' ?></span>
        </a>
        <a href="customers.php" class="nav-item <?= $currentPage === 'customers.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span><?= $isAr ? 'العملاء' : 'Customers' ?></span>
        </a>

        <div class="nav-section-label"><?= $isAr ? 'المخزون' : 'INVENTORY' ?></div>
        <a href="products.php?type=piece" class="nav-item <?= ($currentPage === 'products.php' && ($_GET['type'] ?? '') === 'piece') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            <span><?= $isAr ? 'العطور' : 'Perfumes' ?></span>
        </a>
        <a href="products.php?type=weight" class="nav-item <?= ($currentPage === 'products.php' && ($_GET['type'] ?? '') === 'weight') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
            <span><?= $isAr ? 'البخور' : 'Bakhoor' ?></span>
        </a>
        <a href="products.php" class="nav-item <?= ($currentPage === 'products.php' && !isset($_GET['type'])) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            <span><?= $isAr ? 'كل المنتجات' : 'All Products' ?></span>
        </a>
        <a href="categories.php" class="nav-item <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
            <span><?= $isAr ? 'الفئات' : 'Categories' ?></span>
        </a>
        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
        <a href="promotions.php" class="nav-item <?= $currentPage === 'promotions.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            <span><?= $isAr ? 'العروض الترويجية' : 'Promotions' ?></span>
        </a>
        <?php endif; ?>

        <div class="nav-section-label"><?= $isAr ? 'التقارير' : 'REPORTS' ?></div>
        <a href="reports.php" class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span><?= $isAr ? 'تقارير المبيعات' : 'Sales Report' ?></span>
        </a>

        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
        <a href="expenses.php" class="nav-item <?= $currentPage === 'expenses.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <span><?= $isAr ? 'المصروفات' : 'Expenses' ?></span>
        </a>
        <a href="settings.php" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span><?= $isAr ? 'الإعدادات' : 'Settings' ?></span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                <div class="user-role"><?= $isAr ? (($_SESSION['user_role'] ?? '') === 'admin' ? 'مدير' : 'كاشير') : ucfirst($_SESSION['user_role'] ?? '') ?></div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn" title="Logout" onclick="confirmLogout(event)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:32px;max-width:400px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="text-align:center;">
            <div style="width:64px;height:64px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="width:32px;height:32px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </div>
            <h3 style="font-size:20px;font-weight:700;color:#1f2937;margin:0 0 8px;"><?= $isAr ? 'تسجيل الخروج' : 'Logout' ?></h3>
            <p style="font-size:14px;color:#6b7280;margin:0 0 24px;"><?= $isAr ? 'هل أنت متأكد من أنك تريد تسجيل الخروج؟' : 'Are you sure you want to logout?' ?></p>
            <div style="display:flex;gap:12px;justify-content:center;">
                <button onclick="closeLogoutModal()" style="flex:1;padding:10px 20px;border:1px solid #d1d5db;background:#fff;border-radius:8px;font-size:14px;font-weight:600;color:#374151;cursor:pointer;"><?= $isAr ? 'إلغاء' : 'Cancel' ?></button>
                <a href="logout.php" style="flex:1;padding:10px 20px;background:#dc2626;border:none;border-radius:8px;font-size:14px;font-weight:600;color:#fff;text-decoration:none;text-align:center;"><?= $isAr ? 'تسجيل الخروج' : 'Logout' ?></a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmLogout(e) {
    e.preventDefault();
    document.getElementById('logoutModal').style.display = 'flex';
}
function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}
</script>
