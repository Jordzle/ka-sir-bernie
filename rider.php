<?php
/** CaseZone — RIDER side: assigned deliveries, start delivery, mark delivered. */
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$user = require_role('rider');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $s = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND rider_id = ?');
    $s->execute([$orderId, $user['id']]);
    $status = $s->fetchColumn();
    // Riders can only move: confirmed/preparing -> out_for_delivery -> delivered
    $next = match ($status) { 'confirmed', 'preparing' => 'out_for_delivery', 'out_for_delivery' => 'delivered', default => null };
    if ($next && ($_POST['action'] ?? '') === $next) {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND rider_id = ?')->execute([$next, $orderId, $user['id']]);
        flash($next === 'delivered' ? "Order #$orderId delivered. Great job! 🎉" : "Order #$orderId is on the way.");
    } else {
        flash('That order cannot be updated right now.');
    }
    header('Location: rider.php'); exit;
}

$q = $pdo->prepare("SELECT * FROM orders WHERE rider_id = ? AND status NOT IN ('delivered','cancelled') ORDER BY FIELD(status,'out_for_delivery','preparing','confirmed','pending'), created_at");
$q->execute([$user['id']]);
$active = $q->fetchAll();
$h = $pdo->prepare("SELECT * FROM orders WHERE rider_id = ? AND status = 'delivered' ORDER BY updated_at DESC LIMIT 20");
$h->execute([$user['id']]);
$done = $h->fetchAll();
$today = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM orders WHERE rider_id = ? AND status = 'delivered' AND DATE(updated_at) = CURDATE()");
$today->execute([$user['id']]);
$t = $today->fetch();
$itemsQ = $pdo->prepare('SELECT product_name, quantity FROM order_items WHERE order_id = ?');

page_start('Deliveries', $user, 'rider'); ?>
<h1>Your <em>deliveries.</em></h1>
<p class="sub">Hi <?= e(explode(' ', $user['full_name'])[0]) ?>! Collect cash from the customer when you hand over the order.</p>
<div class="grid g4">
  <div class="stat hero"><span>Active</span><b><?= count($active) ?></b></div>
  <div class="stat"><span>Delivered today</span><b><?= (int) $t['c'] ?></b></div>
  <div class="stat"><span>Cash collected today</span><b><?= peso($t['t']) ?></b></div>
  <div class="stat"><span>All-time delivered</span><b><?= count($done) ?><?= count($done) === 20 ? '+' : '' ?></b></div>
</div>
<br>
<h2>Active deliveries</h2>
<?php if (!$active): ?><div class="card empty">No deliveries assigned right now. The admin will assign orders to you.</div><?php endif; ?>
<div class="grid g2" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr))">
<?php foreach ($active as $o): $itemsQ->execute([$o['id']]); ?>
  <div class="card">
    <div class="row"><h3>Order #<?= (int) $o['id'] ?></h3><?= status_badge($o['status']) ?></div>
    <?= tracker($o['status']) ?>
    <p><b><?= e($o['delivery_name']) ?></b></p>
    <p class="muted">📞 <a href="tel:<?= e($o['delivery_phone']) ?>" style="color:var(--sky-600);font-weight:700"><?= e($o['delivery_phone']) ?></a></p>
    <p class="muted">📍 <?= e($o['delivery_address']) ?> · <a target="_blank" style="color:var(--sky-600)" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($o['delivery_address']) ?>">Open map</a></p>
    <?php if ($o['notes']): ?><p class="muted">📝 <?= e($o['notes']) ?></p><?php endif; ?>
    <p class="muted" style="margin-top:8px"><?php foreach ($itemsQ->fetchAll() as $it) echo (int) $it['quantity'] . '× ' . e($it['product_name']) . '<br>'; ?></p>
    <div class="row" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line)">
      <div><span class="muted">Collect (COD)</span><h3><?= peso($o['total']) ?></h3></div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
        <?php if (in_array($o['status'], ['confirmed', 'preparing'], true)): ?>
          <button class="btn" name="action" value="out_for_delivery">🛵 Start delivery</button>
        <?php elseif ($o['status'] === 'out_for_delivery'): ?>
          <button class="btn success" name="action" value="delivered" onclick="return confirm('Cash collected and order handed over?')">✅ Mark delivered</button>
        <?php else: ?>
          <span class="muted">Waiting for admin to confirm</span>
        <?php endif; ?>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<br>
<div class="card table-wrap"><h2>Delivery history</h2>
  <table><tr><th>Order</th><th>Customer</th><th>Address</th><th>Collected</th><th>Delivered</th></tr>
  <?php foreach ($done as $o): ?><tr><td><b>#<?= (int) $o['id'] ?></b></td><td><?= e($o['delivery_name']) ?></td><td class="muted"><?= e($o['delivery_address']) ?></td><td><?= peso($o['total']) ?></td><td class="muted"><?= date('M j, g:i A', strtotime($o['updated_at'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$done): ?><tr><td colspan="5" class="muted">No completed deliveries yet.</td></tr><?php endif; ?>
  </table>
</div>
<?php page_end();