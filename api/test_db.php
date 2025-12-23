<?php
require_once 'cors.php';
require_once 'config.php';

header("Content-Type: application/json");

$response = [
    "status" => "checking",
    "config" => [
        "host" => DB_HOST,
        "db_name" => DB_NAME,
        "user" => DB_USER,
        // "pass" => "***" // Do not expose password
    ],
    "connection" => "pending"
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $response["connection"] = "success";
    $response["message"] = "Database connection successful!";
} catch (PDOException $e) {
    $response["connection"] = "failed";
    $response["error"] = $e->getMessage();
}

echo json_encode($response);
?>
