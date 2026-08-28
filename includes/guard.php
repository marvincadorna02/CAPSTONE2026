<?php
/**
 * Single entry point for the cross-cutting request checks.
 *
 * Include this near the top of a page or endpoint, AFTER its session_start()
 * and its own auth check:
 *
 *     require_once __DIR__ . '/../includes/guard.php';
 *
 * It deliberately does NOT duplicate the per-page auth/timeout boilerplate —
 * those stay where they are so nothing regresses. This adds only the checks
 * that must apply everywhere:
 *
 *   1. schema migrations (version-gated, ~1 SELECT in steady state)
 *   2. housekeeping tick (throttled to once per 5 min, claimed atomically)
 *   3. maintenance mode
 *   4. single-active-session enforcement  (Batch 2)
 *
 * JSON endpoints are detected automatically; a caller can also force it with
 * `define('FIXIT_GUARD_JSON', true);` before the require.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/housekeeping.php';
require_once __DIR__ . '/maintenance-guard.php';

/** True when this request should answer with JSON rather than an HTML page. */
function fixit_wants_json() {
    if (defined('FIXIT_GUARD_JSON')) return (bool)FIXIT_GUARD_JSON;

    foreach (headers_list() as $h) {
        if (stripos($h, 'content-type: application/json') !== false) return true;
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (substr($dir, -4) === '/api') return true;

    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
}

(function () {
    $conn = fixit_db();
    if (!$conn) return;   // DB down: let the page surface its own error

    runMigrations($conn);

    $wantsJson = fixit_wants_json();
    fixitMaintenanceGuard($conn, $wantsJson);

    runHousekeeping($conn);
})();
