<?php
if (!defined('DB_INCLUDED')) {
    define('DB_INCLUDED', true);

    $DB_HOST = "localhost";
    $DB_USER = "root";
    $DB_PASS = "";
    $DB_NAME = "furshield";

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        die("DB connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
}
