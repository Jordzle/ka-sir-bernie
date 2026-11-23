<?php
/**
 * CaseZone — storefront (index.php)
 * Lists products from MySQL, filters by category, and keeps a cart in the session.
 * Requires: db.php + schema.sql imported. Product images go in a /products folder.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

$user = current_user();
$_SESSION['cart'] ??= [];

/* ---------- Cart actions (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid    = (int) ($_POST['product_id'] ?? 0);

    if ($action === 'add' && $pid > 0) {
        $stmt = db()->prepare('SELECT stock FROM products WHERE id = ? AND is_active = 1');
        $stmt->execute([$pid]);
        $stock = (int) ($stmt->fetchColumn() ?: 0);
        $inCart = $_SESSION['cart'][$pid] ?? 0;
        if ($stock > $inCart) {
            $_SESSION['cart'][$pid] = $inCart + 1;
            $_SESSION['flash'] = 'Added to your bag.';
        } else {
            $_SESSION['flash'] = 'Sorry, no more stock for that item.';
        }
    } elseif ($action === 'remove' && $pid > 0) {
        unset($_SESSION['cart'][$pid]);
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
    }
    header('Location: index.php' . (isset($_GET['cat']) ? '?cat=' . urlencode((string) $_GET['cat']) : ''));
    exit;
}

/* ---------- Data ---------- */
$category   = trim((string) ($_GET['cat'] ?? ''));
$search     = trim((string) ($_GET['q'] ?? ''));
$categories = db()->query('SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

$sql    = 'SELECT * FROM products WHERE is_active = 1';
$params = [];
if ($category !== '') { $sql .= ' AND category = ?';   $params[] = $category; }
if ($search !== '')   { $sql .= ' AND name LIKE ?';    $params[] = '%' . $search . '%'; }
$sql .= ' ORDER BY is_featured DESC, created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

/* Cart summary */
$cartItems = [];
$subtotal  = 0.0;
if ($_SESSION['cart']) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $q   = db()->prepare("SELECT id, name, price, image_url FROM products WHERE id IN ($in)");
    $q->execute($ids);
    foreach ($q->fetchAll() as $p) {
        $qty = $_SESSION['cart'][$p['id']];
        $p['qty']  = $qty;
        $p['line'] = $qty * (float) $p['price'];
        $subtotal += $p['line'];
        $cartItems[] = $p;
    }
}
$deliveryFee = ($subtotal === 0.0 || $subtotal >= 2000) ? 0 : 79;
$cartCount   = array_sum($_SESSION['cart']);
$flash       = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CaseZone — Exclusive iPhone Accessories</title>
  <meta name="description" content="CaseZone: premium iPhone cases, chargers and accessories delivered to your door. Cash on delivery.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@600;700;800;900&family=Instrument+Serif:ital@1&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --sky-50:#f2f9ff; --sky-100:#e3f2fe; --sky-200:#bfe3fd; --sky-300:#8fd0fb;
      --sky-400:#4fb6f5; --sky-500:#1e9de8; --sky-600:#0b7fc7; --ink:#0b1b33; --ink-soft:#4a5d78;
      --glass:rgba(255,255,255,.62); --line:rgba(11,27,51,.08);
      --radius:22px; --shadow:0 30px 60px -30px rgba(11,127,199,.45);
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Manrope,system-ui,sans-serif;color:var(--ink);
      background:radial-gradient(1200px 600px at 85% -10%,var(--sky-200),transparent 60%),
                 radial-gradient(900px 500px at -10% 20%,var(--sky-100),transparent 60%),var(--sky-50);
      min-height:100vh}
    a{color:inherit;text-decoration:none}
    .wrap{max-width:1200px;margin:0 auto;padding:0 24px}
    /* Header */
    header{position:sticky;top:0;z-index:20;backdrop-filter:blur(18px);background:rgba(242,249,255,.7);border-bottom:1px solid var(--line)}
    .nav{display:flex;align-items:center;justify-content:space-between;height:72px;gap:16px}
    .logo{display:inline-flex;align-items:center;gap:10px;font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-weight:800;font-size:22px;letter-spacing:-.04em}
    .logo .mark{width:36px;height:36px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(150deg,var(--sky-300),var(--sky-600));box-shadow:var(--shadow)}
    .logo .mark::before{content:"";width:12px;height:19px;border:2.5px solid #fff;border-radius:5px}
    .logo span{background:linear-gradient(120deg,var(--sky-400),var(--sky-600));-webkit-background-clip:text;color:transparent}
    .links{display:flex;gap:10px;align-items:center;font-weight:600;font-size:14px}
    .pill{padding:10px 18px;border-radius:999px;border:1px solid var(--line);background:var(--glass);transition:.2s}
    .pill:hover{border-color:var(--sky-400)}
    .pill.primary{background:linear-gradient(135deg,var(--sky-400),var(--sky-600));color:#fff;border:0;box-shadow:var(--shadow)}
    .badge{display:inline-grid;place-items:center;min-width:20px;height:20px;border-radius:99px;background:var(--ink);color:#fff;font-size:11px;margin-left:6px}
    /* Hero */
    .hero{padding:80px 0 56px;display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:center}
    .eyebrow{display:inline-flex;gap:8px;align-items:center;font-size:12px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--sky-600);
      padding:8px 14px;border-radius:99px;background:var(--glass);border:1px solid var(--sky-200)}
    h1{font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-weight:800;font-size:clamp(44px,7vw,88px);line-height:.95;letter-spacing:-.045em;margin:22px 0}
    h1 em{font-family:'Instrument Serif';font-weight:400;font-style:italic;letter-spacing:-.01em;color:var(--sky-500)}
    .lead{font-size:18px;color:var(--ink-soft);max-width:480px;line-height:1.6}
    .hero-cta{display:flex;gap:12px;margin-top:32px}
    .orb{aspect-ratio:1;border-radius:40px;background:linear-gradient(160deg,var(--sky-200),var(--sky-500) 70%,var(--sky-600));
      box-shadow:var(--shadow);position:relative;overflow:hidden;display:grid;place-items:center}
    .orb img{width:78%;border-radius:28px;transform:rotate(-8deg);box-shadow:0 40px 60px -20px rgba(11,27,51,.4)}
    .orb::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.55),transparent 45%)}
    .perks{display:flex;gap:28px;margin-top:36px;font-size:13px;color:var(--ink-soft);font-weight:600}
    .perks b{display:block;font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-size:22px;color:var(--ink)}
    /* Filters */
    .bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin:20px 0 28px}
    .chips{display:flex;gap:8px;flex-wrap:wrap}
    .chip{padding:9px 16px;border-radius:99px;font-size:13px;font-weight:600;border:1px solid var(--line);background:var(--glass)}
    .chip.on{background:var(--ink);color:#fff;border-color:var(--ink)}
    .search input{padding:11px 18px;border-radius:99px;border:1px solid var(--line);background:#fff;font:inherit;width:240px;outline:none}
    .search input:focus{border-color:var(--sky-400);box-shadow:0 0 0 4px var(--sky-100)}
    h2{font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-size:34px;letter-spacing:-.03em}
    /* Grid */
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:22px;padding-bottom:40px}
    .card{background:var(--glass);border:1px solid rgba(255,255,255,.8);border-radius:var(--radius);padding:12px;backdrop-filter:blur(12px);
      transition:transform .35s cubic-bezier(.2,.8,.2,1),box-shadow .35s}
    .card:hover{transform:translateY(-6px);box-shadow:var(--shadow)}
    .thumb{aspect-ratio:1;border-radius:16px;overflow:hidden;background:var(--sky-100);position:relative}
    .thumb img{width:100%;height:100%;object-fit:cover;transition:transform .6s}
    .card:hover .thumb img{transform:scale(1.06)}
    .tag{position:absolute;top:10px;left:10px;font-size:11px;font-weight:700;padding:5px 10px;border-radius:99px;background:rgba(255,255,255,.9)}
    .tag.sale{left:auto;right:10px;background:var(--ink);color:#fff}
    .info{padding:14px 6px 6px}
    .cat{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--sky-600);font-weight:700}
    .name{font-weight:700;font-size:16px;margin:6px 0 10px;line-height:1.3}
    .row{display:flex;align-items:center;justify-content:space-between;gap:8px}
    .price{font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-weight:700;font-size:20px}
    .price s{font-family:Manrope;font-size:13px;color:var(--ink-soft);font-weight:500;margin-left:6px}
    .add{border:0;cursor:pointer;width:42px;height:42px;border-radius:14px;font-size:22px;color:#fff;
      background:linear-gradient(135deg,var(--sky-400),var(--sky-600));box-shadow:0 10px 20px -10px var(--sky-600)}
    .add:disabled{background:#c9d6e3;box-shadow:none;cursor:not-allowed}
    .stock{font-size:12px;color:var(--ink-soft);margin-top:8px}
    /* Bag */
    .layout{display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start}
    .bag{position:sticky;top:96px;background:var(--ink);color:#fff;border-radius:var(--radius);padding:24px}
    .bag h3{font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-size:22px;margin-bottom:16px}
    .bag-item{display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.1);font-size:14px}
    .bag-item img{width:48px;height:48px;border-radius:12px;object-fit:cover}
    .bag-item form{margin-left:auto}
    .x{background:none;border:0;color:var(--sky-300);cursor:pointer;font-size:18px}
    .sum{display:flex;justify-content:space-between;font-size:14px;margin-top:12px;color:#b8c7da}
    .sum.total{color:#fff;font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-size:20px;margin-top:16px}
    .checkout{display:block;text-align:center;margin-top:20px;padding:14px;border-radius:14px;font-weight:700;
      background:linear-gradient(135deg,var(--sky-300),var(--sky-500));color:var(--ink)}
    .muted{color:#8aa0bb;font-size:13px}
    .flash{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--ink);color:#fff;padding:12px 22px;border-radius:99px;font-weight:600;z-index:50;animation:fade 3s forwards}
    @keyframes fade{0%,80%{opacity:1}100%{opacity:0;visibility:hidden}}
    footer{border-top:1px solid var(--line);padding:40px 0;color:var(--ink-soft);font-size:13px;margin-top:40px}
    @media (max-width:900px){.hero,.layout{grid-template-columns:1fr}.bag{position:static}.links .hide-sm{display:none}}
  </style>
</head>
<body>

<header>
  <div class="wrap nav">
    <a href="index.php" class="logo"><i class="mark"></i><strong>Case<span>Zone</span></strong></a>
    <nav class="links">
      <a class="pill hide-sm" href="#shop">Shop</a>
      <?php if ($user): ?>
        <a class="pill hide-sm" href="orders.php">My orders</a>
        <?php if ($user['role'] === 'admin'): ?><a class="pill" href="admin.php">Admin</a><?php endif; ?>
        <?php if ($user['role'] === 'rider'): ?><a class="pill" href="rider.php">Deliveries</a><?php endif; ?>
        <a class="pill" href="logout.php">Log out</a>
      <?php else: ?>
        <a class="pill" href="login.php">Log in</a>
        <a class="pill primary" href="register.php">Sign up</a>
      <?php endif; ?>
      <a class="pill" href="checkout.php">Bag<span class="badge"><?= $cartCount ?></span></a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero">
    <div>
      <span class="eyebrow">✦ The Exclusive Collection</span>
      <h1>Dress your iPhone in <em>pure sky.</em></h1>
      <p class="lead">
        <?= $user ? 'Welcome back, ' . e(explode(' ', $user['full_name'])[0]) . '. ' : '' ?>
        Hand-picked cases, chargers and essentials, crafted for those who notice the details. Delivered to your door with cash on delivery.
      </p>
      <div class="hero-cta">
        <a class="pill primary" href="#shop">Shop the collection</a>
        <?php if (!$user): ?><a class="pill" href="register.php">Become a member</a><?php endif; ?>
      </div>
      <div class="perks">
        <div><b>₱0</b>Delivery over ₱2,000</div>
        <div><b>COD</b>Pay at your door</div>
        <div><b>1 yr</b>Warranty</div>
      </div>
    </div>
    <div class="orb"><img src="products/aurora-clear-case.jpg" alt="Aurora Clear MagSafe Case"></div>
  </section>

  <section id="shop">
    <div class="bar">
      <h2><?= $category !== '' ? e($category) : 'All accessories' ?></h2>
      <form class="search" method="get">
        <?php if ($category !== ''): ?><input type="hidden" name="cat" value="<?= e($category) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search accessories…">
      </form>
    </div>
    <div class="chips" style="margin-bottom:24px">
      <a class="chip <?= $category === '' ? 'on' : '' ?>" href="index.php#shop">All</a>
      <?php foreach ($categories as $c): ?>
        <a class="chip <?= $c === $category ? 'on' : '' ?>" href="?cat=<?= urlencode($c) ?>#shop"><?= e($c) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="layout">
      <div class="grid">
        <?php if (!$products): ?>
          <p class="muted">No products found.</p>
        <?php endif; ?>
        <?php foreach ($products as $p):
          $onSale = $p['compare_at_price'] && $p['compare_at_price'] > $p['price']; ?>
          <article class="card">
            <div class="thumb">
              <img src="<?= e($p['image_url'] ?: 'products/placeholder.jpg') ?>" alt="<?= e($p['name']) ?>" loading="lazy">
              <?php if ($p['is_featured']): ?><span class="tag">✦ Signature</span><?php endif; ?>
              <?php if ($onSale): ?><span class="tag sale">-<?= round(100 - $p['price'] / $p['compare_at_price'] * 100) ?>%</span><?php endif; ?>
            </div>
            <div class="info">
              <div class="cat"><?= e($p['category']) ?></div>
              <div class="name"><?= e($p['name']) ?></div>
              <div class="row">
                <div class="price"><?= peso($p['price']) ?><?php if ($onSale): ?><s><?= peso($p['compare_at_price']) ?></s><?php endif; ?></div>
                <form method="post">
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                  <button class="add" <?= $p['stock'] < 1 ? 'disabled' : '' ?> title="Add to bag">+</button>
                </form>
              </div>
              <div class="stock"><?= $p['stock'] > 0 ? (int) $p['stock'] . ' in stock' : 'Sold out' ?></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <aside class="bag" id="bag">
        <h3>Your bag</h3>
        <?php if (!$cartItems): ?>
          <p class="muted">Your bag is empty. Add something beautiful.</p>
        <?php else: ?>
          <?php foreach ($cartItems as $it): ?>
            <div class="bag-item">
              <img src="<?= e($it['image_url']) ?>" alt="">
              <div><?= e($it['name']) ?><div class="muted"><?= $it['qty'] ?> × <?= peso($it['price']) ?></div></div>
              <form method="post">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int) $it['id'] ?>">
                <button class="x" title="Remove">×</button>
              </form>
            </div>
          <?php endforeach; ?>
          <div class="sum"><span>Subtotal</span><span><?= peso($subtotal) ?></span></div>
          <div class="sum"><span>Delivery</span><span><?= $deliveryFee ? peso($deliveryFee) : 'Free' ?></span></div>
          <div class="sum total"><span>Total</span><span><?= peso($subtotal + $deliveryFee) ?></span></div>
          <a class="checkout" href="<?= $user ? 'checkout.php' : 'login.php' ?>">
            <?= $user ? 'Checkout · Cash on delivery' : 'Log in to checkout' ?>
          </a>
        <?php endif; ?>
      </aside>
    </div>
  </section>
</main>

<footer>
  <div class="wrap row">
    <span><b class="logo" style="font-size:16px">Case<span>Zone</span></b> · Exclusive iPhone accessories</span>
    <span>© <?= date('Y') ?> CaseZone. Prices in PHP ₱.</span>
  </div>
</footer>

<?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
</body>
</html>