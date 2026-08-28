<?php
/**
 * Time-based background jobs.
 *
 * There is no cron on XAMPP, so these run opportunistically on page loads —
 * the same trick the codebase already uses for
 * `UPDATE shop_subscriptions SET status='expired' ...`, except throttled to
 * once every 5 minutes instead of once per request, and claimed atomically so
 * two simultaneous visitors don't both run the batch.
 *
 * Jobs must be idempotent: they can run at any moment, in any order, and a
 * crash mid-batch means the rest run on the next tick.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notify.php';

const FIXIT_HOUSEKEEPING_INTERVAL_MIN = 5;

function runHousekeeping($conn) {
    if (!$conn) return;
    if (!fixit_claim_housekeeping_slot($conn)) return;

    foreach (fixit_housekeeping_jobs() as $name => $job) {
        try {
            $job($conn);
        } catch (Throwable $e) {
            // One bad job must not stop the others, and must never surface
            // as a broken page to whoever happened to trigger the tick.
            error_log("housekeeping job {$name} failed: " . $e->getMessage());
        }
    }
}

/**
 * Atomically claims the next run. Returns true for exactly one caller per
 * interval — the conditional UPDATE is the lock.
 */
function fixit_claim_housekeeping_slot($conn) {
    $sql = "UPDATE system_settings
            SET setting_value = DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')
            WHERE setting_key = 'housekeeping_last_run'
              AND (setting_value IS NULL
                   OR setting_value = ''
                   OR setting_value < DATE_FORMAT(
                        DATE_SUB(NOW(), INTERVAL " . FIXIT_HOUSEKEEPING_INTERVAL_MIN . " MINUTE),
                        '%Y-%m-%d %H:%i:%s'))";

    if (!fixit_q($conn, $sql)) return false;      // table not installed yet
    if ($conn->affected_rows > 0) return true;

    // Row may simply not exist yet (fresh install between migration steps).
    $res = fixit_q($conn, "SELECT 1 FROM system_settings WHERE setting_key = 'housekeeping_last_run' LIMIT 1");
    if ($res && $res->num_rows === 0) {
        return fixit_set_setting($conn, 'housekeeping_last_run', date('Y-m-d H:i:s'));
    }
    return false;
}

/**
 * The job list. Batches 2 and 3 register their jobs here:
 *   auto-cancel bookings of deactivated shops, expire unanswered requests,
 *   subscription expiry reminders + grace-period unlisting.
 */
function fixit_housekeeping_jobs() {
    return [
        'prune_notifications' => 'fixit_job_prune_notifications',
    ];
}

/** Keeps the notifications table from growing without bound. */
function fixit_job_prune_notifications($conn) {
    $conn->query("DELETE FROM notifications
                  WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
    $conn->query("DELETE FROM notifications
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
}
