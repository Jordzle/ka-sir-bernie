<?php
/**
 * CaseZone — shared layout, styles and helpers for inner pages
 * (checkout, orders, admin, rider).
 */
declare(strict_types=1);

const STATUS_LABELS = [
    'pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing',
    'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
];
const ORDER_FLOW = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];

/* ---------- CSRF protection for every POST form ---------- */
function csrf_token(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Session expired. Go back and try again.');
    }
}
function flash(?string $msg = null): ?string
{
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}
function status_badge(string $status): string
{
    return '<span class="status s-' . e($status) . '">' . e(STATUS_LABELS[$status] ?? $status) . '</span>';
}
function tracker(string $status): string
{
    if ($status === 'cancelled') return '';
    $cur = array_search($status, ORDER_FLOW, true);
    $html = '<div class="tracker">';
    foreach (ORDER_FLOW as $i => $s) {
        $html .= '<div class="step' . ($i <= $cur ? ' done' : '') . '"><i></i><span>' . STATUS_LABELS[$s] . '</span></div>';
    }
    return $html . '</div>';
}

function page_start(string $title, ?array $user, string $active = ''): void
{
    $cartCount = array_sum($_SESSION['cart'] ?? []);
    $link = fn(string $href, string $label, string $key) =>
        '<a class="pill' . ($active === $key ? ' on' : '') . '" href="' . $href . '">' . $label . '</a>';
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> — CaseZone</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@600;700;800;900&family=Instrument+Serif:ital@1&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--sky-50:#f2f9ff;--sky-100:#e3f2fe;--sky-200:#bfe3fd;--sky-300:#8fd0fb;--sky-400:#4fb6f5;--sky-500:#1e9de8;--sky-600:#0b7fc7;
      --ink:#0b1b33;--ink-soft:#4a5d78;--line:rgba(11,27,51,.09);--glass:rgba(255,255,255,.66);--shadow:0 30px 60px -30px rgba(11,127,199,.45);
      --display:"Unbounded","Arial Black","Segoe UI Black",system-ui,sans-serif}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Manrope,"Segoe UI",system-ui,sans-serif;color:var(--ink);min-height:100vh;
      background:radial-gradient(1100px 600px at 85% -10%,var(--sky-200),transparent 60%),radial-gradient(900px 500px at -10% 20%,var(--sky-100),transparent 60%),var(--sky-50)}
    a{color:inherit;text-decoration:none}
    .wrap{max-width:1200px;margin:0 auto;padding:0 24px}
    header{position:sticky;top:0;z-index:20;backdrop-filter:blur(18px);background:rgba(242,249,255,.75);border-bottom:1px solid var(--line)}
    .nav{display:flex;align-items:center;justify-content:space-between;height:74px;gap:16px}
    .logo{display:inline-flex;align-items:center;gap:10px;font-family:var(--display);font-weight:800;font-size:22px;letter-spacing:-.04em}
    .logo .mark{width:36px;height:36px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(150deg,var(--sky-300),var(--sky-600));box-shadow:var(--shadow)}
    .logo .mark::before{content:"";width:12px;height:19px;border:2.5px solid #fff;border-radius:5px}
    .logo b{font-weight:800;background:linear-gradient(120deg,var(--sky-400),var(--sky-600));-webkit-background-clip:text;background-clip:text;color:transparent}
    .links{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-weight:700;font-size:14px}
    .pill{padding:10px 16px;border-radius:999px;border:1px solid var(--line);background:var(--glass);transition:.2s;display:inline-block}
    .pill:hover,.pill.on{border-color:var(--sky-400);color:var(--sky-600)}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:0;cursor:pointer;padding:11px 20px;border-radius:999px;font:700 14px Manrope,sans-serif;
      color:#fff;background:linear-gradient(135deg,var(--sky-400),var(--sky-600));box-shadow:0 14px 28px -14px var(--sky-600);transition:.2s}
    .btn:hover{filter:brightness(1.08);transform:translateY(-1px)}
    .btn.ink{background:var(--ink);box-shadow:none}.btn.ghost{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none}
    .btn.danger{background:#e5484d;box-shadow:none}.btn.success{background:#16a36a;box-shadow:none}.btn.sm{padding:7px 14px;font-size:12px}
    .badge{display:inline-grid;place-items:center;min-width:20px;height:20px;border-radius:99px;background:var(--ink);color:#fff;font-size:11px;margin-left:6px}
    h1{font-family:var(--display);font-weight:800;font-size:clamp(34px,5vw,58px);letter-spacing:-.05em;line-height:1;margin:44px 0 10px}
    h1 em{font-family:'Instrument Serif',serif;font-weight:400;font-style:italic;letter-spacing:-.01em;color:var(--sky-500)}
    h2{font-family:var(--display);font-weight:800;font-size:22px;letter-spacing:-.04em;margin-bottom:14px}
    h3{font-family:var(--display);font-weight:700;font-size:16px;letter-spacing:-.03em}
    .sub{color:var(--ink-soft);margin-bottom:30px}
    .card{background:var(--glass);backdrop-filter:blur(14px);border:1px solid #fff;border-radius:24px;padding:24px;box-shadow:0 20px 40px -30px rgba(11,27,51,.3)}
    .grid{display:grid;gap:18px}.g2{grid-template-columns:1.4fr 1fr}.g4{grid-template-columns:repeat(4,1fr)}
    .stat{padding:22px;border-radius:22px;background:var(--glass);border:1px solid #fff}
    .stat b{display:block;font-family:var(--display);font-weight:800;font-size:28px;letter-spacing:-.04em;margin-top:6px}
    .stat span{font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--sky-600)}
    .stat.hero{background:linear-gradient(150deg,var(--sky-400),var(--sky-600));color:#fff;border:0}.stat.hero span{color:#e3f2fe}
    label{display:block;font-size:13px;font-weight:700;margin:12px 0 6px}
    input,select,textarea{width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font:inherit;outline:none}
    input:focus,select:focus,textarea:focus{border-color:var(--sky-400);box-shadow:0 0 0 4px var(--sky-100)}
    table{width:100%;border-collapse:collapse;font-size:14px}
    th{text-align:left;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-soft);padding:10px 12px;border-bottom:1px solid var(--line)}
    td{padding:14px 12px;border-bottom:1px solid var(--line);vertical-align:middle}
    .thumb{width:48px;height:48px;border-radius:12px;object-fit:cover;background:var(--sky-100)}
    .status{display:inline-block;padding:5px 11px;border-radius:99px;font-size:12px;font-weight:700;white-space:nowrap}
    .s-pending{background:#fff4d6;color:#8a5a00}.s-confirmed{background:var(--sky-100);color:var(--sky-600)}.s-preparing{background:#e8eefc;color:#3550a8}
    .s-out_for_delivery{background:var(--sky-200);color:#06527f}.s-delivered{background:#dff5ea;color:#0f6b3e}.s-cancelled{background:#fde8e8;color:#9b1c1c}
    .tracker{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin:16px 0}
    .step i{display:block;height:6px;border-radius:9px;background:#dfe9f3}.step.done i{background:linear-gradient(90deg,var(--sky-400),var(--sky-600))}
    .step span{display:block;font-size:11px;font-weight:600;color:var(--ink-soft);margin-top:6px}.step.done span{color:var(--ink)}
    .row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .muted{color:var(--ink-soft);font-size:13px}
    .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px}
    .flash{background:var(--ink);color:#fff;padding:13px 20px;border-radius:16px;font-weight:600;margin-top:24px}
    .error{background:#fde8e8;color:#9b1c1c;padding:12px 16px;border-radius:14px;margin-bottom:12px}
    .inline{display:flex;gap:8px;align-items:center}.inline select{width:auto;padding:8px 10px}
    .empty{text-align:center;padding:60px 20px;color:var(--ink-soft)}
    footer{padding:40px 0;color:var(--ink-soft);font-size:13px;text-align:center}
    @media(max-width:900px){.g2,.g4{grid-template-columns:1fr 1fr}.table-wrap{overflow-x:auto}}
    @media(max-width:600px){.g2,.g4{grid-template-columns:1fr}}
  </style>
</head>
<body>
<header>
  <div class="wrap nav">
    <a href="index.php" class="logo"><span class="mark"></span><span>Case<b>Zone</b></span></a>
    <nav class="links">
      <?= $link('index.php', 'Shop', 'shop') ?>
      <?php if ($user): ?>
        <?= $link('orders.php', 'My orders', 'orders') ?>
        <?php if ($user['role'] === 'admin') echo $link('admin.php', 'Admin', 'admin'); ?>
        <?php if ($user['role'] === 'rider') echo $link('rider.php', 'Deliveries', 'rider'); ?>
        <a class="pill" href="logout.php">Log out</a>
      <?php else: ?>
        <a class="pill" href="login.php">Log in</a><a class="btn" href="register.php">Sign up</a>
      <?php endif; ?>
      <a class="pill" href="checkout.php">Bag<span class="badge"><?= (int) $cartCount ?></span></a>
    </nav>
  </div>
</header>
<main class="wrap">
<?php if ($m = flash()): ?><div class="flash"><?= e($m) ?></div><?php endif; ?>
<?php }

function page_end(): void
{ ?>
</main>
<footer>© <?= date('Y') ?> CaseZone · Exclusive iPhone accessories · Prices in PHP ₱ · Cash on delivery</footer>
</body>
</html>
<?php }