<?php
/** Shared sky-blue layout for the login and sign-up pages. */
function auth_page_start(string $title): void
{ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> — CaseZone</title>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@600;700;800;900&family=Instrument+Serif:ital@1&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--sky-50:#f2f9ff;--sky-100:#e3f2fe;--sky-200:#bfe3fd;--sky-400:#4fb6f5;--sky-500:#1e9de8;--sky-600:#0b7fc7;--ink:#0b1b33;--ink-soft:#4a5d78;--line:rgba(11,27,51,.1)}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Manrope,system-ui,sans-serif;color:var(--ink);min-height:100vh;display:grid;place-items:center;padding:24px;
      background:radial-gradient(900px 500px at 85% -10%,var(--sky-200),transparent 60%),radial-gradient(800px 500px at -10% 30%,var(--sky-100),transparent 60%),var(--sky-50)}
    .card{width:100%;max-width:440px;background:rgba(255,255,255,.7);backdrop-filter:blur(18px);border:1px solid #fff;border-radius:28px;padding:40px;box-shadow:0 30px 60px -30px rgba(11,127,199,.45)}
    .logo{font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-weight:800;font-size:24px;letter-spacing:-.03em;text-decoration:none;color:var(--ink)}
    .logo span{background:linear-gradient(120deg,var(--sky-400),var(--sky-600));-webkit-background-clip:text;color:transparent}
    h1{font-family:Unbounded,'Arial Black','Segoe UI Black',sans-serif;font-size:32px;letter-spacing:-.03em;margin:26px 0 6px}
    h1 em{font-family:'Instrument Serif';font-weight:400;color:var(--sky-500)}
    .sub{color:var(--ink-soft);font-size:14px;margin-bottom:24px}
    label{display:block;font-size:13px;font-weight:600;margin:14px 0 6px}
    input,textarea{width:100%;padding:13px 16px;border-radius:14px;border:1px solid var(--line);background:#fff;font:inherit;outline:none}
    input:focus,textarea:focus{border-color:var(--sky-400);box-shadow:0 0 0 4px var(--sky-100)}
    button{width:100%;margin-top:22px;padding:14px;border:0;border-radius:999px;font:700 15px Manrope;color:#fff;cursor:pointer;
      background:linear-gradient(135deg,var(--sky-400),var(--sky-600));box-shadow:0 16px 30px -14px var(--sky-600)}
    .alt{text-align:center;margin-top:20px;font-size:14px;color:var(--ink-soft)}
    .alt a{color:var(--sky-600);font-weight:700;text-decoration:none}
    .error{background:#fde8e8;color:#9b1c1c;padding:12px 14px;border-radius:12px;font-size:14px;margin-bottom:8px}
    .ok{background:#e3f6ec;color:#0f6b3e;padding:12px 14px;border-radius:12px;font-size:14px;margin-bottom:8px}
  </style>
</head>
<body>
<div class="card">
  <a href="index.php" class="logo">Case<span>Zone</span></a>
<?php }

function auth_page_end(): void
{ ?>
</div>
</body>
</html>
<?php }