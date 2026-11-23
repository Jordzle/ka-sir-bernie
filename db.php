<?php
/**
 * CaseZone — database connection (PDO + MySQL)
 * Edit the credentials below to match your XAMPP / hosting setup.
 */
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'casezone';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP default is empty
const DB_PORT = 3306;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Check db.php settings.');
        }
    }
    return $pdo;
}

/** Escape output for HTML */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format a number as Philippine Peso */
function peso(float|string $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/** Currently signed-in user (or null) */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, full_name, email, role FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/** Block a page unless the user has one of the given roles */
function require_role(string ...$roles): array
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        header('Location: login.php');
        exit;
    }
    return $user;
}