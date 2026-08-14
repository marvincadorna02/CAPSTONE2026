<?php
/**
 * Tiny .env loader — walay kinahanglan Composer/library.
 * I-require ni sa top sa bisan unsang PHP file nga naga-gamit
 * og credentials gikan sa .env (e.g. $_ENV['MAIL_PASSWORD']).
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        return; // .env is optional to have present in every environment
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and blank lines
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue; // malformed line, skip
        }

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        // Strip surrounding quotes if present
        $value = trim($value, "\"'");

        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}

// .env sits at the project root, one level up from /config
loadEnv(__DIR__ . '/../.env');