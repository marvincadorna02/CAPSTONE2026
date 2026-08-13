<?php

$host   = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "fixitdavao";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");