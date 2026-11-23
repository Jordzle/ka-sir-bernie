CaseZone — iPhone Accessories Online Ordering System (PHP + MySQL)
==================================================================

FILES
-----
schema.sql       Creates the "casezone" database, 4 tables and 12 sample products
db.php           Database connection + helpers (edit username/password here)
index.php        Shop / home page (product list, categories, search, bag)
register.php     Sign up (new accounts are always "client")
login.php        Log in (admins go to admin.php, riders to rider.php)
logout.php       Log out
checkout.php     Client checkout — cash on delivery (₱79 delivery, free from ₱2,000)
orders.php       Client side — my orders, live status tracker, cancel pending orders
admin.php        Admin side — overview, orders (status + assign rider), products, users/roles
rider.php        Rider side — assigned deliveries, start delivery, mark delivered, history
layout.php       Shared header, styles and helpers for the inner pages
auth_layout.php  Shared design for the log in / sign up pages
products/        Product photos (put this folder next to index.php)

SETUP (XAMPP)
-------------
1. Start Apache and MySQL in the XAMPP Control Panel.
2. Copy this whole folder to:  C:\xampp\htdocs\casezone
3. Open http://localhost/phpmyadmin → click the "SQL" tab (no database selected)
   → paste everything from schema.sql → click "Go".
4. If your MySQL root has a password, open db.php and set DB_PASS.
5. Open http://localhost/casezone

MAKE YOURSELF ADMIN
-------------------
1. Sign up on the website with your email.
2. In phpMyAdmin → casezone → SQL tab, run:
     UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
3. Log out and log in again — you'll land on the Admin dashboard.

ADD A RIDER
-----------
Ask the rider to sign up, then in Admin → "Users & riders" change their role to Rider.

ORDER FLOW
----------
Client places order (Pending) → Admin sets Confirmed/Preparing and assigns a rider
→ Rider taps "Start delivery" (Out for delivery) → Rider taps "Mark delivered" after
collecting cash → Client sees "Delivered" in My orders.