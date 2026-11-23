<?php
/**
 * CaseZone — ADMIN side
 * Tabs: Overview · Orders (status + assign rider) · Products (add/edit/delete) · Users (roles)
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$user = require_role('admin');
$tab  = $_GET['tab'] ?? 'overview';
$pdo  = db();
$errors = [];

/* ================= ACTIONS ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_order') {
        $status  = $_POST['status'] ?? '';
        $riderId = ($_POST['rider_id'] ?? '') === '' ? null : (int) $_POST['rider_id'];
        if (isset(STATUS_LABELS[$status])) {
            $old = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
            $old->execute([(int) $_POST['order_id']]);
            $prev = $old->fetchColumn();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE orders SET status = ?, rider_id = ? WHERE id = ?')->execute([$status, $riderId, (int) $_POST['order_id']]);
            if ($status === 'cancelled' && $prev !== 'cancelled') {   // put stock back
                $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.stock = p.stock + oi.quantity WHERE oi.order_id = ?')
                    ->execute([(int) $_POST['order_id']]);
            }
            $pdo->commit();
            flash('Order #' . (int) $_POST['order_id'] . ' updated.');
        }
        header('Location: admin.php?tab=orders' . (isset($_GET['status']) ? '&status=' . urlencode((string) $_GET['status']) : '')); exit;
    }

    if ($action === 'save_product') {
        $id   = (int) ($_POST['id'] ?? 0);
        $data = [
            'name'              => trim((string) $_POST['name']),
            'description'       => trim((string) $_POST['description']),
            'category'          => trim((string) $_POST['category']),
            'price'             => (float) $_POST['price'],
            'compare_at_price'  => $_POST['compare_at_price'] === '' ? null : (float) $_POST['compare_at_price'],
            'stock'             => max(0, (int) $_POST['stock']),
            'image_url'         => trim((string) $_POST['image_url']) ?: null,
            'compatible_models' => trim((string) $_POST['compatible_models']) ?: 'iPhone 13 – iPhone 17 series',
            'is_featured'       => isset($_POST['is_featured']) ? 1 : 0,
            'is_active'         => isset($_POST['is_active']) ? 1 : 0,
        ];
        // Optional image upload into /products
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $ext = strtolower(pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && $_FILES['image']['size'] <= 5_000_000) {
                if (!is_dir(__DIR__ . '/products')) mkdir(__DIR__ . '/products', 0775, true);
                $file = 'products/' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/' . $file);
                $data['image_url'] = $file;
            } else {
                $errors[] = 'Image must be JPG, PNG or WEBP under 5MB.';
            }
        }
        if ($data['name'] === '' || $data['category'] === '' || $data['price'] < 0) $errors[] = 'Name, category and a valid price are required.';
        if (!$errors) {
            $cols = array_keys($data);
            if ($id) {
                $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
                $pdo->prepare("UPDATE products SET $set WHERE id = ?")->execute([...array_values($data), $id]);
                flash('Product updated.');
            } else {
                $pdo->prepare('INSERT INTO products (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')
                    ->execute(array_values($data));
                flash('Product added.');
            }
            header('Location: admin.php?tab=products'); exit;
        }
        $tab = 'products';
    }

    if ($action === 'delete_product') {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([(int) $_POST['id']]);
        flash('Product deleted.');
        header('Location: admin.php?tab=products'); exit;
    }

    if ($action === 'set_role') {
        $uid  = (int) $_POST['user_id'];
        $role = $_POST['role'] ?? '';
        if ($uid === (int) $user['id']) {
            flash("You can't change your own role.");
        } elseif (in_array($role, ['admin', 'rider', 'client'], true)) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
            flash('Role updated.');
        }
        header('Location: admin.php?tab=users'); exit;
    }

    if ($action === 'toggle_user') {
        $uid = (int) $_POST['user_id'];
        if ($uid !== (int) $user['id']) {
            $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$uid]);
            flash('Account status changed.');
        }
        header('Location: admin.php?tab=users'); exit;
    }
}

/* ================= DATA ================= */
$riders = $pdo->query("SELECT id, full_name FROM users WHERE role = 'rider' AND is_active = 1 ORDER BY full_name")->fetchAll();
$tabLink = fn(string $key, string $label) => '<a class="pill' . ($tab === $key ? ' on' : '') . '" href="admin.php?tab=' . $key . '">' . $label . '</a>';

page_start('Admin', $user, 'admin'); ?>
<h1>Admin <em>dashboard.</em></h1>
<p class="sub">Welcome, <?= e($user['full_name']) ?>. Manage orders, riders, products and customers.</p>
<div class="tabs">
  <?= $tabLink('overview', '📊 Overview') ?><?= $tabLink('orders', '📦 Orders') ?><?= $tabLink('products', '📱 Products') ?><?= $tabLink('users', '👥 Users & riders') ?>
</div>
<?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>

<?php if ($tab === 'overview'):
  $stats = $pdo->query("SELECT
      COALESCE(SUM(CASE WHEN status = 'delivered' THEN total END), 0) AS revenue,
      COUNT(*) AS orders,
      SUM(status = 'pending') AS pending,
      SUM(status IN ('confirmed','preparing','out_for_delivery')) AS active
    FROM orders")->fetch();
  $customers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
  $lowStock  = $pdo->query('SELECT name, stock FROM products WHERE is_active = 1 AND stock <= 5 ORDER BY stock')->fetchAll();
  $recent    = $pdo->query('SELECT o.id, o.status, o.total, o.created_at, u.full_name FROM orders o JOIN users u ON u.id = o.customer_id ORDER BY o.created_at DESC LIMIT 8')->fetchAll();
  $best      = $pdo->query("SELECT oi.product_name, SUM(oi.quantity) qty FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status <> 'cancelled' GROUP BY oi.product_name ORDER BY qty DESC LIMIT 5")->fetchAll();
?>
  <div class="grid g4">
    <div class="stat hero"><span>Revenue (delivered)</span><b><?= peso($stats['revenue']) ?></b></div>
    <div class="stat"><span>Total orders</span><b><?= (int) $stats['orders'] ?></b></div>
    <div class="stat"><span>Pending / active</span><b><?= (int) $stats['pending'] ?> / <?= (int) $stats['active'] ?></b></div>
    <div class="stat"><span>Customers · Riders</span><b><?= $customers ?> · <?= count($riders) ?></b></div>
  </div>
  <br>
  <div class="grid g2">
    <div class="card table-wrap"><div class="row"><h2>Recent orders</h2><a class="pill" href="admin.php?tab=orders">View all</a></div>
      <table><tr><th>#</th><th>Customer</th><th>Status</th><th>Total</th><th>Date</th></tr>
      <?php foreach ($recent as $o): ?><tr><td><b>#<?= (int) $o['id'] ?></b></td><td><?= e($o['full_name']) ?></td><td><?= status_badge($o['status']) ?></td><td><?= peso($o['total']) ?></td><td class="muted"><?= date('M j', strtotime($o['created_at'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="5" class="muted">No orders yet.</td></tr><?php endif; ?>
      </table>
    </div>
    <div class="grid">
      <div class="card"><h2>Best sellers</h2>
        <?php foreach ($best as $b): ?><div class="row" style="padding:6px 0"><span><?= e($b['product_name']) ?></span><b><?= (int) $b['qty'] ?> sold</b></div><?php endforeach; ?>
        <?php if (!$best): ?><p class="muted">No sales yet.</p><?php endif; ?>
      </div>
      <div class="card"><h2>⚠️ Low stock</h2>
        <?php foreach ($lowStock as $l): ?><div class="row" style="padding:6px 0"><span><?= e($l['name']) ?></span><span class="status s-cancelled"><?= (int) $l['stock'] ?> left</span></div><?php endforeach; ?>
        <?php if (!$lowStock): ?><p class="muted">All products are well stocked.</p><?php endif; ?>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'orders'):
  $filter = $_GET['status'] ?? '';
  $sql = 'SELECT o.*, u.full_name AS customer, u.email FROM orders o JOIN users u ON u.id = o.customer_id';
  $params = [];
  if (isset(STATUS_LABELS[$filter])) { $sql .= ' WHERE o.status = ?'; $params[] = $filter; }
  $sql .= ' ORDER BY o.created_at DESC';
  $st = $pdo->prepare($sql); $st->execute($params); $orders = $st->fetchAll();
  $itemsQ = $pdo->prepare('SELECT product_name, quantity FROM order_items WHERE order_id = ?');
?>
  <div class="tabs">
    <a class="pill<?= $filter === '' ? ' on' : '' ?>" href="admin.php?tab=orders">All</a>
    <?php foreach (STATUS_LABELS as $k => $v): ?><a class="pill<?= $filter === $k ? ' on' : '' ?>" href="admin.php?tab=orders&status=<?= $k ?>"><?= $v ?></a><?php endforeach; ?>
  </div>
  <?php if (!$riders): ?><div class="error">No riders yet. Go to "Users & riders" and set someone's role to Rider.</div><?php endif; ?>
  <div class="card table-wrap">
    <table>
      <tr><th>Order</th><th>Customer & delivery</th><th>Items</th><th>Total</th><th>Status / rider</th></tr>
      <?php foreach ($orders as $o): $itemsQ->execute([$o['id']]); ?>
      <tr>
        <td><b>#<?= (int) $o['id'] ?></b><div class="muted"><?= date('M j, g:i A', strtotime($o['created_at'])) ?></div><?= status_badge($o['status']) ?></td>
        <td><b><?= e($o['delivery_name']) ?></b><div class="muted"><?= e($o['delivery_phone']) ?><br><?= e($o['delivery_address']) ?><?= $o['notes'] ? '<br>📝 ' . e($o['notes']) : '' ?></div></td>
        <td class="muted"><?php foreach ($itemsQ->fetchAll() as $it) echo (int) $it['quantity'] . '× ' . e($it['product_name']) . '<br>'; ?></td>
        <td><b><?= peso($o['total']) ?></b><div class="muted">COD</div></td>
        <td>
          <form method="post" class="grid" style="gap:6px;min-width:190px">
            <?= csrf_field() ?><input type="hidden" name="action" value="update_order"><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
            <select name="status"><?php foreach (STATUS_LABELS as $k => $v): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
            <select name="rider_id"><option value="">— No rider —</option><?php foreach ($riders as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $o['rider_id'] === (int) $r['id'] ? 'selected' : '' ?>>🛵 <?= e($r['full_name']) ?></option><?php endforeach; ?></select>
            <button class="btn sm">Save</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="5" class="empty">No orders here.</td></tr><?php endif; ?>
    </table>
  </div>

<?php elseif ($tab === 'products'):
  $editing = null;
  if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM products WHERE id = ?'); $s->execute([(int) $_GET['edit']]); $editing = $s->fetch() ?: null; }
  $p = $editing ?? ['id' => 0, 'name' => '', 'description' => '', 'category' => 'Cases', 'price' => '', 'compare_at_price' => '', 'stock' => 10, 'image_url' => '', 'compatible_models' => 'iPhone 13 – iPhone 17 series', 'is_featured' => 0, 'is_active' => 1];
  $products = $pdo->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();
?>
  <div class="grid g2">
    <div class="card table-wrap"><h2>All products (<?= count($products) ?>)</h2>
      <table><tr><th></th><th>Product</th><th>Price</th><th>Stock</th><th></th></tr>
      <?php foreach ($products as $pr): ?>
        <tr style="<?= $pr['is_active'] ? '' : 'opacity:.5' ?>">
          <td><img class="thumb" src="<?= e($pr['image_url']) ?>" alt=""></td>
          <td><b><?= e($pr['name']) ?></b><div class="muted"><?= e($pr['category']) ?><?= $pr['is_featured'] ? ' · ✦ Featured' : '' ?><?= $pr['is_active'] ? '' : ' · Hidden' ?></div></td>
          <td><?= peso($pr['price']) ?></td>
          <td><?= (int) $pr['stock'] <= 5 ? '<span class="status s-cancelled">' . (int) $pr['stock'] . '</span>' : (int) $pr['stock'] ?></td>
          <td class="inline"><a class="btn ghost sm" href="admin.php?tab=products&edit=<?= (int) $pr['id'] ?>">Edit</a>
            <form method="post" onsubmit="return confirm('Delete this product?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= (int) $pr['id'] ?>"><button class="btn danger sm">Delete</button></form></td>
        </tr>
      <?php endforeach; ?>
      </table>
    </div>
    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="row"><h2><?= $editing ? 'Edit product' : 'Add product' ?></h2><?php if ($editing): ?><a class="pill" href="admin.php?tab=products">+ New</a><?php endif; ?></div>
      <label>Name</label><input name="name" required value="<?= e($p['name']) ?>">
      <label>Category</label>
      <select name="category"><?php foreach (['Cases', 'Screen Protection', 'Chargers', 'Cables', 'Power', 'Audio', 'Accessories'] as $c): ?><option <?= $p['category'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select>
      <label>Description</label><textarea name="description" rows="3"><?= e($p['description']) ?></textarea>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
        <div><label>Price (₱)</label><input name="price" type="number" step="0.01" min="0" required value="<?= e((string) $p['price']) ?>"></div>
        <div><label>Old price (₱, optional)</label><input name="compare_at_price" type="number" step="0.01" min="0" value="<?= e((string) $p['compare_at_price']) ?>"></div>
        <div><label>Stock</label><input name="stock" type="number" min="0" value="<?= (int) $p['stock'] ?>"></div>
        <div><label>Fits</label><input name="compatible_models" value="<?= e($p['compatible_models']) ?>"></div>
      </div>
      <label>Image path</label><input name="image_url" value="<?= e($p['image_url']) ?>" placeholder="products/my-case.jpg">
      <label>…or upload image</label><input type="file" name="image" accept="image/*">
      <label class="inline"><input type="checkbox" name="is_featured" style="width:auto" <?= $p['is_featured'] ? 'checked' : '' ?>> Featured (Signature)</label>
      <label class="inline"><input type="checkbox" name="is_active" style="width:auto" <?= $p['is_active'] ? 'checked' : '' ?>> Visible in shop</label>
      <br><button class="btn" style="width:100%"><?= $editing ? 'Save changes' : 'Add product' ?></button>
    </form>
  </div>

<?php elseif ($tab === 'users'):
  $users = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = u.id) AS orders_count,
                               (SELECT COUNT(*) FROM orders o WHERE o.rider_id = u.id AND o.status = 'delivered') AS deliveries
                        FROM users u ORDER BY FIELD(role,'admin','rider','client'), created_at DESC")->fetchAll();
?>
  <div class="card table-wrap"><h2>Users & riders (<?= count($users) ?>)</h2>
    <p class="muted" style="margin-bottom:12px">To add a rider: ask them to sign up, then change their role to <b>Rider</b> here.</p>
    <table><tr><th>Name</th><th>Contact</th><th>Activity</th><th>Role</th><th>Account</th></tr>
    <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $user['id']; ?>
      <tr style="<?= $u['is_active'] ? '' : 'opacity:.5' ?>">
        <td><b><?= e($u['full_name']) ?></b><?= $self ? ' <span class="muted">(you)</span>' : '' ?><div class="muted">Joined <?= date('M j, Y', strtotime($u['created_at'])) ?></div></td>
        <td class="muted"><?= e($u['email']) ?><br><?= e($u['phone']) ?></td>
        <td class="muted"><?= $u['role'] === 'rider' ? (int) $u['deliveries'] . ' deliveries' : (int) $u['orders_count'] . ' orders' ?></td>
        <td>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="set_role"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <select name="role" <?= $self ? 'disabled' : '' ?> onchange="this.form.submit()"><?php foreach (['client' => '🛍️ Client', 'rider' => '🛵 Rider', 'admin' => '👑 Admin'] as $k => $v): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
          </form>
        </td>
        <td><?php if (!$self): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button class="btn sm <?= $u['is_active'] ? 'ghost' : 'success' ?>"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </table>
  </div>
<?php endif; page_end();