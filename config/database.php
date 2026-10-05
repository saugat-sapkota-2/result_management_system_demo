<?php
/**
 * RMS -- Database connection (PDO singleton)
 */
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('<div style="font-family:sans-serif;padding:2rem"><h2>Database connection failed</h2><p>Please make sure MySQL/MariaDB is running and the database exists.</p><p>Steps: Import <code>sql/schema.sql</code> as database <code>' . DB_NAME . '</code>, or run <code>setup/install.php</code> in the browser.</p><p><small>' . e($ex->getMessage()) . '</small></p></div>');
        }
    }
    return $pdo;
}

/** Short cut for running a prepared query and returning all rows. */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Short cut returning a single row (or null). */
function db_one(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Short cut returning a single scalar value (or null). */
function db_val(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val === false ? null : $val;
}

/** Short cut for an INSERT/UPDATE/DELETE statement. Returns rowCount. */
function db_run(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Returns the last inserted row id. */
function last_id(): int
{
    return (int) db()->lastInsertId();
}