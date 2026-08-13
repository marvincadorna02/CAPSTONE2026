<?php
// ── DATABASE CONFIGURATION ──────────────────────────────────
// Usa ra ka lugar i-usab kung mag-deploy sa live server
// (halimbawa: InfinityFree, Hostinger, etc.)

$host   = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "fixitdavao";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// UTF-8 para dili mag-garbled ang special characters (₱, emojis, etc.)
$conn->set_charset("utf8mb4");