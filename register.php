<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/auth_layout.php';

if (current_user()) { header('Location: index.php'); exit; }

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) { $old[$k] = trim((string) ($_POST[$k] ?? '')); }
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    if ($old['full_name'] === '')                          $errors[] = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($old['phone'] === '')                              $errors[] = 'Mobile number is required.';
    if (strlen($password) < 8)                             $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                            $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $check = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $check->execute([$old['email']]);
        if ($check->fetchColumn()) {
            $errors[] = 'That email is already registered.';
        }
    }

    if (!$errors) {
        // Every new account is a client. The admin promotes riders.
        $stmt = db()->prepare(
            'INSERT INTO users (full_name, email, password_hash, phone, address, role) VALUES (?, ?, ?, ?, ?, "client")'
        );
        $stmt->execute([
            $old['full_name'],
            $old['email'],
            password_hash($password, PASSWORD_DEFAULT),
            $old['phone'],
            $old['address'] ?: null,
        ]);
        header('Location: login.php?registered=1');
        exit;
    }
}

auth_page_start('Sign up'); ?>
  <h1>Join the <em>sky club.</em></h1>
  <p class="sub">Create your CaseZone account in under a minute.</p>
  <?php foreach ($errors as $err): ?><div class="error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post">
    <label for="full_name">Full name</label>
    <input id="full_name" name="full_name" required value="<?= e($old['full_name']) ?>" placeholder="Juan Dela Cruz">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required value="<?= e($old['email']) ?>" placeholder="you@email.com">
    <label for="phone">Mobile number</label>
    <input id="phone" name="phone" type="tel" required value="<?= e($old['phone']) ?>" placeholder="09XX XXX XXXX">
    <label for="address">Delivery address (optional)</label>
    <input id="address" name="address" value="<?= e($old['address']) ?>" placeholder="House no., street, barangay, city">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required minlength="8" placeholder="At least 8 characters">
    <label for="confirm">Confirm password</label>
    <input id="confirm" name="confirm" type="password" required minlength="8" placeholder="Repeat password">
    <button type="submit">Create account</button>
  </form>
  <p class="alt">Already a member? <a href="login.php">Log in</a></p>
<?php auth_page_end();