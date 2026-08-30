<?php
/**
 * Shared DB connection + settings helpers.
 *
 * Every page/endpoint in this project opens its own
 * `new mysqli("localhost","root","","fixitdavao")`. New code should call
 * fixit_db() instead so credentials live in exactly one place (and can be
 * overridden from .env when this ships to a live host).
 */

require_once __DIR__ . '/../config/env.php';

if (!defined('FIXIT_DB_HOST')) {
    define('FIXIT_DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
    define('FIXIT_DB_USER', $_ENV['DB_USER'] ?? 'root');
    define('FIXIT_DB_PASS', $_ENV['DB_PASS'] ?? '');
    define('FIXIT_DB_NAME', $_ENV['DB_NAME'] ?? 'fixitdavao');
}

/**
 * Returns the request's shared mysqli handle. Opens it on first call.
 * Returns null instead of dying so a guard can degrade gracefully.
 */
function fixit_db() {
    static $conn = null;
    static $tried = false;
    if ($conn instanceof mysqli) return $conn;
    if ($tried) return null;
    $tried = true;

    // PHP 8.1+ puts mysqli in exception mode by default, so a refused
    // connection throws rather than setting connect_error.
    try {
        $c = new mysqli(FIXIT_DB_HOST, FIXIT_DB_USER, FIXIT_DB_PASS, FIXIT_DB_NAME);
        if ($c->connect_error) {
            error_log('fixit_db connect failed: ' . $c->connect_error);
            return null;
        }
    } catch (Throwable $e) {
        error_log('fixit_db connect failed: ' . $e->getMessage());
        return null;
    }

    $c->set_charset('utf8mb4');
    $conn = $c;
    return $conn;
}

// ── Exception-safe query wrappers ────────────────────────────────
// The guards below intentionally probe for tables/columns that may not exist
// yet on a not-yet-migrated database. Under mysqli's default exception mode
// that would be fatal, so route those probes through here.

/** Runs a query, returning false instead of throwing. */
function fixit_q($conn, $sql) {
    if (!$conn) return false;
    try { return $conn->query($sql); }
    catch (Throwable $e) { return false; }
}

/** Prepares a statement, returning false instead of throwing. */
function fixit_p($conn, $sql) {
    if (!$conn) return false;
    try { return $conn->prepare($sql); }
    catch (Throwable $e) { return false; }
}

/** Executes a prepared statement, returning false instead of throwing. */
function fixit_x($stmt) {
    if (!$stmt) return false;
    try { return $stmt->execute(); }
    catch (Throwable $e) { return false; }
}


function fixit_setting($conn, $key, $default = null) {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!$conn) return $default;

    $stmt = fixit_p($conn, "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) return $default;               // table not created yet
    $stmt->bind_param("s", $key);
    if (!fixit_x($stmt)) { $stmt->close(); return $default; }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cache[$key] = $row ? $row['setting_value'] : $default;
    return $cache[$key];
}

function fixit_set_setting($conn, $key, $value) {
    if (!$conn) return false;
    $stmt = fixit_p(
        $conn,
        "INSERT INTO system_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
    );
    if (!$stmt) return false;
    $val = (string)$value;
    $stmt->bind_param("ss", $key, $val);
    $ok = fixit_x($stmt);
    $stmt->close();
    return $ok;
}

// ── Schema introspection helpers (used by migrations) ────────────

function fixit_column_exists($conn, $table, $column) {
    $res = fixit_q($conn, "SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
    return $res && $res->num_rows > 0;
}

function fixit_table_exists($conn, $table) {
    $res = fixit_q($conn, "SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}

function fixit_index_exists($conn, $table, $indexName) {
    $res = fixit_q($conn, "SHOW INDEX FROM `$table` WHERE Key_name = '" . $conn->real_escape_string($indexName) . "'");
    return $res && $res->num_rows > 0;
}
