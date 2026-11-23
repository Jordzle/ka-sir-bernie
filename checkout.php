<?php
/** CaseZone — checkout (client). Places a cash-on-delivery order from the session bag. */
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$user = current_user();
if (!$user) { header('Location: login.php'); exit; }

$_SESSION['cart'] ??= [];
$errors = [];

/* Load bag items with live prices */
function load_cart(): array
{
    if (!$_SESSION['cart']) return [];
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $q   = db()->prepare("SELECT id, name, price, stock, image_url FROM products WHERE is_active = 1 AND id IN ($in)");
    $q->execute($ids);
    $items = [];
    foreach ($q->fetchAll() as $p) {
        $p['qty']  = (int) $_SESSION['cart'][$p['id']];
        $p['line'] = $p['qty'] * (float) $p['price'];
        $items[] = $p;
    }
    return $items;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'qty') {
        $pid = (int) $_POST['product_id'];
        $qty = max(0, min(20, (int) $_POST['qty']));
        if ($qty === 0) unset($_SESSION['cart'][$pid]); else $_SESSION['cart'][$pid] = $qty;
        header('Location: checkout.php'); exit;
    }

    if ($action === 'place') {
        $name    = trim((string) ($_POST['delivery_name'] ?? ''));
        $phone   = trim((string) ($_POST['delivery_phone'] ?? ''));
        $address = trim((string) ($_POST['delivery_address'] ?? ''));
        $notes   = trim((string) ($_POST['notes'] ?? ''));
        if ($name === '' || $phone === '' || $address === '') $errors[] = 'Name, mobile number and address are required.';
        if (!$_SESSION['cart']) $errors[] = 'Your bag is empty.';

        if (!$errors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $subtotal = 0.0;
                $lines = [];
                foreach ($_SESSION['cart'] as $pid => $qty) {
                    $s = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
                    $s->execute([(int) $pid]);
                    $p = $s->fetch();
                    if (!$p) throw new RuntimeException('A product in your bag is no longer available.');
                    if ($p['stock'] < $qty) throw new RuntimeException("Only {$p['stock']} left of {$p['name']}.");
                    $lines[] = [$p, (int) $qty];
                    $subtotal += (float) $p['price'] * $qty;
                }
                $fee = $subtotal >= 2000 ? 0 : 79;   // ₱79 delivery, free from ₱2,000
                $pdo->prepare('INSERT INTO orders (customer_id, subtotal, delivery_fee, total, delivery_name, delivery_phone, delivery_address, notes)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$user['id'], $subtotal, $fee, $subtotal + $fee, $name, $phone, $address, $notes ?: null]);
                $orderId = (int) $pdo->lastInsertId();
                $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)');
                $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                foreach ($lines as [$p, $qty]) {
                    $ins->execute([$orderId, $p['id'], $p['name'], $p['price'], $qty, $p['price'] * $qty]);
                    $dec->execute([$qty, $p['id']]);
                }
                $pdo->prepare('UPDATE users SET phone = COALESCE(phone, ?), address = ? WHERE id = ?')->execute([$phone, $address, $user['id']]);
                $pdo->commit();
                $_SESSION['cart'] = [];
                flash("Order #$orderId placed! Pay cash when your rider arrives.");
                header('Location: orders.php'); exit;
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not place your order. Please try again.';
            }
        }
    }
}

$items    = load_cart();
$subtotal = array_sum(array_column($items, 'line'));
$fee      = ($subtotal == 0 || $subtotal >= 2000) ? 0 : 79;
$me = db()->prepare('SELECT full_name, phone, address FROM users WHERE id = ?');
$me->execute([$user['id']]);
$profile = $me->fetch();

page_start('Checkout', $user); ?>
<h1>Almost <em>yours.</em></h1>
<p class="sub">Review your bag and tell us where to deliver. You pay cash when it arrives.</p>
<?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>

<?php if (!$items): ?>
  <div class="card empty"><h2>Your bag is empty</h2><p>Add something beautiful first.</p><br><a class="btn" href="index.php#shop">Go shopping</a></div>
<?php else: ?>
<div class="grid g2">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="place">
    <h2>Delivery details</h2>
    <label>Full name</label><input name="delivery_name" required value="<?= e($_POST['delivery_name'] ?? $profile['full_name']) ?>">
    <label>Mobile number</label><input name="delivery_phone" required value="<?= e($_POST['delivery_phone'] ?? $profile['phone']) ?>">
    <label>Complete address</label><textarea name="delivery_address" rows="3" required><?= e($_POST['delivery_address'] ?? $profile['address']) ?></textarea>
    <label>Notes for the rider (optional)</label><input name="notes" placeholder="Landmark, gate code…" value="<?= e($_POST['notes'] ?? '') ?>">
    <div class="card" style="margin-top:18px;background:var(--sky-100)"><b>💵 Cash on delivery</b><div class="muted">Prepare <?= peso($subtotal + $fee) ?> for the rider.</div></div>
    <br><button class="btn" style="width:100%">Place order · <?= peso($subtotal + $fee) ?></button>
  </form>
  <div class="card">
    <h2>Your bag</h2>
    <?php foreach ($items as $it): ?>
      <div class="row" style="padding:10px 0;border-bottom:1px solid var(--line)">
        <div class="inline"><img class="thumb" src="<?= e($it['image_url']) ?>" alt=""><div><b><?= e($it['name']) ?></b><div class="muted"><?= peso($it['price']) ?></div></div></div>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="qty"><input type="hidden" name="product_id" value="<?= (int) $it['id'] ?>">
          <select name="qty" onchange="this.form.submit()"><?php for ($i = 0; $i <= min(20, (int) $it['stock']); $i++): ?><option value="<?= $i ?>" <?= $i === $it['qty'] ? 'selected' : '' ?>><?= $i === 0 ? 'Remove' : $i ?></option><?php endfor; ?></select>
        </form>
      </div>
    <?php endforeach; ?>
    <div class="row" style="margin-top:14px"><span class="muted">Subtotal</span><span><?= peso($subtotal) ?></span></div>
    <div class="row"><span class="muted">Delivery</span><span><?= $fee ? peso($fee) : 'Free' ?></span></div>
    <div class="row" style="margin-top:8px"><h3>Total</h3><h3><?= peso($subtotal + $fee) ?></h3></div>
    <?php if ($fee): ?><p class="muted" style="margin-top:10px">Add <?= peso(2000 - $subtotal) ?> more for free delivery.</p><?php endif; ?>
  </div>
</div>
<?php endif; page_end();