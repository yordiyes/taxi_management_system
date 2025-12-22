<?php
// Consolidated CORS handler for all API endpoints
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-KEY");
header("Access-Control-Max-Age: 3600");
header("Content-Type: application/json; charset=UTF-8");

// Handle Preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    http_response_code(200);
    exit();
}
?>
