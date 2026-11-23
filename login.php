<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/auth_layout.php';

if (current_user()) { header('Location: index.php'); exit; }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        $error = 'Wrong email or password.';
    } elseif (!$row['is_active']) {
        $error = 'This account is disabled.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        // Send each role to its own side
        $go = match ($row['role']) { 'admin' => 'admin.php', 'rider' => 'rider.php', default => 'index.php' };
        header('Location: ' . $go);
        exit;
    }
}

auth_page_start('Log in'); ?>
  <h1>Welcome <em>back.</em></h1>
  <p class="sub">Log in to continue shopping.</p>
  <?php if (isset($_GET['registered'])): ?><div class="ok">Account created! You can log in now.</div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="you@email.com">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required placeholder="••••••••">
    <button type="submit">Log in</button>
  </form>
  <p class="alt">New to CaseZone? <a href="register.php">Create an account</a></p>
<?php auth_page_end();