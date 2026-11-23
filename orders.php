<?php
/** CaseZone — client side: my orders with live status tracker. */
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$user = current_user();
if (!$user) { header('Location: login.php'); exit; }

/* Cancel a pending order (restocks items) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    check_csrf();
    $pdo = db();
    $pdo->beginTransaction();
    $s = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND customer_id = ? AND status = 'pending' FOR UPDATE");
    $s->execute([(int) $_POST['order_id'], $user['id']]);
    if ($s->fetch()) {
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([(int) $_POST['order_id']]);
        $pdo->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.stock = p.stock + oi.quantity WHERE oi.order_id = ?')
            ->execute([(int) $_POST['order_id']]);
        flash('Order cancelled.');
    } else {
        flash('Only pending orders can be cancelled.');
    }
    $pdo->commit();
    header('Location: orders.php'); exit;
}

$q = db()->prepare('SELECT o.*, r.full_name AS rider_name, r.phone AS rider_phone
                    FROM orders o LEFT JOIN users r ON r.id = o.rider_id
                    WHERE o.customer_id = ? ORDER BY o.created_at DESC');
$q->execute([$user['id']]);
$orders = $q->fetchAll();
$itemsQ = db()->prepare('SELECT oi.*, p.image_url FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');

page_start('My orders', $user, 'orders'); ?>
<h1>My <em>orders.</em></h1>
<p class="sub">Follow every order from confirmation to your door.</p>
<?php if (!$orders): ?>
  <div class="card empty"><h2>No orders yet</h2><p>Your first exclusive piece is waiting.</p><br><a class="btn" href="index.php#shop">Start shopping</a></div>
<?php endif; ?>
<div class="grid">
<?php foreach ($orders as $o): $itemsQ->execute([$o['id']]); $items = $itemsQ->fetchAll(); ?>
  <div class="card">
    <div class="row">
      <div><h3>Order #<?= (int) $o['id'] ?></h3><div class="muted"><?= date('M j, Y · g:i A', strtotime($o['created_at'])) ?></div></div>
      <?= status_badge($o['status']) ?>
    </div>
    <?= tracker($o['status']) ?>
    <?php foreach ($items as $it): ?>
      <div class="inline" style="margin:8px 0"><img class="thumb" src="<?= e($it['image_url']) ?>" alt=""><div><b><?= e($it['product_name']) ?></b><div class="muted"><?= (int) $it['quantity'] ?> × <?= peso($it['unit_price']) ?></div></div></div>
    <?php endforeach; ?>
    <div class="row" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line)">
      <div class="muted">
        📍 <?= e($o['delivery_address']) ?><br>
        <?php if ($o['rider_name']): ?>🛵 Rider: <b><?= e($o['rider_name']) ?></b> <?= $o['rider_phone'] ? '· ' . e($o['rider_phone']) : '' ?><?php else: ?>🛵 Rider not assigned yet<?php endif; ?>
      </div>
      <div class="inline">
        <h3>Total <?= peso($o['total']) ?> <span class="muted">(COD)</span></h3>
        <?php if ($o['status'] === 'pending'): ?>
          <form method="post" onsubmit="return confirm('Cancel this order?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><button class="btn ghost sm">Cancel</button></form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php page_end();