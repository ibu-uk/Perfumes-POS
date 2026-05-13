<?php
require_once 'config.php';
requireLogin();
requireAdmin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
$msg = '';

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_customer') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = $conn->real_escape_string(trim($_POST['name']));
        $phone    = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $email    = $conn->real_escape_string(trim($_POST['email'] ?? ''));
        $birthday = !empty($_POST['birthday']) ? $conn->real_escape_string($_POST['birthday']) : 'NULL';
        $notes    = $conn->real_escape_string($_POST['notes'] ?? '');
        $birthdayVal = ($birthday === 'NULL') ? 'NULL' : "'$birthday'";

        if ($id) {
            $conn->query("UPDATE customers SET name='$name', phone='$phone', email='$email', birthday=$birthdayVal, notes='$notes' WHERE id=$id");
            $msg = $isAr ? 'تم تحديث العميل.' : 'Customer updated.';
        } else {
            $conn->query("INSERT INTO customers (name, phone, email, birthday, notes) VALUES ('$name','$phone','$email',$birthdayVal,'$notes')");
            $msg = $isAr ? 'تمت إضافة العميل.' : 'Customer added.';
        }
        header('Location: customers.php?msg=' . urlencode($msg));
        exit;
    }

    if ($action === 'delete_customer') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM customers WHERE id=$id");
        $msg = $isAr ? 'تم حذف العميل.' : 'Customer deleted.';
        header('Location: customers.php?msg=' . urlencode($msg));
        exit;
    }
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

// Search
$search = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$where = $search ? "WHERE name LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%'" : '';
$rCusts = $conn->query("SELECT * FROM customers $where ORDER BY name");
$customers = $rCusts ? $rCusts->fetch_all(MYSQLI_ASSOC) : [];
$totalCount = count($customers);

// Edit mode
$editCustomer = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $rEdit = $conn->query("SELECT * FROM customers WHERE id=$editId LIMIT 1");
    $editCustomer = $rEdit ? $rEdit->fetch_assoc() : null;
}

$pageTitle = $isAr ? 'إدارة العملاء' : 'Customer Management';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <div class="topbar-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;margin-right:8px;vertical-align:middle;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <?= $isAr ? 'إدارة العملاء' : 'Customer Management' ?>
    </div>
    <div class="topbar-right">
      <button onclick="document.getElementById('addCustomerModal').style.display='flex'" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= $isAr ? 'إضافة عميل' : 'Add Customer' ?>
      </button>
    </div>
  </div>

  <div class="page-content">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <!-- Search -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body" style="padding:12px 16px;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="<?= $isAr ? 'البحث بالاسم أو الهاتف أو البريد...' : 'Search by name, phone or email...' ?>" style="max-width:400px;">
          <button type="submit" class="btn btn-primary btn-sm"><?= $isAr ? 'بحث' : 'Search' ?></button>
          <?php if ($search): ?><a href="customers.php" class="btn btn-outline btn-sm"><?= $isAr ? 'مسح' : 'Clear' ?></a><?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <span class="card-title"><?= $isAr ? 'قائمة العملاء' : 'Customer List' ?></span>
        <div style="display:flex;gap:8px;align-items:center;">
          <span style="font-size:12px;color:#6b7280;"><?= $totalCount ?> <?= $isAr ? 'عميل مسجل' : ($totalCount == 1 ? 'customer registered' : 'customers registered') ?></span>
          <button onclick="toggleAllPoints(1)" class="btn btn-sm" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;"><?= $isAr?'تفعيل نقاط الكل':'Enable All Points' ?></button>
          <button onclick="toggleAllPoints(0)" class="btn btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;"><?= $isAr?'تعطيل نقاط الكل':'Disable All Points' ?></button>
        </div>
      </div>
      <div class="table-wrapper">
        <?php if (empty($customers)): ?>
        <div style="padding:40px;text-align:center;color:#9ca3af;"><?= $isAr ? 'لا يوجد عملاء' : 'No customers found' ?></div>
        <?php else: ?>
        <table>
          <thead><tr>
            <th><?= $isAr ? 'الاسم' : 'NAME' ?></th>
            <th><?= $isAr ? 'الهاتف' : 'PHONE' ?></th>
            <th><?= $isAr ? 'البريد' : 'EMAIL' ?></th>
            <th><?= $isAr ? 'تاريخ الميلاد' : 'BIRTHDAY' ?></th>
            <th><?= $isAr ? 'النقاط' : 'POINTS' ?></th>
            <th><?= $isAr ? 'إجمالي الإنفاق' : 'TOTAL SPENT' ?></th>
            <th><?= $isAr ? 'نقاط مفعلة' : 'POINTS ON' ?></th>
            <th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($customers as $c): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></td>
            <td><?= htmlspecialchars($c['phone'] ?: '-') ?></td>
            <td style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($c['email'] ?: '-') ?></td>
            <td style="font-size:12px;"><?= $c['birthday'] ? date('d M', strtotime($c['birthday'])) : '-' ?></td>
            <td>
              <span style="display:inline-flex;align-items:center;gap:4px;background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:700;">
                ★ <?= (int)$c['points'] ?>
              </span>
            </td>
            <td style="font-weight:700;color:#1f2937;"><?= number_format($c['total_spent'], 3) ?> KD</td>
            <td>
              <button onclick="toggleSinglePoints(<?= $c['id'] ?>, this)" id="ptsbtn-<?= $c['id'] ?>" class="btn btn-sm" style="min-width:70px;<?= ($c['points_enabled']??1) ? 'background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;' : 'background:#f9fafb;color:#9ca3af;border:1px solid #e5e7eb;' ?>">
                <?= ($c['points_enabled']??1) ? ($isAr?'مفعل':'On') : ($isAr?'معطل':'Off') ?>
              </button>
            </td>
            <td style="text-align:right;">
              <?php if ($c['phone']): ?>
              <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['phone']) ?>" target="_blank" class="btn btn-sm" style="background:#25d366;color:#fff;border:none;padding:4px 8px;border-radius:6px;" title="WhatsApp">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
              </a>
              <?php endif; ?>
              <button onclick="openEditCustomer(<?= htmlspecialchars(json_encode($c)) ?>)" class="btn btn-sm btn-outline" title="Edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $isAr ? 'حذف هذا العميل؟' : 'Delete this customer?' ?>')">
                <input type="hidden" name="action" value="delete_customer">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:32px;max-width:500px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="margin:0;font-size:18px;font-weight:700;" id="customerModalTitle"><?= $isAr ? 'إضافة عميل جديد' : 'Add New Customer' ?></h3>
      <button onclick="closeCustomerModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">&times;</button>
    </div>
    <form method="POST" id="customerForm">
      <input type="hidden" name="action" value="save_customer">
      <input type="hidden" name="id" id="customerFormId" value="0">
      <div class="form-group">
        <label class="form-label"><?= $isAr ? 'الاسم' : 'Name' ?> *</label>
        <input type="text" name="name" id="customerFormName" class="form-control" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?= $isAr ? 'رقم الهاتف' : 'Phone' ?></label>
          <input type="tel" name="phone" id="customerFormPhone" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label"><?= $isAr ? 'البريد الإلكتروني' : 'Email' ?></label>
          <input type="email" name="email" id="customerFormEmail" class="form-control">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label"><?= $isAr ? 'تاريخ الميلاد' : 'Birthday' ?></label>
        <input type="date" name="birthday" id="customerFormBirthday" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label"><?= $isAr ? 'ملاحظات' : 'Notes' ?></label>
        <textarea name="notes" id="customerFormNotes" class="form-control" rows="2"></textarea>
      </div>
      <div style="display:flex;gap:8px;margin-top:16px;">
        <button type="submit" class="btn btn-primary" style="flex:1;"><?= $isAr ? 'حفظ' : 'Save' ?></button>
        <button type="button" onclick="closeCustomerModal()" class="btn btn-outline" style="flex:1;"><?= $isAr ? 'إلغاء' : 'Cancel' ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditCustomer(c) {
    document.getElementById('customerModalTitle').textContent = '<?= $isAr ? "تعديل العميل" : "Edit Customer" ?>';
    document.getElementById('customerFormId').value = c.id;
    document.getElementById('customerFormName').value = c.name || '';
    document.getElementById('customerFormPhone').value = c.phone || '';
    document.getElementById('customerFormEmail').value = c.email || '';
    document.getElementById('customerFormBirthday').value = c.birthday || '';
    document.getElementById('customerFormNotes').value = c.notes || '';
    document.getElementById('addCustomerModal').style.display = 'flex';
}
async function toggleAllPoints(on) {
    if (!confirm(on ? '<?= $isAr ? "تفعيل نقاط جميع العملاء؟" : "Enable points for ALL customers?" ?>' : '<?= $isAr ? "تعطيل نقاط جميع العملاء؟" : "Disable points for ALL customers?" ?>')) return;
    await fetch('api/toggle_customer_points.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({mode: on ? 'all_on' : 'all_off'})});
    location.reload();
}
async function toggleSinglePoints(id, btn) {
    const res = await fetch('api/toggle_customer_points.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({mode:'single', id: id})});
    const data = await res.json();
    if (data.success) {
        const on = data.enabled === 1;
        btn.textContent = on ? '<?= $isAr ? "مفعل" : "On" ?>' : '<?= $isAr ? "معطل" : "Off" ?>';
        btn.style.background = on ? '#f0fdf4' : '#f9fafb';
        btn.style.color = on ? '#16a34a' : '#9ca3af';
        btn.style.border = on ? '1px solid #bbf7d0' : '1px solid #e5e7eb';
    }
}
function closeCustomerModal() {
    document.getElementById('addCustomerModal').style.display = 'none';
    document.getElementById('customerForm').reset();
    document.getElementById('customerFormId').value = '0';
    document.getElementById('customerModalTitle').textContent = '<?= $isAr ? "إضافة عميل جديد" : "Add New Customer" ?>';
}
</script>
<script src="assets/js/main.js"></script>
</body></html>
