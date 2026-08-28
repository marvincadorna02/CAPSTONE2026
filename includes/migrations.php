<?php
/**
 * Schema installer.
 *
 * XAMPP deployments here are "copy the folder and import the .sql", so there's
 * no migration CLI. The existing code scatters `ADD COLUMN IF NOT EXISTS` calls
 * through request handlers (suspend_user.php, cancel_booking.php, get_shops.php,
 * submit-booking.php…), which means every request pays for a pile of ALTERs.
 *
 * This consolidates all of it. A version marker in system_settings means the
 * steady-state cost is one indexed SELECT per request, and the DDL only runs
 * after the code is updated.
 *
 * To add schema: append to fixit_apply_migrations() and bump the version.
 */

require_once __DIR__ . '/db.php';

const FIXIT_SCHEMA_VERSION = 1;

function runMigrations($conn) {
    if (!$conn) return;

    // Cheap gate. Fails (and falls through) the very first time, when
    // system_settings does not exist yet.
    $res = fixit_q($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'schema_version' LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        if ((int)$row['setting_value'] >= FIXIT_SCHEMA_VERSION) return;
    }

    try {
        fixit_apply_migrations($conn);
        fixit_set_setting($conn, 'schema_version', FIXIT_SCHEMA_VERSION);
    } catch (Throwable $e) {
        // A half-applied migration retries on the next request rather than
        // taking the whole page down.
        error_log('runMigrations failed: ' . $e->getMessage());
    }
}

function fixit_apply_migrations($conn) {

    // ── v1: settings, notifications, audit log ───────────────────

    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key   VARCHAR(64) NOT NULL PRIMARY KEY,
        setting_value TEXT DEFAULT NULL,
        updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        role       ENUM('customer','repairshop','admin') NOT NULL DEFAULT 'customer',
        type       VARCHAR(48) NOT NULL,
        title      VARCHAR(160) NOT NULL,
        body       TEXT DEFAULT NULL,
        link       VARCHAR(255) DEFAULT NULL,
        ref_id     INT DEFAULT NULL,
        is_read    TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_user_read (user_id, is_read),
        KEY idx_dedup (user_id, type, ref_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS admin_audit_log (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        admin_id    INT NOT NULL,
        admin_name  VARCHAR(100) DEFAULT NULL,
        action      VARCHAR(64) NOT NULL,
        target_type VARCHAR(32) NOT NULL,
        target_id   INT DEFAULT NULL,
        details     TEXT DEFAULT NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_target (target_type, target_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Optimistic locking for concurrent admin edits needs a change stamp.
    if (!fixit_column_exists($conn, 'users', 'updated_at')) {
        $conn->query("ALTER TABLE users
            ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // Slot-conflict lookups (and the gap lock that makes them race-safe)
    // need this composite index; the dump only indexes shop_id alone.
    if (!fixit_index_exists($conn, 'bookings', 'idx_slot')) {
        $conn->query("ALTER TABLE bookings ADD KEY idx_slot (shop_id, booking_date, booking_time)");
    }

    // Defaults for the maintenance switch, so admin-settings.php has rows to read.
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
        ('maintenance_mode',   '0'),
        ('maintenance_message', 'We are performing scheduled maintenance. Please check back shortly.'),
        ('maintenance_until',  ''),
        ('housekeeping_last_run', '')");
}
